# Diseño

## Contexto

El límite de 4 vive solo en el `Select::make('passengers_count')` de `TicketForm`, en tres partes: las opciones `1..4`, la regla `in:1,2,3,4` y el mensaje "entre 1 y 4". El repeater de pasajeros ya ajusta su mínimo y su máximo a `passengers_count`, así que no conoce el número 4. Otros formularios usan el mismo selector de asientos con `passengers_count => 1` fijo (Elegir fecha del cliente y la reprogramación). No dependen del límite y no cambian.

Los parámetros siguen un patrón establecido: una clave en `Setting`, un getter con valor por defecto, una sección en `TicketSettings` visible según un permiso `settings.*`, y un guardado que solo escribe si quien guarda tiene el permiso y el campo llegó en el estado. Este cambio sigue ese patrón sin variantes.

## Objetivos / Fuera de alcance

**Objetivos:**
- Un único lugar que resuelve el límite (`Setting`), usado por el formulario y por la validación del servidor. Las reservas de clientes lo van a usar después.
- Comportamiento idéntico al actual mientras nadie toque el parámetro.

**Fuera de alcance:**
- Límites distintos por rol o por vendedor.
- Cambiar la cantidad de pasajeros de ventas ya emitidas.

## Decisiones

**1. Getter con límites en `Setting`.** `Setting::limitePasajerosVenta(): int` devuelve el valor guardado acotado a `[1, 8]`, y 4 si no está configurado o no es numérico. Las constantes `LIMITE_PASAJEROS_VENTA`, `LIMITE_PASAJEROS_VENTA_DEFAULT = 4` y `LIMITE_PASAJEROS_VENTA_MAX = 8` las comparten el getter, la validación de Parámetros y el formulario de venta.
- *Por qué acotar en el getter:* un valor corrupto o cargado a mano en la base (0, 50) no puede dejar la venta sin opciones ni habilitar ventas gigantes.

**2. Opciones, regla y mensaje se calculan a partir del getter en el formulario de venta.** Las opciones se generan con `range(1, $limite)` y la regla pasa a ser `in:` más esa misma lista, o un `integer|between:1,$limite` equivalente. El mensaje se arma con el límite. Filament evalúa las closures en cada render y en cada validación, así que la emisión siempre valida contra el valor vigente al emitir. Eso cubre el escenario "el límite baja durante una venta" sin código extra.
- *Alternativa descartada:* validar solo en el repeater (`maxItems`). Hoy el repeater se ata a `passengers_count`, así que bajar el límite no lo rechazaría si el pedido trae una cantidad mayor coherente con su lista de pasajeros.

**3. Permiso por bandera, sin defaults.** `settings.passenger_limit` se agrega al catálogo del módulo `settings` y no entra en `defaultsFor()`. Es el mismo trato que `settings.fixed_price`. El Super Administrador lo tiene por el bypass.

**4. Ubicación de la sección.** "Pasajeros por venta" va después de "Venta fuera de término" y antes de los selectores. "Precio de los boletos" queda al final de la pestaña, como exige su escenario.

## Riesgos / Compromisos

- [Un vendedor con la venta abierta ve opciones viejas hasta que la página se vuelve a renderizar] → La validación del servidor usa el valor vigente y rechaza con el mensaje del límite nuevo. Es aceptable porque cambiar el límite es raro.
- [Con 8 pasajeros, el paso de pasajeros se vuelve largo] → Es un tope elegido por el usuario. El paso ya repite el formulario por pasajero sin cambios de diseño.
- [Tests existentes que esperan el mensaje "entre 1 y 4"] → Siguen pasando: sin configurar, el límite es 4 y el mensaje es idéntico.

## Plan de despliegue

1. Deploy del código.
2. `php artisan permissions:sync` para crear `settings.passenger_limit`.
3. No hay migración: sin fila en `settings`, el límite es 4, como hoy.

Rollback: revertir el código. La fila de `settings` y el permiso que queden son inofensivos.
