<div>
    {{-- La key incluye los filtros: si la tabla cambió de rango o método, el detalle se vuelve a montar con los nuevos. --}}
    @livewire(\App\Livewire\SalesUserTickets::class, [
        'userId' => $user->id,
        'from' => $from,
        'to' => $to,
        'payments' => $payments ?? [],
    ], key('sales-user-tickets-' . $user->id . '-' . md5(json_encode([$from, $to, $payments ?? []]))))
</div>
