# Tasks

## 1. Página pública: estructura y encabezado

- [x] 1.1 Agregar `@source '../../../../resources/views/boletos/**/*.blade.php'` a `resources/css/filament/admin/theme.css` y verificar con `npm run build` que compila sin errores
- [x] 1.2 En `TicketShareController::ticketShow`/`saleShow` pasar `heading` ("Boleto N°{id}" / "Boletos de la venta N°{venta}") y `subheading` (Origen → Destino). Verificar con un test en `TicketShareLinkModeTest` que `GET /boletos/{token}` de un boleto muestra "Boleto N°{id}" y de una venta muestra "Boletos de la venta N°{venta}"
- [x] 1.3 Rehacer el layout de `resources/views/boletos/share.blade.php` con encabezado estilo Filament (logo, heading, subheading, acciones a la derecha en desktop y a todo el ancho debajo del título en mobile) y `<body>` con `bg-gray-50 text-gray-950 dark:bg-gray-950 dark:text-white`. Verificar a mano en claro y oscuro que "Pasajero" y "Detalles de venta" se leen
- [x] 1.4 En el link de venta, poner el subtítulo "Boleto N°{id} — {apellido nombre}" antes de cada bloque de boleto, con una sola tarjeta de detalles de venta al final. Verificar con un test que la respuesta del link de una venta de 2 pasajeros contiene ambos subtítulos
- [x] 1.5 Verificar con un test que el link de un diferido con vuelta asignada muestra ida y vuelta (texto "Vuelta:") y que un token inexistente sigue dando 404

## 2. Página pública: acciones, tema y vista previa

- [x] 2.1 Mover las acciones al encabezado en el orden WhatsApp(s) → Descargar PDF → Copiar link, con el SVG de WhatsApp en vez del ícono de parlante y el texto de "sin teléfonos" cuando no hay destinatarios. Verificar con un test (`assertSeeInOrder`) para 1 teléfono, varios ("Enviar a {nombre}") y ninguno
- [X] 2.2 Script de tema en el `<head>`: `localStorage.theme` (`light`/`dark`/`system`, por defecto `system`, con try/catch), escucha de `prefers-color-scheme` cuando es `system` y selector de 3 opciones en el encabezado. Verificar a mano: con el SO en oscuro abre oscuro, cambiar el SO con la página abierta la actualiza, y elegir "Claro" persiste al recargar
- [x] 2.3 Vista previa del PDF: tarjeta "Abrir PDF" / "Descargar PDF" por defecto, y el iframe insertado por JS solo si `navigator.pdfViewerEnabled` y el ancho es ≥ md. Verificar con un test que el HTML contiene la URL inline del PDF y "Abrir PDF", y a mano en DevTools modo celular que no queda un visor en blanco
- [x] 2.4 Copiar link con fallback `execCommand` y la confirmación "¡Copiado!". Verificar a mano en http local y en https

## 3. Notificación del panel al vender o reprogramar

- [x] 3.1 En `ShareLinkNotification::actionsFor()` agregar "Abrir link" (url, nueva pestaña) y "Copiar link" (evento `copy-share-link` con la URL + listener del panel con clipboard, fallback y toast "Link copiado"), en el orden Abrir link, Copiar link, Descargar PDF, WhatsApp. Verificar con un test que las acciones devueltas tienen esos nombres en ese orden, que WhatsApp falta sin teléfonos y que "Copiar link" lleva la URL en el evento
- [x] 3.2 Simplificar el body de la notificación en `CreateTicket::finishSaleWithShareLink()` y en `RescheduleTicket`. Verificar con el test existente de modo link de `TicketShareLinkModeTest` (`assertNotified`) y ajustarlo si compara el texto
- [x] 3.3 Verificar en el navegador que "Copiar link" de la notificación copia sin cerrarla y sin navegar

## 4. Panel admin: acceso al link desde el detalle

- [x] 4.1 Agregar la acción `ver_link` ("Ver link", nueva pestaña, `url` = `$record->shareUrl()`) en `ViewTicket::getHeaderActions()`, visible solo si `! Setting::descargaAutomaticaPdf()` y el boleto no está eliminado. Verificar con tests Livewire (`assertActionVisible` / `assertActionHidden`, `assertActionHasUrl`): parámetro desactivado → visible con URL `/boletos/t_…`; activado → oculta; sin configurar → oculta; boleto eliminado → oculta
- [x] 4.2 Correr `php artisan test --compact --filter=ViewTicket` y los tests nuevos, más `vendor/bin/pint --dirty --format agent`, y verificar que pasan

## 5. Panel de clientes: "Ver boleto" según el parámetro

- [x] 5.1 Crear la ruta `clientes/boletos/{ticket}` (`clientes.boletos.show`) con su método de controlador: exige cliente con sesión (403), boleto propio (404) y redirige a `shareUrl()` si `Setting::descargaAutomaticaPdf()` es false o a `clientes.boletos.pdf` si no. Verificar con tests en `ClientPanelTicketsTest`: parámetro desactivado → redirect a `/boletos/t_…`, activado → redirect al PDF, sin configurar → redirect al PDF, boleto ajeno → 404, sin sesión → 403
- [x] 5.2 Apuntar "Ver boleto" de la tabla de `MyTickets` y de la notificación "¡Boleto asignado!" a `clientes.boletos.show`. Verificar con un test que la acción `boleto_pdf` de la tabla tiene esa URL y que la tabla no genera `share_token` al listar
- [x] 5.3 Correr `php artisan test --compact tests/Feature/ClientPanelTicketsTest.php tests/Feature/TicketShareLinkModeTest.php` y `vendor/bin/pint --dirty --format agent` y verificar que pasan

## 6. Verificación integral

- [X] 6.1 Con el parámetro desactivado, hacer una venta de 1 pasajero y otra de 2 en el navegador y verificar que se abre la página nueva, que la notificación ofrece Abrir/Copiar/Descargar/WhatsApp, y que la página se ve bien en claro, oscuro y ancho de celular
- [X] 6.2 Con el parámetro desactivado y luego activado, entrar como cliente a "Mis boletos" y verificar que "Ver boleto" abre la página del link o el PDF, respectivamente
- [X] 6.3 Con el parámetro desactivado, abrir el detalle admin de un boleto y verificar que "Ver link" abre su página; con el parámetro activado, verificar que el botón no aparece
