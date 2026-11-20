# Proposal

## Why

Varios detalles chicos del panel generan confusión o fricción en el uso diario: el boleto de vuelta del PDF muestra el número de la ida (ruido para el pasajero), no hay una forma rápida de ver solo los viajes de hoy, el modal de "Resetear contraseña" de clientes muestra códigos `\u00..` en vez de letras y aparece aunque el panel de clientes esté apagado, se puede banear a un cliente sin dejar motivo, el superadministrador no puede corregir los datos del primer administrador, y el botón "Copiar link" usa el color informativo en vez del de la empresa. Son ajustes independientes y de bajo riesgo, por eso van juntos en un solo cambio.

## What Changes

- **PDF del boleto**: el boleto de vuelta de un diferido muestra solo la etiqueta "VUELTA", sin "de N°{id de la ida}".
- **Lista de viajes**: nuevo switch "Mostrar solo los viajes que parten hoy", al lado de "Ver también viajes sin pasajeros", apagado por defecto y conservado en la sesión.
- **Clientes – Resetear contraseña**: se corrigen los textos del modal y de la notificación (tildes y signos reales, redacción clara con voseo). La acción solo se muestra si el parámetro "Panel de clientes activo" está encendido.
- **Clientes – Banear**: el modal de "Banear" pide un motivo obligatorio, que se guarda en el campo existente `comments` ("Motivo / Comentarios"). Habilitar no pide motivo.
- **Usuarios – primer administrador**: un administrador puede editar los datos (y restablecer la contraseña) del primer administrador; nadie puede eliminarlo, ni desde la tabla ni salteando la interfaz.
- **"Copiar link"** (modal de entrega y vista previa del detalle del boleto): pasa al color de la empresa, manteniendo el estilo con contorno sin relleno.

## Capabilities

### New Capabilities
- `client-admin`: administración de clientes desde el panel (banear/habilitar con motivo y resetear contraseña).
- `user-admin`: administración de usuarios del panel, incluida la protección del primer administrador.

### Modified Capabilities
- `ticket-leg-display`: la etiqueta de tramo del boleto de vuelta en el PDF pasa a ser solo "VUELTA".
- `trip-list`: nuevo switch para ver solo los viajes que parten hoy.
- `ticket-delivery-modal`: "Copiar link" usa el color de la empresa (con contorno) en vez del informativo.
- `ticket-detail`: "Copiar link" de la vista previa usa el color de la empresa (con contorno).

## Impact

- `resources/views/tickets/pdf/passenger-tickets.blade.php`
- `app/Filament/Resources/Trips/TripResource.php` (filtros)
- `app/Filament/Resources/Clients/ClientsResource.php` (acciones `toggleBan` y `resetPassword`, y sus copias de escritorio)
- `app/Filament/Resources/Users/UserResource.php` y `app/Policies/UserPolicy.php`
- `resources/views/filament/tickets/delivery-modal.blade.php` y `resources/views/tickets/infolist-pdf-preview.blade.php` (requiere `npm run build` si cambian clases)
- Tests en `tests/Feature/` (PDF de tramos, lista de viajes, clientes, usuarios). Sin migraciones ni dependencias nuevas.
