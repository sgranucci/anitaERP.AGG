<?php

namespace App\Services\Contable\LibroIvaDigital;

use App\Models\Ventas\Venta;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalFormatoSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalMapeosSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalVentasPeriodoSupport;
use App\Support\Compras\ComprobanteProveedorEstados;
use Illuminate\Support\Facades\DB;

class LibroIvaDigitalAnuladosGenerador
{
    /**
     * @param  array{por_fecha_jornada?: bool}  $opciones
     * @return array{ventas: string, compras: string, resumen: array<string, int>}
     */
    public function generar(int $empresaId, int $anio, int $mes, array $opciones = []): array
    {
        $desde = sprintf('%04d-%02d-01', $anio, $mes);
        $hasta = date('Y-m-t', strtotime($desde)).' 23:59:59';
        $porFechaJornada = (bool) ($opciones['por_fecha_jornada'] ?? false);

        $lineasVentas = $this->ventasAnuladas($empresaId, $desde, $hasta, $porFechaJornada);
        $lineasCompras = $this->comprasAnuladas($empresaId, $desde, $hasta);

        return [
            'ventas' => implode("\r\n", $lineasVentas),
            'compras' => implode("\r\n", $lineasCompras),
            'resumen' => [
                'ventas' => count($lineasVentas),
                'compras' => count($lineasCompras),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function ventasAnuladas(int $empresaId, string $desde, string $hasta, bool $porFechaJornada): array
    {
        $lineas = [];

        Venta::onlyTrashed()
            ->with(['tipotransacciones', 'puntoventas'])
            ->whereHas('puntoventas', fn ($q) => $q->where('empresa_id', $empresaId))
            ->whereBetween('deleted_at', [$desde, $hasta])
            ->whereNotNull('cae')
            ->where('cae', '<>', '')
            ->orderBy('fecha')
            ->lazy(100)
            ->each(function (Venta $venta) use (&$lineas, $porFechaJornada): void {
                $letra = LibroIvaDigitalMapeosSupport::letraDesdeCodigoVenta((string) $venta->codigo);
                $tipo = LibroIvaDigitalMapeosSupport::tipoComprobanteVentas(
                    (string) ($venta->tipotransacciones->codigo ?? '001'),
                    $letra,
                    (string) ($venta->tipotransacciones->abreviatura ?? ''),
                );
                $lineas[] = LibroIvaDigitalFormatoSupport::registroComprobanteAnulado([
                    'fecha_comprobante' => LibroIvaDigitalVentasPeriodoSupport::fechaDocumento($venta, $porFechaJornada),
                    'tipo_comprobante' => $tipo,
                    'punto_venta' => (int) ($venta->puntoventas->codigo ?? 0),
                    'numero_comprobante' => (int) $venta->numerocomprobante,
                    'fecha_anulacion' => date('Ymd', strtotime((string) $venta->deleted_at)),
                ]);
            });

        return $lineas;
    }

    /**
     * @return list<string>
     */
    private function comprasAnuladas(int $empresaId, string $desde, string $hasta): array
    {
        $rows = DB::table('comprobante_proveedor as cp')
            ->join('comprobante_proveedor_estado as cpe', function ($join): void {
                $join->on('cpe.comprobante_proveedor_id', '=', 'cp.id')
                    ->where('cpe.estado', ComprobanteProveedorEstados::ANULADO);
            })
            ->join('tipotransaccion_compra as tt', 'tt.id', '=', 'cp.tipotransaccion_compra_id')
            ->where('cp.empresa_id', $empresaId)
            ->whereBetween('cpe.fecha', [substr($desde, 0, 10), substr($hasta, 0, 10)])
            ->select([
                'cp.fechaiva',
                'cp.letra',
                'cp.sucursal',
                'cp.numerocomprobante',
                'tt.codigoafip',
                'cpe.fecha as fecha_anulacion',
            ])
            ->orderBy('cpe.fecha')
            ->get();

        $lineas = [];
        foreach ($rows as $row) {
            $letra = strtoupper((string) ($row->letra ?: 'A'));
            $tipo = LibroIvaDigitalMapeosSupport::tipoComprobanteVentas(
                (string) ($row->codigoafip ?? '001'),
                $letra,
            );
            $lineas[] = LibroIvaDigitalFormatoSupport::registroComprobanteAnulado([
                'fecha_comprobante' => date('Ymd', strtotime((string) $row->fechaiva)),
                'tipo_comprobante' => $tipo,
                'punto_venta' => (int) $row->sucursal,
                'numero_comprobante' => (int) $row->numerocomprobante,
                'fecha_anulacion' => date('Ymd', strtotime((string) $row->fecha_anulacion)),
            ]);
        }

        return $lineas;
    }
}
