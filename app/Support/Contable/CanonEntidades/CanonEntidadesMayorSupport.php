<?php

declare(strict_types=1);

namespace App\Support\Contable\CanonEntidades;

/**
 * Partición del mayor 215010-003: solo Haber de tipos MAQ + BIN (devengamiento).
 * El Debe (pagos a la entidad) no entra en la comparación.
 */
final class CanonEntidadesMayorSupport
{
    /** @var list<string> */
    public const TIPOS_DEVENGAMIENTO = ['MAQ', 'BIN'];

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return array<string, mixed>
     */
    public static function particionar(array $movimientos): array
    {
        $maq = [];
        $bin = [];
        $otros = [];
        $haberMaq = 0.0;
        $haberBin = 0.0;
        $haberOtros = 0.0;
        $debeTotal = 0.0;

        foreach ($movimientos as $mov) {
            $tipo = self::tipoDe($mov);
            $haber = round((float) ($mov['haber'] ?? 0), 2);
            $debe = round((float) ($mov['debe'] ?? 0), 2);
            $debeTotal += $debe;

            $marcado = array_merge($mov, ['tipo' => $tipo]);
            if ($tipo === 'MAQ') {
                $maq[] = $marcado;
                $haberMaq += $haber;
            } elseif ($tipo === 'BIN') {
                $bin[] = $marcado;
                $haberBin += $haber;
            } else {
                $otros[] = $marcado;
                $haberOtros += $haber;
            }
        }

        $haberMaq = round($haberMaq, 2);
        $haberBin = round($haberBin, 2);

        return [
            'maq' => $maq,
            'bin' => $bin,
            'otros' => $otros,
            'comparables' => array_merge($maq, $bin),
            'haber_maq' => $haberMaq,
            'haber_bin' => $haberBin,
            'haber_otros' => round($haberOtros, 2),
            'haber_total' => round($haberMaq + $haberBin, 2),
            'debe_total' => round($debeTotal, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $mov
     */
    public static function tipoDe(array $mov): string
    {
        foreach (['tipo', 'ctav_tipo_asiento', 'subd_tipo', 'tipoasiento'] as $campo) {
            $tipo = strtoupper(trim((string) ($mov[$campo] ?? '')));
            if (in_array($tipo, self::TIPOS_DEVENGAMIENTO, true)) {
                return $tipo;
            }
        }

        return '';
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return list<array<string, mixed>>
     */
    public static function deduplicar(array $movimientos): array
    {
        $vistos = [];
        $out = [];
        foreach ($movimientos as $mov) {
            $clave = implode('|', [
                (string) ($mov['fecha'] ?? ''),
                (string) ($mov['asiento_id'] ?? ''),
                (string) ($mov['cuenta_codigo'] ?? ''),
                number_format((float) ($mov['haber'] ?? 0), 2, '.', ''),
                number_format((float) ($mov['debe'] ?? 0), 2, '.', ''),
                self::tipoDe($mov),
            ]);
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $out[] = $mov;
        }

        return $out;
    }
}
