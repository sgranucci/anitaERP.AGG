<?php

/**
 * Lista en el sistema Anita de Villafranca (/usr2/villafranca) los comprobantes que la
 * alineación ARCA modificó en Bierzo. La alineación no informa path_sistema, así que solo
 * escribió en Bierzo: este script muestra cómo quedó cada comprobante del otro lado.
 *
 * Uso: php scripts/probe_villafranca_facturas_alineadas.php [dia=20260805] [path=/usr2/villafranca]
 */

declare(strict_types=1);

use App\ApiAnita;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

ini_set('memory_limit', '-1');
set_time_limit(0);

$dia = (string) ($argv[1] ?? '20260805');
$path = (string) ($argv[2] ?? '/usr2/villafranca');
$api = new ApiAnita();
$fmt = static fn (float $n): string => number_format($n, 2, ',', '.');

/**
 * @return list<object>
 */
function traer(ApiAnita $api, string $tabla, string $campos, string $where, ?string $path): array
{
    $p = ['acc' => 'list', 'sistema' => 'ventas', 'tabla' => $tabla, 'campos' => $campos, 'whereArmado' => $where];
    if ($path !== null) {
        $p['path_sistema'] = $path;
    }
    $raw = (string) $api->apiCall($p);
    $err = ApiAnita::extraerMensajeError($raw === '' ? null : $raw);
    if ($err !== null) {
        throw new RuntimeException("Error leyendo {$tabla}: {$err}");
    }

    return ApiAnita::decodificarListaFilas($raw);
}

// --- Comprobantes alineados en el día pedido (según los backups del servicio) ---
$dir = storage_path('app/reportes/alineacion_anita_arca');
$claves = [];
foreach (glob($dir.'/backup_*.json') ?: [] as $archivo) {
    if (! preg_match('/backup_([A-Z]+)_([A-Z])_(\d+)_(\d+)_(\d{8})_(\d{6})\.json$/', basename($archivo), $m)) {
        continue;
    }
    if ($m[5] !== $dia) {
        continue;
    }
    $d = json_decode((string) file_get_contents($archivo), true);
    $clave = $m[1].'|'.$m[2].'|'.(int) $m[3].'|'.(int) $m[4];
    $claves[$clave] ??= ['tipo' => $m[1], 'letra' => $m[2], 'sucursal' => (int) $m[3], 'nro' => (int) $m[4], 'aplicado' => false];
    if (! empty($d['aplicar'])) {
        $claves[$clave]['aplicado'] = true;
    }
}

if ($claves === []) {
    echo "No hay comprobantes alineados el {$dia}.\n";
    exit(0);
}

// Todos los alineados comparten tipo/letra/sucursal; se agrupan para consultar por lotes.
$grupos = [];
foreach ($claves as $c) {
    $grupos[$c['tipo'].'|'.$c['letra'].'|'.$c['sucursal']][] = $c['nro'];
}

echo "Comprobantes alineados en Bierzo el {$dia}: ".count($claves)."\n";
echo "Sistema consultado: {$path}/ventas\n\n";

$filas = [];

foreach ($grupos as $g => $nros) {
    [$tipo, $letra, $sucursal] = explode('|', $g);
    sort($nros);
    $in = "'".implode("','", $nros)."'";

    $baseVenta = " WHERE ven_tipo='{$tipo}' AND ven_letra='{$letra}' AND ven_sucursal='{$sucursal}' AND ven_nro IN ({$in}) ";
    $baseCli = " WHERE cliv_tipo='{$tipo}' AND cliv_letra='{$letra}' AND cliv_sucursal='{$sucursal}' AND cliv_nro IN ({$in}) ";
    $baseSub = " WHERE subd_tipo='{$tipo}' AND subd_letra='{$letra}' AND subd_sucursal='{$sucursal}' AND subd_nro IN ({$in}) ";

    foreach ([['VILLAFRANCA', $path], ['BIERZO', null]] as [$etiqueta, $p]) {
        $ventas = traer($api, 'venta',
            'ven_nro,ven_cliente,ven_fecha,ven_gravado,ven_impuesto1,ven_percepcion_iva,ven_monto',
            $baseVenta, $p);
        $climov = traer($api, 'climov',
            'cliv_nro,cliv_cliente,cliv_monto,cliv_t_cobrado,cliv_estado',
            $baseCli, $p);
        // subd_desc_mov último: el bridge parte el CSV por `|`.
        $sub = traer($api, 'subdiario',
            'subd_nro,subd_cuenta,subd_contrapartida,subd_importe,subd_tipo_mov,subd_fecha,subd_emisor,subd_desc_mov',
            $baseSub, $p);

        foreach ($ventas as $v) {
            $filas[(int) $v->ven_nro][$etiqueta]['venta'] = [
                'cliente' => trim((string) $v->ven_cliente),
                'fecha' => trim((string) $v->ven_fecha),
                'monto' => (float) $v->ven_monto,
                'gravado' => (float) $v->ven_gravado,
                'perc_iva' => (float) $v->ven_percepcion_iva,
            ];
        }
        foreach ($climov as $c) {
            $n = (int) $c->cliv_nro;
            $filas[$n][$etiqueta]['climov'] = ($filas[$n][$etiqueta]['climov'] ?? 0) + (float) $c->cliv_monto;
        }
        foreach ($sub as $s) {
            $n = (int) $s->subd_nro;
            $filas[$n][$etiqueta]['sub'] = ($filas[$n][$etiqueta]['sub'] ?? 0) + (float) $s->subd_importe;
            $filas[$n][$etiqueta]['sub_lineas'] = ($filas[$n][$etiqueta]['sub_lineas'] ?? 0) + 1;
            $em = trim((string) ($s->subd_emisor ?? ''));
            if ($em === '' || ltrim($em, '0') === '') {
                $filas[$n][$etiqueta]['sin_emisor'][] = trim((string) $s->subd_cuenta).'='.$s->subd_importe;
            }
        }
    }
}

ksort($filas);

$soloBierzo = [];
$descuadres = [];
$sinEmisor = [];
$totVf = 0.0;
$totBz = 0.0;

printf(
    "%-6s %-8s %-9s | %14s %14s %14s | %14s %14s %14s | %s\n",
    'nro', 'cliente', 'fecha', 'VF venta', 'VF climov', 'VF subd', 'BZ venta', 'BZ climov', 'BZ subd', 'obs'
);
echo str_repeat('-', 165), "\n";

foreach ($filas as $nro => $d) {
    $vf = $d['VILLAFRANCA'] ?? [];
    $bz = $d['BIERZO'] ?? [];

    $vfVenta = $vf['venta']['monto'] ?? null;
    $bzVenta = $bz['venta']['monto'] ?? null;
    $vfSub = $vf['sub'] ?? null;
    $bzSub = $bz['sub'] ?? null;
    $vfCli = $vf['climov'] ?? null;
    $bzCli = $bz['climov'] ?? null;

    $obs = [];
    if ($vfVenta === null) {
        $obs[] = 'no existe en VF';
        $soloBierzo[] = $nro;
    } else {
        $totVf += (float) $vfVenta;
        if ($vfSub !== null && abs(round((float) $vfVenta - (float) $vfSub, 2)) > 0.011) {
            $obs[] = 'VF subd≠venta ('.$fmt((float) $vfVenta - (float) $vfSub).')';
            $descuadres[] = $nro;
        }
        if (($vf['sin_emisor'] ?? []) !== []) {
            $obs[] = 'VF sin emisor: '.implode(' ', $vf['sin_emisor']);
            $sinEmisor[] = $nro;
        }
    }
    if ($bzVenta !== null) {
        $totBz += (float) $bzVenta;
    }

    printf(
        "%-6s %-8s %-9s | %14s %14s %14s | %14s %14s %14s | %s\n",
        $nro,
        $vf['venta']['cliente'] ?? ($bz['venta']['cliente'] ?? '-'),
        $vf['venta']['fecha'] ?? ($bz['venta']['fecha'] ?? '-'),
        $vfVenta === null ? '-' : $fmt((float) $vfVenta),
        $vfCli === null ? '-' : $fmt((float) $vfCli),
        $vfSub === null ? '-' : $fmt((float) $vfSub),
        $bzVenta === null ? '-' : $fmt((float) $bzVenta),
        $bzCli === null ? '-' : $fmt((float) $bzCli),
        $bzSub === null ? '-' : $fmt((float) $bzSub),
        implode('; ', $obs),
    );
}

echo "\n=== RESUMEN ===\n";
echo 'comprobantes de la lista: '.count($filas)."\n";
echo 'presentes en Villafranca: '.(count($filas) - count($soloBierzo))."\n";
echo 'NO existen en Villafranca: '.count($soloBierzo).($soloBierzo === [] ? '' : ' -> '.implode(',', $soloBierzo))."\n";
echo 'VF con subdiario descuadrado: '.count($descuadres).($descuadres === [] ? '' : ' -> '.implode(',', $descuadres))."\n";
echo 'VF con lineas sin emisor: '.count($sinEmisor).($sinEmisor === [] ? '' : ' -> '.implode(',', $sinEmisor))."\n";
echo 'total ven_monto Villafranca: '.$fmt($totVf)."\n";
echo 'total ven_monto Bierzo     : '.$fmt($totBz)."\n";
