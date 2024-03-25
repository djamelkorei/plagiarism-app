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
use Illuminate\Support\Facades\Http;
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
    'modalEventClose' => 'close-modal-create-assignment',
    'credit' => ''
]);

rules([
    'title' => 'required',
    'file' => 'required|file|mimes:doc,docx,ppt,pptx,wpd,ps,pdf,html,htm,rtf,odt,hwp,gdoc,gdocx,txt|max:50000',
]);

mount(function () {
    if (auth()->user()->hasRole('user')) {
        $this->credit = Balance::where('user_id', auth()->user()->id)->first()->credit;
    }
});

with(fn() => ['assignments' => function () {
    $query = Assignment::selectRaw('assignments.*, users.name as user_name')
        ->leftJoin('users', 'users.id', '=', 'assignments.user_id');

    if (strlen($this->search) > 3) {
        $query->where('assignments.title', 'LIKE', "%{$this->search}%");
    }
    if (Auth::user()->hasRole('user')) {
        $query->where('assignments.user_id', Auth::user()->id);
    }
    return $query->orderBy('assignments.posted_at', 'desc')->paginate(10);
}]);

/**
 * Refresh Data
 *
 * @return void
 */
$refreshData = function () {
    $this->resetPage();
};

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
    $success = false;

    try {
        DB::beginTransaction();
        $assignment = Assignment::create([
            'title' => $this->title,
            'status' => AssignmentStatus::PENDING,
            'user_id' => $user_id,
            'posted_at' => Carbon::now(),
            'file_link' => $path
        ]);
        DB::update('update balances set credit = (credit - 1), total_credit = (total_credit + 1) where user_id = ?', [$user_id]);
        DB::commit();
        $success = true;
    } catch (Exception $exception) {
        Log::error("", [
            "code" => $exception->getCode(),
            "message" => $exception->getMessage(),
        ]);
        DB::rollBack();
    }

    try {
        if($success) {
            Http::withHeader('x-token', config('scraper.api_key'))
                ->get(config('scraper.host').'/assignments/'.$assignment->id.'/process');
        }
    } catch (Exception $exception) {
        Log::error("", [
            "code" => $exception->getCode(),
            "message" => $exception->getMessage(),
        ]);
    }

    $this->reset('title', 'file');
    $this->dispatch($this->modalEventClose);
};

$updated = function ($propertyName) {
    if ($propertyName == 'file') {
        $this->validateOnly($propertyName);
    }
};


$resetFile = function () {
    $this->reset('file');
    $this->resetValidation('file');
    $this->resetErrorBag('file');
};

?>

<x-slot:header>
    {{ __('Assignments') }}
</x-slot:header>

<x-container x-data="{ modalCreateAssignment: false }">

    @role('user')
    @if($credit == 0)
        <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50" role="alert">
            <span class="font-medium">Your credit is 0 charge your balance now <x-link-button
                    href="{{ route('dashboard') }}" class="ms-2">top-up now</x-link-button> </span>
        </div>
    @endif
    @endrole

    <div class="flex align-items-center mb-4 gap-4 justify-between">

        @role('super-admin')
        <x-link-button href="{{ route('dashboard') }}">Dashboard</x-link-button>
        @elserole('user')
        <x-primary-button @click="modalCreateAssignment=true">add assignment</x-primary-button>
        @endrole

        <x-filter-search-input model="search" class="w-[30%]"/>
    </div>

    <x-card>
        <button id="refresh-data-btn" class="hidden" wire:click="refreshData"></button>
        <div class="h-[525px] mb-3">
            <table>
                <thead>
                <tr>
                    @role('super-admin')
                    <th>USER</th>
                    @endrole
                    <th>TITLE</th>
                    <th class="w-[200px]">POSTED DATE</th>
                    <th>STATUS</th>
                    <th>SIMILARITY</th>
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
                        <td class="md:w-[300px]">{{ $row->title }}</td>
                        <td>{{ $row->posted_at->format('Y-m-d H:i') ?? '-' }}</td>
                        <td>
                            <x-badge
                                class="{{ $row->status === AssignmentStatus::COMPLETED ? 'bg-emerald-300' : ($row->status === AssignmentStatus::PROCESSING ? 'bg-orange-300' : 'bg-gray-300')  }}">
                                {{ $row->status }}
                            </x-badge>
                        </td>
                        <td class="font-bold">
                            @if($row->status  === AssignmentStatus::COMPLETED)
                                <x-badge class="bg-gray-300">
                                    {{ $row->similarity }}%
                                </x-badge>
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            <div class="flex justify-end align-items-center">
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
                                    <span class="text-gray-500">not available</span>
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

    <x-modal name="modalCreateAssignment" :event="$modalEventClose" title="Create new assignment">
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
                                  autofocus
                                  wire:target="submit"
                                  wire:loading.attr="disabled"
                                  wire:loading.class="disabled"
                    />
                    <x-input-error :messages="$errors->get('title')" class="mt-2"/>
                </div>

                <!-- File -->
                <div class="mb-4"
                     x-data="{ isUploading: false, progress: 0 }"
                     x-on:livewire-upload-start="isUploading = true"
                     x-on:livewire-upload-finish="isUploading = false"
                     x-on:livewire-upload-error="isUploading = false"
                     x-on:livewire-upload-progress="progress = $event.detail.progress"
                >
                    <x-input-label for="email" :value="__('Document')" class="mb-2"/>
                    <div class="flex items-center justify-center w-full mb-2">
                        <label :for="isUploading ? ''  : 'file'"
                               wire:target="file"
                               wire:loading.class="bg-gray-50"
                               wire:loading.class.remove="border-dashed cursor-pointer hover:bg-indigo-50"
                               class="flex flex-col items-center justify-center w-full h-48 border-2 border-gray-300  rounded-lg  @if($file)  bg-gray-50 @else border-dashed cursor-pointer hover:bg-indigo-50 @endif">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                <svg class="w-8 h-8 mb-4" aria-hidden="true"
                                     xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 16">
                                    <path stroke="currentColor" stroke-linecap="round"
                                          stroke-linejoin="round" stroke-width="2"
                                          d="M13 13h3a3 3 0 0 0 0-6h-.025A5.56 5.56 0 0 0 16 6.5 5.5 5.5 0 0 0 5.207 5.021C5.137 5.017 5.071 5 5 5a4 4 0 0 0 0 8h2.167M10 15V6m0 0L8 8m2-2 2 2"/>
                                </svg>

                                @if($file)
                                    <p class="text-sm"><b>Delete</b> file to upload another one</p>
                                @else
                                    <p class="mb-2 text-sm"><span
                                            class="font-semibold">Click to upload</span> or drag and drop
                                    </p>
                                    <p class="text-xs px-20 text-center">Microsoft Word, PowerPoint, WordPerfect, PostScript, PDF, HTML, RTF, OpenOffice (ODT), Hangul (HWP), Google Docs, and plain text files</p>
                                @endif

                            </div>
                            <input wire:model="file" @if($file) readonly disabled @endif id="file"
                                   type="file"
                                   accept=".doc, .docx, .ppt, .pptx, .wpd, .ps, .pdf, .html, .htm, .rtf, .odt, .hwp, .gdoc, .gdocx, .txt"
                                   class="hidden"/>
                        </label>
                    </div>

                    <!-- Progress Bar -->
                    <div x-show="isUploading" class="relative w-full h-3 overflow-hidden rounded-full bg-neutral-100">
                        <span x-bind:style="'width:' + progress + '%'"
                              class="absolute w-24 h-full duration-300 ease-linear bg-neutral-900" x-cloak></span>
                    </div>

                    @if(isset($file) && $file  != '')
                        <div
                            class="flex items-center justify-between mb-4 bg-gray-100 p-1 rounded-md">
                            <div class="flex items-center gap-1">
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13"/>
                                </svg>
                                <span class="text-gray-600 text-sm">file uploaded</span>
                            </div>
                            <button wire:target="submit"
                                    wire:click="resetFile"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="opacity-75"
                                    class="inline-flex items-center px-2 py-1 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 active:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                     stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                </svg>
                            </button>
                        </div>
                    @endif
                    <x-input-error :messages="$errors->get('file')" class="mt-2"/>
                </div>
            </div>

            <div class="flex items-center justify-end">
                <x-primary-button class="ms-3 gap-1"
                                  wire:target="submit"
                                  wire:loading.attr="disabled"
                                  wire:loading.class="opacity-75"
                >
                    <span wire:target="submit" wire:loading.class="hidden">{{ __('Submit') }}</span>
                    <span wire:target="submit" wire:loading.class.remove="hidden"
                          class="hidden">{{ __('Submiting ...') }}</span>
                    <svg wire:target="submit" wire:loading.class.remove="hidden" aria-hidden="true"
                         class="hidden w-4 h-4 text-gray-50 animate-spin dark:text-gray-600 fill-indigo-400"
                         viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z"
                            fill="currentColor"/>
                        <path
                            d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z"
                            fill="currentFill"/>
                    </svg>
                </x-primary-button>
            </div>

        </form>
    </x-modal>

</x-container>>
