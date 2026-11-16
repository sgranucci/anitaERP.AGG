<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente\Entidades;

use App\Repositories\Compras\PropuestaPagoRepositoryInterface;
use App\Support\Compras\PropuestaPagoListadoFiltros;
use App\Support\ConsultaInteligente\EntidadSemantica;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class PropuestaPagoEntidad implements EntidadSemantica
{
    public function clave(): string
    {
        return 'propuesta_pago';
    }

    public function nombre(): string
    {
        return 'Propuestas de pago';
    }

    public function descripcion(): string
    {
        return 'Propuestas AP: estado, empresa, fecha e importe.';
    }

    public function icono(): string
    {
        return 'fa-money-check-alt';
    }

    public function modulo(): string
    {
        return 'Compras';
    }

    public function color(): string
    {
        return '#196F3D';
    }

    public function permisoListar(): string
    {
        return 'listar-propuesta-pago';
    }

    public function rutaNativa(): string
    {
        return 'propuesta_pago';
    }

    public function recurso(): string
    {
        return 'compras.propuesta_pago';
    }

    public function camposQbe(): array
    {
        $out = [];
        foreach (PropuestaPagoListadoFiltros::CAMPOS as $key => $meta) {
            $out[$key] = [
                'label' => $meta['label'] ?? $key,
                'type' => $meta['type'] ?? 'texto',
                'column' => $meta['column'] ?? $key,
            ];
        }

        return $out;
    }

    public function columnasVisibles(): array
    {
        return ['id', 'fecha', 'detalle', 'estado', 'empresa', 'total'];
    }

    public function etiquetasColumnas(): array
    {
        return [
            'id' => 'ID',
            'fecha' => 'Fecha',
            'detalle' => 'Detalle',
            'estado' => 'Estado',
            'empresa' => 'Empresa',
            'total' => 'Total',
        ];
    }

    public function valorCelda(object $row, string $key): string
    {
        if ($key === 'empresa') {
            return e((string) ($row->empresas->nombre ?? $row->nombreempresa ?? ''));
        }
        if ($key === 'fecha') {
            $v = $row->fecha ?? null;
            if ($v) {
                return e(is_string($v) ? date('d/m/Y', strtotime($v)) : $v->format('d/m/Y'));
            }

            return '';
        }
        if ($key === 'total') {
            $v = $row->monto_total ?? $row->total ?? $row->monto ?? null;
            if (is_numeric($v)) {
                return e(number_format((float) $v, 2, ',', '.'));
            }
        }

        return e((string) ($row->{$key} ?? ''));
    }

    public function resolverFiltros(Request $request): array
    {
        return PropuestaPagoListadoFiltros::resolverDesdeRequest($request);
    }

    public function paraQueryString(array $filtros): array
    {
        return PropuestaPagoListadoFiltros::paraQueryString($filtros);
    }

    public function consultar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        return app(PropuestaPagoRepositoryInterface::class)->leePropuestaPago($filtros, $paginar);
    }

    public function urlDetalle(object $row): ?string
    {
        $id = (int) ($row->id ?? 0);
        if ($id < 1 || ! can('editar-propuesta-pago', false)) {
            return null;
        }

        return route('editar_propuesta_pago', [
            'id' => $id,
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ]);
    }

    public function plantillas(): array
    {
        return [
            [
                'clave' => 'borrador',
                'titulo' => 'Propuestas en borrador',
                'descripcion' => 'Estado BORRADOR',
                'query' => [
                    'consultar' => 1,
                    'estado' => 'BORRADOR',
                ],
            ],
            [
                'clave' => 'exportado',
                'titulo' => 'Propuestas exportadas',
                'descripcion' => 'Estado EXPORTADO',
                'query' => [
                    'consultar' => 1,
                    'estado' => 'EXPORTADO',
                ],
            ],
            [
                'clave' => 'todas',
                'titulo' => 'Todas las propuestas',
                'descripcion' => 'Sin filtro de estado',
                'query' => ['consultar' => 1],
            ],
        ];
    }
}
