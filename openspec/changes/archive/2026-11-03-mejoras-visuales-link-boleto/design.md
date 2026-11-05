# Design

## Context

- `/boletos/{token}` (`TicketShareController::show`) renderiza `resources/views/boletos/share.blade.php`. Es un HTML suelto que carga `resources/css/app.css` y el tema `filament/admin/theme.css`, e incluye los mismos blades del infolist admin: `tickets.infolist-summary-header` y `tickets.infolist-sale-details`.
- En el panel, esas tarjetas heredan el color de texto del `<body class="fi-body">` de Filament. En `share.blade.php` el `<body>` no define color de texto, por eso los `<h2>` "Pasajero" y "Detalles de venta" salen negros en modo oscuro.
- El tema hoy se decide una sola vez con `matchMedia` en el `<head>`. No hay selector ni escucha de cambios.
- La notificación del cierre de venta en modo link sale de `ShareLinkNotification::actionsFor()` (Descargar PDF + WhatsApp). La usan `CreateTicket::finishSaleWithShareLink()` y `RescheduleTicket`.
- Las acciones de una notificación Filament se serializan con `Action::toArray()`. `extraAttributes` sobrevive a la serialización. `alpineClickHandler` y los closures no.
- Panel de clientes: "Ver boleto" (`MyTickets`, tabla y notificación de "Elegir fecha") apunta a `clientes.boletos.pdf`, que valida sesión y propiedad por DNI (`ClientTicketAssignmentService::clientOwnsTicket`).
- El token compartible se genera bajo demanda y se persiste (`HasShareToken::shareToken()`).

## Goals / Non-Goals

**Goals:**
- Que `/boletos/{token}` se vea como una página de Filament: encabezado con título y acciones, las mismas tarjetas y los mismos colores en los dos temas.
- Un tema que siga al sistema por defecto, con selector y la misma clave de `localStorage` que el panel.
- Ningún visor de PDF en blanco en mobile.

**Non-Goals:**
- No se cambian los datos visibles, el PDF generado ni los tokens.
- No se convierte la página en un panel o página Livewire de Filament (sigue siendo pública, sin sesión).
- No se cambia el aspecto de las tarjetas dentro del panel admin.

## Decisions

1. **Maquetar con las clases `fi-*` del tema en lugar de montar un panel Filament.** La página replica la estructura del header de Filament: `fi-header` con heading y subheading, acciones a la derecha y `fi-btn` para los botones, con el CSS del tema admin ya cargado. El `<body>` usa las mismas clases base que `fi-body`: `bg-gray-50 text-gray-950 dark:bg-gray-950 dark:text-white`. Eso arregla los títulos en modo oscuro sin tocar los parciales.
   - *Alternativa descartada:* una `Page` Filament en un panel público. Requiere panel sin auth, middleware y layout propios, y es mucho más peso para una sola vista.
   - `resources/css/filament/admin/theme.css` no tiene `@source` para `resources/views/boletos/**`. Se agrega, porque si no las utilidades nuevas de la página no entran en el build del tema.
   - Si una clase `fi-*` no está en el build del tema, se usan las utilidades Tailwind equivalentes (mismos colores `primary`/`gray`). Lo que importa es el resultado visual, no las clases.

2. **Encabezado y datos desde el controlador.** `ticketShow`/`saleShow` pasan además `heading` ("Boleto N°X" / "Boletos de la venta N°X") y `subheading` (Origen → Destino del primer boleto). En la vista de venta, cada bloque de boleto lleva el subtítulo "Boleto N°{id} — {apellido nombre}". La vista no arma textos con lógica.

3. **Tema: mismo contrato que Filament.** Un script inline en el `<head>`, antes del CSS para evitar el parpadeo, lee `localStorage.theme` (`light` | `dark` | `system`, por defecto `system`) y aplica `.dark`. Con `system` escucha `matchMedia('(prefers-color-scheme: dark)').addEventListener('change', …)`. Un selector de tres íconos (sol / luna / monitor) en el encabezado escribe la clave. La lectura y la escritura de `localStorage` van en try/catch, y si falla se usa `system`. Compartir la clave con el panel hace que un vendedor que abre su propio link vea el mismo tema que eligió en el panel.

4. **Vista previa del PDF con detección del lado cliente.** Se muestra el `<iframe>` solo si `navigator.pdfViewerEnabled === true` y el ancho es ≥ `md`. En cualquier otro caso se muestra una tarjeta con el nombre del archivo, "Abrir PDF" (`pdfUrl`, inline, `target=_blank`) y "Descargar PDF". El HTML base trae la tarjeta, y el iframe se inserta por JS con `src` recién en ese momento, así un celular no descarga el PDF de más.
   - *Alternativa descartada:* detectar por User-Agent en el servidor. Es frágil y no cubre escritorios sin visor.

5. **Ícono de WhatsApp como SVG inline.** El logo oficial de WhatsApp va como SVG de un solo path, sin dependencia nueva. Heroicons no tiene marcas.

6. **Notificación: "Abrir link" y "Copiar link" en `ShareLinkNotification`.** El orden es Abrir link (url, nueva pestaña), Copiar link, Descargar PDF y WhatsApp (si hay). "Copiar link" no tiene `url()`: hace `->dispatch('copy-share-link', ['url' => …])`. Livewire dispara ese evento, que sube hasta `window`, y un listener global del panel (`renderHook` `BODY_END` en `AdminPanelProvider`) copia la URL con fallback `execCommand` y muestra una `FilamentNotification` corta "Link copiado". La notificación no se cierra. El texto del body de `CreateTicket` y `RescheduleTicket` se simplifica a qué se vendió y que el link se abrió en otra pestaña, sin repetir los botones.
   - *Por qué no `extraAttributes(['x-on:click' => …])`:* una acción de notificación sin url ni evento se renderiza con un click handler `mountAction(...)` sobre el componente Livewire, que no la conoce. El evento es lo único que sobrevive a la serialización (`toArray`/`fromArray`) y no monta nada.
   - *Riesgo:* `navigator.clipboard` exige un contexto seguro. En http local el fallback usa `document.execCommand('copy')` con un textarea temporal, igual que la página pública.

7. **Panel de clientes: ruta intermedia `clientes/boletos/{ticket}`.** Un método `show` en `ClientTicketPdfController` (o un controlador hermano `ClientTicketController` si queda más claro) valida la sesión del cliente y `clientOwnsTicket`, igual que el PDF. Si `Setting::descargaAutomaticaPdf()` es `false`, redirige a `$ticket->shareUrl()`. Si no, redirige a `clientes.boletos.pdf`. `MyTickets` y la notificación de "Elegir fecha" apuntan a esta ruta.
   - *Por qué no `->url(fn ($record) => $record->shareUrl())` directo:* evaluaría `shareToken()` en cada fila renderizada (escrituras en DB al listar) y la decisión del parámetro quedaría repartida en la UI. La ruta intermedia genera el token solo al hacer click y mantiene la verificación de propiedad del lado servidor.

8. **Admin: acción "Ver link" en `ViewTicket::getHeaderActions()`.** Va junto a "Descargar", con `->url(fn (Ticket $record) => $record->shareUrl(), shouldOpenInNewTab: true)` y `->visible(fn (Ticket $record) => ! Setting::descargaAutomaticaPdf() && ! $record->trashed())`. No pide un permiso extra: quien ya puede ver el boleto puede ver su link.
   - *Por qué `url()` directo, sin ruta intermedia como en el panel de clientes:* es un solo registro por página, así que generar el token al renderizar (si faltaba) es una sola escritura. Además, la autorización ya la resuelve el `ViewRecord` del recurso.
   - *Por qué se oculta con el boleto eliminado:* `findByShareToken` no encuentra registros con soft-delete y el link daría 404.

## Risks / Trade-offs

- [Las clases `fi-*` pueden cambiar en una actualización de Filament] → Se usan las clases del header y de los botones solo como base visual, y el test de render verifica textos y acciones, no clases.
- [`navigator.pdfViewerEnabled` no existe en navegadores viejos] → Sin la propiedad se muestra la tarjeta "Abrir PDF". Es el caso seguro.
- [El link público expone un token permanente y "Ver boleto" del cliente ahora lo genera] → Es el mismo token que ya recibe el pasajero, y solo se genera para boletos del propio DNI.
- [Copiar desde la notificación en http sin TLS] → Fallback con `execCommand`.
- [Abrir el detalle admin con el parámetro desactivado genera y persiste el token del boleto] → Es una escritura idempotente: una sola vez por boleto y el mismo token que recibiría el pasajero.

## Migration Plan

Sin migraciones. Deploy normal más `npm run build`, porque la página usa clases que tienen que estar en el build del tema. Rollback: revertir el commit.
