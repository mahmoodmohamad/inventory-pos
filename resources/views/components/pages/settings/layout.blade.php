@can('manage-shop')
    <flux:navlist.item :href="route('shop.edit')" wire:navigate>{{ __('المحل') }}</flux:navlist.item>
@endcan