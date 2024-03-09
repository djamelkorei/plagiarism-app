@props([
    'model'
])

<div {{ $attributes->merge(['class' => 'flex gap-1 align-items-center relative']) }}>
    <x-text-input wire:model.live.debounce.400ms="{{ $model }}" id="title" class="block w-full" type="text" name="title" placeholder="search..."/>
    <svg class="h-5 w-5 absolute right-2 top-0 bottom-0 m-auto text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" viewBox="0 0 24 24" >
        <g xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M20 20l-6-6"/>
            <path d="M15 9.5a5.5 5.5 0 11-11 0 5.5 5.5 0 0111 0z"/>
        </g>
    </svg>
</div>
