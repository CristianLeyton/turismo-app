# Design

## Context

Ver proposal.md (Why) para la motivación. Estado actual relevante:

- `TicketShareController::show` arma la vista `boletos.share` para tokens de boleto (`t_`) y de venta (`s_`). `pdf` sirve el PDF inline o como adjunto.
- `CreateTicket` (Filament `CreateRecord`), en modo link, ejecuta `finishSaleWithShareLink()` desde `handleRecordCreation`:
  - guarda la URL en `session('auto_download_url')`;
  - `afterCreate()` la abre con `window.open`;
  - manda una notificación con `ShareLinkNotification::actionsFor()`;
  - `getRedirectUrl()` siempre devuelve `/admin`.
- `CreateRecord::create()` de Filament redirige siempre al final, salvo `Halt`, que revierte la transacción. No hay forma limpia de quedarse en la página.
- `RescheduleTicket` hace lo mismo: notificación, `window.open` y redirección a `view`.
- `WhatsAppShare::forSale()` junta los teléfonos de la venta (pasajero y padrón por DNI), los normaliza (`normalizePhone`) y arma `wa.me`. No registra a qué pasajero pertenece cada teléfono.
- El listener de "Copiar link" (`ShareLinkNotification::copyListenerScript`, inyectado en `AdminPanelProvider` con `BODY_END`) existe porque una notificación serializada no puede llevar un handler de Alpine.

## Goals / Non-Goals

**Goals:**
- Un solo modal reutilizable para la venta y la reprogramación, con todas las acciones resueltas en el navegador mediante enlaces reales. Así no lo bloquea el bloqueador de ventanas emergentes.
- Que el modal no vuelva a abrirse al recargar.
- Quitar el código muerto: la vista `boletos.share`, `ShareLinkNotification` y el listener de copiar.

**Non-Goals:**
- Migración de datos: la columna `sales.share_token` y el trait en `Sale` quedan como están.
- Cambios en el flujo con la descarga automática activada.
- Enviar los PDF como adjunto por WhatsApp. Solo se mandan links por `wa.me`.

## Decisions

### 1. `/boletos/{token}` responde con el PDF inline
`show()` delega en la misma respuesta que `pdf(..., 'inline')`. Se conserva la ruta `boletos.share.pdf` para la descarga (`?disposition=attachment`): así `sharePdfUrl('attachment')` sigue igual y "Descargar PDF" no necesita una ruta nueva. Los tokens `s_` dejan de resolverse y dan 404 en las dos rutas. Se eliminan las ramas `saleShow`, `salePdf` y `saleFilename`, y la vista `resources/views/boletos/share.blade.php`.

- *Alternativa:* compartir `/boletos/{token}/pdf`. Se descarta porque da un link más largo y deja `show` sin sentido.
- *Alternativa:* redirigir `show` al PDF. Se descarta porque suma un viaje de ida y vuelta y deja ver otra URL.

### 2. El modal se abre por redirección con flash, con `defaultAction` de Filament
No se puede evitar la redirección de `CreateRecord::create()`, así que se aprovecha:
- En modo link, `getRedirectUrl()` devuelve `TicketResource::getUrl('create')`: la página de venta nueva y vacía.
- Antes de redirigir, se guarda en la sesión flash `ticket_delivery` = `{kind: 'sale'|'reschedule', ticket_ids: [...], sale_id}`.
- `CreateTicket::mount()` y `ViewTicket::mount()` leen el flash y, si existe, asignan `$this->defaultAction = 'entregaBoletos'` con `defaultActionArguments` (ids). Filament abre el modal al cargar.
- Con el flash, el modal no vuelve a abrirse al recargar. "Nueva venta" es simplemente cerrar el modal, porque la página ya está vacía. Cerrar con la X o con Escape equivale a lo mismo.
- La reprogramación redirige a `view`, como hoy, y se le suma el flash.

Alternativas descartadas:
- Abrir el modal sin redirigir, sobrescribiendo `create()` entero. Duplica lógica interna de Filament y es frágil ante actualizaciones.
- Usar un query param `?entrega=ID`. Se reabre al recargar y queda en el historial.
- Mostrar el modal en el dashboard. Obliga a volver a entrar a la venta y separa la confirmación del lugar donde se vendió.

### 3. Acción reutilizable `TicketDeliveryAction`
Es una clase en `app/Filament/Actions/TicketDeliveryAction.php` con un `make(string $kind)` que devuelve un `Filament\Actions\Action`:
- `modalHeading` según el tipo, el texto con la cantidad e ícono de éxito.
- `modalContent(view('filament.tickets.delivery-modal', ...))`.
- `modalSubmitAction(false)`.
- Para la venta: `modalCancelActionLabel('Nueva venta')` y `extraModalFooterActions` con "Ir al inicio" (`url('/admin')`). Para la reprogramación: `modalCancelActionLabel('Cerrar')`.
- `modalWidth` amplio, para que las filas entren en el escritorio. En el celular, las acciones de cada fila van en una grilla de dos columnas.

Los argumentos son ids de boletos, así que la acción vuelve a cargarlos y autoriza cada uno con `can('view', $ticket)`. Un id manipulado no expone links ajenos.

### 4. Datos del modal: `App\Support\TicketDelivery`
Es una clase de apoyo que, para una lista de boletos de ida, arma:

```
rows[]:  { id, title: "Boleto N°id — Apellido Nombre", route: "Origen → Destino",
           link: shareUrl(), download: sharePdfUrl('attachment'),
           defaultPhone: string|null }
phones[]: { name, phone (normalizado), label: "nombre — tel" }   // de la venta, sin repetir
template: Setting::whatsappMensaje()
```

- **Selección de filas en la venta:** un boleto por pasajero, el de ida (`is_return_leg = false`, el más antiguo). Es el mismo criterio que usa hoy `finishSaleWithShareLink` para un pasajero.
- **Teléfono preseleccionado:** primero el teléfono del pasajero de la fila, después el del padrón por su DNI y, si no hay ninguno, el primero de `phones`.
- **Teléfonos por pasajero:** `WhatsAppShare` se refactoriza para exponer los candidatos con el `passenger_id` de origen (`candidatesForSale()`). `forSale()` queda sin uso y se quita, junto con sus usos en el controlador.

### 5. Interacción en el navegador con Alpine
La vista del modal es un componente Alpine. No hay viajes a Livewire para elegir el número:
- Estado por fila: `selected` (teléfono u `'other'`) y `manual`.
- `normalize(phone)` reproduce `WhatsAppShare::normalizePhone`: solo dígitos, sin el 0 inicial y con el prefijo `54` si no empieza con 54 y tiene 10 dígitos o menos. Se valida con un mínimo de 8 dígitos. Se cubre con un test que compara los casos de la regla PHP contra la vista renderizada (ver Riesgos).
- Las URLs `wa.me` se arman con `encodeURIComponent(template.replace('{link}', ...))`. Se aplican a `<a target="_blank">` reales, y el botón queda deshabilitado (`aria-disabled` y sin `href`) si el número no es válido.
- "Enviar todos": `{link}` se reemplaza por `"\n" + rows.map(r => `${r.name} (N°${r.id}): ${r.link}`).join("\n")`. El nombre usa el formato "Apellido Nombre", el mismo del título de la fila.
- "Copiar link": `navigator.clipboard.writeText` con un `textarea` de respaldo para http local, y el estado "¡Copiado!" por fila durante 2 segundos. Como es un click real del usuario, no necesita el evento global.
- El texto de la plantilla y los links se pasan con `@js(...)`, no interpolados.

### 6. Texto por defecto del mensaje
`Setting::WHATSAPP_MENSAJE_DEFAULT = 'Podés acceder a tu(s) boleto(s) desde aquí: {link}'`. No se toca el valor guardado de quien ya lo personalizó.

### 7. Limpieza
- Se eliminan `ShareLinkNotification` y el render hook `copyListenerScript` de `AdminPanelProvider`.
- Se quitan de `CreateTicket` el `window.open` de `afterCreate` para el modo link y la notificación de `finishSaleWithShareLink`. `afterCreate` sigue abriendo la descarga en modo PDF.
- `finishSaleWithShareLink` pasa a solo calcular las filas y escribir el flash. Si falla, deja la notificación de advertencia que ya existe, sin flash.

### 8. Detalle del boleto
- `ViewTicket::mount()` genera el token del boleto (`shareToken()`) en modo link y con el boleto vigente: así la vista previa ya tiene el link para copiar y "Enviar por WhatsApp" no necesita una ruta intermedia.
- "Enviar por WhatsApp" es una acción del encabezado con URL `wa.me` armada en PHP (`WhatsAppShare::url`) hacia `TicketDelivery::phoneForTicket()`: el teléfono del pasajero y, si no, el del padrón, sin caer al primero de la venta (a diferencia del modal, acá no hay selector para corregirlo).
- Se eliminan la ruta `admin.boletos.link`, su controlador y sus tests: solo los usaba "Ver link".

## Risks / Trade-offs

- **[Riesgo]** La normalización del teléfono queda duplicada en PHP y en JS y puede divergir. → *Mitigación:* los teléfonos de la venta llegan ya normalizados desde PHP, así que JS solo normaliza lo que se escribe a mano. Un test feature renderiza el modal y verifica las URLs `wa.me` de los teléfonos de la venta. Los casos de la regla JS se documentan junto a `normalizePhone`.
- **[Riesgo]** Si `defaultAction` no se dispara después de la redirección (por ejemplo, con navegación SPA), el vendedor no ve la confirmación. → *Mitigación:* el flash se lee en `mount()`, que corre en cada carga completa o SPA. Un test de Livewire verifica que la página monta con la acción abierta (`assertActionMounted`).
- **[Trade-off]** En Android el link descarga el PDF en lugar de mostrarlo. Lo aceptó el usuario.
- **[Trade-off]** Los links de venta `s_` ya entregados dejan de funcionar (404). Lo aceptó el usuario, porque todavía no hay uso real.
- **[Riesgo]** Un mensaje con muchos boletos puede ser largo para `wa.me`. → Con el máximo de pasajeros por venta y links de unos 60 caracteres, el texto queda muy por debajo de los límites prácticos de URL. No hace falta tratarlo.

## Migration Plan

Deploy normal, sin migraciones:
1. `npm run build`, porque hay clases Tailwind nuevas en `resources/views/filament/tickets/`, que ya están cubiertas por `@source`.
2. `php artisan view:clear`.

Rollback: revertir el commit. Los tokens de boleto existentes siguen sirviendo.
