<?php

namespace App\Support\Ventas;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros externos (fuera del panel inteligente) compartidos por listados de pedidos/remitos:
 * - Reparto: coma/punto y coma lista; barra / rango (vía KiloPedidoListadoFiltros).
 * - Fecha entrega desde/hasta: por default hoy; hasta vacío → hoy.
 */
final class ListadoRepartoFechaEntregaSupport
{
    public static function fechaHoy(): string
    {
        return date('Y-m-d');
    }

    /**
     * @return array{filtro_reparto: string, fecha_entrega_desde: string, fecha_entrega_hasta: string}
     */
    public static function vaciosConHoy(): array
    {
        $hoy = self::fechaHoy();

        return [
            'filtro_reparto' => '',
            'fecha_entrega_desde' => $hoy,
            'fecha_entrega_hasta' => $hoy,
        ];
    }

    /**
     * @return array{filtro_reparto: string, fecha_entrega_desde: string, fecha_entrega_hasta: string}
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $hoy = self::fechaHoy();
        $reparto = trim((string) $request->input('filtro_reparto', ''));
        $desde = trim((string) $request->input('fecha_entrega_desde', $hoy));
        $hasta = trim((string) $request->input('fecha_entrega_hasta', ''));

        if ($desde === '') {
            $desde = $hoy;
        }
        if ($hasta === '') {
            $hasta = $hoy;
        }

        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [
            'filtro_reparto' => $reparto,
            'fecha_entrega_desde' => $desde,
            'fecha_entrega_hasta' => $hasta,
        ];
    }

    public static function tieneCriteriosNoDefault(array $filtros): bool
    {
        if (trim((string) ($filtros['filtro_reparto'] ?? '')) !== '') {
            return true;
        }

        $hoy = self::fechaHoy();
        $desde = (string) ($filtros['fecha_entrega_desde'] ?? $hoy);
        $hasta = (string) ($filtros['fecha_entrega_hasta'] ?? $hoy);

        return $desde !== $hoy || $hasta !== $hoy;
    }

    /**
     * Siempre incluye fechas para que paginación/export conserven el rango.
     *
     * @return array<string, string>
     */
    public static function paraQueryString(array $filtros): array
    {
        $hoy = self::fechaHoy();
        $params = [
            'fecha_entrega_desde' => (string) ($filtros['fecha_entrega_desde'] ?? $hoy),
            'fecha_entrega_hasta' => (string) ($filtros['fecha_entrega_hasta'] ?? $hoy),
        ];

        $reparto = trim((string) ($filtros['filtro_reparto'] ?? ''));
        if ($reparto !== '') {
            $params['filtro_reparto'] = $reparto;
        }

        return $params;
    }

    public static function subtitulo(array $filtros): string
    {
        $partes = [];
        $reparto = trim((string) ($filtros['filtro_reparto'] ?? ''));
        if ($reparto !== '') {
            $partes[] = 'Reparto: '.$reparto;
        }

        $desde = (string) ($filtros['fecha_entrega_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_entrega_hasta'] ?? '');
        if ($desde !== '' || $hasta !== '') {
            $partes[] = 'Entrega: '.$desde.($hasta !== '' && $hasta !== $desde ? ' … '.$hasta : '');
        }

        return implode(' · ', $partes);
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Query\Builder  $query
     */
    public static function aplicar($query, array $filtros, string $columnaFechaEntrega): void
    {
        $reparto = trim((string) ($filtros['filtro_reparto'] ?? ''));
        if ($reparto !== '') {
            [$desdeReparto, $hastaReparto] = KiloPedidoListadoFiltros::normalizarRangoRepartos($reparto, '');
            KiloPedidoListadoFiltros::aplicarFiltroRepartoEnQuery($query, $desdeReparto, $hastaReparto);
        }

        $desde = trim((string) ($filtros['fecha_entrega_desde'] ?? ''));
        $hasta = trim((string) ($filtros['fecha_entrega_hasta'] ?? ''));
        if ($desde === '' && $hasta === '') {
            return;
        }

        if ($desde !== '' && $hasta !== '') {
            $query->whereDate($columnaFechaEntrega, '>=', $desde)
                ->whereDate($columnaFechaEntrega, '<=', $hasta);

            return;
        }

        if ($desde !== '') {
            $query->whereDate($columnaFechaEntrega, '>=', $desde);
        }
        if ($hasta !== '') {
            $query->whereDate($columnaFechaEntrega, '<=', $hasta);
        }
    }
}
