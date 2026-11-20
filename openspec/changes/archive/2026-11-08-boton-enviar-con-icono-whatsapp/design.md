# Design

## Context

Ver `proposal.md`. El botón de las filas y de "Enviar todos" es uno solo, el de `delivery-modal-phone.blade.php` (`x-filament::button` con `icon="heroicon-m-paper-airplane"`), y el texto llega por `$buttonLabel` desde `delivery-modal.blade.php`. En el detalle del boleto, `ViewTicket` declara la acción `enviar_whatsapp` con `->icon('heroicon-m-paper-airplane')`. El ícono de WhatsApp ya existe como componente (`resources/views/components/icons/whatsapp.blade.php`, con `data-icon="whatsapp"`) y los botones de contacto del panel de clientes lo usan dentro del botón, con `size-4 text-white`.

## Goals / Non-Goals

**Goals:**
- Un mismo ícono y un mismo texto corto en los tres botones.
- Mantener un nombre accesible completo.

**Non-Goals:**
- Cambiar enlaces, mensaje o cuándo se muestran los botones.
- Tocar los botones que ya usan el ícono.

## Decisions

**1. Texto "Enviar" y "Enviar todos", con el ícono como contenido del botón.**
En el modal, el ícono va dentro del botón junto al texto (`<x-icons.whatsapp class="size-4 text-white" />`), como en los contactos del panel de clientes, y se quita la propiedad `icon` del avión. `$buttonLabel` pasa a "Enviar" (filas) y "Enviar todos" (bloque de todos). Alternativa descartada: registrar el SVG como ícono de Filament, que obligaría a mantener otro registro y a que el color y el tamaño los decida Filament, distintos de los demás usos.

**2. Nombre accesible completo.**
Con solo "Enviar" a la vista, los botones llevan `title` y `aria-label` "Enviar por WhatsApp" ("Enviar todos por WhatsApp" para el bloque de todos). Así el tooltip y los lectores de pantalla conservan el contexto que ya no se ve.

**3. En el detalle del boleto, el ícono entra como HTML.**
La acción de Filament acepta un ícono `Htmlable`: se le pasa el componente renderizado. Se mantiene el color `success`, el nombre interno `enviar_whatsapp`, la URL y la apertura en pestaña nueva. Se agrega `->extraAttributes(['title' => 'Enviar por WhatsApp'])` para el nombre accesible, igual que "Descargar" lleva su `title`.

**4. Texto e ícono en blanco.**
El texto de los botones lleva `text-white!` (con `!`, porque Filament le da su propio color a los botones y sin él puede ganar) y el ícono, `text-white`. En la acción del detalle, Filament envuelve el ícono en un contenedor que le aplica su color de ícono de botón, por lo que el SVG lleva `text-white!` por sí mismo.

**5. Botones de contacto del panel de clientes solo con ícono.**
En la ayuda de "Mis boletos" y en el ingreso, los botones de teléfono y de WhatsApp muestran solo el ícono (el de WhatsApp en blanco), con `title` "Llamar" y "WhatsApp" para el tooltip y los lectores de pantalla. En el ingreso, el teléfono escrito también lleva `title="Llamar"`. Es el mismo criterio que los botones "Enviar": ícono de la aplicación y nombre accesible completo.

## Risks / Trade-offs

- [Sin "por WhatsApp" a la vista, el botón depende del ícono para decir qué hace] → El ícono es el mismo de toda la aplicación y el nombre accesible sigue completo.
- [Los tests buscan el texto viejo] → Se actualizan los listados en `Impact`; el nombre interno `enviar_whatsapp` no cambia, así que los demás tests no se ven afectados.
- [El ícono como `Htmlable` en la acción del encabezado no hereda el tamaño de los demás íconos de Filament] → Se fija su tamaño con una clase, como en el modal.
