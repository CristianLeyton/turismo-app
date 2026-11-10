# profile-pages Specification

## Purpose

Contrato de comportamiento del formulario de perfil en ambos paneles ("Perfil" del panel de administración y "Mi perfil" del panel de clientes): los datos que muestra y, en particular, que su estado siempre refleje lo persistido en la base de datos después de guardar.

## Requirements

### Requirement: El formulario refleja la base después de guardar

Al guardar cambios en el perfil, el formulario SHALL volver a hidratarse con los datos persistidos en la base de datos inmediatamente después del guardado exitoso, de modo que lo visible en pantalla coincida con la base sin necesidad de recargar la página ni volver a entrar a la página de perfil. Los campos de contraseña nuevos y su confirmación SHALL volver vacíos después del guardado.

#### Scenario: Guardado exitoso sincroniza la pantalla con la base

- **WHEN** un usuario guarda cambios en el perfil y el guardado no falla
- **THEN** los campos del formulario muestran exactamente los valores que quedaron persistidos en la base de datos para ese usuario/cliente

#### Scenario: Contraseñas se vacían tras guardar

- **WHEN** el guardado del perfil termina de forma exitosa con cambio de contraseña
- **THEN** los campos de nueva contraseña y confirmación quedan vacíos y lo persistido es el hash de la contraseña ingresada

#### Scenario: Guardado con error no toca el formulario

- **WHEN** el guardado del perfil falla por validación
- **THEN** el formulario conserva lo que el usuario escribió (sin re-hidratar desde la base) para poder corregir el error

### Requirement: Comportamiento compartido por ambos paneles

El refresco del formulario después de guardar SHALL aplicarse por igual al perfil del panel de administración y al perfil del panel de clientes, sin diferencias de comportamiento entre ambos.

#### Scenario: Perfil del panel de clientes se refresca igual

- **WHEN** un cliente guarda cambios en "Mi perfil" del panel de clientes
- **THEN** los campos nombre, apellido y teléfono muestran los valores persistidos, con las respuestas de mutación (trim de espacios, teléfono convertido a null si queda vacío) visibles en el formulario
