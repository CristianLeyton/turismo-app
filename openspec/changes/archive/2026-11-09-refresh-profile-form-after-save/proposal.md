# Proposal

## Why

Al editar el perfil y darle a "Regresar" y luego volver a entrar, tanto el panel de administración como el panel de clientes muestran los valores anteriores al guardado hasta que se recarga la página. Los datos sí se guardan en la base: lo que queda desactualizada es la pantalla. El origen es el modo SPA de Livewire (`wire:navigate`), cuyas memorias internas (prefetch con TTL de 30 s y snapshot de back/forward) sirven HTML horneado antes del guardado, y el formulario se llena solo una vez en `mount()`, sin volver a leerse de la base después de `save()`.

## What Changes

- Después de guardar el perfil, el formulario SHALL volver a hidratarse con los datos persistidos en la base (re-llamada a `fillForm()` / re-lectura del usuario), descartando el estado local del componente.
- Aplica a las dos páginas de perfil: `App\Filament\Resources\Users\Pages\Auth\EditProfile` (panel admin, vía proxy `App\Filament\Pages\Auth\EditProfile`) y `App\Filament\Client\Pages\Auth\ClientEditProfile` (panel `/clientes`).
- El botón "Regresar" y la configuración de SPA/prefetch de los paneles NO se modifican en este cambio; el refresco del formulario deja a la pantalla sincronizada con la DB inmediatamente tras guardar.

## Capabilities

### New Capabilities
- `profile-pages`: comportamiento del formulario de perfil en ambos paneles ("Perfil" del panel admin y "Mi perfil" del panel de clientes): qué campos muestra, y ahora, que tras guardar su estado siempre refleje lo persistido en la base.

### Modified Capabilities

## Impact

- `app/Filament/Resources/Users/Pages/Auth/EditProfile.php`: nuevo hook `afterSave()` que re-hidrata el formulario desde el modelo guardado.
- `app/Filament/Client/Pages/Auth/ClientEditProfile.php`: mismo hook `afterSave()`.
- Sin cambios de rutas, dependencias ni configuración de paneles. El/Los test(s) existentes de perfil siguen pasando; se agregan pruebas del refresco post-guardado.
