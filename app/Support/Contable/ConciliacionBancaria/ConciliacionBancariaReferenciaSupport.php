<?php

namespace App\Support\Contable\ConciliacionBancaria;

/**
 * Referencias cruzables entre mayor analítico e Interbanking (validado cuenta 127 Macro).
 */
final class ConciliacionBancariaReferenciaSupport
{
    /**
     * @param  array<string, mixed>  $contable
     */
    public static function extraerChequeContable(array $contable): ?string
    {
        $desc = (string) ($contable['descripcion'] ?? '');
        if (preg_match('/\bCh:\s*(\d{5,12})\b/i', $desc, $m)) {
            return self::normalizarNumero($m[1]);
        }

        return null;
    }

    /**
     * Número de orden de pago (#122853) o cola del comprobante (00122853).
     *
     * @param  array<string, mixed>  $contable
     */
    public static function extraerOrdenPagoContable(array $contable): ?string
    {
        $desc = (string) ($contable['descripcion'] ?? '');
        if (preg_match('/#\s*(\d{4,10})\b/', $desc, $m)) {
            return self::normalizarNumero($m[1]);
        }

        $comp = preg_replace('/\D/', '', (string) ($contable['comprobante'] ?? ''));
        if (strlen($comp) >= 6) {
            return self::normalizarNumero(substr($comp, -8));
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $contable
     */
    public static function extraerCuitContable(array $contable): ?string
    {
        $cuit = preg_replace('/\D/', '', (string) ($contable['cuit'] ?? ''));

        return strlen($cuit) >= 10 ? $cuit : null;
    }

    /**
     * @param  array<string, mixed>  $banco
     */
    public static function extraerChequeBanco(array $banco): ?string
    {
        $voucher = (string) ($banco['voucher_number'] ?? '');
        if ($voucher !== '' && preg_match('/^\d{5,12}$/', self::normalizarNumero($voucher))) {
            return self::normalizarNumero($voucher);
        }

        $desc = strtoupper((string) ($banco['code_description_ib'] ?? ''));
        if (! self::esConceptoCheque($desc)) {
            return null;
        }

        return $voucher !== '' ? self::normalizarNumero($voucher) : null;
    }

    /**
     * @param  array<string, mixed>  $banco
     */
    public static function extraerCuitBanco(array $banco): ?string
    {
        $cuit = preg_replace('/\D/', '', (string) ($banco['customer_cuit'] ?? ''));

        return strlen($cuit) >= 10 ? $cuit : null;
    }

    /**
     * @param  array<string, mixed>  $contable
     * @param  array<string, mixed>  $banco
     */
    public static function coincideCheque(array $contable, array $banco): bool
    {
        $chC = self::extraerChequeContable($contable);
        $chB = self::extraerChequeBanco($banco);

        return $chC !== null && $chB !== null && $chC === $chB;
    }

    /**
     * @param  array<string, mixed>  $contable
     * @param  array<string, mixed>  $banco
     */
    public static function coincideCuit(array $contable, array $banco): bool
    {
        $cC = self::extraerCuitContable($contable);
        $cB = self::extraerCuitBanco($banco);

        return $cC !== null && $cB !== null && $cC === $cB;
    }

    /**
     * @param  array<string, mixed>  $contable
     * @param  array<string, mixed>  $banco
     */
    public static function coincideVoucherEnTextoContable(array $contable, array $banco): bool
    {
        $ref = self::normalizarNumero((string) ($banco['voucher_number'] ?? ''));
        if ($ref === '' || $ref === '0' || strlen($ref) < 5) {
            return false;
        }

        $textoDigitos = preg_replace(
            '/\D/',
            '',
            (string) ($contable['descripcion'] ?? '').(string) ($contable['comprobante'] ?? '')
        ) ?? '';

        return $textoDigitos !== '' && str_contains($textoDigitos, $ref);
    }

    /**
     * @param  array<string, mixed>  $contable
     * @param  array<string, mixed>  $banco
     */
    public static function tipoCompCompatibleConBanco(array $contable, array $banco): bool
    {
        $tipo = strtoupper(trim((string) ($contable['tipo_comp'] ?? '')));
        if ($tipo === '') {
            return false;
        }

        $concepto = strtoupper(trim((string) ($banco['code_description_ib'] ?? '')));
        $descBanco = strtoupper(trim((string) ($banco['code_description_bank'] ?? '')));
        $textoBanco = $concepto.' '.$descBanco;

        $mapa = config('conciliacion_bancaria.tipo_comp_conceptos_banco', []);
        $patrones = $mapa[$tipo] ?? null;
        if (! is_array($patrones) || $patrones === []) {
            return false;
        }

        foreach ($patrones as $patron) {
            if ($patron !== '' && str_contains($textoBanco, strtoupper((string) $patron))) {
                return true;
            }
        }

        return false;
    }

    public static function esConceptoCheque(string $conceptoIb): bool
    {
        $c = strtoupper(trim($conceptoIb));

        foreach (['CH DEP', 'CH/PAG', 'CH/RECIB', 'CHEQUE', 'CHP', 'CHD'] as $p) {
            if (str_contains($c, $p)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $contable
     * @param  array<string, mixed>  $banco
     */
    public static function diasToleranciaFecha(array $contable, array $banco, int $default): int
    {
        if (self::coincideCheque($contable, $banco) || self::esConceptoCheque((string) ($banco['code_description_ib'] ?? ''))) {
            return max($default, (int) config('conciliacion_bancaria.dias_tolerancia_fecha_cheque', 30));
        }

        if (self::coincideCuit($contable, $banco) || self::coincideVoucherEnTextoContable($contable, $banco)) {
            return max($default, (int) config('conciliacion_bancaria.dias_tolerancia_fecha_pago', 7));
        }

        if (self::tipoCompCompatibleConBanco($contable, $banco)) {
            return max($default, (int) config('conciliacion_bancaria.dias_tolerancia_fecha_pago', 7));
        }

        return $default;
    }

    /**
     * @param  array<string, mixed>  $contable
     * @param  array<string, mixed>  $banco
     */
    public static function puntajeReferencia(array $contable, array $banco): int
    {
        $score = 0;

        if (self::coincideCheque($contable, $banco)) {
            $score += 80;
        }

        if (self::coincideCuit($contable, $banco)) {
            $score += 50;
        }

        if (self::coincideVoucherEnTextoContable($contable, $banco)) {
            $score += 40;
        }

        $orden = self::extraerOrdenPagoContable($contable);
        if ($orden !== null) {
            $comp = preg_replace('/\D/', '', (string) ($contable['comprobante'] ?? ''));
            if ($comp !== '' && str_ends_with($comp, $orden)) {
                $score += 15;
            }
        }

        if (self::tipoCompCompatibleConBanco($contable, $banco)) {
            $score += 20;
        }

        $refB = self::normalizarNumero((string) ($banco['voucher_number'] ?? ''));
        $textoDigitos = preg_replace(
            '/\D/',
            '',
            (string) ($contable['descripcion'] ?? '').(string) ($contable['comprobante'] ?? '')
        ) ?? '';
        if ($refB !== '' && $refB !== '0' && strlen($refB) >= 5 && str_contains($textoDigitos, $refB)) {
            $score += 25;
        }

        $descC = (string) ($contable['descripcion'] ?? '');
        $concepto = strtoupper((string) ($banco['code_description_ib'] ?? ''));
        if ($concepto !== '' && str_contains(strtoupper($descC), $concepto)) {
            $score += 10;
        }

        return $score;
    }

    private static function normalizarNumero(string $valor): string
    {
        $d = preg_replace('/\D/', '', $valor) ?? '';

        return ltrim($d, '0') !== '' ? ltrim($d, '0') : '0';
    }
}
