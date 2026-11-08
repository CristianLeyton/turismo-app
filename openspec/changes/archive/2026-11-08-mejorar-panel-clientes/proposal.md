# Proposal

## Why

"Mis boletos" es hoy la única pantalla útil del panel de clientes y es solo una tabla con un switch armado a mano. Un cliente nuevo ve una tabla vacía sin explicación, no se entera de que tiene boletos sin fecha, no ve cuál es su próximo viaje y, si necesita ayuda, solo encuentra las líneas de contacto en el ingreso. Además, la pantalla "Elegir contraseña" es HTML y SVG a mano que se aparta de las convenciones de Filament, y varias pantallas muestran al cliente el mensaje crudo de una excepción.

## What Changes

- "Mis boletos" muestra arriba un **banner de boletos sin fecha** (solo si hay) con un botón que abre "Elegir fecha" del más antiguo, y una tarjeta **"Próximo viaje"** con recorrido, fecha, horario y asiento.
- El switch "Mostrar boletos ya con fecha" se reemplaza por tres **pestañas con conteo**: Pendientes, Próximos e Historial. "Historial" son solo viajes ya realizados con boleto vigente; no incluye boletos eliminados.
- La tabla gana un **estado vacío propio por pestaña**, las acciones de fila en las dos variantes (menú en celular y botones en escritorio, con "Elegir fecha" siempre visible), badges con `TextColumn::badge()` y mensajes de error genéricos que no exponen la excepción (el error se sigue reportando con `report()`).
- Un bloque **"¿Necesitás ayuda?"** en "Mis boletos" con las líneas de contacto de Parámetros, cada una con botón para llamar (`tel:`) y para escribir por WhatsApp (`wa.me`). Sin líneas cargadas no se muestra.
- La pantalla **"Elegir contraseña"** se rehace con componentes de Filament (campos de contraseña con ojito nativo), con un texto más cálido que ya no dice que la clave inicial "la conocemos todos en boletería", y sin mostrar excepciones.
- **Elegir fecha** pasa a ser un asistente de dos pasos: en el primero se elige fecha, horario y asiento, y en el segundo se **reconfirma** con un resumen y el aviso de que la fecha no se podrá cambiar de nuevo. Recién al confirmar se asigna.
- No cambia la regla de que el cliente solo puede asignar fecha a boletos que todavía no la tienen.

## Capabilities

### New Capabilities
- `client-tickets-panel`: la pantalla "Mis boletos" del panel de clientes: banner de pendientes, próximo viaje, pestañas con conteo, estado vacío, acciones de fila, bloque de contacto y manejo de errores al elegir fecha.

### Modified Capabilities
- `client-access`: el requisito "Contraseña inicial y cambio obligatorio" suma cómo se presenta la pantalla de elegir contraseña (componentes de Filament, texto, errores sin excepción).
- `ticket-contact-lines`: las líneas de contacto también se ofrecen en el panel de clientes con enlaces para llamar y para WhatsApp.

## Impact

- Código: `app/Filament/Client/Pages/MyTickets.php`, `app/Filament/Client/Pages/SetPassword.php`, vistas en `resources/views/filament/client/` (`set-password`, y las nuevas del aviso de pendientes, la tarjeta de próximo viaje, el contacto y `assign-confirmation` para la reconfirmación; se elimina `my-tickets`), `app/Services/ClientTicketAssignmentService.php` (consultas por pestaña) y `app/Models/Setting.php` (enlace de WhatsApp a partir de la línea).
- Tests: `tests/Feature/ClientPanelTicketsTest.php` (el test del switch pasa a pestañas) y tests nuevos para las pestañas, el banner, el próximo viaje y el contacto.
- Sin migraciones, sin dependencias nuevas, sin cambios en permisos ni en el panel de administración. Tras tocar clases Tailwind hay que correr `npm run build`.
