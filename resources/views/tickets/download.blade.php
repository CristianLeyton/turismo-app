<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Descargando boleto...</title>
    @vite('resources/css/app.css')
</head>

<body class="bg-gray-50 flex items-center justify-center min-h-screen p-6">
    <div class="bg-white border border-gray-200 rounded-xl p-8 max-w-md w-full text-center shadow-sm">

        <div class="text-5xl mb-2 flex items-center justify-center ">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="size-12 text-fuchsia-600">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 0 1 0 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 0 1 0-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375Z" />
            </svg>
        </div>

        <h2 class="text-xl font-bold text-gray-900">
            Descargando boleto...
        </h2>

        <p class="mt-2 text-sm text-gray-600">
            El boleto
            <span class="text-fuchsia-600 font-semibold">
                {{ $filename }}
            </span>
            se descargará automáticamente.
        </p>

        <p class="mt-1 text-sm text-gray-600">
            Si no se descarga, puedes hacerlo manualmente:
        </p>

        <button onclick="window.close()"
            class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-lg
                  bg-gray-100 hover:bg-gray-200
                  text-gray-600 text-sm font-semibold transition-colors
                  focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2 cursor-pointer">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-5">
                <path fill-rule="evenodd"
                    d="M5.47 5.47a.75.75 0 0 1 1.06 0L12 10.94l5.47-5.47a.75.75 0 1 1 1.06 1.06L13.06 12l5.47 5.47a.75.75 0 1 1-1.06 1.06L12 13.06l-5.47 5.47a.75.75 0 0 1-1.06-1.06L10.94 12 5.47 6.53a.75.75 0 0 1 0-1.06Z"
                    clip-rule="evenodd" />
            </svg>
            Cerrar ventana
        </button>

        <a href="data:application/pdf;base64,{{ $pdfContent }}" download="{{ $filename }}"
            class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-lg
                  bg-fuchsia-600 hover:bg-fuchsia-700
                  text-white text-sm font-semibold transition-colors
                  focus:outline-none focus:ring-2 focus:ring-fuchsia-500 focus:ring-offset-2">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="size-5">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            Descargar boleto
        </a>
    </div>

    <script>
        setTimeout(function() {
            const link = document.createElement('a');
            link.href = 'data:application/pdf;base64,{{ $pdfContent }}';
            link.download = '{{ $filename }}';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }, 500);
    </script>
</body>

</html>
