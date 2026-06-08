<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Listaprecio_Proveedor;
use App\Models\Compras\Listaprecio_Proveedor_Articulo;
use App\Support\Compras\ArticuloProveedorCodigoSyncSupport;

class Listaprecio_Proveedor_ArticuloRepository implements Listaprecio_Proveedor_ArticuloRepositoryInterface
{
    protected $model;

    public function __construct(Listaprecio_Proveedor_Articulo $model)
    {
        $this->model = $model;
    }

    public function createRow(array $data)
    {
        return $this->model->create($data);
    }

    public function syncFromRequest(array $data, $listaprecio_proveedor_id, $usuario_id)
    {
        $submittedIds = [];
        if (! empty($data['linea_ids']) && is_array($data['linea_ids'])) {
            foreach ($data['linea_ids'] as $lid) {
                if ($lid !== null && $lid !== '') {
                    $submittedIds[] = (int) $lid;
                }
            }
        }

        $existentes = $this->model->where('listaprecio_proveedor_id', $listaprecio_proveedor_id)->pluck('id')->all();
        $aBorrar = array_diff($existentes, $submittedIds);
        if ($aBorrar !== []) {
            $this->model->whereIn('id', $aBorrar)->delete();
        }

        if (! isset($data['articulo_ids']) || ! is_array($data['articulo_ids'])) {
            return;
        }

        if ($data['articulo_ids'] === []) {
            return;
        }

        $n = count($data['articulo_ids']);
        for ($i = 0; $i < $n; $i++) {
            $articulo_id = $data['articulo_ids'][$i] ?? null;
            $lineaIdRaw = $data['linea_ids'][$i] ?? null;
            if ($articulo_id === null || $articulo_id === '') {
                if ($lineaIdRaw !== null && $lineaIdRaw !== '') {
                    $this->model->where('id', (int) $lineaIdRaw)
                        ->where('listaprecio_proveedor_id', $listaprecio_proveedor_id)
                        ->delete();
                }

                continue;
            }
            $precio = (float) ($data['precios'][$i] ?? 0);
            if ($precio < 0) {
                continue;
            }
            $fechavigencia = $data['fechavigencias'][$i] ?? null;
            if (empty($fechavigencia)) {
                continue;
            }
            $payload = [
                'listaprecio_proveedor_id' => $listaprecio_proveedor_id,
                'articulo_id' => $articulo_id,
                'precio' => $precio,
                'descuento' => (float) ($data['descuentos'][$i] ?? 0),
                'codigo_articulo_proveedor' => substr((string) ($data['codigos_articulo_proveedor'][$i] ?? ''), 0, 100),
                'fechavigencia' => $fechavigencia,
                'usuarioultcambio_id' => $usuario_id,
            ];
            $lineaId = $lineaIdRaw;
            if ($lineaId !== null && $lineaId !== '') {
                $this->model->where('id', (int) $lineaId)
                    ->where('listaprecio_proveedor_id', $listaprecio_proveedor_id)
                    ->update($payload);
            } else {
                $this->model->create($payload);
            }

            $lista = Listaprecio_Proveedor::query()->find($listaprecio_proveedor_id);
            if ($lista && $lista->proveedor_id) {
                ArticuloProveedorCodigoSyncSupport::desdeLista(
                    (int) $articulo_id,
                    (int) $lista->proveedor_id,
                    $payload['codigo_articulo_proveedor'] ?: null,
                    (int) $listaprecio_proveedor_id
                );
            }
        }
    }
}
