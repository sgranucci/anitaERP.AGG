<?php

declare(strict_types=1);

/*
 * Corrección puntual jornada 2026-07-01 Biyemas (empresa 1), asiento cierre ventas_medio_real.
 * El asiento se grabó con lógica vieja: cigarrillos en 414010001 (golosinas) con importe inflado
 * por facturas mixtas (498.507,52). Se reclasifica al desglose correcto (ExclTotem):
 *   - Ventas cigarrillos -> 414020001 VENTAS TABACO (importe solo líneas cigarrillo)
 *   - El resto vuelve a 413010001 VENTAS ALIMENTOS Y BEBIDAS
 *   - IVA débito re-split (normal / cigarrillos)
 * Total del asiento invariante => no altera conciliación global ni el resto de cuentas.
 *
 * Uso:  php scripts/fix_cigarrillos_biyemas_0107.php          (dry-run, solo verifica)
 *       php scripts/fix_cigarrillos_biyemas_0107.php apply    (persiste ERP + ctamov Anita)
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Contable\Asiento;
use App\Repositories\Contable\AsientoRepository;
use App\Support\Ventas\Gastronomia\CierreJornadaFacturadoAnitaSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoConfigSupport;
use Illuminate\Support\Facades\DB;

$apply = in_array('apply', $argv, true);
$empresa = 1;
$fecha = '2026-07-01';
$asientoId = 38252;         // numeroasiento 349456 (ventas_medio_real)
$excelControlCig = 418672.66;

$tol = 0.05;
$fmt = static fn (float $v): string => number_format($v, 2, ',', '.');

$cfg = CierreJornadaProcesoConfigSupport::paraEmpresaConDetalle($empresa);
$idFood = (int) ($cfg['cuenta_ventas_id'] ?? 0);        // 413010001
$idTabaco = (int) ($cfg['cuenta_ventas_kiosco_id'] ?? 0); // 414020001
$idIva = (int) ($cfg['cuenta_iva_id'] ?? 0);            // 214010009
$idGolosinas = (int) DB::table('cuentacontable')->where('empresa_id', $empresa)->where('codigo', '414010001')->value('id');

echo "=== Corrección cigarrillos Biyemas jornada {$fecha} (".($apply ? 'APLICAR' : 'DRY-RUN').") ===\n";
echo "Cuentas: food 413010001=id{$idFood}  tabaco 414020001=id{$idTabaco}  iva 214010009=id{$idIva}  golosinas 414010001=id{$idGolosinas}\n\n";

// --- Desglose correcto (misma lógica que graba el resto de los días) ---
$d = CierreJornadaFacturadoAnitaSupport::datosAsientoVentasJornadaExclTotem($empresa, $fecha);
$tgtGrav = round((float) $d['ventas_gravadas'], 2);
$tgtKiosco = round((float) $d['ventas_kiosco'], 2);
$tgtIvaN = round((float) $d['iva_normal'], 2);
$tgtIvaC = round((float) $d['iva_cigarrillos'], 2);
echo "Desglose correcto (ExclTotem):\n";
echo "  ventas gravadas (413010001) : {$fmt($tgtGrav)}\n";
echo "  ventas cigarrillos (414020001): {$fmt($tgtKiosco)}   [control contaduría {$fmt($excelControlCig)}, dif ".$fmt(round($tgtKiosco - $excelControlCig, 2))."]\n";
echo "  iva débito normal            : {$fmt($tgtIvaN)}\n";
echo "  iva débito cigarrillos       : {$fmt($tgtIvaC)}\n\n";

$asiento = Asiento::with('asiento_movimientos.cuentacontables')->findOrFail($asientoId);

// --- Clasificar líneas actuales ---
$lineFood = null;   // 413010001 principal (obs sin 'exento')
$lineFoodExento = null;
$lineKiosco = null; // 414010001 (a mover)
$ivaLines = [];
$debeTotal = 0.0;
$haberTotalActual = 0.0;

foreach ($asiento->asiento_movimientos as $m) {
    $monto = round((float) $m->monto, 2);
    if ($monto > 0) {
        $debeTotal += $monto;
    } else {
        $haberTotalActual += abs($monto);
    }
    $cc = (int) $m->cuentacontable_id;
    $obs = mb_strtolower((string) ($m->observacion ?? ''));
    if ($cc === $idFood) {
        if (str_contains($obs, 'exento')) {
            $lineFoodExento = $m;
        } elseif ($lineFood === null || abs($monto) > abs((float) $lineFood->monto)) {
            $lineFood = $m;
        }
    } elseif ($cc === $idGolosinas) {
        $lineKiosco = $m;
    } elseif ($cc === $idIva) {
        $ivaLines[] = $m;
    }
}

if ($lineFood === null || $lineKiosco === null || count($ivaLines) < 2) {
    fwrite(STDERR, "No se pudieron clasificar las líneas esperadas del asiento. Abortando.\n");
    exit(1);
}

usort($ivaLines, static fn ($a, $b) => abs((float) $b->monto) <=> abs((float) $a->monto));
$lineIvaNormal = $ivaLines[0];
$lineIvaCig = $ivaLines[1];
$exentoFood = $lineFoodExento ? round((float) $lineFoodExento->monto, 2) : 0.0; // negativo

// Target de la línea food principal absorbe el exento para no romper el balance
$tgtFoodPrincipal = round(-1 * ($tgtGrav - abs($exentoFood)), 2);

$cambios = [
    ['linea' => 'Ventas gravadas 413010001', 'mov' => $lineFood, 'monto_nuevo' => $tgtFoodPrincipal, 'cuenta_nueva' => $idFood],
    ['linea' => 'Ventas cigarrillos 414010001 -> 414020001', 'mov' => $lineKiosco, 'monto_nuevo' => round(-1 * $tgtKiosco, 2), 'cuenta_nueva' => $idTabaco],
    ['linea' => 'IVA débito normal 214010009', 'mov' => $lineIvaNormal, 'monto_nuevo' => round(-1 * $tgtIvaN, 2), 'cuenta_nueva' => $idIva],
    ['linea' => 'IVA débito cigarrillos 214010009', 'mov' => $lineIvaCig, 'monto_nuevo' => round(-1 * $tgtIvaC, 2), 'cuenta_nueva' => $idIva],
];

echo "=== Líneas del asiento 349456 (#{$asientoId}) ===\n";
printf("%-42s %16s %16s %14s\n", 'Línea', 'Actual', 'Nuevo', 'Delta');
$haberTotalNuevo = 0.0;
foreach ($asiento->asiento_movimientos as $m) {
    $actual = round((float) $m->monto, 2);
    $nuevo = $actual;
    $cuentaNueva = (int) $m->cuentacontable_id;
    foreach ($cambios as $c) {
        if ($c['mov']->id === $m->id) {
            $nuevo = round((float) $c['monto_nuevo'], 2);
            $cuentaNueva = (int) $c['cuenta_nueva'];
        }
    }
    if ($nuevo < 0) {
        $haberTotalNuevo += abs($nuevo);
    }
    $codAct = (string) ($m->cuentacontables->codigo ?? $m->cuentacontable_id);
    $codNue = (string) (DB::table('cuentacontable')->where('id', $cuentaNueva)->value('codigo') ?? $cuentaNueva);
    $marca = ($nuevo !== $actual || $cuentaNueva !== (int) $m->cuentacontable_id) ? ' *' : '';
    printf(
        "%-42s %16s %16s %14s%s\n",
        substr($codAct.($codAct !== $codNue ? '->'.$codNue : '').' '.trim((string) $m->observacion), 0, 42),
        $fmt($actual),
        $fmt($nuevo),
        $fmt(round($nuevo - $actual, 2)),
        $marca,
    );
}

echo "\n=== Verificación de balance ===\n";
echo "  Debe (cobranzas)          : {$fmt(round($debeTotal, 2))}\n";
echo "  Haber actual (ventas+iva) : {$fmt(round($haberTotalActual, 2))}\n";
echo "  Haber nuevo  (ventas+iva) : {$fmt(round($haberTotalNuevo, 2))}\n";
$balanceOk = abs($debeTotal - $haberTotalNuevo) <= $tol && abs($haberTotalActual - $haberTotalNuevo) <= $tol;
echo '  Balanceado y total invariante: '.($balanceOk ? 'SÍ' : 'NO ***').")\n";

// --- Ventas normales a nivel jornada (todos los asientos del cierre) ---
echo "\n=== Ventas normales (413010001) — jornada completa ===\n";
$foodActualJornada = (float) DB::table('asiento_movimiento as am')
    ->join('asiento as a', 'a.id', '=', 'am.asiento_id')
    ->where('a.empresa_id', $empresa)
    ->whereBetween('a.fecha', [$fecha, $fecha])
    ->where('am.cuentacontable_id', $idFood)
    ->sum(DB::raw('-am.monto'));
$deltaFood = round(($tgtFoodPrincipal - (float) $lineFood->monto) * -1, 2);
$foodNuevoJornada = round($foodActualJornada + $deltaFood, 2);
echo "  413010001 haber jornada actual : {$fmt(round($foodActualJornada, 2))}\n";
echo "  Ajuste por reclasificación     : +{$fmt($deltaFood)}\n";
echo "  413010001 haber jornada nuevo  : {$fmt($foodNuevoJornada)}\n";
echo "  (el aumento en food = la baja en cigarrillos; total ventas+IVA de la jornada NO cambia)\n";

if (! $balanceOk) {
    fwrite(STDERR, "\nBalance inválido: no se aplica nada.\n");
    exit(1);
}

if (! $apply) {
    echo "\nDRY-RUN: no se persistió nada. Ejecutar con 'apply' para grabar ERP + ctamov Anita.\n";
    exit(0);
}

echo "\n=== APLICANDO ===\n";
DB::transaction(function () use ($cambios) {
    foreach ($cambios as $c) {
        $m = $c['mov'];
        $m->monto = round((float) $c['monto_nuevo'], 2);
        $m->cuentacontable_id = (int) $c['cuenta_nueva'];
        $m->save();
    }
});
echo "ERP actualizado. Resincronizando ctamov Anita...\n";

$repo = app(AsientoRepository::class);
$asiento->refresh()->load(['asiento_movimientos.monedas']);
$payload = $repo->armarPayloadAnitaDesdeModelo($asiento);
$repo->sincronizarCtamovAnita($payload);
echo "ctamov Anita resincronizado para asiento {$asiento->numeroasiento}.\n";
