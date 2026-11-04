<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>{{ $heading }} - {{ config('app.name') }}</title>

    {{-- Tema: misma clave de localStorage que el panel ('theme': light | dark
         | system). Sin elección guardada se sigue al dispositivo, en vivo.
         Va antes del CSS para no parpadear. --}}
    <script>
        (function() {
            var media = window.matchMedia('(prefers-color-scheme: dark)');
            var theme = 'system';

            try {
                theme = localStorage.getItem('theme') || 'system';
            } catch (e) {}

            window.shareTheme = {
                current: theme,
                apply: function() {
                    var isDark = this.current === 'dark' || (this.current === 'system' && media.matches);
                    document.documentElement.classList.toggle('dark', isDark);
                    document.querySelectorAll('[data-theme-option]').forEach(function(button) {
                        button.classList.toggle('fi-active', button.dataset.themeOption === window
                            .shareTheme.current);
                    });
                },
                set: function(value) {
                    this.current = value;
                    try {
                        localStorage.setItem('theme', value);
                    } catch (e) {}
                    this.apply();
                },
            };

            window.shareTheme.apply();

            media.addEventListener('change', function() {
                if (window.shareTheme.current === 'system') {
                    window.shareTheme.apply();
                }
            });
        })();
    </script>

    {{-- Solo el tema del panel: su variante dark es por clase (.dark), así
         que el selector manda aunque el dispositivo esté en otro modo. --}}
    @vite(['resources/css/filament/admin/theme.css'])

    {{-- Fuera de un panel Filament no se inyecta su paleta (--gray-*,
         --primary-*, ...), de la que dependen las utilidades del tema: la
         misma que el admin, con su primary fucsia. --}}
    <style>
        :root {
            @foreach (array_merge(\Filament\Support\Facades\FilamentColor::getColors(), ['primary' => \Filament\Support\Colors\Color::Fuchsia]) as $colorName => $shades)
                @foreach ($shades as $shade => $value)
                    --{{ $colorName }}-{{ $shade }}: {{ $value }};
                @endforeach
            @endforeach
        }
    </style>
</head>

@php
    $buttonBase = 'fi-btn fi-size-md w-full sm:w-auto';
    $buttonPrimary =
        $buttonBase .
        ' ring-0 bg-fuchsia-600 text-white hover:bg-fuchsia-500 dark:bg-fuchsia-500 dark:hover:bg-fuchsia-400 focus-visible:ring-fuchsia-500/50 [&>.fi-icon]:text-white';
    $buttonSuccess =
        $buttonBase .
        ' ring-0 bg-green-600 text-white hover:bg-green-500 dark:bg-green-500 dark:hover:bg-green-400 focus-visible:ring-green-500/50 [&>.fi-icon]:text-white';
    $buttonGray = $buttonBase . ' cursor-pointer';
    $sectionCard = 'rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10';
@endphp

<body class="fi-body min-h-screen bg-gray-50 text-gray-950 antialiased dark:bg-gray-950 dark:text-white">

    <div class="mx-auto flex max-w-5xl flex-col gap-6 px-4 py-6 md:px-6 md:py-8">

        {{-- Barra superior: logo del panel + selector de tema --}}
        <div class="flex items-center justify-between gap-4">
             <a href="{{ url('') }}" class="inline-block"> 
                    @include('filament.admin.logo')
                </a>

                <div class="flex items-center gap-3">
                    <span class="hidden items-center gap-1.5 text-xs text-gray-500 sm:inline-flex dark:text-gray-400">
                        {{ svg('heroicon-m-shield-check', 'size-4') }}
                        Link verificable del boleto
                    </span>

                    <div class="fi-theme-switcher" role="group" aria-label="Tema">
                        @foreach (['light' => ['heroicon-m-sun', 'Claro'], 'dark' => ['heroicon-m-moon', 'Oscuro'], 'system' => ['heroicon-m-computer-desktop', 'Sistema']] as $themeOption => [$themeIcon, $themeLabel])
                            <button type="button" class="fi-theme-switcher-btn" data-theme-option="{{ $themeOption }}"
                                aria-label="{{ $themeLabel }}" title="{{ $themeLabel }}"
                                onclick="window.shareTheme.set('{{ $themeOption }}')">
                                {{ svg($themeIcon, 'fi-icon fi-size-md') }}
                            </button>
                        @endforeach
                    </div>
                </div>
        </div>

        {{-- Encabezado de página, como el de Filament: título + acciones --}}
        <header class="fi-header">
            <div>
                <h1 class="fi-header-heading">{{ $heading }}</h1>
                @if (filled($subheading))
                    <p class="fi-header-subheading">{{ $subheading }}</p>
                @endif
            </div>

            <div class="flex w-full shrink-0 flex-col gap-3 sm:w-auto sm:flex-row sm:flex-wrap sm:justify-end">
                @foreach ($whatsappTargets as $target)
                    <a href="{{ $target['url'] }}" target="_blank" rel="noopener" class="{{ $buttonSuccess }}">
                        <span class="fi-icon fi-size-md">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                aria-hidden="true">
                                <path
                                    d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z" />
                            </svg>
                        </span>
                        <span>{{ count($whatsappTargets) > 1 ? 'Enviar a ' . $target['name'] : 'Enviar' }}</span>
                    </a>
                @endforeach

                <a href="{{ $downloadUrl }}" class="{{ $buttonPrimary }}">
                    {{ svg('heroicon-m-arrow-down-tray', 'fi-icon fi-size-md') }}
                    <span>Descargar PDF</span>
                </a>

                <button type="button" onclick="copiarLink()" class="{{ $buttonGray }}">
                    {{ svg('heroicon-m-clipboard-document', 'fi-icon fi-size-md') }}
                    <span id="copiar-link-label">Copiar link</span>
                </button>
            </div>
        </header>

        @if (count($whatsappTargets) === 0)
            <p class="-mt-3 text-sm text-gray-500 dark:text-gray-400">
                No hay teléfonos cargados en la venta: solo se puede compartir el link copiándolo.
            </p>
        @endif

        {{-- Cuerpo: mismos blades del infolist "Ver boleto" del panel --}}
        @if ($isSaleLink)
            {{-- Un bloque por boleto de ida (cada ida ya muestra su vuelta) +
                 una sola tarjeta de detalles de venta (venta, fecha, método y
                 vendedor son comunes a todos). El PDF incluye todos. --}}
            @foreach ($displayTickets ?? $sale->tickets as $boletoListado)
                <section class="flex flex-col gap-3">
                    <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                        {{ \App\Http\Controllers\TicketShareController::ticketBlockLabel($boletoListado) }}
                    </h2>
                    @include('tickets.infolist-summary-header', ['record' => $boletoListado])
                </section>
            @endforeach

            <div>
                @include('tickets.infolist-sale-details', ['record' => $sale->tickets->first()])
            </div>
        @else
            <div class="flex flex-col gap-6">
                @include('tickets.infolist-summary-header', ['record' => $ticket])
                <div>
                    @include('tickets.infolist-sale-details', ['record' => $ticket])
                </div>
            </div>
        @endif

        {{-- Vista previa del PDF: visor embebido solo donde el navegador lo
             soporta (escritorio); en el resto, tarjeta para abrirlo. --}}
        <section class="{{ $sectionCard }} p-4 md:p-6">
            <h3 class="mb-3 text-base font-semibold text-gray-950 dark:text-white">
                Vista previa
            </h3>

            <div id="pdf-preview-fallback"
                class="flex flex-col gap-4 rounded-lg bg-gray-50 p-4 sm:flex-row sm:items-center sm:justify-between dark:bg-white/5">
                <div class="flex items-center gap-3">
                    {{ svg('heroicon-o-document-text', 'size-8 shrink-0 text-fuchsia-600 dark:text-fuchsia-400') }}
                    <span
                        class="text-sm font-medium break-all text-gray-700 dark:text-gray-300">{{ $filename }}</span>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <a href="{{ $pdfUrl }}" target="_blank" rel="noopener" class="{{ $buttonGray }}">
                        {{ svg('heroicon-m-arrow-top-right-on-square', 'fi-icon fi-size-md') }}
                        <span>Abrir PDF</span>
                    </a>
                    <a href="{{ $downloadUrl }}" class="{{ $buttonPrimary }}">
                        {{ svg('heroicon-m-arrow-down-tray', 'fi-icon fi-size-md') }}
                        <span>Descargar PDF</span>
                    </a>
                </div>
            </div>

            <div id="pdf-preview-frame" hidden data-src="{{ $pdfUrl }}"></div>
        </section>
    </div>

    <script>
        window.shareTheme.apply();

        (function() {
            var frameContainer = document.getElementById('pdf-preview-frame');
            var fallback = document.getElementById('pdf-preview-fallback');
            var canEmbed = navigator.pdfViewerEnabled === true &&
                window.matchMedia('(min-width: 768px)').matches;

            if (!canEmbed) {
                return;
            }

            var iframe = document.createElement('iframe');
            iframe.src = frameContainer.dataset.src;
            iframe.title = 'Vista previa del boleto';
            iframe.className =
                'w-full rounded-lg border border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5';
            iframe.style.height = '75vh';

            frameContainer.appendChild(iframe);
            frameContainer.hidden = false;
            fallback.hidden = true;
        })();

        function copiarLink() {
            var label = document.getElementById('copiar-link-label');
            var url = window.location.href;

            var done = function() {
                label.textContent = '¡Copiado!';
                setTimeout(function() {
                    label.textContent = 'Copiar link';
                }, 2000);
            };

            var fallbackCopy = function() {
                var input = document.createElement('textarea');
                input.value = url;
                input.setAttribute('readonly', '');
                input.style.position = 'fixed';
                input.style.opacity = '0';
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);
                done();
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(done, fallbackCopy);
            } else {
                fallbackCopy();
            }
        }
    </script>
</body>

</html>
