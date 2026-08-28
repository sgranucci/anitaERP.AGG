<?php

namespace App\Support\Ventas;

use Illuminate\Support\Facades\Log;

/**
 * Marcadores de tiempo para factura de pedido / remito administrativo (El Bierzo).
 * Opt-in vía facturacion.pedido_emision_profile. Con live=true cada paso se escribe
 * al toque (sobrevive un request colgado, a diferencia del resumen de cierre).
 */
final class PedidoFacturacionProfiler
{
    private static ?self $instancia = null;

    /** @var list<array{etapa:string,ms:float,acum_ms:float}> */
    private array $marcas = [];

    private float $inicio;

    /** @var array<string, mixed> */
    private array $contexto = [];

    private function __construct()
    {
        $this->inicio = microtime(true);
    }

    public static function iniciarSiConfigurado(array $contexto = []): ?self
    {
        if (! filter_var(config('facturacion.pedido_emision_profile', false), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        self::$instancia = new self();
        self::$instancia->contexto = $contexto;
        self::$instancia->marcar('inicio');

        return self::$instancia;
    }

    public static function activo(): ?self
    {
        return self::$instancia;
    }

    /**
     * Marca el profiler de pedido y, si hay uno de gastronomía, el de POS.
     * Usar en FacturacionService compartido.
     */
    public static function etapa(string $etapa): void
    {
        self::activo()?->marcar($etapa);
        GastronomiaEmisionProfiler::activo()?->marcar($etapa);
    }

    public function marcar(string $etapa): void
    {
        $ahora = microtime(true);
        $marca = [
            'etapa' => $etapa,
            'ms' => round(($ahora - ($this->ultimoTimestamp() ?? $this->inicio)) * 1000, 2),
            'acum_ms' => round(($ahora - $this->inicio) * 1000, 2),
        ];
        $this->marcas[] = $marca;

        if (filter_var(config('facturacion.pedido_emision_profile_live', true), FILTER_VALIDATE_BOOLEAN)) {
            Log::info('facturacion.pedido.paso', array_merge($this->contexto, $marca));
        }
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
        $payload = array_merge($this->contexto, $contexto, [
            'total_ms' => $this->totalMs(),
            'etapas' => $this->resumen(),
        ]);

        Log::info('facturacion.pedido.profile', $payload);

        $umbralMs = max(0, (int) config('facturacion.pedido_emision_umbral_advertencia_ms', 5000));
        if ($umbralMs > 0 && $this->totalMs() >= $umbralMs) {
            Log::warning('facturacion.pedido.lento', array_merge($this->contexto, $contexto, [
                'total_ms' => $this->totalMs(),
                'umbral_ms' => $umbralMs,
                'etapas_lentas' => $this->etapasMasLentas(5),
            ]));
        }
    }

    /**
     * @return list<array{etapa:string,ms:float,acum_ms:float}>
     */
    public function etapasMasLentas(int $limite = 5): array
    {
        if ($this->marcas === []) {
            return [];
        }

        $etapas = $this->marcas;
        usort($etapas, static fn (array $a, array $b): int => ($b['ms'] <=> $a['ms']));

        return array_slice($etapas, 0, max(1, $limite));
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
