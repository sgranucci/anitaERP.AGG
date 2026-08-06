<?php

/**
 * Parseo en seco de un padrón IIBB: mide velocidad de lectura y muestra las
 * primeras filas normalizadas. No escribe nada en la base.
 *
 * Uso: php scripts/prueba_padron_parseo_seco.php <jurisdiccion> <archivo> [tipoTucuman]
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\Configuracion\PadronIibb\PadronIibbArchivoSupport;
use App\Support\Configuracion\PadronIibb\PadronIibbParserFactory;

$jurisdiccion = (int) ($argv[1] ?? 0);
$ruta = $argv[2] ?? '';
$tipo = $argv[3] ?? null;

if ($jurisdiccion === 0 || $ruta === '') {
    fwrite(STDERR, "Uso: php scripts/prueba_padron_parseo_seco.php <jurisdiccion> <archivo> [tipoTucuman]\n");
    exit(1);
}

$parser = PadronIibbParserFactory::crear($jurisdiccion, $tipo);
$archivo = PadronIibbArchivoSupport::resolver($ruta, ['csv', 'txt']);

echo $parser->etiqueta(), ' — ', basename($archivo), "\n";

$fp = fopen($archivo, 'rb');
$inicio = microtime(true);
$leidas = $omitidas = 0;
$muestra = [];
$periodos = [];

while (($raw = fgets($fp)) !== false) {
    $linea = $parser->parseLinea($raw);

    if ($linea === null) {
        $omitidas++;

        continue;
    }

    $leidas++;
    $periodos[$linea->periodo()] = ($periodos[$linea->periodo()] ?? 0) + 1;

    if (count($muestra) < 3) {
        $muestra[] = $linea;
    }
}

fclose($fp);
$segundos = microtime(true) - $inicio;

foreach ($muestra as $linea) {
    printf(
        "  cuit=%s per=%s ret=%s lado=%s tipo=%s vig=%s a %s nombre=%s\n",
        $linea->cuit,
        $linea->tasapercepcion ?? '-',
        $linea->tasaretencion ?? '-',
        $linea->lado,
        $linea->tipocontribuyente ?? '-',
        $linea->desdefecha,
        $linea->hastafecha,
        $linea->nombre ?? '-'
    );
}

echo "\n  periodos detectados:\n";
arsort($periodos);
foreach (array_slice($periodos, 0, 5, true) as $periodo => $cantidad) {
    printf("    %s → %s filas\n", $periodo, number_format($cantidad, 0, ',', '.'));
}

printf(
    "\n  validas=%s omitidas=%s en %.1fs (%s filas/s)\n",
    number_format($leidas, 0, ',', '.'),
    number_format($omitidas, 0, ',', '.'),
    $segundos,
    number_format($segundos > 0 ? $leidas / $segundos : 0, 0, ',', '.')
);

PadronIibbArchivoSupport::limpiarTemporal($archivo);
