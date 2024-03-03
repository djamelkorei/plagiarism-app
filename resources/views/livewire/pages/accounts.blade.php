<?php

use App\Models\User;
use App\Models\Account;
use App\Models\Enums\AttributionStatus;
use App\Models\Enums\AccountStatus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use function Livewire\Volt\{layout, rules, state,mount, form, with, usesFileUploads, usesPagination};

layout('layouts.app');
usesPagination();

state([
    'email' => '',
    'password' => '',
    'search' => '',
    'query' => '',
]);

rules([
    'email' => 'required|email',
    'password' => 'required',
]);

with( fn () => ['accounts' => function () {
    $query = Account::select('*');
    if(strlen($this->query) > 3) {
        $query->where('accounts.email', 'LIKE', "%{$this->query}%");
    }
    return $query->orderBy('accounts.created_at', 'desc')->paginate(10);
}]);

$searchFilter = function () {
    $this->query = $this->search;
};

$submit = function () {

    $validated = $this->validate();
    $validated['status'] = AccountStatus::PENDING;
    $account = Account::create($validated);

    $this->email = '';
    $this->passowrd = '';
    $this->dispatch('close-modal');
};

$handlePending = function ($id)  {
    $account = Account::find($id);
    $account->status = AccountStatus::PENDING;
    $account->save();
};

$handleSuspend = function ($id)  {
    try {
        DB::beginTransaction();
        $account = Account::find($id);
        $account->status = AccountStatus::SUSPENDED;
        $account->save();
        DB::table('attributions')
            ->where('account_id', $id)
            ->update(array('status' => AttributionStatus::SUSPENDED));
        DB::commit();
    } catch (Exception $exception) {
        Log::error("", [
            "code" => $exception->getCode(),
            "message" => $exception->getMessage(),
        ]);
        DB::rollBack();
    }
};

?>

<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ __('Accounts') }}
    </h2>
</x-slot>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div x-data="{ modalOpen: false }" @keydown.escape.window="modalOpen = false" x-on:close-modal.window="modalOpen = false">
            <div class="flex align-items-center mb-4 gap-4 justify-between">

                <x-primary-button @click="modalOpen=true">Create</x-primary-button>

                <div class="flex gap-1 align-items-center w-[30%] relative">
                    <x-text-input wire:model="search" wire:keydown.debounce.400ms="searchFilter" id="title" class="block w-full" type="text" name="title" placeholder="search..."/>
                    <svg class="h-5 w-5 absolute right-2 top-0 bottom-0 m-auto text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" viewBox="0 0 24 24" ><g xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M20 20l-6-6"/><path d="M15 9.5a5.5 5.5 0 11-11 0 5.5 5.5 0 0111 0z"/></g></svg>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="h-[525px] mb-3">
                        <table>
                            <thead>
                            <tr>
                                <th>EMAIL</th>
                                <th>PASSWORD</th>
                                <th>STATUS</th>
                                <th class="text-right">ACTIONS</th>
                            </tr>
                            </thead>
                            <tbody class="relative">
                            @foreach($accounts as $row)
                                <tr>
                                    <td>{{ $row->email }}</td>
                                    <td>{{ $row->password }}</td>
                                    <td>
                                        <div class="inline-flex align-items-center justify-center px-3 py-2 rounded-md
                                            {{ $row->status === AccountStatus::ACTIVE  ? 'bg-emerald-300' : ($row->status === AccountStatus::PENDING ? 'bg-orange-300' : 'bg-red-300') }} font-weight-bolder text-xs">
                                            {{ $row->status === AccountStatus::ACTIVE ? 'active' : ($row->status === AccountStatus::PENDING ? 'pending' : 'suspended') }}
                                        </div>
                                    </td>
                                    <td>
                                        @if($row->status === AccountStatus::SUSPENDED)
                                            <div class="flex justify-end align-items-center">
                                                <x-danger-button href="#" class="gap-1 " wire:click="handlePending({{ $row->id }})">
                                                    process now
                                                </x-danger-button>
                                            </div>
                                        @endif
                                        @if($row->status !== AccountStatus::SUSPENDED)
                                            <div class="flex justify-end align-items-center">
                                                <x-primary-button href="#" class="gap-1" wire:click="handleSuspend({{ $row->id }})">
                                                    suspend
                                                </x-primary-button>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{ $accounts->links() }}
                </div>
            </div>

            <template x-teleport="body">
                <div x-show="modalOpen" class="fixed top-0 left-0 z-[99] flex items-center justify-center w-screen h-screen" x-cloak>
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
                            <h3 class="text-lg font-semibold">Create new user</h3>
                            <button @click="modalOpen=false" class="absolute top-0 right-0 flex items-center justify-center w-8 h-8 mt-5 mr-5 text-gray-600 rounded-full hover:text-gray-800 hover:bg-gray-50">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <div class="relative w-auto">
                            <form wire:submit="submit" id="post-account">

                                <!-- Email -->
                                <div class="mb-4">
                                    <x-input-label for="text" :value="__('Email')"  class="mb-2"/>
                                    <x-text-input placeholder="Enter a email" wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autofocus/>
                                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                                </div>

                                <!-- Name -->
                                <div class="mb-4">
                                    <x-input-label for="text" :value="__('Password')"  class="mb-2"/>
                                    <x-text-input placeholder="Enter a password" wire:model="password" id="password" class="block mt-1 w-full" type="text" name="password" required autofocus/>
                                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
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
