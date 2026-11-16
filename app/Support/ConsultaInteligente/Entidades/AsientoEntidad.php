<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente\Entidades;

use App\Models\Contable\Asiento;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Contable\AsientoListadoFiltros;
use App\Support\ConsultaInteligente\EntidadSemantica;
use App\Support\Listado\ListadoQbeSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class AsientoEntidad implements EntidadSemantica
{
    public function clave(): string
    {
        return 'asiento';
    }

    public function nombre(): string
    {
        return 'Asientos contables';
    }

    public function descripcion(): string
    {
        return 'Asientos del ERP: número, fecha, tipo, empresa y estado de aprobación.';
    }

    public function icono(): string
    {
        return 'fa-book';
    }

    public function modulo(): string
    {
        return 'Contable';
    }

    public function color(): string
    {
        return '#1A5276';
    }

    public function permisoListar(): string
    {
        return 'listar-asiento';
    }

    public function rutaNativa(): string
    {
        return 'asiento';
    }

    public function recurso(): string
    {
        return 'contable.asiento';
    }

    public function camposQbe(): array
    {
        $out = [];
        foreach (AsientoListadoFiltros::CAMPOS as $key => $meta) {
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
        return ['id', 'numeroasiento', 'fecha', 'nombreempresa', 'nombretipoasiento', 'estado_aprobacion'];
    }

    public function etiquetasColumnas(): array
    {
        return [
            'id' => 'ID',
            'numeroasiento' => 'Número',
            'fecha' => 'Fecha',
            'nombreempresa' => 'Empresa',
            'nombretipoasiento' => 'Tipo',
            'estado_aprobacion' => 'Estado',
            'observacion' => 'Observación',
        ];
    }

    public function valorCelda(object $row, string $key): string
    {
        $v = $row->{$key} ?? '';
        if ($key === 'fecha' && $v) {
            return e(date('d/m/Y', strtotime((string) $v)));
        }

        return e((string) $v);
    }

    public function resolverFiltros(Request $request): array
    {
        $f = AsientoListadoFiltros::resolverDesdeRequest($request);
        $qbe = ListadoQbeSupport::resolverDesdeRequest(
            $request,
            $this->camposQbe(),
            static fn (string $op, string $campo): string => $op
        );
        if (ListadoQbeSupport::tieneCriterios($qbe)) {
            $f['qbe'] = $qbe;
            $f['filtro_modo'] = 'qbe';
        }

        return $f;
    }

    public function paraQueryString(array $filtros): array
    {
        $out = AsientoListadoFiltros::paraQueryString($filtros);
        if (! empty($filtros['qbe']) && is_array($filtros['qbe'])) {
            $out['qbe'] = $filtros['qbe'];
            $out['filtro_modo'] = 'qbe';
        }

        return $out;
    }

    public function consultar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        $query = Asiento::query()
            ->select([
                'asiento.id as id',
                'asiento.numeroasiento as numeroasiento',
                'asiento.fecha as fecha',
                'asiento.observacion as observacion',
                'asiento.estado_aprobacion as estado_aprobacion',
                'empresa.nombre as nombreempresa',
                'tipoasiento.nombre as nombretipoasiento',
            ])
            ->join('tipoasiento', 'tipoasiento.id', '=', 'asiento.tipoasiento_id')
            ->join('empresa', 'empresa.id', '=', 'asiento.empresa_id')
            ->orderByDesc('asiento.id');

        if (ListadoQbeSupport::tieneCriterios(is_array($filtros['qbe'] ?? null) ? $filtros['qbe'] : [])) {
            ListadoQbeSupport::aplicar($query, $filtros['qbe'], function (Builder $q, array $criterio): void {
                $campo = (string) ($criterio['campo'] ?? '');
                $meta = AsientoListadoFiltros::CAMPOS[$campo] ?? null;
                if (! $meta) {
                    return;
                }
                $column = (string) $meta['column'];
                $op = (string) ($criterio['op'] ?? 'contiene');
                $valor = (string) ($criterio['valor'] ?? '');
                $hasta = (string) ($criterio['valor_hasta'] ?? '');
                $type = (string) ($meta['type'] ?? 'texto');
                if ($type === 'fecha') {
                    if ($op === 'entre') {
                        if ($valor !== '') {
                            $q->whereDate($column, '>=', $valor);
                        }
                        if ($hasta !== '') {
                            $q->whereDate($column, '<=', $hasta);
                        }

                        return;
                    }
                    if ($op === 'este_mes') {
                        $q->whereDate($column, '>=', now()->startOfMonth()->toDateString())
                            ->whereDate($column, '<=', now()->endOfMonth()->toDateString());

                        return;
                    }
                    if ($op === 'mes_anterior') {
                        $m = now()->subMonthNoOverflow();
                        $q->whereDate($column, '>=', $m->copy()->startOfMonth()->toDateString())
                            ->whereDate($column, '<=', $m->copy()->endOfMonth()->toDateString());

                        return;
                    }
                    if ($valor !== '') {
                        match ($op) {
                            'igual', 'es_el_dia' => $q->whereDate($column, '=', $valor),
                            'mayor', 'posterior_a' => $q->whereDate($column, '>', $valor),
                            'menor', 'anterior_a' => $q->whereDate($column, '<', $valor),
                            'desde' => $q->whereDate($column, '>=', $valor),
                            'hasta' => $q->whereDate($column, '<=', $valor),
                            default => $q->whereDate($column, '=', $valor),
                        };
                    }

                    return;
                }
                if ($type === 'entero') {
                    if ($op === 'igual' && $valor !== '') {
                        $q->where($column, (int) $valor);
                    }

                    return;
                }
                if ($op === 'igual') {
                    $q->where($column, $valor);
                } elseif ($op === 'empieza') {
                    $q->where($column, 'like', $valor.'%');
                } elseif ($op === 'termina') {
                    $q->where($column, 'like', '%'.$valor);
                } else {
                    $q->where($column, 'like', '%'.$valor.'%');
                }
            });
        } else {
            AsientoListadoFiltros::aplicar($query, $filtros);
        }

        app(EmpresaRepositoryInterface::class)->aplicarFiltroEmpresasAsignadas($query, 'asiento.empresa_id');

        if ($paginar) {
            return $query->paginate(10);
        }

        return $query->limit(2000)->get();
    }

    public function urlDetalle(object $row): ?string
    {
        $id = (int) ($row->id ?? 0);
        if ($id < 1 || ! can('editar-asiento', false)) {
            return null;
        }

        return route('editar_asiento', [
            'id' => $id,
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ]);
    }

    public function plantillas(): array
    {
        return [
            [
                'clave' => 'este_mes',
                'titulo' => 'Asientos este mes',
                'descripcion' => 'Fecha del mes en curso',
                'query' => [
                    'consultar' => 1,
                    'filtro_modo' => 'qbe',
                    'qbe' => [
                        'entre_grupos' => 'and',
                        'grupos' => [[
                            'logic' => 'and',
                            'not' => 0,
                            'criterios' => [[
                                'campo' => 'fecha',
                                'op' => 'este_mes',
                                'valor' => '',
                                'valor_hasta' => '',
                            ]],
                        ]],
                    ],
                ],
            ],
        ];
    }
}
