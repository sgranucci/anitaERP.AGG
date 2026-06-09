<?php

namespace App\Repositories\Caja\Estacionamiento;

use App\Models\Caja\Estacionamiento\ListaPrecioEstacionamientoItem;
use Auth;

class ListaPrecioEstacionamientoItemRepository implements ListaPrecioEstacionamientoItemRepositoryInterface
{
    public function __construct(
        private readonly ListaPrecioEstacionamientoItem $model,
    ) {}

    public function syncFromRequest(array $data, int $listaPrecioEstacionamientoId): void
    {
        $usuarioId = (int) Auth::id();
        $renglones = $this->normalizarRenglones($data);

        $submittedIds = [];
        foreach ($renglones as $renglon) {
            if ($renglon['linea_id'] > 0) {
                $submittedIds[] = $renglon['linea_id'];
            }
        }

        $existentes = $this->model
            ->where('lista_precio_estacionamiento_id', $listaPrecioEstacionamientoId)
            ->pluck('id')
            ->all();
        $aBorrar = array_diff($existentes, $submittedIds);
        if ($aBorrar !== []) {
            $this->model->whereIn('id', $aBorrar)->delete();
        }

        foreach ($renglones as $renglon) {
            if ($renglon['item_id'] <= 0) {
                continue;
            }

            if ($renglon['precio'] === null) {
                if ($renglon['linea_id'] > 0) {
                    $this->model->where('id', $renglon['linea_id'])
                        ->where('lista_precio_estacionamiento_id', $listaPrecioEstacionamientoId)
                        ->delete();
                }

                continue;
            }

            if ($renglon['precio'] < 0 || $renglon['fecha_vigencia'] === '') {
                continue;
            }

            $payload = [
                'lista_precio_estacionamiento_id' => $listaPrecioEstacionamientoId,
                'item_estacionamiento_id' => $renglon['item_id'],
                'precio' => $renglon['precio'],
                'fecha_vigencia' => $renglon['fecha_vigencia'],
                'usuarioultcambio_id' => $usuarioId,
            ];

            if ($renglon['linea_id'] > 0) {
                $this->model->where('id', $renglon['linea_id'])
                    ->where('lista_precio_estacionamiento_id', $listaPrecioEstacionamientoId)
                    ->update($payload);
            } else {
                $this->model->create($payload);
            }
        }
    }

    /**
     * @return list<array{linea_id: int, item_id: int, precio: ?float, fecha_vigencia: string}>
     */
    private function normalizarRenglones(array $data): array
    {
        $renglones = [];

        if (! empty($data['precio_renglones']) && is_array($data['precio_renglones'])) {
            foreach ($data['precio_renglones'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $renglones[] = $this->mapearRenglon($row);
            }

            return $renglones;
        }

        // Compatibilidad con envío legacy (arrays paralelos)
        $itemIds = (array) ($data['item_ids'] ?? []);
        $lineaIds = (array) ($data['linea_ids'] ?? []);
        $precios = (array) ($data['precios'] ?? []);
        $fechas = (array) ($data['fechavigencias'] ?? []);
        $n = max(count($itemIds), count($lineaIds), count($precios), count($fechas));

        for ($i = 0; $i < $n; $i++) {
            $renglones[] = $this->mapearRenglon([
                'linea_id' => $lineaIds[$i] ?? '',
                'item_id' => $itemIds[$i] ?? '',
                'precio' => $precios[$i] ?? null,
                'fecha_vigencia' => $fechas[$i] ?? '',
            ]);
        }

        return $renglones;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{linea_id: int, item_id: int, precio: ?float, fecha_vigencia: string}
     */
    private function mapearRenglon(array $row): array
    {
        $precioRaw = $row['precio'] ?? null;
        $precio = null;
        if ($precioRaw !== null && $precioRaw !== '') {
            $precio = (float) $precioRaw;
        }

        return [
            'linea_id' => (int) ($row['linea_id'] ?? 0),
            'item_id' => (int) ($row['item_id'] ?? 0),
            'precio' => $precio,
            'fecha_vigencia' => trim((string) ($row['fecha_vigencia'] ?? '')),
        ];
    }
}
