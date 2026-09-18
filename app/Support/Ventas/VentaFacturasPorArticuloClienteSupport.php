<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Emision;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Carbon\Carbon;

/**
 * Facturas ERP de un cliente que contienen un artículo (NC parcial mostrador).
 * No usa Anita: sin ítems no se puede armar la NC en el facturador.
 */
final class VentaFacturasPorArticuloClienteSupport
{
    private const LIMITE = 80;

    public function __construct(
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    /**
     * @return list<array{
     *     venta_id:int,
     *     codigo:string,
     *     fecha:string,
     *     fecha_orden:string,
     *     total:float,
     *     cantidad:float,
     *     cantidad_nc:float,
     *     cantidad_pendiente:float
     * }>
     */
    public function listar(int $clienteId, int $articuloId, int $empresaId, string $consulta = ''): array
    {
        if ($clienteId <= 0) {
            return [];
        }

        $query = Venta::query()
            ->with([
                'tipotransacciones:id,abreviatura,operacion',
                'puntoventas:id,codigo,empresa_id',
            ])
            ->where('cliente_id', $clienteId)
            ->whereHas('tipotransacciones', static function ($q) {
                $q->where('operacion', '!=', 'C');
            })
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(self::LIMITE);

        $this->aplicarFiltroEmpresa($query, $empresaId);

        if ($articuloId > 0) {
            $query->whereHas('venta_emisiones', static function ($q) use ($articuloId) {
                $q->where('articulo_id', $articuloId);
            });
        }

        $consulta = trim($consulta);
        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('codigo', 'like', '%'.$consulta.'%')
                    ->orWhere('numerocomprobante', 'like', '%'.$consulta.'%')
                    ->orWhere('id', 'like', '%'.$consulta.'%');
            });
        }

        $ventas = $query->get();
        if ($ventas->isEmpty()) {
            return [];
        }

        $ventaIds = $ventas->pluck('id')->map(static fn ($id) => (int) $id)->all();
        $cantidades = $articuloId > 0
            ? $this->cantidadesVendidasPorVenta($ventaIds, $articuloId)
            : [];
        $acreditadas = $articuloId > 0
            ? $this->cantidadesAcreditadasPorVenta($ventaIds, $articuloId)
            : [];

        $out = [];
        foreach ($ventas as $row) {
            $ventaId = (int) $row->id;
            $codigo = $this->codigoDesdeVenta($row);
            if ($codigo === '') {
                continue;
            }
            $fechaOrden = $this->fechaOrdenDesdeValor($row->fecha);
            $vendida = (float) ($cantidades[$ventaId] ?? 0);
            $nc = (float) ($acreditadas[$ventaId] ?? 0);
            $out[] = [
                'venta_id' => $ventaId,
                'codigo' => $codigo,
                'fecha' => $this->formatearFechaDisplay($fechaOrden),
                'fecha_orden' => $fechaOrden,
                'total' => (float) ($row->total ?? 0),
                'cantidad' => $vendida,
                'cantidad_nc' => $nc,
                'cantidad_pendiente' => self::pendiente($vendida, $nc),
            ];
        }

        return $out;
    }

    /**
     * Pendiente por id de venta_emision de la factura origen.
     *
     * @return array<int, array{vendida:float, acreditada:float, pendiente:float}>
     */
    public function pendientesPorEmision(int $ventaOrigenId): array
    {
        if ($ventaOrigenId <= 0) {
            return [];
        }

        $emisiones = Venta_Emision::query()
            ->where('venta_id', $ventaOrigenId)
            ->orderBy('numeroitem')
            ->orderBy('id')
            ->get(['id', 'articulo_id', 'combinacion_id', 'talle_id', 'cantidad']);

        if ($emisiones->isEmpty()) {
            return [];
        }

        $lineas = [];
        foreach ($emisiones as $emision) {
            $lineas[] = [
                'id' => (int) $emision->id,
                'clave' => self::claveLinea(
                    (int) ($emision->articulo_id ?? 0),
                    (int) ($emision->combinacion_id ?? 0),
                    (int) ($emision->talle_id ?? 0)
                ),
                'cantidad' => (float) ($emision->cantidad ?? 0),
            ];
        }

        $acreditadoPorClave = $this->cantidadesAcreditadasPorClave($ventaOrigenId);
        $prorrateo = self::prorratearAcreditado($lineas, $acreditadoPorClave);

        $out = [];
        foreach ($prorrateo as $i => $fila) {
            $id = (int) ($lineas[$i]['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[$id] = [
                'vendida' => (float) $fila['cantidad'],
                'acreditada' => (float) $fila['acreditada'],
                'pendiente' => (float) $fila['pendiente'],
            ];
        }

        return $out;
    }

    public static function claveLinea(int $articuloId, int $combinacionId, int $talleId): string
    {
        return $articuloId.'-'.$combinacionId.'-'.$talleId;
    }

    public static function pendiente(float $vendida, float $acreditada): float
    {
        $p = round($vendida - $acreditada, 4);

        return $p > 0 ? $p : 0.0;
    }

    /**
     * @param  list<array{clave?:string,cantidad?:float}>  $lineas
     * @param  array<string, float>  $acreditadoPorClave
     * @return list<array{clave:string,cantidad:float,acreditada:float,pendiente:float}>
     */
    public static function prorratearAcreditado(array $lineas, array $acreditadoPorClave): array
    {
        $restante = $acreditadoPorClave;
        $out = [];
        foreach ($lineas as $linea) {
            $clave = (string) ($linea['clave'] ?? '');
            $cant = (float) ($linea['cantidad'] ?? 0);
            $disp = (float) ($restante[$clave] ?? 0);
            if ($disp < 0) {
                $disp = 0.0;
            }
            $acred = min($cant < 0 ? 0.0 : $cant, $disp);
            $restante[$clave] = $disp - $acred;
            $out[] = [
                'clave' => $clave,
                'cantidad' => $cant,
                'acreditada' => $acred,
                'pendiente' => self::pendiente($cant, $acred),
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $ventaIds
     * @return array<int, float>
     */
    private function cantidadesVendidasPorVenta(array $ventaIds, int $articuloId): array
    {
        if ($ventaIds === [] || $articuloId <= 0) {
            return [];
        }

        $filas = Venta_Emision::query()
            ->selectRaw('venta_id, SUM(cantidad) as cantidad')
            ->where('articulo_id', $articuloId)
            ->whereIn('venta_id', $ventaIds)
            ->groupBy('venta_id')
            ->get();

        $out = [];
        foreach ($filas as $fila) {
            $out[(int) $fila->venta_id] = (float) ($fila->cantidad ?? 0);
        }

        return $out;
    }

    /**
     * @param  list<int>  $ventaIds
     * @return array<int, float>
     */
    private function cantidadesAcreditadasPorVenta(array $ventaIds, int $articuloId): array
    {
        if ($ventaIds === [] || $articuloId <= 0) {
            return [];
        }

        $filas = Venta_Emision::query()
            ->from('venta_emision as ve')
            ->join('venta as nc', 'nc.id', '=', 've.venta_id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'nc.tipotransaccion_id')
            ->where('tt.operacion', 'C')
            ->where('ve.articulo_id', $articuloId)
            ->whereIn('nc.venta_origen_id', $ventaIds)
            ->selectRaw('nc.venta_origen_id as venta_id, SUM(ve.cantidad) as cantidad')
            ->groupBy('nc.venta_origen_id')
            ->get();

        $out = [];
        foreach ($filas as $fila) {
            $out[(int) $fila->venta_id] = abs((float) ($fila->cantidad ?? 0));
        }

        return $out;
    }

    /**
     * @return array<string, float>
     */
    private function cantidadesAcreditadasPorClave(int $ventaOrigenId): array
    {
        $filas = Venta_Emision::query()
            ->from('venta_emision as ve')
            ->join('venta as nc', 'nc.id', '=', 've.venta_id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'nc.tipotransaccion_id')
            ->where('tt.operacion', 'C')
            ->where('nc.venta_origen_id', $ventaOrigenId)
            ->get([
                've.articulo_id',
                've.combinacion_id',
                've.talle_id',
                've.cantidad',
            ]);

        $out = [];
        foreach ($filas as $fila) {
            $clave = self::claveLinea(
                (int) ($fila->articulo_id ?? 0),
                (int) ($fila->combinacion_id ?? 0),
                (int) ($fila->talle_id ?? 0)
            );
            $out[$clave] = ($out[$clave] ?? 0.0) + abs((float) ($fila->cantidad ?? 0));
        }

        return $out;
    }

    private function aplicarFiltroEmpresa($query, int $empresaId): void
    {
        if ($empresaId > 0) {
            $query->whereHas('puntoventas', static fn ($q) => $q->where('empresa_id', $empresaId));

            return;
        }
        $asignadas = $this->empresaRepository->traeEmpresasAsignadas();
        if (is_array($asignadas) && count($asignadas) > 0) {
            $query->whereHas('puntoventas', static fn ($q) => $q->whereIn('empresa_id', $asignadas));
        }
    }

    private function codigoDesdeVenta(Venta $venta): string
    {
        $codigo = trim((string) ($venta->codigo ?? ''));
        if ($codigo !== '') {
            return $codigo;
        }
        $abrev = (string) ($venta->tipotransacciones?->abreviatura ?? '');
        $pv = str_pad((string) ($venta->puntoventas?->codigo ?? 0), 5, '0', STR_PAD_LEFT);
        $nro = str_pad((string) ($venta->numerocomprobante ?? 0), 8, '0', STR_PAD_LEFT);

        return trim($abrev.' '.$pv.'-'.$nro);
    }

    private function fechaOrdenDesdeValor(mixed $fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }
        $raw = trim((string) $fecha);
        if ($raw === '') {
            return '0000-00-00';
        }
        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable $e) {
            return '0000-00-00';
        }
    }

    private function formatearFechaDisplay(string $fechaOrden): string
    {
        if ($fechaOrden === '' || $fechaOrden === '0000-00-00') {
            return '';
        }
        try {
            return Carbon::parse($fechaOrden)->format('d/m/Y');
        } catch (\Throwable $e) {
            return $fechaOrden;
        }
    }
}
