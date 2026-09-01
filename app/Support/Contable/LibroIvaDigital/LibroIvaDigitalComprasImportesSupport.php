<?php

declare(strict_types=1);

namespace App\Support\Contable\LibroIvaDigital;

/**
 * Importes de compras para el TXT (RG 4597).
 *
 * Los descuentos (concepto 80, etc.) vienen con signo negativo: hay que
 * netearlos. Si se toma abs() por renglón, el neto se infla, el IVA no
 * cierra con la alícuota y el total no coincide con la suma.
 */
final class LibroIvaDigitalComprasImportesSupport
{
    /** @var array<string, float> */
    private const TASA_POR_LID = [
        '0004' => 10.5,
        '0005' => 21.0,
        '0006' => 27.0,
        '0008' => 5.0,
        '0009' => 2.5,
    ];

    public static function absolutoInformable(float $valor): float
    {
        return abs(round($valor, 2));
    }

    /**
     * @param  list<array<string, mixed>>  $alicuotas
     */
    public static function residualCabecera(array $cabecera, array $alicuotas = []): float
    {
        $neto = 0.0;
        $iva = 0.0;
        foreach ($alicuotas as $fila) {
            $neto += (float) ($fila['neto_gravado'] ?? $fila['neto'] ?? 0);
            $iva += (float) ($fila['impuesto_liquidado'] ?? $fila['iva'] ?? 0);
        }

        $suma = $neto
            + $iva
            + (float) ($cabecera['no_integra_neto'] ?? 0)
            + (float) ($cabecera['operaciones_exentas'] ?? 0)
            + (float) ($cabecera['percepciones_iva'] ?? 0)
            + (float) ($cabecera['percepcion_no_categorizados'] ?? 0)
            + (float) ($cabecera['percepciones_nacionales'] ?? 0)
            + (float) ($cabecera['percepciones_iibb'] ?? 0)
            + (float) ($cabecera['percepciones_municipales'] ?? 0)
            + (float) ($cabecera['impuestos_internos'] ?? 0)
            + (float) ($cabecera['otros_tributos'] ?? 0);

        return round((float) ($cabecera['importe_total'] ?? 0) - $suma, 2);
    }

    /**
     * Factura C (011-016): sin alícuotas. El total tiene que ir a no gravado
     * para que ARCA acepte Importe Total = suma. El tipo identifica monotributo;
     * IVA Simple no suma este campo en «Exento / no grav.».
     *
     * @param  array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}  $registro
     * @return array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}
     */
    public static function equilibrarTipoC(array $registro): array
    {
        $tipo = str_pad((string) ($registro['cabecera']['tipo_comprobante'] ?? ''), 3, '0', STR_PAD_LEFT);
        if (! in_array($tipo, LibroIvaDigitalVentasAlicuotaSupport::TIPOS_SIN_ALICUOTA, true)) {
            return $registro;
        }

        $residual = self::residualCabecera($registro['cabecera'], $registro['alicuotas'] ?? []);
        if (abs($residual) < 0.005) {
            return $registro;
        }

        $registro['cabecera']['no_integra_neto'] = round(
            (float) ($registro['cabecera']['no_integra_neto'] ?? 0) + $residual,
            2,
        );

        return $registro;
    }

    /**
     * Ajuste fino de neto para que IVA = alícuota × neto (redondeo de agrupación).
     * No toca desvíos grandes: esos son errores de armado, no redondeo.
     *
     * @param  array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}  $registro
     * @return array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}
     */
    public static function reconciliarAlicuotasRedondeo(array $registro): array
    {
        $ajusteNeto = 0.0;
        foreach ($registro['alicuotas'] ?? [] as $i => $fila) {
            $codigo = str_pad((string) ($fila['alicuota_iva'] ?? $fila['codigo_lid'] ?? ''), 4, '0', STR_PAD_LEFT);
            $tasa = self::TASA_POR_LID[$codigo] ?? 0.0;
            if ($tasa <= 0) {
                continue;
            }
            $neto = (float) ($fila['neto_gravado'] ?? $fila['neto'] ?? 0);
            $iva = (float) ($fila['impuesto_liquidado'] ?? $fila['iva'] ?? 0);
            if (abs($neto) < 0.0001 && abs($iva) < 0.0001) {
                continue;
            }
            $esperado = round($neto * $tasa / 100, 2);
            $diff = abs($esperado - $iva);
            $tasaImplicita = abs($neto) > 0.0001 ? abs($iva / $neto * 100) : 0.0;
            $redondeoFx = $diff <= max(1.0, abs($neto) * 0.0002);
            $tasaCercana = abs($tasaImplicita - $tasa) <= 0.5;
            if ($diff <= 0.001 || ! $tasaCercana || ! $redondeoFx) {
                continue;
            }
            $netoAjustado = round($iva * 100 / $tasa, 2);
            $ajusteNeto += $neto - $netoAjustado;
            $registro['alicuotas'][$i]['neto_gravado'] = $netoAjustado;
            if (isset($registro['alicuotas'][$i]['neto'])) {
                $registro['alicuotas'][$i]['neto'] = $netoAjustado;
            }
        }

        if (abs($ajusteNeto) > 0.001) {
            $registro['cabecera']['no_integra_neto'] = round(
                (float) ($registro['cabecera']['no_integra_neto'] ?? 0) + $ajusteNeto,
                2,
            );
        }

        return $registro;
    }
}
