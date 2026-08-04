<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Recuento_Item;
use App\Support\Stock\ArticuloStockColorTalleSupport;

class Recuento_ItemRepository implements Recuento_ItemRepositoryInterface
{
    public function __construct(
        private readonly Articulo_Saldo_DepositoRepositoryInterface $saldoRepository,
        private readonly Recuento_Item $model,
    ) {}

    public function syncFromRequest(array $data, int $recuentoId, int $depositoId): void
    {
        if (! isset($data['articulo_ids']) || ! is_array($data['articulo_ids'])) {
            $this->model->where('recuento_id', $recuentoId)->delete();

            return;
        }

        $idsExistentes = $this->model->where('recuento_id', $recuentoId)->pluck('id')->all();
        $idsExistentesFlip = array_flip($idsExistentes);
        $idsEntrantes = $data['recuento_item_ids'] ?? [];

        $idsAConservar = [];
        $aActualizar = [];
        $aInsertar = [];

        $n = count($data['articulo_ids']);
        for ($i = 0; $i < $n; $i++) {
            $articuloId = (int) ($data['articulo_ids'][$i] ?? 0);
            if ($articuloId <= 0) {
                continue;
            }

            $colorRaw = isset($data['colores_id'][$i]) ? (int) $data['colores_id'][$i] : 0;
            $talleRaw = isset($data['talles_id'][$i]) ? (int) $data['talles_id'][$i] : 0;
            [$colorKey, $talleKey] = ArticuloStockColorTalleSupport::claveSaldo(
                $colorRaw > 0 ? $colorRaw : null,
                $talleRaw > 0 ? $talleRaw : null
            );

            $cantidadContada = (float) ($data['cantidades_contadas'][$i] ?? 0);
            $saldoSistema = isset($data['saldos_sistema'][$i]) && $data['saldos_sistema'][$i] !== ''
                ? (float) $data['saldos_sistema'][$i]
                : $this->saldoRepository->saldoVariante(
                    $articuloId,
                    $depositoId,
                    $colorKey > 0 ? $colorKey : null,
                    $talleKey > 0 ? $talleKey : null
                );

            $articulo = Articulo::query()->select('id', 'unidadmedida_id')->find($articuloId);
            $unidadmedidaId = $data['unidadmedida_ids'][$i] ?? ($articulo->unidadmedida_id ?? null);

            $payload = [
                'recuento_id' => $recuentoId,
                'articulo_id' => $articuloId,
                'color_id' => $colorKey,
                'talle_id' => $talleKey,
                'detalle' => $data['detalle_articulos'][$i] ?? '',
                'unidadmedida_id' => $unidadmedidaId ?: null,
                'saldo_sistema' => $saldoSistema,
                'cantidad_contada' => $cantidadContada,
            ];

            $idCandidato = $idsEntrantes[$i] ?? null;
            $idCandidato = ($idCandidato === null || $idCandidato === '') ? null : (int) $idCandidato;

            if ($idCandidato !== null && isset($idsExistentesFlip[$idCandidato])) {
                $aActualizar[$idCandidato] = $payload;
                $idsAConservar[] = $idCandidato;
            } else {
                $aInsertar[] = $payload;
            }
        }

        $queryEliminar = $this->model->where('recuento_id', $recuentoId);
        if (! empty($idsAConservar)) {
            $queryEliminar->whereNotIn('id', $idsAConservar);
        }
        $queryEliminar->delete();

        foreach ($aActualizar as $id => $payload) {
            $registro = $this->model->where('id', $id)->where('recuento_id', $recuentoId)->first();
            if ($registro) {
                $registro->update($payload);
            }
        }

        foreach ($aInsertar as $payload) {
            $this->model->create($payload);
        }
    }
}
