<div>
    @livewire(\App\Livewire\SalesUserTickets::class, [
        'userId' => $user->id,
        'from' => $from,
        'to' => $to,
    ], key('sales-user-tickets-' . $user->id))
</div>
