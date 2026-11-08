# Proposal

## Why

El aviso de "Mis boletos" con varios boletos sin fecha ofrece un botón "Elegir fecha" que abre, sin decirlo, el boleto sin fecha más antiguo. El cliente no sabe cuál va a abrir, y elegir por él es arbitrario cuando los boletos no son una ida y su vuelta. Además repite lo que ya muestra la pestaña "Pendientes", donde cada fila tiene su propio botón.

## What Changes

- Con **un solo** boleto sin fecha, el aviso nombra su tramo y su número ("Tenés 1 boleto sin fecha: Yrigoyen → Salta, N°39114") y su botón "Elegir fecha" abre ese boleto.
- Con **dos o más**, el aviso dice cuántos son y su botón pasa a ser "Ver pendientes": lleva a la pestaña "Pendientes", donde el cliente elige cuál, y no abre ningún formulario.
- El aviso sigue apareciendo siempre que haya boletos sin fecha, en cualquier pestaña, pero "Ver pendientes" se oculta si el cliente ya está en "Pendientes".

## Capabilities

### New Capabilities
<!-- Ninguna. -->

### Modified Capabilities
- `client-tickets-panel`: cambia el requisito "Aviso de boletos pendientes de fecha".

## Impact

- Código: `app/Filament/Client/Pages/MyTickets.php` (datos del aviso y un método para ir a "Pendientes") y `resources/views/filament/client/pending-notice.blade.php`.
- Tests: `tests/Feature/ClientPanelTicketsTest.php` (los del aviso).
- Sin migraciones, dependencias ni permisos.
