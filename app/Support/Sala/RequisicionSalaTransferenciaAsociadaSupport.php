<?php

namespace App\Support\Sala;

use App\Models\Sala\RequisicionSala;
use App\Models\Sala\RequisicionSalaArticulo;
use App\Models\Stock\Transferencia_Mercaderia;

final class RequisicionSalaTransferenciaAsociadaSupport
{
    public static function mensajeBloqueoCambioArticulo(): string
    {
        return 'No se puede cambiar el artículo en líneas incluidas en la transferencia al laboratorio.';
    }

    public static function mensajeBloqueoEliminarLinea(): string
    {
        return 'No se puede eliminar líneas incluidas en la transferencia al laboratorio.';
    }

    public static function tieneTransferenciaLaboratorio(RequisicionSala|int $req): bool
    {
        return self::transferenciaLaboratorio($req) !== null;
    }

    public static function transferenciaLaboratorio(RequisicionSala|int $req): ?Transferencia_Mercaderia
    {
        $model = self::resolverModelo($req);
        if ($model === null) {
            return null;
        }

        $needle = self::needleObservacion($model);

        return Transferencia_Mercaderia::query()
            ->where('observacion', 'like', '%'.$needle.'%')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * IDs de líneas reparación/devolución incluidas en la TM (artículo no editable).
     *
     * @return list<int>
     */
    public static function idsLineasArticuloBloqueadas(RequisicionSala $req): array
    {
        if (! self::tieneTransferenciaLaboratorio($req)) {
            return [];
        }

        $req->loadMissing('requisicion_sala_articulos');

        return RequisicionSalaLineasLaboratorioSupport::lineasTransferibles($req)
            ->pluck('id')
            ->filter()
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Valida que no se cambie el artículo ni se eliminen líneas transferidas.
     */
    public static function validarActualizacion(RequisicionSala $req, array $data): ?string
    {
        if (! self::tieneTransferenciaLaboratorio($req)) {
            return null;
        }

        $req->loadMissing(['requisicion_sala_articulos.articulos']);
        $lineasTm = RequisicionSalaLineasLaboratorioSupport::lineasTransferibles($req);
        if ($lineasTm->isEmpty()) {
            return null;
        }

        /** @var array<int, RequisicionSalaArticulo> $porId */
        $porId = $lineasTm->keyBy('id')->all();
        $idsEntrantes = $data['requisicion_sala_articulo_ids'] ?? [];
        $articuloIds = $data['articulo_ids'] ?? [];

        foreach ($porId as $idLinea => $lineaOrig) {
            $encontrada = false;
            foreach ($idsEntrantes as $i => $idEntrante) {
                if ((int) $idEntrante !== (int) $idLinea) {
                    continue;
                }
                $encontrada = true;
                $nuevoArticuloId = (int) ($articuloIds[$i] ?? 0);
                if ($nuevoArticuloId !== (int) $lineaOrig->articulo_id) {
                    $sku = (string) (optional($lineaOrig->articulos)->sku ?? $idLinea);

                    return self::mensajeBloqueoCambioArticulo().' (SKU '.$sku.').';
                }
                break;
            }
            if (! $encontrada) {
                $sku = (string) (optional($lineaOrig->articulos)->sku ?? $idLinea);

                return self::mensajeBloqueoEliminarLinea().' (SKU '.$sku.').';
            }
        }

        return null;
    }

    private static function resolverModelo(RequisicionSala|int $req): ?RequisicionSala
    {
        if ($req instanceof RequisicionSala) {
            return $req;
        }

        return RequisicionSala::query()->find((int) $req);
    }

    private static function needleObservacion(RequisicionSala $req): string
    {
        return 'requisición sala #'.($req->numerorequisicion ?? $req->id);
    }
}
