@props(['texto' => 'ou'])

<div {{ $attributes->class('my-6 flex items-center gap-3') }}>
    <div class="h-px flex-1 bg-coal/10" aria-hidden="true"></div>
    <span class="text-[10px] font-bold uppercase tracking-widest text-muted">{{ $texto }}</span>
    <div class="h-px flex-1 bg-coal/10" aria-hidden="true"></div>
</div>
