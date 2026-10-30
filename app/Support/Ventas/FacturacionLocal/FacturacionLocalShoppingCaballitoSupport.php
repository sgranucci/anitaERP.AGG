<?php

declare(strict_types=1);

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Ventas\LocalVenta;

/**
 * Parámetros del archivo de ventas del shopping para el local de Caballito (Ferli).
 * Los valores viven en Parámetros Facturación Local.
 */
final class FacturacionLocalShoppingCaballitoSupport
{
    public const CLAVE_EMAIL = FacturacionLocalParametroSupport::CLAVE_SHOPPING_EMAIL;

    public const CLAVE_NRO_LOCAL = FacturacionLocalParametroSupport::CLAVE_SHOPPING_NRO_LOCAL;

    public const CLAVE_CONTRATO = FacturacionLocalParametroSupport::CLAVE_SHOPPING_CONTRATO;

    public const CLAVE_CAJA = FacturacionLocalParametroSupport::CLAVE_SHOPPING_CAJA;

    public const CLAVE_HORA = FacturacionLocalParametroSupport::CLAVE_SHOPPING_HORA;

    public const CLAVE_HABILITADO = FacturacionLocalParametroSupport::CLAVE_SHOPPING_HABILITADO;

    public const CLAVE_LOCAL_CODIGO = FacturacionLocalParametroSupport::CLAVE_SHOPPING_LOCAL_CODIGO;

    public static function hora(): string
    {
        $raw = FacturacionLocalParametroSupport::valor(self::CLAVE_HORA, '21:00');
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $raw) !== 1) {
            return '21:00';
        }

        return $raw;
    }

    public static function habilitado(): bool
    {
        $valor = strtolower(FacturacionLocalParametroSupport::valor(self::CLAVE_HABILITADO, '1'));

        return in_array($valor, ['1', 'si', 'sí', 's', 'true'], true);
    }

    public static function local(): ?LocalVenta
    {
        $codigo = FacturacionLocalParametroSupport::valor(self::CLAVE_LOCAL_CODIGO, '');
        if ($codigo === '') {
            return null;
        }

        return LocalVenta::query()->where('codigo', $codigo)->first();
    }

    /**
     * @return list<string>
     */
    public static function emails(): array
    {
        $raw = FacturacionLocalParametroSupport::valor(self::CLAVE_EMAIL, '');
        $partes = preg_split('/[,;\s]+/', $raw) ?: [];
        $ok = [];
        foreach ($partes as $parte) {
            $parte = trim((string) $parte);
            if ($parte !== '' && filter_var($parte, FILTER_VALIDATE_EMAIL)) {
                $ok[] = $parte;
            }
        }

        return array_values(array_unique($ok));
    }

    public static function nroLocal(): string
    {
        return self::digitos(self::CLAVE_NRO_LOCAL, 4);
    }

    public static function nroContrato(): string
    {
        return self::digitos(self::CLAVE_CONTRATO, 10);
    }

    public static function nroCaja(): string
    {
        $digitos = self::digitos(self::CLAVE_CAJA, 2);

        return $digitos !== '' ? $digitos : '01';
    }

    /**
     * Datos del shopping y del local ERP. El correo no entra: sin correo igual se arma el archivo.
     *
     * @return list<string>
     */
    public static function faltantesIdentificacion(): array
    {
        $faltan = [];
        $codigo = FacturacionLocalParametroSupport::valor(self::CLAVE_LOCAL_CODIGO, '');
        if ($codigo === '') {
            $faltan[] = 'Falta el código del local ERP en Parámetros Facturación Local.';
        } elseif (self::local() === null) {
            $faltan[] = 'No existe un local con código '.$codigo.'.';
        }
        if (self::nroLocal() === '') {
            $faltan[] = 'Falta el número de local que asignó el shopping (4 dígitos).';
        }
        if (self::nroContrato() === '') {
            $faltan[] = 'Falta el número de contrato del shopping (hasta 10 dígitos).';
        }

        return $faltan;
    }

    private static function digitos(string $clave, int $largo): string
    {
        $raw = preg_replace('/\D+/', '', FacturacionLocalParametroSupport::valor($clave, '')) ?? '';
        if ($raw === '' || strlen($raw) > $largo) {
            return '';
        }

        return str_pad($raw, $largo, '0', STR_PAD_LEFT);
    }
}
