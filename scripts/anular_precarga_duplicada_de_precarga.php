<?php

/**
 * Anula una precarga que duplica a OTRA PRECARGA del mismo legajo (no a un comprobante ya
 * contabilizado; para ese caso está fix_precargas_duplicadas_anita_import.php).
 *
 * Caso que originó el script: el mismo documento de Anita se materializó dos veces en el modal
 * del legajo, una como cáscara vacía y otra con el dato real. La cáscara se anula.
 *
 * No escribe nada si alguna precondición falla: la idea es que sea seguro correrlo y leer la salida.
 *
 * Uso: php artisan tinker --execute="require 'scripts/anular_precarga_duplicada_de_precarga.php';"
 *      APLICAR=1 para escribir; sin la variable corre en seco.
 */

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Historia;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Compras\Precarga_Comprobante_Proveedor_Recepcion;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\ComprobanteProveedorUnicidadSupport;
use App\Support\Compras\PrecargaComprobanteEstados;
use App\Support\Compras\PrecargaFacturaScanPathResolver;
use Illuminate\Support\Facades\DB;

$aplicar = (string) (getenv('APLICAR') ?: '') === '1';
$usuarioId = 1; // ordencompra_historia.creousuario_id es NOT NULL y esto corre sin sesión.

// audit.console viene en false, así que por consola no se graba nada en audits.
config(['audit.console' => true]);

/** @var list<array{anular: int, conservar: int, motivo: string}> */
$pares = [
    [
        'anular' => 555,
        'conservar' => 559,
        'motivo' => 'Duplicada: el mismo scan de Anita (interno 428722) se materializó dos veces en el'
            .' modal del legajo. Esta quedó como cáscara vacía (total 0, sin CAE, sin CUIT).',
    ],
];

echo ($aplicar ? '>>> APLICANDO' : '>>> EN SECO (no escribe)').PHP_EOL.PHP_EOL;

$resolver = app(PrecargaFacturaScanPathResolver::class);

foreach ($pares as $par) {
    $idAnular = (int) $par['anular'];
    $idConservar = (int) $par['conservar'];

    $anular = Precarga_Comprobante_Proveedor::query()->find($idAnular);
    $conservar = Precarga_Comprobante_Proveedor::query()->find($idConservar);
    echo 'pre#'.$idAnular.' (a anular) vs pre#'.$idConservar.' (a conservar)'.PHP_EOL;
    if ($anular === null || $conservar === null) {
        echo '   ABORTA: alguna de las dos no existe'.PHP_EOL.PHP_EOL;

        continue;
    }

    $etiqueta = $anular->letra.'-'.$anular->sucursal.'-'.$anular->numerocomprobante;
    echo '   '.$etiqueta.' de proveedor '.$anular->proveedor_id.' (OC '.$anular->numeroordencompra.')'.PHP_EOL;

    // 1) Misma clave fiscal y mismo legajo: si no, no son el mismo documento.
    $mismaClave = (int) $anular->empresa_id === (int) $conservar->empresa_id
        && (int) $anular->proveedor_id === (int) $conservar->proveedor_id
        && strtoupper(trim((string) $anular->letra)) === strtoupper(trim((string) $conservar->letra))
        && (int) $anular->sucursal === (int) $conservar->sucursal
        && (int) $anular->numerocomprobante === (int) $conservar->numerocomprobante
        && (string) $anular->numeroordencompra === (string) $conservar->numeroordencompra;
    if (! $mismaClave) {
        echo '   ABORTA: no comparten empresa/proveedor/letra/sucursal/número/OC'.PHP_EOL.PHP_EOL;

        continue;
    }

    // 2) Mismo documento de Anita: es la prueba de que se materializó dos veces y no son dos
    //    comprobantes fiscales distintos que casualmente comparten número.
    $internoAnular = $anular->anita_nro_interno !== null ? (int) $anular->anita_nro_interno : 0;
    $internoConservar = $conservar->anita_nro_interno !== null ? (int) $conservar->anita_nro_interno : 0;
    if ($internoAnular <= 0 || $internoAnular !== $internoConservar) {
        echo '   ABORTA: anita_nro_interno distinto o vacío ('.$internoAnular.' vs '.$internoConservar
            .'). Podrían ser dos documentos fiscales distintos; requiere revisión humana'.PHP_EOL.PHP_EOL;

        continue;
    }
    echo '   mismo scan de Anita (interno '.$internoAnular.')'.PHP_EOL;

    // 3) La que se anula no puede haber generado comprobante ni estar ya anulada.
    $estado = strtoupper(trim((string) $anular->estado));
    if ($estado === PrecargaComprobanteEstados::ANULADA) {
        echo '   ya estaba ANULADA, se omite'.PHP_EOL.PHP_EOL;

        continue;
    }
    if ($estado === PrecargaComprobanteEstados::GENERADA || $anular->comprobante_proveedor !== null) {
        echo '   ABORTA: generó su propio comprobante, no es una precarga fantasma'.PHP_EOL.PHP_EOL;

        continue;
    }

    // 4) La que se conserva tiene que seguir viva y ser la que gana el match del scan
    //    (precargaDelLegajoCompatibleConScan ordena por id desc y excluye ANULADA).
    $estadoConservar = strtoupper(trim((string) $conservar->estado));
    if ($estadoConservar === PrecargaComprobanteEstados::ANULADA) {
        echo '   ABORTA: la que hay que conservar está ANULADA'.PHP_EOL.PHP_EOL;

        continue;
    }
    if ($idConservar < $idAnular) {
        echo '   ABORTA: la que se conserva tiene id menor, así que el modal seguiría eligiendo a la'
            .' anulada y volvería a materializar el scan'.PHP_EOL.PHP_EOL;

        continue;
    }

    // 5) La que se anula no puede llevarse reservas ni carga de artículos puestas a mano.
    $comsAnular = Precarga_Comprobante_Proveedor_Recepcion::query()
        ->where('precarga_comprobante_proveedor_id', $idAnular)
        ->pluck('recepcion_proveedor_id')
        ->all();
    $articulosAnular = DB::table('precarga_comprobante_proveedor_articulo')
        ->where('precarga_comprobante_proveedor_id', $idAnular)
        ->count();
    if ($comsAnular !== [] || $articulosAnular > 0) {
        echo '   ABORTA: tiene '.count($comsAnular).' COM vinculada(s) y '.$articulosAnular
            .' artículo(s). Habría que moverlos a pre#'.$idConservar.' antes de anular'.PHP_EOL.PHP_EOL;

        continue;
    }
    echo '   sin COM vinculadas ni artículos: no se pierde nada al anularla'.PHP_EOL;

    // 6) La que se conserva tiene que ser la más rica en datos.
    $totalAnular = (float) $anular->total;
    $totalConservar = (float) $conservar->total;
    if ($totalConservar <= 0.0 || $totalAnular > 0.0) {
        echo '   ABORTA: se esperaba que la anulada tenga total 0 y la conservada un total real'
            .' (anular '.$totalAnular.' / conservar '.$totalConservar.')'.PHP_EOL.PHP_EOL;

        continue;
    }
    echo '   pre#'.$idConservar.' tiene el dato real: total '
        .number_format($totalConservar, 2, ',', '.')
        .', CAE '.($conservar->numerocae ?: 'sin CAE')
        .', CUIT '.($conservar->identificacion_proveedor_cuit ?: 'sin CUIT').PHP_EOL;

    // 7) El PDF de la que se conserva tiene que resolver: el operador no puede quedarse sin scan.
    $rutaConservar = trim((string) ($conservar->rutaalmacenamiento ?? ''));
    if ($rutaConservar === '' || ! $resolver->resolve($rutaConservar)) {
        echo '   ABORTA: el PDF de pre#'.$idConservar.' no resuelve ('.($rutaConservar ?: 'sin ruta')
            .'); el operador se quedaría sin scan visible'.PHP_EOL.PHP_EOL;

        continue;
    }
    echo '   el PDF de pre#'.$idConservar.' resuelve OK'.PHP_EOL;

    // 8) No debe existir ya el comprobante en el ERP: si existiera, el caso es el del otro script.
    $cuit = ComprobanteProveedorUnicidadSupport::resolverCuitDigitos(
        $conservar->proveedor_id !== null ? (int) $conservar->proveedor_id : null,
        $conservar->identificacion_proveedor_cuit,
    );
    $codigoAfip = ComprobanteProveedorUnicidadSupport::codigoAfipDesdeTipoId(
        (int) $conservar->tipotransaccion_compra_id
    );
    $enErp = ComprobanteProveedorUnicidadSupport::findDuplicadoPorAfip(
        (int) $conservar->empresa_id,
        $codigoAfip,
        (string) $conservar->letra,
        (int) $conservar->sucursal,
        (int) $conservar->numerocomprobante,
        $cuit,
    );
    if ($enErp !== null) {
        echo '   ABORTA: ya existe cp#'.$enErp->id.' ('.$enErp->estado.') con esa clave fiscal;'
            .' este caso va por fix_precargas_duplicadas_anita_import.php'.PHP_EOL.PHP_EOL;

        continue;
    }
    echo '   no hay comprobante en el ERP con esa clave fiscal (AFIP '.$codigoAfip.')'.PHP_EOL;

    if (! $aplicar) {
        echo '   [seco] anularía pre#'.$idAnular.' y dejaría pre#'.$idConservar.PHP_EOL.PHP_EOL;

        continue;
    }

    $oc = Ordencompra::query()
        ->where('empresa_id', $anular->empresa_id)
        ->where('numeroordencompra', $anular->numeroordencompra)
        ->first();

    DB::transaction(function () use ($anular, $idAnular, $idConservar, $oc, $par, $usuarioId, $etiqueta): void {
        $anular->estado = PrecargaComprobanteEstados::ANULADA;
        $anular->save();

        if ($oc !== null) {
            Ordencompra_Historia::query()->create([
                'ordencompra_id' => (int) $oc->id,
                'sector_legajocompra_id' => $oc->sector_legajocompra_id ? (int) $oc->sector_legajocompra_id : null,
                'fecha' => now(),
                'observacion' => 'Anulación de factura duplicada del legajo',
                'leyenda' => 'Factura #'.$idAnular.' ('.$etiqueta.') anulada. '.$par['motivo']
                    .' Queda vigente la factura #'.$idConservar.', que tiene el importe, el CAE y el CUIT.',
                'creousuario_id' => $usuarioId,
            ]);
        }
    });

    echo '   OK: pre#'.$idAnular.' ANULADA, queda pre#'.$idConservar
        .($oc !== null ? ', registrado en la historia de la OC' : ', SIN historia (no se ubicó la OC)').PHP_EOL.PHP_EOL;
}
