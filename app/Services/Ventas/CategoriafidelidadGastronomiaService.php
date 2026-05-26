<?php

namespace App\Services\Ventas;

use App\Repositories\Ventas\CategoriafidelidadArticuloGastronomiaRepositoryInterface;
use App\Repositories\Ventas\CategoriafidelidadGastronomiaRepositoryInterface;
use Illuminate\Support\Facades\DB;

class CategoriafidelidadGastronomiaService
{
    public function __construct(
        private CategoriafidelidadGastronomiaRepositoryInterface $categoriafidelidadRepository,
        private CategoriafidelidadArticuloGastronomiaRepositoryInterface $categoriafidelidadArticuloRepository,
    ) {
    }

    public function guardar(array $data)
    {
        DB::beginTransaction();
        try {
            $categoria = $this->categoriafidelidadRepository->create([
                'nombre' => $data['nombre'],
                'codigo' => $data['codigo'],
            ]);
            $this->categoriafidelidadArticuloRepository->syncFromRequest($data, (int) $categoria->id);
            DB::commit();

            return $categoria;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function actualizar(array $data, int $id): void
    {
        DB::beginTransaction();
        try {
            $this->categoriafidelidadRepository->update([
                'nombre' => $data['nombre'],
                'codigo' => $data['codigo'],
            ], $id);
            $this->categoriafidelidadArticuloRepository->syncFromRequest($data, $id);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
