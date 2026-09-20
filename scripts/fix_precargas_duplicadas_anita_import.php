<?php

/**
 * Anula las precargas que duplican una factura ya contabilizada (y pagada) por el import de Anita,
 * y libera las COM que esas precargas tenian reservadas.
 *
 * Uso: php artisan tinker --execute="require 'scripts/fix_precargas_duplicadas_anita_import.php';"
 *      APLICAR=1 para escribir; sin la variable corre en seco.
 */

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Historia;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Compras\Precarga_Comprobante_Proveedor_Recepcion;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\ComprobanteProveedorUnicidadSupport;
use App\Support\Compras\PrecargaComprobanteEstados;
use Illuminate\Support\Facades\DB;

$aplicar = (string) (getenv('APLICAR') ?: '') === '1';
$usuarioId = 1; // ordencompra_historia.creousuario_id es NOT NULL y esto corre sin sesion.
$motivo = 'Duplicada: la factura ya estaba contabilizada y pagada en el ERP por el import de Anita.';
// Se pueden pasar por PRECARGAS=394,395,... ; si no, la tanda original.
$precargaIds = array_values(array_filter(array_map(
    static fn (string $id): int => (int) trim($id),
    explode(',', (string) (getenv('PRECARGAS') ?: '636,673,774,777,663'))
)));

echo ($aplicar ? '>>> APLICANDO' : '>>> EN SECO (no escribe)').PHP_EOL.PHP_EOL;

foreach ($precargaIds as $precargaId) {
    $precarga = Precarga_Comprobante_Proveedor::query()->find($precargaId);
    if ($precarga === null) {
        echo 'pre#'.$precargaId.': NO EXISTE, se omite'.PHP_EOL;

        continue;
    }

    $etiqueta = $precarga->letra.'-'.$precarga->sucursal.'-'.$precarga->numerocomprobante;
    echo 'pre#'.$precargaId.' '.$etiqueta.' (OC '.$precarga->numeroordencompra.')'.PHP_EOL;

    // Precondicion 1: no debe estar ya anulada ni haber generado su propio comprobante.
    $estado = strtoupper(trim((string) $precarga->estado));
    if ($estado === PrecargaComprobanteEstados::ANULADA) {
        echo '   ya estaba ANULADA, se omite'.PHP_EOL;

        continue;
    }
    if ($estado === PrecargaComprobanteEstados::GENERADA || $precarga->comprobante_proveedor !== null) {
        echo '   ABORTA: genero su propio comprobante, no es una precarga fantasma'.PHP_EOL;

        continue;
    }

    // Precondicion 2: tiene que existir el comprobante contabilizado con la misma clave fiscal.
    $cuit = ComprobanteProveedorUnicidadSupport::resolverCuitDigitos(
        $precarga->proveedor_id !== null ? (int) $precarga->proveedor_id : null,
        $precarga->identificacion_proveedor_cuit,
    );
    $codigoAfip = ComprobanteProveedorUnicidadSupport::codigoAfipDesdeTipoId(
        (int) $precarga->tipotransaccion_compra_id
    );
    $duplicado = ComprobanteProveedorUnicidadSupport::findDuplicadoPorAfip(
        (int) $precarga->empresa_id,
        $codigoAfip,
        (string) $precarga->letra,
        (int) $precarga->sucursal,
        (int) $precarga->numerocomprobante,
        $cuit,
    );
    if ($duplicado === null) {
        echo '   ABORTA: no se encuentra el comprobante duplicado, no hay motivo para anular'.PHP_EOL;

        continue;
    }
    if (strtoupper(trim((string) $duplicado->estado)) !== ComprobanteProveedorEstados::CONTABILIZADO) {
        echo '   ABORTA: el comprobante #'.$duplicado->id.' no esta CONTABILIZADO (esta '.$duplicado->estado.')'.PHP_EOL;

        continue;
    }
    echo '   duplica cp#'.$duplicado->id.' ('.$duplicado->estado.', total '
        .number_format((float) $duplicado->total, 2, ',', '.').')'.PHP_EOL;

    $vinculos = Precarga_Comprobante_Proveedor_Recepcion::query()
        ->where('precarga_comprobante_proveedor_id', $precargaId)
        ->get();
    $comsLiberadas = [];
    foreach ($vinculos as $vinculo) {
        $comId = (int) $vinculo->recepcion_proveedor_id;
        $nro = DB::table('recepcion_proveedor')->where('id', $comId)->value('numerorecepcion');
        $comsLiberadas[] = 'COM '.($nro ?: '#'.$comId).' (id '.$comId.')';
    }
    echo '   COM a liberar: '.($comsLiberadas === [] ? 'ninguna' : implode(', ', $comsLiberadas)).PHP_EOL;

    if (! $aplicar) {
        echo '   [seco] anularia la precarga y liberaria esos vinculos'.PHP_EOL.PHP_EOL;

        continue;
    }

    $oc = Ordencompra::query()
        ->where('empresa_id', $precarga->empresa_id)
        ->where('numeroordencompra', $precarga->numeroordencompra)
        ->first();

    DB::transaction(function () use ($precarga, $precargaId, $duplicado, $comsLiberadas, $oc, $motivo, $usuarioId, $etiqueta): void {
        Precarga_Comprobante_Proveedor_Recepcion::query()
            ->where('precarga_comprobante_proveedor_id', $precargaId)
            ->get()
            ->each(static fn ($vinculo) => $vinculo->delete());

        $precarga->estado = PrecargaComprobanteEstados::ANULADA;
        $precarga->save();

        if ($oc !== null) {
            $leyenda = 'Factura #'.$precargaId.' ('.$etiqueta.') anulada. '.$motivo
                .' Ya estaba cargada como comprobante #'.$duplicado->id.'.'
                .($comsLiberadas !== [] ? ' Se liberaron: '.implode(', ', $comsLiberadas).'.' : '');
            Ordencompra_Historia::query()->create([
                'ordencompra_id' => (int) $oc->id,
                'sector_legajocompra_id' => $oc->sector_legajocompra_id ? (int) $oc->sector_legajocompra_id : null,
                'fecha' => now(),
                'observacion' => 'Anulación de factura duplicada del legajo',
                'leyenda' => $leyenda,
                'creousuario_id' => $usuarioId,
            ]);
        }
    });

    echo '   OK: precarga ANULADA'.($comsLiberadas !== [] ? ' y COM liberada' : '')
        .($oc !== null ? ', registrado en la historia de la OC' : ', SIN historia (no se ubico la OC)').PHP_EOL.PHP_EOL;
}
