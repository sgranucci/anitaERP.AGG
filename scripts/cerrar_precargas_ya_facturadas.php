<?php

/**
 * Repaso de una sola vez: cierra las precargas vivas cuya factura YA está cargada en el ERP.
 *
 * El import de Anita es la puerta por la que entró el 98,7% de los comprobantes y hasta ahora no
 * pasaba por el legajo, así que quedaron precargas esperando una factura que ya estaba adentro: el
 * sector las veía como pendientes y algunas seguían reteniendo la COM. Desde ahora el import las
 * cierra solo; esto es para las que quedaron de arrastre.
 *
 * Uso:  php scripts/cerrar_precargas_ya_facturadas.php          (seco, no escribe nada)
 *       APLICAR=1 php scripts/cerrar_precargas_ya_facturadas.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Services\Compras\ComprobanteProveedorCierrePrecargaLegajoService;
use App\Support\Compras\ComprobanteProveedorUnicidadSupport as Unicidad;
use Illuminate\Support\Facades\DB;

$aplicar = (string) (getenv('APLICAR') ?: '') === '1';
$usuarioId = 1; // ordencompra_historia.creousuario_id es NOT NULL y esto corre sin sesión.

$servicio = app(ComprobanteProveedorCierrePrecargaLegajoService::class);

$vivas = Precarga_Comprobante_Proveedor::query()
    ->whereRaw("UPPER(TRIM(COALESCE(estado, ''))) IN ('PENDIENTE', 'CARGADA_ANITA')")
    ->orderBy('id')
    ->get();

echo ($aplicar ? 'APLICANDO' : 'SECO (no escribe nada)').': '.$vivas->count().' precargas vivas a revisar'.PHP_EOL.PHP_EOL;

$cerradas = 0;
$comsMovidas = 0;
$sinFactura = 0;
$fallos = 0;

foreach ($vivas as $precarga) {
    $cuit = Unicidad::resolverCuitDigitos((int) $precarga->proveedor_id, $precarga->identificacion_proveedor_cuit);
    $codigoAfip = Unicidad::codigoAfipDesdeTipoId((int) $precarga->tipotransaccion_compra_id);
    if ($cuit === '' || $codigoAfip === '') {
        $sinFactura++;
        continue;
    }

    $comprobante = Unicidad::findDuplicadoPorAfip(
        (int) $precarga->empresa_id,
        $codigoAfip,
        (string) $precarga->letra,
        (int) $precarga->sucursal,
        (int) $precarga->numerocomprobante,
        $cuit,
    );
    if ($comprobante === null) {
        $sinFactura++;
        continue;
    }

    $etiqueta = 'pre#'.$precarga->id.' OC '.$precarga->numeroordencompra.' '
        .$precarga->letra.'-'.$precarga->sucursal.'-'.$precarga->numerocomprobante
        .' -> cp#'.$comprobante->id;

    $coms = DB::table('precarga_comprobante_proveedor_recepcion')
        ->where('precarga_comprobante_proveedor_id', (int) $precarga->id)
        ->pluck('recepcion_proveedor_id')
        ->all();

    if (! $aplicar) {
        echo '  '.$etiqueta.($coms !== [] ? '  COM a traspasar: '.implode(',', $coms) : '').PHP_EOL;
        $cerradas++;
        $comsMovidas += count($coms);
        continue;
    }

    try {
        $resultado = $servicio->cerrar($comprobante, $usuarioId);
        if ($resultado === null) {
            echo '  '.$etiqueta.': sin cambios (ya estaba cerrada)'.PHP_EOL;
            continue;
        }
        $cerradas++;
        $comsMovidas += count($resultado['coms']);
        echo '  '.$etiqueta.': GENERADA'
            .($resultado['coms'] !== [] ? ', COM traspasadas: '.implode(',', $resultado['coms']) : '').PHP_EOL;
    } catch (\Throwable $e) {
        $fallos++;
        echo '  '.$etiqueta.': FALLO -> '.$e->getMessage().PHP_EOL;
    }
}

echo PHP_EOL.'precargas cerradas: '.$cerradas.PHP_EOL;
echo 'COM traspasadas al comprobante: '.$comsMovidas.PHP_EOL;
echo 'precargas sin factura en el ERP (se dejan como están): '.$sinFactura.PHP_EOL;
echo 'fallos: '.$fallos.PHP_EOL;
if (! $aplicar) {
    echo PHP_EOL.'No se escribió nada. Para aplicar: APLICAR=1 php scripts/cerrar_precargas_ya_facturadas.php'.PHP_EOL;
}
