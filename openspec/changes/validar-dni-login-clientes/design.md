# Design

## Context

`ClientLogin::getEmailFormComponent()` define el campo `dni` como obligatorio, sin otras reglas. El `authenticate()` de Filament limita los intentos (5) primero, después valida el formulario (`$this->form->getState()`) y por último busca al cliente con el DNI que normaliza `ClientSession::normalizeDni()`, que deja solo los dígitos.

## Goals / Non-Goals

**Goals:**
- Que un valor que no tiene forma de DNI se rechace en la validación del formulario, con su propio mensaje.

**Non-Goals:**
- Cambiar lo que sugiere o completa Chrome.
- Validar el largo del DNI. Hoy el padrón no lo restringe, y validar el largo podría dejar afuera DNI viejos o cargados con otro formato.

## Decisions

- **Regla de formulario en el campo y no en `getCredentialsFromFormData`.** Una regla `regex` con `validationMessages` deja el error debajo del campo `dni`, igual que el "obligatorio" que ya existe, y corta antes de consultar el padrón. Expresión: `/^(?=.*\d)[\d.\s-]+$/`. La alternativa de validar en `getCredentialsFromFormData` y lanzar una `ValidationException` también funciona, pero agrega un camino más que usar el mecanismo estándar de Filament.
- **Solo del lado del servidor.** No se agrega el atributo `pattern` de HTML: Chrome igual completaría el campo, y el mensaje nativo del navegador no respeta el estilo de los errores del panel.

## Risks / Trade-offs

- [Un intento rechazado por formato igual cuenta para el límite de 5, porque Filament limita antes de validar] → Se acepta: es el comportamiento de Filament para cualquier error del formulario, como el DNI vacío.
- [Un cliente que escriba su DNI con otro separador, por ejemplo "/", ve el error de formato] → El mensaje le indica que use solo números, así que puede corregirlo enseguida.
