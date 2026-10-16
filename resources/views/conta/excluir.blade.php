<x-layouts::auth.ranco :sobrio="true" :title="__('Delete account')">
    <livewire:pages::settings.delete-user-form />
    <form method="POST" action="{{ route('logout') }}" class="mt-6">
        @csrf
        <button type="submit" class="min-h-11 rounded px-2 text-sm font-bold text-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coal">Sair</button>
    </form>
</x-layouts::auth.ranco>
