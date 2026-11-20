# Tasks

## 1. Parámetro y regla de anticipación

- [x] 1.1 En `Setting`, agregar la constante `clients.anticipacion_horas`, su valor por defecto (24) y `clientAdvanceNoticeHours(): int`, que devuelve 24 si no hay valor o es inválido (no entero o fuera de 0–720). Verificar con un test de que sin configurar da 24, con 48 da 48, con 0 da 0 y con "abc", "-5" o "9999" vuelve a 24.
- [x] 1.2 Crear `App\Support\ClientAdvanceNotice` con la hora mínima, la comprobación de si un horario de una fecha la cumple (fecha elegida más la hora de salida del horario, la del origen del servicio) y el mensaje con las horas. Verificar con tests de: horario dentro y fuera del plazo, exactamente en el límite, una parada del pasajero con hora distinta de la salida del origen (se usa la del origen), 0 horas sin exclusiones, y valor por defecto.

## 2. Parámetro en Parámetros > Clientes

- [x] 2.1 En `TicketSettings`, agregar el campo de anticipación mínima (entero 0–720, obligatorio, mensajes en voseo, texto "0 = sin mínimo" y hint sobre el PDF), mostrarlo según el interruptor `panel_clientes_activo` en vivo, sumarlo a `TAB_FIELDS`, cargarlo en `getFormData()` y guardarlo solo si llegó en el estado y quien guarda tiene el permiso de la pestaña. Verificar en `TicketSettingsTest` con: valor inicial 24, guardar 48 y 0, rechazo de texto, decimal, negativo, mayor que 720 y vacío, campo oculto con el panel apagado, aparece al encender el interruptor, guardar con el campo oculto conserva el valor, y un usuario sin permiso no lo cambia.

## 3. Panel de clientes: horarios y control en el servidor

- [x] 3.1 En `ClientTicketAssignForm`, filtrar los horarios ofrecidos con `ClientAdvanceNotice` para la fecha elegida (ida o vuelta), y mostrar el texto de "no hay horarios con Nhs de anticipación, elegí otro día" cuando no queda ninguno. Verificar en `ClientPanelTicketsTest` con: horario fuera de plazo no ofrecido, horario en plazo ofrecido, tramo de vuelta, día sin ningún horario con el aviso, y con 0 horas todos ofrecidos.
- [x] 3.2 En `ClientTicketAssignmentService::assign()`, rechazar después de los días de servicio un horario que no cumple la anticipación, con `ValidationException` sobre `schedule_id` y "Elegí un horario con al menos Nhs de anticipación.", dejando el boleto sin fecha. Verificar con tests de: pedido fuera de plazo sin pasar por el selector, parámetro subido con el selector abierto, que la reprogramación del administrador no se ve afectada, y que un día sin servicio sigue rechazándose.

## 4. Aviso en el PDF

- [x] 4.1 Leer la anticipación en `passenger-tickets.blade.php` (como las líneas de contacto) y usarla en el aviso del boleto sin fecha, omitiendo la condición de anticipación con 0 horas y sin tocar la política de cambios y devoluciones. Verificar en `PendingTicketPdfTest` con: 24 por defecto, 48 configurado, cambio de configuración, 0 horas, panel de clientes apagado, boleto con fecha sin el aviso, y que las condiciones de cambios y devoluciones siguen igual.

## 5. Cierre

- [x] 5.1 Correr `vendor/bin/pint --dirty --format agent` y `php artisan test --compact --filter="TicketSettings|ClientPanel|PendingTicketPdf|ClientAdvanceNotice|TicketPdfConsistency|BrandColorUsage"`; verificar que todo pasa.

## Workflow follow-up

- Preguntar si se corre la suite completa antes de archivar.
- Archivar el cambio cuando se haya probado en el navegador.
