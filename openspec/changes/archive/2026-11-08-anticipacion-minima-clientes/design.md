# Design

## Context

Ver `proposal.md` para la motivación. Estado actual:

- El aviso de boletos sin fecha está escrito a mano en `passenger-tickets.blade.php` ("…con al menos 24hs de anticipación, dentro de las cuales no se aplicaran recargos…"). Las otras "24hs" de esa vista son la política de cambios y devoluciones.
- Los días de servicio (`ServiceDays`) ya aplican al cliente: `ClientTicketAssignForm` deshabilita las fechas con `ServiceDays::disabledTravelDates()` y `ClientTicketAssignmentService::assign()` las rechaza con `blockReason($date, allowBypass: false)`. Es la única fuente de verdad, igual que `SaleCutoff` para la venta fuera de término.
- El selector de horarios del cliente (`ClientTicketAssignForm::scheduleOptions`) lista los horarios de la línea para el tramo, sin mirar la hora actual.
- La salida del servicio es la hora de salida del horario (`Schedule::departure_time`), la del origen de la ruta. El PDF muestra además la hora a la que el colectivo llega a la parada del pasajero, que es otra.
- La pestaña Clientes de `TicketSettings` tiene el interruptor `panel_clientes_activo` y guarda con `array_key_exists`, porque un campo oculto no llega en `getState()`. Los parámetros viven en la tabla de configuración vía `Setting`.

## Goals / Non-Goals

**Goals:**
- Un único valor, configurable, que gobierne tanto la regla del panel de clientes como el texto del PDF.
- Que la regla se compruebe en el selector y en el servidor, con el mismo código.

**Non-Goals:**
- Aplicar la anticipación a la venta, la reprogramación o la creación de viajes del admin.
- Cambiar los días de servicio ni sus excepciones.
- Cambiar la política de cambios y devoluciones del PDF.
- Un mínimo distinto por línea, horario o día.

## Decisions

**1. Parámetro `clients.anticipacion_horas` en la tabla de configuración, por defecto 24.**
`Setting::clientAdvanceNoticeHours(): int` devuelve el valor guardado, o 24 si no hay o es inválido (no numérico o fuera de 0–720). El rango se valida al guardar; el getter solo protege de datos corruptos. Sin migración, igual que los demás parámetros.

**2. Un solo lugar para la regla: `App\Support\ClientAdvanceNotice`.**
Como `ServiceDays`, concentra la regla: la hora mínima (`ahora + horas`), si un horario de una fecha la cumple, y el mensaje con las horas. El selector y el servidor la usan, así no se desincronizan. La salida se calcula como la fecha elegida con la hora de salida del horario (`Schedule::departure_time`), es decir, la salida del origen del servicio. Es una decisión de la empresa: el plazo corre hasta que el servicio sale, aunque el pasajero suba más tarde en otra parada. Con 0 horas no excluye nada. Alternativa descartada: usar la hora de subida del pasajero (`Route::getDepartureTimeForStop`), que depende de la parada del tramo.

**3. El selector filtra los horarios; el servidor rechaza.**
`ClientTicketAssignForm` recibe la fecha elegida y ofrece solo los horarios que cumplen; sin ninguno, el selector muestra un texto que dice que no hay horarios con esas horas de anticipación y que elija otro día. `assign()` vuelve a comprobar después de los días de servicio y lanza `ValidationException` sobre `schedule_id` con "Elegí un horario con al menos Nhs de anticipación." El control del servidor cubre los pedidos manipulados y el cambio del parámetro con el selector abierto. Alternativa descartada: bloquear días enteros en el calendario, porque un día puede tener horarios dentro y fuera del plazo.

**4. El campo se ve según el interruptor del formulario, no según lo guardado.**
`TextInput` entero, 0–720, obligatorio, con `visible()` ligado al estado en vivo de `panel_clientes_activo` (el interruptor pasa a `live()`), así aparece al encenderlo sin guardar. Al guardar se escribe solo si el campo llegó (`array_key_exists`) y quien guarda tiene el permiso de la pestaña; oculto, conserva el valor. Se suma a `TAB_FIELDS` para el badge de errores. El texto visible explica el efecto ("0 = sin mínimo") y el hint aclara que el PDF de los boletos sin fecha usa el mismo número. Alternativa descartada: mostrarlo según el valor guardado, que obligaría a guardar y volver para verlo.

**5. El PDF muestra siempre el valor vigente, con el panel encendido o no.**
La vista del PDF (`passenger-tickets.blade.php`) lee el valor una vez por PDF con `Setting::clientAdvanceNoticeHours()`, igual que ya hace con las líneas de contacto, así los tres generadores del PDF lo comparten sin pasarlo uno por uno. El aviso se arma con una expresión `{{ }}`, no con `@if` pegado al texto, porque Blade no reconoce una directiva sin espacio antes. Con 0 se omite la frase "con al menos Nhs de anticipación, dentro de las cuales no se aplicaran recargos". Un boleto sin fecha puede emitirse con el panel apagado y su PDF ya traía el aviso, así que no depende del panel. Los PDF se generan al pedirlos, por lo que reflejan el valor actual.

## Risks / Trade-offs

- [El cliente elige un horario y mientras tanto pasa el plazo] → El servidor vuelve a comprobar al confirmar y el cliente ve el mensaje con las horas; el boleto sigue sin fecha.
- [Subir el parámetro deja boletos ya impresos con un número viejo] → Un PDF se genera al pedirlo, así que muestra el valor vigente; los archivos ya descargados no cambian.
- [Con 0 horas el cliente podría elegir un horario que ya salió hoy] → Es el comportamiento actual y no se cambia; 0 significa "sin mínimo", como en el resto de los parámetros.
- [Cambiar el interruptor y el campo en la misma pantalla] → Un solo guardado todo-o-nada, como el resto de la página.
