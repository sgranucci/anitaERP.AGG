<?php

namespace App\Support\Stock;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * recepmae / aplicped según instalación.
 *
 * - AGG: recm_com_* + documentoid; aplicped con aplp_nro_interno.
 * - El Bierzo: sin recm_com_* ni documentoid; sin aplp_nro_interno.
 * - Crown (/usr2/casino, verificado 17/oct/2026): igual que Bierzo en recepmae
 *   (sin recm_com_*); OC se resuelve por aplicped (aplp_ref_* = PEP).
 *   recm_sucursal = casino (puede ser 0 = Adm.Central).
 */
final class RecepcionProveedorAnitaEsquemaSupport
{
    public static function esCrown(): bool
    {
        return EntornoEmpresaSupport::esCrown();
    }

    public static function esquemaReducido(): bool
    {
        return EntornoEmpresaSupport::esElBierzo() || self::esCrown();
    }

    /**
     * @return list<string>
     */
    public static function columnasRecepmaeAusentes(): array
    {
        return [
            'recm_com_tipo',
            'recm_com_letra',
            'recm_com_sucursal',
            'recm_com_nro',
            'recm_documentoid',
        ];
    }

    /**
     * @return list<string>
     */
    public static function columnasAplicpedAusentes(): array
    {
        return ['aplp_nro_interno'];
    }

    /**
     * Crown recepmov: sin recv_empresa ni recv_cotizacion (verificado 17/oct/2026).
     *
     * @return list<string>
     */
    public static function columnasRecepmovAusentes(): array
    {
        if (! self::esCrown()) {
            return [];
        }

        return ['recv_empresa', 'recv_cotizacion'];
    }

    public static function camposRecepmae(string $campos): string
    {
        return self::filtrarCampos($campos, self::columnasRecepmaeAusentes());
    }

    public static function camposRecepmov(string $campos): string
    {
        return self::filtrarCampos($campos, self::columnasRecepmovAusentes());
    }

    /**
     * Crown/Bierzo: sucursal 0 es válida (Adm.Central).
     */
    public static function sucursalValida(int $sucursal): bool
    {
        if (self::esCrown() || EntornoEmpresaSupport::esElBierzo()) {
            return $sucursal >= 0;
        }

        return $sucursal > 0;
    }

    /**
     * @param  array<string, string>  $columnas
     * @param  list<string>  $ausentes
     * @return array<string, string>
     */
    public static function filtrarEscritura(array $columnas, array $ausentes): array
    {
        if (! self::esquemaReducido()) {
            return $columnas;
        }

        foreach ($ausentes as $columna) {
            unset($columnas[$columna]);
        }

        return $columnas;
    }

    /**
     * @param  list<string>  $ausentes
     */
    public static function filtrarCampos(string $campos, array $ausentes): string
    {
        if (! self::esquemaReducido() || $ausentes === []) {
            return $campos;
        }

        $drop = array_fill_keys($ausentes, true);
        $out = [];
        foreach (explode(',', $campos) as $parte) {
            $parte = trim($parte);
            if ($parte === '') {
                continue;
            }
            $nombre = strtolower(trim((string) preg_split('/\s+as\s+/i', $parte)[0]));
            if (isset($drop[$nombre])) {
                continue;
            }
            $out[] = $parte;
        }

        return implode(', ', $out);
    }
}
