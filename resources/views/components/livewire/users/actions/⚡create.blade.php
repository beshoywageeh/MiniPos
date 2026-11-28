<?php

use App\Models\User;
use Livewire\Component;
use Livewire\Attributes\Validate;
new class extends Component {
    #[Validate('required|min:3')]
    public string $name = '';

    #[Validate('required|email|unique:users,email')]
    public string $email = '';

    #[Validate('required|min:8|confirmed')]
    public string $password = '';

    public string $password_confirmation = '';

    public function create(): void
    {
        $this->validate();

        User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
        ]);

        $this->reset('name', 'email', 'password', 'password_confirmation');
        $this->resetValidation();
        $this->dispatch('user-created');
    }
};
?>

<div>
    <x-modal @user-created.window="open = false">
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

            <form class="space-y-4" wire:submit="create">
                <x-form.input name="name" wire:model="name" :label="__('users.name')" />
                <x-form.input name="email" type="email" wire:model="email" :label="__('user.email')" />
                <x-form.input name="password" type="password" wire:model="password" :label="__('user.password')" />
                <x-form.input name="password_confirmation" type="password" wire:model="password_confirmation"
                    :label="__('user.password_confirmation')" />

                <div class="flex justify-end gap-3 pt-2">
                    <x-button variant="secondary" @click="open = false">
                        {{ __('general.cancel') }}
                    </x-button>
                    <x-button type="submit" wire:loading.attr="disabled">
                        {{ __('general.save') }}
                    </x-button>
                </div>
            </form>
        </x-modal>
</div>
