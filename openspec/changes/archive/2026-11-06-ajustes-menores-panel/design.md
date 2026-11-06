# Design

## Context

Seis ajustes independientes (ver proposal.md). Estado actual relevante:

- **PDF**: `passenger-tickets.blade.php` arma `$legLabel` con `"VUELTA de N°{$outboundTicket->id}"` llamando a `roundTripOutboundTicket()` por cada vuelta. `PendingTicketPdfTest` usa ese texto para verificar la etiqueta y también el orden de las hojas.
- **Viajes**: `TripResource` tiene `Filter::make('include_empty_trips')` con un `Toggle` propio (`columnSpan(2)`, `filtersFormColumns(5)`), y la búsqueda por `trip_id` saltea ese filtro. También existe el filtro `trip_date` (fecha exacta).
- **Clientes**: `ClientsResource` define `toggleBanAction()` y `resetPasswordAction()` como builders, pero las copias de escritorio están **reescritas inline** en vez de llamar a los builders. Los textos del reseteo usan `ñ`, `¿`, etc.: en PHP la secuencia válida es `\u{00f1}`, y en strings con comillas simples no se interpreta nada, así que hoy se ven los códigos literales. El motivo del baneo ya existe: columna `comments` ("Motivo / Comentarios"). El parámetro del panel es `Setting::panelClientesActivo()`.
- **Usuarios**: `UserResource` excluye `id = 1` (superadmin) de la tabla y deshabilita Edit/Delete/ForceDelete/Restore/ResetPassword con `$record->id === 2` (primer administrador), para todos. `UserPolicy` no tiene ninguna guarda para ese usuario.
- **"Copiar link"**: `<x-filament::button color="info" outlined>` en `delivery-modal.blade.php` (1 lugar) e `infolist-pdf-preview.blade.php` (2 lugares).

## Goals / Non-Goals

**Goals:**
- Que cada copia móvil/escritorio de una acción de fila salga del mismo builder, para que las reglas nuevas (motivo, visibilidad por parámetro) no se desincronicen.
- Que la protección del primer administrador se aplique también en el servidor, no solo deshabilitando botones.

**Non-Goals:**
- Pedir motivo al desactivar "¿Puede comprar boletos?" desde el formulario de edición del cliente: el pedido es sobre el modal de "Banear". Queda como posible ajuste posterior.
- Cambiar la etiqueta "Vuelta" en los resúmenes del panel (infolist, wizard): solo cambia el PDF.
- Reemplazar el ID fijo `2` por otra forma de identificar al primer administrador (por ejemplo, el admin de menor ID): se mantiene la convención actual.

## Decisions

1. **Etiqueta "VUELTA" fija.** Se elimina la llamada a `roundTripOutboundTicket()` en la vista (además ahorra una consulta por vuelta). El test de orden de hojas pasa a comparar posiciones por otro marcador único de cada boleto (su N°), no por la etiqueta.

2. **Switch "solo hoy" como un `Filter` separado** (`only_today_trips`) con su propio `Toggle`, igual que `include_empty_trips`, en vez de agregar un segundo toggle dentro del mismo filtro: así cada uno persiste y se indica por separado. Query: `whereDate('trip_date', today())`. Si hay un `trip_id` buscado, se saltea (misma regla que el filtro de vacíos). Con el filtro de fecha puesto en otro día, el resultado es la intersección (vacía): se acepta por simple y predecible. Para que los dos switches queden lado a lado se ajustan `columnSpan`/`filtersFormColumns` según cómo se vea; se verifica en el navegador.

3. **Banear con formulario en la acción.** `toggleBanAction()` agrega `->schema(fn (Clients $record) => $record->can_buy ? [Textarea::make('comments')->label('Motivo del baneo')->required()->maxLength(65535)->validationMessages([...])] : [])` y `fillForm` con el `comments` actual. En `action(array $data)`: si banea, guarda `can_buy = false` y `comments = $data['comments']`; si habilita, solo `can_buy = true`. Se quita `requiresConfirmation()` cuando hay formulario (el propio modal confirma) y se mantiene para "Habilitar". Alternativa descartada: una columna nueva `ban_reason` (el usuario pidió usar el campo existente).

4. **Desduplicar las acciones de clientes.** Las copias de escritorio pasan a ser `static::toggleBanAction()->button()->hiddenLabel()->extraAttributes([...])` y lo mismo con `resetPasswordAction()`, como indica la convención del panel.

5. **Visibilidad de "Resetear contraseña".** `->visible(fn () => Setting::panelClientesActivo() && Auth::user()?->can('clients.reset_password'))`. Con la acción oculta, Filament tampoco la ejecuta, así que no hace falta una guarda extra en `action()`. Los textos se reescriben con caracteres reales, por ejemplo: modal "¿Querés resetear la contraseña de {nombre}? Va a entrar con su DNI como contraseña y, al ingresar, va a tener que elegir una nueva."; sin contraseña propia: "{nombre} todavía no eligió su propia contraseña: ya entra con su DNI."; notificación: "{nombre} entra con su DNI y elige una contraseña nueva en el próximo ingreso."

6. **Primer administrador.**
   - Constante `User::FIRST_ADMIN_ID = 2` y helper `User::isFirstAdmin(): bool`, para no repetir el `2` mágico.
   - `UserPolicy::delete()` y `forceDelete()` devuelven `false` si `$model->isFirstAdmin()`; `update()` exige además ser administrador (`hasAnyRole([Permissions::ROLE_ADMIN, Permissions::ROLE_SUPER])`) para ese usuario. El usuario eligió que lo pueda editar cualquier administrador, no solo el superadministrador. Filament usa la policy para habilitar o deshabilitar las acciones, y la regla vale también fuera de la interfaz. Como el `Gate::before` de `AppServiceProvider` da todo al superadministrador antes de consultar las policies, ahí se agrega una excepción: para `delete` y `forceDelete` del primer administrador devuelve `null`, y así decide la policy (que lo rechaza).
   - En `UserResource`: `EditAction` y `resetPasswordAction` se deshabilitan con `isFirstAdmin() && ! administrador`; Delete/ForceDelete siguen deshabilitados siempre para él (se mantiene el `disabled` visual además de la policy). Restore queda como está (no se puede borrar, así que nunca aplica).
   - El `Select::make('roles')` se deshabilita (`->disabled(fn (?User $record) => $record?->isFirstAdmin())`) para que no se le saque el rol de Administrador. Al estar deshabilitado no se deshidrata, así que los roles no se tocan al guardar; se verifica en el test.
   - La tabla de usuarios no tiene acciones masivas, así que no hay borrado en lote que cubrir.

7. **"Copiar link"** pasa de `color="info"` a `color="primary"`, manteniendo `outlined`. Es una prop del componente de Filament, no clases Tailwind nuevas, así que `BrandColorUsageTest` no se afecta.

## Risks / Trade-offs

- [El modal de "Banear" deja de ser una confirmación simple y pasa a tener un formulario] → el texto de la descripción se mantiene, así el usuario sigue viendo qué implica.
- [Al deshabilitar `roles` en la edición del primer admin, un `required` sobre ese campo podría fallar] → el campo deshabilitado no se valida; se cubre con el test de edición por superadministrador.
- [Los dos switches de la lista de viajes pueden no entrar lado a lado en el celular] → en celular se apilan; es aceptable y se revisa visualmente.
- [Tests existentes que esperan "VUELTA de N°"] → se actualizan en este cambio (`PendingTicketPdfTest`).
