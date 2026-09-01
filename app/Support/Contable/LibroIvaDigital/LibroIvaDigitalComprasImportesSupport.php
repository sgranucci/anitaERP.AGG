<?php

declare(strict_types=1);

namespace App\Support\Contable\LibroIvaDigital;

/**
 * Importes de compras para el TXT (RG 4597).
 *
 * Los descuentos (concepto 80/81, etc.) vienen con signo negativo: hay que
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
     * Neto de un rubro de cabecera (exento / no gravado). Conserva el signo:
     * un descuento exento mayor al no gravado no puede pasar a positivo.
     */
    public static function importeNeteado(float $valor): float
    {
        return round($valor, 2);
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
     * @param  array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}  $registro
     * @return array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}
     */
    public static function cerrarRegistro(array $registro): array
    {
        $registro = self::reubicarAlicuotasHuerfanas($registro);
        $registro = self::reconciliarAlicuotasRedondeo($registro);

        return self::equilibrarResidual($registro);
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

        return self::equilibrarResidual($registro);
    }

    /**
     * @param  array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}  $registro
     * @return array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}
     */
    public static function equilibrarResidual(array $registro): array
    {
        $residual = self::residualCabecera($registro['cabecera'], $registro['alicuotas'] ?? []);
        if (abs($residual) < 0.005) {
            return $registro;
        }

        $campo = self::campoAjusteCabecera($registro);
        $registro['cabecera'][$campo] = round(
            (float) ($registro['cabecera'][$campo] ?? 0) + $residual,
            2,
        );

        return $registro;
    }

    /**
     * Tipo C: el ajuste va a no gravado (el tipo identifica monotributo).
     * Resto: Otros tributos, para no inflar «Exento / no grav.» del cruce con Anita.
     */
    public static function campoAjusteCabecera(array $registro): string
    {
        $tipo = str_pad((string) ($registro['cabecera']['tipo_comprobante'] ?? ''), 3, '0', STR_PAD_LEFT);
        if (in_array($tipo, LibroIvaDigitalVentasAlicuotaSupport::TIPOS_SIN_ALICUOTA, true)) {
            return 'no_integra_neto';
        }

        return 'otros_tributos';
    }

    /**
     * SIRTAC de liquidaciones «medio de cobro» / FISE: no es no gravado.
     * Anita lo deja fuera de NO GRAVADO+EX; en el TXT va a Otros tributos.
     */
    public static function esRetencionMedioCobro(int $codigo, string $nombreConcepto, string $nombreVendedor): bool
    {
        $esSirtac = $codigo === 611 || str_contains(strtoupper($nombreConcepto), 'SIRTAC');
        if (! $esSirtac) {
            return false;
        }
        $v = strtoupper($nombreVendedor);

        return str_contains($v, 'FISE') || str_contains($v, 'MEDIO DE COBRO');
    }

    public static function destinoRubroLid(
        int $codigo,
        string $nombreConcepto,
        string $tipo,
        string $nombreVendedor = '',
    ): string {
        if (self::esRetencionMedioCobro($codigo, $nombreConcepto, $nombreVendedor)) {
            return 'otros';
        }

        return match (strtoupper(trim($tipo))) {
            'N' => 'no_integra',
            'G' => 'gravado',
            'I' => 'iva',
            'E' => 'exento',
            'P' => 'perc_iva',
            'B', 'S' => 'perc_iibb',
            'M' => 'perc_municipal',
            'V', 'A' => 'perc_nacional',
            'T' => 'imp_interno',
            default => '',
        };
    }

    /**
     * Neto en una alícuota e IVA en otra (concepto G e I con tasa distinta).
     * Si IVA / neto ≈ la tasa del IVA, el gravado se mueve a esa alícuota.
     *
     * @param  array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}  $registro
     * @return array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}
     */
    public static function reubicarAlicuotasHuerfanas(array $registro): array
    {
        $alicuotas = $registro['alicuotas'] ?? [];
        if (count($alicuotas) < 2) {
            return $registro;
        }

        $sinIva = [];
        $sinNeto = [];
        foreach ($alicuotas as $i => $fila) {
            $codigo = str_pad((string) ($fila['alicuota_iva'] ?? $fila['codigo_lid'] ?? ''), 4, '0', STR_PAD_LEFT);
            $tasa = self::TASA_POR_LID[$codigo] ?? 0.0;
            if ($tasa <= 0) {
                continue;
            }
            $neto = (float) ($fila['neto_gravado'] ?? $fila['neto'] ?? 0);
            $iva = (float) ($fila['impuesto_liquidado'] ?? $fila['iva'] ?? 0);
            if (abs($neto) > 0.0001 && abs($iva) < 0.0001) {
                $sinIva[$i] = ['neto' => $neto, 'tasa' => $tasa];
            } elseif (abs($iva) > 0.0001 && abs($neto) < 0.0001) {
                $sinNeto[$i] = ['iva' => $iva, 'tasa' => $tasa];
            }
        }

        $usadosNeto = [];
        foreach ($sinNeto as $iIva => $ivaFila) {
            $elegido = null;
            foreach ($sinIva as $iNeto => $netoFila) {
                if (isset($usadosNeto[$iNeto]) || abs($netoFila['neto']) < 0.0001) {
                    continue;
                }
                $implicita = abs($ivaFila['iva'] / $netoFila['neto'] * 100);
                if (abs($implicita - $ivaFila['tasa']) <= 0.5 || abs($implicita - $netoFila['tasa']) <= 0.5) {
                    $elegido = $iNeto;
                    break;
                }
            }
            if ($elegido === null) {
                continue;
            }

            $neto = (float) ($alicuotas[$elegido]['neto_gravado'] ?? $alicuotas[$elegido]['neto'] ?? 0);
            $registro['alicuotas'][$iIva]['neto_gravado'] = $neto;
            if (isset($registro['alicuotas'][$iIva]['neto'])) {
                $registro['alicuotas'][$iIva]['neto'] = $neto;
            }
            unset($registro['alicuotas'][$elegido]);
            $usadosNeto[$elegido] = true;
        }

        if ($usadosNeto === []) {
            return $registro;
        }

        $registro['alicuotas'] = array_values($registro['alicuotas']);
        $registro['cabecera']['cantidad_alicuotas'] = count($registro['alicuotas']);

        return $registro;
    }

    /**
     * Ajuste fino de neto para que IVA = alícuota × neto (redondeo de agrupación / FX).
     * Si la tasa implícita no es la de la alícuota, no toca: es error de armado.
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
            $tasaCercana = abs($tasaImplicita - $tasa) <= 0.5;
            if ($diff <= 0.001 || ! $tasaCercana) {
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
            $campo = self::campoAjusteCabecera($registro);
            $registro['cabecera'][$campo] = round(
                (float) ($registro['cabecera'][$campo] ?? 0) + $ajusteNeto,
                2,
            );
        }

        return $registro;
    }
}
