<?php

use App\Providers\RouteServiceProvider;
use App\Models\Assignment;
use App\Models\User;
use App\Models\Balance;
use App\Models\Enums\BalanceLineStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Models\BalanceLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use function Livewire\Volt\{rules, state, mount, form, layout, with, usesPagination};

layout('layouts.app');
usesPagination();

state([
    'aggregation' => (object)[
        'credit' => 0,
        'total_credit' => 0,
        'total_value' => 0,
        'total_files' => 0,
        'total_users' => 0,
        'total_balance' => 0,
    ],

    'search' => '',
    'pack' => '1',
    'range' => '0',

    'current_id' => null,
    'current_user' => null,
    'current_value' => null,
    'current_credit' => null,
    'current_date' => null,
    'current_type' => null,
    'current_balance_id' => null,

    'modalApproveClose' => 'close-modal-approve',
    'modalTopUpClose' => 'close-modal-top-up',
]);

with(fn() => ['assignments' => function () {
    $query = BalanceLine::selectRaw('balance_lines.*, users.id as user_id, users.name as user_name, users.email as user_email')
        ->leftJoin('balances', 'balances.id', '=', 'balance_lines.balance_id')
        ->leftJoin('users', 'users.id', '=', 'balances.user_id');

    // Filter
    if (strlen($this->search) > 3) {
        $query->where('users.name', 'LIKE', "%{$this->search}%");
    }

    // Auth user
    if (Auth::user()->hasRole('user')) {
        $query->where('user_id', Auth::user()->id);
     //   $query->where('status', BalanceLineStatus::PENDING);
    }

    return $query->orderBy('status')->orderBy('created_at', 'desc')->paginate(10);
}]);

mount(function () {
    if (Auth::user()->hasRole('user')) {
        $balance = Balance::where('user_id', Auth::user()->id)->first();
        $this->aggregation->credit = $balance->credit;
        $this->aggregation->total_files = Assignment::where('user_id', Auth::user()->id)->count();
    }
    if (Auth::user()->hasRole('super-admin')) {
        $this->aggregation->total_users = User::count();
        $this->aggregation->total_value = Balance::sum('total_value');
        $this->aggregation->total_files = Assignment::count();
    }
});

$submitApprove = function () {

    if(!auth()->user()->hasRole('super-admin')) {
        return;
    }

    try {
        DB::beginTransaction();

        $status = $this->current_type == 'yes'
            ? BalanceLineStatus::APPROVED
            : BalanceLineStatus::REFUSED;

        DB::update("update balance_lines set credit = ?, value = ?, status = ? where id = ? and status = ?",
            [$this->current_credit, $this->current_value, $status, $this->current_id, BalanceLineStatus::PENDING]);

        if (BalanceLineStatus::APPROVED == $status) {
            DB::update('update balances set credit = (credit + ?), total_value = (total_value + ?) where id = ?',
                [$this->current_credit, $this->current_value, $this->current_balance_id]);
        }
        DB::commit();
    } catch (Exception $exception) {
        Log::error("", [
            "code" => $exception->getCode(),
            "message" => $exception->getMessage(),
        ]);
        DB::rollBack();
    }

    $this->reset('current_id', 'current_user', 'current_value', 'current_credit', 'current_date', 'current_balance_id');
    $this->dispatch($this->modalApproveClose);
};

$submitTopUp = function () {

    if(!auth()->user()->hasRole('user')) {
        return;
    }

    // If not pack selected fire error
    if ($this->pack == null || !in_array($this->pack, ['1', '2', '3', '4'])) {
        $validator = Validator::make([], []);
        $validator->errors()->add('pack', 'Please select a pack');
        throw new ValidationException($validator);
    }

    // Proper range selection
    if ($this->pack == '4' && (!is_numeric($this->range) || ((int)$this->range) <= 0 || ((int)$this->range) > 100)) {
        $validator = Validator::make([], []);
        $validator->errors()->add('range', 'Please select a proper range');
        throw new ValidationException($validator);
    }

    $balance = Balance::where('user_id', Auth::user()->id)->first();
    $credit = 0;
    $pack = 0;

    if ($this->pack == '1') {
        $credit = 20;
        $pack = 2000;
    } else if ($this->pack == '2') {
        $credit = 50;
        $pack = 4000;
    } else if ($this->pack == '3') {
        $credit = 75;
        $pack = 6000;
    } else if ($this->pack == '4') {
        $credit = $this->range;
    }

    BalanceLine::create([
        'credit' => $credit,
        'value' => $pack,
        'status' => BalanceLineStatus::PENDING,
        'balance_id' => $balance->id
    ]);

    $this->pack = '1';
    $this->range = '0';
    $this->dispatch($this->modalTopUpClose);
};

/**
 * @param $id
 * @param $user
 * @param $value
 * @param $credit
 * @param $date
 * @param $balance_id
 * @return void
 */
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

<x-slot name="header">
    {{ __('Dashboard') }}
</x-slot>

<x-container x-data="{ modalTopUp: false, modalApprove: false, pack: 0 }"
             x-on:open-modal.window="modalApprove = true">

    @role('user')
        @if($aggregation->credit == 0 && sizeof($assignments) != 0)
            <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50" role="alert">
                <span class="font-medium">Your credit is 0 charge your balance now </span>
            </div>
        @endif
    @endrole

    {{-- Widgets --}}
    @role('super-admin')
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 mb-4">

        <x-card-widget title="Users" :description="$aggregation->total_users">
            <x-slot:icon>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                     class="h-8 w-8 text-muted-foreground">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
            </x-slot:icon>
        </x-card-widget>

        <x-card-widget title="Revenue" description="DZD {{ number_format($aggregation->total_value, 2) }}">
            <x-slot:icon>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                </svg>
            </x-slot:icon>
        </x-card-widget>

        <x-card-widget title="Assignments" :description="$aggregation->total_files">
            <x-slot:icon>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
            </x-slot:icon>
        </x-card-widget>

    </div>
    @endrole

    @hasrole('user')
    <div class="grid gap-4 md:grid-cols-2 mb-4">

        <x-card-widget title="Credit" :description="$aggregation->credit">
            <x-slot:icon>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                </svg>
            </x-slot:icon>
        </x-card-widget>

        <x-card-widget title="Assignments" :description="$aggregation->total_files">
            <x-slot:icon>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
            </x-slot:icon>
        </x-card-widget>

    </div>
    @endrole

    {{-- Call action + Filter --}}
    <div class="flex align-items-center mb-4 gap-4 justify-between">

        @role('user')
            @if(sizeof($assignments) != 0)
            <x-primary-button @click="modalTopUp=true"
                              class=" bg-indigo-600 hover:bg-indigo-500 focus:bg-indigo-700 active:bg-indigo-700 focus:ring-indigo-700">
                <svg class="w-6 h-6 me-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                     stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"/>
                </svg>
                <span>top up balance</span>
            </x-primary-button>
            @endif
        @endrole

        @role('super-admin')
        <x-filter-search-input model="search" class="w-[30%]"/>
        @endrole
    </div>

    {{-- Table --}}
    <x-card>
        <div class="h-[525px] mb-3">
            <table>
                <thead>
                <tr>
                    @hasrole('super-admin')
                    <th>USER</th>
                    @endhasrole
                    <th class="w-[200px]">CREDIT</th>
                    <th class="w-[200px]">VALUE</th>
                    <th class="w-[200px]">DATE</th>
                    <th class="w-[100px]">STATUS</th>
                    <th class="text-end">ACTIONS</th>
                </tr>
                </thead>
                <tbody class="relative">

                @foreach($assignments as $row)
                    <tr>
                        @role('super-admin')
                            <td class="md:w-[300px]">
                                <a href="#"
                                   class="capitalize font-bold underline text-indigo-800">{{ $row->user_name }}</a>
                            </td>
                        @endrole
                        <td class="md:w-[300px]">{{ $row->credit }}</td>
                        <td class="md:w-[300px] @if($row->status  === BalanceLineStatus::APPROVED) text-green-700 @elseif($row->status  === BalanceLineStatus::PENDING) text-gray-600 @else line-through @endif">
                            <span
                                class="font-bold @if($row->status  != BalanceLineStatus::APPROVED) invisible @endif">+ </span>{{ number_format($row->value, 2)  }}
                        </td>
                        <td>{{ $row->created_at->format('Y-m-d H:i') ?? '-' }}</td>
                        <td>
                            <x-badge
                                class="{{ $row->status === BalanceLineStatus::APPROVED ? 'bg-emerald-300' : ($row->status === BalanceLineStatus::REFUSED ? 'bg-red-300' : 'bg-orange-300' ) }}">
                                {{ $row->status }}
                            </x-badge>
                        </td>
                        <td>
                            <div class="flex justify-end">
                                @if($row->status  === BalanceLineStatus::APPROVED)
                                    <span class="text-gray-500">claimed</span>
                                @elseif($row->status  === BalanceLineStatus::REFUSED)
                                    <span class="text-gray-500">ignored</span>
                                @else
                                    @role('super-admin')
                                        <x-primary-button
                                            wire:click="beforeOpenModelApprove({{ $row->id . ',\''. $row->user_name . '\','. $row->value . ','. $row->credit . ',\''. $row->created_at->format('Y-m-d H:i') .'\','.  $row->balance_id }})"
                                            class="gap-1">
                                            <span>approve</span>
                                        </x-primary-button>
                                    @endrole
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            @role('user')
                @if($aggregation->credit == 0 && sizeof($assignments) == 0)
                    <div class="p-8 text-gray-900 flex flex-col gap-2 items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-gray-600 w-12 h-12">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
                        </svg>

                        <div class="text-center">
                            <p class="text-indigo-700 font-bold text-sm">Choose a plan</p>
                            <p class="text-lg font-bold">Get Started with a plan that works for you </p>
                            <p>There are various types of plans available to a flexible plan based on quota</p>
                        </div>
                        <br/>
                        <x-primary-button @click="modalTopUp=true"
                                          class="bg-indigo-800 hover:bg-indigo-600 focus:bg-indigo-700 active:bg-indigo-700 focus:ring-indigo-700">
                            <svg class="w-6 h-6 me-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                 stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"/>
                            </svg>
                            <span>top up your balance now</span>
                        </x-primary-button>
                    </div>
                @endif
            @endrole

        </div>
        {{ $assignments->links() }}
    </x-card>

    @role('super-admin')
    <x-modal name="modalApprove" title="Approve request" :event="$modalApproveClose">
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
                <x-input-label for="email" :value="__('Credit')" class="mb-2"/>
                <x-text-input placeholder="Enter a credit" wire:model="current_credit" id="credit"
                              class="block mt-1 w-full" type="number" name="credit" required/>
                <x-input-error :messages="$errors->get('current_value')" class="mt-2"/>
            </div>

            <!-- Value -->
            <div class="mb-4">
                <x-input-label for="email" :value="__('Payment')" class="mb-2"/>
                <x-text-input placeholder="Enter a value" wire:model="current_value" id="credit"
                              class="block mt-1 w-full" type="number" name="value" required/>
                <x-input-error :messages="$errors->get('current_value')" class="mt-2"/>
            </div>

            <div class="flex items-center justify-between gap-4">
                <label for="current_type_yes"
                       class="flex-1 cursor-pointer flex items-start p-4 space-x-3 bg-indigo-50 border rounded-md shadow-sm hover:bg-indigo-100 border-neutral-200/70">
                    <input wire:model.live="current_type" id="current_type_yes" type="radio"
                           name="current_type" value="yes"
                           class="text-gray-900 translate-y-px focus:ring-gray-700"/>
                    <span class="relative flex flex-col text-left space-y-1.5 leading-none">
                                <span class="font-semibold">Accept</span>
                                </span>
                </label>
                <label for="current_type_no"
                       class="flex-1 cursor-pointer flex items-start p-4 space-x-3 bg-red-50 border rounded-md shadow-sm hover:bg-red-100 border-neutral-200/70">
                    <input wire:model.live="current_type" id="current_type_no" type="radio"
                           name="current_type" value="no"
                           class="text-gray-900 translate-y-px focus:ring-gray-700"/>
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
    </x-modal>
    @endrole

    @role('user')
    <x-modal name="modalTopUp" title="Top up balance" :event="$modalTopUpClose">
        <form wire:submit="submitTopUp" class="pt-4">

            <div class="flex flex-col gap-2 mb-4">
                <label for="pack1"
                       class="cursor-pointer flex items-start p-5 space-x-3 bg-white border rounded-md shadow-sm hover:bg-indigo-50 border-neutral-200/70">
                    <input wire:model.live="pack" id="pack1" type="radio" name="pack" value="1"
                           class="text-gray-900 translate-y-px focus:ring-gray-700"/>
                    <span class="relative flex flex-col text-left space-y-1.5 leading-none">
                                <span class="font-semibold">Pack 1 - <span class='font-black font-mono text-pink-600'>2000 DZD</span></span>
                                <span class="text-sm opacity-80"><span
                                        class='font-black'>20 documents</span> included</span>
                            </span>
                </label>
                <label for="pack2"
                       class="cursor-pointer flex items-start p-5 space-x-3 bg-white border rounded-md shadow-sm hover:bg-indigo-50 border-neutral-200/70">
                    <input wire:model.live="pack" id="pack2" type="radio" name="pack" value="2"
                           class="text-gray-900 translate-y-px focus:ring-gray-700"/>
                    <span class="relative flex flex-col text-left space-y-1.5 leading-none">
                                <span class="font-semibold">Pack 2 - <span class='font-black font-mono text-pink-600'>4000 DZD</span></span>
                                <span class="text-sm opacity-80"><span
                                        class='font-black'>50 documents</span> included</span>
                            </span>
                </label>
                <label for="pack3"
                       class="cursor-pointer flex items-start p-5 space-x-3 bg-white border rounded-md shadow-sm hover:bg-indigo-50 border-neutral-200/70">
                    <input wire:model.live="pack" id="pack3" type="radio" name="pack" value="3"
                           class="text-gray-900 translate-y-px focus:ring-gray-700"/>
                    <span class="relative flex flex-col text-left space-y-1.5 leading-none">
                                 <span class="font-semibold">Pack 3 - <span class='font-black font-mono text-pink-600'>6000 DZD</span></span>
                                 <span class="text-sm opacity-80"><span class='font-black'>75 documents</span> included</span>
                                </span>
                </label>
                <label for="pack4"
                       class="cursor-pointer flex items-start p-5 space-x-3 bg-white border rounded-md shadow-sm hover:bg-indigo-50 border-neutral-200/70">
                    <input wire:model.live="pack" id="pack4" type="radio" name="pack" value="4"
                           class="text-gray-900 translate-y-px focus:ring-gray-700"/>
                    <span class="relative flex flex-col text-left space-y-1.5 leading-none">
                                 <span class="font-semibold">Pack 4 - <span class='font-black font-mono text-pink-600'>CUSTOM</span></span>
                                 <span class="text-sm opacity-80"><span class='font-black'>select</span>  the documents numbers</span>
                                </span>
                </label>
            </div>

            @if($pack == '4')
                <!-- total_pages -->
                <div class="mb-4">
                    <div class="flex gap-2 items-center mb-2">
                        <x-input-label for="email" :value="__('Total pages')"/>
                        <span x-text="pack" class="text-md font-black">0</span>
                    </div>
                    <x-text-input x-model="pack" required wire:model="range" placeholder="Enter a range"
                                  id="range" class="block mt-1 w-full" type="range" step="1" min="0"
                                  max="100" name="range"/>
                    <x-input-error :messages="$errors->get('range')" class="mt-2"/>
                </div>
            @endif
            <div class="flex items-center justify-end mt-4">
                <x-primary-button class="ms-3">
                    {{ __('Submit') }}
                </x-primary-button>
            </div>
        </form>
    </x-modal>
    @endrole

</x-container>
