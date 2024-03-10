<?php

use App\Models\User;
use App\Models\Account;
use App\Models\Enums\AttributionStatus;
use App\Models\Enums\AccountType;
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
    'class_id' => '',
    'stateless' => '',
    // Others
    'search' => '',
    'modalEventClose' => 'close-modal-create-account'
])->url(as: 'q', history: true, keep: false);

rules([
    'email' => 'required|email',
    'password' => 'required',
    'type' => 'required',
    'class_id' => 'required',
]);

with( fn () => ['accounts' => function () {
    $query = Account::select('*');
    if(strlen($this->search) > 3) {
        $query->where('accounts.email', 'LIKE', "%{$this->search}%");
    }
    return $query->orderBy('accounts.created_at', 'desc')->paginate(10);
}]);


/**
 * Submit Form
 *
 * @return void
 */
$submit = function () {

    $validated = $this->validate();

    $validated['status'] = $this->stateless == '1' ? AccountStatus::ACTIVE : AccountStatus::PENDING;
    $validated['stateless'] = $this->stateless == '1';
    Account::create($validated);

    $this->reset('email', 'password', 'type', 'class_id','stateless');
    $this->dispatch($this->modalEventClose);
};

/**
 * Handle pending status
 *
 * @param $id {number}
 * @return void
 */
$handlePending = function ($id)  {
    $account = Account::find($id);
    $account->status = AccountStatus::PENDING;
    $account->save();
};


/**
 * Handle suspend status
 *
 * @param $id {number}
 * @return void
 */
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
        <x-primary-button @click="modalCreateAccount=true">add account</x-primary-button>
        <x-filter-search-input model="search" class="w-[30%]"/>
    </div>

    <x-card>
        <div class="h-[565px]">
            <table>
                <thead>
                <tr>
                    <th>EMAIL</th>
                    <th>PASSWORD</th>
                    <th>TYPE</th>
                    <th>STATUS</th>
                    <th>CLASS ID</th>
                    <th>STATELESS</th>
                    <th class="text-right">ACTIONS</th>
                </tr>
                </thead>
                <tbody class="relative">
                @foreach($accounts as $row)
                    <tr>
                        <td>{{ $row->email }}</td>
                        <td>{{ $row->password }}</td>
                        <td>
                            <x-badge class="{{ $row->type === AccountType::INSTRUCTOR  ? 'bg-gray-300' : 'bg-gray-100' }}">
                                {{ $row->type }}
                            </x-badge>
                        </td>
                        <td>
                            <x-badge class="{{ $row->status === AccountStatus::ACTIVE  ? 'bg-emerald-300' : ($row->status === AccountStatus::PENDING ? 'bg-orange-300' : 'bg-red-300') }}">
                                {{ $row->status === AccountStatus::ACTIVE ? 'active' : ($row->status === AccountStatus::PENDING ? 'pending' : 'suspended') }}
                            </x-badge>
                        </td>
                        <td>
                           <span class="font-bold"># {{ $row->class_id }}</span>
                        </td>
                        <td>
                            <x-badge class="{{ $row->stateless ? 'bg-emerald-300' : 'bg-orange-300' }}">
                                {{ $row->stateless ? 'YES' : 'NO' }}
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

    <x-modal name="modalCreateAccount" :event="$modalEventClose" title="Create new account">
        <form wire:submit="submit">

            <div class="mb-4">
                <!-- Email -->
                <div class="mb-4">
                    <x-input-label for="text" :value="__('Email')"  class="mb-2"/>
                    <x-text-input placeholder="Enter a email" wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autofocus/>
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <!-- Password -->
                <div class="mb-4">
                    <x-input-label for="text" :value="__('Password')"  class="mb-2"/>
                    <x-text-input placeholder="Enter a password" wire:model="password" id="password" class="block mt-1 w-full" type="text" name="password" required/>
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <!-- Type -->
                <div class="mb-4">
                    <x-input-label for="text" :value="__('Type')"  class="mb-2"/>
                    <x-select-input :options="[AccountType::INSTRUCTOR => AccountType::INSTRUCTOR]" wire:model="type" id="type" class="block mt-1 w-full"  name="type" required/>
                    <x-input-error :messages="$errors->get('type')" class="mt-2" />
                </div>

                <!-- Class ID -->
                <div class="mb-4">
                    <x-input-label for="text" :value="__('Class ID')"  class="mb-2"/>
                    <x-text-input placeholder="Enter a class ID" wire:model="class_id" id="classId" class="block mt-1 w-full" type="text" name="classId" required/>
                    <x-input-error :messages="$errors->get('class_id')" class="mt-2" />
                </div>

                <!-- Remember Me -->
                <div class="block mt-4">
                    <label for="stateless" class="inline-flex items-center">
                        <input wire:model="stateless" value="1" id="stateless" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="stateless">
                        <span class="ms-2 text-sm text-gray-600">{{ __('Stateless') }}</span>
                    </label>
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
