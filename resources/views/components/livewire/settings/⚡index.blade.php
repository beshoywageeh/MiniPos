<?php

use App\Support\Settings;
use Livewire\Component;

new class extends Component {
    public string $companyName = '';

    public string $companyPhone = '';

    public string $companyAddress = '';

    public string $currency = '';

    public string $taxRate = '';

    public string $invoiceFooter = '';

    public bool $saved = false;

    public function mount(): void
    {
        $this->companyName = (string) Settings::get('company_name', 'MiniPOS Store');
        $this->companyPhone = (string) Settings::get('company_phone', '');
        $this->companyAddress = (string) Settings::get('company_address', '');
        $this->currency = (string) Settings::get('currency', 'USD');
        $this->taxRate = (string) Settings::get('tax_rate', '0');
        $this->invoiceFooter = (string) Settings::get('invoice_footer', '');
    }

    public function save(): void
    {
        $validated = $this->validate([
            'companyName' => ['required', 'string', 'max:255'],
            'companyPhone' => ['nullable', 'string', 'max:30'],
            'companyAddress' => ['nullable', 'string', 'max:500'],
            'currency' => ['required', 'string', 'max:3'],
            'taxRate' => ['required', 'numeric', 'min:0', 'max:100'],
            'invoiceFooter' => ['nullable', 'string', 'max:1000'],
        ]);

        Settings::set('company_name', $validated['companyName'], 'string');
        Settings::set('company_phone', $validated['companyPhone'] ?? '', 'string');
        Settings::set('company_address', $validated['companyAddress'] ?? '', 'string');
        Settings::set('currency', $validated['currency'], 'string');
        Settings::set('tax_rate', $validated['taxRate'], 'decimal');
        Settings::set('invoice_footer', $validated['invoiceFooter'] ?? '', 'string');

        $this->saved = true;

        $this->dispatch('settings-saved');
    }
};
?>

<div class="mx-auto max-w-7xl space-y-6" x-data="{ show: false }"
    @settings-saved.window="show = true; setTimeout(() => show = false, 3000)">
    <div x-show="show" x-cloak x-transition.opacity
        class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
        {{ __('settings.saved') }}
    </div>

    <x-page-header :title="__('settings.title')" :subtitle="__('settings.subtitle')" />

    <x-card>
        <form class="space-y-6 p-5" wire:submit="save">
            <h2 class="text-base font-semibold text-slate-900">{{ __('settings.general') }}</h2>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-form.input name="companyName" wire:model="companyName" :label="__('settings.company_name')" />
                <x-form.input name="companyPhone" wire:model="companyPhone" :label="__('settings.company_phone')" />
                <x-form.input name="currency" wire:model="currency" :label="__('settings.currency')" />
                <x-form.input name="taxRate" type="number" step="0.01" wire:model="taxRate" :label="__('settings.tax_rate')" />
            </div>

            <x-form.textarea name="companyAddress" rows="2" wire:model="companyAddress" :label="__('settings.company_address')" />

            <x-form.textarea name="invoiceFooter" rows="3" wire:model="invoiceFooter" :label="__('settings.invoice_footer')" />

            <div class="flex justify-end">
                <x-button type="submit" wire:loading.attr="disabled">{{ __('general.save') }}</x-button>
            </div>
        </form>
    </x-card>
</div>
