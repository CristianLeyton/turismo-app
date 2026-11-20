# Tasks

## 1. Validación del DNI en el login de clientes

- [x] 1.1 Agregar a `tests/Feature/ClientPanelTicketsTest.php` los tests de los cuatro escenarios de "Formato del DNI en el ingreso" (usuario del admin, sin dígitos, separadores aceptados, DNI bien escrito fuera del padrón) y verificar que los dos de formato fallan antes del cambio
- [x] 1.2 Agregar la regla `regex` `/^(?=.*\d)[\d.\s-]+$/` con el mensaje "Ingresá solo los números de tu DNI." al campo `dni` de `ClientLogin::getEmailFormComponent()` y verificar que pasan los tests nuevos y los de ingreso existentes (`php artisan test --compact tests/Feature/ClientPanelTicketsTest.php`)
- [x] 1.3 Correr `vendor/bin/pint --dirty --format agent` y los tests de `ClientLoginRedirectTest` y `LoginBackNavigationSyncTest` para verificar que el resto del ingreso no cambió

## Workflow follow-up

- Probar en Chrome: con la credencial del admin guardada, autocompletar en `/clientes/login` y ver el mensaje de formato.
- Archivar el cambio y sincronizar `client-access`.
