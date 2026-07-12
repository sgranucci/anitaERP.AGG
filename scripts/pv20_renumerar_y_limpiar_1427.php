<?php

declare(strict_types=1);

/**
 * PV 20 BIYEMAS — renumerar duplicados julio 2026 y corregir obs ARCA 1427 ($0,01 gravado sin IVA).
 *
 * Uso:
 *   php scripts/pv20_renumerar_y_limpiar_1427.php [--fase=renumerar|limpiar1427|reinformar|todo] [--dry-run]
 */
require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Ventas\ArcaCaea;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Impuesto;
use App\Services\Arca\ArcaCaeaPresentacionService;
use App\Support\Ventas\ArcaCaeaInformeDatosDesdeVentaSupport;
use App\Support\Ventas\Gastronomia\GastronomiaAnitaVenGravadoSupport;
use Illuminate\Support\Facades\DB;

const PV_ID = 24;
const PV_CODIGO = 20;
const SUCURSAL = 20;

/** @var list<array{venta_id:int, nro_viejo:int, nro_nuevo:int}> */
const RENUMERAR = [
    ['venta_id' => 50920, 'nro_viejo' => 184405, 'nro_nuevo' => 184585],
    ['venta_id' => 55949, 'nro_viejo' => 184412, 'nro_nuevo' => 184586],
];

/** CAEA junio 2026 (empresa 1) */
const CAEA_Q1_ID = 31;
const CAEA_Q2_ID = 34;

$opts = getopt('', ['fase::', 'dry-run']);
$fase = strtolower(trim((string) ($opts['fase'] ?? 'todo')));
$dryRun = array_key_exists('dry-run', $opts);

if (! in_array($fase, ['renumerar', 'limpiar1427', 'reinformar', 'todo'], true)) {
    fwrite(STDERR, "Fase inválida: {$fase}\n");
    exit(1);
}

function logLine(string $msg): void
{
    echo $msg.PHP_EOL;
}

function armarCodigo(int $nro): string
{
    return 'FAC B-'.str_pad((string) PV_CODIGO, 5, '0', STR_PAD_LEFT).'-'.str_pad((string) $nro, 8, '0', STR_PAD_LEFT);
}

function reemplazarImpuestosCortesiaMinima(int $ventaId, bool $dryRun): void
{
    $ts = now();
    if ($dryRun) {
        return;
    }

    Venta_Impuesto::query()->where('venta_id', $ventaId)->delete();

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

function faseRenumerar(bool $dryRun): void
{
    logLine('=== Fase renumerar duplicados julio ===');

    foreach (RENUMERAR as $item) {
        $ventaId = (int) $item['venta_id'];
        $nroViejo = (int) $item['nro_viejo'];
        $nroNuevo = (int) $item['nro_nuevo'];

        $venta = DB::table('venta')->where('id', $ventaId)->where('puntoventa_id', PV_ID)->first();
        if ($venta === null) {
            throw new RuntimeException("Venta {$ventaId} no encontrada en PV ".PV_ID);
        }
        if ((int) $venta->numerocomprobante !== $nroViejo) {
            throw new RuntimeException("Venta {$ventaId}: esperado nro {$nroViejo}, tiene {$venta->numerocomprobante}");
        }

        $ocupado = DB::table('venta')
            ->where('puntoventa_id', PV_ID)
            ->where('numerocomprobante', $nroNuevo)
            ->where('id', '!=', $ventaId)
            ->exists();
        if ($ocupado) {
            throw new RuntimeException("Número {$nroNuevo} ya ocupado en PV ".PV_ID);
        }

        $codigoNuevo = armarCodigo($nroNuevo);
        logLine("  #{$nroViejo} id={$ventaId} → #{$nroNuevo} ({$codigoNuevo})".($dryRun ? ' [dry-run]' : ''));

        if (! $dryRun) {
            DB::table('venta')->where('id', $ventaId)->update([
                'numerocomprobante' => $nroNuevo,
                'codigo' => $codigoNuevo,
                'updated_at' => now(),
            ]);
        }
    }

    $dups = DB::select(
        'SELECT numerocomprobante, COUNT(*) c FROM venta WHERE puntoventa_id = ? GROUP BY numerocomprobante HAVING c > 1',
        [PV_ID]
    );
    if ($dups !== []) {
        throw new RuntimeException('Quedan duplicados en PV '.PV_ID.': '.json_encode($dups));
    }

    logLine('Renumeración OK — sin duplicados en PV '.PV_ID);
}

function idsObs1427(): array
{
    return DB::table('venta')
        ->where('puntoventa_id', PV_ID)
        ->where('caea_informado_estado', 'observacion')
        ->where('caea_informado_mensaje', 'like', '%1427%')
        ->orderBy('numerocomprobante')
        ->pluck('id')
        ->map(fn ($id) => (int) $id)
        ->all();
}

function faseLimpiar1427(bool $dryRun): void
{
    logLine('=== Fase limpiar obs 1427 ($0,01 exento) ===');

    $ids = idsObs1427();
    logLine('  Comprobantes con obs 1427: '.count($ids));

    $corregidos = 0;
    $anitaOk = 0;
    $anitaSkip = 0;
    $errores = [];

    foreach ($ids as $ventaId) {
        $venta = Venta::query()
            ->with(['venta_impuestos', 'venta_emisiones', 'puntoventas', 'tipotransacciones', 'clientes.tipodocumentos', 'monedas'])
            ->find($ventaId);

        if ($venta === null) {
            $errores[] = "venta {$ventaId} no encontrada";
            continue;
        }

        $nro = (int) $venta->numerocomprobante;
        $datosAntes = ArcaCaeaInformeDatosDesdeVentaSupport::construir($venta);

        reemplazarImpuestosCortesiaMinima($ventaId, $dryRun);

        if (! $dryRun) {
            $venta->refresh()->load([
                'venta_impuestos', 'venta_emisiones', 'puntoventas', 'tipotransacciones', 'clientes.tipodocumentos', 'monedas',
            ]);
            $datosDespues = ArcaCaeaInformeDatosDesdeVentaSupport::construir($venta);

            if (abs((float) $datosDespues['gravado']) > 0.001 || abs((float) $datosDespues['exento'] - 0.01) > 0.001) {
                $errores[] = "#{$nro} id={$ventaId} payload incorrecto grav={$datosDespues['gravado']} exento={$datosDespues['exento']}";
                continue;
            }

            DB::table('venta')->where('id', $ventaId)->update([
                'caea_informado_estado' => null,
                'caea_informado_mensaje' => null,
                'caea_informado_at' => null,
                'updated_at' => now(),
            ]);

            try {
                GastronomiaAnitaVenGravadoSupport::actualizarMontosCabeceraAnita(
                    'FAC',
                    'B',
                    SUCURSAL,
                    $nro,
                    0.0,
                    0.0,
                    0.01,
                    0.01,
                );
                $anitaOk++;
            } catch (Throwable $e) {
                $anitaSkip++;
                $errores[] = "#{$nro} Anita: ".$e->getMessage();
            }
        } else {
            logLine("  [dry-run] #{$nro} id={$ventaId} grav antes={$datosAntes['gravado']} exento antes={$datosAntes['exento']}");
        }

        $corregidos++;
    }

    logLine("  Corregidos ERP: {$corregidos}");
    logLine("  Anita actualizados: {$anitaOk} (omitidos/error: {$anitaSkip})");

    if ($errores !== []) {
        logLine('  Errores ('.count($errores).'):');
        foreach (array_slice($errores, 0, 15) as $e) {
            logLine('    - '.$e);
        }
        if (count($errores) > 15) {
            logLine('    ... y '.(count($errores) - 15).' más');
        }
    }

    if (! $dryRun) {
        $restantes = count(idsObs1427());
        $gravMal = DB::table('venta as v')
            ->join('venta_impuesto as vi', 'vi.venta_id', '=', 'v.id')
            ->where('v.puntoventa_id', PV_ID)
            ->whereRaw('ABS(v.total - 0.01) < 0.001')
            ->where('vi.concepto', 'like', 'Gravado%')
            ->distinct()
            ->count('v.id');

        logLine("  Verificación: obs 1427 restantes={$restantes}, \$0,01 con Gravado={$gravMal}");
    }
}

function faseReinformar(bool $dryRun): void
{
    logLine('=== Fase re-informar comps corregidos (junio CAEA) ===');

    if ($dryRun) {
        foreach ([CAEA_Q1_ID, CAEA_Q2_ID] as $caeaId) {
            $pend = DB::table('venta')
                ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
                ->where('puntoventa.empresa_id', 1)
                ->where('venta.puntoventa_id', PV_ID)
                ->whereNull('venta.caea_informado_estado')
                ->whereNotNull('venta.cae')
                ->whereBetween('venta.fecha', $caeaId === CAEA_Q1_ID ? ['2026-06-01', '2026-06-15'] : ['2026-06-16', '2026-06-30'])
                ->count();
            logLine("  [dry-run] CAEA id={$caeaId} pendientes null estado: {$pend}");
        }

        return;
    }

    /** @var ArcaCaeaPresentacionService $svc */
    $svc = app(ArcaCaeaPresentacionService::class);
    $usuarioId = 1;
    $limite = 500;

    foreach ([CAEA_Q1_ID => '1Q', CAEA_Q2_ID => '2Q'] as $caeaId => $etiqueta) {
        $registro = ArcaCaea::query()->find($caeaId);
        if ($registro === null) {
            throw new RuntimeException("Registro CAEA {$caeaId} no encontrado");
        }

        $totalInformados = 0;
        $iter = 0;
        do {
            $iter++;
            $resultado = $svc->informarPeriodo($registro, $usuarioId, false, $limite);
            $detalle = $resultado['detalle'] ?? [];
            $informados = (int) ($detalle['informados'] ?? 0);
            $totalInformados += $informados;
            $pendientes = (int) ($detalle['pendientes_restantes'] ?? ($resultado['resumen']['pendientes'] ?? 0));
            $obs = (int) ($detalle['con_observaciones'] ?? 0);
            $errores = (int) ($detalle['errores_lote'] ?? 0);

            logLine("  {$etiqueta} iter={$iter}: informados={$informados} obs={$obs} errores={$errores} pendientes={$pendientes}");

            if (! ($resultado['ok'] ?? false) && $informados === 0) {
                logLine('  Mensaje: '.($resultado['mensaje'] ?? 'sin mensaje'));
                break;
            }

            $registro->refresh();
        } while ($pendientes > 0 && $iter < 20);

        $obs1427 = DB::table('venta')
            ->where('puntoventa_id', PV_ID)
            ->where('caea_informado_estado', 'observacion')
            ->where('caea_informado_mensaje', 'like', '%1427%')
            ->whereBetween('fecha', $caeaId === CAEA_Q1_ID ? ['2026-06-01', '2026-06-15'] : ['2026-06-16', '2026-06-30'])
            ->count();

        logLine("  {$etiqueta} total informados en loop: {$totalInformados}; obs 1427 restantes en quincena: {$obs1427}");
    }
}

try {
    if ($fase === 'renumerar' || $fase === 'todo') {
        faseRenumerar($dryRun);
    }
    if ($fase === 'limpiar1427' || $fase === 'todo') {
        faseLimpiar1427($dryRun);
    }
    if ($fase === 'reinformar' || $fase === 'todo') {
        faseReinformar($dryRun);
    }

    logLine('Listo.');
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: '.$e->getMessage().PHP_EOL);
    exit(1);
}
