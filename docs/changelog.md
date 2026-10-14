# Registro de funcionalidades y cambios

> Documento de control de versiones funcional del panel de venta de pasajes (Expresso Sofia).
> Estilo changelog: un bloque por fecha, orden inverso al cronológico. Sin detalle técnico: cada funcionalidad se describe en una o dos líneas. Para detalles profundos ver `docs/roles-y-permisos.md`. Para llevar los cambios a producción ver `docs/deploy.md`.

## Panorama general del sistema

Panel de administración para venta de pasajes de colectivos, construido sobre Laravel + Filament + Livewire.

| Módulo | Qué hace |
| Venta de boletos | Wizard de venta (ida, ida y vuelta, diferido y sin fecha): colectivo, ruta, fecha/horario, asientos y pasajeros; genera PDFs de boletos. |
| Boletos | Listado y detalle de tickets, con reprogramación e historial de cambios. |
| Viajes | Viajes de los colectivos por fecha/horario y sus asientos. |
| Ventas | Ventas agrupadas por vendedor, con totales, pagos y exportación a Excel/PDF. |
| Clientes | Fichas de clientes, con posibilidad de banear. |
| Pagos | Registro de pagos por método, con resumen y filtros. |
| Flota | Colectivos, layouts de asientos (intercambiables entre líneas), rutas, horarios y ubicaciones. |
| Historial (auditoría) | Log de cambios de fecha/horario/asiento por boleto. |
| Usuarios / Roles y permisos | Gestión de cuentas, edición de perfil y contraseña, roles con matriz de permisos (Spatie Permission). |
| Configuración | Parámetros generales (ej. horas de venta fuera de término). |
| Cron | `app:cleanup-expired-reservations` limpia reservas vencidas (documentado en el README). |

---

## 2026-10-13 — Usuarios: rol obligatorio y filtros

- No se pueden crear ni editar usuarios sin rol: el campo Roles es obligatorio (si alguien llegara a quedar sin rol, el sistema le asigna Vendedor como red de seguridad).
- Historial de usuarios: nuevos filtros por rol y por usuarios eliminados (con opción de ver sólo los eliminados). Los filtros están arriba de la tabla y se aplican solos, sin botón "Aplicar".
- Historial general de boletos: se corrigió el orden por "Última actividad" (no funcionaba en algunos entornos) y la fecha de la columna (antes no se calculaba y siempre mostraba vacío).

## 2026-10-13 — Edición de boletos (corrección de errores de venta)

- Editar boleto (permiso `tickets.edit`, que ningún rol trae por defecto): corrige los datos del pasajero, el acompañante (menor/mascota) y los datos de la venta (precio y método de pago) sin tocar viaje, asiento, fecha ni horario (eso es Reprogramar). Cada campo cambiado queda en el historial del boleto (`ticket_edits`) con valor anterior, valor nuevo, motivo y usuario.
- DNI del pasajero: se quita la validación de "único por boleto" (un mismo pasajero puede comprar todos los boletos que quiera). En su lugar se valida que el cliente no esté baneado.
- Confirmación al guardar: el modal muestra el resumen de los cambios y, si está activado "Pedir contraseña al editar boletos" (Configuración), pide la contraseña dentro del propio modal. Si la edición no se guarda (contraseña, cliente baneado, datos inválidos o error), el modal se cierra y aparece una notificación con el motivo.
- Mensajes de validación propios en cada campo de la edición (obligatorio, formato, largo y rangos).
- El DNI se valida al completarlo, sin esperar a guardar: el aviso de cliente baneado aparece en el momento, tanto al editar un boleto como en el paso "Pasajeros" del wizard de venta.
- Detalle del boleto: la tarjeta "Detalles de venta" se rehizo para que sea más clara y consistente con el resto de la vista.
- Las ediciones de datos también aparecen en el **Historial general**, junto a las eliminaciones y reprogramaciones: nuevo estado "Editado", filtro propio y la acción más reciente del boleto (eliminado / reprogramado / editado).
- El historial de ediciones —tanto dentro del boleto como en el Historial general— se muestra con la misma lectura que el resto del sistema: una entrada por edición con autor, fecha, motivo y el detalle **Antes / Después** de cada campo (precio en formato de moneda y método de pago con su etiqueta).

## 2026-10-12 — Diseño del layout de buses

- Ajustes finales al editor visual del diseño interno del colectivo (CSS del tema y vista del designer).

## 2026-10-05 → 10-10 — Roles, permisos y cuentas

- Editar colectivo de forma fácil: nuevo *Bus Designer* visual para dibujar el layout interno (pisos, fila/asiento, sentado/no sentado) y guardarlo como plantilla reutilizable; incluye comando `app:clone-bus` (anticipado el 07-09, formalizado con plantillas), tests y servicio `BusLayoutService`.
- Gestión de cuentas: los usuarios vuelven a poder editar su perfil y su contraseña.
- Seguridad en roles: los administradores ya no pueden otorgar el rol de Super Administrador a otros usuarios.
- Nuevo sistema de roles y permisos (Spatie Laravel Permission): 3 roles del sistema (Super Administrador, Administrador, Vendedor) + roles custom con matriz de permisos por módulo/acción; flag `is_admin` sigue sincronizado para permitir rollback. Comando `permissions:sync`. Documentación completa en `docs/roles-y-permisos.md`.

## 2026-10-09 — Ventas fuera de término y ajustes de venta

- Venta fuera de término: quien tenga el permiso (`tickets.vender_pasado_limite`) puede vender pasajes de viajes ya salidos dentro de un plazo configurable (Configuración, default 5 hs desde la salida en la parada de subida). Bloquea el wizard paso a paso y server-side.
- Nuevas validaciones y mejoras en la gestión de boletos (viajes de vuelta con otro colectivo, reparación de vínculos ida↔vuelta con `tickets:repair-round-trip-links`).

## 2026-09-25 / 10-05 — Ventas, pagos y clientes (finanzas)

- Recurso Ventas: ventas por vendedor con agrupado de boletos, badges de cantidad/totales, validaciones y autorización propia (`sales.*`).
- Recurso Pagos: resumen de pagos, filtros, manejo de métodos de pago y refinamiento de la policy (update / force delete).
- Recurso Clientes: listado y formulario propios, autorización con `clients.*`, acciones de borrado visible por rol y exportaciones Excel/PDF con detalle.

## 2026-09-26 — Reprogramación de boletos

- Reprogramar pasaje (permiso `tickets.reschedule`): cambiar fecha, horario, colectivo y asiento de un boleto ya emitido, incluyendo ida y vuelta en un paso; valida asiento ocupado y horarios de las rutas del colectivo.
- Nuevo módulo Historial (auditoría): cada cambio de fecha/asiento queda registrado en `ticket_date_changes` con vista propia.

## 2026-09-07 → 09-10 — Clientes y venta

- Banear clientes (permiso `clients.ban`): un cliente baneado no puede comprar; validación también en el wizard de venta. Solo un admin puede banear.
- Lógica de precios unificada en `display_price` (boletos y PDFs); validación de email y teléfono obligatorios en el perfil.
- Deshabilita domingos en el selector de fechas de venta (excepción 13/09/2026).

## 2026-08-05 → 08-18 — Primera versión de Ventas y Clientes

- Nuevo recurso de ventas (primera parte), listado de clientes y formulario acomodado.
- Legibilidad de PDFs de boletos: políticas de equipaje/devoluciones y texto de cambios de fecha/horario.

## 2026-06 → 07 — Flota y rutas activas

- Rutas con `is_active`: se filtran rutas/horarios inactivos en la venta; filtros que excluyen rutas y destinos borrados.
- Marca unificada "Expresso Sofia"; layouts intercambiables entre Línea 1 y Línea 2 (comandos `app:swap-layouts-linea1-linea2` / `app:revert-layouts-linea1-linea2`).

## 2026-02 → 03 — Ajustes iniciales del panel

- Línea 1 con 55 asientos y Línea 2 con 60; setups de layout por línea (`app:setup-layout-*`).
- Tabla de boletos: filtros por pasajero/nombre/DNI, columnas de info de ida y vuelta, botón de vista.
- Venta: horarios con offsets por parada, advertencias a admins al vender de salidas pasadas, domingos deshabilitados, nombres con apóstrofes/guiones.

---

## Comandos artisan del sistema

| Comando | Para qué |
| `app:cleanup-expired-reservations` | Cron: libera reservas vencidas. |
| `app:clone-bus` | Clona un colectivo con su layout. |
| `permissions:sync [--reconcile-users]` | Aplica novedades del catálogo de permisos. |
| `tickets:repair-round-trip-links` | Repara vínculos ida↔vuelta huérfanos. |
| `app:swap-layouts-linea1-linea2` / `app:revert-...` | Intercambia / revierte layouts entre líneas. |
| `app:setup-layout-*`, `app:setup-embarcacion` | Carga inicial de layouts por línea. |

## Tests relevantes

- `SellWithoutDateTest`, `TicketSaleCutoffTest`, `TicketSaleCutoffWizardTest` — venta sin fecha / fuera de término.
- `RescheduleDoesNotAffectOtherSeatsTest` — reprogramación.
- `RolesAndPermissionsTest` — roles y permisos.
- `BusLayoutTemplatesTest`, `ClientsTableTest` — designer de buses y clientes.
- `ReturnTripDifferentBusTest` — vuelta con otro colectivo.
- `TicketEditTest` — edición de boletos: permiso, DNI (sin unique, cliente baneado), auditoría y contraseña en el modal.
- `TicketSaleDniValidationTest` — aviso de cliente baneado en tiempo real en el paso "Pasajeros" del wizard.
- `TicketEditHistoryTest` — agrupado por edición y valores legibles del historial de ediciones.
- `TicketAuditResourceTest` — Historial general: eliminados, reprogramados y editados.
