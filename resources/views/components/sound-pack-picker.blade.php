@props(['name', 'packs', 'selected'])

<div class="grid grid-cols-2 gap-2">
    @foreach ($packs as $pack)
        <label class="flex cursor-pointer flex-col gap-0.5 rounded-xl border border-zinc-200 bg-white p-3 transition has-checked:border-emerald-500 has-checked:ring-1 has-checked:ring-emerald-500 dark:border-zinc-800 dark:bg-zinc-900">
            <input type="radio" name="{{ $name }}" value="{{ $pack['slug'] }}" class="sr-only" @checked($selected === $pack['slug'])>
            <span class="text-sm font-medium">{{ $pack['name'] }}</span>
            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $pack['description'] }}</span>
        </label>
    @endforeach
</div>
