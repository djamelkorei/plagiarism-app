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
    // Form
    'email' => '',
    'password' => '',
    'type' => '',
    // Others
    'search' => '',
    'modalEvent' => 'close-modal-create-account'
]);

rules([
    'email' => 'required|email',
    'password' => 'required',
]);

with( fn () => ['accounts' => function () {
    $query = Account::select('*');
    if(strlen($this->search) > 3) {
        $query->where('accounts.email', 'LIKE', "%{$this->search}%");
    }
    return $query->orderBy('accounts.created_at', 'desc')->paginate(10);
}]);


$submit = function () {

    $validated = $this->validate();
    $validated['status'] = AccountStatus::PENDING;
    $account = Account::create($validated);

    $this->email = '';
    $this->passowrd = '';
    $this->dispatch($this->modalEvent);
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

<x-slot:header>
    {{ __('Accounts') }}
</x-slot:header>

<x-container x-data="{ modalCreateAccount: false }">

    <div class="flex align-items-center mb-4 gap-4 justify-between">
        <x-primary-button @click="modalCreateAccount=true">Create</x-primary-button>
        <x-filter-search-input model="search" class="w-[30%]"/>
    </div>

    <x-card>
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
                            <x-badge class="{{ $row->status === AccountStatus::ACTIVE  ? 'bg-emerald-300' : ($row->status === AccountStatus::PENDING ? 'bg-orange-300' : 'bg-red-300') }}">
                                {{ $row->status === AccountStatus::ACTIVE ? 'active' : ($row->status === AccountStatus::PENDING ? 'pending' : 'suspended') }}
                            </x-badge>
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
    </x-card>

    <x-modal name="modalCreateAccount" :event="$modalEvent" title="Create new account">

        <form wire:submit="submit" id="post-account">

            <div class="mb-4">
                <!-- Email -->
                <div class="mb-4">
                    <x-input-label for="text" :value="__('Email')"  class="mb-2"/>
                    <x-text-input placeholder="Enter a email" wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autofocus/>
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <!-- Name -->
                <div class="mb-4">
                    <x-input-label for="text" :value="__('Password')"  class="mb-2"/>
                    <x-text-input placeholder="Enter a password" wire:model="password" id="password" class="block mt-1 w-full" type="text" name="password" required/>
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <!-- Name -->
                <div class="mb-4">
                    <x-input-label for="text" :value="__('Type')"  class="mb-2"/>
                    <x-select-input :options="['instructor' => 'instructor', 'student' => 'student']" placeholder="Select type" wire:model="type" id="type" class="block mt-1 w-full"  name="type" required/>
                    <x-input-error :messages="$errors->get('type')" class="mt-2" />
                </div>
            </div>

            <div class="flex items-center justify-end">
                <x-primary-button class="ms-3">
                    {{ __('Submit') }}
                </x-primary-button>
            </div>

        </form>
    </x-modal>

</x-container>
