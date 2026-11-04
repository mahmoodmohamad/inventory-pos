<?php

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('بيانات المحل')] class extends Component
{
    public string $name = '';

    public string $phone = '';

    public string $address = '';

    public string $currency = 'EGP';

    public bool $allow_negative_stock = true;

    public function mount(): void
    {
        $this->authorize('manage-shop');

        $shop = Auth::user()->shop;

        $this->name = $shop->name;
        $this->phone = $shop->phone ?? '';
        $this->address = $shop->address ?? '';
        $this->currency = $shop->currency;
        $this->allow_negative_stock = (bool) $shop->setting('allow_negative_stock', true);
    }

    /** @return array<string, string> */
    #[Computed]
    public function currencies(): array
    {
        return [
            'EGP' => 'جنيه مصري (EGP)',
            'SAR' => 'ريال سعودي (SAR)',
            'AED' => 'درهم إماراتي (AED)',
            'USD' => 'دولار (USD)',
        ];
    }

    public function save(): void
    {
        $this->authorize('manage-shop');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', Rule::in(array_keys($this->currencies))],
            'allow_negative_stock' => ['boolean'],
        ]);

        $shop = Auth::user()->shop;

        $shop->update([
            'name' => trim($validated['name']),
            'phone' => filled($validated['phone']) ? trim($validated['phone']) : null,
            'address' => filled($validated['address']) ? trim($validated['address']) : null,
            'currency' => $validated['currency'],
            // merge عشان منمسحش أي settings تانية
            'settings' => [
                ...($shop->settings ?? []),
                'allow_negative_stock' => $validated['allow_negative_stock'],
            ],
        ]);

        Flux::toast(variant: 'success', text: __('تم حفظ بيانات المحل.'));
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('بيانات المحل') }}</flux:heading>

    <x-pages::settings.layout :heading="__('بيانات المحل')" :subheading="__('الاسم والتواصل والعملة وسياسة المخزون')">
        <form wire:submit="save" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('اسم المحل')" required />
            <flux:input wire:model="phone" :label="__('التليفون')" dir="ltr" />
            <flux:input wire:model="address" :label="__('العنوان')" />

            <flux:select wire:model="currency" :label="__('العملة')">
                @foreach ($this->currencies as $code => $label)
                    <flux:select.option value="{{ $code }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:switch
                wire:model="allow_negative_stock"
                :label="__('السماح بالبيع على الرصيد السالب')"
                :description="__('لو مقفول، أي عملية تخرج كمية أكتر من الرصيد هتترفض.')"
            />

            <flux:button variant="primary" type="submit">{{ __('حفظ') }}</flux:button>
        </form>
    </x-pages::settings.layout>
</section>