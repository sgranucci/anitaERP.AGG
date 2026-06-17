<?php

namespace App\Services\Ventas;

use App\Queries\Ventas\PedidoQueryInterface;
use App\Support\Ventas\KiloPedidoListadoFiltros;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class KiloPedidoReporteService
{
    public function __construct(
        private readonly PedidoQueryInterface $pedidoQuery,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function generarDatos(array $filtros): Collection
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        return $this->pedidoQuery->findPorKiloPedidoFiltros($filtros);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    public function aplanarFilas(Collection $datos, array $filtros): array
    {
        $tipolistado = (string) ($filtros['tipolistado'] ?? 'TOTAL');
        $filas = [];
        $repartoActual = '';
        $nombreRepartoActual = '';
        $transporteIdActual = 0;
        $subtotal = self::totalesVacios();
        $totalFinal = self::totalesVacios();

        foreach ($datos as $row) {
            $codigoReparto = (string) ($row->codigotransporte ?? '');

            if ($codigoReparto !== $repartoActual && $repartoActual !== '') {
                $filas[] = self::filaSubtotalReparto(
                    $repartoActual,
                    $nombreRepartoActual,
                    $transporteIdActual,
                    $subtotal,
                    $tipolistado,
                );
                $subtotal = self::totalesVacios();
            }

            if ($codigoReparto !== $repartoActual) {
                $repartoActual = $codigoReparto;
                $nombreRepartoActual = (string) ($row->nombretransporte ?? '');
                $transporteIdActual = (int) ($row->transporte_id ?? 0);
            }

            $filas[] = self::filaDetalle($row, $tipolistado);
            self::acumularTotales($subtotal, $row);
            self::acumularTotales($totalFinal, $row);
        }

        if ($repartoActual !== '') {
            $filas[] = self::filaSubtotalReparto(
                $repartoActual,
                $nombreRepartoActual,
                $transporteIdActual,
                $subtotal,
                $tipolistado,
            );
        }

        if ($datos->isNotEmpty()) {
            $filas[] = self::filaTotalFinal($totalFinal, $tipolistado);
        }

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public function paginarFilas(array $filas, int $perPage, int $page = 1): LengthAwarePaginator
    {
        $perPage = max(10, min(200, $perPage));
        $page = max(1, $page);
        $total = count($filas);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($filas, $offset, $perPage);

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{total_pieza: float, total_kilo: float, total_pesada: float, total_caja: float, cantidad_detalle: int}
     */
    public function totalesGenerales(array $filas): array
    {
        $totales = self::totalesVacios();
        $cantidadDetalle = 0;

        foreach ($filas as $fila) {
            if (($fila['tipo_fila'] ?? '') !== 'detalle') {
                continue;
            }
            $cantidadDetalle++;
            $totales['total_pieza'] += (float) ($fila['total_pieza'] ?? 0);
            $totales['total_kilo'] += (float) ($fila['total_kilo'] ?? 0);
            $totales['total_pesada'] += (float) ($fila['total_pesada'] ?? 0);
            $totales['total_caja'] += (float) ($fila['total_caja'] ?? 0);
        }

        $totales['cantidad_detalle'] = $cantidadDetalle;

        return $totales;
    }

    /**
     * @return array{total_pieza: float, total_kilo: float, total_pesada: float, total_caja: float}
     */
    private static function totalesVacios(): array
    {
        return [
            'total_pieza' => 0.0,
            'total_kilo' => 0.0,
            'total_pesada' => 0.0,
            'total_caja' => 0.0,
        ];
    }

    private static function acumularTotales(array &$totales, object $row): void
    {
        $totales['total_pieza'] += (float) ($row->total_pieza ?? 0);
        $totales['total_kilo'] += (float) ($row->total_kilo ?? 0);
        $totales['total_pesada'] += (float) ($row->total_pesada ?? 0);
        $totales['total_caja'] += (float) ($row->total_caja ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    private static function filaDetalle(object $row, string $tipolistado): array
    {
        $fechaEntrega = $row->fechaentrega ?? null;
        $fechaTexto = $fechaEntrega ? date('d/m/Y', strtotime((string) $fechaEntrega)) : '';

        $base = [
            'tipo_fila' => 'detalle',
            'codigotransporte' => (string) ($row->codigotransporte ?? ''),
            'nombretransporte' => (string) ($row->nombretransporte ?? ''),
            'transporte_id' => (int) ($row->transporte_id ?? 0),
            'total_pieza' => (float) ($row->total_pieza ?? 0),
            'total_kilo' => (float) ($row->total_kilo ?? 0),
            'total_pesada' => (float) ($row->total_pesada ?? 0),
            'total_caja' => (float) ($row->total_caja ?? 0),
        ];

        if ($tipolistado === 'TOTAL') {
            return array_merge($base, [
                'pedido_id' => (int) ($row->pedido_id ?? 0),
                'cliente_id' => (int) ($row->cliente_id ?? 0),
                'codigopedido' => (string) ($row->codigopedido ?? ''),
                'codigocliente' => (string) ($row->codigocliente ?? ''),
                'nombrecliente' => (string) ($row->nombrecliente ?? ''),
                'fechaentrega' => $fechaTexto,
                'nombrelocalidad' => (string) ($row->nombrelocalidad ?? ''),
                'nombreprovincia' => (string) ($row->nombreprovincia ?? ''),
            ]);
        }

        return array_merge($base, [
            'articulo_id' => (int) ($row->articulo_id ?? 0),
            'sku' => (string) ($row->sku ?? ''),
            'descripcion' => (string) ($row->descripcion ?? ''),
            'codigocategoria' => (string) ($row->codigocategoria ?? ''),
        ]);
    }

    /**
     * @param  array{total_pieza: float, total_kilo: float, total_pesada: float, total_caja: float}  $subtotal
     * @return array<string, mixed>
     */
    private static function filaSubtotalReparto(
        string $codigoReparto,
        string $nombreReparto,
        int $transporteId,
        array $subtotal,
        string $tipolistado,
    ): array {
        return array_merge($subtotal, [
            'tipo_fila' => 'subtotal_reparto',
            'codigotransporte' => $codigoReparto,
            'nombretransporte' => $nombreReparto,
            'transporte_id' => $transporteId,
            'tipolistado' => $tipolistado,
        ]);
    }

    /**
     * @param  array{total_pieza: float, total_kilo: float, total_pesada: float, total_caja: float}  $totalFinal
     * @return array<string, mixed>
     */
    private static function filaTotalFinal(array $totalFinal, string $tipolistado): array
    {
        return array_merge($totalFinal, [
            'tipo_fila' => 'total_final',
            'tipolistado' => $tipolistado,
        ]);
    }
}
