@props(['name', 'packs', 'selected'])

<span class="relative justify-self-end">
    <select
        name="{{ $name }}"
        {{ $attributes->class('cursor-pointer appearance-none rounded-lg border border-zinc-300 bg-white py-1.5 pr-7 pl-3 text-zinc-900 outline-none focus-visible:border-blue-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100') }}
    >
        @foreach ($packs as $pack)
            <option value="{{ $pack['slug'] }}" title="{{ $pack['description'] }}" @selected($selected === $pack['slug'])>{{ $pack['name'] }}</option>
        @endforeach
    </select>
    <span aria-hidden="true" class="pointer-events-none absolute top-1/2 right-2.5 -translate-y-1/2 text-[0.6rem] text-zinc-500 dark:text-zinc-300">▾</span>
</span>
