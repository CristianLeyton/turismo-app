## 2026-10-22 — Mi perfil del panel de clientes: formulario Filament nativo

- La página **Mi perfil** del panel de clientes se reescribe con el mismo mecanismo del perfil del panel administrativo: un **form Filament real** (Secciones «Mis datos» y «Cambiar contraseña», un solo botón **Guardar cambios**, Ctrl+S) con **validaciones nativas resaltadas debajo de cada campo**.
- **Nombre y Apellido son obligatorios** (máxima 255 caracteres, los mismos textos de validación «El nombre es obligatorio.» / «El apellido es obligatorio.» etc.). Si algo falla, **nada se guarda y los campos mantienen lo escrito**.
- El **Teléfono** valida que (si viene) sea de **7 a 12 números** (se limpian espacios de más, sin invalidar). El **DNI** queda read-only con su explicación.
- Contraseña: mínimo 6 caracteres y confirmación igual; si se escribe una nueva se **exige la actual** (o el DNI si todavía no eligió una propia). Después de guardar, campos de contraseña se vacían.

## 2026-10-22 — Tablas administrativas: acciones con tres puntitos en el celular

- Todas las tablas del panel administrativo que faltaban pasan a usar la convención de Boletos (TicketsTable): en el celular las acciones van dentro del **menú de tres puntitos** de cada fila, y en pantallas medianas o mayores quedan los botones directos. Aplicado a Usuarios, Clientes, Pagos, Métodos de pago, Roles y permisos, Asientos, Colectivos, Layouts de colectivo, y el clúster de Destinos (Ubicaciones, Rutas, Paradas y Horarios). Los actions con closures largas (Roles, Usuarios y Clientes) se extrajeron a builders privados para no duplicar lógica.
- Métodos de pago también migra de `->actions(` (API vieja de Filament) a `->recordActions(`.
- Auditoría de boletos queda por fuera del lote: su botón «Ver» sigue directo en la fila (avisar si también va dentro del menú).

## 2026-10-22 — Panel de clientes: mis boletos a la medida del celular

- La tabla «Mis boletos» se alza **a la medida del celular**: en pantallas chicas quedan las columnas **N°, Tramo y Fecha/Horario**, y el resto (Pasajero, Tipo, Estado, Asiento, Precio) reaparece en pantallas medianas o mayores.
- Las acciones (Elegir fecha, Ver boleto) ya no quedan colgando de la fila: se agrupan adentro del **menú de tres puntitos**, como en el resto del panel administrativo.

## 2026-10-22 — Clientes con perfil y contraseña en el panel público

- El panel de clientes ahora identifica con **DNI + contraseña siempre obligatoria** (nadie entra con el DNI solo). La contraseña inicial de cada cliente es **su propio DNI** (viaja hasheada en la base desde una migración de backfill) y al primer ingreso (bandera de primer login) el panel muestra la pantalla **«¡Bienvenido a Expresso Sofia, Nombre!»** que lo obliga a **elegir una contraseña propia** y no muestra nada hasta que la define; desde entonces entra con DNI + la elegida.
- Validaciones simples de contraseña (mínimo 6 caracteres y que las dos coincidan); **el error se resalta debajo de cada campo, estilo Filament**, y las notificaciones se reservan para éxitos, límite de intentos y problemas inesperados. Los campos de contraseña tienen **botón de ojito** para mostrar/ocultar lo tipeado.
- El primer ingreso **recibe al cliente por su nombre**: la pantalla de clave dice «¡Bienvenido a Expresso Sofia, Nombre!» y explica el porqué del cambio de contraseña.
- **Mi perfil** (menú del panel público): el cliente actualiza Nombre, Apellido y Teléfono y cambia su contraseña (exigiendo la actual). El DNI queda read-only: es la clave que une clientes, pasajeros y boletos.
- **Resetear contraseña** en el listado de Clientes (permiso `clients.reset_password`, lo trae Administrador): si el cliente la olvidó, se resetea y vuelve a entrar con su DNI, eligiendo una nueva al primer ingreso.

## 2026-10-22 — Panel de clientes: ida y vuelta bien identificados

- En el listado «Mis boletos» del panel de clientes, la columna **Tipo** marca el tramo: **«Ida»** (fucsia) para la ida de un pasaje de ida y vuelta, **«Vuelta de N°X»** (fucsia) para la vuelta con el N° del boleto de ida que lleva el precio, y **«Solo ida»** (neutro) para el boleto que no pertenece a un diferido.

## 2026-10-22 — Atajo al panel de clientes en el login de administración

- La página de login del panel de administración muestra, debajo del formulario, un apartado con el enlace **Ingresar a «Mis boletos»** que lleva al panel de clientes (ingreso por DNI), para el pasajero que llegó por error a ese login.

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

## 2026-10-22 — Autocompletado del pasajero desde el padrón de clientes

- Al escribir el DNI de un pasajero en la venta, si ese DNI existe en el padrón de clientes se **autocompletan apellido, nombre y teléfono** (los datos de la ficha reemplazan a los tipeados a mano). Solo se activa desde los 7 dígitos.
- El paso Pasajeros reordenado: **DNI y Teléfono van primero** y Apellido/Nombre después — así lo primero que carga el vendedor es el DNI.
- La validación del DNI (cliente baneado, etc.) corre también en el momento a partir de los 7 dígitos, sin salir del campo ni avanzar de paso.

## 2026-10-22 — Inputs numéricos sin flechas ni cambio por ruedita

- Los campos numéricos dejan de mostrar las flechas de incrementar/decrementar del navegador y la **ruedita del mouse ya no cambia su valor** (el valor se tipea; el scroll de la página funciona con normalidad).

## 2026-10-21 — Venta sin fecha: sólo pasajeros del padrón de clientes

- Los boletos sin fecha (ida, vuelta o mixta) sólo se venden a pasajeros **registrados en el padrón de clientes**: si el DNI no está registrado, el wizard avisa al completar el campo (en el momento, como el aviso de cliente baneado) y la venta se bloquea también server-side.
- Motivo: el panel de clientes muestra los boletos por el DNI del padrón; un boleto sin fecha a un DNI no registrado le quedaría invisible al pasajero para verlo o reprogramarlo.
- El alta del pasajero se puede hacer en el mismo wizard con las acciones **Crear cliente** o **Cargar cliente** del paso Pasajeros.

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
