<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente\Entidades;

use App\Queries\Caja\CobranzaQuery;
use App\Support\Caja\CobranzaListadoVisual;
use App\Support\ConsultaInteligente\EntidadSemantica;
use App\Support\Listado\ListadoQbeSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class CobranzaEntidad implements EntidadSemantica
{
    public function clave(): string
    {
        return 'cobranza';
    }

    public function nombre(): string
    {
        return 'Cobranzas';
    }

    public function descripcion(): string
    {
        return 'Recibos de cobranza: cliente, monto, estado y fechas.';
    }

    public function icono(): string
    {
        return 'fa-hand-holding-usd';
    }

    public function modulo(): string
    {
        return 'Caja';
    }

    public function color(): string
    {
        return '#196F3D';
    }

    public function permisoListar(): string
    {
        return 'listar-cobranza';
    }

    public function rutaNativa(): string
    {
        return 'cobranza';
    }

    public function recurso(): string
    {
        return CobranzaListadoVisual::RECURSO;
    }

    public function camposQbe(): array
    {
        $out = [];
        foreach (CobranzaListadoVisual::campos() as $key => $meta) {
            $out[$key] = [
                'label' => $meta['label'],
                'type' => $meta['type'] ?? 'texto',
                'column' => $meta['column'] ?? $key,
            ];
        }

        return $out;
    }

    public function columnasVisibles(): array
    {
        return ['id', 'numero', 'fecha', 'nombrecliente', 'nombreempresa', 'estado', 'monto', 'moneda'];
    }

    public function etiquetasColumnas(): array
    {
        return [
            'id' => 'ID',
            'numero' => 'Número',
            'fecha' => 'Fecha',
            'nombrecliente' => 'Cliente',
            'nombreempresa' => 'Empresa',
            'estado' => 'Estado',
            'monto' => 'Monto',
            'moneda' => 'Moneda',
            'detalle' => 'Detalle',
        ];
    }

    public function valorCelda(object $row, string $key): string
    {
        $v = $row->{$key} ?? '';
        if ($key === 'fecha' && $v) {
            return e(date('d/m/Y', strtotime((string) $v)));
        }
        if ($key === 'monto' && $v !== '' && $v !== null) {
            return e(number_format((float) $v, 2, ',', '.'));
        }

        return e((string) $v);
    }

    public function resolverFiltros(Request $request): array
    {
        $campos = $this->camposQbe();
        $qbe = ListadoQbeSupport::resolverDesdeRequest(
            $request,
            $campos,
            static fn (string $op, string $campo): string => $op
        );

        return [
            'busqueda' => trim((string) $request->input('filtro_valor', $request->input('busqueda', ''))),
            'fecha_desde' => (string) $request->input('fecha_desde', ''),
            'fecha_hasta' => (string) $request->input('fecha_hasta', ''),
            'origen' => (string) $request->input('origen', ''),
            'qbe' => $qbe,
            'filtro_modo' => (string) $request->input('filtro_modo', 'qbe'),
            'filtro_valor' => trim((string) $request->input('filtro_valor', '')),
        ];
    }

    public function paraQueryString(array $filtros): array
    {
        $out = array_filter([
            'filtro_valor' => $filtros['filtro_valor'] ?? $filtros['busqueda'] ?? null,
            'fecha_desde' => $filtros['fecha_desde'] ?? null,
            'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
            'origen' => $filtros['origen'] ?? null,
            'filtro_modo' => $filtros['filtro_modo'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');
        if (! empty($filtros['qbe']) && is_array($filtros['qbe'])) {
            $out['qbe'] = $filtros['qbe'];
        }

        return $out;
    }

    public function consultar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        /** @var CobranzaQuery $query */
        $query = app(CobranzaQuery::class);

        return $query->leeCobranza(
            (string) ($filtros['busqueda'] ?? $filtros['filtro_valor'] ?? ''),
            0,
            $paginar,
            ($filtros['fecha_desde'] ?? '') !== '' ? (string) $filtros['fecha_desde'] : null,
            ($filtros['fecha_hasta'] ?? '') !== '' ? (string) $filtros['fecha_hasta'] : null,
            is_array($filtros['qbe'] ?? null) ? $filtros['qbe'] : null,
            (string) ($filtros['origen'] ?? ''),
        );
    }

    public function urlDetalle(object $row): ?string
    {
        $id = (int) ($row->id ?? 0);
        if ($id < 1 || ! can('editar-cobranza', false)) {
            return null;
        }

        return route('editar_cobranza', [
            'id' => $id,
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ]);
    }

    public function plantillas(): array
    {
        return [
            [
                'clave' => 'todas',
                'titulo' => 'Cobranzas',
                'descripcion' => 'Listado de recibos',
                'query' => ['consultar' => 1],
            ],
            [
                'clave' => 'este_mes',
                'titulo' => 'Cobranzas este mes',
                'descripcion' => 'Recibos del mes en curso',
                'query' => [
                    'consultar' => 1,
                    'fecha_desde' => date('Y-m-01'),
                    'fecha_hasta' => date('Y-m-t'),
                ],
            ],
        ];
    }
}
