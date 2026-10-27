# Manual funcional del sistema — Expresso Sofia

> Inventario completo de funciones y opciones del panel de venta de pasajes.
> Estado: **2026-10-16** (incluye el trabajo en curso del cluster Destinos, que
> todavía no está en producción — ver `docs/auditoria-deploy-destinos.md`).
>
> Relación con los otros documentos: `docs/changelog.md` cuenta **qué cambió y
> cuándo**; `docs/plan-destinos.md` y `docs/auditoria-deploy-destinos.md`
> cubren el cluster en curso; `docs/deploy.md` explica cómo llevar cambios a
> producción; `docs/roles-y-permisos.md` profundiza en autorización. Este
> manual describe **cómo está el sistema hoy y qué se puede hacer en cada
> pantalla**, pensado para quien arranca a usarlo.

## 1. Cómo usar este manual

Cada sección describe una pantalla del panel: para qué sirve, qué campos y
columnas tiene, qué botones hay y qué permisos hacen falta. Los nombres de
menús, botones y campos están escritos **tal como aparecen en el sistema**
(entre comillas o en negrita), para que se puedan buscar sin traducción.

**Primeros pasos**: el sistema es un panel web. Se ingresa con **Nombre de
usuario** y **contraseña** (no con correo). Lo primero que se ve es **Inicio**,
con tarjetas de acceso rápido. Desde el menú lateral se llega a cada módulo;
las pantallas que tu rol no tiene habilitadas simplemente no aparecen.

Flujo de trabajo de un día típico:

1. **Inicio → tarjeta "Vender"** → wizard de venta (sección 3) → se descarga el PDF.
2. **Boletos** para buscar, descargar o corregir un boleto (sección 4).
3. **Ventas / Pagos** para el cierre y los cobros (secciones 6 y 7).
4. **Destinos** para mantener rutas, horarios y destinos al día (sección 10).


## 2. Panorama: el menú del panel

**Inicio** muestra tarjetas de acceso rápido: **Venta de boletos / Vender**
(el wizard — es el único acceso a la venta, no está en el menú lateral),
**Boletos vendidos**, **Detalle de viajes**, y (**sólo si tu rol lo permite**)
**Administrar usuarios**, **Ver ventas** y **Ver clientes**.

El menú lateral, en orden:

| Menú | Pantallas | Qué contiene |
| --- | --- | --- |
| **Boletos** (grupo) | Boletos · Historial | Listado/gestión de boletos y auditoría de movimientos |
| **Viajes** | Viajes | Supervisión de viajes generados y exportación de listas |
| **Clientes** | Clientes | Padrón y baneos |
| **Ventas** (grupo) | Ventas · Pagos | Resultado por vendedor y registro de cobros |
| **Usuarios** (grupo) | Usuarios · Roles y permisos | Cuentas y matriz de permisos |
| **Configuración** (grupo) | Colectivos (Colectivos · Distribuciones de asientos · Asientos) · Destinos (Destinos · Rutas · Paradas · Horarios) · Métodos de pago · Configuración | Toda la operación de flota y red |

Lo que se ve en cada menú **depende del rol** (sección 12). El grupo
**Configuración** es administrativo: un vendedor normal no lo ve.


## 3. Venta de boletos (el wizard)

La venta se hace desde el menú **Venta de boletos**: un asistente de cinco
pasos. Se puede volver y corregir hasta confirmar; nada se guarda hasta el
último paso.

### 3.1 Paso 1 — Buscar viaje

| Campo | Qué hace |
| --- | --- |
| Colectivo | Elige el colectivo (sólo los activos) |
| Cantidad de pasajeros | Define cuántos asientos/pasajeros se van a cargar |
| Origen / Destino | Paradas de la ruta; el destino se acota según el origen |
| Fecha de ida / Horario de ida | Fecha y salida elegida (se generan los viajes disponibles por ruta/horario) |
| Viaje de ida sin fecha | Vende el boleto **sin fecha ni horario**; se asignan después con "Reprogramar boleto" (permiso `tickets.vender_sin_fecha`) |
| Diferido | Marca la venta como **ida y vuelta**: aparecen los campos de vuelta |
| Viaje de vuelta sin fecha / Fecha de vuelta / Horario de vuelta | Ídem para el tramo de vuelta |

Al apretar el botón de búsqueda el sistema verifica el viaje y avisa:
"Viaje disponible — Asientos disponibles: N", "Asientos insuficientes" o
"Venta fuera de término" (si la salida ya pasó fuera del plazo de
Configuración; sólo quien tiene el permiso puede continuar).

**Boletos sin fecha y padrón:** los tramos sin fecha sólo se venden a
pasajeros **registrados en el padrón de clientes**. Si el DNI no está
registrado, el wizard avisa al completar el campo (en el momento, como el
aviso de cliente baneado) y la venta no se completa server-side. El alta se
puede hacer en el mismo paso con **Crear cliente** o **Cargar cliente**. El
motivo: el panel de clientes ubica los boletos por el DNI del padrón — un
boleto sin fecha dado a un DNI no registrado le quedaría invisible al
pasajero para verlo o reprogramarlo.

### 3.2 Paso 2 — Asientos (Ida)

Aparece el **mapa de asientos del colectivo** (el diseño real del colectivo,
con pisos y plazas). Al marcar asientos se genera una **reserva temporal de 5
minutos** para tu sesión: si otro vendedor los toca en ese lapso aparece
"Conflicto de reservación" y el mapa se refresca. Si la reserva está por vencer
se extiende sola ("Reservas extendidas") y, si se vence, avisa "Asientos
expirados". El mapa también muestra los asientos ya vendidos y los reservados
por otros.

Si la venta es **diferido (ida y vuelta)**, sigue un paso análogo
**"Asientos (Vuelta)"** para el tramo de regreso.

### 3.3 Paso 3 — Pasajeros

Se carga un bloque por pasajero (la cantidad salió del paso 1):

- **DNI y Teléfono primero, y después Apellido y Nombre**. El DNI va primero
  a propósito: se carga el DNI y, si el pasajero existe en el padrón,
  apellido, nombre y teléfono se autocompletan solos. El DNI se valida en el
  momento (con los 7 dígitos mínimo, sin salir del campo ni avanzar de paso):
  si el cliente está en el padrón con prohibición de compra, avisa y no deja
  continuar. Con menos de 7 dígitos no valida ni autocompleta (todavía se
  está escribiendo).
- **¿Viaja con un menor?** → apellido, nombre y DNI del menor.
- **¿Viaja con mascotas?** → nombres y cantidad.
- **Precio** (se sugiere según la ruta; se puede ajustar) y **Método de pago**
  (los métodos activos; desde ahí mismo se puede dar de alta un método nuevo
  con **Crear método de pago**).

Además, por pasajero se puede **Cargar cliente** (buscar por DNI en el padrón
y completar los datos del pasajero; si está baneado lo avisa) o **Crear
cliente** (alta rápida con apellido, nombre, DNI y teléfono).

En las ventas **ida y vuelta**, el precio se carga una sola vez (en la ida) y
el tramo de vuelta queda con precio $0 compartido.

### 3.4 Paso 4 — Resumen y confirmación

Muestra el resumen completo (viaje, asientos, pasajeros, importe total). Al
finalizar, el sistema vuelve a verificar todo server-side y:

- Si todo está bien: crea pasajeros/clientes, la venta, los boletos y sus
  pagos, y **descarga automáticamente el PDF** de cada boleto (o uno conjunto
  por pasajero cuando es ida y vuelta). Aviso: "Boleto vendido correctamente".
- Si algún asiento ya fue vendido: "Asientos de ida/vuelta no disponibles",
  te actualiza la selección y te deja reintentar.
- Si el cliente quedó baneado, falta método de pago o el viaje ya no existe:
  bloquea con el motivo ("No se puede completar la venta").

### 3.5 Los cuatro tipos de venta

| Tipo | Cómo se hace |
| --- | --- |
| Ida simple | Paso 1 con fecha y horario |
| Ida y vuelta ("Diferido") | Se marca Diferido: dos tramos con sus asientos; el precio se pone en la ida |
| Sin fecha | Toggle "Viaje de ida/vuelta sin fecha": se vende hoy y la fecha se asigna después con Reprogramar. Exige pasajeros del padrón de clientes (ver paso 2) |
| Fuera de término | Viajes ya salidos dentro del plazo de Configuración (ver 13); pasado el plazo hace falta el permiso `tickets.vender_pasado_limite` |

### 3.6 PDF del boleto

Nota de dimensionado: la plantilla (`resources/views/tickets/pdf/passenger-tickets.blade.php`)
usa una altura fija de **124 mm** por boleto (media hoja A4). Si se cambia la
altura, revisar que el cartel "FECHA Y HORARIO A CONFIRMAR" no se corte.

Cada boleto se imprime en A4 con: vendedor y datos de la venta, colectivo, ruta
(origen → destino), fecha y hora de salida, asiento, pasajero y acompañantes,
políticas de equipaje/devoluciones y las condiciones de cambio de fecha u
horario. Los boletos de ida y vuelta salen en un solo PDF con los dos tramos.
Nombre del archivo: `Boleto_N°{número}_{colectivo}.pdf`. Se puede volver a
descargar desde el listado o el detalle (ver 4.1 y 4.2).

### 3.7 Reservas temporales de asientos

Mientras un vendedor arma la venta, los asientos marcados quedan reservados 5
minutos para su sesión (nadie más puede tomarlos). Al confirmar la venta la
reserva se convierte en boleto; si no se confirma, se libera sola. El comando
`app:cleanup-expired-reservations` (cron) limpia las reservas vencidas.


## 4. Boletos

Desde el menú **Boletos**. Es el listado de todos los boletos emitidos, de
cualquier fecha y estado.

### 4.1 Listado

Ordenado por número de boleto (más reciente arriba). Paginación de 5, 10, 25,
50 o 100 (50 por defecto).

| Columna | Qué muestra |
| --- | --- |
| N° | Número del boleto (gris) |
| Nombre | Apellido y nombre del pasajero (el mouse encima muestra el completo) |
| DNI | DNI del pasajero |
| Emisión | Fecha de emisión (verde); al hacer clic abre el detalle del boleto |
| Vendedor | Quien lo vendió (amarillo) |
| Salida | Fecha y hora de salida; en amarillo "Sin fecha" para los boletos diferidos sin fecha asignada (clic abre el detalle) |
| Ruta | "Origen → Destino" (amarillo) |
| Asiento | Número de asiento; "X" si es un boleto sin fecha |
| Diferido | Palomita si el boleto es de ida y vuelta |

**Filtros** (arriba de la tabla, se aplican solos y quedan guardados en la
sesión): Número de boleto, Pasajero (nombre o DNI), Fecha de venta, Vendedor,
Fecha de salida, Ruta (origen o destino, búsqueda parcial) y el interruptor
**"Ver únicamente boletos sin fecha"**.

**Acciones por fila**:

- **Ver boleto**: abre el detalle.
- **Descargar**: baja el PDF del boleto. Si es de ida y vuelta, el PDF incluye
  los dos tramos del pasajero. El archivo se llama `Boleto_N°{número}_{colectivo}.pdf`.

### 4.2 Detalle del boleto

Muestra el resumen de la venta (pasajero, viaje, asiento, importe, método de
pago) en tarjetas. Arriba se encuentran las acciones:

- **Editar boleto** — sólo si el usuario tiene el permiso `tickets.edit` (ver 4.3).
- **Reprogramar boleto** — sólo con permiso `tickets.reschedule` (ver 4.4).
- **Eliminar** — pide un **motivo obligatorio** (máx. 250 caracteres) que queda
  guardado y visible en el Historial; si Configuración lo exige, además pide la
  contraseña del admin. El boleto se marca como eliminado (se puede restaurar).
- **Eliminar definitivamente** — borra el registro para siempre (si Configuración
  lo exige, pide contraseña).
- **Restaurar** — devuelve a la vida un boleto eliminado.
- **Descargar** — PDF como en el listado.

Hacia abajo aparece la sección colapsable **"Historial de ediciones"**, con una
entrada por corrección: quién, cuándo, motivo y el detalle *Antes / Después* de
cada campo tocado.

### 4.3 Editar boleto (corrección de errores de venta)

Permiso `tickets.edit`: **ningún rol lo trae por defecto**; se otorga desde la
matriz de Roles y permisos y recién ahí aparece la acción. Sirve para corregir
errores de carga **sin tocar el inventario**: se puede cambiar

- datos del pasajero: apellido, nombre, DNI y teléfono;
- el acompañante: "Viaja con un menor" (apellido, nombre y DNI del menor) y
  "Viaja con mascota" (nombres y cantidad);
- los datos económicos: precio y método de pago.

**No** se puede cambiar viaje, asiento, fecha ni horario: eso es Reprogramar
(ver 4.4).

Reglas y detalles:

- El DNI se valida al salir del campo: si el pasajero (o el menor) está en el
  padrón de clientes con prohibición de compra, aparece el aviso en el momento
  y no se puede guardar.
- Al apretar **Guardar cambios** se abre un modal que resume qué va a cambiar
  ("Apellido: \"García\" → \"García López\""). Si Configuración lo exige, el
  modal pide la contraseña ahí mismo.
- Si no hay cambios, o si la edición no se puede aplicar (contraseña incorrecta,
  cliente baneado, datos inválidos), no se guarda nada y aparece una notificación
  explicando el motivo.
- Cada campo cambiado queda registrado en el historial del boleto con valor
  anterior, valor nuevo, motivo (opcional) y autor.
- En los boletos de ida y vuelta, el precio vive en el tramo de ida: en el tramo
de vuelta el campo aparece deshabilitado con el precio real para no duplicar el dato.

### 4.4 Reprogramar boleto

Permiso `tickets.reschedule` (lo trae Administrador por defecto). Cambia **fecha,
horario, colectivo y asiento** de un boleto ya emitido, sin tocar los datos del
pasajero.

En la pantalla "Reprogramar boleto":

1. Se ve el **boleto actual** (viaje, fecha, horario y asiento).
2. El selector **"¿Qué tramo querés reprogramar?"** permite mover la ida, la
   vuelta, o ambos (según sea el boleto).
3. Por cada tramo se elige **nueva fecha**, **horario** (sólo los que existan
   para la ruta del colectivo en esa fecha) y **nuevo asiento** (sólo los
   disponibles en ese viaje; si el asiento ya está ocupado no deja avanzar).
4. **Confirmar reprogramación** aplica los cambios; si Configuración lo exige,
   pide la contraseña antes. **Cancelar** vuelve sin tocar nada.
5. Después de confirmar se puede **Descargar boleto** con los datos nuevos.

Cada reprogramación queda registrada en el Historial general (sección 4.6) con
la fecha/hora/asiento anterior y el nuevo.

### 4.5 Eliminar y restaurar

- La **eliminación es blanda** (soft delete): el boleto desaparece de los
  listados pero sigue en la base y en los reportes históricos. Pide **motivo
  obligatorio** y, si Configuración lo exige, contraseña.
- Ojo con esto: **la tabla de Boletos no tiene filtro de eliminados**, así que
  un boleto borrado no se ve en el listado. El **Historial** (4.6) sí muestra
  el movimiento con su estado "Eliminado", y desde ahí se obtiene el número
  para abrir el detalle del boleto por su enlace directo (el sistema permite
  abrir boletos eliminados por número) y apretar **Restaurar**.
- **Eliminar definitivamente** es sólo para casos extremos: borra el registro
  para siempre (ya no se puede recuperar ni por enlace directo).

### 4.6 Historial general de boletos (auditoría)

Menú **Historial** (permiso `ticket_audit.view_any`). Una fila por boleto con
movimientos, con columnas: N°, Pasajero, DNI, **Estado** (Editado / Reprogramado /
Eliminado / activo) y **Última actividad** (fecha y hora del último movimiento).

Filtros: Estado, Número de boleto y Pasajero. El detalle de cada fila muestra el
boleto y el desglose completo del movimiento: quién, cuándo, motivo, y el
*Antes / Después* de cada campo (precio en moneda, método de pago con su
etiqueta), incluido el motivo de eliminación cuando el boleto fue borrado.


## 5. Viajes

Menú **Viajes**: un viaje es un colectivo circulando una ruta con un horario en
una fecha. **No se crean a mano**: el sistema los genera solo cuando un vendedor
busca un viaje en el wizard de venta (ruta + horario + fecha, vía
"findOrCreateForBooking"). Esta pantalla sirve para **supervisar**.

| Columna | Qué muestra |
| --- | --- |
| Nº | Número de viaje |
| Colectivo | Colectivo asignado |
| Fecha | Fecha del viaje |
| Horario | Hora de salida |
| Ruta | Ruta del viaje |
| Asientos vendidos | Cuántos asientos ya están ocupados |
| Última actualización / Eliminado | Control de cambios |

**Filtros**: Colectivo, Viaje Nº, Fecha de salida, Horario y Ruta (el filtro
Ruta sólo ofrece rutas activas; ver 10.5).

**Acciones por fila**:

- **Ver detalles**: modal con el listado de los boletos de ese viaje, con dos
  exportaciones: **PDF** y **Excel**.
- **Descargar PDF / Descargar Excel**: la lista de pasajeros del viaje en cada
  formato.

Acciones masivas: eliminar, eliminar definitivamente y restaurar (sólo para
quienes tienen esos permisos).


## 6. Ventas

Menú **Ventas**: una fila por **vendedor**, con el resultado del período filtrado.

| Columna | Qué muestra |
| --- | --- |
| Usuario / Nombre | El vendedor |
| Boletos vendidos | Cantidad de boletos |
| Total ventas | Importe total vendido |
| Pagos | Cantidad de pagos registrados |
| Total pagos | Importe total cobrado |
| Saldo | Lo que falta cobrar (ventas − pagos) |

**Filtros**: rango de fechas (Desde / Hasta) y Método de pago.

**Acciones por fila**:

- **Ver detalle**: modal con los boletos que componen la venta de ese vendedor
  en el período.
- **Registrar pago**: alta rápida de un pago (Monto, Método de pago y Fecha de
  recepción) sin salir de la pantalla — útil para registrar cobros en
  efectivo/transferencia que llegaron por fuera.


## 7. Pagos y métodos de pago

### 7.1 Pagos

Menú **Pagos**: registro de cobros. Columnas: Fecha de pago, Vendedor, Monto,
Método (con color) y Cargado (fecha de carga). Botón **Nuevo pago** arriba
(Vendedor, Monto, Método de pago, Fecha de recepción).

**Filtros**: rango de fechas (Desde/Hasta), Vendedor y eliminados (Trashed).
**Exportaciones**: botones **PDF** y **Excel** del listado filtrado.

Por fila: Editar, Eliminar (blando), Eliminar definitivamente y Restaurar, más
las acciones masivas equivalentes. La eliminación definitiva de pagos es
exclusiva del Super Administrador.

### 7.2 Métodos de pago

Menú **Métodos de pago**: catálogo de los medios de cobro (efectivo, transferencia,
etc.). Alta con sólo el **Nombre**: el código interno (slug) y el orden se
generan solos y no se pueden editar después. Se pueden editar el nombre y el
color, eliminar (blando) y restaurar; el filtro de eliminados permite verlos.

Detalles que conviene saber:

- Sólo los métodos **activos** aparecen en los selectores de venta y pagos.
- Un método eliminado ya no se puede usar en ventas nuevas, pero los boletos y
  pagos históricos **siguen mostrando su nombre** (nunca se rompe el histórico).
- Se pueden dar de alta métodos nuevos desde el propio wizard de venta (ver 3.3).


## 8. Clientes

Menú **Clientes**: el padrón de clientes. Se alimenta desde la propia venta (ver
3.3) y desde acá.

**Formulario**: Apellido, Nombre, DNI, Teléfono, **¿Puede comprar boletos?**
(interruptor; apagado = cliente baneado) y **Motivo / Comentarios** (para qué
está baneado).

| Columna | Qué muestra |
| --- | --- |
| DNI, Nombre, Apellido, Teléfono | Datos del cliente |
| Estado | Puede comprar / baneado |
| Boletos históricos | Cuántos boletos tiene a su nombre (aunque esté eliminado el cliente) |

**Filtro**: Estado (puede comprar / baneado).**Acciones por fila**:

- **Banear / Habilitar** (permiso `clients.ban`): alterna la prohibición de
compra sin entrar al formulario; muestra el motivo si lo hay.
- **Resetear contraseña** (permiso `clients.reset_password`, lo trae
Administrador): si el cliente olvidó su contraseña del panel de clientes, se
resetea y vuelve a ser su DNI (hasheado en la base); al próximo ingreso debe
elegir otra.
- Editar, Eliminar, Eliminar definitivamente y Restaurar + acciones masivas.

Un cliente baneado **no puede comprar**: el wizard de venta y la edición de
boletos lo detectan al validar el DNI y bloquean con el motivo del baneo.


## 9. Flota (colectivos, diseños y asientos)

### 9.1 Colectivos

Menú **Colectivos**. Formulario: **Nombre, Patente, Cantidad de asientos,
Cantidad de pisos** y **"Partir de un diseño existente"** (opcional: copia la
distribución de otro colectivo o de una plantilla guardada al dar de alta).

| Columna | Qué muestra |
| --- | --- |
| Nombre / Patente | Identificación del colectivo |
| Cantidad de asientos / pisos | Capacidad y planta |
| Creación / Actualización | Control |

Filtro de eliminados. Acciones por fila: **Diseñar layout** (ver 9.2), Editar,
Eliminar, Eliminar definitivamente y Restaurar, más las masivas. Botón arriba:
**Nuevo colectivo**.

### 9.2 Bus Designer ("Diseñar layout")

Editor visual del interior del colectivo, sobre una grilla por piso:

- **Clic en una celda**: agrega o quita un asiento ahí (no deja borrar un
  asiento que ya tenga boletos).
- **Tipo de asiento**: cada asiento puede cambiar su tipo (por ejemplo, sentado
  o no sentado) desde la propia celda.
- **Mover**: el asiento se puede arrastrar a otra celda (avisa "Celda ocupada"
  si ya hay algo).
- **Zonas**: se dibujan rectángulos para baño, pasillo, puerta, etc. El sistema
  no deja que una zona tape un asiento ni que se superponga a otra zona.
- **Pisos**: se cambia de piso para diseñar cada planta.
- **Renumerar**: reordena la numeración de los asientos ("Numeración reordenada").
- **Copiar de otro colectivo**: trae el layout completo de otro colectivo.
- **Guardar / Descartar**: los cambios no se aplican hasta apretar Guardar
  ("Layout guardado"); Descartar vuelve al diseño guardado.
- **Guardar como plantilla**: guarda el diseño con un nombre propio
  (no se puede repetir) para reutilizarlo en otros colectivos.

### 9.3 Distribuciones de asientos y Asientos

Detrás del designer hay dos tablas de edición directa:

- **Distribuciones de asientos**: las zonas del layout (Colectivo, Piso, Tipo
  de área, Etiqueta, Fila inicial/final, Columna inicial/final, filas y
  columnas que abarca). Filtros por Colectivo, Piso, Tipo y eliminados.
- **Asientos**: cada plaza (Colectivo, Número, Piso, Fila, Columna, Posición,
  Tipo de asiento, Activo). Filtros por Colectivo, Estado y eliminados. Un
  asiento inactivo no se puede vender.

Ambas tienen CRUD completo con eliminación blanda y restauración.

### 9.4 Comandos de flota (consola)

| Comando | Qué hace |
| --- | --- |
| `app:clone-bus {colectivo}` | Clona un colectivo con su layout, rutas, paradas y horarios (no viajes ni boletos). Opciones `--name` y `--plate` |
| `app:setup-layout-linea1` / `linea2-55` / `linea2-60` | Carga inicial de layouts por línea |
| `app:swap-layouts-linea1-linea2` / `app:revert-...` | Intercambia / revierte los layouts entre Línea 1 y Línea 2 |
| `app:setup-embarcacion` | Carga inicial de paradas, horarios y layout del colectivo Embarcación (`--force` para recrear) |


## 10. Destinos, rutas, paradas y horarios

Menú **Destinos**: el corazón de la operación. Define dónde se puede subir y
bajar, por qué rutas circulan los colectivos y a qué horas.

### 10.1 Destinos (ubicaciones)

Formulario: **Nombre** (único: si se repite avisa "Ya existe un destino con
ese nombre") y el interruptor **"Disponible para vender"**. La tabla muestra
Nombre y el estado (palomita/cruz), con filtro por estado y por eliminados.

**Un destino desactivado no aparece en las ventas nuevas**; los boletos ya
vendidos ahí siguen mostrándolo y funcionando normal.

### 10.2 Rutas

Cada ruta une un colectivo con un recorrido de destinos. La tabla muestra:

| Columna | Qué muestra |
| --- | --- |
| Nombre | Nombre de la ruta |
| Colectivo | Colectivo asignado |
| Activa | Estado (palomita/cruz) |
| Recorrido | Resumen de un vistazo: "Tartagal (08:00) → Urundel (09:15)" |
| Horarios | Cuántos horarios tiene |

**Filtros**: Activa y eliminados. Arriba están:

- **"Mostrar rutas desactivadas"** (interruptor con estado en la URL):
  sin prenderlo, la tabla sólo lista rutas activas (ver 10.5).
- **"Asistente «Nueva ruta»"**: el asistente de 3 pasos (ver abajo).
- **Nuevo**: el formulario normal (Colectivo, Nombre, Activa).

**Acciones por fila**:

- **Asistente**: modifica la ruta existente con el mismo asistente de 3 pasos.
- **Duplicar**: copia la ruta con todas sus paradas y horarios; el modal permite
elegir el colectivo de la copia y el nombre (viene con "(copia)"); la copia
queda **desactivada** para no mezclarse con las que se venden, y se guarda todo
junto (o se copia completo o no se copia nada).
- Editar (CRUD normal), Eliminar, Eliminar definitivamente y Restaurar.

**Borrar con dependencias**: si la ruta, parada u horario ya se usa, el borrado
se bloquea con un mensaje claro que sugiere desactivarlo en su lugar (los
registros sin dependencias se borran normal).

#### El asistente de rutas (3 pasos)

1. **Recorrido**: Colectivo, Nombre de la ruta y los **Puntos del recorrido**
   (Destino + Hora de salida en cada punto). Valida que no se repita un destino
   ("El destino ya está en el recorrido") ni que un punto salga antes que la
   primera salida.
2. **Horarios**: las salidas del día (Hora de salida + Nombre opcional), con el
   bloque **"Cómo queda el día"** que muestra en vivo cómo calcan los horarios
   sobre el recorrido.
3. **Confirmar**: **"Checklist antes de guardar"** (un punto por requisito, con
   tilde, aviso o error) y el interruptor **Activa**. Al guardar: "Ruta creada"
   / "Ruta actualizada".

El asistente corre en paralelo al formulario normal: no reemplaza nada. En la
edición sincroniza paradas y horarios, y no deja sacar un punto que ya esté en
uso ("No se puede sacar este punto del recorrido").

### 10.3 Paradas

Pantalla propia con **selector de Ruta arriba** (sólo rutas activas; ver 10.5).
Cada parada es un punto de una ruta:

- **Ruta, Destino (punto del recorrido), Orden en el recorrido**.
- **"Hora de salida del colectivo desde esta parada"** y **"Hora de llegada a
  esta parada"**: se cargan como horas reales (ej. 08:45) y el sistema deriva
  los offsets por debajo (sin tocar la base de datos).

La tabla (Destino, Orden, Hora de salida, Hora de llegada) permite **arrastrar
la fila para reordenar** el recorrido (pide el mismo permiso que editar). Las
validaciones avisan con mensajes claros: orden repetido, mismo destino dos veces
en la ruta y llegada antes que la salida.

### 10.4 Horarios

Formulario: **Ruta, Nombre del horario, Hora de salida, Hora de llegada y
Activo**. La tabla muestra Ruta, Nombre, Salida, Llegada, **Horario completo**,
Estado y **Viajes** (cuántos viajes genera). Filtros: Ruta (sólo rutas activas;
ver 10.5), Estado y eliminados. Cada horario activo genera viajes para las
fechas en que se vende.

### 10.5 Rutas desactivadas: qué se oculta

Cuando una ruta se desactiva, deja de aparecer en ningún lado hasta prender
**"Mostrar rutas desactivadas"**:

| Pantalla | Qué se oculta hasta prender el toggle |
| --- | --- |
| Rutas | La tabla lista sólo activas |
| Paradas | El selector de ruta no ofrece desactivadas (la pantalla abre en la primera activa) |
| Horarios | Los horarios de rutas desactivadas y el filtro Ruta no las ofrece |
| Formularios de Parada y Horario | El selector Ruta no ofrece desactivadas |
| Viajes | El filtro Ruta no ofrece desactivadas (los viajes se siguen listando igual) |

Al apagar el toggle se limpian los filtros que dejarían la tabla vacía. Duplicar
y los asistentes abren la vista con el toggle prendido si la ruta quedó
desactivada, para que no se pierda de vista. **El histórico no se toca**: viajes
y boletos de rutas desactivadas siguen mostrando su ruta.


## 11. Usuarios

Menú **Usuarios**. Alta con el botón **Nuevo usuario** (modal):

- **Usuario** (nombre de acceso para el login), **Contraseña**, **Nombre** y **Apellido**.
- **Roles**: obligatorio. Si alguien quedara sin rol, el sistema le asigna
  Vendedor como red de seguridad.

| Columna | Qué muestra |
| --- | --- |
| Usuario / Nombre | Identificación |
| Roles | Roles asignados |

**Filtros**: por Rol y por eliminados. **Acciones por fila**: Editar,
**Restablecer contraseña** (modal propio, avisa "Contraseña restablecida"),
Eliminar, Eliminar definitivamente y Restaurar (las acciones de borrado están
deshabilitadas para la cuenta de respaldo del sistema). El botón **Nuevo
usuario** está arriba.


## 12. Roles y permisos

Menú **Roles y permisos**. Cada rol agrupa permisos de la forma
`módulo.acción` en una **matriz con casillas por módulo** (Boletos, Clientes,
Ventas, Viajes, Pagos, Métodos de pago, Usuarios, Roles, Historial de boletos,
Configuración, Colectivos, Distribuciones de asientos, Ubicaciones, Rutas,
Paradas, Horarios, Asientos). La tabla muestra Rol, cantidad de Permisos y
cantidad de Usuarios.

### 12.1 Los tres roles del sistema

| Rol | Qué puede (por defecto) |
| --- | --- |
| **Super Administrador** | Todo, siempre (incluye borrar pagos definitivamente y administrar la configuración) |
| **Administrador** | Boletos (ver, vender, reprogramar, eliminar/restaurar), clientes completo (incl. banear), ventas, viajes, pagos (sin borrado definitivo), métodos de pago, usuarios y roles, historial y configuración |
| **Vendedor** | Ver y vender boletos; ver, crear y editar clientes; ver ventas y viajes |

Los roles del sistema **no se borran desde la UI**, y ningún rol puede otorgar
el de Super Administrador.

### 12.2 Permisos que se habilitan a mano ("por bandera")

Algunos permisos **no vienen en ningún rol por defecto** y se asignan desde la
matriz cuando se necesitan:

- `tickets.edit` — editar datos de un boleto (sección 4.3).
- `tickets.vender_sin_fecha` — vender sin fecha/horario (sección 3.5).
- `tickets.vender_pasado_limite` — vender después del plazo de salida (sección 3.5).
- `clients.ban` — banear/habilitar clientes (sólo Administrador lo trae).

Mientras un permiso de este tipo no esté asignado a ningún rol (el Super
Administrador lo tiene por bypass), la función asociada **no aparece en la
interfaz**. Al asignarlo a un rol, aparece sola, sin tocar código.

### 12.3 Comando de sincronización

`php artisan permissions:sync` crea los permisos y roles del catálogo. Es
idempotente y **sólo agrega**: nunca revoca permisos que se hayan otorgado
desde la matriz. Con `--reconcile-users` además recalcula el indicador de
administrador de todos los usuarios a partir de sus roles.

Detalle completo de la matriz por rol: `docs/roles-y-permisos.md`.


## 13. Configuración

Menú **Configuración** (permiso `settings.manage`). Dos bloques:

**Confirmación de contraseña** — tres interruptores que exigen la clave del
admin logueado antes de acciones sensibles sobre boletos:

| Interruptor | Efecto |
| --- | --- |
| Pedir contraseña al borrar boletos | Sin la clave correcta no se elimina (ni definitivamente) |
| Pedir contraseña al reprogramar boletos | Sin la clave correcta no se reprograma |
| Pedir contraseña al editar boletos | Sin la clave correcta no se guardan las ediciones de datos |

Los tres están apagados por defecto.

**Venta fuera de término** — **"Horas de venta después de la salida"** (default
5, entre 0 y 720): plazo, contado desde la salida del colectivo en la parada
donde sube el pasajero, durante el cual el wizard deja vender ese viaje. Ejemplo:
con 5 horas, un viaje que salió 08:00 se vende hasta las 13:00. **0 = sin
límite**. Pasado el plazo, sólo puede vender quien tenga el permiso
`tickets.vender_pasado_limite`.


## 14. Perfil y acceso

- **Login**: Nombre de usuario + contraseña. Debajo del formulario hay un
  apartado **«¿Entraste por error?»** con un enlace **Ingresar a «Mis
  boletos»** que lleva al panel de clientes (ingreso por DNI), para el
  pasajero que llegó por error al login de administración.- **Editar perfil** (menú de la cuenta, arriba a la derecha): Nombre, Apellido,
Correo electrónico, Teléfono, Nombre de usuario, Nueva contraseña +
Confirmar nueva contraseña (ambas piden la **Contraseña actual**). Los Roles
se muestran en modo lectura: sólo un administrador los cambia.

### 14.1 Panel de clientes (/clientes): acceso por DNI + contraseña

El panel público donde el pasajero ve sus boletos identifica así:

- **Ingreso**: DNI + contraseña, **siempre obligatoria**: nadie entra con el
DNI solo. La **contraseña inicial de todo cliente es su propio DNI** (hasheada
en la base desde la alta o un reset), y al primer ingreso (bandera en la base)
sale un cartel **«Protegé tu cuenta»** que lo obliga a **elegir una contraseña
propia** y no deja usar nada hasta que la define. Esa primera pantalla lo
**recibe por su nombre** («¡Bienvenido a Expresso Sofia, Nombre!») y explica
por qué le piden la clave, en vez de ir directo al formulario.
- Los campos de contraseña muestran un **botón de ojito** para ver/ocultar lo
tipeado. Validaciones simples: mínimo 6 caracteres y que las dos coincidan;
**el error se resalta debajo de cada campo, como en el panel de
administración**, y las notificaciones quedan reservadas para lo que no es un
error de formulario (éxitos, límite de intentos, problemas inesperados).
- Un cliente que ya eligió contraseña entra con DNI + la elegida; si la
olvidó, se resuelve con **Resetear contraseña** (sección 8).
- **Mis boletos** (la pantalla del panel público): columna **Tipo** etiqueta el
tramo — «Ida» y «Vuelta de N°X» (fucsia) para los tramos de un diferido, y
**«Solo ida»** (neutro) para el boleto que no pertenece a un diferido. En el
celular quedan las columnas **N°, Tramo y Fecha/Horario** y las acciones
(**Elegir fecha**, **Ver boleto**) se guardan dentro del **menú de tres
puntitos** de cada fila.
- **Mi perfil** (menú del panel público): el cliente puede actualizar Nombre,
Apellido y Teléfono, y cambiar su contraseña (pidiendo la actual). El **DNI
queda read-only**: es la clave que une clientes, pasajeros y boletos — si está
mal, se corrige en boletería.


## 15. Comandos de consola (artisan)

| Comando | Para qué |
| --- | --- |
| `app:cleanup-expired-reservations` | Libera los asientos reservados vencidos. El programador lo corre **cada 5 minutos** (requiere el cron del servidor con `schedule:run`) |
| `app:clone-bus {colectivo}` | Clona un colectivo con layout, rutas, paradas y horarios (`--name`, `--plate`) |
| `app:setup-layout-linea1` / `-linea2-55` / `-linea2-60` | Carga inicial de layouts de asientos por línea |
| `app:swap-layouts-linea1-linea2` / `app:revert-layouts-linea1-linea2` | Intercambia / revierte layouts entre Línea 1 y Línea 2 |
| `app:setup-embarcacion` | Carga inicial del colectivo Embarcación (`--force` para recrear) |
| `tickets:repair-round-trip-links` | Repara vínculos ida↔vuelta huérfanos (`--dry-run` para ver qué haría sin escribir) |
| `permissions:sync [--reconcile-users]` | Aplica novedades del catálogo de permisos (sólo agrega) |


## 16. Mapa de datos (de qué vive cada cosa)

Para entender de dónde salen los números de cada pantalla:

| Entidad | Qué guarda y con qué se vincula |
| --- | --- |
| **Colectivo (Bus)** | Un vehículo: nombre, patente, pisos, cantidad de asientos. Tiene sus Asientos, sus zonas de Distribución y sus Rutas |
| **Asiento (Seat)** | Una plaza del colectivo (número, piso, fila, columna, posición, tipo, activo) |
| **Destino (Location)** | Un pueblo/parada del mapa. Nombre único; se activa o desactiva para vender |
| **Ruta (Route)** | Colectivo + recorrido de destinos. Tiene Paradas y Horarios; se activa o desactiva |
| **Parada (RouteStop)** | Un punto de la ruta, con orden y horas de salida/llegada (guardadas como offset desde la primera salida) |
| **Horario (Schedule)** | Una salida diaria de la ruta (hora de salida y llegada, activo) |
| **Viaje (Trip)** | Horario + fecha concreta; se crea solo al buscar en el wizard. Tiene sus asientos vendidos y reservados |
| **Reserva (SeatReservation)** | Asiento apartado temporalmente (5 min) para una sesión de venta |
| **Cliente (Clients)** | Padrón: datos + "¿puede comprar?" (baneo) y motivo |
| **Pasajero (Passenger)** | Quien viaja en un boleto; puede tener menores (hijos) asociados |
| **Venta (Sale)** | Una operación de cierre de caja: agrupa boletos y pagos de un vendedor en una fecha |
| **Boleto (Ticket)** | Un asiento vendido a un pasajero: viaje (o "sin fecha"), asiento, origen/destino, precio, método de pago, si es ida y vuelta, menores/mascotas. Eliminación blanda con motivo |
| **Edición (TicketEdit)** | Auditoría de correcciones de datos (campo, antes, después, motivo, autor) |
| **Reprogramación (TicketDateChange)** | Auditoría de cambios de fecha/hora/asiento |
| **Pago (Payment)** | Cobro registrado a un vendedor (monto, método, fecha) |
| **Método de pago (PaymentMethod)** | Catálogo de medios de cobro (nombre, color, activo) |
| **Usuario (User)** | Cuenta del panel, con roles (Spatie) |
| **Configuración (Setting)** | Pares clave/valor de los parámetros de la sección 13 |


## 17. Glosario

| Término | Significado en este sistema |
| --- | --- |
| **Boleto** | Un asiento vendido a un pasajero para un viaje |
| **Venta** | La operación completa en la caja (puede tener varios boletos) |
| **Viaje** | Colectivo + ruta + horario + fecha |
| **Ruta / Parada / Horario** | El recorrido, cada punto del recorrido, y la salida diaria |
| **Diferido** | Ida y vuelta en una misma venta (los dos tramos en un boleto doble) |
| **Sin fecha** | Boleto vendido hoy cuya fecha de viaje se asigna después con Reprogramar |
| **Reprogramar** | Cambiar fecha/horario/asiento de un boleto ya emitido |
| **Editar boleto** | Corregir datos del pasajero o de la cobranza sin tocar el viaje |
| **Banear** | Quitarle a un cliente la posibilidad de comprar (se controla por DNI) |
| **Layout** | El dibujo del interior del colectivo (asientos, pisos, zonas) |
| **Reserva** | Asiento apartado 5 minutos mientras un vendedor arma la venta |
| **Eliminado / Restaurar** | Borrado blando: se oculta pero no se pierde; se puede recuperar |
| **Permiso-bandera** | Permiso que no se da por defecto y habilita una función al asignarlo |

