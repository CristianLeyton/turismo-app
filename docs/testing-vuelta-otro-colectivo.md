# Cómo testear: Vuelta con otro colectivo

## 1. Tests automatizados

Ejecutá los tests de la app (incluido el de vuelta con otro colectivo):

```bash
php artisan test
```

O solo el test de vuelta con otro colectivo:

```bash
php artisan test --filter=ReturnTripDifferentBusTest
```

Ese test comprueba que, con dos colectivos que tienen rutas Salta↔Orán, al pedir horarios de **vuelta** (Salta→Orán) se devuelvan horarios de **ambos** colectivos, no solo del de la ida.

---

## 2. Checklist manual (cuando cambies algo del flujo)

Hacé estas pruebas en el panel de venta de boletos:

### Ida en un colectivo, vuelta en otro

- [ ] **Colectivo ida:** Elegir "Linea 2 (Embarcacion)". Origen: Orán, Destino: Salta. Fecha y horario de ida. Verificar viaje y continuar.
- [ ] **Ida y vuelta:** Marcar "Ida y vuelta". Fecha de vuelta (≥ fecha ida).
- [ ] **Horario de vuelta:** En el desplegable "Horario de vuelta" deben aparecer horarios de **Linea 1** y de **Linea 2** (si ambos tienen ruta Salta→Orán ese día).
- [ ] Elegir un horario de **Linea 1** para la vuelta. Completar asientos y pasajeros.
- [ ] **Resumen:** Ver que se muestren correctamente ida (Linea 2, Orán→Salta) y vuelta (Linea 1, Salta→Orán).
- [ ] **Header del wizard:** En los pasos superiores debe verse "Ida — Colectivo: Linea 2 ..." y "Vuelta — Colectivo: Linea 1 ...".
- [ ] Finalizar venta y comprobar que el boleto guarde bien el viaje de ida y el de vuelta (y que el de vuelta sea del colectivo elegido).

### Mismo colectivo ida y vuelta

- [ ] Hacer una venta ida y vuelta eligiendo el **mismo** colectivo para ida y vuelta y confirmar que todo funcione igual (asientos, resumen, PDF si aplica).

### Sin vuelta

- [ ] Una venta solo ida: el header no debe mostrar fila "Vuelta" y el resumen solo ida.

---

## 3. Qué revisar si algo falla

- **No aparecen horarios de vuelta de otro colectivo:** En `TicketForm.php`, el `Select::make('return_schedule_id')` en `->options(...)` y `->placeholder(...)` (y `->helperText` si existe) **no** debe filtrar por `bus_id`. Debe buscar solo por rutas que tengan parada en origen y destino de la vuelta y `isValidSegment`.
- **Header sin vuelta:** En `wizard-step-header.blade.php` se usa `isRoundTrip` y los datos `returnBusName`, `returnOriginName`, `returnDestinationName` que envía cada `viewData` del wizard. Comprobar que los tres pasos que usan el header pasen esos datos cuando hay `return_trip_id`.
- **Viaje de vuelta incorrecto:** `Trip::findOrCreateForBooking` se llama con el `return_schedule_id` elegido; ese schedule ya tiene su `route` y por tanto el bus. No debe usarse el bus de la ida para la vuelta.
