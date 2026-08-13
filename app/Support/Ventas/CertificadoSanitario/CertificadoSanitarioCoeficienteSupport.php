<?php

namespace App\Support\Ventas\CertificadoSanitario;

use App\Models\Ventas\Cliente;
use App\Models\Ventas\Transporte;

/**
 * Cantidades del certificado sanitario según p-certsan.c un_mov():
 * Anita guarda el pedido en crudo; al emitir se aplica el coeficiente del cliente
 * solo si el transporte es tipo expreso Divide (tipoexpreso = 3).
 *
 * No es cantidad / coeficiente. Es conservar (100 - porcentajedivision) / 100.
 * Coef 100 y transporte Divide → se omite la línea.
 */
final class CertificadoSanitarioCoeficienteSupport
{
    public const TIPO_EXPRESO_DIVIDE = '3';

    public static function clienteEmiteCertificado(?Cliente $cliente): bool
    {
        if ($cliente === null) {
            return true;
        }

        $valor = trim((string) ($cliente->emitecertificado ?? ''));
        if ($valor === '') {
            return true;
        }

        return $valor === 'S' || strcasecmp($valor, 'Emite Certificado') === 0;
    }

    /**
     * Factor a aplicar a kilos/cajas/piezas. null = omitir la línea (coef 100).
     */
    public static function factorCantidad(?Cliente $cliente, ?Transporte $transporte): ?float
    {
        if (! self::transporteAplicaDivision($transporte)) {
            return 1.0;
        }

        $coef = 0.0;
        $coeficiente = $cliente?->coeficientes;
        if ($coeficiente !== null) {
            $coef = (float) ($coeficiente->porcentajedivision ?? 0);
        }

        if ($coef >= 100.0) {
            return null;
        }

        if ($coef == 0.0) {
            return 1.0;
        }

        return (100.0 - $coef) / 100.0;
    }

    public static function transporteAplicaDivision(?Transporte $transporte): bool
    {
        return (string) ($transporte?->tipoexpreso ?? '') === self::TIPO_EXPRESO_DIVIDE;
    }

    /**
     * @return array{kilos: float, cajas: float, piezas: float}|null null si la línea no entra al certificado
     */
    public static function cantidadesParaCertificado(
        ?Cliente $cliente,
        ?Transporte $transporte,
        float $kilos,
        float $cajas,
        float $piezas
    ): ?array {
        if (! self::clienteEmiteCertificado($cliente)) {
            return null;
        }

        $factor = self::factorCantidad($cliente, $transporte);
        if ($factor === null) {
            return null;
        }

        return [
            'kilos' => $kilos * $factor,
            'cajas' => $cajas * $factor,
            'piezas' => $piezas * $factor,
        ];
    }
}
