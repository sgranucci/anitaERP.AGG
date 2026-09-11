<?php

namespace App\Support\Compras;

use App\Models\Compras\Pagoproveedor_Retencion;

/**
 * Arma las filas del certificado de retención por comprobante.
 *
 * No parte la base ni el importe en partes iguales: cada documento lleva su
 * gravado (NC negativo, OPA afuera) y se prorratea base/importe con ese peso.
 */
final class PagoproveedorRetencionCertificadoLineasSupport
{
    /**
     * @param  list<array<string, mixed>>  $aplicaciones  Filas de armarAplicaciones (cc_id, numero, fecha)
     * @param  list<array<string, mixed>>  $detalleBases  detalle de RetencionesPagoBasesDesdeConceptosSupport
     * @return list<array{
     *   cc_id:int,
     *   numero:string,
     *   fecha:string,
     *   gravado:float,
     *   base_imp:float,
     *   alicuota:float,
     *   retencion:float
     * }>
     */
    public static function lineas(
        array $aplicaciones,
        array $detalleBases,
        string $tipoRetencion,
        float $baseCalculo,
        float $importe,
        float $alicuota,
    ): array {
        $tipo = strtoupper(trim($tipoRetencion));
        $pesos = [];
        foreach ($aplicaciones as $apl) {
            $ccId = (int) ($apl['cc_id'] ?? $apl['proveedor_cuentacorriente_id'] ?? 0);
            if ($ccId <= 0) {
                continue;
            }
            $gravado = self::gravadoDeDetalle($detalleBases, $ccId, $tipo);
            if (abs($gravado) < 0.005) {
                continue;
            }
            $pesos[] = [
                'cc_id' => $ccId,
                'numero' => (string) ($apl['numero'] ?? ''),
                'fecha' => (string) ($apl['fecha'] ?? ''),
                'gravado' => round($gravado, 2),
            ];
        }

        $suma = round(array_sum(array_column($pesos, 'gravado')), 2);
        if ($pesos === [] || abs($suma) < 0.005) {
            return [];
        }

        $baseCalculo = round($baseCalculo, 2);
        $importe = round($importe, 2);
        $alicuota = round($alicuota, 4);
        $asignadaBase = 0.0;
        $asignadaRet = 0.0;
        $last = count($pesos) - 1;
        $out = [];

        foreach ($pesos as $i => $fila) {
            if ($i === $last) {
                $baseImp = round($baseCalculo - $asignadaBase, 2);
                $ret = round($importe - $asignadaRet, 2);
            } else {
                $ratio = $fila['gravado'] / $suma;
                $baseImp = round($ratio * $baseCalculo, 2);
                $ret = round($ratio * $importe, 2);
                $asignadaBase += $baseImp;
                $asignadaRet += $ret;
            }

            $out[] = [
                'cc_id' => $fila['cc_id'],
                'numero' => $fila['numero'],
                'fecha' => $fila['fecha'],
                'gravado' => $fila['gravado'],
                'base_imp' => $baseImp,
                'alicuota' => $alicuota,
                'retencion' => $ret,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $detalle
     */
    public static function gravadoDeDetalle(array $detalle, int $ccId, string $tipo): float
    {
        $tipo = strtoupper(trim($tipo));
        $filtroIva = $tipo === Pagoproveedor_Retencion::TIPO_IVA
            ? (self::detalleTieneConcepto($detalle, 'I') ? 'I' : 'G')
            : null;

        $suma = 0.0;
        foreach ($detalle as $d) {
            if ((int) ($d['cc_id'] ?? 0) !== $ccId) {
                continue;
            }
            if (($d['omitido_retencion'] ?? '') === 'opa') {
                continue;
            }
            if (! self::lineaAportaAlTipo($d, $tipo, $filtroIva)) {
                continue;
            }
            $suma += (float) ($d['porcion_pago'] ?? $d['equivalente_pago'] ?? 0);
        }

        return round($suma, 2);
    }

    /**
     * @param  list<array<string, mixed>>  $detalle
     */
    private static function detalleTieneConcepto(array $detalle, string $tipoconcepto): bool
    {
        $buscado = strtoupper($tipoconcepto);
        foreach ($detalle as $d) {
            if (strtoupper(trim((string) ($d['tipoconcepto'] ?? ''))) === $buscado) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $d
     */
    private static function lineaAportaAlTipo(array $d, string $tipo, ?string $filtroIva): bool
    {
        $concepto = strtoupper(trim((string) ($d['tipoconcepto'] ?? '')));

        return match ($tipo) {
            Pagoproveedor_Retencion::TIPO_IIBB => self::aportaIibb($d),
            Pagoproveedor_Retencion::TIPO_GANANCIAS => strtoupper(trim((string) ($d['retieneganancia'] ?? 'N'))) === 'S',
            Pagoproveedor_Retencion::TIPO_SUSS => $concepto === 'G',
            Pagoproveedor_Retencion::TIPO_IVA => $filtroIva !== null && $concepto === $filtroIva,
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $d
     */
    private static function aportaIibb(array $d): bool
    {
        if (strtoupper(trim((string) ($d['retieneIIBB'] ?? 'N'))) !== 'S') {
            return false;
        }
        if (array_key_exists('destino_buenos_aires', $d) && ! $d['destino_buenos_aires']) {
            return false;
        }

        return true;
    }
}
