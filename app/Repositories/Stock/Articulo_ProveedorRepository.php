<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Articulo_Proveedor;
use App\Support\Compras\ArticuloProveedorCodigoSyncSupport;
use InvalidArgumentException;

class Articulo_ProveedorRepository implements Articulo_ProveedorRepositoryInterface
{
    protected $model;

    public function __construct(Articulo_Proveedor $model)
    {
        $this->model = $model;
    }

    public function syncFromRequest(array $data, int $articuloId): void
    {
        $preferidoProveedorId = isset($data['ap_preferido_proveedor_id']) && $data['ap_preferido_proveedor_id'] !== ''
            ? (int) $data['ap_preferido_proveedor_id']
            : null;

        if (! isset($data['ap_proveedor_ids']) || ! is_array($data['ap_proveedor_ids'])) {
            $this->model->where('articulo_id', $articuloId)->delete();

            return;
        }

        self::validarProveedoresUnicosEnRequest($data['ap_proveedor_ids']);

        $n = count($data['ap_proveedor_ids']);
        $idsGuardados = [];

        for ($i = 0; $i < $n; $i++) {
            $proveedorId = $data['ap_proveedor_ids'][$i] ?? null;
            $lineaIdRaw = $data['ap_linea_ids'][$i] ?? null;

            if ($proveedorId === null || $proveedorId === '') {
                if ($lineaIdRaw !== null && $lineaIdRaw !== '') {
                    $this->model->where('id', (int) $lineaIdRaw)
                        ->where('articulo_id', $articuloId)
                        ->delete();
                }

                continue;
            }

            $coef = (float) ($data['ap_coeficientes_conversion'][$i] ?? 1);
            if ($coef <= 0) {
                $coef = 1;
            }

            $activo = ($data['ap_activos'][$i] ?? '1') === '1' || ($data['ap_activos'][$i] ?? 1) === 1;
            $proveedorIdInt = (int) $proveedorId;

            $payload = [
                'articulo_id' => $articuloId,
                'proveedor_id' => $proveedorIdInt,
                'nombre_articulo_proveedor' => substr((string) ($data['ap_nombres_articulo_proveedor'][$i] ?? ''), 0, 255) ?: null,
                'codigobarra' => substr((string) ($data['ap_codigosbarra'][$i] ?? ''), 0, 50) ?: null,
                'codigo_articulo_proveedor' => substr((string) ($data['ap_codigos_articulo_proveedor'][$i] ?? ''), 0, 100) ?: null,
                'unidadmedida_compra_id' => ! empty($data['ap_unidadmedida_compra_ids'][$i]) ? (int) $data['ap_unidadmedida_compra_ids'][$i] : null,
                'coeficiente_conversion' => $coef,
                'activo' => $activo,
                'preferido' => $preferidoProveedorId !== null && $preferidoProveedorId === $proveedorIdInt,
            ];

            $lineaId = $lineaIdRaw;
            if ($lineaId !== null && $lineaId !== '') {
                $this->model->where('id', (int) $lineaId)
                    ->where('articulo_id', $articuloId)
                    ->update($payload);
                $idsGuardados[] = (int) $lineaId;
            } else {
                $row = $this->model->updateOrCreate(
                    [
                        'articulo_id' => $articuloId,
                        'proveedor_id' => $proveedorIdInt,
                    ],
                    $payload
                );
                $idsGuardados[] = (int) $row->id;
            }

            ArticuloProveedorCodigoSyncSupport::desdeCatalogo(
                $articuloId,
                $proveedorIdInt,
                $payload['codigo_articulo_proveedor']
            );
        }

        if ($idsGuardados !== []) {
            $this->model->where('articulo_id', $articuloId)
                ->whereNotIn('id', $idsGuardados)
                ->delete();
        } else {
            $this->model->where('articulo_id', $articuloId)->delete();
        }

        if ($preferidoProveedorId === null && $idsGuardados !== []) {
            $this->model->where('articulo_id', $articuloId)->update(['preferido' => false]);
        }
    }

    /**
     * @param  array<int, mixed>  $proveedorIds
     */
    public static function validarProveedoresUnicosEnRequest(array $proveedorIds): void
    {
        $vistos = [];

        foreach ($proveedorIds as $proveedorId) {
            if ($proveedorId === null || $proveedorId === '') {
                continue;
            }

            $id = (int) $proveedorId;
            if ($id <= 0) {
                continue;
            }

            if (isset($vistos[$id])) {
                throw new InvalidArgumentException('El proveedor está repetido en la solapa Proveedores. Cada proveedor puede figurar una sola vez por artículo.');
            }

            $vistos[$id] = true;
        }
    }
}
