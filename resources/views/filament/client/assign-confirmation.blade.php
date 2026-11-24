{{-- Reconfirmación del action "Elegir fecha" del panel de clientes: resume lo
     elegido y avisa que no se podrá cambiar. Recibe $leg, $route, $date,
     $schedule y $seat (los que falten se muestran con "—"). --}}

<div class="flex flex-col gap-4">
    <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
        <p class="text-[11px] uppercase tracking-wide text-gray-500 dark:text-gray-400">
            Tu {{ $leg }}
        </p>
        <p class="text-base font-bold text-gray-950 dark:text-white">{{ $route }}</p>

        <dl class="mt-2 grid grid-cols-1 gap-2 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-[11px] text-gray-500 dark:text-gray-400">Fecha</dt>
                <dd class="font-semibold text-gray-950 dark:text-white">{{ $date ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] text-gray-500 dark:text-gray-400">Horario</dt>
                <dd class="font-semibold text-gray-950 dark:text-white">{{ $schedule ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] text-gray-500 dark:text-gray-400">Asiento</dt>
                <dd class="font-semibold text-gray-950 dark:text-white">{{ $seat ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    <div role="alert"
        class="flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200">
        <x-filament::icon icon="heroicon-m-exclamation-triangle" class="mt-0.5 size-5 shrink-0" />
        <p>
            <span class="font-semibold">¿Estás seguro de confirmar esta fecha?</span>
            Una vez confirmada, no vas a poder cambiarla de nuevo. Si después
            necesitás modificarla, vas a tener que comunicarte con boletería.
        </p>
    </div>
</div>
