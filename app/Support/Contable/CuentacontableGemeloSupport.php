<?php

namespace App\Support\Contable;

/**
 * Gemelo TOTAL …9999 que Anita necesita al lado de cada título.
 */
class CuentacontableGemeloSupport
{
    /**
     * @param  array<string, mixed>  $grupo
     * @return array<string, mixed>|null
     */
    public static function payloadTotalizadora(array $grupo): ?array
    {
        if ((string) ($grupo['tipocuenta'] ?? '') !== CuentacontableArbolSupport::TIPO_TITULO) {
            return null;
        }

        $codigo = CuentacontableArbolSupport::codigoTotalizadoraDeGrupo((string) ($grupo['codigo'] ?? ''));
        if ($codigo === null) {
            return null;
        }

        $nombreGrupo = trim((string) ($grupo['nombre'] ?? ''));
        $nombre = $nombreGrupo === '' ? 'TOTAL' : 'TOTAL '.$nombreGrupo;

        return [
            'empresa_id' => (int) ($grupo['empresa_id'] ?? 0),
            'rubrocontable_id' => (int) ($grupo['rubrocontable_id'] ?? 0),
            'nombre' => mb_substr($nombre, 0, 100),
            'codigo' => $codigo,
            'tipocuenta' => CuentacontableArbolSupport::TIPO_TOTALIZADORA,
            'nivel' => (int) ($grupo['nivel'] ?? 1),
            'monetaria' => (string) ($grupo['monetaria'] ?? 'N'),
            'manejaccosto' => 'N',
            'ajustamonedaextranjera' => (string) ($grupo['ajustamonedaextranjera'] ?? 'N'),
            'conceptogasto_id' => null,
            'cuentacontable_difcambio_id' => null,
        ];
    }
}
