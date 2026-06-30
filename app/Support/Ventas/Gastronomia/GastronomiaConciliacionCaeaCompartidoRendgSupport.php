<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\Models\Caja\Estacionamiento\ConfiguracionPuntoventaEstacionamiento;
use App\Models\Caja\Estacionamiento\VentaEstacionamientoEmision;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use Carbon\Carbon;

/**
 * PV CAEA compartido entre gastronomía y estacionamiento: atribución rendgastro por PC originadora.
 *
 * Facturas CAEA no post-cierre Waitry deben sumar al Z / rendg_tot_fc_caea de la PC que emitió
 * (host estacionamiento o salón), no inflar el Z de otra terminal del salón.
 */
final class GastronomiaConciliacionCaeaCompartidoRendgSupport
{
    /**
     * Facturación bruta estacionamiento en PV CAEA compartido, excluyendo la PC indicada.
     */
    public function totalEstacionamientoEnPuntoventaCaeaExcluyendoHost(
        int $empresaId,
        string $fechaJornada,
        int $puntoventaCaeaId,
        string $excluirIdentificadorPc,
    ): float {
        if ($empresaId <= 0 || $puntoventaCaeaId <= 0) {
            return 0.0;
        }

        $excluir = trim($excluirIdentificadorPc);
        if ($excluir === '') {
            return 0.0;
        }

        return $this->totalEstacionamientoEnPuntoventaCaea(
            $empresaId,
            $fechaJornada,
            $puntoventaCaeaId,
            null,
            $excluir,
        );
    }

    /**
     * Facturación bruta estacionamiento en PV CAEA compartido para una PC (o todas si $soloHost null).
     */
    public function totalEstacionamientoEnPuntoventaCaea(
        int $empresaId,
        string $fechaJornada,
        int $puntoventaCaeaId,
        ?string $soloIdentificadorPc = null,
        ?string $excluirIdentificadorPc = null,
    ): float {
        if ($empresaId <= 0 || $puntoventaCaeaId <= 0) {
            return 0.0;
        }

        $fechaJornada = Carbon::parse($fechaJornada)->toDateString();

        $query = VentaEstacionamientoEmision::query()
            ->whereNull('venta_estacionamiento_emision.venta_factura_origen_id')
            ->join('venta', 'venta.id', '=', 'venta_estacionamiento_emision.venta_id')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->where('venta.puntoventa_id', $puntoventaCaeaId)
            ->where('puntoventa.empresa_id', $empresaId)
            ->where(function ($fecha) use ($fechaJornada) {
                $fecha->whereDate('venta.fechajornada', $fechaJornada)
                    ->orWhere(function ($legacy) use ($fechaJornada) {
                        $legacy->whereNull('venta.fechajornada')
                            ->whereDate('venta.fecha', $fechaJornada);
                    });
            });

        $solo = trim((string) $soloIdentificadorPc);
        if ($solo !== '') {
            $query->where('venta_estacionamiento_emision.identificador_pc', $solo);
        }

        $excluir = trim((string) $excluirIdentificadorPc);
        if ($excluir !== '') {
            $query->where('venta_estacionamiento_emision.identificador_pc', '!=', $excluir);
        }

        $total = (float) ($query->sum('venta.total') ?? 0);

        return round($total, 2);
    }

    public function puntoventaCaeaIdConfigGastronomia(int $empresaId, string $identificadorPc): ?int
    {
        $host = trim($identificadorPc);
        if ($host === '' || $empresaId <= 0) {
            return null;
        }

        $id = ConfiguracionPuntoventaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->where('identificador_pc', $host)
            ->value('puntoventa_caea_id');

        $id = (int) ($id ?? 0);

        return $id > 0 ? $id : null;
    }

    /**
     * @return list<string>
     */
    public function hostsEstacionamientoConPuntoventaCaea(int $empresaId, int $puntoventaCaeaId): array
    {
        if ($empresaId <= 0 || $puntoventaCaeaId <= 0) {
            return [];
        }

        return ConfiguracionPuntoventaEstacionamiento::query()
            ->where('empresa_id', $empresaId)
            ->where('puntoventa_caea_id', $puntoventaCaeaId)
            ->pluck('identificador_pc')
            ->map(static fn ($pc): string => trim((string) $pc))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Ajusta total rendg salón cuando el Z de la portadora arrastró CAEA de estacionamiento ajena.
     */
    public function ajustarTotalBrutoGastroExcluyendoCaeaEstacionamientoAjena(
        int $empresaId,
        string $fechaJornada,
        string $identificadorPc,
        float $totalRendg,
        float $zPortadora,
        float $erpCae,
        float $erpCaea,
        float $tolerancia = 0.02,
    ): float {
        $puntoventaCaeaId = $this->puntoventaCaeaIdConfigGastronomia($empresaId, $identificadorPc);
        if ($puntoventaCaeaId === null) {
            return $totalRendg;
        }

        $ajeno = $this->totalEstacionamientoEnPuntoventaCaeaExcluyendoHost(
            $empresaId,
            $fechaJornada,
            $puntoventaCaeaId,
            $identificadorPc,
        );

        return $this->ajustarTotalSiArrastraCaeaAjena(
            $totalRendg,
            $zPortadora,
            $erpCae,
            $erpCaea,
            $ajeno,
            $tolerancia,
        );
    }

    /**
     * Lógica pura: si el Z incluye CAEA de estacionamiento de otra PC, devolver el bruto ERP salón.
     */
    public function ajustarTotalSiArrastraCaeaAjena(
        float $totalRendg,
        float $zPortadora,
        float $erpCae,
        float $erpCaea,
        float $montoAjeno,
        float $tolerancia = 0.02,
    ): float {
        if ($montoAjeno <= $tolerancia) {
            return $totalRendg;
        }

        $erpTotal = round($erpCae + $erpCaea, 2);
        if (abs($totalRendg - $erpTotal) <= $tolerancia) {
            return $totalRendg;
        }

        if (abs($totalRendg - $montoAjeno - $erpTotal) <= $tolerancia) {
            return $erpTotal;
        }

        if (abs($zPortadora - $montoAjeno - $erpTotal) <= $tolerancia) {
            return $erpTotal;
        }

        return $totalRendg;
    }
}
