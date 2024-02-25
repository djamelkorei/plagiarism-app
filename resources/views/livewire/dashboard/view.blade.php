<?php

use App\Providers\RouteServiceProvider;
use App\Models\Assignment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use function Livewire\Volt\{rules, state, mount, form, layout, with, usesPagination};

usesPagination();

state([
    'total_credit' => 0,
    'total_files' => 0,
    'total_users' => 0,
    'current_balance' => 0,
    'search' => '',
    'query' => '',
    'pack' => '1',
    'range' => '0',
    'current_id' => null,
    'current_user' => null,
    'current_value' => null,
    'current_credit' => null,
    'current_date' => null,
    'current_balance_id' => null
]);

with( fn () => ['assignments' => function () {
    $query = \App\Models\BalanceLine::selectRaw('balance_lines.*, users.id as user_id, users.name as user_name, users.email as user_email')
        ->leftJoin('balances', 'balances.id', '=', 'balance_lines.balance_id')
        ->leftJoin('users', 'users.id', '=', 'balances.user_id');

    if( strlen($this->query) > 3) {
        $query->where('users.name', 'LIKE', "%{$this->query}%");
    }

    if (Auth::user()->hasRole('user')) {
        $query->where('user_id', Auth::user()->id);
        $query->where('status', \App\Models\Enums\BalanceLineStatus::PENDING);
    }

    return $query->orderBy('status', 'asc')->orderBy('created_at', 'desc')->paginate(10);
}]);

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

$submitApprove = function () {

     try {
         \Illuminate\Support\Facades\DB::beginTransaction();
         \Illuminate\Support\Facades\DB::update( 'update balance_lines set credit = ?, value = ?, status = ? where id = ?',
             [$this->current_credit, $this->current_value, \App\Models\Enums\BalanceLineStatus::APPROVED, $this->current_id]);

         \Illuminate\Support\Facades\DB::update( 'update balances set credit = (credit + ?), total_value = (total_value + ?) where id = ?',
             [$this->current_credit, $this->current_value, $this->current_balance_id]);
         \Illuminate\Support\Facades\DB::commit();
     } catch (Exception $exception) {
         \Illuminate\Support\Facades\Log::error("", [
             "code" => $exception->getCode(),
             "message" => $exception->getMessage(),
         ]);
         \Illuminate\Support\Facades\DB::rollBack();
     }

    $this->current_id = null;
    $this->current_user = null;
    $this->current_value = null;
    $this->current_credit = null;
    $this->current_date = null;
    $this->current_balance_id = null;
    $this->dispatch('close-modal');
};

$submit = function () {

    if($this->pack == null || !in_array($this->pack, ['1','2','3','4'])) {
        $validator = \Illuminate\Support\Facades\Validator::make([], []);
        $validator->errors()->add('pack', 'Please select a pack');
        throw new ValidationException($validator);
    }

    if($this->pack == '4' && (!is_numeric($this->range) ||  ((int)$this->range) <= 0 || ((int)$this->range) > 100 )) {
        $validator = \Illuminate\Support\Facades\Validator::make([], []);
        $validator->errors()->add('range', 'Please select a proper range');
        throw new ValidationException($validator);
    }

    $balance = \App\Models\Balance::where('user_id', Auth::user()->id)->first();
    $credit = 0;
    $pack = 0;

    if($this->pack == '1') {
        $credit = 20;
        $pack = 2000;
    } else if($this->pack == '2') {
        $credit = 50;
        $pack = 4000;
    } else if($this->pack == '3') {
        $credit = 75;
        $pack = 6000;
    } else if($this->pack == '4') {
        $credit = $this->range;
    }

    \App\Models\BalanceLine::create([
        'credit' =>  $credit,
        'value' =>  $pack,
        'status' => \App\Models\Enums\BalanceLineStatus::PENDING,
        'balance_id' => $balance->id
    ]);

    $this->pack = '1';
    $this->range = '0';
    $this->dispatch('close-modal');
};

$searchFilter = function () {
    $this->query = $this->search;
};


$beforeOpenModelApprove = function ($id, $user, $value, $credit, $date, $balance_id) {
    $this->current_id = $id;
    $this->current_user = $user;
    $this->current_value = $value;
    $this->current_credit = $credit;
    $this->current_date = $date;
    $this->current_balance_id = $balance_id;
    $this->dispatch('open-modal');
};

?>

<div  x-data="{ modalOpen: false, modalApproveOpen: false, pack: 0 }" @keydown.escape.window="modalOpen = false; modalApproveOpen = false" x-on:close-modal.window="modalOpen = false; modalApproveOpen = false" x-on:open-modal.window="modalApproveOpen = true">

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
                    class="tracking-tight text-sm font-medium">Assignments</h3>
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
    <div class="mb-4">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 flex flex-col gap-3 items-center justify-center">
                <svg   class="w-12 h-12 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.182 16.318A4.486 4.486 0 0 0 12.016 15a4.486 4.486 0 0 0-3.198 1.318M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Z" />
                </svg>
                <p class="text-center"> <span class="text-lg">Your current balance is <b>"0"</b> </span><br/> <span class="text-md text-gray-600">charge your balance in order to create new assignments</span></p>
            </div>
        </div>
    </div>
    @endrole

    <div class="flex align-items-center mb-4 gap-4 justify-between">

        @hasrole('user')
        <x-primary-button @click="modalOpen=true" class="bg-indigo-600 hover:bg-indigo-500 focus:bg-indigo-700 active:bg-indigo-700 focus:ring-indigo-700">
            <svg class="w-6 h-6 me-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
            </svg>
            <span>top up balance</span>
        </x-primary-button>
        @endhasrole

        @can('users.index')
        <div class="flex gap-1 align-items-center w-[30%] relative">
            <x-text-input wire:model="search" wire:keydown.debounce.400ms="searchFilter" id="title" class="block w-full" type="text" name="title" placeholder="search..."/>
            <svg class="h-5 w-5 absolute right-2 top-0 bottom-0 m-auto text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" viewBox="0 0 24 24" ><g xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M20 20l-6-6"/><path d="M15 9.5a5.5 5.5 0 11-11 0 5.5 5.5 0 0111 0z"/></g></svg>
        </div>
        @endcan
    </div>
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <div class="h-[525px] mb-3">
                <table>
                    <thead>
                    <tr>
                        @can('users.index')
                            <th >USER</th>
                        @endcan
                        <th class="w-[200px]">CREDIT</th>
                        <th class="w-[200px]">VALUE</th>
                        <th class="w-[200px]">DATE</th>
                        <th class="w-[100px]">STATUS</th>
                        <th >ACTIONS</th>
                    </tr>
                    </thead>
                    <tbody class="relative">

                    @foreach($assignments as $row)
                        <tr>
                            @can('users.index')
                                <td class="md:w-[300px]">
                                    <a href="#" class="capitalize font-bold underline text-indigo-800">{{ $row->user_name }}</a>
                                </td>
                            @endcan
                            <td class="md:w-[300px]">{{ $row->credit }}</td>
                            <td class="md:w-[300px]"> <b>{{ number_format($row->value, 2)  }}</b></td>
                            <td>{{ $row->created_at->format('Y-m-d H:i') ?? '-' }}</td>
                            <td>
                                <div class="inline-flex align-items-center justify-center px-3 py-2 rounded-md
                                {{ $row->status === \App\Models\Enums\BalanceLineStatus::APPROVED ? 'bg-emerald-300' : 'bg-orange-300'  }} font-weight-bolder text-xs">
                                    {{ $row->status }}
                                </div>
                            </td>
                            <td>
                                <div class="flex">
                                    @if($row->status  === \App\Models\Enums\BalanceLineStatus::APPROVED)
                                        <span class="text-gray-500 w-[135px]">- claimed</span>
                                    @else
                                        <x-primary-button wire:click="beforeOpenModelApprove({{ $row->id . ',\''. $row->user_name . '\','. $row->value . ','. $row->credit . ',\''. $row->created_at->format('Y-m-d H:i') .'\','.  $row->balance_id }})" class="gap-1">
                                            <span>approve</span>
                                        </x-primary-button>
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

    @hasrole('super-admin')
    <template x-teleport="body">
        <div x-show="modalApproveOpen" class="fixed top-0 left-0 z-[99] flex items-center justify-center w-screen h-screen" x-cloak>
            <div x-show="modalApproveOpen"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-300"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="modalApproveOpen=false" class="absolute inset-0 w-full h-full bg-black bg-opacity-40"></div>
            <div x-show="modalApproveOpen"
                 x-trap.inert.noscroll="modalApproveOpen"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="relative w-full py-6 bg-white px-7 sm:max-w-lg sm:rounded-lg">
                <div class="flex items-center justify-between pb-2">
                    <h3 class="text-lg font-semibold">Approve request</h3>
                    <button @click="modalApproveOpen=false" class="absolute top-0 right-0 flex items-center justify-center w-8 h-8 mt-5 mr-5 text-gray-600 rounded-full hover:text-gray-800 hover:bg-gray-50">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="relative w-auto">
                    <form wire:submit="submitApprove" class="pt-4">

                        <div class="flex gap-4 rounded-md p-3 bg-gray-100  mb-4">
                            <div class="text-md capitalize ">#{{ $current_id }}</div>
                            <span>-</span>
                            <div class="text-md capitalize ">{{ $current_user }}</div>
                            <span>-</span>
                            <div class="text-md capitalize opacity-75">{{ $current_date }}</div>
                        </div>

                        <!-- Credit -->
                        <div class="mb-4">
                            <x-input-label for="email" :value="__('Credit')"  class="mb-2"/>
                            <x-text-input placeholder="Enter a credit" wire:model="current_credit" id="credit" class="block mt-1 w-full" type="number" name="credit" required/>
                            <x-input-error :messages="$errors->get('current_value')" class="mt-2" />
                        </div>

                        <!-- Value -->
                        <div class="mb-4">
                            <x-input-label for="email" :value="__('Payment')"  class="mb-2"/>
                            <x-text-input placeholder="Enter a value" wire:model="current_value" id="credit" class="block mt-1 w-full" type="number" name="value" required/>
                            <x-input-error :messages="$errors->get('current_value')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-between gap-4">
                            <label for="current_type_yes" class="flex-1 cursor-pointer flex items-start p-4 space-x-3 bg-indigo-50 border rounded-md shadow-sm hover:bg-indigo-100 border-neutral-200/70">
                                <input wire:model.live="current_type" id="current_type_yes" type="radio" name="current_type" value="yes" class="text-gray-900 translate-y-px focus:ring-gray-700" />
                                <span class="relative flex flex-col text-left space-y-1.5 leading-none">
                                <span class="font-semibold">Accept</span>
                                </span>
                            </label>
                            <label for="current_type_no" class="flex-1 cursor-pointer flex items-start p-4 space-x-3 bg-red-50 border rounded-md shadow-sm hover:bg-red-100 border-neutral-200/70">
                                <input wire:model.live="current_type" id="current_type_no" type="radio" name="current_type" value="yes" class="text-gray-900 translate-y-px focus:ring-gray-700" />
                                <span class="relative flex flex-col text-left space-y-1.5 leading-none">
                                <span class="font-semibold">Refuse</span>
                                </span>
                            </label>
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
    @endhasrole

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
                    <h3 class="text-lg font-semibold">Top up balance</h3>
                    <button @click="modalOpen=false" class="absolute top-0 right-0 flex items-center justify-center w-8 h-8 mt-5 mr-5 text-gray-600 rounded-full hover:text-gray-800 hover:bg-gray-50">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="relative w-auto">
                    <form wire:submit="submit" class="pt-4">

                        <div class="flex flex-col gap-2 mb-4">
                            <label for="pack1" class="cursor-pointer flex items-start p-5 space-x-3 bg-white border rounded-md shadow-sm hover:bg-indigo-50 border-neutral-200/70">
                                <input wire:model.live="pack" id="pack1" type="radio" name="pack" value="1" class="text-gray-900 translate-y-px focus:ring-gray-700" />
                                <span class="relative flex flex-col text-left space-y-1.5 leading-none">
                                <span class="font-semibold">Pack 1 - <span class='font-black font-mono text-pink-600'>2000 DZD</span></span>
                                <span class="text-sm opacity-80"><span class='font-black'>20 documents</span> included</span>
                            </span>
                            </label>
                            <label for="pack2" class="cursor-pointer flex items-start p-5 space-x-3 bg-white border rounded-md shadow-sm hover:bg-indigo-50 border-neutral-200/70">
                                <input wire:model.live="pack" id="pack2" type="radio" name="pack" value="2" class="text-gray-900 translate-y-px focus:ring-gray-700" />
                                <span class="relative flex flex-col text-left space-y-1.5 leading-none">
                                <span class="font-semibold">Pack 2 - <span class='font-black font-mono text-pink-600'>4000 DZD</span></span>
                                <span class="text-sm opacity-80"><span class='font-black'>50 documents</span> included</span>
                            </span>
                            </label>
                            <label for="pack3" class="cursor-pointer flex items-start p-5 space-x-3 bg-white border rounded-md shadow-sm hover:bg-indigo-50 border-neutral-200/70">
                                <input wire:model.live="pack" id="pack3" type="radio" name="pack" value="3" class="text-gray-900 translate-y-px focus:ring-gray-700" />
                                <span class="relative flex flex-col text-left space-y-1.5 leading-none">
                                 <span class="font-semibold">Pack 3 - <span class='font-black font-mono text-pink-600'>6000 DZD</span></span>
                                 <span class="text-sm opacity-80"><span class='font-black'>75 documents</span> included</span>
                                </span>
                            </label>
                            <label for="pack4" class="cursor-pointer flex items-start p-5 space-x-3 bg-white border rounded-md shadow-sm hover:bg-indigo-50 border-neutral-200/70">
                                <input wire:model.live="pack" id="pack4" type="radio" name="pack" value="4" class="text-gray-900 translate-y-px focus:ring-gray-700" />
                                <span class="relative flex flex-col text-left space-y-1.5 leading-none">
                                 <span class="font-semibold">Pack 4 - <span class='font-black font-mono text-pink-600'>custom</span></span>
                                 <span class="text-sm opacity-80"><span class='font-black'>select</span>  the documents numbers</span>
                                </span>
                            </label>
                        </div>

                        @if($pack == '4')
                            <!-- total_pages -->
                            <div class="mb-4" >
                                <div class="flex gap-2 items-center mb-2">
                                    <x-input-label for="email" :value="__('Total pages')"/>
                                    <span x-text="pack" class="text-md font-black">0</span>
                                </div>
                                <x-text-input x-model="pack" required  wire:model="range" placeholder="Enter a range"  id="range" class="block mt-1 w-full" type="range" step="1" min="0" max="100" name="range"/>
                                <x-input-error :messages="$errors->get('range')" class="mt-2" />
                            </div>
                        @endif

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

