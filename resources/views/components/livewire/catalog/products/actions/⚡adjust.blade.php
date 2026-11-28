<?php

use App\Enums\MovementType;
use App\Enums\StockAdjustmentReason;
use App\Models\Product;
use App\Models\Warehouse;
use App\Support\RecordStockAdjustment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Product $product;

    public int|string $warehouseId = '';

    public string $movementType = '';

    public string $quantity = '';

    public string $unitCost = '';

    public string $reason = '';

    public string $note = '';

    public string $movementDate = '';

    public bool $isOverride = false;

    public function mount(Product $product): void
    {
        $this->product = $product;
        $this->movementType = MovementType::IN->value;
        $this->movementDate = today()->format('Y-m-d');
    }

    /**
     * @return array<int|string, string>
     */
    #[Computed]
    public function warehouseOptions(): array
    {
        return ['' => __('products.none')] + Warehouse::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function directionOptions(): array
    {
        return [
            MovementType::IN->value => __('products.movement_in'),
            MovementType::OUT->value => __('products.movement_out'),
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function reasonOptions(): array
    {
        return ['' => __('products.select_reason')] + collect(StockAdjustmentReason::cases())
            ->mapWithKeys(fn (StockAdjustmentReason $reason): array => [$reason->value => $reason->label()])
            ->all();
    }

    /**
     * @return array<string, list<string>>
     */
    protected function rules(): array
    {
        return [
            'warehouseId' => ['required', 'integer', 'exists:warehouses,id'],
            'movementType' => ['required', Rule::in(array_column(MovementType::cases(), 'value'))],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'unitCost' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['required', Rule::in(array_column(StockAdjustmentReason::cases(), 'value'))],
            'note' => ['nullable', 'string'],
            'movementDate' => ['required', 'date'],
            'isOverride' => ['boolean'],
        ];
    }

    public function adjust(): void
    {
        $this->validate();

        try {
            RecordStockAdjustment::execute([
                'product_id' => $this->product->id,
                'warehouse_id' => (int) $this->warehouseId,
                'movement_type' => MovementType::from($this->movementType),
                'quantity' => $this->quantity,
                'unit_cost' => $this->unitCost !== '' ? $this->unitCost : 0,
                'reason' => StockAdjustmentReason::from($this->reason),
                'note' => $this->note !== '' ? $this->note : null,
                'movement_date' => $this->movementDate,
                'is_override' => $this->isOverride,
                'created_by' => Auth::id(),
            ]);
        } catch (ValidationException $exception) {
            /** @var array<string, string> $fieldMap */
            $fieldMap = [
                'product_id' => 'productId',
                'warehouse_id' => 'warehouseId',
                'unit_cost' => 'unitCost',
                'movement_date' => 'movementDate',
                'is_override' => 'isOverride',
            ];

            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($fieldMap[$field] ?? $field, $message);
                }
            }

            return;
        }

        $this->reset('warehouseId', 'quantity', 'unitCost', 'reason', 'note', 'isOverride');
        $this->movementType = MovementType::IN->value;
        $this->movementDate = today()->format('Y-m-d');

        $this->dispatch('product-adjusted');
    }
};
?>

<div>
    <x-modal @product-adjusted.window="open = false">
        <x-slot:trigger>
            <x-button variant="secondary" @click="open = true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                </svg>
                {{ __('products.adjust') }}
            </x-button>
        </x-slot:trigger>

        <x-slot:title>
            {{ __('products.adjust_title') }} {{ $product->name }}
        </x-slot:title>

        <form class="space-y-4" wire:submit="adjust">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-form.select name="warehouseId" wire:model="warehouseId" :label="__('products.warehouse')"
                    :options="$this->warehouseOptions" :selected="$warehouseId" />
                <x-form.select name="movementType" wire:model="movementType" :label="__('products.direction')"
                    :options="$this->directionOptions" :selected="$movementType" />
                <x-form.select name="reason" wire:model="reason" :label="__('products.reason')"
                    :options="$this->reasonOptions" :selected="$reason" />
                <x-form.input type="number" step="0.001" min="0.001" name="quantity" wire:model="quantity"
                    :label="__('products.quantity')" />
                <x-form.input type="number" step="0.01" min="0" name="unitCost" wire:model="unitCost"
                    :label="__('products.unit_cost')" />
                <x-form.input type="date" name="movementDate" wire:model="movementDate" :value="$movementDate"
                    :label="__('products.date')" />
                <div class="sm:col-span-2">
                    <x-form.checkbox name="isOverride" wire:model="isOverride" :label="__('products.is_override')"
                        :checked="$isOverride" />
                    <p class="mt-1 text-xs text-slate-500">{{ __('products.override_help') }}</p>
                </div>
            </div>

            <x-form.textarea name="note" wire:model="note" :label="__('products.description')"
                :help="__('products.override_help')" />

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
