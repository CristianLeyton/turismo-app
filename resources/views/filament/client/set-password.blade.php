{{-- Bienvenida del primer ingreso (SetPassword): resumen de lo que se puede
     hacer en el panel. El formulario de contraseña es de Filament y se arma
     en SetPassword::form(). --}}

@php
    $capacidades = [
        [
            'icono' => 'heroicon-m-ticket',
            'titulo' => 'Ver tus boletos',
            'detalle' => 'Tus próximos viajes y el historial, siempre a mano.',
        ],
        [
            'icono' => 'heroicon-m-calendar-days',
            'titulo' => 'Elegir fecha y asiento',
            'detalle' => 'Si tenés boletos sin fecha, los programás vos cuando quieras.',
        ],
        [
            'icono' => 'heroicon-m-arrow-down-tray',
            'titulo' => 'Ver o descargar el boleto',
            'detalle' => 'En PDF, para mostrarlo desde el celular o imprimirlo.',
        ],
    ];
@endphp

<div class="flex flex-col gap-4 text-sm text-gray-600 dark:text-gray-300">
    <div class="flex flex-col gap-1">
        <p>
            Desde acá vas a poder manejar tus boletos de forma simple y rápida.
        </p>
    </div>

    <div class="flex flex-col gap-2">
        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Qué vas a poder hacer</h3>

        <ul class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            @foreach ($capacidades as $capacidad)
                <li wire:key="bienvenida-{{ $loop->index }}"
                    class="flex items-start gap-3 rounded-lg p-4 sm:flex-col bg-white/95 dark:bg-white/5">
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-600 dark:bg-primary-400/15 dark:text-primary-400">
                        <x-filament::icon :icon="$capacidad['icono']" class="size-5" />
                    </span>

                    <div class="flex min-w-0 flex-col gap-0.5">
                        <span class="font-semibold text-gray-950 dark:text-white">{{ $capacidad['titulo'] }}</span>
                        <span class="text-gray-600 dark:text-gray-400">{{ $capacidad['detalle'] }}</span>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="flex flex-col gap-1 border-t border-gray-200 pt-4 dark:border-white/10">
        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Primer paso: elegí tu contraseña</h3>
        <p>
            Tu contraseña inicial es tu DNI. Elegí una propia para proteger tus
            boletos y entrar más fácil la próxima vez.
        </p>
        <p class="text-gray-500 dark:text-gray-400">
            Después podés cambiarla desde «Mi perfil», en el menú de tu usuario.
        </p>
    </div>
</div>
