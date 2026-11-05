# Tasks

Este change no modifica código de la aplicación. Las tareas verifican que las specs describan el sistema real y confirman los hallazgos antes de archivar. Los tests que se escriban para confirmar un hallazgo son temporales y no se commitean: el resultado queda registrado en `design.md`.

## 1. Verificación de `ticket-sale`

- [x] 1.1 Correr los tests existentes de venta (`php artisan test --filter="TicketSaleCutoffTest|TicketSaleCutoffWizardTest|SellWithoutDateTest|TicketSaleDniValidationTest|ReturnTripDifferentBusTest"`) y verificar que todos pasan sobre el árbol de trabajo actual
- [x] 1.2 Recorrer cada requisito de `specs/ticket-sale/spec.md` contra el asistente de venta, la emisión y la regla de corte de venta, y agregar en `design.md` una sección "Trazabilidad" que vincule cada requisito con el archivo o test que lo respalda. Verificar que no quede ningún requisito sin respaldo
- [x] 1.3 Probar en el panel los escenarios sin test (domingos deshabilitados salvo el 13/09/2026, vuelta el mismo día con horario posterior, menor y mascotas excluyentes, acompañantes desactivados en Parametros) y verificar que el comportamiento coincide con la spec; si alguno no coincide, corregir la spec

## 2. Verificación de `seat-reservation`

- [x] 2.1 Recorrer cada requisito de `specs/seat-reservation/spec.md` contra la API de reservas, el modelo de reserva, el selector de asientos y la emisión, y completar la sección "Trazabilidad" de `design.md`. Verificar que no quede ningún requisito sin respaldo
- [x] 2.2 Con dos sesiones de navegador distintas sobre el mismo viaje, reservar un asiento en una e intentar reservarlo en la otra. Verificar que la segunda recibe "reservado por otros usuarios" y que, al cancelar la primera venta, el asiento queda libre
- [x] 2.3 Ejecutar `php artisan app:cleanup-expired-reservations` con al menos una reserva vencida y verificar que se elimina e informa la cantidad, como dice el requisito "Limpieza de reservas vencidas a demanda"

## 3. Confirmación de hallazgos

- [x] 3.1 H2: con un test temporal, emitir una venta mixta (ida con fecha, vuelta sin fecha) con precio 0 y verificar si el boleto de ida queda con `is_return_leg = true`. Registrar el resultado (confirmado o descartado) en `design.md` y borrar el test
- [x] 3.2 H3: reservar asientos en un viaje, volver al paso 1, cambiar el horario y verificar en la tabla `seat_reservations` que las reservas del viaje anterior siguen ahí. Registrar el resultado en `design.md`
- [x] 3.3 H6: con las herramientas de red del navegador abiertas en el paso de asientos por más de 2 minutos, verificar que no se llama a `/api/seat-reservations/keep-alive`. Registrar el resultado en `design.md`
- [x] 3.4 H4: revisar que el panel de administración no tenga transacciones de base de datos habilitadas (`databaseTransactions()` en el panel o en la página de emisión) y registrar el resultado en `design.md`
- [x] 3.5 Si algún hallazgo se descarta, corregir el requisito de spec que lo describe para que refleje el comportamiento real, y verificar que la spec y `design.md` no se contradicen

## 4. Cierre

- [x] 4.1 Ejecutar `openspec validate baseline-venta-y-reservas --strict` y verificar que el change es válido

## Workflow follow-up

- Archivar el change con `/opsx:archive` para que `ticket-sale` y `seat-reservation` pasen a `openspec/specs/`.
- Después de archivar, proponer los changes de corrección en este orden: `dias-de-servicio` (H8), `emision-atomica` (H4), `reservas-ciclo-de-vida` (H3, H6, H7, H10 y deuda de H1), `marca-tramo-vuelta` (H2) y `api-reservas-segura` (H5).
