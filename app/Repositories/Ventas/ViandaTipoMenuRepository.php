<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\ViandaTipoMenu;
use App\Models\Ventas\ViandaTipoMenuArticulo;
use App\Support\Ventas\Vianda\ViandaEmpresaSupport;
use App\Support\Ventas\ViandaDiaSemanaSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ViandaTipoMenuRepository implements ViandaTipoMenuRepositoryInterface
{
    public function __construct(
        private ViandaTipoMenu $model,
        private ViandaTipoMenuArticulo $articuloModel,
    ) {
    }

    public function all(?int $empresaId = null)
    {
        $query = $this->model->with(['empresa', 'articulos.articulo'])
            ->orderBy('empresa_id')
            ->orderBy('nombre');

        // Filtro tradicional del sistema: solo empresas asignadas al usuario en sesión.
        ViandaEmpresaSupport::aplicarFiltroAsignadas($query, 'empresa_id');

        if ($empresaId !== null && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        return $query->get();
    }

    public function existeRegistro(): bool
    {
        return $this->model->newQuery()->exists();
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $lineas = $this->extraerLineasPorDia($data);
            $cabecera = $this->filtrarCabecera($data);
            $registro = $this->model->create($cabecera);
            $this->sincronizarArticulos((int) $registro->id, $lineas);

            return $registro->load(['articulos.articulo']);
        });
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $registro = $this->model->findOrFail($id);
            $lineas = $this->extraerLineasPorDia($data);
            $cabecera = $this->filtrarCabecera($data);
            $registro->update($cabecera);
            $this->sincronizarArticulos((int) $registro->id, $lineas);

            return $registro->fresh(['articulos.articulo']);
        });
    }

    public function delete($id)
    {
        return $this->model->destroy($id);
    }

    public function find($id)
    {
        return $this->model->with(['empresa', 'articulos.articulo'])->find($id);
    }

    public function findOrFail($id)
    {
        return $this->model->with(['empresa', 'articulos.articulo'])->findOrFail($id);
    }

    /**
     * @return array<int, Collection<int, ViandaTipoMenuArticulo>>
     */
    public function agruparArticulosPorDia(ViandaTipoMenu $tipoMenu): array
    {
        $agrupado = [];
        foreach (ViandaDiaSemanaSupport::diasValidos() as $dia) {
            $agrupado[$dia] = collect();
        }

        foreach ($tipoMenu->articulos as $linea) {
            $dia = (int) $linea->dia_semana;
            if (! ViandaDiaSemanaSupport::diaValido($dia)) {
                continue;
            }
            $agrupado[$dia]->push($linea);
        }

        foreach ($agrupado as $dia => $coleccion) {
            $agrupado[$dia] = $coleccion->sortBy('orden')->values();
        }

        return $agrupado;
    }

    public function resumenDia(ViandaTipoMenu $tipoMenu, int $dia): string
    {
        $items = $tipoMenu->articulos
            ->where('dia_semana', $dia)
            ->sortBy('orden')
            ->map(function ($linea) {
                $articulo = $linea->articulo;
                if ($articulo === null) {
                    return null;
                }

                return trim($articulo->sku.' — '.$articulo->descripcion);
            })
            ->filter()
            ->values();

        return $items->implode('; ');
    }

    /** @return list<array{dia_semana:int, articulo_id:int, orden:int}> */
    private function extraerLineasPorDia(array $data): array
    {
        $porDia = $data['articulo_por_dia'] ?? [];
        if (! is_array($porDia)) {
            return [];
        }

        $lineas = [];
        foreach (ViandaDiaSemanaSupport::diasValidos() as $dia) {
            $ids = $porDia[$dia] ?? $porDia[(string) $dia] ?? [];
            if (! is_array($ids)) {
                continue;
            }
            $orden = 0;
            foreach ($ids as $articuloId) {
                $articuloId = (int) $articuloId;
                if ($articuloId <= 0) {
                    continue;
                }
                $lineas[] = [
                    'dia_semana' => $dia,
                    'articulo_id' => $articuloId,
                    'orden' => ++$orden,
                ];
            }
        }

        return $lineas;
    }

    private function filtrarCabecera(array $data): array
    {
        return collect($data)->only([
            'empresa_id',
            'codigo_anita',
            'nombre',
            'estado',
        ])->all();
    }

    /** @param list<array{dia_semana:int, articulo_id:int, orden:int}> $lineas */
    private function sincronizarArticulos(int $tipoMenuId, array $lineas): void
    {
        $this->articuloModel->where('vianda_tipo_menu_id', $tipoMenuId)->delete();

        foreach ($lineas as $linea) {
            $this->articuloModel->create([
                'vianda_tipo_menu_id' => $tipoMenuId,
                'dia_semana' => $linea['dia_semana'],
                'articulo_id' => $linea['articulo_id'],
                'orden' => $linea['orden'],
            ]);
        }
    }
}
