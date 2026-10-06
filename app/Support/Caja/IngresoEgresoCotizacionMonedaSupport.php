<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\Support\Configuracion\CotizacionVigenteSupport;
use App\Support\Numerico\NumeroDecimalLocalSupport;
use InvalidArgumentException;

/**
 * Cotización obligatoria en moneda extranjera al grabar un IE.
 *
 * El formulario arranca los renglones en 0 y el parseo de importes completa 1 cuando el campo
 * queda vacío. Un renglón en dólares con cotización 1 entra a caja, al asiento y a la cuenta
 * corriente como si fuera un peso, y recién aparece días después en el control contra Anita.
 */
final class IngresoEgresoCotizacionMonedaSupport
{
    /**
     * Grupos del formulario: moneda, cotización y monto de cada bloque de renglones.
     *
     * @var list<array{titulo: string, moneda: string, cotizacion: string, montos: list<string>, campo: string}>
     */
    private const GRUPOS = [
        [
            'titulo' => 'la cuenta de caja',
            'moneda' => 'moneda_ids',
            'cotizacion' => 'cotizaciones',
            'montos' => ['montos'],
            'campo' => 'cotizaciones',
        ],
        [
            'titulo' => 'el asiento contable',
            'moneda' => 'monedaasiento_ids',
            'cotizacion' => 'cotizacionasientos',
            'montos' => ['debeasientos', 'haberasientos'],
            'campo' => 'cotizacionasientos',
        ],
        [
            'titulo' => 'el cheque recibido',
            'moneda' => 'monedacheque_recibido_ids',
            'cotizacion' => 'cotizacioncheque_recibidos',
            'montos' => ['montocheque_recibidos'],
            'campo' => 'cotizacioncheque_recibidos',
        ],
        [
            'titulo' => 'el cheque emitido',
            'moneda' => 'moneda_emitido_ids',
            'cotizacion' => 'cotizacioncheque_emitidos',
            'montos' => ['montocheque_emitidos'],
            'campo' => 'cotizacioncheque_emitidos',
        ],
        [
            'titulo' => 'el cheque de reemplazo',
            'moneda' => 'moneda_reemplazo_ids',
            'cotizacion' => 'cotizacioncheque_reemplazo',
            'montos' => ['montocheque_reemplazo'],
            'campo' => 'cotizacioncheque_reemplazo',
        ],
    ];

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public static function assertCotizacionEnMonedaExtranjera(array $data): void
    {
        foreach (self::GRUPOS as $grupo) {
            $renglon = self::primerRenglonSinCotizacion($data, $grupo);
            if ($renglon === null) {
                continue;
            }

            throw new InvalidArgumentException(
                'Cargue la cotización en '.$grupo['titulo'].' del renglón '.$renglon.
                ': en moneda extranjera no puede quedar en 0 ni en 1.'
            );
        }
    }

    /**
     * Campo del formulario donde mostrar el error del primer grupo que falla.
     *
     * @param  array<string, mixed>  $data
     */
    public static function campoConError(array $data): string
    {
        foreach (self::GRUPOS as $grupo) {
            if (self::primerRenglonSinCotizacion($data, $grupo) !== null) {
                return $grupo['campo'];
            }
        }

        return self::GRUPOS[0]['campo'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{titulo: string, moneda: string, cotizacion: string, montos: list<string>, campo: string}  $grupo
     * @return int|null número de renglón (base 1) sin cotización válida
     */
    private static function primerRenglonSinCotizacion(array $data, array $grupo): ?int
    {
        $monedas = array_values((array) ($data[$grupo['moneda']] ?? []));
        $cotizaciones = array_values((array) ($data[$grupo['cotizacion']] ?? []));

        $montos = [];
        foreach ($grupo['montos'] as $campoMonto) {
            $montos[] = array_values((array) ($data[$campoMonto] ?? []));
        }

        foreach ($monedas as $i => $moneda) {
            if ((int) $moneda <= CotizacionVigenteSupport::MONEDA_LOCAL_ID) {
                continue;
            }

            $importe = 0.0;
            foreach ($montos as $lista) {
                $importe += abs(NumeroDecimalLocalSupport::aFloat($lista[$i] ?? 0));
            }
            if ($importe < 0.000001) {
                continue;
            }

            $cotizacion = NumeroDecimalLocalSupport::aFloat($cotizaciones[$i] ?? 0);
            if ($cotizacion <= CotizacionVigenteSupport::COTIZACION_MINIMA_EXTRANJERA) {
                return (int) $i + 1;
            }
        }

        return null;
    }
}
