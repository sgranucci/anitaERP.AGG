<?php

namespace App\Services\Compras;

use App\Models\Compras\Listaprecio_Proveedor_Estado;
use App\Repositories\Compras\Listaprecio_Proveedor_ArchivoRepositoryInterface;
use App\Repositories\Compras\Listaprecio_Proveedor_ArticuloRepositoryInterface;
use App\Repositories\Compras\Listaprecio_Proveedor_EstadoRepositoryInterface;
use App\Repositories\Compras\Listaprecio_ProveedorRepositoryInterface;
use Auth;
use DB;

class Listaprecio_ProveedorService
{
    public function __construct(
        private Listaprecio_ProveedorRepositoryInterface $listaprecioProveedorRepository,
        private Listaprecio_Proveedor_EstadoRepositoryInterface $listaprecioProveedorEstadoRepository,
        private Listaprecio_Proveedor_ArticuloRepositoryInterface $listaprecioProveedorArticuloRepository,
        private Listaprecio_Proveedor_ArchivoRepositoryInterface $listaprecioProveedorArchivoRepository,
    ) {}

    public function guarda($request): array
    {
        $activa = Listaprecio_Proveedor_Estado::$enumEstado[0]['nombre'];
        $data = $request->all();
        $data['creousuario_id'] = Auth::user()->id;
        $data['estado'] = $activa;

        $cabecera = $this->armaCabecera($data, true);

        DB::beginTransaction();
        try {
            $lista = $this->listaprecioProveedorRepository->create($cabecera);
            $this->listaprecioProveedorEstadoRepository->createInicial(
                $lista->id,
                $activa,
                Auth::user()->id,
                'Alta de lista de precios'
            );
            $this->listaprecioProveedorArticuloRepository->syncFromRequest($data, $lista->id, Auth::user()->id);
            $this->listaprecioProveedorArchivoRepository->create($request, $lista->id);
            $this->listaprecioProveedorRepository->persistirEnAnita((int) $lista->id);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        return ['mensaje' => 'ok'];
    }

    public function actualiza($request, int $id): array
    {
        $data = $request->all();
        $existente = $this->listaprecioProveedorRepository->find($id);
        $cabecera = $this->armaCabecera($data, false);
        if (isset($data['estado']) && Listaprecio_Proveedor_Estado::esNombreEstadoValido((string) $data['estado'])) {
            $cabecera['estado'] = $data['estado'];
        } else {
            $cabecera['estado'] = $existente->estado;
        }

        DB::beginTransaction();
        try {
            unset($cabecera['creousuario_id']);
            $this->listaprecioProveedorRepository->update($cabecera, $id);
            $this->listaprecioProveedorArticuloRepository->syncFromRequest($data, $id, Auth::user()->id);
            $this->listaprecioProveedorArchivoRepository->update($request, $id);
            $this->listaprecioProveedorRepository->persistirEnAnita($id);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        return ['mensaje' => 'ok'];
    }

    public function cambiarEstado(int $id, string $observacion = ''): array
    {
        $lista = $this->listaprecioProveedorRepository->find($id);
        $actual = $lista->estado ?? '';
        $nuevo = Listaprecio_Proveedor_Estado::otroEstado($actual);
        if ($nuevo === null) {
            return ['mensaje' => 'error', 'errores' => 'Estado no reconocido.'];
        }

        DB::beginTransaction();
        try {
            $this->listaprecioProveedorRepository->update(['estado' => $nuevo], $id);
            $this->listaprecioProveedorEstadoRepository->creaEstado(
                $id,
                $nuevo,
                Auth::user()->id,
                $observacion !== '' ? $observacion : 'Cambio de estado de '.$actual.' a '.$nuevo
            );
            $this->listaprecioProveedorRepository->persistirEnAnita($id);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        return ['mensaje' => 'ok'];
    }

    public function leeHistoriaJson(int $listaprecio_proveedor_id)
    {
        $rows = $this->listaprecioProveedorEstadoRepository->leeHistoria($listaprecio_proveedor_id);

        return $rows->map(function ($e) {
            return [
                'fecha' => $e->created_at ? $e->created_at->format('Y-m-d H:i') : '',
                'estado' => $e->estado,
                'usuarios' => ['nombre' => $e->usuarios->nombre ?? ''],
                'observacion' => $e->observacion ?? '',
            ];
        });
    }

    private function armaCabecera(array $data, bool $esAlta): array
    {
        $row = [
            'proveedor_id' => $data['proveedor_id'] ?? null,
            'fecha' => $data['fecha'] ?? date('Y-m-d'),
            'nombre' => $data['nombre'] ?? '',
            'observaciones' => $data['observaciones'] ?? '',
            'condicionpago_id' => ! empty($data['condicionpago_id']) ? $data['condicionpago_id'] : null,
            'condicionentrega_id' => ! empty($data['condicionentrega_id']) ? $data['condicionentrega_id'] : null,
            'condicioncompra_id' => ! empty($data['condicioncompra_id']) ? $data['condicioncompra_id'] : null,
            'moneda_id' => ! empty($data['moneda_id']) ? $data['moneda_id'] : null,
        ];
        if ($esAlta) {
            $row['creousuario_id'] = $data['creousuario_id'] ?? Auth::user()->id;
            $row['estado'] = $data['estado'] ?? Listaprecio_Proveedor_Estado::$enumEstado[0]['nombre'];
        }

        return $row;
    }
}
