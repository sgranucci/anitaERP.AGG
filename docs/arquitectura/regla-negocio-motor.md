# Motor de reglas de negocio (BRE transversal)

**Fecha:** 17 nov 2026  
**Fases:** F0–F5 + ampliación (canon, árbol, AFIP, diseñador IF). Madurez ~80%.

## Qué es

Runtime **síncrono** para decisiones de negocio tipadas:

```php
$d = app(\App\Services\Configuracion\ReglaNegocioMotorService::class)
    ->decidir('stock.tm.requiere_aprobacion', $ctx);

if (! $d->evaluado) {
    // Motor OFF o sin match → legacy hardcode
}
if (! $d->permitido) {
    return ['ok' => false, 'mensaje' => $d->mensaje];
}
$requiere = (bool) ($d->datos['requiere_aprobacion'] ?? false);
```

## Piezas

| Pieza | Ruta |
|---|---|
| Evaluador IF | `app/Support/ReglaNegocio/ReglaNegocioCondicionSupport.php` |
| Consulta segura | `ReglaNegocioConsultaSupport` |
| Versionado | `ReglaNegocioVersionSupport` + tabla `regla_negocio_version` |
| Cobertura | `ReglaNegocioCoberturaSupport` |
| Puente avisos | `ReglaNegocioAvisoBridgeSupport` |
| Motor | `app/Services/Configuracion/ReglaNegocioMotorService.php` |
| UI | Configuración → Reglas de negocio |
| Kill-switch | `REGLA_NEGOCIO_HABILITADO` ∩ `regla_negocio_config.motor_habilitado` |
| Purge logs | `php artisan regla-negocio:purgar-logs` |

## Acciones (catálogo cerrado)

`noop` · `deny` · `set_dato` · `require_approval` · `enviar_aviso` (puente F5)

## Permisos (F4)

| Slug | Uso |
|---|---|
| `listar-regla-negocio` | Ver |
| `disenar-regla-negocio` | Crear/editar/probar/restaurar (sin poder activar si no tiene el otro) |
| `activar-regla-negocio` | Motor ON/OFF + toggle activo |
| `actualizar-regla-negocio` | Compat: implica diseñar + activar |

## Puntos cableados

| Punto | Support |
|---|---|
| `stock.tm.requiere_aprobacion` | TransferenciaMercaderiaAprobacionSupport |
| `compras.com.reserva_factura` | ComprobanteProveedorReservaComLegajoSupport |
| `contable.canon.alicuota` | CanonEntidadesReglasSupport |
| `caja.programa_pago.cheques_param` | ProgramaPagoAsignarChequesConfigSupport |
| `configuracion.arbol.umbral_doble_aprobacion` | ArbolAprobacionUmbralSupport |
| `ventas.afip.circuito` | FacturacionCircuitoAfipSupport |

`CanonEntidadesCalculoSupport::calcular($dias, $regla, $params)` acepta overrides de
`resolver()` (piso/alícuotas). El reporte pasa la identidad completa.

UI: diseñador IF/THEN visual en `regla_negocio/form` (JSON avanzado colapsado).

## F5 — Puente avisos

- Evaluador IF compartido (`AvisoReglaCondicionSupport` → wrapper).
- Storage **separado** a propósito: `aviso_regla*` (async EventBus) vs `regla_negocio*` (sync).
- Acción BRE `enviar_aviso` puede notificar tipado desde una regla sync.
- UI muestra estado de ambos motores.

## No-objetivos

- Reemplazar árbol de aprobación completo / fórmulas sueldos / AFIP completo
- Fusionar tablas `aviso_regla` → `regla_negocio` (costo alto, poco ROI)
