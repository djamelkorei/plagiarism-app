<?php

use App\Providers\RouteServiceProvider;
use App\Models\Assignment;
use App\Models\Balance;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Enums\AssignmentStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use function Livewire\Volt\{rules, state, mount, form, layout, with, usesFileUploads, usesPagination};

layout('layouts.app');
usesPagination();
usesFileUploads();

state([
    'title' => '',
    'file' => '',
    'user_id' => '',
    'search' => '',
    'query' => '',
]);

rules([
    'title' => 'required',
    'file' => 'required|file|mimes:pdf',
]);

with(fn() => ['assignments' => function () {
    $query = Assignment::select('*');
    if (strlen($this->query) > 3) {
        $query->where('title', 'LIKE', "%{$this->query}%");
    }
    if (Auth::user()->hasRole('user')) {
        $query->where('user_id', Auth::user()->id);
    }
    return $query->orderBy('posted_at', 'desc')->paginate(10);
}]);

$searchFilter = function () {
    $this->query = $this->search;
};

$submit = function () {

    $this->validate();
    $user_id = (Auth::user()->hasRole('user')) ? Auth::user()->id : $this->user_id;

    $balance = Balance::where('user_id', $user_id)->first();
    if ($balance->credit <= 0) {
        $validator = \Illuminate\Support\Facades\Validator::make([], []);
        $validator->errors()->add('file', 'Balance credit is 0. charge it and try again.');
        throw new ValidationException($validator);
    }

    $path = $this->file->store('assignments', 's3');

    try {
        DB::beginTransaction();
        Assignment::create([
            'title' => $this->title,
            'status' => AssignmentStatus::WAITING,
            'user_id' => $user_id,
            'posted_at' => Carbon::now(),
            'file_link' => $path
        ]);
        DB::update('update balances set credit = (credit - 1), total_credit = (total_credit + 1) where user_id = ?', [$user_id]);
        DB::commit();
    } catch (Exception $exception) {
        Log::error("", [
            "code" => $exception->getCode(),
            "message" => $exception->getMessage(),
        ]);
        DB::rollBack();
    }

    $this->title = '';
    $this->file = '';
    $this->dispatch('close-modal');
};

$updated = function ($field) {
    $this->resetValidation();
};

$clean = function () {
    $this->file = "";
};

?>

<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ __('Assignments') }}
    </h2>
</x-slot>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div x-data="{ modalOpen: false }" @keydown.escape.window="modalOpen = false"
             x-on:close-modal.window="modalOpen = false">
            <div class="flex align-items-center mb-4 gap-4 justify-between">

                @can('users.index')
                    <x-link-button href="{{ route('dashboard') }}">Dashboard</x-link-button>
                @elsecannot('users.index')
                    <x-primary-button @click="modalOpen=true">Create</x-primary-button>
                @endcan

                <div class="flex gap-1 align-items-center w-[30%] relative">
                    <x-text-input wire:model="search" wire:keydown.debounce.400ms="searchFilter" id="title"
                                  class="block w-full" type="text" name="title" placeholder="search..."/>
                    <svg class="h-5 w-5 absolute right-2 top-0 bottom-0 m-auto text-gray-600"
                         xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <g xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" d="M20 20l-6-6"/>
                            <path d="M15 9.5a5.5 5.5 0 11-11 0 5.5 5.5 0 0111 0z"/>
                        </g>
                    </svg>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="h-[525px] mb-3">
                        <table>
                            <thead>
                            <tr>
                                @can('users.index')
                                    <th>USER</th>
                                @endcan
                                <th>TITLE</th>
                                <th class="w-[200px]">POSTED DATE</th>
                                <th class="w-[200px]">ISSUED DATE</th>
                                <th class="text-center">STATUS</th>
                                <th>ACTIONS</th>
                            </tr>
                            </thead>
                            <tbody class="relative">

                            @foreach($assignments as $row)
                                <tr>
                                    @can('users.index')
                                        <td class="md:w-[300px]">
                                            <a href="#"
                                               class="capitalize font-bold underline text-indigo-800">{{ $row->user->name }}</a>
                                        </td>
                                    @endcan
                                    <td class="md:w-[300px]">{{ $row->title }}</td>
                                    <td>{{ $row->posted_at->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td>{{ isset($row) ? '-' : $row->issued_at->format('Y-m-d H:i')}}</td>
                                    <td>
                                        <div class="inline-flex align-items-center justify-center px-3 py-2 rounded-md
                                            {{ $row->status === AssignmentStatus::COMPLETED ? 'bg-emerald-300' : 'bg-orange-300'  }} font-weight-bolder text-xs">
                                            {{ $row->status }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex justify-center align-items-center">
                                            @if($row->status  === AssignmentStatus::COMPLETED)
                                                <x-link-button href="#" class="gap-1">
                                                    <span>download</span>
                                                    <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none"
                                                         stroke="currentColor" viewBox="0 0 24 24">
                                                        <path xmlns="http://www.w3.org/2000/svg" stroke="currentColor"
                                                              stroke-linecap="round" stroke-linejoin="round"
                                                              stroke-width="2"
                                                              d="M12 11.5V20m0 0l3-3m-3 3l-3-3M8 7.036a3.484 3.484 0 011.975.99M17.5 14c1.519 0 2.5-1.231 2.5-2.75a2.75 2.75 0 00-2.016-2.65A5 5 0 008.37 7.108a3.5 3.5 0 00-1.87 6.746"/>
                                                    </svg>
                                                </x-link-button>
                                            @else
                                                <span class="text-gray-500 w-[135px]">not available</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{ $assignments->links() }}
                </div>
            </div>

            <template x-teleport="body">
                <div x-show="modalOpen"
                     class="fixed top-0 left-0 z-[99] flex items-center justify-center w-screen h-screen" x-cloak>
                    <div x-show="modalOpen"
                         x-transition:enter="ease-out duration-300"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="ease-in duration-300"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         @click="modalOpen=false" class="absolute inset-0 w-full h-full bg-black bg-opacity-40"></div>
                    <div x-show="modalOpen"
                         x-trap.inert.noscroll="modalOpen"
                         x-transition:enter="ease-out duration-300"
                         x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                         x-transition:leave="ease-in duration-200"
                         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                         class="relative w-full py-6 bg-white px-7 sm:max-w-lg sm:rounded-lg">
                        <div class="flex items-center justify-between pb-2">
                            <h3 class="text-lg font-semibold">Create new assignment</h3>
                            <button @click="modalOpen=false"
                                    class="absolute top-0 right-0 flex items-center justify-center w-8 h-8 mt-5 mr-5 text-gray-600 rounded-full hover:text-gray-800 hover:bg-gray-50">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                     stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <div class="relative w-auto">
                            <form wire:submit="submit" id="post-assignment">


                                @can('users.index')
                                    <!-- Users -->
                                    <div class="mb-4">
                                        <x-input-label for="email" :value="__('User')" class="mb-2"/>
                                        <x-text-input placeholder="select a user" wire:model="user_id" id="user_id"
                                                      class="block mt-1 w-full" type="text" name="user_id" required
                                                      autofocus/>
                                        <x-input-error :messages="$errors->get('User')" class="mt-2"/>
                                    </div>
                                @endcan

                                <!-- Title -->
                                <div class="mb-4">
                                    <x-input-label for="email" :value="__('Title')" class="mb-2"/>
                                    <x-text-input placeholder="Enter a title" wire:model="title" id="title"
                                                  class="block mt-1 w-full" type="text" name="title" required
                                                  autofocus/>
                                    <x-input-error :messages="$errors->get('title')" class="mt-2"/>
                                </div>

                                <!-- Title -->
                                <div class="mb-4">
                                    <x-input-label for="email" :value="__('Document')" class="mb-2"/>
                                    <div class="flex items-center justify-center w-full">
                                        <label for="file"
                                               class="flex flex-col items-center justify-center w-full h-48 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer hover:bg-indigo-50 ">
                                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                                <svg class="w-8 h-8 mb-4" aria-hidden="true"
                                                     xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 16">
                                                    <path stroke="currentColor" stroke-linecap="round"
                                                          stroke-linejoin="round" stroke-width="2"
                                                          d="M13 13h3a3 3 0 0 0 0-6h-.025A5.56 5.56 0 0 0 16 6.5 5.5 5.5 0 0 0 5.207 5.021C5.137 5.017 5.071 5 5 5a4 4 0 0 0 0 8h2.167M10 15V6m0 0L8 8m2-2 2 2"/>
                                                </svg>
                                                <p class="mb-2 text-sm"><span
                                                        class="font-semibold">Click to upload</span> or drag and drop
                                                </p>
                                                <p class="text-xs">PDF Files</p>
                                            </div>
                                            <input wire:model="file" id="file" type="file" class="hidden"/>
                                        </label>
                                    </div>
                                    @if(isset($file) && $file  != '')
                                        <div
                                            class="flex items-center justify-between py-2 mt-2 rounded-md bg-gray-25 mb-4">
                                            <div class="flex items-center gap-1">
                                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                                                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13"/>
                                                </svg>
                                                <span class="text-gray-600">file uploaded</span>
                                            </div>
                                            <button wire:click="clean"
                                                    class="inline-flex items-center px-2 py-1 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 active:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                                delete
                                            </button>
                                        </div>
                                    @endif

                                    <x-input-error :messages="$errors->get('file')" class="mt-2"/>
                                </div>

                                <div class="flex items-center justify-end mt-4">
                                    <x-primary-button class="ms-3">
                                        {{ __('Submit') }}
                                    </x-primary-button>
                                </div>

                            </form>
                        </div>
                    </div>
                </div>
            </template>

        </div>
    </div>
</div>
