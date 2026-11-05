{{-- Atajo para pasajeros debajo del login de administración. Solo se
     renderiza con el panel de clientes activo (ver AdminPanelProvider). --}}
<div
    class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-fuchsia-100 bg-fuchsia-50 px-4 py-3 text-sm text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
    <p>
        ¿Sos cliente? Tus boletos están en el
        <a href="{{ route('filament.clientes.auth.login') }}"
            class="font-semibold whitespace-nowrap text-fuchsia-600 hover:underline dark:text-fuchsia-400">panel de Clientes.</a>
    </p>
</div>
