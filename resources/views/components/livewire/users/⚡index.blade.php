<?php
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use Livewire\Component;
use App\Models\User;
new class extends Component {
    use WithPagination;

    public $userName = '';
    public $email = '';
    public $password = '';
    public function newUser()
    {
        User::create([
            'name' => $this->userName,
            'email' => $this->email,
            'password' => Hash::make($this->password),
        ]);
    }
    #[Computed]
    public function users()
    {
        return User::paginate('1');
    }
};
?>

<div class="mx-auto max-w-7xl space-y-6">
    <x-page-header :title="__('users.title')" :subtitle="__('users.subtitle')">
        <x-slot:actions>
            <x-modal>
                <x-slot:trigger>
                    <x-button @click="open = true">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        {{ __('users.add') }}
                    </x-button>
                </x-slot:trigger>

                <x-slot:title>
                    {{ __('users.add') }}
                </x-slot:title>

                {{-- Sample form: static inputs, no submission logic yet. --}}
                <form class="space-y-4" wire:submit="newUser()">
                    <x-form.input name="name" wire:model="name" :label="__('users.name')" />
                    <x-form.input name="email" type="email" wire:model="email" :label="__('user.email')" />
                    <x-form.input name="password" type="password" wire:model="password" :label="__('user.password')" />
                    <x-form.input name="confirmpassword" type="confirmpassword" wire:model="confirmpassword"
                        :label="__('user.confirmpassword')" />
                    <x-slot:footer>
                        <x-button variant="secondary" @click="open = false">
                            {{ __('general.cancel') }}
                        </x-button>
                        <x-button @click="open = false">
                            {{ __('general.save') }}
                        </x-button>
                    </x-slot:footer>
                </form>


            </x-modal>
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
                    <x-table.td></x-table.td>
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
