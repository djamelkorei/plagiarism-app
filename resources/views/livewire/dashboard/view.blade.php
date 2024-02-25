<?php

use App\Providers\RouteServiceProvider;
use App\Models\Assignment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use function Livewire\Volt\{rules, state, mount, form, layout, with, usesFileUploads, usesPagination};

state([
    'total_credit' => 0,
    'total_files' => 0,
    'total_users' => 0,

    'current_balance' => 0
]);

mount(function () {

    if (Auth::user()->hasRole('user')) {
        $balance = \App\Models\Balance::where('user_id', Auth::user()->id)->first();
        $this->current_balance = $balance->credit;
        $this->total_credit = $balance->credit;
        $this->total_files = \App\Models\Assignment::where('user_id', Auth::user()->id)->count();
    }

    if (Auth::user()->can('users.index')) {
        $this->total_users = \App\Models\User::all()->count();
        $this->total_credit = \App\Models\Balance::sum('total_value');
        $this->total_files = \App\Models\Assignment::all()->count();
    }

});

?>

<div>
    <div class="flex align-items-center mb-4 gap-4 justify-between h-[42px]">
        <x-link-button href="{{ route('assignments.index') }}">Assignments</x-link-button>
    </div>

    <div class="grid gap-4 md:grid-cols-2 @can('users.index') lg:grid-cols-3 @endcan mb-4">
        @can('users.index')
            <div class="rounded-md border bg-card text-card-foreground shadow-sm bg-white">
                <div class="p-6 flex flex-row items-start justify-between space-y-0 pb-2"><h3
                        class="tracking-tight text-sm font-medium">Users</h3>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                         class="h-8 w-8 text-muted-foreground">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
                <div class="p-6 pt-0">
                    <div class="text-xl font-bold">{{ $total_users }}</div>
                    {{--                <p class="text-xs text-muted-foreground">+180.1% from last month</p>--}}
                </div>
            </div>
        @endcan

        <div class="rounded-md border bg-card text-card-foreground shadow-sm bg-white">
            <div class="p-6 flex flex-row items-start justify-between space-y-0 pb-2"><h3
                    class="tracking-tight text-sm font-medium">
                    @can('users.index') Revnue @elsecannot('users.index') Credit  @endcan
                </h3>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                     class="h-8 w-8 text-muted-foreground">
                    <rect width="20" height="14" x="2" y="5" rx="2"></rect>
                    <path d="M2 10h20"></path>
                </svg>
            </div>
            <div class="p-6 pt-0">
                <div class="text-xl font-bold">
                    @can('users.index')
                        DZD {{ number_format($total_credit, 2)  }}
                    @elsecannot('users.index')
                        {{ $total_credit }}
                    @endcan
                </div>
                {{--                <p class="text-xs text-muted-foreground">+19% from last month</p>--}}
            </div>
        </div>


        <div class="rounded-md border bg-card text-card-foreground shadow-sm bg-white">
            <div class="p-6 flex flex-row items-start justify-between space-y-0 pb-2"><h3
                    class="tracking-tight text-sm font-medium">Assignements</h3>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                     class="h-8 w-8 text-muted-foreground">
                    <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
                </svg>
            </div>
            <div class="p-6 pt-0">
                <div class="text-xl font-bold">{{ $total_files }}</div>
                {{--                <p class="text-xs text-muted-foreground">+201 since last hour</p>--}}
            </div>
        </div>
    </div>


    @role('user')

    <div  x-data="{ modalOpen: false }" @keydown.escape.window="modalOpen = false" x-on:close-modal.window="modalOpen = false">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-10 text-gray-900 flex flex-col gap-4 items-center justify-center">
                <svg   class="w-12 h-12 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.182 16.318A4.486 4.486 0 0 0 12.016 15a4.486 4.486 0 0 0-3.198 1.318M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Z" />
                </svg>
                <p class="text-center"> <span class="text-lg">Your current balance is <b>"0"</b> </span><br/> <span class="text-md text-gray-600">charge your balance in order to create new assignments</span></p>
                <x-primary-button @click="modalOpen=true">charge now</x-primary-button>
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
                        <h3 class="text-lg font-semibold">Create new assignment</h3>
                        <button @click="modalOpen=false" class="absolute top-0 right-0 flex items-center justify-center w-8 h-8 mt-5 mr-5 text-gray-600 rounded-full hover:text-gray-800 hover:bg-gray-50">
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <div class="relative w-auto">
                        <form wire:submit="submit" id="post-assignment">

                            <!-- Title -->
                            <div class="mb-4">
                                <x-input-label for="email" :value="__('Title')"  class="mb-2"/>
                                <x-text-input placeholder="Enter a title" wire:model="title" id="title" class="block mt-1 w-full" type="text" name="title" required autofocus/>
                                <x-input-error :messages="$errors->get('title')" class="mt-2" />
                            </div>

                            <!-- Title -->
                            <div class="mb-4">
                                <x-input-label for="email" :value="__('Document')" class="mb-2"/>
                                <div class="flex items-center justify-center w-full">
                                    <label for="file" class="flex flex-col items-center justify-center w-full h-48 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer hover:bg-indigo-50 ">
                                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                            <svg class="w-8 h-8 mb-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 16">
                                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 13h3a3 3 0 0 0 0-6h-.025A5.56 5.56 0 0 0 16 6.5 5.5 5.5 0 0 0 5.207 5.021C5.137 5.017 5.071 5 5 5a4 4 0 0 0 0 8h2.167M10 15V6m0 0L8 8m2-2 2 2"/>
                                            </svg>
                                            <p class="mb-2 text-sm"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                                            <p class="text-xs">PDF Files</p>
                                        </div>
                                        <input wire:model="file" id="file" type="file" class="hidden" />
                                    </label>
                                </div>
                                @if(isset($file) && $file  != '')
                                    <div class="flex items-center justify-between py-2 mt-2 rounded-md bg-gray-25 mb-4">
                                        <div class="flex items-center gap-1">
                                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" >
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" />
                                            </svg>
                                            <span class="text-gray-600">file uploaded</span>
                                        </div>
                                        <button wire:click="clean" class="inline-flex items-center px-2 py-1 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 active:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                            delete
                                        </button>
                                    </div>
                                @endif

                                <x-input-error :messages="$errors->get('file')" class="mt-2" />
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


    @endrole

</div>

