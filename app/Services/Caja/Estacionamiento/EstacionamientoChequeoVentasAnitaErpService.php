<?php

declare(strict_types=1);

namespace App\Services\Caja\Estacionamiento;

use App\ApiAnita;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Venta;
use App\Services\Ventas\Gastronomia\GastronomiaChequeoVentasAnitaErpService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Concilia ventas estacionamiento ERP ↔ cabecera Anita (Informix).
 */
final class EstacionamientoChequeoVentasAnitaErpService
{
    public function __construct(
        private readonly GastronomiaChequeoVentasAnitaErpService $gastronomiaChequeo,
    ) {
    }

    /**
     * @return Collection<int, Venta>
     */
    public function listarVentasErpPorJornada(int $puntoventaId, string $fechaJornada): Collection
    {
        return Venta::query()
            ->where('puntoventa_id', $puntoventaId)
            ->whereDate('fechajornada', $fechaJornada)
            ->whereHas('estacionamientoEmision')
            ->orderBy('numerocomprobante')
            ->get(['id', 'codigo', 'numerocomprobante', 'total', 'fechajornada', 'fecha', 'tipotransaccion_id']);
    }

    /**
     * Ventas estacionamiento del ERP sin cabecera en Informix para un PV y fecha de jornada.
     *
     * @return Collection<int, Venta>
     */
    public function listarVentasErpSinCabeceraAnita(int $puntoventaId, string $fechaJornada): Collection
    {
        $puntoventa = Puntoventa::query()->findOrFail($puntoventaId);
        $sucursal = $this->sucursalDesdeCodigoPuntoventa((string) $puntoventa->codigo);
        $fechaEntera = (int) str_replace('-', '', $fechaJornada);

        $anitaPorClave = $this->listarCabecerasAnitaPorJornada($sucursal, $fechaEntera);
        $ventasErp = $this->listarVentasErpPorJornada($puntoventaId, $fechaJornada);

        return $ventasErp->filter(function (Venta $venta) use ($anitaPorClave): bool {
            $clave = $this->claveComprobanteDesdeVenta($venta);

            return $clave !== null && ! isset($anitaPorClave[$clave]);
        })->values();
    }

    /**
     * @return list<array{puntoventa_id:int, codigo_pv:string, fecha_jornada:string}>
     */
    public function listarCombinacionesPvJornada(
        string $fechaDesde,
        ?string $fechaHasta,
        int $empresaId,
        ?string $codigoPv = null,
    ): array {
        $query = Venta::query()
            ->selectRaw('venta.puntoventa_id, DATE(venta.fechajornada) as fecha_jornada, puntoventa.codigo as codigo_pv')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->whereDate('venta.fechajornada', '>=', $fechaDesde)
            ->whereHas('estacionamientoEmision')
            ->where('puntoventa.modofacturacion', '!=', 'M')
            ->where('puntoventa.empresa_id', $empresaId)
            ->groupBy('venta.puntoventa_id', 'fecha_jornada', 'codigo_pv')
            ->orderBy('fecha_jornada')
            ->orderBy('codigo_pv');

        if ($fechaHasta !== null && $fechaHasta !== '') {
            $query->whereDate('venta.fechajornada', '<=', $fechaHasta);
        }

        if ($codigoPv !== null && trim($codigoPv) !== '') {
            $query->where('puntoventa.codigo', trim($codigoPv));
        }

        $filas = [];
        foreach ($query->get() as $row) {
            $filas[] = [
                'puntoventa_id' => (int) $row->puntoventa_id,
                'codigo_pv' => (string) $row->codigo_pv,
                'fecha_jornada' => (string) $row->fecha_jornada,
            ];
        }

        return $filas;
    }

    /**
     * @return array{cabecera: ?object, error_lectura: ?string}
     */
    public function consultarCabeceraAnitaDesdeVenta(Venta $venta, string $letra = 'B'): array
    {
        return $this->gastronomiaChequeo->consultarCabeceraAnitaDesdeVenta($venta, $letra);
    }

    /**
     * @return array<string, object>
     */
    private function listarCabecerasAnitaPorJornada(int $sucursal, int $fechaEntera): array
    {
        $api = new ApiAnita;
        $where = " WHERE ven_sucursal = '".$sucursal."'"
            ." AND ven_fecha_vto = '".$fechaEntera."'"
            ." AND ven_letra = 'B' ";

        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'tabla' => 'venta',
            'campos' => implode(',', [
                'ven_tipo', 'ven_letra', 'ven_sucursal', 'ven_nro',
                'ven_fecha', 'ven_fecha_vto',
                'ven_monto', 'ven_gravado', 'ven_exento', 'ven_impuesto1', 'ven_monto_desc',
            ]),
            'whereArmado' => $where,
            'orderBy' => 'ven_tipo, ven_nro',
        ]));

        if ($parsed['error_lectura'] !== null) {
            Log::warning('estacionamiento.chequeo_anita.lista_jornada_fallo', [
                'sucursal' => $sucursal,
                'fecha_jornada' => $fechaEntera,
                'msg' => $parsed['error_lectura'],
            ]);

            throw new \RuntimeException(
                'No se pudo listar cabeceras Anita para la jornada: '.$parsed['error_lectura']
            );
        }

        $map = [];
        foreach ($parsed['filas'] as $fila) {
            $tipo = trim((string) ($fila->ven_tipo ?? ''));
            $nro = (int) ($fila->ven_nro ?? 0);
            if ($tipo === '' || $nro <= 0) {
                continue;
            }
            $map[$tipo.'-'.$nro] = $fila;
        }

        return $map;
    }

    private function claveComprobanteDesdeVenta(Venta $venta): ?string
    {
        $codigo = trim((string) ($venta->codigo ?? ''));
        if (preg_match('/^(\S+)\s+[A-Z]-\d+-(\d+)$/', $codigo, $m)) {
            return $m[1].'-'.(int) $m[2];
        }

        if ((int) ($venta->numerocomprobante ?? 0) <= 0) {
            return null;
        }

        return 'FAC-'.(int) $venta->numerocomprobante;
    }

    private function sucursalDesdeCodigoPuntoventa(string $codigo): int
    {
        return (int) preg_replace('/\D+/', '', trim($codigo));
    }
}
