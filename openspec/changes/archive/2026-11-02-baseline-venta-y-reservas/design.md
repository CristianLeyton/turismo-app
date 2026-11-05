# Design

## Context

Este change no implementa nada: releva el comportamiento actual de la venta y de la reserva de asientos y lo fija en specs (ver `proposal.md`, sección Why). El "diseño" consiste en cómo se relevó, en qué criterios se usaron para decidir qué es contrato, y sobre todo en la lista de **hallazgos**: comportamientos que las specs registran tal como son, pero que parecen defectos o decisiones a revisar.

Fuentes relevadas: el asistente de venta y la página de emisión de boletos del panel, la regla centralizada de corte de venta, los modelos de venta, boleto, viaje, horario y reserva, la API de reservas, el selector de asientos (Alpine.js) y el comando de limpieza. Laravel 12 y Filament 4, sin Octane (cada pedido Livewire es un proceso PHP nuevo).

## Goals / Non-Goals

**Goals:**
- Que cada requisito de las specs pueda señalar el código o el test que lo cumple.
- Separar con claridad "qué hace el sistema" (specs) de "qué parece estar mal" (hallazgos).

**Non-Goals:**
- Corregir hallazgos. Cada uno se trata, si se decide, en un change propio.
- Documentar la edición o reprogramación de boletos más allá de lo que la venta deja preparado.

## Decisions

### D1. Las specs describen el comportamiento real, aunque sea defectuoso
Cuando código y comentario se contradicen, manda el código. Por ejemplo, la liberación de reservas al cambiar la búsqueda está comentada como existente pero nunca se ejecuta (H3), así que la spec dice que no ocurre.
- *Alternativa descartada:* describir la intención del comentario. Daría specs que fallan contra el sistema real y ocultaría el defecto.

### D2. Hallazgos fuera de las specs
Cada rareza se registra en este documento, con evidencia y severidad, y la spec solo registra el comportamiento observable. Al corregir un hallazgo, su change modifica la spec con un `MODIFIED`.

### D3. Nivel de detalle
Se incluyen mensajes visibles al vendedor, nombres de permisos y valores de configuración, porque son contrato observable (los ven los usuarios y se configuran en la matriz de roles). No se nombran clases ni métodos.

### D4. Capacidades separadas
`seat-reservation` se separa de `ticket-sale` porque también la reutilizan la reprogramación de boletos y la asignación de fecha desde el panel de clientes, con la misma duración de 5 minutos.

## Hallazgos

Severidad: **Alta** = afecta ventas reales hoy · **Media** = datos inconsistentes en casos puntuales · **Baja** = deuda o riesgo acotado.

### H1. La app no programa la limpieza de reservas vencidas; la cubre un cron externo (Baja)
- **Qué pasa:** el comando `app:cleanup-expired-reservations` existe, pero la app no programa ninguna tarea: `php artisan schedule:list` responde "No scheduled tasks have been defined.". La programación está escrita en `app/Console/Kernel.php` y en `config/schedule.php`, y ninguno de los dos se carga en Laravel 12. **En producción el comando lo ejecuta un cron del servidor** (confirmado por el responsable del proyecto), fuera del scheduler de Laravel.
- **Por qué importa (riesgo residual):** la tabla de reservas tiene un índice único (viaje, asiento) que incluye las filas vencidas. Una reserva vencida de otra sesión se ignora al calcular disponibilidad, pero impide que otro vendedor reserve ese asiento hasta la próxima corrida del cron. Además, la limpieza depende de configuración del servidor que no está en el repositorio: en un entorno nuevo, sin ese cron, el problema vuelve a ser grave.
- **Deuda menor:** `Kernel.php` y `config/schedule.php` son código muerto que sugiere una programación inexistente.
- **Verificación (2026-11-02):** confirmado en MySQL local, dentro de una transacción revertida: con una reserva vencida de otra sesión sin limpiar, reservar el mismo asiento devuelve "Algunos asientos ya están reservados por otro usuario". En SQLite (entorno de tests) el mismo caso responde **500**: el mensaje de violación de unicidad de SQLite nombra las columnas y no el índice, así que el código no lo reconoce. Un test automatizado de este requisito tiene que tener en cuenta esa diferencia.
- **Spec afectada:** `seat-reservation`, requisitos "Unicidad de la reserva por asiento y viaje" y "Limpieza de reservas vencidas a demanda".

### H2. Una ida con precio 0 sin viaje de vuelta queda marcada como tramo de vuelta (Media)
- **Qué pasa:** en la venta mixta (ida con fecha y vuelta sin fecha), la emisión crea la ida con `is_return_leg = false` y comenta "Ida explícita, aunque el precio viniera en 0". Pero el hook `creating` del boleto pone `is_return_leg = true` en todo boleto de ida y vuelta, sin viaje de vuelta y con precio 0, y pisa ese `false`. Lo mismo pasa con una ida sin fecha de ida y vuelta con ambos tramos pendientes, si el precio es 0.
- **Por qué importa:** con precio 0 (cortesía o canje), la venta queda con dos "tramos de vuelta" y ninguna ida. Eso puede afectar la reprogramación, el PDF y los listados que distinguen ida y vuelta.
- **A verificar:** con un test de venta mixta a precio 0.
- **Verificación (2026-11-02): CONFIRMADO.** Con un test temporal (borrado después) se emitió una venta mixta, ida con fecha y vuelta sin fecha, con precio 0: la ida quedó con viaje asignado, `is_return_leg = true` e `isReturnLeg() = true`.
- **Spec afectada:** `ticket-sale`, requisito "Identificación del tramo de vuelta".

### H3. Cambiar la búsqueda no libera las reservas (Media)
- **Qué pasa:** el paso "Buscar viaje" intenta detectar un cambio de búsqueda con una variable `static` dentro de un closure. Sin Octane, cada pedido Livewire es un proceso nuevo y la variable siempre arranca en `null`, así que la condición de liberar nunca se cumple.
- **Por qué importa:** las reservas del viaje abandonado quedan hasta vencer. Con H1, después siguen bloqueando ese asiento para otros vendedores.
- **Verificación (2026-11-02): CONFIRMADO en el navegador.** Se reservó el asiento 13 del viaje 709 (03/11), se volvió al paso 1, se cambió la fecha al 04/11 y se avanzó: en `seat_reservations` la reserva del viaje 709 siguió ahí, con el mismo vencimiento. Efecto colateral: al mostrar el selector del viaje nuevo aparece el aviso "Asientos expirados: Los siguientes asientos expiraron: 13", aunque la reserva no venció. Lo que pasó es que cambió el viaje.
- **Spec afectada:** `seat-reservation`, requisito "Liberación de reservas".

### H4. La emisión no es atómica (Media)
- **Qué pasa:** el panel no tiene transacciones de base de datos habilitadas y la emisión crea en pasos separados la venta, los pasajeros, los boletos de ida y los de vuelta. La creación de boletos con bloqueo confirma los que sí pudo crear aunque falle alguno.
- **Por qué importa:** si un asiento se ocupa en el último momento, pueden quedar ventas sin boletos, pasajeros huérfanos o una ida sin su vuelta. La ventana de carrera es chica, porque antes se verifica con bloqueo, pero existe.
- **Verificación (2026-11-02): CONFIRMADO.** No hay `databaseTransactions()` en `app/Providers`, ni `DB::transaction` o `beginTransaction` en la página de emisión. Solo son transaccionales, por separado, la verificación con bloqueo y la creación de boletos de cada tramo.
- **Spec afectada:** `seat-reservation`, requisito "Emisión no atómica ante fallos tardíos".

### H5. La API de reservas no exige login (Baja)
- **Qué pasa:** `/api/seat-reservations/*` está en el grupo web (con CSRF), pero sin autenticación. `cleanup` lo puede invocar cualquier página con token CSRF. La sesión se identifica por un `session_id` que manda el cliente; el selector inserta el identificador de sesión de Laravel en el HTML. Ante una excepción, `reserve` devuelve el mensaje interno del error.
- **Por qué importa:** quien conozca el identificador de sesión de un vendedor puede liberar o renovar sus reservas. El riesgo de robo de sesión es bajo, porque la cookie va cifrada con la clave de la app, pero el identificador queda expuesto en el DOM ante un XSS.
- **Spec afectada:** `seat-reservation`, requisito "API de reservas para el selector".

### H6. El keep-alive del front nunca se activa (Baja)
- **Qué pasa:** el selector define un keep-alive cada 2 minutos, pero nadie lo invoca. Las reservas se renuevan solo cuando el selector se vuelve a mostrar o se cambia la selección.
- **Por qué importa:** un vendedor que se queda más de 5 minutos en el paso de pasajeros, sin volver al selector, pierde las reservas. Al emitir solo lo protege la verificación final.
- **Verificación (2026-11-02): CONFIRMADO en el navegador.** Durante unos 4 minutos desde la reserva, 2,5 de ellos seguidos en el paso de asientos, hubo 0 pedidos a `/api/seat-reservations/keep-alive`: solo `reserve` y `status`. En el código, `startKeepAlive()` (SEL:463) no se invoca en ningún lugar de `app/`, `resources/` ni `public/`.
- **Spec afectada:** `seat-reservation`, requisito "Renovación de reservas".

### H7. Dos cálculos de disponibilidad distintos (Baja)
- **Qué pasa:** para dejar avanzar el paso 1, los "asientos libres" son la capacidad del colectivo menos los boletos vigentes; ese cálculo ignora asientos inactivos y reservas de otros. La reserva y la emisión sí excluyen asientos inactivos y reservados.
- **Por qué importa:** el paso 1 puede decir que hay lugar y que después el selector no tenga suficientes asientos elegibles.
- **Spec afectada:** `ticket-sale`, requisito "Disponibilidad suficiente para avanzar".

### H8. Domingos deshabilitados por código, con una excepción fija (Baja)
- **Qué pasa:** el calendario deshabilita todos los domingos, salvo el 2026-09-13, que está escrito en el código. No es configurable.
- **Por qué importa:** cada excepción nueva requiere un deploy, y la fecha fija quedará como código muerto.
- **Spec afectada:** `ticket-sale`, requisito "Fechas de viaje seleccionables".

### H9. La base de datos no impide dos boletos vigentes en el mismo asiento (Media)
- **Qué pasa:** la migración `2026_01_28_142357` cambió el índice único `tickets_trip_seat_unique` a (`trip_id`, `seat_id`, `deleted_at`) para liberar los asientos de boletos dados de baja. En MySQL un índice único no compara `NULL` como iguales, y los boletos vigentes tienen `deleted_at = NULL`: el índice ya no rechaza dos boletos vigentes en el mismo asiento. Además, la emisión verifica la disponibilidad con el viaje bloqueado (`lockForUpdate`), pero crea los boletos en otra transacción, ya sin bloqueo.
- **Por qué importa:** dos ventas simultáneas del mismo asiento pueden pasar los controles y crear ambas el boleto. La captura del error de duplicado en la creación de boletos nunca se dispara. Verificado sobre la base local (MySQL): el índice incluye `deleted_at` con `Null: YES`. No hay duplicados en los datos locales al momento de verificar.
- **Detectado:** durante la verificación de trazabilidad (tarea 2.1), no en el relevamiento inicial.
- **Verificación (2026-11-02): CONFIRMADO.** Con un test temporal se crearon dos boletos vigentes para el mismo viaje y asiento sin error de la base. En MySQL local, `SHOW INDEX` muestra `tickets_trip_seat_unique` sobre (`trip_id`, `seat_id`, `deleted_at`) con `deleted_at` nullable.
- **Spec afectada:** `seat-reservation`, requisitos "Verificación final de disponibilidad al emitir" y "Unicidad de boletos vigentes solo por control de la aplicación".

### H10. Abrir la página de venta borra todas las reservas de la sesión (Media)
- **Qué pasa:** `CreateTicket::__destruct` (CT:36-48) elimina todas las reservas de la sesión en cada pedido que no es AJAX ni Livewire. Es decir, cada vez que se carga la página de venta, también en una pestaña nueva o al recargar.
- **Por qué importa:** las pestañas de una misma sesión comparten reservas. Si un vendedor tiene una venta a medio hacer y abre "Vender" en otra pestaña, la primera pierde sus asientos sin aviso, y otro vendedor puede tomarlos. El comentario dice que se ejecuta "solo si el usuario confirma salir", pero no es así: corre al destruir el componente al final del pedido.
- **Detectado:** durante la tarea 2.2, al revisar la liberación al cancelar.
- **Verificación (2026-11-02): CONFIRMADO en el navegador.** Con una reserva vigente de la sesión (viaje 710, 1 fila), abrir `/admin/tickets/tickets/create` en una segunda pestaña dejó 0 filas para esa sesión.
- **Spec afectada:** `seat-reservation`, requisito "Liberación de reservas".

## Verificación en el panel (tareas 1.3 y 2.2)

Hecha el 2026-11-02 con Chrome sobre `https://turismo-app.me`, con un usuario superadmin. Ningún escenario contradijo la spec.

| Escenario | Resultado |
|---|---|
| Domingos deshabilitados salvo el 13/09/2026 | En noviembre 2026, los domingos 1, 8, 15, 22 y 29 aparecen deshabilitados. En septiembre 2026, el 13 está habilitado y el 6, el 20 y el 27 no. Coincide. |
| Vuelta el mismo día con horario posterior | Ida 03/11 a las 13:00 y vuelta 03/11: "No hay horarios disponibles (el horario de vuelta debe ser posterior al de ida)". El único horario de vuelta vigente es el de las 11:00 (el de las 15:50 está dado de baja). La fecha de vuelta no permite días anteriores a la ida. Coincide. |
| Menor y mascotas excluyentes | Al marcar mascotas con el menor ya cargado, se desmarca el menor y, al volver a marcarlo, sus campos están vacíos. Al revés también funciona. Coincide. |
| Acompañantes desactivados en Parametros | No se probó en el navegador, para no modificar la configuración local. Lo respalda TF:2117, donde la visibilidad depende solo de `Setting::permiteAcompanantes()`. |
| Reserva desde otra sesión | Una segunda sesión sin login (curl con su propio token CSRF) recibe **409**: "Los siguientes asientos están reservados por otros usuarios: 10". Coincide. |
| Cancelar la venta | Con "Cancelar" → "Aceptar" se borran todas las reservas de la sesión (también las del viaje abandonado por H3) y redirige a `/admin`. Después, la segunda sesión reserva el mismo asiento con 200. Coincide. |

Datos que dejó la prueba: los viajes 709 (03/11) y 710 (04/11) de Don Esteban quedaron creados, sin boletos, por la resolución automática del viaje del paso 1 (el comportamiento normal del requisito de creación del viaje). Las reservas de prueba se liberaron todas.

## Trazabilidad

Rutas abreviadas: **TF** = `app/Filament/Resources/Tickets/Schemas/TicketForm.php` · **CT** = `app/Filament/Resources/Tickets/Pages/CreateTicket.php` · **SC** = `app/Support/SaleCutoff.php` · **API** = `app/Http/Controllers/Api/SeatReservationController.php` · **SR** = `app/Models/SeatReservation.php` · **SEL** = `resources/views/tickets/seat-selector.blade.php`. Tests en `tests/Feature/`.

### `ticket-sale`

| Requisito | Código | Tests |
|---|---|---|
| Wizard de venta en pasos secuenciales | TF:55, 1286, 1586, 1883, 2510, `skippable(false)` TF:2520 | `TicketSaleCutoffWizardTest` (paso 1) |
| Cantidad de pasajeros por venta | TF:385-430 (`in:1,2,3,4` TF:397) | - |
| Colectivo obligatorio | TF:362; CT:366-378 | `SellWithoutDateTest::test_la_venta_exige_colectivo_incluso_sin_fecha` |
| Horarios ofrecidos para el tramo de ida | TF:552-720 (filtro de corte TF:604, 643), `autoSelectedScheduleId` TF:2598; `scopeBookable` `app/Models/Schedule.php:37` | - |
| Fechas de viaje seleccionables | `getDisabledTravelDates` TF:2801; `minDate` TF:529, 979; SC:60 | `TicketSaleCutoffWizardTest::test_min_selectable_date_*` |
| Resolución del viaje para la venta | `Trip::findOrCreateForBooking` `app/Models/Trip.php:309` | - |
| Disponibilidad suficiente para avanzar | TF:116-160, 253-290; `Trip::remainingSeats` `app/Models/Trip.php:265` | - |
| Corte de venta por fecha anterior | SC:72-89, 113-130 | `TicketSaleCutoffWizardTest::test_la_fecha_anterior_*`, `test_con_horas_en_cero_*` |
| Corte de venta por horas desde la salida | SC:91-110; `Setting::ventaLimiteHoras` `app/Models/Setting.php:114` | `TicketSaleCutoffTest` (8 tests) |
| Puntos de control del corte de venta | TF:207, 340 (paso 1); TF:604, 643, 1094, 1166 (opciones de horario); CT:384-399, 673 (emisión) | `TicketSaleCutoffWizardTest::test_el_paso_1_*`, `TicketSaleCutoffTest::test_el_permiso_esta_en_el_catalogo_*` |
| Venta de ida y vuelta ("Diferido") | TF:750-945; `after_or_equal` TF:1005 | - |
| Horarios ofrecidos para el tramo de vuelta | TF:1019-1250 | `ReturnTripDifferentBusTest` |
| Selección exacta de asientos por tramo | TF:1293-1360, 1592-1660 | - |
| Datos obligatorios del pasajero | TF:1934-2070 | `TicketSaleDniValidationTest::test_el_dni_se_valida_*`, `test_un_dni_valido_*` |
| Autocompletado desde el padrón de clientes | TF:1938-1955 | `TicketSaleDniValidationTest::test_el_dni_del_padron_autocompleta_*`, `test_con_menos_de_7_digitos_*` |
| Bloqueo de clientes con prohibición de compra | TF:1981-1995; CT:270-300 | - |
| Acompañantes del pasajero | TF:2076-2200; `Setting::permiteAcompanantes` `app/Models/Setting.php:123` | `CompanionParamEditTest` (edición) |
| Menor acompañante sin asiento ni boleto propio | CT:414-440 (`parent_passenger_id` CT:436) | - |
| Precio y método de pago por pasajero | TF:2211-2260; CT:333-361 | - |
| Venta sin fecha | TF:492-520, 945-972; CT:249-268, 706 | `SellWithoutDateTest` (permiso, roles, solo ida, toggle apagado) |
| Venta sin fecha solo a pasajeros del padrón | TF:2008-2022; CT:303-330 | `SellWithoutDateTest::test_la_venta_sin_fecha_exige_pasajeros_del_padron`, `TicketSaleDniValidationTest::test_la_venta_sin_fecha_*` |
| Ventas mixtas con un tramo sin fecha | CT:706-912 (`return_trip_id` CT:726, `is_return_leg` CT:782, 830) | `SellWithoutDateTest::test_venta_mixta_*` |
| Estructura de la venta emitida | CT:408, 632; `Sale::createNew`/`recalculateTotal` `app/Models/Sale.php:40-54` | - |
| Boletos de vuelta | CT:543-625 (`price => 0` CT:600); vuelta con fecha en venta mixta CT:873-890 | - |
| Identificación del tramo de vuelta | `Ticket::booted` `app/Models/Ticket.php:71-87`; `isReturnLeg` `app/Models/Ticket.php:136` | - (ver H2) |
| Cierre de una venta exitosa | CT:634-639, 108-118, 918-930 | `TicketShareLinkModeTest` (modo sin descarga) |
| Cancelación de la venta | CT:61-92 | - |

### `seat-reservation`

| Requisito | Código | Tests |
|---|---|---|
| Reserva temporal por sesión | API:16-150 (reemplazo API:64-67, vacío API:41-56); SR:26 | - |
| Asientos no reservables | API:79-125 | - |
| Unicidad de la reserva por asiento y viaje | índice `unique_trip_seat_reservation` en `database/migrations/2026_01_22_160000_create_seat_reservations_table.php:32`; SR:46-50 | - |
| Reservas vencidas no cuentan como ocupación | API:84-87; SR:91-120; `app/Models/Trip.php:156-160, 211-215` | - |
| Renovación de reservas | TF:1535-1545, 1831-1840; API:180-215; SR:81-86 | - |
| Vencimiento visible para el vendedor | SEL:106-250 (5 min SEL:111, espera SEL:174, 247); CT:1641 | - |
| Detección de asientos expirados al volver al selector | TF:1476-1530, 1774-1830 | - |
| Liberación de reservas | CT:36-48 (`__destruct`, H10), 72, 636, 645, 907; SEL:58-72; API:162-175; `static $lastSearchHash` TF:58 (sin efecto, H3) | - |
| Limpieza de reservas vencidas a demanda | `app/Console/Commands/CleanupExpiredReservationsCommand.php`; API:260; SR:66 | - |
| API de reservas para el selector | `routes/web.php:40-46`; API:220-255 | - |
| Verificación final de disponibilidad al emitir | CT:130-245, 464-517; `Trip::reserveSeatsWithLock` `app/Models/Trip.php:131`, `createTicketsWithLock` `app/Models/Trip.php:193` | - |
| Unicidad de boletos vigentes solo por control de la aplicación | `database/migrations/2026_01_28_142357_update_tickets_trip_seat_unique_constraint_to_include_deleted_at.php:19` | - (ver H9) |
| Emisión no atómica ante fallos tardíos | CT:402-660; panel sin `databaseTransactions()` | - (ver H4) |

Los requisitos de `seat-reservation` no tienen tests automatizados: su respaldo es el código y las verificaciones manuales de las tareas 2.2, 2.3 y 3.x.

## Risks / Trade-offs

- [Las specs fijan como contrato comportamientos defectuosos, por ejemplo H3 o la no atomicidad] → Cada uno está vinculado a un hallazgo. Corregirlo implica un change con `MODIFIED` sobre ese requisito, que es justo la traza que se busca.
- [El relevamiento es manual y puede omitir reglas] → Las specs cubren lo observado en código y tests a la fecha; lo que falte se agrega con changes posteriores.
- [El código sin commitear (links compartibles, acompañantes) puede cambiar la venta] → Las specs describen el árbol de trabajo actual. Si esos cambios no se integran, hay que revisar la spec de `ticket-sale`.
