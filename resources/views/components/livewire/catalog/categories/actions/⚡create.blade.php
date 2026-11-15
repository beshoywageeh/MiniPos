<?php

use App\Models\Category;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $code = '';

    public string $name = '';

    public string $description = '';

    public bool $isActive = true;

    public int|string $parentId = '';

    #[Computed]
    public function parentOptions()
    {
        return Category::query()->orderBy('name')->pluck('name', 'id');
    }

    public function create(): void
    {
        $this->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('categories', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'isActive' => ['boolean'],
            'parentId' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        Category::create([
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->isActive,
            'parent_id' => $this->parentId !== '' ? $this->parentId : null,
        ]);

        $this->reset('code', 'name', 'description', 'isActive', 'parentId');
        $this->dispatch('category-created');
    }
};
?>

<div>
    <x-modal @category-created.window="open = false">
        <x-slot:trigger>
            <x-button @click="open = true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ __('categories.add') }}
            </x-button>
        </x-slot:trigger>

        <x-slot:title>
            {{ __('categories.add') }}
        </x-slot:title>

        <form class="space-y-4" wire:submit="create">
            <x-form.input name="code" wire:model="code" :label="__('categories.code')" />
            <x-form.input name="name" wire:model="name" :label="__('categories.name')" />
            <x-form.textarea name="description" wire:model="description" :label="__('categories.description')" />
            <x-form.select name="parentId" wire:model="parentId" :label="__('categories.parent')"
                :options="collect(['' => __('categories.parent_none')])->merge($this->parentOptions)" />
            <x-form.checkbox name="isActive" wire:model="isActive" :label="__('categories.active')"
                :checked="$isActive" />

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
