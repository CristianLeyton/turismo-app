# Tasks

## 1. Consultas del servicio (pendientes, próximos, historial, próximo viaje)

- [x] 1.1 Agregar a `ClientTicketAssignmentService` las consultas de boletos pendientes, próximos (viaje de hoy en adelante) e historial (viaje anterior a hoy), más la del próximo viaje con orden por fecha, horario y número. Verificar con tests de servicio en `tests/Feature/ClientPanelTicketsTest.php` que: cada boleto cae en una sola pestaña, un viaje de hoy va a Próximos, un boleto eliminado no aparece y los boletos de otro DNI no se cuentan.
- [x] 1.2 Cubrir el desempate del próximo viaje (mismo día y horario → menor número) y que un boleto sin fecha nunca sea próximo viaje; verificar con tests que pasan con `php artisan test --compact --filter=ClientPanelTicketsTest`.

## 2. Pestañas, estado vacío y tabla en "Mis boletos"

- [x] 2.1 En `MyTickets`, reemplazar `$mostrarFechados` y el switch de `my-tickets.blade.php` por las pestañas Pendientes / Próximos / Historial con badge de conteo, eligiendo "Pendientes" como inicial solo si hay boletos sin fecha. Verificar con tests Livewire de conteos, pestaña inicial y que ningún boleto ajeno aparece; actualizar `test_por_defecto_ve_solo_pendientes_y_el_switch_incluye_fechados`.
- [x] 2.2 Agregar el estado vacío propio de cada pestaña (`emptyStateHeading`/`emptyStateDescription`, en voseo). Verificar con un test que un cliente sin boletos ve el mensaje de cada pestaña.
- [x] 2.3 Pasar `BadgeColumn` a `TextColumn::badge()` conservando textos y colores, y dejar las columnas secundarias con `visibleFrom`. Verificar que los tests de tramos ("Ida", "Vuelta de N°X", "Solo ida") siguen pasando.
- [x] 2.4 Declarar las acciones de fila en sus dos variantes (menú `md:hidden` y botones `hidden md:inline-flex`) con "Elegir fecha" siempre visible en escritorio y solo en boletos sin fecha, usando un método por acción para no desincronizarlas. Verificar con un test que inspecciona la configuración de la tabla (no el HTML) y con tests de que un boleto con fecha no ofrece "Elegir fecha".
- [x] 2.5 En el `catch (Throwable)` de "Elegir fecha", hacer `report($e)` y mostrar un texto fijo sin `getMessage()`. Verificar con un test que fuerza una excepción y comprueba que la notificación no contiene su mensaje, y que una `ValidationException` sigue mostrando su motivo.

## 3. Aviso de pendientes y tarjeta de próximo viaje

- [x] 3.1 Crear la vista del aviso de boletos sin fecha (singular/plural, `warning`, dark mode) con el botón que monta "Elegir fecha" del boleto sin fecha de menor número, y mostrarla solo con pendientes. Verificar con tests de que aparece con 2 y con 1 pendiente, no aparece sin pendientes y el botón abre el formulario del boleto más antiguo.
- [x] 3.2 Crear la tarjeta "Próximo viaje" (recorrido, fecha, horario, asiento y "Ver boleto", con `primary` y `dark:`), mostrada solo si hay próximo viaje, y que "Ver boleto" use la misma ruta que la tabla. Verificar con tests de que muestra el viaje más cercano, no aparece sin viajes futuros y salta un boleto eliminado.

## 4. Contacto con llamada y WhatsApp

- [x] 4.1 Agregar a `Setting` el método que arma el número para WhatsApp (dígitos con código de país: "+" tal cual; sin "+", sin "0" ni "15" y con prefijo 549), sin tocar `contactLines()` ni el teléfono guardado. Verificar con un test unitario de los casos "+54 9 (3878) 804-506", "3878-804506" y "03878-804506" → 5493878804506, y que `dial` no cambia.
- [x] 4.2 Crear la vista "¿Necesitás ayuda?" con una fila por línea (nombre opcional, teléfono, botones "Llamar" `tel:` y "WhatsApp" `wa.me`), mostrarla en "Mis boletos" solo si hay líneas, en el orden cargado. Verificar con tests de dos líneas, línea sin nombre, sin líneas y cambio de configuración.

## 5. Rediseño de "Elegir contraseña"

- [x] 5.1 Reescribir `SetPassword` con un formulario de Filament (`TextInput::password()->revealable()`, mínimo 6, repetición con `same`, mensajes en voseo) y quitar el HTML y los SVG de `set-password.blade.php`, con saludo por nombre y texto cálido sin "la conocemos todos en boletería". Verificar con tests de: saludo por nombre, contraseña corta, no coinciden, y que al guardar se levanta la obligación y redirige a "Mis boletos".
- [x] 5.2 Hacer que un error inesperado al guardar haga `report($e)` y muestre un texto fijo sin `getMessage()`; mantener la acción "Salir" y el middleware existente. Verificar con un test que fuerza la excepción y comprueba que el mensaje no se muestra, y que `ClientAuthenticationTest` sigue pasando.

## 6. Reconfirmación al elegir fecha

- [x] 6.1 Convertir "Elegir fecha" en un asistente de dos pasos (`ClientTicketAssignForm::steps()`, vista `assign-confirmation`, `MyTickets::assignAction` con `steps()` y el botón "Sí, confirmar fecha"), con `Halt` si falta el asiento al salir del paso 1. Verificar en `ClientPanelTicketsTest` con: la acción tiene dos pasos, el segundo pregunta "¿Estás seguro de confirmar esta fecha?" y avisa que no se podrá cambiar, resume tramo, fecha, horario y asiento, sin asiento no se pasa al segundo paso (se avisa y no se emite `next-wizard-step`), confirmar asigna la fecha y "Elegir fecha" deja de ofrecerse, y el botón final dice "Sí, confirmar fecha".

## 7. Cierre

- [x] 7.1 Revisar que todas las vistas nuevas usen `primary`, tengan `dark:` y pasen `BrandColorUsageTest`; correr `vendor/bin/pint --dirty --format agent` y `php artisan test --compact --filter=Client`. Verificar que todo pasa y avisar que hay que correr `npm run build` para ver las clases nuevas.

## Workflow follow-up

- Preguntar si se corre la suite completa antes de archivar.
- Archivar el cambio cuando se haya revisado y probado en el navegador.
