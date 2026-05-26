<?php

namespace App\Support\Ventas;

use Illuminate\Support\Facades\Log;

/**
 * Marcadores de tiempo para emisión gastronomía (opt-in vía config gastronomia.emision_profile).
 */
final class GastronomiaEmisionProfiler
{
    private static ?self $instancia = null;

    /** @var list<array{etapa:string,ms:float,acum_ms:float}> */
    private array $marcas = [];

    private float $inicio;

    private function __construct()
    {
        $this->inicio = microtime(true);
    }

    public static function iniciarSiConfigurado(): ?self
    {
        if (! filter_var(config('gastronomia.emision_profile', false), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        self::$instancia = new self();
        self::$instancia->marcar('inicio');

        return self::$instancia;
    }

    public static function activo(): ?self
    {
        return self::$instancia;
    }

    public function marcar(string $etapa): void
    {
        $ahora = microtime(true);
        $this->marcas[] = [
            'etapa' => $etapa,
            'ms' => round(($ahora - ($this->ultimoTimestamp() ?? $this->inicio)) * 1000, 2),
            'acum_ms' => round(($ahora - $this->inicio) * 1000, 2),
        ];
    }

    /**
     * @return list<array{etapa:string,ms:float,acum_ms:float}>
     */
    public function resumen(): array
    {
        return $this->marcas;
    }

    public function totalMs(): float
    {
        if ($this->marcas === []) {
            return 0.;
        }

        return (float) end($this->marcas)['acum_ms'];
    }

    public function registrarLog(array $contexto = []): void
    {
        Log::info('gastronomia.emision.profile', array_merge($contexto, [
            'total_ms' => $this->totalMs(),
            'etapas' => $this->resumen(),
        ]));
    }

    public static function finalizar(?self $profiler, array $contexto = []): ?array
    {
        if ($profiler === null) {
            return null;
        }

        $profiler->marcar('fin');
        $profiler->registrarLog($contexto);
        $resumen = $profiler->resumen();
        self::$instancia = null;

        return $resumen;
    }

    private function ultimoTimestamp(): ?float
    {
        if ($this->marcas === []) {
            return null;
        }

        $acumMs = (float) end($this->marcas)['acum_ms'];

        return $this->inicio + ($acumMs / 1000);
    }
}
