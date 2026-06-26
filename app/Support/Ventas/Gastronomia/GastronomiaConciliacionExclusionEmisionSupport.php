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
 */
final class GastronomiaConciliacionExclusionEmisionSupport
{
    /** @var array<string, list<string>> */
    private array $clavesExcluirCache = [];

    public function __construct(
        private readonly GastronomiaChequeoVentasAnitaErpService $chequeoVentasService,
    ) {
    }

    /**
     * @return list<string> claves FAC|letra|sucursal|nro
     */
    public function clavesExcluirConciliacion(
        int $empresaId,
        string $fechaJornada,
        ?array $indiceAnitaBulk = null,
    ): array {
        $cacheKey = $empresaId.':'.$fechaJornada.':'.($indiceAnitaBulk !== null ? 'bulk' : 'live');
        if (isset($this->clavesExcluirCache[$cacheKey])) {
            return $this->clavesExcluirCache[$cacheKey];
        }

        $claves = [];

        foreach ($this->clavesVentasEstacionamientoSinGastronomia($empresaId, $fechaJornada) as $clave) {
            $claves[$clave] = true;
        }

        foreach ($this->clavesAnitaSinParGastronomiaEnPvCaea($empresaId, $fechaJornada, $indiceAnitaBulk) as $clave) {
            $claves[$clave] = true;
        }

        return $this->clavesExcluirCache[$cacheKey] = array_keys($claves);
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
     * @return list<string>
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

        $claves = [];
        foreach ($ventas as $venta) {
            $clave = $this->chequeoVentasService->claveComprobanteDesdeVentaErp($venta);
            if ($clave !== null) {
                $claves[] = $clave;
            }
        }

        return $claves;
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
