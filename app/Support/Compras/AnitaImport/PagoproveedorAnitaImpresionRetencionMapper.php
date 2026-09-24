<?php

declare(strict_types=1);

namespace App\Support\Compras\AnitaImport;

use App\Models\Compras\Pagoproveedor_Retencion;
use App\Models\Configuracion\Provincia;
use App\Support\Contable\IngresosBrutos\IngresosBrutosProvinciaAnitaSupport;

/**
 * Bloques retmov/retimov/retsmov/retibrmov → atributos de pagoproveedor_retencion.
 */
final class PagoproveedorAnitaImpresionRetencionMapper
{
    /** @var array<int, int>|null */
    private static ?array $mapaProvincia = null;

    /**
     * @param  array{
     *   ganancias?:list<object>,
     *   iva?:list<object>,
     *   suss?:list<object>,
     *   ibr?:list<object>
     * }  $bloques
     * @return list<array<string, mixed>>
     */
    public static function aFilasPersistencia(array $bloques, int $monedaId, float $cotizacion): array
    {
        $out = [];

        foreach ($bloques['ganancias'] ?? [] as $f) {
            $importe = abs((float) ($f->retv_retencion ?? 0));
            if ($importe < 0.005) {
                continue;
            }
            $codigo = trim((string) ($f->retv_codigo_ret ?? ''));
            $base = abs((float) ($f->retv_sujeto ?? $f->retv_gravado ?? 0));
            $out[] = self::fila(
                Pagoproveedor_Retencion::TIPO_GANANCIAS,
                $base,
                (float) ($f->retv_porc_ret ?? 0),
                $importe,
                (string) ((int) ($f->retv_nro_retencion ?? 0) ?: ''),
                $codigo,
                $codigo,
                null,
                $monedaId,
                $cotizacion,
                [
                    'tabla' => 'retmov',
                    'gravado' => (float) ($f->retv_gravado ?? 0),
                    'pago_actual' => (float) ($f->retv_pago_actual ?? 0),
                    'sujeto' => (float) ($f->retv_sujeto ?? 0),
                ]
            );
        }

        foreach ($bloques['iva'] ?? [] as $f) {
            $importe = abs((float) ($f->retiv_retencion ?? 0));
            if ($importe < 0.005) {
                continue;
            }
            $codigo = trim((string) ($f->retiv_codigo_ret ?? ''));
            $base = abs((float) ($f->retiv_sujeto ?? $f->retiv_gravado ?? 0));
            $out[] = self::fila(
                Pagoproveedor_Retencion::TIPO_IVA,
                $base,
                (float) ($f->retiv_porc_ret ?? 0),
                $importe,
                (string) ((int) ($f->retiv_nro_ret ?? 0) ?: ''),
                $codigo,
                $codigo,
                null,
                $monedaId,
                $cotizacion,
                [
                    'tabla' => 'retimov',
                    'gravado' => (float) ($f->retiv_gravado ?? 0),
                    'iva' => (float) ($f->retiv_iva ?? 0),
                    'comp' => trim(sprintf(
                        '%s%s-%s-%s',
                        (string) ($f->retiv_tipo_comp ?? ''),
                        (string) ($f->retiv_letra_comp ?? ''),
                        (string) ($f->retiv_suc_comp ?? ''),
                        (string) ($f->retiv_nro_comp ?? '')
                    )),
                ]
            );
        }

        foreach ($bloques['suss'] ?? [] as $f) {
            $importe = abs((float) ($f->retsv_retencion ?? 0));
            if ($importe < 0.005) {
                continue;
            }
            $codigo = trim((string) ($f->retsv_codigo_ret ?? ''));
            $base = abs((float) ($f->retsv_base_calculo ?? $f->retsv_gravado ?? 0));
            $out[] = self::fila(
                Pagoproveedor_Retencion::TIPO_SUSS,
                $base,
                (float) ($f->retsv_porc_ret ?? 0),
                $importe,
                (string) ((int) ($f->retsv_nro_ret ?? 0) ?: ''),
                $codigo,
                $codigo,
                null,
                $monedaId,
                $cotizacion,
                ['tabla' => 'retsmov', 'gravado' => (float) ($f->retsv_gravado ?? 0)]
            );
        }

        foreach ($bloques['ibr'] ?? [] as $f) {
            $importe = abs((float) ($f->retibr_retencion ?? 0));
            if ($importe < 0.005) {
                continue;
            }
            $base = abs((float) ($f->retibr_sujeto ?? $f->retibr_gravado ?? 0));
            $codProv = (int) ($f->retibr_provincia ?? 0);
            $out[] = self::fila(
                Pagoproveedor_Retencion::TIPO_IIBB,
                $base,
                (float) ($f->retibr_porc_ret ?? 0),
                $importe,
                (string) ((int) ($f->retibr_nro_ret ?? 0) ?: ''),
                '',
                (string) $codProv,
                self::resolverProvinciaId($codProv),
                $monedaId,
                $cotizacion,
                [
                    'tabla' => 'retibrmov',
                    'provincia_anita' => $codProv,
                    'gravado' => (float) ($f->retibr_gravado ?? 0),
                ]
            );
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function fila(
        string $tipo,
        float $base,
        float $alicuota,
        float $importe,
        string $nroCert,
        string $codigoRegimen,
        string $codigoRetencion,
        ?int $provinciaId,
        int $monedaId,
        float $cotizacion,
        array $extra,
    ): array {
        return [
            'tiporetencion' => $tipo,
            'retencionganancia_id' => null,
            'retencioniva_id' => null,
            'retencionsuss_id' => null,
            'provincia_id' => $provinciaId,
            'codigo_regimen' => $codigoRegimen !== '' ? $codigoRegimen : null,
            'codigo_retencion' => $codigoRetencion !== '' ? $codigoRetencion : null,
            'base_calculo' => round($base, 4),
            'alicuota' => round($alicuota, 4),
            'importe' => round($importe, 4),
            'nro_certificado' => $nroCert !== '' && $nroCert !== '0' ? $nroCert : null,
            'moneda_id' => $monedaId,
            'cotizacion' => $cotizacion > 0 ? $cotizacion : 1.0,
            'detalle_calculo' => array_merge(
                ['origen' => PagoproveedorAnitaImpresionElegibleSupport::ORIGEN_RETENCION],
                $extra
            ),
            'motivo' => PagoproveedorAnitaImpresionElegibleSupport::MOTIVO_RETENCION,
        ];
    }

    private static function resolverProvinciaId(int $codigoAnita): ?int
    {
        if ($codigoAnita <= 0) {
            return null;
        }
        if (self::$mapaProvincia === null) {
            self::$mapaProvincia = [];
            foreach (Provincia::query()->get(['id', 'codigo', 'codigoexterno', 'jurisdiccion', 'nombre']) as $p) {
                $id = (int) $p->id;
                foreach (IngresosBrutosProvinciaAnitaSupport::codigosAnita($p) as $c) {
                    self::$mapaProvincia[$c] = $id;
                }
                $cod = (int) ($p->codigo ?? 0);
                if ($cod > 0) {
                    self::$mapaProvincia[$cod] = $id;
                }
            }
        }

        return self::$mapaProvincia[$codigoAnita] ?? null;
    }
}
