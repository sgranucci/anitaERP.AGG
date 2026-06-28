<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Services\Ventas\Gastronomia\GastronomiaChequeoVentasAnitaErpService;

/**
 * Excluye del reporte de conciliación gastronomía facturas de estacionamiento
 * u otros circuitos que comparten PV CAEA pero no tienen emisión gastronomía.
 *
 * Las exclusiones son por puntoventa_id: una clave huérfana en PV CAEA 00020
 * no debe ocultar la misma numeración en PV CAE 00070.
 */
final class GastronomiaConciliacionExclusionEmisionSupport
{
    /** @var array<string, array<int, list<string>>> */
    private array $clavesPorPuntoventaCache = [];

    public function __construct(
        private readonly GastronomiaChequeoVentasAnitaErpService $chequeoVentasService,
    ) {
    }

    /**
     * @return list<string> claves tipo|letra|sucursal|nro (legacy: unión global — evitar en conciliación por PC)
     */
    public function clavesExcluirConciliacion(
        int $empresaId,
        string $fechaJornada,
        ?array $indiceAnitaBulk = null,
    ): array {
        $porPv = $this->clavesExcluirPorPuntoventa($empresaId, $fechaJornada, $indiceAnitaBulk);
        $claves = [];
        foreach ($porPv as $lista) {
            foreach ($lista as $clave) {
                $claves[$clave] = true;
            }
        }

        return array_keys($claves);
    }

    /**
     * @return array<int, list<string>> claves a excluir indexadas por puntoventa_id ERP
     */
    public function clavesExcluirPorPuntoventa(
        int $empresaId,
        string $fechaJornada,
        ?array $indiceAnitaBulk = null,
    ): array {
        $cacheKey = $empresaId.':'.$fechaJornada.':'.($indiceAnitaBulk !== null ? 'bulk' : 'live');
        if (isset($this->clavesPorPuntoventaCache[$cacheKey])) {
            return $this->clavesPorPuntoventaCache[$cacheKey];
        }

        $porPv = [];

        foreach ($this->clavesVentasEstacionamientoSinGastronomia($empresaId, $fechaJornada) as $pvId => $claves) {
            foreach ($claves as $clave) {
                $porPv[$pvId][$clave] = true;
            }
        }

        $resultado = [];
        foreach ($porPv as $pvId => $set) {
            $resultado[(int) $pvId] = array_keys($set);
        }

        return $this->clavesPorPuntoventaCache[$cacheKey] = $resultado;
    }

    /**
     * @return list<string>
     */
    public function clavesExcluirListaParaPuntoventa(
        int $empresaId,
        string $fechaJornada,
        int $puntoventaId,
        ?array $indiceAnitaBulk = null,
    ): array {
        if ($puntoventaId <= 0) {
            return [];
        }

        return $this->clavesExcluirPorPuntoventa($empresaId, $fechaJornada, $indiceAnitaBulk)[$puntoventaId] ?? [];
    }

    /**
     * @param  list<int>  $ventaIds
     * @return list<int>
     */
    public function filtrarVentaIdsSoloGastronomia(array $ventaIds): array
    {
        if ($ventaIds === []) {
            return [];
        }

        return VentaGastronomiaEmision::query()
            ->whereIn('venta_id', $ventaIds)
            ->whereNull('venta_factura_origen_id')
            ->pluck('venta_id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<int, list<string>>
     */
    private function clavesVentasEstacionamientoSinGastronomia(int $empresaId, string $fechaJornada): array
    {
        $ventas = Venta::query()
            ->whereHas('estacionamientoEmision')
            ->whereDoesntHave('gastronomiaEmision')
            ->where(function ($fecha) use ($fechaJornada) {
                $fecha->whereDate('fechajornada', $fechaJornada)
                    ->orWhere(function ($legacy) use ($fechaJornada) {
                        $legacy->whereNull('fechajornada')
                            ->whereDate('fecha', $fechaJornada);
                    });
            })
            ->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId))
            ->get(['id', 'codigo', 'numerocomprobante', 'puntoventa_id']);

        $porPv = [];
        foreach ($ventas as $venta) {
            $clave = $this->chequeoVentasService->claveComprobanteDesdeVentaErp($venta);
            $pvId = (int) ($venta->puntoventa_id ?? 0);
            if ($clave !== null && $pvId > 0) {
                $porPv[$pvId][] = $clave;
            }
        }

        return $porPv;
    }

    /**
     * Cabeceras Anita en PV CAEA compartido sin venta gastronomía en ERP (legacy / cortesías).
     *
     * @return list<string>
     */
    private function clavesAnitaSinParGastronomiaEnPvCaea(
        int $empresaId,
        string $fechaJornada,
        ?array $indiceAnitaBulk = null,
    ): array {
        $pvCaea = $this->puntoventaCaeaEmpresa($empresaId);
        if ($pvCaea === null) {
            return [];
        }

        $pvCaeaId = (int) $pvCaea->id;
        $mapAnita = $this->chequeoVentasService->cabecerasAnitaMapPorPuntoventa(
            $pvCaeaId,
            $fechaJornada,
            [],
            $indiceAnitaBulk,
        );

        $clavesGastroErp = Venta::query()
            ->where('puntoventa_id', $pvCaeaId)
            ->whereHas('gastronomiaEmision', fn ($q) => $q->whereNull('venta_factura_origen_id'))
            ->where(function ($fecha) use ($fechaJornada) {
                $fecha->whereDate('fechajornada', $fechaJornada)
                    ->orWhere(function ($legacy) use ($fechaJornada) {
                        $legacy->whereNull('fechajornada')
                            ->whereDate('fecha', $fechaJornada);
                    });
            })
            ->get(['id', 'codigo', 'numerocomprobante', 'puntoventa_id']);

        $clavesErp = [];
        foreach ($clavesGastroErp as $venta) {
            $clave = $this->chequeoVentasService->claveComprobanteDesdeVentaErp($venta);
            if ($clave !== null) {
                $clavesErp[$clave] = true;
            }
        }

        $excluir = [];
        foreach (array_keys($mapAnita) as $clave) {
            if (! isset($clavesErp[$clave])) {
                $excluir[] = $clave;
            }
        }

        return $excluir;
    }

    private function puntoventaCaeaEmpresa(int $empresaId): ?Puntoventa
    {
        $caeaId = ConfiguracionPuntoventaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereNotNull('puntoventa_caea_id')
            ->value('puntoventa_caea_id');

        if ($caeaId === null || (int) $caeaId <= 0) {
            return null;
        }

        return Puntoventa::query()->find((int) $caeaId);
    }
}
