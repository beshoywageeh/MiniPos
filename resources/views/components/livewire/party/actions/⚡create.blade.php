<?php

use App\Enums\PartyType;
use App\Models\Brand;
use App\Models\Party;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $code = '';

    public string $name = '';

    public string $phone = '';

    public string $address = '';

    public string $note = '';

    public string $type = '';

    public bool $isActive = true;

    /** @var array<int, int|string> */
    public array $brands = [];

    public function mount(): void
    {
        $this->type = PartyType::CUSTOMER->value;
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function typeOptions(): array
    {
        return collect(PartyType::cases())->mapWithKeys(fn(PartyType $type): array => [$type->value => $type->label()])->all();
    }

    #[Computed]
    public function brandOptions()
    {
        return Brand::query()->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('parties', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
            'type' => ['required', Rule::in(array_column(PartyType::cases(), 'value'))],
            'isActive' => ['boolean'],
            'brands' => ['array'],
            'brands.*' => ['integer', 'exists:brands,id'],
        ]);

        $party = Party::create([
            'code' => $this->code,
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'note' => $this->note,
            'type' => $this->type,
            'is_active' => $this->isActive,
        ]);

        $party->brands()->sync($this->brands);

        $this->reset('code', 'name', 'phone', 'address', 'note', 'isActive', 'brands');
        $this->type = PartyType::CUSTOMER->value;

        $this->dispatch('party-created');
    }
};
?>

<div>
    <x-modal maxWidth="lg" @party-created.window="open = false">
        <x-slot:trigger>
            <x-button @click="open = true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ __('general.add') }}
            </x-button>
        </x-slot:trigger>

        <x-slot:title>
            {{ __('general.add') }}
        </x-slot:title>

        <form class="space-y-4" wire:submit="create">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input name="code" wire:model="code" :label="__('parties.code')" />
                <x-form.input name="name" wire:model="name" :label="__('parties.name')" />
                <x-form.input name="phone" wire:model="phone" :label="__('parties.phone')" />
                <x-form.select name="type" wire:model="type" :label="__('parties.type')" :options="$this->typeOptions" :selected="$type" />
            </div>

            <x-form.textarea name="address" wire:model="address" :label="__('parties.address')" />
            <x-form.textarea name="note" wire:model="note" :label="__('parties.note')" />

            <fieldset>
                <legend class="block text-sm font-medium text-slate-700">{{ __('parties.brands') }}</legend>

                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @forelse ($this->brandOptions as $brand)
                        <x-form.checkbox :name="'brands[]'" :id="'brand-' . $brand->id" :value="$brand->id" wire:model="brands"
                            :label="$brand->name" wire:key="brand-option-{{ $brand->id }}" />
                    @empty
                        <p class="text-sm text-slate-500">{{ __('parties.no_brands') }}</p>
                    @endforelse
                </div>

                @error('brands.*')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </fieldset>

            <x-form.checkbox name="isActive" wire:model="isActive" :label="__('parties.active')" :checked="$isActive" />

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
