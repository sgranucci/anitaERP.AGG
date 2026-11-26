# Auditor P3 (IVA fuera de tipo): excepción FNB A 0046-3119 (#32657)

**Empresa:** Emprendimientos Crown SA
**Fecha de la decisión:** 26/11/2026
**Decidido por:** Sergio Granucci (operador)
**Estado:** Aceptado tal cual. Consulta enviada a Contaduría el 26/11/2026; pendiente de respuesta.

---

## Comprobante

| Campo | Valor |
|---|---|
| ID ERP | 32657 |
| Tipo / número | FNB A 0046-3119 |
| Proveedor | VALLE FULL S.R.L. (ERP 5257 / Anita 007100) |
| Total | 62.025,00 (gravado servicios 21 % 50.777,06 + impuesto interno 584,76 + IVA servicios directos 21 % 10.663,18) |
| Fecha al cargar | 10/10/2026 |
| Fecha actual | 04/11/2026 (cambiada el 25/11/2026 por lroldan, desde `/caja/actualizaringresoegreso/197`) |
| Origen | INGRESO_EGRESO: rendición EGR 56141 (caja 197, asiento 627235 / Anita asiento 823054) |

## Por qué salta el auditor

- El concepto **221 Iva Serv.Dir.21** solo está cargado en tipos de servicios directos (FDS, CDS, DDS). El tipo **FNB** es de bienes y no lo admite.
- Las líneas (51 Gravado Servic.21, 5 Impuesto interno, 221 IVA Serv.Dir.21) corresponden a un tipo **FDS**, no a FNB.
- El auditor compara la fecha `fechaiva`. Con la fecha original (10/10) el comprobante estaba fuera de noviembre; con 04/11 entra en el período y aparece como desvío.

## Verificación de la contabilidad

- El asiento 627235 cuadra: 25 movimientos, debe = haber = 365.215,06.
- Las líneas del comprobante suman 62.025,00, igual que el total.
- Anita (`ctamov` EGR 56141, fecha 20261104) tiene los mismos importes y cuentas.
- La cuenta 130200000 (CREDITO FISCAL IVA DIRECTO) es la del IVA directo de servicios, coherente con FDS.

## Puntos pendientes de confirmar

1. **Fecha real de la factura** (10/10 o 04/11): según el papel.
2. **Naturaleza** (servicios o bienes): si es de servicios, el tipo correcto sería FDS.
3. **Centro de costo** de gravado e impuesto interno: cambiado el 25/11 de 1 (MAESTRANZA CORPORATIVO) a 166 (ADMINISTRACION ON LINE). Confirmar si es el correcto.

## Decisión y excepción

Se mantiene el registro actual (tipo FNB, fecha 04/11, centros de costo como están) hasta que Contaduría responda. Para que el control no lo reporte como desvío mientras tanto, el comprobante se agregó como **excepción** en el P3:

- Variable: `CP_AUDITOR_IVA_TIPO_EXCLUIR_IDS=32657` (en `.env`; documentada en `.env.example`).
- Config: `config/comprobante_proveedor_auditoria_erp.php`, clave `iva_fuera_tipo.excluir_comprobante_ids`.
- Lógica: `App\Services\Compras\ComprobanteProveedorAuditorIvaTipoService`. El comprobante excluido cuenta como **omitido**.
- Verificación del 26/11/2026 (período 01/11 → 26/11): en alcance 709, OK 708, omitidos 1, desvíos 0.

**Esta excepción tapa un problema de clasificación. No lo resuelve.**

## Cómo revertir

Quitar el ID de `CP_AUDITOR_IVA_TIPO_EXCLUIR_IDS` (o dejarla vacía), luego:

```
php artisan config:clear && php artisan config:cache
```

Revertir también si Contaduría cambia el tipo, la fecha o las líneas del comprobante. Si el comprobante se corrige y ya no genera desvío, la excepción debe quitarse igual.
