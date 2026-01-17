<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Descargando boletos...</title>
    @vite('resources/css/app.css')
</head>

<body class="bg-gray-50 flex items-center justify-center min-h-screen p-6">
    <div class="bg-white border border-gray-200 rounded-xl p-8 max-w-2xl w-full text-center shadow-sm">

        <div class="text-5xl mb-2 flex items-center justify-center ">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="size-12 text-fuchsia-600">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 0 1 0 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 0 1 0-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375Z" />
            </svg>
        </div>

        <h2 class="text-xl font-bold text-gray-900">
            Descargando boletos...
        </h2>

        <p class="mt-2 text-sm text-gray-600">
            Los archivos se descargarán automáticamente. Si no se descargan, haz clic en los enlaces:
        </p>

        <div class="mt-6 space-y-3">
            @foreach ($downloads as $download)
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <div class="flex-1 text-left">
                        <div class="text-sm font-medium text-gray-900">
                            {{ $download['filename'] }}
                        </div>
                        <div class="text-xs text-gray-500">
                            Boleto de viaje
                        </div>
                    </div>
                    <a href="data:application/pdf;base64,{{ $download['content'] }}"
                        download="{{ $download['filename'] }}"
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md
                              bg-fuchsia-600 hover:bg-fuchsia-700
                              text-white text-xs font-semibold transition-colors
                              focus:outline-none focus:ring-2 focus:ring-fuchsia-500 focus:ring-offset-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="size-5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Descargar boleto
                    </a>
                </div>
            @endforeach
        </div>
    </div>

    <script>
        const downloads = @json($downloads);
        let downloadIndex = 0;

        function downloadNext() {
            if (downloadIndex < downloads.length) {
                const download = downloads[downloadIndex];
                const link = document.createElement('a');
                link.href = 'data:application/pdf;base64,' + download.content;
                link.download = download.filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                downloadIndex++;
                // Delay de 800ms entre cada descarga
                setTimeout(downloadNext, 800);
            }
        }

        // Iniciar descargas después de 500ms
        setTimeout(downloadNext, 500);
    </script>
</body>

</html>
