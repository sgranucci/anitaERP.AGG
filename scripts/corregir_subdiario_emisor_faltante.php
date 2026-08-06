<?php

/**
 * Completa el emisor (cliente) de las líneas de subdiario imputadas a la cuenta de deudores
 * que quedaron sin él. Una línea sin emisor entra al mayor pero no a la deuda de ningún
 * cliente, y descuadra el control del mayor contra la cuenta corriente.
 *
 * Origen del defecto: INSERT de AlinearFacturaAnitaArcaService que no informaba subd_emisor.
 *
 * DRY-RUN por defecto: muestra el UPDATE exacto y no escribe nada.
 * Para aplicar hay que pasar --aplicar.
 *
 * Uso:
 *   php scripts/corregir_subdiario_emisor_faltante.php
 *   php scripts/corregir_subdiario_emisor_faltante.php --desde=20260101 --hasta=20261231
 *   php scripts/corregir_subdiario_emisor_faltante.php --aplicar
 */

declare(strict_types=1);

use App\ApiAnita;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

ini_set('memory_limit', '-1');
set_time_limit(0);

// --- argumentos ---
$opciones = [];
$aplicar = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--aplicar') {
        $aplicar = true;

        continue;
    }
    if (preg_match('/^--([a-z_]+)=(.*)$/', $arg, $m)) {
        $opciones[$m[1]] = $m[2];
    }
}

$cuenta = (int) ($opciones['cuenta'] ?? (int) config('cliente.DEUDORES_POR_VENTAS'));
$desde = (int) ($opciones['desde'] ?? (int) date('Ymd', strtotime('-90 days')));
$hasta = (int) ($opciones['hasta'] ?? (int) date('Ymd'));
$sistemaSub = (string) config('anita.subdiario_sistema', 'ventas');

$api = new ApiAnita();
$fmt = static fn (float $n): string => number_format($n, 2, ',', '.');

echo "cuenta={$cuenta}  periodo={$desde}..{$hasta}  modo=".($aplicar ? 'APLICAR' : 'DRY-RUN')."\n\n";

/**
 * @return list<object>
 */
function listar(ApiAnita $api, string $sistema, string $tabla, string $campos, string $where): array
{
    $raw = (string) $api->apiCall([
        'acc' => 'list',
        'sistema' => $sistema,
        'tabla' => $tabla,
        'campos' => $campos,
        'whereArmado' => $where,
    ]);
    $err = ApiAnita::extraerMensajeError($raw === '' ? null : $raw);
    if ($err !== null) {
        throw new RuntimeException("Error leyendo {$tabla}: {$err}");
    }

    return ApiAnita::decodificarListaFilas($raw);
}

// `subd_desc_mov` va último: el bridge parte el CSV por `|` y una descripción con `|` corre los campos.
$campos = 'subd_fecha,subd_tipo,subd_letra,subd_sucursal,subd_nro,subd_tipo_mov,'
    .'subd_cuenta,subd_contrapartida,subd_importe,subd_sistema,subd_emisor,subd_desc_mov';

$lineas = listar($api, $sistemaSub, 'subdiario', $campos,
    ' WHERE (subd_cuenta = '.$cuenta.' OR subd_contrapartida = '.$cuenta.')'
    .' AND subd_fecha BETWEEN '.$desde.' AND '.$hasta
);

echo 'lineas leidas en el periodo: '.count($lineas)."\n";

$vacio = static function (mixed $v): bool {
    $s = trim((string) ($v ?? ''));

    return $s === '' || ltrim($s, '0') === '';
};

$huerfanas = array_values(array_filter($lineas, static fn ($l): bool => $vacio($l->subd_emisor ?? null)));
echo 'lineas SIN emisor: '.count($huerfanas)."\n\n";

if ($huerfanas === []) {
    echo "Nada para corregir.\n";
    exit(0);
}

// --- Armar un UPDATE por línea huérfana, resolviendo emisor y descripción de sus hermanas ---
$pasos = [];
foreach ($huerfanas as $l) {
    $tipo = trim((string) $l->subd_tipo);
    $letra = trim((string) $l->subd_letra);
    $sucursal = (int) $l->subd_sucursal;
    $nro = (int) $l->subd_nro;
    $sistema = trim((string) $l->subd_sistema);
    $cuentaLinea = (string) ((int) round((float) $l->subd_cuenta));
    $importe = number_format(round((float) $l->subd_importe, 2), 2, '.', '');
    $clave = "{$tipo} {$letra} {$sucursal} {$nro}";

    // Hermanas del mismo comprobante: de ahí salen emisor y descripción reales.
    $hermanas = listar($api, $sistemaSub, 'subdiario', $campos,
        " WHERE subd_tipo='{$tipo}' AND subd_letra='{$letra}'"
        ." AND subd_sucursal='{$sucursal}' AND subd_nro='{$nro}'"
    );

    $emisor = '';
    $desc = '';
    foreach ($hermanas as $h) {
        if (! $vacio($h->subd_emisor ?? null)) {
            $emisor = trim((string) $h->subd_emisor);
            $desc = trim((string) ($h->subd_desc_mov ?? ''));
            break;
        }
    }

    // Respaldo: cliente de la cabecera de la venta.
    $origenEmisor = 'hermanas subdiario';
    if ($emisor === '') {
        $venta = listar($api, 'ventas', 'venta', 'ven_cliente,ven_tipo,ven_letra,ven_sucursal,ven_nro',
            " WHERE ven_tipo='{$tipo}' AND ven_letra='{$letra}'"
            ." AND ven_sucursal='{$sucursal}' AND ven_nro='{$nro}'"
        );
        if ($venta !== [] && ! $vacio($venta[0]->ven_cliente ?? null)) {
            $emisor = trim((string) $venta[0]->ven_cliente);
            $origenEmisor = 'venta.ven_cliente';
        }
    }

    echo "--- {$tipo} {$letra}-{$sucursal}-{$nro}  cuenta {$cuentaLinea}  importe ".$fmt((float) $importe)." ---\n";
    echo "  fecha={$l->subd_fecha}  contrapartida=".trim((string) $l->subd_contrapartida)
        .'  tipo_mov='.trim((string) $l->subd_tipo_mov)."\n";
    echo "  lineas del comprobante: ".count($hermanas)."\n";
    foreach ($hermanas as $h) {
        printf(
            "    cta=%-10s imp=%14s emisor='%s' desc='%s'\n",
            trim((string) $h->subd_cuenta),
            $fmt((float) $h->subd_importe),
            trim((string) ($h->subd_emisor ?? '')),
            trim((string) ($h->subd_desc_mov ?? '')),
        );
    }

    if ($emisor === '') {
        echo "  !! NO se pudo resolver el emisor: se omite esta linea\n\n";

        continue;
    }

    echo "  emisor resuelto = '{$emisor}' (origen: {$origenEmisor})\n";
    echo "  descripcion     = '{$desc}'\n";

    $esc = static fn (string $v): string => str_replace("'", "''", $v);

    $set = " subd_emisor='".$esc($emisor)."'";
    if ($desc !== '') {
        $set .= ", subd_desc_mov='".$esc($desc)."'";
    }
    $set .= ' ';

    // El filtro por emisor vacío hace el UPDATE idempotente y evita tocar las líneas correctas.
    $where = " WHERE subd_tipo='{$tipo}' AND subd_letra='{$letra}'"
        ." AND subd_sucursal='{$sucursal}' AND subd_nro='{$nro}'"
        ." AND subd_cuenta='{$cuentaLinea}' AND subd_importe='{$importe}'"
        ." AND (subd_emisor IS NULL OR TRIM(subd_emisor)='') ";
    if ($sistema !== '') {
        $where = str_replace(' WHERE ', " WHERE subd_sistema='{$sistema}' AND ", $where);
    }

    echo "\n  SQL:\n    UPDATE subdiario SET{$set}\n    {$where}\n\n";

    $pasos[] = [
        'clave' => $clave,
        'descripcion' => "subdiario emisor {$clave} cuenta {$cuentaLinea} = {$emisor}",
        'valores' => $set,
        'where' => $where,
        'antes' => $l,
        'hermanas' => $hermanas,
    ];
}

// --- Backup del estado previo ---
$dir = storage_path('app/reportes/correccion_subdiario_emisor');
if (! is_dir($dir)) {
    mkdir($dir, 0775, true);
}
$backup = $dir.'/backup_'.$cuenta.'_'.date('Ymd_His').'.json';
file_put_contents($backup, json_encode([
    'generado' => date('c'),
    'cuenta' => $cuenta,
    'periodo' => [$desde, $hasta],
    'aplicar' => $aplicar,
    'pasos' => $pasos,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "backup: {$backup}\n\n";

if (! $aplicar) {
    echo "DRY-RUN: no se escribio nada. Volve a correr con --aplicar para ejecutar los ".count($pasos)." UPDATE.\n";
    exit(0);
}

// --- Aplicar ---
foreach ($pasos as $paso) {
    echo "aplicando: {$paso['descripcion']}\n";
    try {
        $raw = $api->apiCallEscritura(
            [
                'acc' => 'update',
                'tabla' => 'subdiario',
                'sistema' => $sistemaSub,
                'valores' => $paso['valores'],
                'whereArmado' => $paso['where'],
            ],
            $paso['descripcion'],
            'correccion.subdiario_emisor.update',
            true,
        );
        echo '  OK  '.trim((string) $raw)."\n";
    } catch (Throwable $e) {
        echo '  FALLO: '.$e->getMessage()."\n";
        exit(1);
    }
}

// --- Verificación posterior ---
echo "\n--- verificacion ---\n";
$post = listar($api, $sistemaSub, 'subdiario', $campos,
    ' WHERE (subd_cuenta = '.$cuenta.' OR subd_contrapartida = '.$cuenta.')'
    .' AND subd_fecha BETWEEN '.$desde.' AND '.$hasta
);
$restantes = array_values(array_filter($post, static fn ($l): bool => $vacio($l->subd_emisor ?? null)));
echo 'lineas sin emisor luego de aplicar: '.count($restantes)."\n";
foreach ($restantes as $l) {
    printf(
        "  %s %s %s-%s-%s cta=%s imp=%s\n",
        $l->subd_fecha,
        trim((string) $l->subd_tipo),
        trim((string) $l->subd_letra),
        trim((string) $l->subd_sucursal),
        trim((string) $l->subd_nro),
        trim((string) $l->subd_cuenta),
        $fmt((float) $l->subd_importe),
    );
}
echo $restantes === [] ? "\nOK: no quedan lineas sin emisor en el periodo.\n" : "\nATENCION: quedan lineas sin emisor.\n";
