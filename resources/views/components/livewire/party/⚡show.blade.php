<?php

use App\Enums\DocumentStatus;
use App\Enums\PaymentType;
use App\Models\Party;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public Party $party;

    public function mount(Party $party): void
    {
        $this->party = $party;
    }

    /**
     * @return array{
     *     sales: float,
     *     sales_returns: float,
     *     purchases: float,
     *     purchase_returns: float,
     *     receipts: float,
     *     payments_made: float,
     *     total_payments: float,
     *     balance: float,
     *     balance_amount: float,
     *     balance_label: string
     * }
     */
    #[Computed]
    public function totals(): array
    {
        $sales = (float) $this->party->sales()->sum('total_amount');
        $salesReturns = (float) $this->party->salesReturns()->sum('total_amount');
        $purchases = (float) $this->party->purchases()
            ->where('status', '!=', DocumentStatus::DRAFT->value)
            ->sum('total_amount');
        $purchaseReturns = (float) $this->party->purchaseReturns()->sum('total_amount');
        $receipts = (float) $this->party->payments()
            ->where('type', PaymentType::recipt->value)
            ->sum('amount');
        $paymentsMade = (float) $this->party->payments()
            ->where('type', PaymentType::payment->value)
            ->sum('amount');

        $balance = ($sales - $salesReturns - $receipts) - ($purchases - $purchaseReturns - $paymentsMade);

        return [
            'sales' => $sales,
            'sales_returns' => $salesReturns,
            'purchases' => $purchases,
            'purchase_returns' => $purchaseReturns,
            'receipts' => $receipts,
            'payments_made' => $paymentsMade,
            'total_payments' => $receipts + $paymentsMade,
            'balance' => $balance,
            'balance_amount' => abs($balance),
            'balance_label' => $balance >= 0
                ? __('parties.party_owes_you')
                : __('parties.you_owe_party'),
        ];
    }

    /**
     * @return Collection<int, object{
     *     id: int,
     *     key: string,
     *     type: string,
     *     color: string,
     *     document_number: string,
     *     date: string,
     *     amount: float,
     *     status: string|null,
     *     notes: string|null,
     *     sale_id: int|null,
     *     purchase_id: int|null
     * }>
     */
    #[Computed]
    public function allTransactions(): Collection
    {
        return $this->party->sales()
            ->select('id', 'document_number', 'invoice_date', 'total_amount', 'status', 'notes')
            ->get()
            ->map(fn ($sale) => (object) [
                'id' => (int) $sale->id,
                'key' => 'sale-'.$sale->id,
                'type' => __('parties.transaction_sale'),
                'color' => 'emerald',
                'document_number' => $sale->document_number,
                'date' => $sale->invoice_date?->format('Y-m-d') ?? '—',
                'amount' => (float) $sale->total_amount,
                'status' => $sale->status?->label(),
                'notes' => $sale->notes,
                'sale_id' => (int) $sale->id,
                'purchase_id' => null,
            ])
            ->concat(
                $this->party->salesReturns()
                    ->select('id', 'document_number', 'return_date', 'total_amount', 'status', 'notes')
                    ->get()
                    ->map(fn ($return) => (object) [
                        'id' => (int) $return->id,
                        'key' => 'sales-return-'.$return->id,
                        'type' => __('parties.transaction_sales_return'),
                        'color' => 'amber',
                        'document_number' => $return->document_number,
                        'date' => $return->return_date?->format('Y-m-d') ?? '—',
                        'amount' => (float) $return->total_amount,
                        'status' => $return->status?->label(),
                        'notes' => $return->notes,
                        'sale_id' => null,
                        'purchase_id' => null,
                    ]),
            )
            ->concat(
                $this->party->purchases()
                    ->select('id', 'document_number', 'invoice_date', 'total_amount', 'status', 'notes')
                    ->get()
                    ->map(fn ($purchase) => (object) [
                        'id' => (int) $purchase->id,
                        'key' => 'purchase-'.$purchase->id,
                        'type' => __('parties.transaction_purchase'),
                        'color' => 'indigo',
                        'document_number' => $purchase->document_number,
                        'date' => $purchase->invoice_date?->format('Y-m-d') ?? '—',
                        'amount' => (float) $purchase->total_amount,
                        'status' => $purchase->status?->label(),
                        'notes' => $purchase->notes,
                        'sale_id' => null,
                        'purchase_id' => (int) $purchase->id,
                    ]),
            )
            ->concat(
                $this->party->purchaseReturns()
                    ->select('id', 'document_number', 'return_date', 'total_amount', 'status', 'notes')
                    ->get()
                    ->map(fn ($return) => (object) [
                        'id' => (int) $return->id,
                        'key' => 'purchase-return-'.$return->id,
                        'type' => __('parties.transaction_purchase_return'),
                        'color' => 'rose',
                        'document_number' => $return->document_number,
                        'date' => $return->return_date?->format('Y-m-d') ?? '—',
                        'amount' => (float) $return->total_amount,
                        'status' => $return->status?->label(),
                        'notes' => $return->notes,
                        'sale_id' => null,
                        'purchase_id' => null,
                    ]),
            )
            ->concat(
                $this->party->payments()
                    ->select('id', 'document_number', 'payment_date', 'type', 'amount', 'notes')
                    ->get()
                    ->map(fn ($payment) => (object) [
                        'id' => (int) $payment->id,
                        'key' => 'payment-'.$payment->id,
                        'type' => $payment->type === PaymentType::recipt
                            ? __('parties.transaction_payment_received')
                            : __('parties.transaction_payment_made'),
                        'color' => 'sky',
                        'document_number' => $payment->document_number,
                        'date' => $payment->payment_date?->format('Y-m-d') ?? '—',
                        'amount' => (float) $payment->amount,
                        'status' => null,
                        'notes' => $payment->notes,
                        'sale_id' => null,
                        'purchase_id' => null,
                    ]),
            );
    }

    #[Computed]
    public function transactions(): LengthAwarePaginator
    {
        $transactions = $this->allTransactions()
            ->sortByDesc(fn (object $transaction): array => [$transaction->date, $transaction->id])
            ->values();

        return new LengthAwarePaginator(
            $transactions->forPage($this->getPage(), 10),
            $transactions->count(),
            10,
            $this->getPage(),
        );
    }
};
?>

<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex items-center gap-2 text-sm text-slate-500">
        <a href="{{ route('parties.index') }}" class="inline-flex items-center gap-1 hover:text-slate-700">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="h-4 w-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
            {{ __('parties.back') }}
        </a>
    </div>

    <x-page-header :title="$party->name" :subtitle="$party->code">
        <x-slot:actions>
            <x-badge :color="$party->is_active ? 'emerald' : 'slate'">
                {{ $party->is_active ? __('parties.active') : __('parties.inactive') }}
            </x-badge>
            <livewire:livewire.party.actions.update :party="$party" />
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">{{ __('parties.total_sales') }}</p>
            <p class="mt-1 text-xl font-semibold text-slate-900">{{ number_format($this->totals['sales'], 2) }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">{{ __('parties.total_purchases') }}</p>
            <p class="mt-1 text-xl font-semibold text-slate-900">{{ number_format($this->totals['purchases'], 2) }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">{{ __('parties.total_payments') }}</p>
            <p class="mt-1 text-xl font-semibold text-slate-900">{{ number_format($this->totals['total_payments'], 2) }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">{{ __('parties.net_balance') }}</p>
            <p class="mt-1 text-xl font-semibold text-slate-900">
                {{ number_format($this->totals['balance_amount'], 2) }}
                <span class="block text-xs font-medium text-slate-500">{{ $this->totals['balance_label'] }}</span>
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card title="{{ __('parties.details') }}" class="lg:col-span-1">
            <dl class="divide-y divide-slate-100 text-sm">
                <div class="flex items-center justify-between px-5 py-3">
                    <dt class="text-slate-500">{{ __('parties.code') }}</dt>
                    <dd class="font-medium text-slate-900">{{ $party->code }}</dd>
                </div>
                <div class="flex items-center justify-between px-5 py-3">
                    <dt class="text-slate-500">{{ __('parties.type') }}</dt>
                    <dd class="font-medium text-slate-900">{{ $party->type->label() }}</dd>
                </div>
                <div class="flex items-center justify-between px-5 py-3">
                    <dt class="text-slate-500">{{ __('parties.phone') }}</dt>
                    <dd class="font-medium text-slate-900">{{ $party->phone ?: '—' }}</dd>
                </div>
                <div class="flex items-center justify-between px-5 py-3">
                    <dt class="text-slate-500">{{ __('parties.address') }}</dt>
                    <dd class="font-medium text-slate-900">{{ $party->address ?: '—' }}</dd>
                </div>
                <div class="flex items-center justify-between px-5 py-3">
                    <dt class="text-slate-500">{{ __('parties.note') }}</dt>
                    <dd class="font-medium text-slate-900">{{ $party->note ?: '—' }}</dd>
                </div>
                <div class="flex items-center justify-between px-5 py-3">
                    <dt class="text-slate-500">{{ __('parties.active') }}</dt>
                    <dd class="font-medium text-slate-900">
                        {{ $party->is_active ? __('parties.active') : __('parties.inactive') }}
                    </dd>
                </div>
                <div class="flex items-center justify-between px-5 py-3">
                    <dt class="text-slate-500">{{ __('parties.brands') }}</dt>
                    <dd class="font-medium text-slate-900">
                        {{ $party->brands->pluck('name')->join(', ') ?: '—' }}
                    </dd>
                </div>
                <div class="flex items-center justify-between px-5 py-3">
                    <dt class="text-slate-500">{{ __('general.created_at') }}</dt>
                    <dd class="font-medium text-slate-900">{{ $party->created_at?->format('Y-m-d') ?? '—' }}</dd>
                </div>
            </dl>
        </x-card>

        <x-card title="{{ __('parties.balance_breakdown') }}" class="lg:col-span-2">
            <dl class="divide-y divide-slate-100 text-sm">
                <div class="flex items-center justify-between px-5 py-3">
                    <dt class="text-slate-500">{{ __('parties.total_sales') }}</dt>
                    <dd class="font-medium text-slate-900">{{ number_format($this->totals['sales'], 2) }}</dd>
                </div>
                <div class="flex items-center justify-between px-5 py-3">
                    <dt class="text-slate-500">{{ __('parties.sales_returns') }}</dt>
                    <dd class="font-medium text-slate-900">− {{ number_format($this->totals['sales_returns'], 2) }}</dd>
                </div>
                <div class="flex items-center justify-between px-5 py-3">
                    <dt class="text-slate-500">{{ __('parties.receipts') }}</dt>
                    <dd class="font-medium text-slate-900">− {{ number_format($this->totals['receipts'], 2) }}</dd>
                </div>
                <div class="flex items-center justify-between px-5 py-3">
                    <dt class="text-slate-500">{{ __('parties.total_purchases') }}</dt>
                    <dd class="font-medium text-slate-900">− {{ number_format($this->totals['purchases'], 2) }}</dd>
                </div>
                <div class="flex items-center justify-between px-5 py-3">
                    <dt class="text-slate-500">{{ __('parties.purchase_returns') }}</dt>
                    <dd class="font-medium text-slate-900">+ {{ number_format($this->totals['purchase_returns'], 2) }}</dd>
                </div>
                <div class="flex items-center justify-between px-5 py-3">
                    <dt class="text-slate-500">{{ __('parties.payments_made') }}</dt>
                    <dd class="font-medium text-slate-900">+ {{ number_format($this->totals['payments_made'], 2) }}</dd>
                </div>
            </dl>
        </x-card>
    </div>

    <x-card title="{{ __('parties.transactions') }}">
        <x-table>
            @slot('head')
                <tr>
                    <x-table.th>{{ __('parties.date') }}</x-table.th>
                    <x-table.th>{{ __('parties.type') }}</x-table.th>
                    <x-table.th>{{ __('parties.document_number') }}</x-table.th>
                    <x-table.th align="right">{{ __('parties.amount') }}</x-table.th>
                    <x-table.th>{{ __('parties.status') }}</x-table.th>
                    <x-table.th>{{ __('parties.note') }}</x-table.th>
                </tr>
            @endslot
            @forelse ($this->transactions as $transaction)
                <tr class="transition hover:bg-slate-50" wire:key="party-transaction-{{ $transaction->key }}">
                    <x-table.td class="text-slate-600">{{ $transaction->date }}</x-table.td>
                    <x-table.td>
                        <x-badge :color="$transaction->color">{{ $transaction->type }}</x-badge>
                    </x-table.td>
                    <x-table.td class="font-medium text-slate-900">
                        @if ($transaction->sale_id)
                            <a href="{{ route('sales.invoice_preview', $transaction->sale_id) }}"
                                class="hover:text-indigo-600">{{ $transaction->document_number }}</a>
                        @elseif ($transaction->purchase_id)
                            <a href="{{ route('purchases.invoice_preview', $transaction->purchase_id) }}"
                                class="hover:text-indigo-600">{{ $transaction->document_number }}</a>
                        @else
                            {{ $transaction->document_number }}
                        @endif
                    </x-table.td>
                    <x-table.td align="right" class="text-slate-600">{{ number_format($transaction->amount, 2) }}</x-table.td>
                    <x-table.td class="text-slate-600">{{ $transaction->status ?? '—' }}</x-table.td>
                    <x-table.td class="text-slate-500">{{ $transaction->notes ?: '—' }}</x-table.td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">
                        {{ __('parties.no_transactions') }}
                    </td>
                </tr>
            @endforelse
        </x-table>
        <div class="px-4 py-4">
            {{ $this->transactions->links() }}
        </div>
    </x-card>
</div>
