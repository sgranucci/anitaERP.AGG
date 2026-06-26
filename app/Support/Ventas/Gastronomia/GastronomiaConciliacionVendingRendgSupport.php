<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\Models\Ventas\Puntoventa;
use App\Support\Caja\AnitaSync\RendicionGastronomiaAnitaRendgastroSupport;

/**
 * Rendiciones rendgastro de máquinas vending (PV manual Maquina N, host vacío en Informix).
 */
final class GastronomiaConciliacionVendingRendgSupport
{
    /** @var array<int, array<int, Puntoventa>> */
    private array $pvVendingPorSucursalCache = [];

    public function __construct(
        private readonly RendicionGastronomiaAnitaRendgastroSupport $rendgastroSupport,
    ) {
    }

    public function esCabeceraVending(object $fila, int $empresaId): bool
    {
        if ($this->rendgastroSupport->esCabeceraPostCierreWaitry($fila)) {
            return false;
        }

        if ($this->rendgastroSupport->esCabeceraEstacionamiento($fila)) {
            return false;
        }

        if ($this->rendgastroSupport->esCabeceraAgregadosCaea($fila)) {
            return false;
        }

        if (trim((string) ($fila->rendg_host ?? '')) !== '') {
            return false;
        }

        $sucursal = (int) ($fila->rendg_sucursal ?? 0);

        return $sucursal > 0 && isset($this->puntoventasVendingPorSucursal($empresaId)[$sucursal]);
    }

    /**
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   totales: array{rendgastro_z: float, cantidad: int}
     * }
     */
    public function filasReporte(
        int $empresaId,
        string $fechaJornada,
        float $tolerancia,
        bool $jornadaAbierta,
    ): array {
        if ($jornadaAbierta) {
            return ['filas' => [], 'totales' => ['rendgastro_z' => 0.0, 'cantidad' => 0]];
        }

        $fechaEntera = (int) str_replace('-', '', $fechaJornada);
        $cabeceras = $this->rendgastroSupport->listarCabecerasEmpresaFechaDetalle($empresaId, $fechaEntera);
        $pvPorSucursal = $this->puntoventasVendingPorSucursal($empresaId);

        /** @var array<int, list<object>> $porSucursal */
        $porSucursal = [];
        foreach ($cabeceras as $fila) {
            if (! $this->esCabeceraVending($fila, $empresaId)) {
                continue;
            }
            $sucursal = (int) ($fila->rendg_sucursal ?? 0);
            $porSucursal[$sucursal][] = $fila;
        }

        $filas = [];
        $sumRendg = 0.0;

        ksort($porSucursal);
        foreach ($porSucursal as $sucursal => $grupo) {
            $pv = $pvPorSucursal[$sucursal] ?? null;
            if ($pv === null) {
                continue;
            }

            $portadora = $this->rendgastroSupport->elegirPortadora($grupo);
            $rendgZ = round((float) ($portadora->rendg_total_z ?? 0), 2);
            if ($rendgZ <= $tolerancia) {
                continue;
            }

            $sumRendg += $rendgZ;
            $codigoPv = (string) ($pv->codigo ?? '');
            $filas[] = [
                'tipo_fila' => 'vending_rendg',
                'tipo_pv' => 'VENDING',
                'identificador_pc' => 'VENDING-'.$codigoPv,
                'pv_codigo' => $codigoPv,
                'descripcion_pc' => trim((string) ($pv->nombre ?? 'Maquina vending')).' (rendg host vacío)',
                'pv_cae' => '—',
                'pv_caea' => '—',
                'ventas_erp_cae' => 0.0,
                'ventas_erp_caea' => 0.0,
                'ventas_erp' => 0.0,
                'ventas_anita_cae' => 0.0,
                'ventas_anita_caea' => 0.0,
                'ventas_anita' => 0.0,
                'rendgastro_z' => $rendgZ,
                'rendgastro_z_cae' => null,
                'rendgastro_caea' => null,
                'rendgastro_nro_oper' => (int) ($portadora->rendg_nro_oper ?? 0),
                'rendg_sucursal' => $sucursal,
                'diff_erp_anita' => 0.0,
                'diff_erp_rendg' => round(0.0 - $rendgZ, 2),
                'estado' => 'RENDG',
                'cantidad_facturas_erp' => 0,
                'es_vending_rendg' => true,
            ];
        }

        return [
            'filas' => $filas,
            'totales' => [
                'rendgastro_z' => round($sumRendg, 2),
                'cantidad' => count($filas),
            ],
        ];
    }

    /**
     * @param  array{rendgastro_z: float, cantidad: int}  $totalesVending
     * @return array<string, mixed>
     */
    public function filaTotalVending(array $totalesVending, bool $jornadaAbierta): array
    {
        $rendgZ = round((float) ($totalesVending['rendgastro_z'] ?? 0), 2);

        return [
            'tipo_fila' => 'total_vending',
            'identificador_pc' => 'TOTAL-VENDING',
            'tipo_pv' => 'TOTAL',
            'pv_codigo' => '—',
            'descripcion_pc' => 'Total vending (rendgastro, sin facturación ERP gastronomía)',
            'pv_cae' => '',
            'pv_caea' => '',
            'ventas_erp_cae' => 0.0,
            'ventas_erp_caea' => 0.0,
            'ventas_erp' => 0.0,
            'ventas_anita_cae' => 0.0,
            'ventas_anita_caea' => 0.0,
            'ventas_anita' => 0.0,
            'rendgastro_z' => $jornadaAbierta ? null : $rendgZ,
            'diff_erp_anita' => 0.0,
            'diff_erp_rendg' => $jornadaAbierta ? null : round(0.0 - $rendgZ, 2),
            'estado' => $jornadaAbierta || $rendgZ <= 0.02 ? '—' : 'RENDG',
            'cantidad_facturas_erp' => 0,
            'es_total' => true,
            'es_vending_total' => true,
        ];
    }

    /**
     * @return array<int, Puntoventa>
     */
    public function puntoventasVendingPorSucursal(int $empresaId): array
    {
        if (isset($this->pvVendingPorSucursalCache[$empresaId])) {
            return $this->pvVendingPorSucursalCache[$empresaId];
        }

        $map = [];
        $puntoventas = Puntoventa::query()
            ->where('empresa_id', $empresaId)
            ->where('modofacturacion', 'M')
            ->where(function ($q) {
                $q->where('nombre', 'like', 'Maquina %')
                    ->orWhere('nombre', 'like', 'Maquina%')
                    ->orWhere('nombre', 'like', 'Máquina %')
                    ->orWhere('nombre', 'like', 'Máquina%');
            })
            ->whereRaw('LOWER(nombre) NOT LIKE ?', ['%estacionamiento%'])
            ->whereRaw('LOWER(nombre) NOT LIKE ?', ['%estac.%'])
            ->get();

        foreach ($puntoventas as $pv) {
            $sucursal = (int) preg_replace('/\D+/', '', (string) $pv->codigo);
            if ($sucursal > 0) {
                $map[$sucursal] = $pv;
            }
        }

        return $this->pvVendingPorSucursalCache[$empresaId] = $map;
    }
}
