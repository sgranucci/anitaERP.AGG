<?php

namespace App\Queries\Caja;

use App\Models\Caja\TicketCanjeCaja;
use App\Support\Caja\TicketCanjeCajaReporteFiltros;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Informe de tickets canje (formato legacy informe_datos_x_ventas).
 */
final class TicketCanjeCajaReporteQuery
{
    /**
     * @param  array<string, mixed>  $filtros
     */
    public function listado(array $filtros, bool $paginar = true, int $perPage = 25): LengthAwarePaginator|Collection
    {
        $query = $this->baseQuery($filtros)
            ->orderBy('ticket_canje_caja.fecha')
            ->orderBy('ticket_canje_caja.movimiento_id')
            ->orderBy('ticket_canje_caja.numero_ticket');

        if ($paginar) {
            return $query->paginate($perPage)->through(fn ($row) => $this->enriquecer($row));
        }

        return $query->get()->map(fn ($row) => $this->enriquecer($row));
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{cantidad:int,monto_venta:float,monto_ticket:float}
     */
    public function totales(array $filtros): array
    {
        $agg = $this->baseQuery($filtros)
            ->without(['empresa', 'cajero', 'usuario', 'turnoOperativo.turno', 'venta.puntoventas', 'venta.tipotransacciones'])
            ->reorder()
            ->toBase()
            ->selectRaw('COUNT(*) as cantidad')
            ->selectRaw('COALESCE(SUM(ticket_canje_caja.monto_venta),0) as monto_venta')
            ->selectRaw('COALESCE(SUM(ticket_canje_caja.monto_ticket),0) as monto_ticket')
            ->first();

        return [
            'cantidad' => (int) ($agg->cantidad ?? 0),
            'monto_venta' => round((float) ($agg->monto_venta ?? 0), 2),
            'monto_ticket' => round((float) ($agg->monto_ticket ?? 0), 2),
        ];
    }

    /**
     * Usuarios que emitieron canjes (para el selector del informe), sin filtrar por fecha ni estado.
     *
     * @param  array<string, mixed>  $filtros
     * @return list<int>
     */
    public function idsUsuariosEmisores(array $filtros): array
    {
        $query = TicketCanjeCaja::query()->from('ticket_canje_caja')->toBase();

        if (! empty($filtros['empresas_asignadas']) && is_array($filtros['empresas_asignadas'])) {
            $query->whereIn('ticket_canje_caja.empresa_id', $filtros['empresas_asignadas']);
        }
        if (! empty($filtros['empresa_id'])) {
            $query->where('ticket_canje_caja.empresa_id', (int) $filtros['empresa_id']);
        }

        return $query->whereNotNull('ticket_canje_caja.usuario_id')
            ->distinct()
            ->pluck('ticket_canje_caja.usuario_id')
            ->map(static fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function baseQuery(array $filtros): Builder
    {
        $query = TicketCanjeCaja::query()
            ->from('ticket_canje_caja')
            ->select([
                'ticket_canje_caja.*',
            ])
            ->with([
                'empresa',
                'cajero',
                'usuario',
                'turnoOperativo.turno',
                'venta.puntoventas',
                'venta.tipotransacciones',
            ]);

        if (! empty($filtros['empresas_asignadas']) && is_array($filtros['empresas_asignadas'])) {
            $query->whereIn('ticket_canje_caja.empresa_id', $filtros['empresas_asignadas']);
        }

        if (! empty($filtros['empresa_id'])) {
            $query->where('ticket_canje_caja.empresa_id', (int) $filtros['empresa_id']);
        }

        if (! empty($filtros['usuario_id'])) {
            $query->where('ticket_canje_caja.usuario_id', (int) $filtros['usuario_id']);
        }

        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('ticket_canje_caja.fecha', '>=', $filtros['fecha_desde']);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('ticket_canje_caja.fecha', '<=', $filtros['fecha_hasta']);
        }

        $estado = (string) ($filtros['estado'] ?? TicketCanjeCajaReporteFiltros::ESTADO_TODOS);
        if (in_array($estado, [
            TicketCanjeCajaReporteFiltros::ESTADO_PENDIENTE,
            TicketCanjeCajaReporteFiltros::ESTADO_CANJEADO,
            TicketCanjeCajaReporteFiltros::ESTADO_VIP,
        ], true)) {
            $query->where('ticket_canje_caja.estado', $estado);
        }

        return $query;
    }

    private function enriquecer(TicketCanjeCaja $row): TicketCanjeCaja
    {
        $row->setAttribute('nombreempresa', $row->empresa->nombre ?? '');
        $row->setAttribute('vale', $row->etiquetaVale());
        $row->setAttribute('fecha_fmt', $row->fecha?->format('d/m/Y') ?? '');
        $row->setAttribute('hora_fmt', $row->created_at?->format('H:i') ?? '');
        $row->setAttribute('turno_nombre', $row->turnoOperativo?->turno?->nombre ?? '');
        $row->setAttribute('caja', (string) ($row->identificador_pc ?? ''));
        $row->setAttribute('cajero_nombre', $row->cajero->nombre ?? '');
        $row->setAttribute('autorizante_nombre', $row->usuario->nombre ?? '');
        $row->setAttribute('estado_etiqueta', $row->etiquetaEstado());
        $row->setAttribute('fecha_canje_fmt', $row->fecha_canje?->format('d/m/Y') ?? '');

        $tip = '';
        $numeroFactura = '';
        if ($row->venta) {
            $tip = (string) ($row->venta->tipotransacciones->abreviatura
                ?? $row->venta->tipotransacciones->nombre
                ?? '');
            $pv = (string) ($row->venta->puntoventas->codigo ?? '');
            $nro = (string) ($row->venta->numerocomprobante ?? '');
            if ($pv !== '' || $nro !== '') {
                $numeroFactura = trim($pv.'-'.$nro, '-');
            }
        }
        $row->setAttribute('tip_factura', $tip);
        $row->setAttribute('numero_factura', $numeroFactura);

        return $row;
    }
}
