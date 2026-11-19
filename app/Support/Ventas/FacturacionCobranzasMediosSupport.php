<?php

declare(strict_types=1);

namespace App\Support\Ventas;

/**
 * Clasifica medios de cobro (efectivo / cheques / valores) para el reporte facturación y cobranzas.
 * En Anita l-factycobr los depósitos bancarios (BFR/BNA/BPR) se listaban aparte; acá se agrupan en «valores».
 */
final class FacturacionCobranzasMediosSupport
{
    /**
     * @return array{efectivo: float, cheques: float, valores: float, varios: float, total: float}
     */
    public static function vacios(): array
    {
        return [
            'efectivo' => 0.0,
            'cheques' => 0.0,
            'valores' => 0.0,
            'varios' => 0.0,
            'total' => 0.0,
        ];
    }

    /**
     * Clasifica una línea de auxpag Anita (axp_tipo_ap).
     * Las aplicaciones a comprobantes (FAC/NCR/…) no son medios: se ignoran (null).
     *
     * @return null|'efectivo'|'cheques'|'valores'|'varios'
     */
    public static function bucketDesdeTipoApli(string $tipoApli): ?string
    {
        $t = strtoupper(substr(trim($tipoApli), 0, 3));
        if ($t === '') {
            return 'varios';
        }

        // Aplicación a factura / NC / ND / remito: no es medio de cobro.
        if (in_array($t, ['FAC', 'FAF', 'FAS', 'NCR', 'NCA', 'NCB', 'NCC', 'NDB', 'NDA', 'NDC', 'REM', 'TICK'], true)
            || str_starts_with($t, 'FA')
            || str_starts_with($t, 'NC')
            || str_starts_with($t, 'ND')) {
            return null;
        }

        if (in_array($t, ['CHT', 'CTC', 'MUL', 'CHP', 'CHE'], true) || str_starts_with($t, 'CH')) {
            return 'cheques';
        }
        if (in_array($t, ['BFR', 'BFA', 'BNA', 'BPR', 'TRA', 'DEP', 'TRF', 'TRB'], true)
            || str_starts_with($t, 'B')
            || str_starts_with($t, 'TR')) {
            return 'valores';
        }
        // ED1 / EFE / EFT / ATE / efectivo genérico (tctes tipo_imp E en Anita).
        if (in_array($t, ['EFE', 'EFT', 'ATE', 'ED1', 'ED2', 'ED3'], true)
            || str_starts_with($t, 'EF')
            || str_starts_with($t, 'ED')) {
            return 'efectivo';
        }

        return 'varios';
    }

    /**
     * Clasifica cuenta de caja ERP por código/nombre.
     */
    public static function bucketDesdeCuentacaja(string $codigo, string $nombre): string
    {
        $txt = strtoupper(trim($codigo.' '.$nombre));
        if ($txt === '') {
            return 'varios';
        }
        if (str_contains($txt, 'CHEQUE') || str_contains($txt, 'E-CHEQ') || str_contains($txt, 'ECHEQ')) {
            return 'cheques';
        }
        if (str_contains($txt, 'EFECTIVO') || preg_match('/\bEFE\b/', $txt) === 1) {
            return 'efectivo';
        }
        if (
            str_contains($txt, 'TRANSF')
            || str_contains($txt, 'BANCO')
            || str_contains($txt, 'DEPOSITO')
            || str_contains($txt, 'DEPÓSITO')
            || str_contains($txt, 'CBU')
        ) {
            return 'valores';
        }

        return 'varios';
    }

    /**
     * @param  array{efectivo: float, cheques: float, valores: float, varios: float, total?: float}  $medios
     * @return array{efectivo: float, cheques: float, valores: float, varios: float, total: float}
     */
    public static function cerrarConTotalRecibo(array $medios, float $totalRecibo): array
    {
        $efectivo = round((float) ($medios['efectivo'] ?? 0), 2);
        $cheques = round((float) ($medios['cheques'] ?? 0), 2);
        $valores = round((float) ($medios['valores'] ?? 0), 2);
        $suma = round($efectivo + $cheques + $valores, 2);
        $varios = round((float) ($medios['varios'] ?? 0), 2);
        $total = round($totalRecibo, 2);
        if (abs($varios) < 0.0001 && abs($total - $suma) >= 0.01) {
            $varios = round($total - $suma, 2);
        }
        if (abs($total) < 0.0001) {
            $total = round($efectivo + $cheques + $valores + $varios, 2);
        }

        return [
            'efectivo' => $efectivo,
            'cheques' => $cheques,
            'valores' => $valores,
            'varios' => $varios,
            'total' => $total,
        ];
    }
}
