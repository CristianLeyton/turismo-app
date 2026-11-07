<x-filament-panels::page>
    @livewire(\App\Livewire\SalesUserTickets::class, $this->getDetailParameters(), key('my-sales-' . auth()->id()))
</x-filament-panels::page>
