<?php

namespace App\Support\Compras;

/**
 * Validación CBU / alias (Argentina). Convenio banco a medida: ver PropuestaPagoConvenioBancarioSupport.
 */
class CbuSupport
{
    public static function normalizar(?string $valor): string
    {
        return preg_replace('/\D+/', '', (string) $valor) ?? '';
    }

    public static function esValido(?string $cbu): bool
    {
        $n = self::normalizar($cbu);
        if (strlen($n) !== 22) {
            return false;
        }
        if (! ctype_digit($n)) {
            return false;
        }

        return self::bloqueValido(substr($n, 0, 8)) && self::bloqueValido(substr($n, 8, 14));
    }

    /**
     * Algoritmo oficial BCRA (ponderadores) para bloque de 8 o 14 dígitos.
     */
    private static function bloqueValido(string $bloque): bool
    {
        $len = strlen($bloque);
        if ($len === 8) {
            $pesos = [7, 1, 3, 9, 7, 1, 3];
            $suma = 0;
            for ($i = 0; $i < 7; $i++) {
                $suma += (int) $bloque[$i] * $pesos[$i];
            }
            $dif = (10 - ($suma % 10)) % 10;

            return $dif === (int) $bloque[7];
        }
        if ($len === 14) {
            $pesos = [3, 9, 7, 1, 3, 9, 7, 1, 3, 9, 7, 1, 3];
            $suma = 0;
            for ($i = 0; $i < 13; $i++) {
                $suma += (int) $bloque[$i] * $pesos[$i];
            }
            $dif = (10 - ($suma % 10)) % 10;

            return $dif === (int) $bloque[13];
        }

        return false;
    }

    /**
     * @return array{ok:bool,mensaje:string,cbu:string}
     */
    public static function validarConMensaje(?string $cbu): array
    {
        $n = self::normalizar($cbu);
        if ($n === '') {
            return ['ok' => false, 'mensaje' => 'CBU vacío', 'cbu' => ''];
        }
        if (strlen($n) !== 22) {
            return ['ok' => false, 'mensaje' => 'CBU debe tener 22 dígitos (tiene '.strlen($n).')', 'cbu' => $n];
        }
        if (! self::esValido($n)) {
            return ['ok' => false, 'mensaje' => 'CBU inválido (dígito verificador BCRA)', 'cbu' => $n];
        }

        return ['ok' => true, 'mensaje' => 'OK', 'cbu' => $n];
    }
}
