# Crown — briefing para implementación en sala

Documento operativo para llevar a Crown. Complementa `tesoreria-crown-manual.md` y el manual de usuario en `docs/manual-tesoreria-crown/`.

## Chats en el repo (recomendado para el viaje)

Los hilos útiles de **Tesorería Crown** y **Gastronomía Crown** están exportados acá:

**[`docs/caja/chats-crown/`](chats-crown/README.md)**

- Índice por fecha/tema
- Carpetas `tesoreria/` y `gastronomia/`
- Diálogo redactado (sin passwords ni dumps de tools)

En la notebook: `git pull` y abrí `@docs/caja/chats-crown/README.md` (o el chat puntual).

Cursor **no sincroniza** el historial local entre PCs; este export es la copia portable. Cloud Agents / Share quedan como opcionales.

---

## Qué quedó armado / tocando recién (Tesorería Crown)

### Resumen de cajeros — auditoría por importe

- Panel jornada → **Res. cajeros**.
- Cada importe azul (o botón ℹ) abre modal:
  - **Cómo se calcula**
  - **Cómo auditarlo**
  - **Documentos que lo arman** (links a rendición / habilitación / cierre)
- Backend: `TesoreriaCrownAnexo3AuditoriaSupport` (+ columnas voucher).
- Ruta: `auditoria_rescajero_tesoreria_crown_jornada`.
- UI compartida con Anexo III: partial `modal_auditoria_importe`, JS `auditoria_importe.js`, CSS en `tesoreria-crown-jornada.css`.

### Index de slots

- Orden por **fecha de jornada** (antes por `id`: el 30/10 quedaba en página 2).
- Selector de jornada: filtra solo al pulsar **Ir**; “Nuevo” usa la jornada abierta sin filtrar el histórico.
- En CIP el 30/10/2026 hay **1** slot Recibido (#949725), no el lote de Contadora/Corrección del screenshot viejo.

### ABMs / UI reciente

- Tipos de movimiento de bóveda (ABM).
- Mesas = depósitos tipo Mesa; config general (no ABM Opus aparte).
- Slots / Bingo: index con filtros + export; form slot barra full-width; Volver según `origen=menu` vs escritorio.
- Resultado online: gráficos variables (lienzo listado).
- Borrador slot: permiso `eliminar-borrador` + ícono en index.

### Reglas de entorno

- Tablas/migraciones Crown: gate `EntornoEmpresaSupport::esCrown()`.
- Export en listados Crown: chips de sala + export compacto (no botones grandes arriba).

---

## Mapa rápido de pantallas

| Necesidad | Dónde |
|---|---|
| Escritorio del día | `ver_tesoreria_crown_jornada` (paneles cajeros / mesas / bóveda / slots / bingo / anexos) |
| Res. cajeros + audit | panel `rescajero` |
| Anexo III + audit | panel `anexo3` |
| Slots listado menú | `tesoreria_crown_slot` |
| Manual usuario PDF | `docs/manual-tesoreria-crown/Manual_Usuario_AnitaERP_Tesoreria_Crown.pdf` |
| Manual técnico | `docs/caja/tesoreria-crown-manual.md` |

---

## Checklist en sala

1. Login con usuario Crown / sala CIP (u otra).
2. Jornada del día abierta.
3. Recorrer: cajeros → cierres → res. cajeros (probar ℹ en un importe) → anexo III.
4. Slots: filtrar jornada con **Ir**; borrar borrador si aplica; nuevo desde menú vs escritorio.
5. Bingo / bóveda / mesas según el plan del día.
6. Si algo no cuadra: anotar caja, asignación, número de comprobante y columna; abrir el modal de auditoría y el link al documento.

---

## Archivos clave (código)

```
app/Support/Caja/TesoreriaCrownResumenCajeroCalculo.php
app/Support/Caja/TesoreriaCrownAnexo3AuditoriaSupport.php
app/Http/Controllers/Caja/TesoreriaCrownAnexoController.php
app/Http/Controllers/Caja/TesoreriaCrownSlotController.php
resources/views/caja/tesoreria_crown/jornada/partials/rescajero.blade.php
resources/views/caja/tesoreria_crown/jornada/partials/anexo3.blade.php
resources/views/caja/tesoreria_crown/jornada/partials/modal_auditoria_importe.blade.php
public/assets/pages/scripts/caja/tesoreria_crown/auditoria_importe.js
public/assets/css/tesoreria-crown-jornada.css
```

---

*Generado para el viaje de implementación. Actualizar este archivo si el plan del día cambia.*
