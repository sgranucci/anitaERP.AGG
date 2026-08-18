<?php

namespace App\Support\Contable\LibroIvaDigital;

final class LibroIvaDigitalMapeosSupport
{
    /** @var array<string, string> tasa ERP → código LID 4 dígitos */
    private const ALICUOTA_LID_POR_TASA = [
        '0' => '0003',
        '2.5' => '0009',
        '5' => '0008',
        '10.5' => '0004',
        '21' => '0005',
        '27' => '0006',
    ];

    /** @var array<string, int> tasa ERP → código IVA simple (1 dígito) */
    private const ALICUOTA_IVA_SIMPLE_POR_TASA = [
        '0' => 3,
        '2.5' => 9,
        '5' => 8,
        '10.5' => 4,
        '21' => 5,
        '27' => 6,
    ];

    /** Desplazamiento letra B/C/M respecto al código base tipo A en tipotransaccion.codigo */
    private const OFFSET_LETRA = [
        'A' => 0,
        'B' => 5,
        'C' => 10,
        'M' => 50,
        'E' => 18,
        'T' => 194,
    ];

    public static function codigoAlicuotaLid(float $tasa): string
    {
        $key = (string) round($tasa, 1);
        if ($key === '21' || $key === '10.5' || $key === '2.5') {
            return self::ALICUOTA_LID_POR_TASA[$key] ?? '0003';
        }
        $keyInt = (string) (int) round($tasa);

        return self::ALICUOTA_LID_POR_TASA[$keyInt] ?? self::ALICUOTA_LID_POR_TASA[$key] ?? '0003';
    }

    public static function codigoAlicuotaIvaSimple(float $tasa): int
    {
        $key = (string) round($tasa, 1);
        if (isset(self::ALICUOTA_IVA_SIMPLE_POR_TASA[$key])) {
            return self::ALICUOTA_IVA_SIMPLE_POR_TASA[$key];
        }
        $keyInt = (string) (int) round($tasa);

        return self::ALICUOTA_IVA_SIMPLE_POR_TASA[$keyInt] ?? self::ALICUOTA_IVA_SIMPLE_POR_TASA[$key] ?? 3;
    }

    public static function tipoComprobanteVentas(string $codigoBase, string $letra): string
    {
        $base = (int) preg_replace('/\D+/', '', $codigoBase);
        $offset = self::OFFSET_LETRA[strtoupper($letra)] ?? 0;

        return str_pad((string) ($base + $offset), 3, '0', STR_PAD_LEFT);
    }

    public static function letraDesdeCodigoVenta(string $codigoVenta): string
    {
        // Incluye Z (RMV / informe Z interno Anita p-vtagastro).
        if (preg_match('/\b([ABCEMTZ])\b/i', $codigoVenta, $m)) {
            return strtoupper($m[1]);
        }
        if (preg_match('/\s([ABCEMTZ])-/i', $codigoVenta, $m)) {
            return strtoupper($m[1]);
        }

        return 'B';
    }

    public static function codigoMonedaAfip(?string $codigoMonedaErp, ?string $nombreMoneda = null): string
    {
        $codigo = strtoupper(trim((string) $codigoMonedaErp));
        if ($codigo === 'PES' || $codigo === '1' || stripos((string) $nombreMoneda, 'PES') !== false) {
            return 'PES';
        }
        if ($codigo === 'DOL' || $codigo === '2' || stripos((string) $nombreMoneda, 'DOL') !== false) {
            return 'DOL';
        }
        if (in_array($codigo, ['EUR', '060', '60', '3'], true) || stripos((string) $nombreMoneda, 'EURO') !== false) {
            return '060';
        }

        return 'PES';
    }

    /**
     * Campo 18 RG 4597: 4 enteros + 6 decimales.
     * Con moneda PES ARCA exige tipo de cambio = 1 (error: "Tipo de cambio es debe ser 1").
     */
    public static function tipoCambioArca(string $codigoMoneda, float $cotizacion): float
    {
        if (strtoupper(trim($codigoMoneda)) === 'PES') {
            return 1.0;
        }

        $tasa = abs($cotizacion);

        return $tasa > 0.000001 ? $tasa : 1.0;
    }

    /**
     * IVA simple — tipo de sujeto comprador (1=RI, 2=Monotributo, 3=CF/Exento/No alcanzado).
     */
    public static function tipoSujetoCompradorIvaSimple(?string $codigoExternoCondicionIva): int
    {
        $codigo = trim((string) $codigoExternoCondicionIva);
        if (in_array($codigo, ['6'], true)) {
            return 2;
        }
        if (in_array($codigo, ['1'], true)) {
            return 1;
        }

        return 3;
    }

    public static function importeCsvIvaSimple(float $valor): string
    {
        if (abs($valor) < 0.00001) {
            return '';
        }

        return number_format($valor, 2, ',', '');
    }
}
