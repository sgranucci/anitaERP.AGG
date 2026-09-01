<?php

/**
 * Compara, cliente por cliente, la alícuota de percepción IIBB que se aplicaba
 * antes (tasa fija de descarte de provincia_tasaiibb) contra la que corresponde
 * ahora (alícuota del padrón, con descarte solo si el CUIT no está).
 *
 * Solo lectura: no escribe nada. Sirve para dimensionar el cambio antes de
 * facturar.
 *
 * Uso: php scripts/impacto_percepcion_iibb_padron.php [YYYY-MM-DD]
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Configuracion\IIBBService;
use Illuminate\Support\Facades\DB;

$fecha = $argv[1] ?? date('Y-m-d');
$iibb = app(IIBBService::class);

$jurisdicciones = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) config('anita.agente_percepcion_iibb', ''))
)));

// ARBA (902) y CABA (901) ya resolvían por padrón antes de la corrección: para
// esas dos el comparativo es informativo, no un cambio de comportamiento.
$afectadasPorCorreccion = ['904', '908', '914', '921', '924'];

echo "Fecha de referencia: {$fecha}\n";
echo 'Jurisdicciones en las que la empresa percibe: ' . implode(', ', $jurisdicciones) . "\n\n";

printf(
    "%-16s %-6s %8s %8s %10s %8s %8s %8s  %s\n",
    'Provincia',
    'Juris',
    'clientes',
    'en padr',
    'descarte',
    'sube',
    'baja',
    'igual',
    'corrige'
);
echo str_repeat('-', 100), "\n";

$totalSube = $totalBaja = $totalSinPadron = 0;
$ejemplos = [];

foreach ($jurisdicciones as $jurisdiccion) {
    $provincia = DB::table('provincia')->where('jurisdiccion', $jurisdiccion)->first();
    if (! $provincia) {
        continue;
    }

    // Solo se percibe a clientes locales de la jurisdicción: son los únicos que
    // hoy reciben la tasa de descarte y por lo tanto los únicos que cambian.
    $clientes = DB::table('cliente')
        ->where('provincia_id', $provincia->id)
        ->where('estado', '1')
        ->whereNotNull('numerodocumento')
        ->select('id', 'nombre', 'numerodocumento', 'condicioniibb_id')
        ->get();

    $descartePorCondicion = DB::table('provincia_tasaiibb')
        ->where('provincia_id', $provincia->id)
        ->pluck('tasa', 'condicioniibb_id')
        ->all();

    $enPadron = $sube = $baja = $igual = $sinPadron = 0;

    foreach ($clientes as $cliente) {
        $descarte = (float) ($descartePorCondicion[$cliente->condicioniibb_id] ?? 0);

        $registro = $iibb->leeTasaPercepcion($cliente->numerodocumento, $jurisdiccion, $fecha);
        $tasaPadron = $iibb->tasaPercepcionDesdePadron($registro, $jurisdiccion);

        if ($tasaPadron === null) {
            $sinPadron++;

            continue;
        }

        $enPadron++;
        $diferencia = round($tasaPadron - $descarte, 4);

        if ($diferencia > 0) {
            $sube++;
        } elseif ($diferencia < 0) {
            $baja++;
        } else {
            $igual++;

            continue;
        }

        if (count($ejemplos) < 12 && in_array((string) $jurisdiccion, $afectadasPorCorreccion, true)) {
            $ejemplos[] = sprintf(
                '  %-12s %-11s %-32s antes %5s%% -> ahora %5s%%',
                $provincia->nombre,
                $cliente->numerodocumento,
                mb_substr((string) $cliente->nombre, 0, 32),
                number_format($descarte, 2, ',', ''),
                number_format($tasaPadron, 2, ',', '')
            );
        }
    }

    $corrige = in_array((string) $jurisdiccion, $afectadasPorCorreccion, true);

    printf(
        "%-16s %-6s %8d %8d %10d %8d %8d %8d  %s\n",
        mb_substr($provincia->nombre, 0, 16),
        $jurisdiccion,
        count($clientes),
        $enPadron,
        $sinPadron,
        $sube,
        $baja,
        $igual,
        $corrige ? 'SI' : 'ya usaba padron'
    );

    if ($corrige) {
        $totalSube += $sube;
        $totalBaja += $baja;
        $totalSinPadron += $sinPadron;
    }
}

echo str_repeat('-', 100), "\n";
printf(
    "Cambio real (solo columnas con corrige=SI): suben %d, bajan %d, sin padron y siguen con descarte %d\n",
    $totalSube,
    $totalBaja,
    $totalSinPadron
);

if ($ejemplos !== []) {
    echo "\nEjemplos del cambio:\n";
    echo implode("\n", $ejemplos), "\n";
}
