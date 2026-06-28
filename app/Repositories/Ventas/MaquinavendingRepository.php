<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Maquinavending;
use App\Models\Ventas\MaquinavendingArticulo;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Support\Facades\DB;

class MaquinavendingRepository implements MaquinavendingRepositoryInterface
{
    public function __construct(
        private Maquinavending $model,
        private MaquinavendingArticulo $articuloModel,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function all()
    {
        $query = $this->model->with(['empresa', 'ubicacion', 'puntoventa', 'deposito'])
            ->withCount('articulos')
            ->orderBy('empresa_id')
            ->orderBy('nombre');

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);

        return $query->get();
    }

    public function existeRegistro(): bool
    {
        $query = $this->model->newQuery();
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);

        return $query->exists();
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $articulos = $this->extraerLineasArticulos($data);
            $cabecera = $this->filtrarCabecera($data);
            $registro = $this->model->create($cabecera);
            $this->sincronizarArticulos((int) $registro->id, $articulos);

            return $registro->load(['empresa', 'ubicacion', 'puntoventa', 'deposito', 'articulos.articulo']);
        });
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $registro = $this->model->findOrFail($id);
            $articulos = $this->extraerLineasArticulos($data);
            $cabecera = $this->filtrarCabecera($data);
            $registro->update($cabecera);
            $this->sincronizarArticulos((int) $registro->id, $articulos);

            return $registro->fresh(['empresa', 'ubicacion', 'puntoventa', 'deposito', 'articulos.articulo']);
        });
    }

    public function delete($id)
    {
        return $this->model->destroy($id);
    }

    public function find($id)
    {
        return $this->model->with(['empresa', 'ubicacion', 'puntoventa', 'deposito', 'articulos.articulo'])->find($id);
    }

    public function findOrFail($id)
    {
        return $this->model->with(['empresa', 'ubicacion', 'puntoventa', 'deposito', 'articulos.articulo'])->findOrFail($id);
    }

    /** @return list<array{numero_rulo:int, articulo_id:int, precio_lista:?float}> */
    private function extraerLineasArticulos(array $data): array
    {
        $numeros = $data['numero_rulo'] ?? [];
        $articuloIds = $data['articulo_ids'] ?? [];
        $precios = $data['precio_lista'] ?? [];
        $lineas = [];

        if (! is_array($numeros) || ! is_array($articuloIds)) {
            return [];
        }

        $total = max(count($numeros), count($articuloIds));
        for ($i = 0; $i < $total; $i++) {
            $numero = (int) ($numeros[$i] ?? 0);
            $articuloId = (int) ($articuloIds[$i] ?? 0);
            if ($numero <= 0 && $articuloId <= 0) {
                continue;
            }
            if ($numero <= 0 || $articuloId <= 0) {
                continue;
            }
            $precioRaw = is_array($precios) ? ($precios[$i] ?? null) : null;
            $precioLista = ($precioRaw !== null && $precioRaw !== '')
                ? round((float) $precioRaw, 2)
                : null;
            $lineas[] = [
                'numero_rulo' => $numero,
                'articulo_id' => $articuloId,
                'precio_lista' => $precioLista,
            ];
        }

        return $lineas;
    }

    private function filtrarCabecera(array $data): array
    {
        return collect($data)->only([
            'codigo_anita',
            'empresa_id',
            'nombre',
            'puntoventa_id',
            'ubicacion_id',
            'deposito_id',
            'listaprecio_id',
            'codigo_arca',
            'numero_serie',
        ])->all();
    }

    /** @param list<array{numero_rulo:int, articulo_id:int, precio_lista:?float}> $lineas */
    private function sincronizarArticulos(int $maquinavendingId, array $lineas): void
    {
        $this->articuloModel->where('maquinavending_id', $maquinavendingId)->delete();

        foreach ($lineas as $linea) {
            $this->articuloModel->create([
                'maquinavending_id' => $maquinavendingId,
                'numero_rulo' => $linea['numero_rulo'],
                'articulo_id' => $linea['articulo_id'],
                'precio_lista' => $linea['precio_lista'],
            ]);
        }
    }
}
