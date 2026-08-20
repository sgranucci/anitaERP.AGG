<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\ApiAnita;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Marca Anita t_comp.tcomp_subdiar: solo 'V' va al subdiario IVA-ventas.
 * El proceso CAEA no debe informar comprobantes sin esa marca (ej. NDA).
 */
final class ArcaCaeaAnitaIvaVentasSupport
{
    public const SUBDIARIO_IVA_VENTAS = 'V';

    /** @var array<string, string>|null tipo Anita => tcomp_subdiar */
    private static ?array $mapaSubdiario = null;

    private static bool $cargaFallida = false;

    public static function resetCache(): void
    {
        self::$mapaSubdiario = null;
        self::$cargaFallida = false;
    }

    /**
     * ¿El tipo Anita está marcado para IVA-ventas?
     * Si Anita no responde, no se informa (fail closed).
     */
    public static function vaAlSubdiarioIvaVentas(string $tipoAnita): bool
    {
        $tipo = strtoupper(trim($tipoAnita));
        if ($tipo === '') {
            return false;
        }

        $mapa = self::mapaSubdiario();
        if ($mapa === []) {
            return false;
        }

        return strtoupper((string) ($mapa[$tipo] ?? '')) === self::SUBDIARIO_IVA_VENTAS;
    }

    /**
     * En ERP: si el tipo no está en t_comp, no se excluye.
     * Si está y no es V, no va a CAEA.
     */
    public static function erpVaAlSubdiarioIvaVentas(?string $abreviatura): bool
    {
        $tipo = strtoupper(trim((string) $abreviatura));
        if ($tipo === '') {
            return true;
        }

        $mapa = self::mapaSubdiario();
        if ($mapa === [] || ! array_key_exists($tipo, $mapa)) {
            return true;
        }

        return strtoupper($mapa[$tipo]) === self::SUBDIARIO_IVA_VENTAS;
    }

    /**
     * @return list<string>
     */
    public static function tiposQueVanAlIvaVentas(): array
    {
        $out = [];
        foreach (self::mapaSubdiario() as $tipo => $subdiar) {
            if (strtoupper((string) $subdiar) === self::SUBDIARIO_IVA_VENTAS) {
                $out[] = $tipo;
            }
        }

        return $out;
    }

    /**
     * Tipos con marca distinta de V (NDA, remitos, internos, etc.).
     *
     * @return list<string>
     */
    public static function tiposQueNoVanAlIvaVentas(): array
    {
        $out = [];
        foreach (self::mapaSubdiario() as $tipo => $subdiar) {
            if (strtoupper((string) $subdiar) !== self::SUBDIARIO_IVA_VENTAS) {
                $out[] = $tipo;
            }
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    public static function mapaSubdiario(): array
    {
        if (self::$mapaSubdiario !== null) {
            return self::$mapaSubdiario;
        }

        if (self::$cargaFallida) {
            return [];
        }

        try {
            $parsed = ApiAnita::parsearRespuestaLista((new ApiAnita)->apiCall([
                'acc' => 'list',
                'sistema' => 'ventas',
                'tabla' => 't_comp',
                'campos' => 'tcomp_clave,tcomp_subdiar,tcomp_desc,tcomp_tipo_comp',
                'whereArmado' => '',
            ]));
        } catch (Throwable $e) {
            self::$cargaFallida = true;
            Log::warning('arca.caea.iva_ventas.t_comp_fallo', ['msg' => $e->getMessage()]);

            return [];
        }

        if ($parsed['error_lectura'] !== null) {
            self::$cargaFallida = true;
            Log::warning('arca.caea.iva_ventas.t_comp_fallo', ['msg' => $parsed['error_lectura']]);

            return [];
        }

        $mapa = [];
        foreach ($parsed['filas'] as $fila) {
            $row = (array) $fila;
            $clave = strtoupper(trim((string) ($row['tcomp_clave'] ?? '')));
            if ($clave === '') {
                continue;
            }
            $mapa[$clave] = strtoupper(trim((string) ($row['tcomp_subdiar'] ?? '')));
        }

        self::$mapaSubdiario = $mapa;

        return $mapa;
    }
}
