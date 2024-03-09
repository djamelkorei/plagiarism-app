<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use function Livewire\Volt\{layout, rules, state, mount, form, with, usesFileUploads, usesPagination};

layout('layouts.app');
usesPagination();

state([
    // Form
    'name' => '',
    'email' => '',
    // Other
    'search' => '',
    'modalEventClose' => 'close-modal-create-user'
]);

rules([
    'name' => 'required',
    'email' => 'required|email|unique:users',
]);

with(fn() => ['users' => function () {
    $query = User::selectRaw('users.id, users.name, users.email, users.active, roles.name as role')
        ->leftJoin('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
        ->leftJoin('roles', 'roles.id', '=', 'model_has_roles.role_id');
    if (strlen($this->search) > 3) {
        $query->where('users.name', 'LIKE', "%{$this->search}%");
    }
    return $query->orderBy('users.created_at', 'desc')->paginate(10);
}]);

/**
 * Submit Form
 *
 * @return void
 */
$submit = function () {

    $validated = $this->validate();

    $validated['password'] = Hash::make('123456');
    $validated['active'] = true;
    $user = User::create($validated);
    $user->assignRole('user');

    $this->reset('name', 'email');
    $this->dispatch($this->modalEventClose);
};

/**
 * Activate user, update active state
 *
 * @param $id {number}
 * @return void
 */
$activateUser = function ($id) {
    $user = User::find($id);
    $user->active = !$user->active;
    $user->save();
}

?>

<x-slot name="header">
    {{ __('Users') }}
</x-slot>

<x-container x-data="{ modalCreateUser: false }">

    <div class="flex align-items-center mb-4 gap-4 justify-between">
        <x-primary-button @click="modalCreateUser=true">Create</x-primary-button>
        <x-filter-search-input model="search" class="w-[30%]"/>
    </div>

    <x-card>
        <div class="h-[525px] mb-3">
            <table>
                <thead>
                <tr>
                    <th>NAME</th>
                    <th>EMAIL</th>
                    <th>ROLE</th>
                    <th>ACTIVE</th>
                    <th class="text-right">ACTIONS</th>
                </tr>
                </thead>
                <tbody class="relative">
                @foreach($users as $row)
                    <tr>
                        <td class="capitalize font-bold">{{ $row->name }}</td>
                        <td>{{ $row->email }}</td>
                        <td>
                            <x-badge class="{{ $row->role === 'user' ? 'bg-emerald-300' : 'bg-black text-white'  }}">
                                {{ $row->role }}
                            </x-badge>
                        </td>
                        <td>
                            <x-badge class="{{ $row->active ? 'bg-emerald-300' : 'bg-orange-300'  }}">
                                {{ $row->active ? 'active' : 'suspended' }}
                            </x-badge>
                        </td>
                        <td>
                            @if($row->role === 'user')
                                <div class="flex justify-end align-items-center">
                                    <x-primary-button class="gap-1" wire:click="activateUser({{ $row->id }})">
                                        {{ $row->active ? 'suspend' : 'activate' }}
                                    </x-primary-button>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $users->links() }}
    </x-card>

    <x-modal name="modalCreateUser" :event="$modalEventClose" title="Create new user">
        <form wire:submit="submit">

            <div class="mb-4">
                <!-- Name -->
                <div class="mb-4">
                    <x-input-label for="text" :value="__('Name')" class="mb-2"/>
                    <x-text-input placeholder="Enter a name" wire:model="name" id="name" class="block mt-1 w-full"
                                  type="text" name="name" required autofocus/>
                    <x-input-error :messages="$errors->get('name')" class="mt-2"/>
                </div>

                <!-- Email -->
                <div class="mb-4">
                    <x-input-label for="text" :value="__('Email')" class="mb-2"/>
                    <x-text-input placeholder="Enter a email" wire:model="email" id="email" class="block mt-1 w-full"
                                  type="email" name="email" required autofocus/>
                    <x-input-error :messages="$errors->get('email')" class="mt-2"/>
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
