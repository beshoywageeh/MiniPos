<?php

use App\Enums\ProductType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $code = '';

    public string $type = '';

    public string $sku = '';

    public string $barcode = '';

    public string $name = '';

    public string $description = '';

    public int|string $categoryId = '';

    public int|string $brandId = '';

    public int|string $unitId = '';

    public string $purchasePrice = '';

    public string $sellingPrice = '';

    public string $minStock = '';

    public bool $isActive = true;

    public function mount(): void
    {
        $this->type = ProductType::Product->value;
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function typeOptions(): array
    {
        return collect(ProductType::cases())
            ->mapWithKeys(fn (ProductType $type): array => [$type->value => $type->label()])
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    #[Computed]
    public function categoryOptions(): array
    {
        return ['' => __('products.none')] + Category::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<int|string, string>
     */
    #[Computed]
    public function brandOptions(): array
    {
        return ['' => __('products.none')] + Brand::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<int|string, string>
     */
    #[Computed]
    public function unitOptions(): array
    {
        return ['' => __('products.none')] + Unit::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function create(): void
    {
        $this->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('products', 'code')],
            'type' => ['required', Rule::in(array_column(ProductType::cases(), 'value'))],
            'sku' => ['nullable', 'string', 'max:50', Rule::unique('products', 'sku')],
            'barcode' => ['nullable', 'string', 'max:50', Rule::unique('products', 'barcode')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'categoryId' => ['nullable', 'integer', 'exists:categories,id'],
            'brandId' => ['nullable', 'integer', 'exists:brands,id'],
            'unitId' => ['nullable', 'integer', 'exists:units,id'],
            'purchasePrice' => ['required', 'numeric', 'min:0'],
            'sellingPrice' => ['required', 'numeric', 'min:0'],
            'minStock' => ['required', 'numeric', 'min:0'],
            'isActive' => ['boolean'],
        ]);

        Product::create([
            'code' => $this->code,
            'type' => $this->type,
            'sku' => $this->sku !== '' ? $this->sku : null,
            'barcode' => $this->barcode !== '' ? $this->barcode : null,
            'name' => $this->name,
            'description' => $this->description,
            'category_id' => $this->categoryId !== '' ? $this->categoryId : null,
            'brand_id' => $this->brandId !== '' ? $this->brandId : null,
            'unit_id' => $this->unitId !== '' ? $this->unitId : null,
            'purchase_price' => $this->purchasePrice,
            'selling_price' => $this->sellingPrice,
            'min_stock' => $this->minStock,
            'is_active' => $this->isActive,
        ]);

        $this->reset('code', 'sku', 'barcode', 'name', 'description', 'categoryId', 'brandId', 'unitId', 'purchasePrice', 'sellingPrice', 'minStock', 'isActive');
        $this->type = ProductType::Product->value;

        $this->dispatch('product-created');
    }
};
?>

<div>
    <x-modal maxWidth="lg" @product-created.window="open = false">
        <x-slot:trigger>
            <x-button @click="open = true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ __('products.add') }}
            </x-button>
        </x-slot:trigger>

        <x-slot:title>
            {{ __('products.add') }}
        </x-slot:title>

        <form class="space-y-4" wire:submit="create">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-form.input name="code" wire:model="code" :label="__('products.code')" />
                <x-form.select name="type" wire:model="type" :label="__('products.type')"
                    :options="$this->typeOptions" :selected="$type" />
                <x-form.input name="name" wire:model="name" :label="__('products.name')" />
                <x-form.input name="sku" wire:model="sku" :label="__('products.sku')" />
                <x-form.input name="barcode" wire:model="barcode" :label="__('products.barcode')" />
                <x-form.select name="categoryId" wire:model="categoryId" :label="__('products.category')"
                    :options="$this->categoryOptions" :selected="$categoryId" />
                <x-form.select name="brandId" wire:model="brandId" :label="__('products.brand')"
                    :options="$this->brandOptions" :selected="$brandId" />
                <x-form.select name="unitId" wire:model="unitId" :label="__('products.unit')"
                    :options="$this->unitOptions" :selected="$unitId" />
                <x-form.input type="number" step="0.01" name="purchasePrice" wire:model="purchasePrice"
                    :label="__('products.purchase_price')" />
                <x-form.input type="number" step="0.01" name="sellingPrice" wire:model="sellingPrice"
                    :label="__('products.selling_price')" />
                <x-form.input type="number" step="0.001" name="minStock" wire:model="minStock"
                    :label="__('products.min_stock')" />
                <div class="flex items-end pb-2">
                    <x-form.checkbox name="isActive" wire:model="isActive" :label="__('products.active')"
                        :checked="$isActive" />
                </div>
            </div>

            <x-form.textarea name="description" wire:model="description" :label="__('products.description')" />

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
