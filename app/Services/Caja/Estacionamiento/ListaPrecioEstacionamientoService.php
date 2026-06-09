<?php

namespace App\Services\Caja\Estacionamiento;

use App\Repositories\Caja\Estacionamiento\ListaPrecioEstacionamientoItemRepositoryInterface;
use App\Repositories\Caja\Estacionamiento\ListaPrecioEstacionamientoRepositoryInterface;
use Auth;
use DB;

class ListaPrecioEstacionamientoService
{
    public function __construct(
        private readonly ListaPrecioEstacionamientoRepositoryInterface $listaRepository,
        private readonly ListaPrecioEstacionamientoItemRepositoryInterface $itemRepository,
    ) {}

    public function guarda(array $data): array
    {
        $cabecera = $this->armaCabecera($data, true);

        DB::beginTransaction();
        try {
            $lista = $this->listaRepository->create($cabecera);
            $this->itemRepository->syncFromRequest($data, (int) $lista->id);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        return ['mensaje' => 'ok', 'id' => $lista->id];
    }

    public function actualiza(array $data, int $id): array
    {
        $cabecera = $this->armaCabecera($data, false);

        DB::beginTransaction();
        try {
            unset($cabecera['creousuario_id']);
            $this->listaRepository->update($cabecera, $id);
            $this->itemRepository->syncFromRequest($data, $id);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        return ['mensaje' => 'ok'];
    }

    /**
     * @return array<string, mixed>
     */
    private function armaCabecera(array $data, bool $esAlta): array
    {
        $cabecera = [
            'empresa_id' => (int) ($data['empresa_id'] ?? 0),
            'categoria_automovil_id' => (int) ($data['categoria_automovil_id'] ?? 0),
            'moneda_id' => (int) ($data['moneda_id'] ?? 0),
        ];

        if ($esAlta) {
            $cabecera['creousuario_id'] = Auth::user()->id;
        }

        return $cabecera;
    }
}
