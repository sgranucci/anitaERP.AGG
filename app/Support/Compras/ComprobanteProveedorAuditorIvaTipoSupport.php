<?php

namespace App\Support\Compras;

use App\Models\Compras\Concepto_Ivacompra;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorConceptosTipoImputadoSupport;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorProrrateoMultiCcSupport;
use Illuminate\Support\Collection;

/**
 * Detecta conceptos IVA de un CP que no pertenecen al tipo de transacción (Contaduría P3).
 */
final class ComprobanteProveedorAuditorIvaTipoSupport
{
    /**
     * @param  list<int>  $conceptoIdsConMonto
     * @return array{
     *     ok: bool,
     *     omitido: bool,
     *     abreviatura: string,
     *     fuera: Collection<int, Concepto_Ivacompra>,
     *     motivo: string|null
     * }
     */
    public static function evaluar(
        int $tipotransaccionCompraId,
        array $conceptoIdsConMonto,
        bool $soloImpuestoIva = true,
    ): array {
        $abrev = PrecargaProveedorConceptosTipoImputadoSupport::abreviaturaTipo($tipotransaccionCompraId);
        if ($abrev === '' || $tipotransaccionCompraId <= 0) {
            return [
                'ok' => true,
                'omitido' => true,
                'abreviatura' => $abrev,
                'fuera' => collect(),
                'motivo' => null,
            ];
        }

        if (PrecargaProveedorProrrateoMultiCcSupport::esTipoProrrateado($abrev)) {
            return [
                'ok' => true,
                'omitido' => true,
                'abreviatura' => $abrev,
                'fuera' => collect(),
                'motivo' => null,
            ];
        }

        $fuera = PrecargaProveedorConceptosTipoImputadoSupport::ivaFueraDelTipo(
            $conceptoIdsConMonto,
            $tipotransaccionCompraId,
            $abrev
        );

        if (! $soloImpuestoIva) {
            // ivaFueraDelTipo ya filtra tipoconcepto I; sin más.
        }

        if ($fuera->isEmpty()) {
            return [
                'ok' => true,
                'omitido' => false,
                'abreviatura' => $abrev,
                'fuera' => $fuera,
                'motivo' => null,
            ];
        }

        $lista = $fuera->map(
            static fn (Concepto_Ivacompra $c): string => trim((string) $c->codigo.' '.$c->nombre)
        )->implode(', ');

        return [
            'ok' => false,
            'omitido' => false,
            'abreviatura' => $abrev,
            'fuera' => $fuera,
            'motivo' => 'Conceptos IVA ajenos al tipo '.$abrev.': '.$lista,
        ];
    }
}
