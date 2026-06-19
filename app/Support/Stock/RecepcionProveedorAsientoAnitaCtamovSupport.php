<?php

namespace App\Support\Stock;

use App\ApiAnita;
use App\Models\Stock\Recepcion_Proveedor;

/**
 * Lectura de totales ctamov en Anita para asientos de recepción COM.
 */
final class RecepcionProveedorAsientoAnitaCtamovSupport
{
    /**
     * @return array{debe: float, haber: float, lineas: int}|null null si no hay filas en Anita
     */
    public static function totalesCtamovRecepcion(Recepcion_Proveedor $recepcion): ?array
    {
        $clave = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
        $tipo = str_replace("'", "''", trim((string) ($clave['tipo'] ?? '')));
        $letra = str_replace("'", "''", trim((string) ($clave['letra'] ?? '')));
        $sucursal = (int) ($clave['sucursal'] ?? 0);
        $nro = (int) ($clave['nro'] ?? 0);

        if ($tipo === '' || $nro <= 0) {
            return null;
        }

        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => 'contab',
            'tabla' => 'ctamov',
            'campos' => 'ctav_importe,ctav_d_h',
            'whereArmado' => " WHERE ctav_tipo='{$tipo}'"
                ." AND ctav_letra='{$letra}'"
                .' AND ctav_sucursal='.$sucursal
                .' AND ctav_nro='.$nro,
        ]);

        $filas = ApiAnita::decodificarListaFilas($raw);
        if ($filas === []) {
            return null;
        }

        $debe = 0.0;
        $haber = 0.0;
        foreach ($filas as $fila) {
            $row = is_array($fila) ? $fila : get_object_vars($fila);
            $importe = (float) ($row['ctav_importe'] ?? 0);
            $dh = strtoupper(trim((string) ($row['ctav_d_h'] ?? 'D')));
            if ($dh === 'H') {
                $haber += $importe;
            } else {
                $debe += $importe;
            }
        }

        return [
            'debe' => round($debe, 2),
            'haber' => round($haber, 2),
            'lineas' => count($filas),
        ];
    }
}
