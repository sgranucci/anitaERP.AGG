<?php

namespace App\Support\Compras\AnitaSync\Surmar;

use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaSyncContext;

/**
 * pendmaep Surmar → ordencompra (sin empresa/ccosto/legajo/aprobación/anticipo en Anita).
 */
final class SurmarCabeceraFieldMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function mapAll(object $row, OrdencompraAnitaSyncContext $ctx): array
    {
        $empresaId = (int) config('ordencompra_anita_surmar.empresa_id', 3);
        $centrocostoId = (int) config('ordencompra_anita_surmar.centrocosto_id', 1);
        $nro = (int) ($row->penmp_nro ?? 0);
        $leyenda = trim((string) ($row->penmp_leyenda ?? ''));

        $comentarioPartes = array_filter([
            isset($row->penmp_fecha_ing) ? 'Fecha ingreso Anita: '.$row->penmp_fecha_ing : null,
            isset($row->penmp_hora_ing) ? 'Hora: '.trim((string) $row->penmp_hora_ing) : null,
            isset($row->penmp_razon_susp) && trim((string) $row->penmp_razon_susp) !== ''
                ? 'Suspensión: '.trim((string) $row->penmp_razon_susp) : null,
            'Origen: Anita Surmar',
        ]);

        $lugar = trim((string) ($row->penmp_entrega ?? ''));

        return [
            'numeroordencompra' => $nro,
            'fecha' => $ctx->fechaYmd($row->penmp_fecha ?? null),
            'fechaentrega' => $ctx->fechaYmd($row->penmp_fecha_ent ?? null) ?: $ctx->fechaYmd($row->penmp_fecha ?? null),
            'empresa_id' => $empresaId,
            'requisicion_id' => $ctx->fkRequisicionPorNumero($row->penmp_requisicion ?? null),
            'centrocosto_id' => $centrocostoId > 0 ? $centrocostoId : null,
            'detalle' => $leyenda !== '' ? $leyenda : ($nro > 0 ? "OC {$nro}" : 'Importada desde Anita Surmar'),
            'comentario' => implode(' | ', $comentarioPartes) ?: 'Importada desde Anita Surmar',
            'lugarentrega' => $lugar !== '' && $lugar !== '0' ? $lugar : null,
            'transporte_id' => $ctx->fkTransporte($row->penmp_expreso ?? null),
            'tratamiento' => 'NO ANTICIPADA',
            'proveedor_id' => $ctx->fkProveedor($row->penmp_proveedor ?? null),
            'condicioncompra_id' => $ctx->fkCondicioncompra($row->penmp_cond_compra ?? null),
            'condicionentrega_id' => $ctx->fkCondicionentrega($row->penmp_cond_entrega ?? null),
            'condicionpago_id' => $ctx->fkCondicionpago($row->penmp_cond_pago ?? null),
            'descuento' => isset($row->penmp_dto) ? (float) $row->penmp_dto : null,
            'descuento_tipo' => 'porcentaje',
            'estadoordencompra' => $ctx->mapEstadoOc($row->penmp_estado ?? '0'),
            'sector_legajocompra_id' => null,
            'creousuario_id' => $ctx->usuarioSyncId,
        ];
    }
}
