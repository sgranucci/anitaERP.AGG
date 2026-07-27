<?php

declare(strict_types=1);

/*
 * Corrección puntual jornada 2026-07-01 Kandiko (2) y Rebisco (3), asiento cierre ventas_medio_real.
 * Misma causa que Biyemas 1/7: cigarrillos en 414010001 (golosinas) con importe inflado
 * por facturas mixtas. Se reclasifica al desglose correcto (ExclTotem):
 *   - Ventas cigarrillos -> 414020001 VENTAS TABACO
 *   - El resto vuelve a 413010001 VENTAS ALIMENTOS Y BEBIDAS
 *   - IVA débito re-split (normal / cigarrillos)
 * Total del asiento invariante => no altera conciliación global ni el resto de cuentas.
 *
 * Uso:  php scripts/fix_cigarrillos_ksa_rsa_0107.php          (dry-run)
 *       php scripts/fix_cigarrillos_ksa_rsa_0107.php apply    (persiste ERP + ctamov Anita)
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
$fecha = '2026-07-01';
$tol = 0.05;
$fmt = static fn (float $v): string => number_format($v, 2, ',', '.');

/** @var list<array{empresa:int, nombre:string, asiento_id:int, excel_control:float}> $casos */
$casos = [
    [
        'empresa' => 2,
        'nombre' => 'Kandiko',
        'asiento_id' => 38255, // numeroasiento 222160 (ventas_medio_real)
        'excel_control' => 34889.39,
    ],
    [
        'empresa' => 3,
        'nombre' => 'Rebisco',
        'asiento_id' => 38258, // numeroasiento 226368 (ventas_medio_real)
        'excel_control' => 186076.74,
    ],
];

$errores = 0;

foreach ($casos as $caso) {
    $empresa = (int) $caso['empresa'];
    $asientoId = (int) $caso['asiento_id'];
    $excelControlCig = (float) $caso['excel_control'];
    $nombre = (string) $caso['nombre'];

    $cfg = CierreJornadaProcesoConfigSupport::paraEmpresaConDetalle($empresa);
    $idFood = (int) ($cfg['cuenta_ventas_id'] ?? 0);
    $idTabaco = (int) ($cfg['cuenta_ventas_kiosco_id'] ?? 0);
    $idIva = (int) ($cfg['cuenta_iva_id'] ?? 0);
    $idGolosinas = (int) DB::table('cuentacontable')
        ->where('empresa_id', $empresa)
        ->where('codigo', '414010001')
        ->value('id');

    echo "=== Corrección cigarrillos {$nombre} (emp {$empresa}) jornada {$fecha} ("
        .($apply ? 'APLICAR' : 'DRY-RUN').") ===\n";
    echo "Cuentas: food=id{$idFood} tabaco=id{$idTabaco} iva=id{$idIva} golosinas=id{$idGolosinas}\n\n";

    $d = CierreJornadaFacturadoAnitaSupport::datosAsientoVentasJornadaExclTotem($empresa, $fecha);
    $tgtGrav = round((float) $d['ventas_gravadas'], 2);
    $tgtKiosco = round((float) $d['ventas_kiosco'], 2);
    $tgtIvaN = round((float) $d['iva_normal'], 2);
    $tgtIvaC = round((float) $d['iva_cigarrillos'], 2);

    echo "Desglose correcto (ExclTotem):\n";
    echo "  ventas gravadas (413010001) : {$fmt($tgtGrav)}\n";
    echo "  ventas cigarrillos (414020001): {$fmt($tgtKiosco)}   [control contaduría {$fmt($excelControlCig)}, dif "
        .$fmt(round($tgtKiosco - $excelControlCig, 2))."]\n";
    echo "  iva débito normal            : {$fmt($tgtIvaN)}\n";
    echo "  iva débito cigarrillos       : {$fmt($tgtIvaC)}\n\n";

    $asiento = Asiento::with('asiento_movimientos.cuentacontables')->findOrFail($asientoId);

    $lineFood = null;
    $lineFoodExento = null;
    $lineKiosco = null;
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
        fwrite(STDERR, "No se pudieron clasificar las líneas esperadas del asiento #{$asientoId}. Abortando caso.\n\n");
        $errores++;
        continue;
    }

    usort($ivaLines, static fn ($a, $b) => abs((float) $b->monto) <=> abs((float) $a->monto));
    $lineIvaNormal = $ivaLines[0];
    $lineIvaCig = $ivaLines[1];
    $exentoFood = $lineFoodExento ? round((float) $lineFoodExento->monto, 2) : 0.0;
    $tgtFoodPrincipal = round(-1 * ($tgtGrav - abs($exentoFood)), 2);

    $cambios = [
        ['linea' => 'Ventas gravadas 413010001', 'mov' => $lineFood, 'monto_nuevo' => $tgtFoodPrincipal, 'cuenta_nueva' => $idFood],
        ['linea' => 'Ventas cigarrillos 414010001 -> 414020001', 'mov' => $lineKiosco, 'monto_nuevo' => round(-1 * $tgtKiosco, 2), 'cuenta_nueva' => $idTabaco],
        ['linea' => 'IVA débito normal 214010009', 'mov' => $lineIvaNormal, 'monto_nuevo' => round(-1 * $tgtIvaN, 2), 'cuenta_nueva' => $idIva],
        ['linea' => 'IVA débito cigarrillos 214010009', 'mov' => $lineIvaCig, 'monto_nuevo' => round(-1 * $tgtIvaC, 2), 'cuenta_nueva' => $idIva],
    ];

    echo "=== Líneas del asiento {$asiento->numeroasiento} (#{$asientoId}) ===\n";
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
    echo '  Balanceado y total invariante: '.($balanceOk ? 'SÍ' : 'NO ***')."\n";

    if (! $balanceOk) {
        fwrite(STDERR, "\nBalance inválido en {$nombre}: no se aplica este caso.\n\n");
        $errores++;
        continue;
    }

    if (! $apply) {
        echo "\nDRY-RUN: no se persistió nada para {$nombre}.\n\n";
        continue;
    }

    echo "\n=== APLICANDO {$nombre} ===\n";
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
    echo "ctamov Anita resincronizado para asiento {$asiento->numeroasiento}.\n\n";
}

if ($errores > 0) {
    exit(1);
}

if (! $apply) {
    echo "DRY-RUN OK. Ejecutar con 'apply' para grabar ERP + ctamov Anita.\n";
}
