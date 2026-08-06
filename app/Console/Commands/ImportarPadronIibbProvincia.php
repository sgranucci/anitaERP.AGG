<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Configuracion\ImportarPadronIibbProvinciaJob;
use App\Services\Configuracion\PadronIibbTasaCargaService;
use App\Services\Configuracion\PadronIibbTucumanCoeficienteCargaService;
use App\Support\Configuracion\PadronIibbCargaNotificacionSupport;
use App\Support\Configuracion\PadronIibbCargaRegistroSupport;
use App\Support\Configuracion\PadronIibb\PadronIibbParserFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportarPadronIibbProvincia extends Command
{
    protected $signature = 'padron-iibb:import
                            {archivo : Ruta absoluta al CSV, TXT o ZIP del padrón}
                            {--jurisdiccion= : Jurisdicción IIBB (904 Córdoba, 908 Entre Ríos, 914 Misiones, 924 Tucumán)}
                            {--tipo= : Solo Tucumán: T tasas, C coeficientes}
                            {--batch=3000 : Filas por lote}
                            {--pause-ms=20 : Pausa entre lotes (ms)}
                            {--keep-period : No reemplazar la carga anterior}
                            {--encolar : Encolar en background en lugar de procesar acá}';

    protected $description = 'Importa el padrón IIBB de Córdoba, Entre Ríos, Misiones o Tucumán';

    public function handle(
        PadronIibbTasaCargaService $tasaService,
        PadronIibbTucumanCoeficienteCargaService $coeficienteService,
    ): int {
        $archivo = $this->resolverArchivo((string) $this->argument('archivo'));
        if ($archivo === null) {
            return self::FAILURE;
        }

        $jurisdiccion = (int) $this->option('jurisdiccion');
        if (! PadronIibbParserFactory::soporta($jurisdiccion)) {
            $this->error(
                'Indique --jurisdiccion con uno de estos valores: '
                . implode(', ', PadronIibbParserFactory::jurisdiccionesSoportadas())
            );

            return self::FAILURE;
        }

        $tipoPadron = $this->resolverTipoPadron($jurisdiccion);
        if ($tipoPadron === false) {
            return self::FAILURE;
        }

        $provincia = DB::table('provincia')->where('jurisdiccion', (string) $jurisdiccion)->first();
        if ($provincia === null) {
            $this->error("No hay provincia con jurisdicción {$jurisdiccion}.");

            return self::FAILURE;
        }

        $esCoeficientes = PadronIibbParserFactory::esTucumanCoeficientes($jurisdiccion, $tipoPadron);
        $etiqueta = $esCoeficientes
            ? 'IIBB Tucumán (coeficientes)'
            : PadronIibbParserFactory::crear($jurisdiccion, $tipoPadron)->etiqueta();

        $cargaId = PadronIibbCargaRegistroSupport::iniciar([
            'provincia_id' => (int) $provincia->id,
            'jurisdiccion' => $jurisdiccion,
            'etiqueta' => $etiqueta,
            'tipopadron' => $tipoPadron,
            'origen' => PadronIibbCargaRegistroSupport::ORIGEN_CONSOLA,
            'archivo' => $archivo,
        ]);

        if ((bool) $this->option('encolar')) {
            ImportarPadronIibbProvinciaJob::dispatch(
                $archivo,
                (int) $provincia->id,
                $jurisdiccion,
                $tipoPadron,
                $cargaId,
                (int) $this->option('batch'),
                (int) $this->option('pause-ms'),
                (bool) $this->option('keep-period'),
            );

            $this->info("Importación {$etiqueta} encolada en la cola '" . config('padrones_iibb.cola') . "'.");

            return self::SUCCESS;
        }

        $this->info("Importando {$etiqueta} desde {$archivo}");

        try {
            $stats = $esCoeficientes
                ? $coeficienteService->cargar(
                    $archivo,
                    (int) $this->option('batch'),
                    (int) $this->option('pause-ms'),
                    fn (array $s) => PadronIibbCargaRegistroSupport::progreso($cargaId, $s)
                )
                : $tasaService->cargar(
                    $archivo,
                    (int) $provincia->id,
                    PadronIibbParserFactory::crear($jurisdiccion, $tipoPadron),
                    (int) $this->option('batch'),
                    (int) $this->option('pause-ms'),
                    (bool) $this->option('keep-period'),
                    fn (array $s) => PadronIibbCargaRegistroSupport::progreso($cargaId, $s)
                );
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            PadronIibbCargaRegistroSupport::fallar($cargaId, $e->getMessage());
            PadronIibbCargaNotificacionSupport::notificar(
                false,
                $etiqueta,
                'Falló la importación desde consola.',
                $archivo,
                [],
                $e->getMessage()
            );

            return self::FAILURE;
        }

        PadronIibbCargaRegistroSupport::finalizar($cargaId, $stats);
        $this->mostrarResumen($stats);

        return self::SUCCESS;
    }

    private function resolverArchivo(string $archivo): ?string
    {
        if ($archivo !== '' && $archivo[0] !== '/') {
            $archivo = base_path($archivo);
        }

        if (! is_file($archivo) || ! is_readable($archivo)) {
            $this->error("No existe o no se puede leer el archivo: {$archivo}");

            return null;
        }

        return $archivo;
    }

    /** @return string|null|false false = error de validación */
    private function resolverTipoPadron(int $jurisdiccion): string|null|false
    {
        if ($jurisdiccion !== PadronIibbParserFactory::JURISDICCION_TUCUMAN) {
            return null;
        }

        $tipo = strtoupper(trim((string) $this->option('tipo')));
        if (! in_array($tipo, [PadronIibbParserFactory::TUCUMAN_TASAS, PadronIibbParserFactory::TUCUMAN_COEFICIENTES], true)) {
            $this->error('Para Tucumán indique --tipo=T (tasas) o --tipo=C (coeficientes).');

            return false;
        }

        return $tipo;
    }

    /**
     * @param  array<string,mixed>  $stats
     */
    private function mostrarResumen(array $stats): void
    {
        $this->info(sprintf(
            'OK %s — %s',
            $stats['etiqueta'] ?? '',
            $stats['desdefecha'] !== null
                ? 'período ' . $stats['desdefecha'] . ' a ' . $stats['hastafecha']
                : 'padrón completo'
        ));

        $this->table(['Métrica', 'Valor'], [
            ['Líneas leídas', $stats['leidas'] ?? 0],
            ['Tasas insertadas', $stats['insertadas_tasa'] ?? $stats['insertadas'] ?? 0],
            ['Tasas actualizadas', $stats['actualizadas_tasa'] ?? 0],
            ['CUIT nuevos', $stats['insertadas_cuit'] ?? 0],
            ['Nombres completados', $stats['nombres_actualizados'] ?? 0],
            ['Filas reemplazadas', $stats['borrados'] ?? 0],
            ['Líneas omitidas', $stats['omitidas'] ?? 0],
            ['Errores', $stats['errores'] ?? 0],
            ['Segundos', $stats['segundos'] ?? 0],
        ]);
    }
}
