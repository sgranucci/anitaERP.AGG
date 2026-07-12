<?php

declare(strict_types=1);

/**
 * PV 20 BIYEMAS — cerrar huecos junio 2026 + CAEA faltante.
 * Uso: php scripts/pv20_huecos_junio2026.php [--fase=importar|crear|caea|todo] [--dry-run]
 */
require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\ApiAnita;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Emision;
use App\Models\Ventas\Venta_Impuesto;
use App\Support\Ventas\ArcaCaeaInformeDatosDesdeVentaSupport;
use App\Support\Ventas\Gastronomia\GastronomiaAnitaVenGravadoSupport;
use App\Support\Ventas\GastronomiaAnitaImportEmpresaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

const PV_ID = 24;
const PV_CODIGO = 20;
const SUCURSAL = 20;
const EMPRESA_ID = 1;
const TIPOTRANSACCION_ID = 1;
const ACTIVIDAD_ARCA_ID = 1;
const CLIENTE_ID = 1;
const CONDICIONIVA_ID = 3;
const USUARIO_ID = 1;
const INVENTARIO_JSON = '/tmp/pv20_inventario_junio2026.json';

/** @var array<string, array{cfg:int, pc:string}> */
const HOST_CFG = [
    'pc-estac1' => ['cfg' => 3, 'pc' => '10.20.28.193'],
    'pc-estac2' => ['cfg' => 4, 'pc' => '10.20.28.192'],
    'pc-estac3' => ['cfg' => 2, 'pc' => '10.20.31.153'],
    'pc-estac4' => ['cfg' => 5, 'pc' => '10.20.31.152'],
    'pc-estac6' => ['cfg' => 6, 'pc' => '10.20.28.194'],
];

$opts = getopt('', ['fase::', 'dry-run']);
$fase = strtolower(trim((string) ($opts['fase'] ?? 'todo')));
$dryRun = array_key_exists('dry-run', $opts);

if (! in_array($fase, ['importar', 'crear', 'caea', 'todo'], true)) {
    fwrite(STDERR, "Fase inválida: {$fase}\n");
    exit(1);
}

if (! is_readable(INVENTARIO_JSON)) {
    fwrite(STDERR, 'No se encuentra inventario: '.INVENTARIO_JSON."\n");
    exit(1);
}

/** @var array<string, mixed> $inventario */
$inventario = json_decode((string) file_get_contents(INVENTARIO_JSON), true, 512, JSON_THROW_ON_ERROR);
$empresaCodigo = GastronomiaAnitaImportEmpresaSupport::codigoEmpresa(EMPRESA_ID);
$api = new ApiAnita();

$resumen = [
    'importados' => 0,
    'creados' => 0,
    'caea_erp' => 0,
    'caea_anita_ins' => 0,
    'caea_anita_upd' => 0,
    'omitidos' => 0,
    'errores' => [],
];

function logLine(string $msg): void
{
    echo $msg.PHP_EOL;
}

function armarCodigo(int $nro): string
{
    return 'FAC B-'.str_pad((string) PV_CODIGO, 5, '0', STR_PAD_LEFT).'-'.str_pad((string) $nro, 8, '0', STR_PAD_LEFT);
}

function parseFechaAnita(?string $yyyymmdd): string
{
    $yyyymmdd = trim((string) $yyyymmdd);
    if (strlen($yyyymmdd) === 8 && ctype_digit($yyyymmdd)) {
        return Carbon::createFromFormat('Ymd', $yyyymmdd)->format('Y-m-d');
    }

    return Carbon::today()->format('Y-m-d');
}

function anitaList(string $tabla, string $campos, string $where, string $prefijo = 'ven'): array
{
    global $api;

    $ultimo = ['filas' => []];
    for ($intento = 0; $intento < 4; $intento++) {
        $ultimo = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'tabla' => $tabla,
            'campos' => $campos,
            'whereArmado' => $where,
        ], EMPRESA_ID));
        if (($ultimo['filas'] ?? []) !== []) {
            return $ultimo;
        }
        usleep(250000 * ($intento + 1));
    }

    return $ultimo;
}

function whereComprobante(int $nro, string $prefijo = 'ven', string $tipo = 'FAC'): string
{
    return ' WHERE '.$prefijo."_tipo='".$tipo."' AND ".$prefijo."_letra='B'"
        ." AND ".$prefijo."_sucursal='".SUCURSAL."' AND ".$prefijo.'_nro = '.$nro
        .GastronomiaAnitaImportEmpresaSupport::whereEmpresa($prefijo, $GLOBALS['empresaCodigo']);
}

function leerCabeceraAnita(int $nro): ?object
{
    $parsed = anitaList('venta', implode(',', [
        'ven_tipo', 'ven_fecha', 'ven_fecha_vto', 'ven_monto', 'ven_gravado', 'ven_exento', 'ven_impuesto1', 'ven_imp_interno',
    ]), whereComprobante($nro));

    return $parsed['filas'][0] ?? null;
}

function leerVengravAnita(int $nro): array
{
    $parsed = anitaList('vengrav', 'veng_tasa,veng_gravado,veng_impuesto,veng_codigo_tasa', whereComprobante($nro, 'veng'));

    return $parsed['filas'] ?? [];
}

function leerVencaeAnita(int $nro): ?object
{
    $where = " WHERE venc_tipo='FAC' AND venc_letra='B' AND venc_sucursal='".SUCURSAL."' AND venc_nro='".$nro."' ";
    $parsed = anitaList('vencae', 'venc_nro_cae,venc_fecha_vto', $where, 'venc');

    return $parsed['filas'][0] ?? null;
}

function leerResvHost(int $nro): string
{
    $parsed = anitaList('resvta', 'resv_host', whereComprobante($nro, 'resv'));
    $host = strtolower(trim((string) ($parsed['filas'][0]->resv_host ?? '')));

    return $host !== '' ? $host : 'pc-estac3';
}

/** @return array{cfg:int, pc:string} */
function resolverCfg(string $host): array
{
    if (isset(HOST_CFG[$host])) {
        return HOST_CFG[$host];
    }
    foreach (HOST_CFG as $clave => $cfg) {
        if (str_contains($host, str_replace('pc-', '', $clave))) {
            return $cfg;
        }
    }

    return HOST_CFG['pc-estac3'];
}

/** @return array{jornada_id:?int, turno_id:?int} */
function resolverJornadaTurno(string $fechaJornada, int $cfgId): array
{
    $jornadaId = DB::table('jornada_estacionamiento')
        ->where('empresa_id', EMPRESA_ID)
        ->where('fecha_jornada', $fechaJornada)
        ->value('id');

    if (! $jornadaId) {
        return ['jornada_id' => null, 'turno_id' => null];
    }

    $turnoId = DB::table('turno_operativo_estacionamiento')
        ->where('jornada_estacionamiento_id', $jornadaId)
        ->where('configuracion_puntoventa_estacionamiento_id', $cfgId)
        ->orderBy('id')
        ->value('id');

    if (! $turnoId) {
        $turnoId = DB::table('turno_operativo_estacionamiento')
            ->where('jornada_estacionamiento_id', $jornadaId)
            ->orderBy('id')
            ->value('id');
    }

    return ['jornada_id' => (int) $jornadaId, 'turno_id' => $turnoId ? (int) $turnoId : null];
}

function crearImpuestosDesdeCabecera(int $ventaId, object $cab, array $vengrav, Carbon $ts): void
{
    $total = round(abs((float) ($cab->ven_monto ?? 0)), 2);
    if (GastronomiaAnitaVenGravadoSupport::esCortesiaMinima($total)) {
        crearImpuestosInvitacion($ventaId, $ts);

        return;
    }

    $gravadoNeto = round((float) ($cab->ven_gravado ?? 0), 2);
    $exento = round((float) ($cab->ven_exento ?? 0), 2);
    $iva = round((float) ($cab->ven_impuesto1 ?? 0), 2);
    $total = round(abs((float) ($cab->ven_monto ?? 0)), 2);
    $subtotal = round($gravadoNeto + $exento, 2);

    $filas = [];
    if ($subtotal > 0) {
        $filas[] = ['concepto' => 'Subtotal', 'base' => 0., 'tasa' => 0., 'importe' => $subtotal, 'impuesto_id' => null];
    }
    if ($exento > 0) {
        $filas[] = ['concepto' => 'Exento', 'base' => 0., 'tasa' => 0., 'importe' => $exento, 'impuesto_id' => 1];
    }

    if ($vengrav !== []) {
        foreach ($vengrav as $vg) {
            $tasa = (float) ($vg->veng_tasa ?? 0);
            $filas[] = [
                'concepto' => 'Gravado al '.number_format($tasa, 3, '.', '').'%',
                'base' => 0.,
                'tasa' => $tasa,
                'importe' => round((float) ($vg->veng_gravado ?? 0), 2),
                'impuesto_id' => (int) ($vg->veng_codigo_tasa ?? 0) ?: null,
            ];
            $filas[] = [
                'concepto' => 'Iva '.number_format($tasa, 3, '.', '').'%',
                'base' => round((float) ($vg->veng_gravado ?? 0), 2),
                'tasa' => $tasa,
                'importe' => round((float) ($vg->veng_impuesto ?? 0), 2),
                'impuesto_id' => (int) ($vg->veng_codigo_tasa ?? 0) ?: null,
            ];
        }
    } elseif ($iva > 0) {
        $filas[] = [
            'concepto' => 'Gravado al 21.000%',
            'base' => 0.,
            'tasa' => 21.,
            'importe' => $gravadoNeto,
            'impuesto_id' => 3,
        ];
        $filas[] = [
            'concepto' => 'Iva 21.000%',
            'base' => $gravadoNeto,
            'tasa' => 21.,
            'importe' => $iva,
            'impuesto_id' => 3,
        ];
    }

    $filas[] = ['concepto' => 'Total', 'base' => 0., 'tasa' => 0., 'importe' => $total, 'impuesto_id' => null];

    foreach ($filas as $f) {
        if (abs($f['importe']) < 0.0001 && $f['concepto'] !== 'Total') {
            continue;
        }
        $vi = Venta_Impuesto::query()->create([
            'venta_id' => $ventaId,
            'concepto' => $f['concepto'],
            'baseimponible' => $f['base'],
            'tasa' => $f['tasa'],
            'importe' => $f['importe'],
            'provincia_id' => null,
            'impuesto_id' => $f['impuesto_id'],
        ]);
        $vi->created_at = $ts;
        $vi->updated_at = $ts;
        $vi->save();
    }
}

function crearImpuestosInvitacion(int $ventaId, Carbon $ts): void
{
    foreach ([
        ['concepto' => 'Exento', 'importe' => 0.01, 'impuesto_id' => 1],
        ['concepto' => 'Total', 'importe' => 0.01, 'impuesto_id' => null],
    ] as $f) {
        $vi = Venta_Impuesto::query()->create([
            'venta_id' => $ventaId,
            'concepto' => $f['concepto'],
            'baseimponible' => 0.,
            'tasa' => 0.,
            'importe' => $f['importe'],
            'provincia_id' => null,
            'impuesto_id' => $f['impuesto_id'],
        ]);
        $vi->created_at = $ts;
        $vi->updated_at = $ts;
        $vi->save();
    }
}

function ventaExiste(int $nro): bool
{
    return Venta::query()
        ->where('puntoventa_id', PV_ID)
        ->where('numerocomprobante', $nro)
        ->exists();
}

/** @param array<string, mixed> $hueco */
function importarDesdeAnita(array $hueco, bool $dryRun): void
{
    global $resumen;

    $nro = (int) $hueco['nro'];
    if (ventaExiste($nro)) {
        $resumen['omitidos']++;
        logLine("importar #{$nro}: omitido (ya en ERP)");

        return;
    }

    $cab = leerCabeceraAnita($nro);
    if ($cab === null) {
        throw new RuntimeException("Sin cabecera Anita para #{$nro}");
    }

    $vencae = leerVencaeAnita($nro);
    $caea = (string) ($hueco['caea_esperado'] ?? $vencae->venc_nro_cae ?? '');
    $vto = (string) ($hueco['caea_vto'] ?? '');
    if ($vto === '' && isset($vencae->venc_fecha_vto)) {
        $vto = parseFechaAnita((string) $vencae->venc_fecha_vto);
    }

    $fecha = parseFechaAnita((string) ($cab->ven_fecha ?? ''));
    $fechaJornada = parseFechaAnita((string) ($cab->ven_fecha_vto ?? $cab->ven_fecha ?? ''));
    $total = round(abs((float) ($cab->ven_monto ?? 0)), 2);
    $host = leerResvHost($nro);
    $cfg = resolverCfg($host);
    $jt = resolverJornadaTurno($fechaJornada, $cfg['cfg']);
    $codigo = armarCodigo($nro);
    $ts = Carbon::parse($fechaJornada.' 12:00:00');

    if ($dryRun) {
        logLine("importar #{$nro}: simulado total={$total} fecha={$fecha} caea={$caea} host={$host}");
        $resumen['importados']++;

        return;
    }

    DB::transaction(function () use ($nro, $cab, $fecha, $fechaJornada, $total, $caea, $vto, $cfg, $jt, $codigo, $ts, $host): void {
        $venta = Venta::query()->create([
            'fecha' => $fecha,
            'fechajornada' => $fechaJornada,
            'tipotransaccion_id' => TIPOTRANSACCION_ID,
            'puntoventa_id' => PV_ID,
            'numerocomprobante' => $nro,
            'actividad_arca_id' => ACTIVIDAD_ARCA_ID,
            'cliente_id' => CLIENTE_ID,
            'condicionventa_id' => null,
            'vendedor_id' => null,
            'transporte_id' => null,
            'total' => $total,
            'moneda_id' => 1,
            'cotizacion' => 1.,
            'estado' => ' ',
            'usuario_id' => USUARIO_ID,
            'leyenda' => 'Estacionamiento — importación Anita '.$codigo.' ('.$host.')',
            'descuento' => 0.,
            'descuentointegrado' => ' ',
            'lugarentrega' => null,
            'cliente_entrega_id' => null,
            'codigo' => $codigo,
            'nombre' => 'CONSUMIDOR FINAL',
            'domicilio' => '',
            'localidad_id' => null,
            'provincia_id' => null,
            'pais_id' => 1,
            'codigopostal' => '',
            'email' => null,
            'telefono' => null,
            'numerodocumento' => '0',
            'condicioniva_id' => CONDICIONIVA_ID,
            'cae' => $caea !== '' ? $caea : null,
            'fechavencimientocae' => $vto !== '' ? $vto : null,
            'puntoventaremito_id' => null,
            'numeroremito' => 0,
            'cantidadbulto' => 1,
        ]);
        $venta->created_at = $ts;
        $venta->updated_at = $ts;
        $venta->save();

        crearImpuestosDesdeCabecera((int) $venta->id, $cab, leerVengravAnita($nro), $ts);

        DB::table('venta_estacionamiento_emision')->insert([
            'venta_id' => $venta->id,
            'ticket_estacionamiento_id' => null,
            'identificador_pc' => $cfg['pc'],
            'configuracion_puntoventa_estacionamiento_id' => $cfg['cfg'],
            'jornada_estacionamiento_id' => $jt['jornada_id'],
            'turno_operativo_estacionamiento_id' => $jt['turno_id'],
            'venta_factura_origen_id' => null,
            'created_at' => $ts,
            'updated_at' => $ts,
        ]);
    });

    $resumen['importados']++;
    logLine("importar #{$nro}: OK total={$total} caea={$caea}");
}

/** @param array<string, mixed> $hueco */
function crearInvitacion(array $hueco, bool $dryRun): void
{
    global $resumen, $api;

    $nro = (int) $hueco['nro'];
    if (ventaExiste($nro)) {
        $resumen['omitidos']++;
        logLine("crear #{$nro}: omitido (ya en ERP)");

        return;
    }

    $fecha = (string) ($hueco['fecha_sugerida'] ?? '2026-06-15');
    $caea = (string) ($hueco['caea_esperado'] ?? '');
    $vto = (string) ($hueco['caea_vto'] ?? '2026-06-15');
    $vtoAnita = str_replace('-', '', $vto);
    $fechaAnita = str_replace('-', '', $fecha);
    $codigo = armarCodigo($nro);
    $cfg = HOST_CFG['pc-estac3'];
    $jt = resolverJornadaTurno($fecha, $cfg['cfg']);
    $ts = Carbon::parse($fecha.' 12:00:00');

    if ($dryRun) {
        logLine("crear #{$nro}: simulado invitación fecha={$fecha} caea={$caea}");
        $resumen['creados']++;

        return;
    }

    DB::transaction(function () use ($nro, $fecha, $caea, $vto, $codigo, $cfg, $jt, $ts): void {
        $venta = Venta::query()->create([
            'fecha' => $fecha,
            'fechajornada' => $fecha,
            'tipotransaccion_id' => TIPOTRANSACCION_ID,
            'puntoventa_id' => PV_ID,
            'numerocomprobante' => $nro,
            'actividad_arca_id' => ACTIVIDAD_ARCA_ID,
            'cliente_id' => CLIENTE_ID,
            'condicionventa_id' => null,
            'vendedor_id' => null,
            'transporte_id' => null,
            'total' => 0.01,
            'moneda_id' => 1,
            'cotizacion' => 1.,
            'estado' => ' ',
            'usuario_id' => USUARIO_ID,
            'leyenda' => 'Estacionamiento — invitación correlatividad CAEA '.$codigo,
            'descuento' => 0.,
            'descuentointegrado' => ' ',
            'lugarentrega' => null,
            'cliente_entrega_id' => null,
            'codigo' => $codigo,
            'nombre' => 'CONSUMIDOR FINAL',
            'domicilio' => '',
            'localidad_id' => null,
            'provincia_id' => null,
            'pais_id' => 1,
            'codigopostal' => '',
            'email' => null,
            'telefono' => null,
            'numerodocumento' => '0',
            'condicioniva_id' => CONDICIONIVA_ID,
            'cae' => $caea,
            'fechavencimientocae' => $vto,
            'puntoventaremito_id' => null,
            'numeroremito' => 0,
            'cantidadbulto' => 1,
        ]);
        $venta->created_at = $ts;
        $venta->updated_at = $ts;
        $venta->save();

        crearImpuestosInvitacion((int) $venta->id, $ts);

        DB::table('venta_estacionamiento_emision')->insert([
            'venta_id' => $venta->id,
            'ticket_estacionamiento_id' => null,
            'identificador_pc' => $cfg['pc'],
            'configuracion_puntoventa_estacionamiento_id' => $cfg['cfg'],
            'jornada_estacionamiento_id' => $jt['jornada_id'],
            'turno_operativo_estacionamiento_id' => $jt['turno_id'],
            'venta_factura_origen_id' => null,
            'created_at' => $ts,
            'updated_at' => $ts,
        ]);
    });

    $whereVenta = whereComprobante($nro)." AND ven_empresa='".$GLOBALS['empresaCodigo']."' ";
    $respVenta = $api->apiCallEscritura([
        'acc' => 'insert',
        'tabla' => 'venta',
        'sistema' => 'ventas',
        'campos' => 'ven_tipo,ven_letra,ven_sucursal,ven_nro,ven_empresa,ven_fecha,ven_fecha_vto,ven_monto,ven_gravado,ven_exento,ven_impuesto1',
        'valores' => "'FAC','B','".SUCURSAL."','".$nro."','".$GLOBALS['empresaCodigo']."','".$fechaAnita."','".$fechaAnita."',0.01,0,0.01,0",
    ], 'venta insert invitacion PV20 '.$nro);
    if (! ApiAnita::respuestaBridgeEscrituraExitosa($respVenta)) {
        throw new RuntimeException('Anita venta '.$nro.': '.(ApiAnita::extraerMensajeError($respVenta) ?? 'fallo insert'));
    }

    $whereVencae = " WHERE venc_tipo='FAC' AND venc_letra='B' AND venc_sucursal='".SUCURSAL."' AND venc_nro='".$nro."' ";
    $parsed = anitaList('vencae', 'venc_nro', $whereVencae, 'venc');
    if ($parsed['filas'] === []) {
        $respVencae = $api->apiCallEscritura([
            'acc' => 'insert',
            'tabla' => 'vencae',
            'sistema' => 'ventas',
            'campos' => 'venc_tipo,venc_letra,venc_sucursal,venc_nro,venc_nro_cae,venc_fecha_vto',
            'valores' => "'FAC','B','".SUCURSAL."','".$nro."','".$caea."','".$vtoAnita."'",
        ], 'vencae insert invitacion PV20 '.$nro);
        if (! ApiAnita::respuestaBridgeEscrituraExitosa($respVencae)) {
            throw new RuntimeException('Anita vencae '.$nro.': '.(ApiAnita::extraerMensajeError($respVencae) ?? 'fallo insert'));
        }
    }

    $resumen['creados']++;
    logLine("crear #{$nro}: OK invitación ERP+Anita caea={$caea}");
}

/** @param array<string, mixed> $item */
function asignarCaea(array $item, bool $dryRun): void
{
    global $resumen, $api;

    $nro = (int) $item['nro'];
    $caea = (string) ($item['caea_esperado'] ?? '');
    $vto = (string) ($item['caea_vto'] ?? '');
    $vtoAnita = str_replace('-', '', $vto);

    $venta = Venta::query()
        ->where('puntoventa_id', PV_ID)
        ->where('numerocomprobante', $nro)
        ->where('codigo', 'like', 'FAC B%')
        ->first();

    if ($venta === null) {
        throw new RuntimeException("Venta ERP inexistente para CAEA #{$nro}");
    }

    if ((string) ($venta->cae ?? '') === $caea && (string) ($venta->fechavencimientocae ?? '') === $vto) {
        $resumen['omitidos']++;
        logLine("caea #{$nro}: omitido (ya asignado)");

        return;
    }

    if ($dryRun) {
        logLine("caea #{$nro}: simulado -> {$caea}");
        $resumen['caea_erp']++;

        return;
    }

    DB::table('venta')->where('id', $venta->id)->update([
        'cae' => $caea,
        'fechavencimientocae' => $vto,
        'updated_at' => now(),
    ]);
    $resumen['caea_erp']++;

    $whereVencae = " WHERE venc_tipo='FAC' AND venc_letra='B' AND venc_sucursal='".SUCURSAL."' AND venc_nro='".$nro."' ";
    $parsed = anitaList('vencae', 'venc_nro', $whereVencae, 'venc');

    if ($parsed['filas'] !== []) {
        $resp = $api->apiCallEscritura([
            'acc' => 'update',
            'tabla' => 'vencae',
            'sistema' => 'ventas',
            'valores' => " venc_nro_cae='".$caea."', venc_fecha_vto='".$vtoAnita."' ",
            'whereArmado' => $whereVencae,
        ], 'vencae update PV20 '.$nro);
        if (! ApiAnita::respuestaBridgeEscrituraExitosa($resp)) {
            throw new RuntimeException('Anita vencae update '.$nro.': '.(ApiAnita::extraerMensajeError($resp) ?? 'fallo'));
        }
        $resumen['caea_anita_upd']++;
    } else {
        $resp = $api->apiCallEscritura([
            'acc' => 'insert',
            'tabla' => 'vencae',
            'sistema' => 'ventas',
            'campos' => 'venc_tipo,venc_letra,venc_sucursal,venc_nro,venc_nro_cae,venc_fecha_vto',
            'valores' => "'FAC','B','".SUCURSAL."','".$nro."','".$caea."','".$vtoAnita."'",
        ], 'vencae insert PV20 '.$nro);
        if (! ApiAnita::respuestaBridgeEscrituraExitosa($resp)) {
            throw new RuntimeException('Anita vencae insert '.$nro.': '.(ApiAnita::extraerMensajeError($resp) ?? 'fallo'));
        }
        $resumen['caea_anita_ins']++;
    }

    logLine("caea #{$nro}: OK {$caea}");
}

logLine('PV20 huecos junio 2026 · fase='.$fase.($dryRun ? ' [dry-run]' : ''));

if ($fase === 'importar' || $fase === 'todo') {
    logLine('--- Importar desde Anita (estacionamiento) ---');
    foreach ($inventario['huecos'] as $hueco) {
        if (! ($hueco['en_anita'] ?? false)) {
            continue;
        }
        try {
            importarDesdeAnita($hueco, $dryRun);
            usleep(120000);
        } catch (Throwable $e) {
            $resumen['errores'][] = 'importar '.$hueco['nro'].': '.$e->getMessage();
            logLine('ERROR importar #'.$hueco['nro'].': '.$e->getMessage());
        }
    }
}

if ($fase === 'crear' || $fase === 'todo') {
    logLine('--- Crear invitaciones (sin Anita) ---');
    foreach ($inventario['huecos'] as $hueco) {
        if ($hueco['en_anita'] ?? true) {
            continue;
        }
        try {
            crearInvitacion($hueco, $dryRun);
            usleep(150000);
        } catch (Throwable $e) {
            $resumen['errores'][] = 'crear '.$hueco['nro'].': '.$e->getMessage();
            logLine('ERROR crear #'.$hueco['nro'].': '.$e->getMessage());
        }
    }
}

if ($fase === 'caea' || $fase === 'todo') {
    logLine('--- Asignar CAEA ---');
    foreach ($inventario['sin_caea'] as $item) {
        try {
            asignarCaea($item, $dryRun);
            if (! $dryRun) {
                usleep(80000);
            }
        } catch (Throwable $e) {
            $resumen['errores'][] = 'caea '.$item['nro'].': '.$e->getMessage();
            logLine('ERROR caea #'.$item['nro'].': '.$e->getMessage());
        }
    }
}

logLine('--- Resumen ---');
echo json_encode($resumen, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;

if (! $dryRun && $fase === 'todo' && $resumen['errores'] === []) {
    $huecos = array_column($inventario['huecos'], 'nro');
    $faltan = array_diff($huecos, DB::table('venta')->where('puntoventa_id', PV_ID)->whereIn('numerocomprobante', $huecos)->pluck('numerocomprobante')->map(fn ($n) => (int) $n)->all());
    $sinCaea = DB::table('venta')->where('puntoventa_id', PV_ID)
        ->whereIn('numerocomprobante', array_column($inventario['sin_caea'], 'nro'))
        ->where(function ($q): void {
            $q->whereNull('cae')->orWhere('cae', '');
        })->count();
    logLine('Verificación: huecos faltantes='.count($faltan).' sin_caea_restantes='.$sinCaea);
}

exit($resumen['errores'] === [] ? 0 : 1);
