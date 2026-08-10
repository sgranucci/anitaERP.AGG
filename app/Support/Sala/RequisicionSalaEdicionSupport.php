<?php

namespace App\Support\Sala;

use App\Models\Sala\CumplimientoRequisicionSala;
use App\Models\Sala\CumplimientoRequisicionSalaArticulo;
use App\Models\Sala\RequisicionSala;
use App\Models\Sala\RequisicionSalaEstado;

/**
 * Modos de edición post-aprobación de requisición de sala.
 *
 * - Edición completa: PENDIENTE / EN LABORATORIO / RECHAZADA.
 * - Edición menor: APROBADA / PARCIAL (datos menores + cambio de artículo por línea,
 *   sin tocar cantidad/destino/depósito ni agregar/quitar líneas).
 * - Reabrir/desaprobar: vuelve a PENDIENTE para cambio de negocio.
 */
final class RequisicionSalaEdicionSupport
{
    /** @return list<string> */
    public static function estadosEdicionCompleta(): array
    {
        return [
            self::nombreEstado('0'),
            self::nombreEstado('5'),
            self::nombreEstado('Z'),
        ];
    }

    /** @return list<string> */
    public static function estadosEdicionMenor(): array
    {
        return [
            self::nombreEstado('A'),
            self::nombreEstado('2'),
        ];
    }

    /** @return list<string> */
    public static function estadosReabrir(): array
    {
        return [
            self::nombreEstado('A'),
            self::nombreEstado('2'),
        ];
    }

    public static function permiteEdicionCompleta(?string $estado): bool
    {
        return in_array((string) $estado, self::estadosEdicionCompleta(), true);
    }

    public static function permiteEdicionMenor(?string $estado): bool
    {
        return in_array((string) $estado, self::estadosEdicionMenor(), true);
    }

    public static function permiteReabrir(?string $estado): bool
    {
        return in_array((string) $estado, self::estadosReabrir(), true);
    }

    /**
     * @return list<string>
     */
    public static function camposCabeceraMenores(): array
    {
        return [
            'comentario',
            'detalle',
            'zona_sala_id',
            'prioridad_sala_id',
            'fecha_entrega',
        ];
    }

    public static function cantidadCumplimientosActivos(RequisicionSala|int $req): int
    {
        $id = $req instanceof RequisicionSala ? (int) $req->id : (int) $req;
        if ($id <= 0) {
            return 0;
        }

        return (int) CumplimientoRequisicionSala::query()
            ->where('estado', CumplimientoRequisicionSala::ESTADO_ACTIVO)
            ->whereHas('articulos', fn ($q) => $q->where('requisicion_sala_id', $id))
            ->count();
    }

    public static function mensajeBloqueoReabrirPorCumplimientos(): string
    {
        return 'No se puede reabrir: hay cumplimientos activos. Revertí los cumplimientos primero y luego reabrí la requisición.';
    }

    public static function mensajeNoEditable(): string
    {
        return 'Solo se puede editar en estado PENDIENTE, EN LABORATORIO o RECHAZADA. '
            .'En APROBADA/PARCIAL solo se permiten cambios menores, o bien reabrir para un cambio de negocio.';
    }

    public static function mensajeBloqueoCambioArticuloPorCumplimiento(): string
    {
        return 'No se puede cambiar el artículo en líneas con cumplimientos activos. Revertí el cumplimiento primero.';
    }

    /**
     * IDs de líneas con al menos un cumplimiento activo (artículo no editable en edición menor).
     *
     * @return list<int>
     */
    public static function idsLineasConCumplimientoActivo(RequisicionSala|int $req): array
    {
        $id = $req instanceof RequisicionSala ? (int) $req->id : (int) $req;
        if ($id <= 0) {
            return [];
        }

        return CumplimientoRequisicionSalaArticulo::query()
            ->where('requisicion_sala_id', $id)
            ->whereHas(
                'cumplimiento',
                static fn ($q) => $q->where('estado', CumplimientoRequisicionSala::ESTADO_ACTIVO)
            )
            ->pluck('requisicion_sala_articulo_id')
            ->filter()
            ->map(static fn ($lineaId): int => (int) $lineaId)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Valida cambios de artículo en edición menor (TM laboratorio + cumplimientos activos).
     *
     * @return string|null Mensaje de error o null si ok.
     */
    public static function validarCambioArticuloEdicionMenor(RequisicionSala $req, array $data): ?string
    {
        $req->loadMissing(['requisicion_sala_articulos.articulos']);

        $idsTm = array_flip(RequisicionSalaTransferenciaAsociadaSupport::idsLineasArticuloBloqueadas($req));
        $idsCumplimiento = array_flip(self::idsLineasConCumplimientoActivo($req));

        $idsEntrantes = $data['requisicion_sala_articulo_ids'] ?? [];
        $articuloIds = $data['articulo_ids'] ?? [];

        foreach ($idsEntrantes as $i => $idEntrante) {
            $idLinea = ($idEntrante === null || $idEntrante === '') ? 0 : (int) $idEntrante;
            if ($idLinea <= 0) {
                continue;
            }

            $lineaOrig = $req->requisicion_sala_articulos->firstWhere('id', $idLinea);
            if ($lineaOrig === null) {
                continue;
            }

            $nuevoArticuloId = (int) ($articuloIds[$i] ?? 0);
            if ($nuevoArticuloId <= 0 || $nuevoArticuloId === (int) $lineaOrig->articulo_id) {
                continue;
            }

            $sku = (string) (optional($lineaOrig->articulos)->sku ?? $idLinea);

            if (isset($idsTm[$idLinea])) {
                return RequisicionSalaTransferenciaAsociadaSupport::mensajeBloqueoCambioArticulo()
                    .' (SKU '.$sku.').';
            }

            if (isset($idsCumplimiento[$idLinea])) {
                return self::mensajeBloqueoCambioArticuloPorCumplimiento()
                    .' (SKU '.$sku.').';
            }
        }

        return null;
    }

    private static function nombreEstado(string $valor): string
    {
        $idx = array_search($valor, array_column(RequisicionSalaEstado::$enumEstado, 'valor'), true);

        return RequisicionSalaEstado::$enumEstado[$idx]['nombre'];
    }
}
