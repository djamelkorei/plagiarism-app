@props([
    'title',
    'description',
    'icon',
])
<div class="rounded-md border bg-card text-card-foreground shadow-sm bg-white">
    <div class="p-6 flex flex-row items-start justify-between space-y-0 pb-2"><h3
            class="tracking-tight text-md font-medium uppercase">{{ $title }}</h3>
        {{ $icon }}
    </div>
    <div class="p-6 pt-0">
        <div class="text-xl font-bold">{{ $description }}</div>
    </div>
</div>
