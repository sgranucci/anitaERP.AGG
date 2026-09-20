#!/usr/bin/env php
<?php

/**
 * Smoke test del circuito propuesta → OP (integridad post-fixes).
 *
 * SOLO corre si APP_ENV=testing y la base se llama *test* / *testing*.
 * No escribe en Anita si PAGOPROVEEDOR_ANITA_ESCRITURA_HABILITADA=false (recomendado).
 *
 * Uso en .211:
 *   cd /var/www/html/anitaERP
 *   php scripts/smoke-propuesta-pago.php              # dry: solo diagnostica
 *   php scripts/smoke-propuesta-pago.php --ejecutar    # ejecuta una AUTORIZADA si hay
 *
 * Qué valida al ejecutar:
 *   - lock/estado de propuesta
 *   - OP creada con asiento balanceado (si CONFIRMADA)
 *   - aplicaciones CC con saldo coherente
 *   - neto = bruto − retenciones (importe, no monto)
 */

use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\PropuestaPago;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Models\Contable\Asiento_Movimiento;
use App\Services\Compras\PropuestaPagoService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$ejecutar = in_array('--ejecutar', $argv, true);

$env = (string) config('app.env');
$db = (string) config('database.connections.'.config('database.default').'.database');
$host = gethostname();

echo "host={$host}  APP_ENV={$env}  DB={$db}\n";

if ($env !== 'testing' && $env !== 'local') {
    fwrite(STDERR, "ABORT: APP_ENV debe ser testing|local (ahora: {$env}).\n");
    exit(1);
}
if (! preg_match('/test/i', $db)) {
    fwrite(STDERR, "ABORT: la base debe contener 'test' en el nombre (ahora: {$db}).\n");
    exit(1);
}

try {
    DB::connection()->getPdo();
} catch (Throwable $e) {
    fwrite(STDERR, 'ABORT: MySQL no responde: '.$e->getMessage()."\n");
    exit(1);
}

$anitaOn = (bool) config('pagoproveedor.anita_escritura_habilitada', true);
echo 'Anita escritura OP: '.($anitaOn ? 'ON (ojo: puede pegarle a Anita real)' : 'OFF')."\n";

echo "\n=== propuestas por estado ===\n";
foreach (DB::table('propuesta_pago')->selectRaw('estado, count(*) c')->groupBy('estado')->get() as $r) {
    echo "  {$r->estado}: {$r->c}\n";
}

$candidata = PropuestaPago::query()
    ->where('estado', 'AUTORIZADA')
    ->whereHas('lineas', function ($q) {
        $q->where('incluido', true)
            ->where('monto_propuesto', '>', 0)
            ->where(function ($q2) {
                $q2->whereNull('pagoproveedor_id')->orWhere('pagoproveedor_id', 0);
            });
    })
    ->orderByDesc('id')
    ->first();

if ($candidata === null) {
    echo "\nNo hay propuesta AUTORIZADA con líneas pendientes.\n";
    echo "Armá una desde la UI (http://10.20.30.211) o cargá fixtures, y reejecutá con --ejecutar.\n";
    exit($ejecutar ? 2 : 0);
}

$pendientes = $candidata->lineas
    ->where('incluido', true)
    ->filter(fn ($l) => (float) $l->monto_propuesto > 0 && empty($l->pagoproveedor_id));

echo "\nCandidata: #{$candidata->id} empresa={$candidata->empresa_id} fecha={$candidata->fecha} líneas_pend=". $pendientes->count()."\n";

if (! $ejecutar) {
    echo "Dry-run OK. Para ejecutar: php scripts/smoke-propuesta-pago.php --ejecutar\n";
    exit(0);
}

echo "\n=== ejecutando PropuestaPagoService::ejecutar({$candidata->id}) ===\n";
/** @var PropuestaPagoService $svc */
$svc = app(PropuestaPagoService::class);
$resultado = $svc->ejecutar((int) $candidata->id);

echo 'ok='.(! empty($resultado['ok']) ? 'true' : 'false')."\n";
echo 'mensaje='.($resultado['mensaje'] ?? '')."\n";
$ops = $resultado['ops'] ?? [];
echo 'ops='.implode(',', $ops)."\n";

if (empty($resultado['ok']) || $ops === []) {
    fwrite(STDERR, "FAIL: ejecución no generó OPs.\n");
    exit(3);
}

$fallas = 0;
foreach ($ops as $opId) {
    $op = Pagoproveedor::query()->with(['pagoproveedor_retenciones', 'pagoproveedor_comprobantes'])->find((int) $opId);
    if ($op === null) {
        echo "FAIL OP #{$opId}: no existe\n";
        $fallas++;
        continue;
    }

    $ret = $op->totalRetenciones();
    $neto = $op->netoAPagar(4);
    $bruto = (float) $op->monto;
    echo "\nOP #{$op->id} nro={$op->numerotransaccion} estado={$op->estado} bruto={$bruto} ret={$ret} neto={$neto}\n";

    if (round($neto - max(0, $bruto - $ret), 4) !== 0.0) {
        echo "  FAIL neto inconsistente\n";
        $fallas++;
    }

    $apl = Proveedor_Cuentacorriente_Aplicacion::query()->where('pagoproveedor_id', $op->id)->count();
    echo "  aplicaciones_cc={$apl}\n";
    if ($op->pagoproveedor_comprobantes->isNotEmpty() && $apl < 1) {
        echo "  FAIL: tiene comprobantes pero no aplicaciones CC\n";
        $fallas++;
    }

    if ((string) $op->estado === 'CONFIRMADA') {
        $asientoId = (int) ($op->asiento_id ?? 0);
        echo "  asiento_id={$asientoId}\n";
        if ($asientoId <= 0) {
            echo "  FAIL: CONFIRMADA sin asiento\n";
            $fallas++;
        } else {
            $movs = Asiento_Movimiento::query()->where('asiento_id', $asientoId)->get();
            $debe = round($movs->sum(fn ($m) => (float) $m->debe), 2);
            $haber = round($movs->sum(fn ($m) => (float) $m->haber), 2);
            echo "  asiento debe={$debe} haber={$haber}\n";
            if (abs($debe - $haber) > 0.02) {
                echo "  FAIL: asiento desbalanceado\n";
                $fallas++;
            }
        }
    }
}

$prop = PropuestaPago::query()->find((int) $candidata->id);
echo "\npropuesta estado final={$prop?->estado}\n";

if ($fallas > 0) {
    fwrite(STDERR, "SMOKE FAIL: {$fallas} chequeo(s) fallaron.\n");
    exit(4);
}

echo "\nSMOKE OK\n";
exit(0);
