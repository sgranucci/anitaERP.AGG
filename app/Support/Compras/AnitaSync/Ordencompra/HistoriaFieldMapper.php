<?php

namespace App\Support\Compras\AnitaSync\Ordencompra;

/**
 * Mapeo campo a campo: legcompra → ordencompra_historia.
 */
final class HistoriaFieldMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function mapAll(object $row, OrdencompraAnitaSyncContext $ctx, int $ordencompraId): array
    {
        return [
            'ordencompra_id' => self::mapOrdencompraId($ordencompraId),
            'sector_legajocompra_id' => self::mapSectorLegajocompraId($ctx),
            'fecha' => self::mapFecha($row, $ctx),
            'observacion' => self::mapObservacion($row),
            'leyenda' => self::mapLeyenda($row),
            'creousuario_id' => self::mapCreousuarioId($row, $ctx),
        ];
    }

    public static function mapOrdencompraId(int $ordencompraId): int
    {
        return $ordencompraId;
    }

    public static function mapSectorLegajocompraId(OrdencompraAnitaSyncContext $ctx): ?int
    {
        return $ctx->sectorComprasId();
    }

    public static function mapFecha(object $row, OrdencompraAnitaSyncContext $ctx): ?string
    {
        return $ctx->fechaHoraAnita($row->legc_fecha ?? null, $row->legc_hora ?? null);
    }

    public static function mapObservacion(object $row): ?string
    {
        $obs = trim((string) ($row->legc_observacion ?? ''));

        return $obs !== '' ? mb_substr($obs, 0, 255) : null;
    }

    public static function mapLeyenda(object $row): ?string
    {
        $est = trim((string) ($row->legc_estado ?? ''));
        if ($est === '') {
            return null;
        }

        return 'Estado Anita: '.$est;
    }

    public static function mapCreousuarioId(object $row, OrdencompraAnitaSyncContext $ctx): int
    {
        return $ctx->fkUsuarioAnita($row->legc_usuario ?? '');
    }
}
