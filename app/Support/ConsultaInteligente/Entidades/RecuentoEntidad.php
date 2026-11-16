<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente\Entidades;

use App\Repositories\Stock\RecuentoRepositoryInterface;
use App\Support\ConsultaInteligente\EntidadSemantica;
use App\Support\Stock\RecuentoListadoFiltros;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

final class RecuentoEntidad implements EntidadSemantica
{
    public function clave(): string
    {
        return 'recuento';
    }

    public function nombre(): string
    {
        return 'Recuentos de inventario';
    }

    public function descripcion(): string
    {
        return 'Recuentos: depósito, estado, usuario y fecha.';
    }

    public function icono(): string
    {
        return 'fa-clipboard-check';
    }

    public function modulo(): string
    {
        return 'Stock';
    }

    public function color(): string
    {
        return '#1A5276';
    }

    public function permisoListar(): string
    {
        return 'listar-recuento';
    }

    public function rutaNativa(): string
    {
        return 'recuento';
    }

    public function recurso(): string
    {
        return 'stock.recuento';
    }

    public function camposQbe(): array
    {
        $out = [];
        foreach (RecuentoListadoFiltros::CAMPOS as $key => $meta) {
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
        return ['id', 'codigo', 'fecha', 'deposito', 'empresa', 'usuario', 'tipo', 'estado'];
    }

    public function etiquetasColumnas(): array
    {
        return [
            'id' => 'ID',
            'codigo' => 'Código',
            'fecha' => 'Fecha',
            'deposito' => 'Depósito',
            'empresa' => 'Empresa',
            'usuario' => 'Usuario',
            'tipo' => 'Tipo',
            'estado' => 'Estado',
            'comentario' => 'Comentario',
        ];
    }

    public function valorCelda(object $row, string $key): string
    {
        if ($key === 'deposito') {
            return e((string) ($row->deposito->nombre ?? ''));
        }
        if ($key === 'empresa') {
            return e((string) ($row->empresa->nombre ?? ''));
        }
        if ($key === 'usuario') {
            return e((string) ($row->usuario->nombre ?? ''));
        }
        $v = $row->{$key} ?? '';
        if ($key === 'fecha' && $v) {
            return e(is_string($v) ? date('d/m/Y', strtotime($v)) : $v->format('d/m/Y'));
        }

        return e((string) $v);
    }

    public function resolverFiltros(Request $request): array
    {
        $filtros = RecuentoListadoFiltros::resolverDesdeRequest($request);

        return RecuentoListadoFiltros::aplicarAlcanceUsuario(
            $filtros,
            (int) (Auth::id() ?? 0)
        );
    }

    public function paraQueryString(array $filtros): array
    {
        return RecuentoListadoFiltros::paraQueryString($filtros);
    }

    public function consultar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        $filtros = RecuentoListadoFiltros::aplicarAlcanceUsuario(
            $filtros,
            (int) (Auth::id() ?? ($filtros['usuario_id'] ?? 0))
        );

        return app(RecuentoRepositoryInterface::class)->leeRecuentos($filtros, $paginar);
    }

    public function urlDetalle(object $row): ?string
    {
        $id = (int) ($row->id ?? 0);
        if ($id < 1 || ! can('editar-recuento', false)) {
            return null;
        }

        return route('editar_recuento', [
            'id' => $id,
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ]);
    }

    public function plantillas(): array
    {
        return [
            [
                'clave' => 'pendientes',
                'titulo' => 'Recuentos pendientes',
                'descripcion' => 'Estado PENDIENTE',
                'query' => [
                    'consultar' => 1,
                    'ver_todos_recuentos' => 1,
                    'filtro_modo' => 'campo',
                    'filtro_campo' => 'estado',
                    'filtro_operador' => 'igual',
                    'filtro_valor' => 'PENDIENTE',
                ],
            ],
            [
                'clave' => 'cerrado_parcial',
                'titulo' => 'Cierre parcial',
                'descripcion' => 'Estado CERRADO_PARCIAL',
                'query' => [
                    'consultar' => 1,
                    'ver_todos_recuentos' => 1,
                    'filtro_modo' => 'campo',
                    'filtro_campo' => 'estado',
                    'filtro_operador' => 'igual',
                    'filtro_valor' => 'CERRADO_PARCIAL',
                ],
            ],
        ];
    }
}
