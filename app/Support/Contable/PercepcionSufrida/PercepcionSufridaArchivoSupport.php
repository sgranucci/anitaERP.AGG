<?php

declare(strict_types=1);

namespace App\Support\Contable\PercepcionSufrida;

/**
 * Archivos de importación.
 *
 * SIFERE (formato predeterminado de percepciones sufridas): jurisdicción 3,
 * CUIT con guiones 13, fecha dd/mm/aaaa 10, sucursal 4, número 8, tipo 1, letra 1,
 * importe 11 con coma decimal. Terminador LF, como p-sifere.c.
 *
 * Percepciones de IVA: CSV de IVA Simple (F.2051), separador `;`.
 * Tipos vigentes: 1 factura, 2 recibo, 3 nota de crédito, 4 nota de débito, 5 otro.
 */
final class PercepcionSufridaArchivoSupport
{
    public const EOL = "\n";

    /**
     * @param  list<array<string, mixed>>  $cruzados
     */
    public static function sifere(array $cruzados, ?int $jurisdiccion = null): string
    {
        $out = '';
        foreach ($cruzados as $linea) {
            $jur = (int) ($linea['jurisdiccion'] ?? 0);
            if (! in_array($jur, [901, 902], true)) {
                continue;
            }
            if ($jurisdiccion !== null && $jur !== $jurisdiccion) {
                continue;
            }
            $cuit = self::cuitConGuiones((string) ($linea['cuit'] ?? ''));
            if ($cuit === '') {
                continue;
            }
            $importe = round((float) ($linea['importe'] ?? 0), 2);
            $tipo = self::tipoSifere((string) ($linea['tipo'] ?? ''), $importe);
            $letra = strtoupper(substr(trim((string) ($linea['letra'] ?? '')).' ', 0, 1));
            if (trim($letra) === '') {
                $letra = ' ';
            }
            $importeTxt = str_replace('.', ',', sprintf('%011.2f', abs($importe)));
            $out .= sprintf(
                '%03d%13s%10s%04d%08d%1s%1s%s',
                $jur,
                $cuit,
                self::fechaDma((string) ($linea['fecha'] ?? '')),
                max(0, (int) ($linea['sucursal'] ?? 0)) % 10000,
                max(0, (int) ($linea['nro'] ?? 0)) % 100000000,
                $tipo,
                $letra,
                $importeTxt,
            ).self::EOL;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $cruzados
     */
    public static function percepcionIva(array $cruzados): string
    {
        $regimen = PercepcionSufridaCorteSupport::regimenPercepcionIva();
        $out = '';
        foreach ($cruzados as $linea) {
            $cuit = preg_replace('/\D/', '', (string) ($linea['cuit'] ?? '')) ?? '';
            if (strlen($cuit) !== 11) {
                continue;
            }
            $importe = round((float) ($linea['importe'] ?? 0), 2);
            if (abs($importe) < 0.009) {
                continue;
            }
            $tipo = self::tipoComprobanteIva((string) ($linea['tipo'] ?? ''), $importe);
            $nro = self::numeroComprobanteIva(
                $tipo,
                (int) ($linea['sucursal'] ?? 0),
                (int) ($linea['nro'] ?? 0),
            );
            $fecha = (string) ($linea['fecha'] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) !== 1) {
                continue;
            }
            $importeTxt = number_format(abs($importe), 2, ',', '');
            // C3 vacío: sin CUIT intermediario (el diseño pide el separador igual).
            $out .= $regimen.';'.$cuit.';;'.$fecha.';'.$tipo.';'.$nro.';'.$importeTxt.self::EOL;
        }

        return $out;
    }

    public static function tipoSifere(string $tipo, float $importe): string
    {
        if ($importe < 0) {
            return 'C';
        }
        $t = strtoupper(substr(trim($tipo), 0, 3));

        return match (true) {
            str_starts_with($t, 'NC') => 'C',
            str_starts_with($t, 'ND') => 'D',
            in_array($t, ['FAC', 'FIS', 'FGA', 'FCP', 'FAE', 'FOV'], true) => 'F',
            in_array($t, ['REC', 'ING'], true) => 'R',
            default => 'O',
        };
    }

    public static function tipoComprobanteIva(string $tipo, float $importe): int
    {
        if ($importe < 0) {
            return 3;
        }
        $t = strtoupper(substr(trim($tipo), 0, 3));

        return match (true) {
            str_starts_with($t, 'NC') => 3,
            str_starts_with($t, 'ND') => 4,
            in_array($t, ['REC', 'ING'], true) => 2,
            in_array($t, ['FAC', 'FIS', 'FGA', 'FCP', 'FAE'], true) => 1,
            default => 5,
        };
    }

    public static function numeroComprobanteIva(int $tipo, int $sucursal, int $nro): string
    {
        $sucursal = max(0, $sucursal);
        $nro = max(0, $nro);
        if (in_array($tipo, [1, 2, 3, 4], true)) {
            if ($sucursal === 0) {
                $sucursal = 1;
            }

            return sprintf('%05d-%08d', $sucursal % 100000, $nro % 100000000);
        }
        if ($sucursal > 0) {
            return $sucursal.'-'.$nro;
        }

        return (string) $nro;
    }

    public static function cuitConGuiones(string $cuit): string
    {
        $d = preg_replace('/\D/', '', $cuit) ?? '';
        if (strlen($d) !== 11) {
            return '';
        }

        return substr($d, 0, 2).'-'.substr($d, 2, 8).'-'.substr($d, 10, 1);
    }

    public static function fechaDma(string $iso): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso) !== 1) {
            return str_repeat(' ', 10);
        }

        return substr($iso, 8, 2).'/'.substr($iso, 5, 2).'/'.substr($iso, 0, 4);
    }
}
