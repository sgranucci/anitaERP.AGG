<?php

namespace App\Services\Stock;

use App\Models\Stock\Transferencia_Mercaderia;
use App\Support\Stock\TransferenciaMercaderiaEstados;
use App\Support\Stock\TransferenciaPendienteListadoFiltros;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TransferenciaPendienteReporteService
{
    /**
     * @param  array<string, mixed>  $filtros
     */
    public function consultar(array $filtros, bool $paginar = true, int $porPagina = 25): LengthAwarePaginator|Collection
    {
        $query = $this->baseQuery($filtros);

        if ($paginar) {
            return $query->paginate($porPagina);
        }

        return $query->get();
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function baseQuery(array $filtros)
    {
        $query = Transferencia_Mercaderia::query()
            ->with([
                'depositoOrigen',
                'depositoDestino',
                'bienUsoOrigen',
                'bienUsoDestino',
                'tipotransaccion_stock',
                'usuarioOrigen',
                'usuarioDestino',
                'articulos',
            ])
            ->where('estado', TransferenciaMercaderiaEstados::PENDIENTE_RECEPCION)
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        if ($filtros['solo_requiere_aprobacion'] ?? true) {
            $query->where('requiere_aprobacion', true);
        }

        if (! empty($filtros['empresa_id'])) {
            $query->where('empresa_id', (int) $filtros['empresa_id']);
        }

        if (! empty($filtros['deposito_origen_id'])) {
            $query->where('deposito_origen_id', (int) $filtros['deposito_origen_id']);
        }

        if (! empty($filtros['deposito_destino_id'])) {
            $query->where('deposito_destino_id', (int) $filtros['deposito_destino_id']);
        }

        if (! empty($filtros['bien_uso_destino_id'])) {
            $query->where('bien_uso_destino_id', (int) $filtros['bien_uso_destino_id']);
        }

        if (! empty($filtros['fecha_desde'])) {
            $query->where('fecha', '>=', $filtros['fecha_desde']);
        }

        if (! empty($filtros['fecha_hasta'])) {
            $query->where('fecha', '<=', $filtros['fecha_hasta']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{total: int, total_items: int}
     */
    public function totales(array $filtros): array
    {
        $filas = $this->consultar($filtros, false);

        return [
            'total' => $filas->count(),
            'total_items' => (int) $filas->sum(fn (Transferencia_Mercaderia $t) => $t->articulos->count()),
        ];
    }

    public function subtituloFiltros(array $filtros): string
    {
        $partes = [];

        if ($filtros['solo_requiere_aprobacion'] ?? true) {
            $partes[] = 'Solo con aprobación requerida';
        }

        if (! empty($filtros['empresa_id'])) {
            $nombre = \App\Models\Configuracion\Empresa::query()->whereKey((int) $filtros['empresa_id'])->value('nombre');
            if ($nombre) {
                $partes[] = 'Empresa: '.$nombre;
            }
        }

        if (! empty($filtros['fecha_desde']) || ! empty($filtros['fecha_hasta'])) {
            $partes[] = 'Fechas: '.($filtros['fecha_desde'] ?? '…').' — '.($filtros['fecha_hasta'] ?? '…');
        }

        return implode(' · ', $partes);
    }
}
