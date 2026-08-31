<?php

namespace App\Support\Contable;

use App\Models\Caja\Cuentacaja;

/**
 * Clasificación de valores del cierre máquinas (valormae / p-vtamaquina.c lee_rendvalor + genera_asiento).
 *
 * No usar cuentacaja.codigo como código valormae: 100 es «CAJA PESOS» en tesorería y
 * valormae 100 es Totalcoin. El código Anita vive en rendicion_maquina_valor.codigo_valormae
 * o se infiere por descripción/nombre.
 *
 * Asiento de cierre: casi todos los valores van por cuentacontable de la cuentacaja.
 * Excepción: CODIGOS_TOTALCOIN_SLOT (25/76/100) → slot fijo cierre_maquina.totalcoin.
 */
final class CierreRendicionMaquinaValormaeSupport
{
    public const TIPO_EFE_PESOS = '0';

    public const TIPO_EFE_DOLAR = '1';

    public const TIPO_EFE_EURO = '2';

    public const TIPO_TARJETA = '3';

    public const TIPO_TICKET = '4';

    public const TIPO_QR = '5';

    public const TIPO_MEP = '6';

    public const TIPO_VARIOS = '7';

    public const TIPO_EFE_CRIPTO = '8';

    public const TIPO_TOTALCOIN_QR = '9';

    /** Códigos que van al slot c_totalcoin (no a tesm_cta_contable). */
    public const CODIGOS_TOTALCOIN_SLOT = [25, 76, 100];

    /**
     * Tipo valormae conocido por código Anita (empresa 1 / AGG).
     *
     * @var array<int, string>
     */
    private const TIPO_POR_CODIGO = [
        1 => self::TIPO_EFE_PESOS,
        2 => self::TIPO_EFE_DOLAR,
        3 => self::TIPO_EFE_EURO,
        4 => self::TIPO_QR,
        5 => self::TIPO_TICKET,
        6 => self::TIPO_MEP,
        7 => self::TIPO_MEP,
        8 => self::TIPO_VARIOS,
        9 => self::TIPO_EFE_CRIPTO,
        15 => self::TIPO_TICKET,
        17 => self::TIPO_TICKET,
        18 => self::TIPO_TICKET,
        20 => self::TIPO_TICKET,
        21 => self::TIPO_TOTALCOIN_QR,
        22 => self::TIPO_TOTALCOIN_QR,
        23 => self::TIPO_VARIOS,
        24 => self::TIPO_VARIOS,
        25 => self::TIPO_VARIOS,
        26 => self::TIPO_TARJETA,
    ];

    /**
     * @return array{codigo: int, tipo: string}
     */
    public static function resolver(?int $codigoValormae, ?Cuentacaja $caja): array
    {
        $codigo = (int) ($codigoValormae ?? 0);
        if ($codigo > 0 && $codigo < 200 && isset(self::TIPO_POR_CODIGO[$codigo])) {
            return ['codigo' => $codigo, 'tipo' => self::TIPO_POR_CODIGO[$codigo]];
        }

        $inferido = self::inferirDesdeCaja($caja);
        if ($inferido !== null) {
            return $inferido;
        }

        if ($codigo > 0 && $codigo < 200) {
            return ['codigo' => $codigo, 'tipo' => self::TIPO_POR_CODIGO[$codigo] ?? self::TIPO_VARIOS];
        }

        return ['codigo' => 0, 'tipo' => self::TIPO_EFE_PESOS];
    }

    public static function esTotalcoinSlot(int $codigo): bool
    {
        return in_array($codigo, self::CODIGOS_TOTALCOIN_SLOT, true);
    }

    public static function esCuentaFinanciera(int $codigo, string $tipo): bool
    {
        if (self::esTotalcoinSlot($codigo)) {
            return false;
        }

        return in_array($tipo, [
            self::TIPO_TICKET,
            self::TIPO_VARIOS,
            self::TIPO_TOTALCOIN_QR,
            self::TIPO_QR,
        ], true);
    }

    public static function esPlayuzu(int $codigo): bool
    {
        return $codigo === 17;
    }

    /**
     * @return array{codigo: int, tipo: string}|null
     */
    private static function inferirDesdeCaja(?Cuentacaja $caja): ?array
    {
        if ($caja === null) {
            return null;
        }

        $texto = mb_strtolower(trim(implode(' ', [
            (string) ($caja->descripcion_operaciones ?? ''),
            (string) ($caja->nombre ?? ''),
            (string) ($caja->codigo ?? ''),
        ])));

        if ($texto === '') {
            return null;
        }

        if (str_contains($texto, 'deposito') && str_contains($texto, 'qr')) {
            return ['codigo' => 25, 'tipo' => self::TIPO_VARIOS];
        }
        if (str_contains($texto, 'totalcoin') || str_contains($texto, 'total coin')) {
            if (str_contains($texto, 'caja')) {
                return ['codigo' => 22, 'tipo' => self::TIPO_TOTALCOIN_QR];
            }

            return ['codigo' => 21, 'tipo' => self::TIPO_TOTALCOIN_QR];
        }
        if (str_contains($texto, 'mep')) {
            return ['codigo' => 7, 'tipo' => self::TIPO_MEP];
        }
        if (str_contains($texto, 'playuzu')) {
            return ['codigo' => 17, 'tipo' => self::TIPO_TICKET];
        }
        if (
            str_contains($texto, 'transf')
            || str_contains($texto, 'check ms')
            || str_contains($texto, 'macro')
            || str_contains($texto, 'itau')
        ) {
            return ['codigo' => 8, 'tipo' => self::TIPO_VARIOS];
        }
        if (self::esVisaTexto($texto)) {
            return ['codigo' => 26, 'tipo' => self::TIPO_TARJETA];
        }
        if (self::esMasterTexto($texto)) {
            return ['codigo' => 26, 'tipo' => self::TIPO_TARJETA];
        }
        if (str_contains($texto, 'cripto') || str_contains($texto, 'usdt') || str_contains($texto, 'satoshi')) {
            return ['codigo' => 9, 'tipo' => self::TIPO_EFE_CRIPTO];
        }
        if (str_contains($texto, 'dolar') || str_contains($texto, 'dólar')) {
            return ['codigo' => 2, 'tipo' => self::TIPO_EFE_DOLAR];
        }
        if (str_contains($texto, 'euro')) {
            return ['codigo' => 3, 'tipo' => self::TIPO_EFE_EURO];
        }
        if (preg_match('/\bqr\b/', $texto) === 1) {
            return ['codigo' => 4, 'tipo' => self::TIPO_QR];
        }
        if (
            str_contains($texto, 'efectivo pesos')
            || str_contains($texto, 'caja pesos')
        ) {
            return ['codigo' => 1, 'tipo' => self::TIPO_EFE_PESOS];
        }

        return null;
    }

    /** p-vtamaquina.c lee_rendvalor: Visa / Electron → tot_visa. */
    public static function esVisaTexto(string $texto): bool
    {
        $t = mb_strtolower($texto);

        return str_contains($t, 'visa') || str_contains($t, 'electron');
    }

    /** p-vtamaquina.c lee_rendvalor: Master / Maestro → tot_master. */
    public static function esMasterTexto(string $texto): bool
    {
        $t = mb_strtolower($texto);

        return str_contains($t, 'master') || str_contains($t, 'maestro');
    }

    public static function textoCaja(?Cuentacaja $caja): string
    {
        if ($caja === null) {
            return '';
        }

        return mb_strtolower(trim(
            (string) ($caja->nombre ?? '')
            .' '
            .(string) ($caja->descripcion_operaciones ?? '')
            .' '
            .$caja->etiquetaOperaciones()
        ));
    }
}
