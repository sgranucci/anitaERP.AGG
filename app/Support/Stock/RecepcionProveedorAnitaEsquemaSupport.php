<?php

namespace App\Support\Stock;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * recepmae / aplicped de El Bierzo (/usr2/bierzo compras, 5/oct/2026):
 * no tienen recm_com_*, recm_documentoid ni aplp_nro_interno.
 * AGG sí. La vinculación ERP↔Anita en Bierzo es por clave COM (tipo/letra/sucursal/nro).
 */
final class RecepcionProveedorAnitaEsquemaSupport
{
    public static function esquemaReducido(): bool
    {
        return EntornoEmpresaSupport::esElBierzo();
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

    public static function camposRecepmae(string $campos): string
    {
        return self::filtrarCampos($campos, self::columnasRecepmaeAusentes());
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
