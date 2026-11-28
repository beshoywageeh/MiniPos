<?php

use App\Enums\MovementType;
use App\Enums\ProductType;
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
    public int|string $productId = '';

    public int|string $warehouseId = '';

    public string $movementType = '';

    public string $quantity = '';

    public int|float|string|null $unitCost = null;

    public string $reason = '';

    public string $note = '';

    public string $movementDate = '';

    public bool $isOverride = false;

    public function mount(): void
    {
        $this->movementType = MovementType::IN->value;
        $this->movementDate = today()->format('Y-m-d');
    }

    /**
     * @return array<int|string, string>
     */
    #[Computed]
    public function productOptions(): array
    {
        return ['' => __('stock_adjustments.select_product')] + Product::query()
            ->where('is_active', true)
            ->where('type', ProductType::Product->value)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    #[Computed]
    public function warehouseOptions(): array
    {
        return ['' => __('stock_adjustments.select_warehouse')] + Warehouse::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function movementTypeOptions(): array
    {
        return ['' => __('stock_adjustments.select_direction')] + [
            MovementType::IN->value => __('stock_adjustments.movement_in'),
            MovementType::OUT->value => __('stock_adjustments.movement_out'),
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function reasonOptions(): array
    {
        return ['' => __('stock_adjustments.select_reason')] + collect(StockAdjustmentReason::cases())
            ->mapWithKeys(fn (StockAdjustmentReason $reason): array => [$reason->value => $reason->label()])
            ->all();
    }

    /**
     * @return array<string, list<string>>
     */
    protected function rules(): array
    {
        return [
            'productId' => ['required', 'integer', Rule::exists('products', 'id')->where(fn ($query) => $query
                ->where('is_active', true)
                ->where('type', ProductType::Product->value))],
            'warehouseId' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'movementType' => ['required', Rule::in(array_column(MovementType::cases(), 'value'))],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'unitCost' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['required', Rule::in(array_column(StockAdjustmentReason::cases(), 'value'))],
            'note' => ['nullable', 'string'],
            'movementDate' => ['required', 'date'],
            'isOverride' => ['boolean'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        try {
            $adjustment = RecordStockAdjustment::execute([
                'product_id' => (int) $this->productId,
                'warehouse_id' => (int) $this->warehouseId,
                'movement_type' => MovementType::from($this->movementType),
                'quantity' => $this->quantity,
                'unit_cost' => $this->unitCost,
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

        $this->redirectRoute('stock-adjustments.preview', ['stockAdjustment' => $adjustment], navigate: true);
    }
};
?>

<div class="mx-auto max-w-3xl space-y-6">
    <form wire:submit="save">
        <x-page-header :title="__('stock_adjustments.title')" :subtitle="__('stock_adjustments.subtitle')">
            <x-slot:actions>
                <x-button type="submit" wire:loading.attr="disabled">
                    {{ __('stock_adjustments.save') }}
                </x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card :title="__('stock_adjustments.details')">
            <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
                <x-form.select name="productId" wire:model="productId" :label="__('stock_adjustments.product')"
                    :options="$this->productOptions" :selected="$productId" />
                <x-form.select name="warehouseId" wire:model="warehouseId" :label="__('stock_adjustments.warehouse')"
                    :options="$this->warehouseOptions" :selected="$warehouseId" />
                <x-form.select name="movementType" wire:model="movementType" :label="__('stock_adjustments.direction')"
                    :options="$this->movementTypeOptions" :selected="$movementType" />
                <x-form.select name="reason" wire:model="reason" :label="__('stock_adjustments.reason')"
                    :options="$this->reasonOptions" :selected="$reason" />
                <x-form.input type="number" step="0.001" min="0.001" name="quantity" wire:model="quantity"
                    :label="__('stock_adjustments.quantity')" />
                <x-form.input type="number" step="0.01" min="0" name="unitCost" wire:model="unitCost"
                    :label="__('stock_adjustments.unit_cost')" :value="$unitCost ?? ''" />
                <x-form.input type="date" name="movementDate" wire:model="movementDate" :value="$movementDate"
                    :label="__('stock_adjustments.date')" />
                <div class="flex items-end pb-2">
                    <x-form.checkbox name="isOverride" wire:model="isOverride" :label="__('stock_adjustments.is_override')"
                        :checked="$isOverride" />
                </div>
                <div class="sm:col-span-2">
                    <x-form.textarea name="note" wire:model="note" :label="__('stock_adjustments.note')"
                        :help="__('stock_adjustments.note_help')" :value="$note" />
                </div>
            </div>
        </x-card>
    </form>
</div>
