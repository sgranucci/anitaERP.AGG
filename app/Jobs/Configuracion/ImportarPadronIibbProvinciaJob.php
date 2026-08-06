<?php

declare(strict_types=1);

namespace App\Jobs\Configuracion;

use App\Services\Configuracion\PadronIibbTasaCargaService;
use App\Services\Configuracion\PadronIibbTucumanCoeficienteCargaService;
use App\Support\Configuracion\PadronIibbCargaNotificacionSupport;
use App\Support\Configuracion\PadronIibbCargaRegistroSupport;
use App\Support\Configuracion\PadronIibb\PadronIibbParserFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Importa en background el padrón IIBB de las provincias que cargan contra
 * padron_iibb_tasa (Córdoba, Entre Ríos, Misiones y Tucumán).
 */
class ImportarPadronIibbProvinciaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public function __construct(
        public readonly string $archivo,
        public readonly int $provinciaId,
        public readonly int $jurisdiccion,
        public readonly ?string $tipoPadron = null,
        public readonly ?int $cargaId = null,
        public readonly int $batchSize = PadronIibbTasaCargaService::DEFAULT_BATCH,
        public readonly int $pauseMs = PadronIibbTasaCargaService::DEFAULT_PAUSE_MS,
        public readonly bool $keepPeriod = false,
        public readonly bool $borrarArchivoAlTerminar = false,
    ) {
        $this->timeout = max(600, (int) config('padrones_iibb.job_timeout', 7200));
        $this->onQueue((string) config('padrones_iibb.cola', 'padrones'));
    }

    public function handle(
        PadronIibbTasaCargaService $tasaService,
        PadronIibbTucumanCoeficienteCargaService $coeficienteService,
    ): void {
        Log::info('ImportarPadronIibbProvinciaJob:inicio', [
            'archivo' => $this->archivo,
            'provincia_id' => $this->provinciaId,
            'jurisdiccion' => $this->jurisdiccion,
            'tipopadron' => $this->tipoPadron,
        ]);

        try {
            $stats = $this->ejecutar($tasaService, $coeficienteService);

            PadronIibbCargaRegistroSupport::finalizar($this->cargaId, $stats);
            Log::info('ImportarPadronIibbProvinciaJob:ok', $stats);

            PadronIibbCargaNotificacionSupport::notificar(
                true,
                (string) $stats['etiqueta'],
                $this->resumen($stats),
                $this->archivo,
                $this->statsMail($stats)
            );
        } finally {
            $this->limpiarArchivoSiCorresponde();
        }
    }

    public function failed(?Throwable $e): void
    {
        $error = $e?->getMessage() ?? 'Error desconocido';

        Log::error('ImportarPadronIibbProvinciaJob:failed', [
            'archivo' => $this->archivo,
            'jurisdiccion' => $this->jurisdiccion,
            'error' => $error,
        ]);

        PadronIibbCargaRegistroSupport::fallar($this->cargaId, $error);
        PadronIibbCargaNotificacionSupport::notificar(
            false,
            'Padrón IIBB jurisdicción ' . $this->jurisdiccion,
            'La importación falló.',
            $this->archivo,
            [],
            $error
        );

        $this->limpiarArchivoSiCorresponde();
    }

    /**
     * @return array<string,mixed>
     */
    private function ejecutar(
        PadronIibbTasaCargaService $tasaService,
        PadronIibbTucumanCoeficienteCargaService $coeficienteService,
    ): array {
        $onProgreso = fn (array $stats) => PadronIibbCargaRegistroSupport::progreso($this->cargaId, $stats);

        if (PadronIibbParserFactory::esTucumanCoeficientes($this->jurisdiccion, $this->tipoPadron)) {
            return $coeficienteService->cargar(
                $this->archivo,
                PadronIibbTucumanCoeficienteCargaService::DEFAULT_BATCH,
                $this->pauseMs,
                $onProgreso
            );
        }

        return $tasaService->cargar(
            $this->archivo,
            $this->provinciaId,
            PadronIibbParserFactory::crear($this->jurisdiccion, $this->tipoPadron),
            $this->batchSize,
            $this->pauseMs,
            $this->keepPeriod,
            $onProgreso
        );
    }

    /**
     * @param  array<string,mixed>  $stats
     */
    private function resumen(array $stats): string
    {
        $periodo = $stats['desdefecha'] ?? null;

        return sprintf(
            'Importación %s OK. %s. %d registros en %s segundos.',
            $stats['etiqueta'] ?? 'padrón IIBB',
            $periodo !== null
                ? 'Período ' . $periodo . ' → ' . ($stats['hastafecha'] ?? '?')
                : 'Padrón completo reemplazado',
            (int) ($stats['insertadas_tasa'] ?? $stats['insertadas'] ?? 0),
            (string) ($stats['segundos'] ?? '?')
        );
    }

    /**
     * @param  array<string,mixed>  $stats
     * @return array<string,mixed>
     */
    private function statsMail(array $stats): array
    {
        return array_filter([
            'leidas' => $stats['leidas'] ?? null,
            'insertadas' => $stats['insertadas_tasa'] ?? $stats['insertadas'] ?? null,
            'actualizadas' => $stats['actualizadas_tasa'] ?? null,
            'cuits_nuevos' => $stats['insertadas_cuit'] ?? null,
            'nombres_completados' => $stats['nombres_actualizados'] ?? null,
            'borrados' => $stats['borrados'] ?? null,
            'omitidas' => $stats['omitidas'] ?? null,
            'errores' => $stats['errores'] ?? null,
            'segundos' => $stats['segundos'] ?? null,
        ], static fn ($valor) => $valor !== null);
    }

    private function limpiarArchivoSiCorresponde(): void
    {
        if (! $this->borrarArchivoAlTerminar) {
            return;
        }

        $raizStorage = realpath(storage_path('app')) ?: storage_path('app');
        $real = realpath($this->archivo);

        if ($real && str_starts_with($real, rtrim($raizStorage, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            @unlink($real);
        }
    }
}
