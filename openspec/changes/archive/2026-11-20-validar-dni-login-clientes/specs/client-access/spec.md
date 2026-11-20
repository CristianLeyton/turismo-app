# Spec Delta

## ADDED Requirements

### Requirement: Formato del DNI en el ingreso
El campo DNI del ingreso al panel de clientes SHALL aceptar solo dígitos, puntos, espacios y guiones, con al menos un dígito. Si trae otro carácter o ningún dígito, el ingreso MUST rechazarse sin buscar al cliente, con el mensaje "Ingresá solo los números de tu DNI." en el campo DNI. El mensaje depende solo del formato y MUST NOT revelar si el DNI está en el padrón.

#### Scenario: Usuario del admin en el campo DNI
- **WHEN** en el campo DNI queda un nombre de usuario como "cristian", por ejemplo autocompletado por el navegador, y se intenta ingresar
- **THEN** no inicia sesión y ve "Ingresá solo los números de tu DNI." en el campo DNI

#### Scenario: DNI sin ningún dígito
- **WHEN** alguien ingresa "..." como DNI
- **THEN** no inicia sesión y ve "Ingresá solo los números de tu DNI."

#### Scenario: DNI con puntos, espacios o guiones
- **WHEN** un cliente del padrón ingresa "11.223.344", "11 223 344" o "11-223-344" con su contraseña
- **THEN** queda con sesión iniciada

#### Scenario: DNI bien escrito que no está en el padrón
- **WHEN** alguien ingresa un DNI de solo dígitos que no está en el padrón
- **THEN** ve el mensaje genérico de credenciales incorrectas, no el de formato
