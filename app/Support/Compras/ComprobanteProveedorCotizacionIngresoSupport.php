<?php

namespace App\Support\Compras;

use App\Support\Configuracion\CotizacionVigenteSupport;

/**
 * Cotización que llega en la precarga (API / PDF+IA).
 *
 * En moneda extranjera:
 * - Si coincide con la del día (banda amplia): se respeta (puede ser el TC de la factura).
 * - Si parece la del día mal leída (1,51 vs 1510): se deduce y se marca error.
 * - Si no tiene relación: se usa la del día y se marca error.
 */
final class ComprobanteProveedorCotizacionIngresoSupport
{
    public const MARCA_ESCALA = 'COTIZACION_ESCALA';

    public const MARCA_INVALIDA = 'COTIZACION_INVALIDA';

    /** Banda para aceptar el TC de la factura frente al del día. */
    private const TOLERANCIA_RECIBIDA = 0.15;

    /** Banda para aceptar un múltiplo 10/100/1000 como la misma cotización. */
    private const TOLERANCIA_ESCALA = 0.05;

    /** @var list<float> */
    private const FACTORES_ESCALA = [0.01, 0.1, 10.0, 100.0, 1000.0, 10000.0];

    /**
     * @return array{
     *   cotizacion: float,
     *   cotizacion_recibida: float,
     *   cotizacion_dia: float,
     *   origen: string,
     *   marca_error: string|null,
     *   aviso: string|null
     * }
     */
    public static function resolverParaFecha(int $monedaId, mixed $cotizacionRecibida, ?string $fechaYmd): array
    {
        $dia = 0.0;
        if (ComprobanteProveedorCotizacionSupport::esMonedaExtranjera($monedaId)) {
            $dia = CotizacionVigenteSupport::ventaValor($fechaYmd, $monedaId);
        }

        return self::resolver($monedaId, $cotizacionRecibida, $dia);
    }

    /**
     * @return array{
     *   cotizacion: float,
     *   cotizacion_recibida: float,
     *   cotizacion_dia: float,
     *   origen: string,
     *   marca_error: string|null,
     *   aviso: string|null
     * }
     */
    public static function resolver(int $monedaId, mixed $cotizacionRecibida, float $cotizacionDia): array
    {
        $recibida = (float) ($cotizacionRecibida ?? 0);
        $dia = $cotizacionDia > 0 ? $cotizacionDia : 0.0;

        if (! ComprobanteProveedorCotizacionSupport::esMonedaExtranjera($monedaId)) {
            return self::resultado(1.0, $recibida, 1.0, 'mn', null, null);
        }

        if ($dia <= ComprobanteProveedorMonedaMotor::COTIZACION_MINIMA) {
            if ($recibida > ComprobanteProveedorMonedaMotor::COTIZACION_MINIMA) {
                return self::resultado($recibida, $recibida, $dia, 'recibida', null, null);
            }

            return self::resultado(
                1.0,
                $recibida,
                $dia,
                'sin_dia',
                self::MARCA_INVALIDA,
                self::avisoInvalida($recibida, $dia)
            );
        }

        if ($recibida > ComprobanteProveedorMonedaMotor::COTIZACION_MINIMA
            && self::dentroDeBanda($recibida, $dia, self::TOLERANCIA_RECIBIDA)) {
            return self::resultado($recibida, $recibida, $dia, 'recibida', null, null);
        }

        if (self::pareceEscalaDe($recibida, $dia)) {
            return self::resultado(
                $dia,
                $recibida,
                $dia,
                'deducida',
                self::MARCA_ESCALA,
                sprintf(
                    'La cotización recibida (%s) parece la del día (%s) mal leída (punto/escala). Se grabó %s.',
                    self::fmt($recibida),
                    self::fmt($dia),
                    self::fmt($dia)
                )
            );
        }

        return self::resultado(
            $dia,
            $recibida,
            $dia,
            'dia',
            self::MARCA_INVALIDA,
            self::avisoInvalida($recibida, $dia)
        );
    }

    public static function etiquetaMarca(?string $marca): string
    {
        return match ($marca) {
            self::MARCA_ESCALA => 'Cotización escala',
            self::MARCA_INVALIDA => 'Cotización inválida',
            default => '',
        };
    }

    private static function pareceEscalaDe(float $recibida, float $dia): bool
    {
        if ($recibida <= 0 || $dia <= 0) {
            return false;
        }

        foreach (self::FACTORES_ESCALA as $factor) {
            if (self::dentroDeBanda($recibida * $factor, $dia, self::TOLERANCIA_ESCALA)) {
                return true;
            }
        }

        return false;
    }

    private static function dentroDeBanda(float $valor, float $referencia, float $tolerancia): bool
    {
        if ($referencia <= 0) {
            return false;
        }

        return abs($valor - $referencia) / $referencia <= $tolerancia;
    }

    /**
     * @return array{
     *   cotizacion: float,
     *   cotizacion_recibida: float,
     *   cotizacion_dia: float,
     *   origen: string,
     *   marca_error: string|null,
     *   aviso: string|null
     * }
     */
    private static function resultado(
        float $cotizacion,
        float $recibida,
        float $dia,
        string $origen,
        ?string $marca,
        ?string $aviso,
    ): array {
        return [
            'cotizacion' => $cotizacion,
            'cotizacion_recibida' => $recibida,
            'cotizacion_dia' => $dia,
            'origen' => $origen,
            'marca_error' => $marca,
            'aviso' => $aviso,
        ];
    }

    private static function avisoInvalida(float $recibida, float $dia): string
    {
        if ($dia > ComprobanteProveedorMonedaMotor::COTIZACION_MINIMA) {
            return sprintf(
                'La cotización recibida (%s) no coincide con la del día (%s). Se grabó la cotización del sistema.',
                self::fmt($recibida),
                self::fmt($dia)
            );
        }

        return sprintf(
            'La cotización recibida (%s) no es válida y no hay cotización del día cargada. Revisar antes de contabilizar.',
            self::fmt($recibida)
        );
    }

    private static function fmt(float $valor): string
    {
        return number_format($valor, 4, ',', '.');
    }
}
