# Proposal

## Why

La página Configuración > Parámetros creció sección por sección y hoy tiene demasiado texto explicativo, todo con el mismo peso: lo que evita errores queda mezclado con ejemplos y aclaraciones de permisos. Las validaciones son desparejas: algunos campos no validan nada (el mensaje de WhatsApp puede quedar sin `{link}`), otros redondean sin avisar y, como no hay traducciones de validación al español, varios errores aparecen en inglés. La página se usa también desde el celular, donde el exceso de texto y las secciones sin agrupar obligan a desplazarse mucho.

## What Changes

- La página se organiza en cuatro pestañas según a qué afecta cada parámetro: **Venta** (acompañantes, venta fuera de término), **Boleto** (descarga automática del PDF, mensaje de WhatsApp, líneas de contacto), **Calendario** (días de servicio y excepciones) y **Seguridad** (confirmación de contraseña).
- Los textos se reducen a tres niveles: visible (una oración, sólo lo que evita errores), hint con ícono de información (datos extra que no son necesarios para operar bien) o se eliminan. Los tres toggles de contraseña comparten una sola explicación en la sección.
- Errores de validación claros con pestañas: cada pestaña con errores muestra un badge rojo con la cantidad, y al fallar el guardado aparece una notificación que dice que no se guardó nada y nombra las pestañas a revisar. Filament ya abre la pestaña del primer error y desplaza hasta el campo.
- El guardado sigue siendo todo o nada.
- Validaciones nuevas o corregidas:
  - Horas de venta: número entero entre 0 y 720, con mensajes propios.
  - Mensaje de WhatsApp: si se carga, debe incluir `{link}`; máximo de caracteres.
  - Días de servicio: al menos un día de la semana.
  - Excepciones: no se pueden cargar fechas anteriores a hoy; las excepciones vencidas ya guardadas se eliminan al guardar y la notificación lo informa.
  - Todas las reglas con mensaje propio en español con voseo ("Ingresá…"), incluido el del teléfono de contacto.
- "Parámetros" pasa a escribirse con tilde en el menú y el título.
- Se agrega al skill `filament-ui` una sección "Parámetros" con las reglas para sumar parámetros nuevos: en qué pestaña va, qué nivel de texto lleva y cómo se valida.

## Capabilities

### New Capabilities
- `settings-page`: estructura y comportamiento de la página Configuración > Parámetros: agrupación en pestañas, niveles de texto, presentación de errores, guardado todo o nada y validación de los parámetros que no pertenecen a otra capacidad (horas de venta fuera de término y mensaje de WhatsApp).

### Modified Capabilities
- `service-days`: la configuración exige al menos un día de la semana con servicio, rechaza excepciones con fecha pasada y elimina al guardar las excepciones vencidas.
- `ticket-contact-lines`: el mensaje de error del teléfono pasa a voseo ("Ingresá un teléfono válido…") y la sección vive en la pestaña Boleto.

## Impact

- Código: `app/Filament/Pages/TicketSettings.php` (formulario, guardado y `onValidationError`). `app/Models/Setting.php` sólo si hace falta una constante para el largo máximo del mensaje.
- Tests: `tests/Feature/TicketSettingsTest.php` (labels cambiados, fechas fijas que hoy ya son pasadas y casos nuevos de validación).
- Dependencias: `laravel-lang/common` en `require-dev`; archivos nuevos en `lang/es/` y `lang/es.json`.
- Documentación del proyecto: `.claude/skills/filament-ui/SKILL.md`.
- Sin cambios de base de datos ni de comportamiento en la venta, el boleto o el link compartido.
