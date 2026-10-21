# Anulación de caja: la línea queda del mismo lado que el original

**Fecha:** 2026-10-21  
**Visto en:** Calzados Ferli (`anitaERP_l12`), OPP 46146 / anulación 46148.  
**Para correr en:** AGG, antes de corregir nada.

## Qué se ve

Al anular una orden de pago, un ingreso o un egreso, el asiento queda bien (debe y haber invertidos). En el listado de la cuenta de caja la anulación sale en la misma columna que el movimiento original: un egreso anulado sigue como egreso, un ingreso anulado sigue como ingreso. El saldo no vuelve atrás.

Anita no se desalinea por este caso. El importe que se manda a tesorería sale del valor absoluto por el factor de anulación, no del signo guardado en la línea.

## Por qué

La anulación calcula el importe opuesto. Al grabar la línea, `Caja_Movimiento_CuentacajaRepository` vuelve a firmar según el tipo: egreso siempre negativo, ingreso siempre positivo (`IngresoEgresoCajaMontoSignoSupport::aBaseDatos`). Ese segundo paso deshace la inversión.

El asiento no pasa por ahí. Lo arma `AsientoReversoSupport` invirtiendo debe y haber del asiento original.

## Código

A partir de este cambio, la anulación manda `montos_firmados = true` y el importe ya opuesto (`IngresoEgresoCajaMontoSignoSupport::montoAnulacion`). El repositorio no lo vuelve a firmar.

Archivos:

- `app/Support/Caja/IngresoEgresoCajaMontoSignoSupport.php`
- `app/Repositories/Caja/Caja_Movimiento_CuentacajaRepository.php`
- `app/Services/Compras/PagoproveedorAnularRevertirService.php`
- `app/Services/Caja/IngresoEgresoAnularRevertirService.php`

En AGG, si `rg montos_firmados app/Services/Compras/PagoproveedorAnularRevertirService.php` no devuelve nada, el código viejo sigue grabando mal las anulaciones nuevas. Corregir datos sin este código no evita que la próxima anulación repita el fallo.

## Chequeo en AGG (solo lectura)

Confirmar instalación y base. Tiene que decir AGG, no Ferli.

```bash
cd /var/www/html/anitaERP && php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo "empresa=".config("app.empresa")." db=".config("database.connections.".config("database.default").".database").PHP_EOL;
'
```

Líneas de anulación con el mismo signo que el original, misma cuenta de caja:

```bash
cd /var/www/html/anitaERP && php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$rows = DB::select("
SELECT orig.numerotransaccion nro_orig, rev.numerotransaccion nro_rev, rev.fecha, rev.detalle,
       cc.codigo, cc.nombre, lo.monto monto_orig, lr.monto monto_rev, lr.id linea_rev
FROM caja_movimiento orig
JOIN caja_movimiento rev ON rev.id = orig.caja_movimiento_revertido_por_id
JOIN caja_movimiento_cuentacaja lo ON lo.caja_movimiento_id = orig.id
JOIN caja_movimiento_cuentacaja lr
  ON lr.caja_movimiento_id = rev.id AND lr.cuentacaja_id = lo.cuentacaja_id
JOIN cuentacaja cc ON cc.id = lo.cuentacaja_id
WHERE (lo.monto > 0 AND lr.monto > 0) OR (lo.monto < 0 AND lr.monto < 0)
ORDER BY rev.fecha, rev.numerotransaccion
");
echo "n=".count($rows).PHP_EOL;
foreach ($rows as $r) echo json_encode($r, JSON_UNESCAPED_UNICODE).PHP_EOL;
'
```

Cero filas: en esa base no hay anulaciones de caja del mismo lado. No hay nada que corregir.

Si hay filas, listarlas y frenar. No actualizar todavía. No tocar `asiento` ni `asiento_movimiento`. No reenviar nada a Anita.

Control aparte, también en solo lectura: asientos de anulación con el mismo signo en la misma cuenta. En Ferli dio cero. Si en AGG da filas, es otro problema y no se arregla negando la línea de caja.

```bash
cd /var/www/html/anitaERP && php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$rows = DB::select("
SELECT ao.numeroasiento nro_orig, ar.numeroasiento nro_rev, c.codigo,
       mo.monto monto_orig, mr.monto monto_rev, ar.observacion
FROM asiento ar
JOIN asiento ao ON ao.pagoproveedor_id = (
    SELECT p.pagoproveedor_origen_id FROM pagoproveedor p WHERE p.id = ar.pagoproveedor_id
)
JOIN asiento_movimiento mr ON mr.asiento_id = ar.id
JOIN asiento_movimiento mo ON mo.asiento_id = ao.id AND mo.cuentacontable_id = mr.cuentacontable_id
JOIN cuentacontable c ON c.id = mr.cuentacontable_id
WHERE ar.observacion LIKE \"ANULA %\"
  AND ((mo.monto > 0 AND mr.monto > 0) OR (mo.monto < 0 AND mr.monto < 0))
");
echo "asientos_mismo_signo=".count($rows).PHP_EOL;
foreach ($rows as $r) echo json_encode($r, JSON_UNESCAPED_UNICODE).PHP_EOL;
'
```

## Si hay que corregir datos

Solo la línea de la anulación (`caja_movimiento_cuentacaja.monto` de `linea_rev`): pasar al signo opuesto del original. Una línea por `linea_rev`, las que devolvió la consulta. Una corrida por consola no queda en `audits` (`audit.console` está en false): anotar antes el id, el monto viejo y el nuevo.

Después de grabar, la misma consulta tiene que devolver `n=0`.

## Ferli, ya revisado el 2026-10-21

Había 9 líneas. Quedaron del lado opuesto. La consulta de mismo signo devuelve 0. Los asientos de esas anulaciones ya estaban bien y no se modificaron.

| Original | Anulación | Cuenta | Línea anulación ahora |
|---|---|---|---|
| OPP 45897 | 45923 | Fondo fijo MercadoPago | +27.867,69 |
| OPP 45965 | 45968 | Fondo fijo MercadoPago | +206.500,00 |
| EGR 77758 | 77774 | Fondo fijo Caballito | +320.000,00 |
| ING 77775 | 77776 | Francés C/C | −9.000.000,00 |
| OPP 46046 | 46050 | Pago a proveedores | +2.022.541,15 |
| OPP 46006 | 46062 | Fondo fijo MercadoPago | +23.006,80 |
| OPP 46146 | 46148 | Fondo fijo MercadoPago | +800.000,00 |
| OPP 46153 | 46154 | MercadoPago | +130.680,00 |
| OPP 46129 | 46157 | Fondo fijo | +990,00 |
