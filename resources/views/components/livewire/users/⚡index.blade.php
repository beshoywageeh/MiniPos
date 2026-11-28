<?php
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

use Livewire\WithPagination;
use Livewire\Component;

new class extends Component {
    use WithPagination;
    #[Computed]
    public function users()
    {
        return User::query()->latest('id')->paginate('10');
    }

    #[On('user-created')]
    #[On('user-updated')]
    #[On('user-deleted')]
    public function refreshList(): void
    {
        //
    }
};
?>

<div class="mx-auto max-w-7xl space-y-6" x-data="{ flash: false, flashMessage: '' }"
    @user-created.window="flashMessage = @js(__('users.created')); flash = true; setTimeout(() => flash = false, 3000)"
    @user-updated.window="flashMessage = @js(__('users.updated')); flash = true; setTimeout(() => flash = false, 3000)"
    @user-deleted.window="flashMessage = @js(__('users.deleted')); flash = true; setTimeout(() => flash = false, 3000)">
    <div x-show="flash" x-cloak x-transition.opacity
        class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
        <span x-text="flashMessage"></span>
    </div>

    <x-page-header :title="__('users.title')" :subtitle="__('users.subtitle')">
        <x-slot:actions>
            <livewire:livewire.users.actions.create />
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <x-table>
            @slot('head')
                <tr>
                    <x-table.th>#</x-table.th>
                    <x-table.th>{{ __('users.name') }}</x-table.th>
                    <x-table.th>{{ __('user.email') }}</x-table.th>
                    <x-table.th>{{ __('general.created_at') }}</x-table.th>
                    <x-table.th>{{ __('general.actions') }}</x-table.th>
                </tr>
            @endslot
            @forelse ($this->users as $user)
                <tr class="transition hover:bg-slate-50" wire:key="user-{{ $user->id }}">
                    <x-table.td class="text-slate-500">{{ $loop->index + 1 }}</x-table.td>
                    <x-table.td class="font-medium text-slate-900">{{ $user->name }}</x-table.td>
                    <x-table.td class="text-slate-600">{{ $user->email }}</x-table.td>
                    <x-table.td class="text-slate-500">{{ $user->created_at->format('Y-m-d') }}</x-table.td>
                    <x-table.td>
                        <livewire:livewire.users.actions.update :user="$user"
                            wire:key="user-update-{{ $user->id }}" />
                        <livewire:livewire.users.actions.delete :user="$user"
                            wire:key="user-delete-{{ $user->id }}" />
                    </x-table.td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">
                        {{ __('dashboard.no_users') }}
                    </td>
                </tr>
            @endforelse
        </x-table>
        <div class="px-4 py-4">
            {{ $this->users->links() }}
        </div>
    </x-card>
</div>
