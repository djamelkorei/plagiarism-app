<?php

use App\Providers\RouteServiceProvider;
use App\Models\Assignment;
use App\Models\Balance;
use App\Models\Enums\AssignmentStatus;

use Carbon\Carbon;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use function Livewire\Volt\{rules, state, mount, form, layout, with, usesFileUploads, usesPagination};

layout('layouts.app');
usesPagination();
usesFileUploads();

state([
    // Form
    'title' => '',
    'file' => '',
    // Others
    'search' => '',
    'modalEvent' => 'close-modal-create-assignment'
]);

rules([
    'title' => 'required',
    'file' => 'required|file|mimes:pdf',
]);

with(fn() => ['assignments' => function () {
    $query = Assignment::select('*');
    if (strlen($this->search) > 3) {
        $query->where('title', 'LIKE', "%{$this->search}%");
    }
    if (Auth::user()->hasRole('user')) {
        $query->where('user_id', Auth::user()->id);
    }
    return $query->orderBy('posted_at', 'desc')->paginate(10);
}]);

/**
 * Submit Form
 *
 * @return void
 */
$submit = function () {

    $this->validate();
    $user_id = Auth::user()->id;

    $balance = Balance::where('user_id', $user_id)->first();
    if ($balance->credit <= 0) {
        $validator = Validator::make([], []);
        $validator->errors()->add('file', 'Balance credit is 0. charge it and try again.');
        throw new ValidationException($validator);
    }

    $path = $this->file->store('assignments', 's3');

    try {
        DB::beginTransaction();
        Assignment::create([
            'title' => $this->title,
            'status' => AssignmentStatus::PENDING,
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

    $this->reset('title', 'file');
    $this->dispatch($this->modalEvent);
};


$resetFile = function () {
    $this->reset('file');
};

?>

<x-slot:header>
    {{ __('Assignments') }}
</x-slot:header>

<x-container x-data="{ modalCreateAssignment: false }">

    <div class="flex align-items-center mb-4 gap-4 justify-between">

        @can('users.index')
            <x-link-button href="{{ route('dashboard') }}">Dashboard</x-link-button>
        @elsecannot('users.index')
            <x-primary-button @click="modalOpen=true">Create</x-primary-button>
        @endcan

        <x-filter-search-input model="search" class="w-[30%]"/>
    </div>

    <x-card>
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
                            <x-badge class="{{ $row->status === AssignmentStatus::COMPLETED ? 'bg-emerald-300' : 'bg-orange-300'  }}">
                                {{ $row->status }}
                            </x-badge>
                        </td>
                        <td>
                            <div class="flex justify-center align-items-center">
                                @if($row->status  === AssignmentStatus::COMPLETED)
                                    <x-link-button
                                        href="{{ route('assignments.download', ['assignmentId' => $row->id]) }}"
                                        class="gap-1">
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
    </x-card>

    <x-modal name="modalCreateAssignment" :event="$modalEvent" title="Create new assignment">
        <form wire:submit="submit">

            <div class="mb-4">
                <!-- Title -->
                <div class="mb-4">
                    <x-input-label for="email" :value="__('Title')" class="mb-2"/>
                    <x-text-input placeholder="Enter a title"
                                  wire:model="title"
                                  id="title"
                                  class="block mt-1 w-full"
                                  type="text"
                                  name="title"
                                  required
                                  autofocus/>
                    <x-input-error :messages="$errors->get('title')" class="mt-2"/>
                </div>

                <!-- File -->
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
                            <button wire:click="resetFile"
                                    class="inline-flex items-center px-2 py-1 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 active:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                delete
                            </button>
                        </div>
                    @endif
                    <x-input-error :messages="$errors->get('file')" class="mt-2"/>
                </div>
            </div>

            <div class="flex items-center justify-end">
                <x-primary-button class="ms-3">
                    {{ __('Submit') }}
                </x-primary-button>
            </div>

        </form>
    </x-modal>

</x-container>>
