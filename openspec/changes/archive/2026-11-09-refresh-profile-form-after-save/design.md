# Design

## Context

Ambas páginas de perfil heredan de `Filament\Auth\Pages\EditProfile` (Filament v4). Esa clase base hace `fillForm()` solo en `mount()`: `$form->fill()` se llena con `$this->getUser()->attributesToArray()` y nunca se vuelve a ejecutar después de `save()`. Los paneles funcionan con `->spa(hasPrefetching: true)`, por lo que el HTML/cache de navegación de Livewire puede servir la página con estado horneado antes del guardado (ver proposal.md — Why). La solución elegida en este cambio es mínima y de superficie local: re-hidratar el formulario al terminar `save()`, para que el componente quede siempre en sincronía con la base.

Archivo real del panel admin: `app/Filament/Resources/Users/Pages/Auth/EditProfile.php` (`app/Filament/Pages/Auth/EditProfile.php` es solo un proxy para la resolución de ruta por defecto de Filament — no se modifica). Panel clientes: `app/Filament/Client/Pages/Auth/ClientEditProfile.php`.

## Goals / Non-Goals

- Goal: tras un guardado exitoso, `$this->data` y la vista reflejan exactamente lo persistido (incluidas las respuestas de `mutateFormDataBeforeSave`: trim, teléfono vacío → null, `must_change_password` → false).
- Goal: cubrir ambas páginas con el mismo patrón y pruebas.
- Non-Goal: tocar configuring SPA/prefetching de los paneles, el `backAction()`/botón "Regresar", ni las caches internas de Livewire.
- Non-Goal: cambiar campos, reglas de validación o el flujo de cambio de contraseña/email.

## Decisions

- **Hook `afterSave()` en las páginas hijas, re-llamando a `fillForm()`** en vez de sobrescribir `save()` en la base o en las hijas.
  - Por qué: `save()` en la base (vendor) ya ejecuta `afterSave` entre transacción y notificación; el hook respeta el orden del vendor (transacción confirmada, sesión de password ya actualizada) sin duplicar su manejo de errores. Sobrescribir `save()` completo duplicaría lógica del vendor y acoplamiento.
  - Alternativa descartada: sobrescribir `save()` en las páginas hijas — duplica el try/catch y el manejo de `Halt` del vendor.
  - Detalle importante: la clase base llama a `fillForm()` (protegido) desde `mount()`; el hook puede reutilizarlo tal cual, Epic: mismo método en ambas clases. `fillForm()` respeta `mutateFormDataBeforeFill`, así que el estado queda canónico igual que en el `mount()` inicial.
- **No tocar propiedades de contraseña:** la base ya hace `$this->data['password'] = null; $this->data['passwordConfirmation'] = null;` antes del hook; `fillForm()` completa `attributesToArray()`, que no incluye el hash plano, así que los campos de contraseña quedan vacíos conforme al spec.
- **Ambas páginas por igual:** el mismo hook en `EditProfile` (Users) y `ClientEditProfile`; sin abstraer a un trait para dos sitios — dos hooks, uno por clase, más legible que una abstracción para el mismo patrón de 3 líneas.

## Risks / Trade-offs

- [Re-hidratación limpia lo que el usuario estaba escribiendo → Mitigation: el hook corre solo tras guardado exitoso; en la validación fallida `save()` retorna antes (vía `Halt`) sin pasar por `afterSave`, y el spec captura ese comportamiento como escenario explícito]
- [Campos `disabled`/`dehydrated(false)` (name, surname, dni, roles) vuelven a llenarse desde la base → Mitigation: es el mismo estado del `mount()` inicial; sin delta visible para el usuario]
- [Si un día Filament cambia el nombre de `fillForm()`, el hook rompería → Mitigation: son dos usos escopetados a la clase base, cubiertos por los tests del cambio]

## Migration Plan

Sin migraciones ni despliegue especial. Rollback: revertir los dos hooks.

## Open Questions

Ninguna.
