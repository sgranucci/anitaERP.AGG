# FIX-FIB-COM-VIA-PEP — Mayor por concepto

**Fecha:** 2026-08-06  
**Archivo:** `app/Support/Contable/MayorConcepto/MayorConceptoPeriodoProcesador.php`  
**Marcadores en código:** `FIX-FIB-COM-VIA-PEP`

## Problema

OPP con factura **FIB** (y similares FIA/FIC/…) cuyo subdiario solo trae:

- `114010-00x` IVA crédito fiscal (concepto **63**)
- `211010-004` FC a recibir

El motor imputaba **todo el cheque** al concepto 63 (ej. compra de monitor ALDAX Rebisco julio OPP A-3-68026).

El gasto real está en la **COM vía PEP** (ej. `521180-011` concepto 24). Además, a veces el COM ERP guarda el neto en **moneda extranjera** y hay que escalarlo al Debe `211010-004` de la FIB.

## Qué hace el fix

1. En `cargarGastoDesdeAplicacion` (bloque FIB/FIA/…): si la adelantada no tiene gasto 521/115, preferir COM vía PEP con resultado.
2. En `facturaTieneGastoPropioEnSubdiario` (mismo bloque): solo IVA/puente **no** bloquea el hop PEP←COM.
3. Método `escalarComResultadoANetoFacturaAdelantada`: escala importes COM cuando hay desfase típico de moneda.

## Casos de prueba

| Empresa | Mes | OPP | Esperado |
|---|---|---|---|
| Rebisco (3) | 07/2026 | A-3-68026 ALDAX | `521180-011` conc. 24 (no 63 en todo el cheque) |
| Rebisco (3) | 07/2026 | A-3-68010 CIA INTER | `521180-011` conc. 24 |
| Kandiko (2) | 07/2026 | — | Reglas FIS/114040/117010 de ayer intactas; conciliación 100% |

```bash
# Spot check rápido (solo lectura)
cd /var/www/html/anitaERP && php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
App\Support\Contable\MayorConcepto\MayorConceptoRuntimeSupport::elevarLimites();
$r=app(App\Services\Contable\MayorConceptoReporteService::class)->generarDesdeFiltros([
  "empresa_ids"=>[3],"modo_periodo"=>"mes","mes"=>7,"anio"=>2026,"moneda_id"=>1
]);
foreach ($r["secciones"]??[] as $sec) foreach ($sec["cuentas"]??[] as $cta) foreach ($cta["lineas"]??[] as $l) {
  if (!str_contains((string)($l["comprobante"]??""),"68026")) continue;
  echo ($l["concepto_id"]??"")." ".($l["cuenta_codigo"]??"")." ".($l["origen"]??"")."\n";
}
'
```

## Rollback (si rompe conciliaciones / otros meses)

Buscar en `MayorConceptoPeriodoProcesador.php` los bloques marcados `FIX-FIB-COM-VIA-PEP` y restaurar el comportamiento previo:

### 1) `cargarGastoDesdeAplicacion` — bloque FIB

Reemplazar el bloque marcado por:

```php
        if (in_array($tipoAp, ['FIB', 'FIC', 'FID', 'FIE', 'FIF', 'FIG', 'FIH', 'FIA'], true)) {
            $comGasto = $this->filtrarComGasto($this->cargarComDesdeFactura($aplicacion));
            if ($comGasto !== []) {
                return $comGasto;
            }

            $sub = $this->cargarSubdiarioComprobanteAplicacion($aplicacion);
            $adelantada = $this->filtrarLineasFacturaAdelantadaMayorConcepto($sub);
            if ($adelantada !== []) {
                return $adelantada;
            }

            return $this->filtrarComGasto($this->cargarComDesdeFacturaViaPepHermano($aplicacion));
        }
```

### 2) `facturaTieneGastoPropioEnSubdiario` — bloque FIB

```php
        if (in_array($tipoAp, ['FIB', 'FIC', 'FID', 'FIE', 'FIF', 'FIG', 'FIH', 'FIA'], true)) {
            return $this->filtrarLineasFacturaAdelantadaMayorConcepto($sub) !== [];
        }
```

### 3) Borrar método

Eliminar por completo `escalarComResultadoANetoFacturaAdelantada`.

**No tocar** `filtrarLineasResultadoDesdeCom` ni el hop FIS (reglas 114020/114040/117010).

Tras rollback: `php -l app/Support/Contable/MayorConcepto/MayorConceptoPeriodoProcesador.php` y regenerar mayor Rebisco/Kandiko julio para confirmar.
