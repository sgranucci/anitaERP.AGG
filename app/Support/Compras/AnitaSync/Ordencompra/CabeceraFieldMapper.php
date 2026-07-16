<?php

namespace App\Support\Compras\AnitaSync\Ordencompra;

/**
 * Mapeo campo a campo: pendmaep → ordencompra.
 */
final class CabeceraFieldMapper
{
    public static function mapNumeroordencompra(object $row): int
    {
        return (int) $row->penmp_nro;
    }

    public static function mapFecha(object $row, OrdencompraAnitaSyncContext $ctx): ?string
    {
        return $ctx->fechaYmd($row->penmp_fecha ?? null);
    }

    public static function mapFechaentrega(object $row, OrdencompraAnitaSyncContext $ctx): ?string
    {
        return $ctx->fechaYmd($row->penmp_fecha_ent ?? null);
    }

    public static function mapEmpresaId(object $row): int
    {
        return (int) ($row->penmp_empresa ?? 0);
    }

    public static function mapRequisicionId(object $row, OrdencompraAnitaSyncContext $ctx): ?int
    {
        return $ctx->fkRequisicionPorNumero($row->penmp_requisicion ?? null);
    }

    public static function mapCentrocostoId(object $row, OrdencompraAnitaSyncContext $ctx): ?int
    {
        return $ctx->fkCentrocosto($row->penmp_ccosto ?? null);
    }

    public static function mapDetalle(object $row): string
    {
        $detalle = trim((string) ($row->penmp_leyenda ?? ''));
        if ($detalle !== '') {
            return $detalle;
        }

        $nro = (int) ($row->penmp_nro ?? 0);

        return $nro > 0 ? "OC {$nro}" : 'Importada desde Anita';
    }

    public static function mapComentario(object $row): string
    {
        $partes = array_filter([
            isset($row->penmp_fecha_ing) ? 'Fecha ingreso Anita: '.$row->penmp_fecha_ing : null,
            isset($row->penmp_hora_ing) ? 'Hora: '.trim((string) $row->penmp_hora_ing) : null,
            isset($row->penmp_usuario_ini) ? 'Usuario ini: '.$row->penmp_usuario_ini : null,
            isset($row->penmp_razon_susp) && trim((string) $row->penmp_razon_susp) !== ''
                ? 'Suspensión: '.trim((string) $row->penmp_razon_susp) : null,
        ]);

        return implode(' | ', $partes) ?: 'Importada desde Anita';
    }

    public static function mapLugarentrega(object $row): ?string
    {
        $v = trim((string) ($row->penmp_entrega ?? ''));

        return $v !== '' ? $v : null;
    }

    public static function mapTransporteId(object $row, OrdencompraAnitaSyncContext $ctx): ?int
    {
        return $ctx->fkTransporte($row->penmp_expreso ?? null);
    }

    public static function mapTratamiento(object $row, OrdencompraAnitaSyncContext $ctx): string
    {
        return $ctx->mapTratamientoAnticipo($row->penmp_es_anticipo ?? 'N');
    }

    public static function mapProveedorId(object $row, OrdencompraAnitaSyncContext $ctx): ?int
    {
        return $ctx->fkProveedor($row->penmp_proveedor ?? null);
    }

    public static function mapCondicioncompraId(object $row, OrdencompraAnitaSyncContext $ctx): ?int
    {
        return $ctx->fkCondicioncompra($row->penmp_cond_compra ?? null);
    }

    public static function mapCondicionentregaId(object $row, OrdencompraAnitaSyncContext $ctx): ?int
    {
        return $ctx->fkCondicionentrega($row->penmp_cond_entrega ?? null);
    }

    public static function mapCondicionpagoId(object $row, OrdencompraAnitaSyncContext $ctx): ?int
    {
        return $ctx->fkCondicionpago($row->penmp_cond_pago ?? null);
    }

    public static function mapDescuento(object $row): ?float
    {
        if (! isset($row->penmp_dto)) {
            return null;
        }

        return (float) $row->penmp_dto;
    }

    public static function mapEstadoordencompra(object $row, OrdencompraAnitaSyncContext $ctx): string
    {
        return $ctx->mapEstadoOc($row->penmp_estado ?? '0');
    }

    public static function mapSectorLegajocompraId(OrdencompraAnitaSyncContext $ctx): ?int
    {
        return $ctx->sectorComprasId();
    }

    public static function mapCreousuarioId(OrdencompraAnitaSyncContext $ctx): int
    {
        return $ctx->usuarioSyncId;
    }

    /**
     * @return array<string, mixed>
     */
    public static function mapAll(object $row, OrdencompraAnitaSyncContext $ctx): array
    {
        return [
            'numeroordencompra' => self::mapNumeroordencompra($row),
            'fecha' => self::mapFecha($row, $ctx),
            'fechaentrega' => self::mapFechaentrega($row, $ctx),
            'empresa_id' => self::mapEmpresaId($row),
            'requisicion_id' => self::mapRequisicionId($row, $ctx),
            'centrocosto_id' => self::mapCentrocostoId($row, $ctx),
            'detalle' => self::mapDetalle($row),
            'comentario' => self::mapComentario($row),
            'lugarentrega' => self::mapLugarentrega($row),
            'transporte_id' => self::mapTransporteId($row, $ctx),
            'tratamiento' => self::mapTratamiento($row, $ctx),
            'proveedor_id' => self::mapProveedorId($row, $ctx),
            'condicioncompra_id' => self::mapCondicioncompraId($row, $ctx),
            'condicionentrega_id' => self::mapCondicionentregaId($row, $ctx),
            'condicionpago_id' => self::mapCondicionpagoId($row, $ctx),
            'descuento' => self::mapDescuento($row),
            'estadoordencompra' => self::mapEstadoordencompra($row, $ctx),
            'sector_legajocompra_id' => self::mapSectorLegajocompraId($ctx),
            'creousuario_id' => self::mapCreousuarioId($ctx),
        ];
    }
}
