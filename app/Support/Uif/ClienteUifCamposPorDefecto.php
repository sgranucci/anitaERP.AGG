<?php

namespace App\Support\Uif;

use Illuminate\Support\Facades\DB;

/**
 * Valores por defecto de la solapa UIF cuando el formulario no los envía
 * (p. ej. perfil cajero: campos deshabilitados no viajan en el POST).
 */
final class ClienteUifCamposPorDefecto
{
    /** @var array<string, mixed> */
    private const DEFECTOS_ALTA = [
        'nivelsocioeconomico_uif_id' => 8,
        'riesgopep' => 'BAJO',
        'firmodeclaracionjurada' => 'N',
        'resideparaisofiscal' => 'N',
        'resideexterior' => 'N',
        'cumplenormativaso' => 'N',
        'fotodocumento' => '',
    ];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function aplicarEnAlta(array $data): array
    {
        foreach (self::DEFECTOS_ALTA as $campo => $valorDefecto) {
            if (! self::valorPresente($data, $campo)) {
                $data[$campo] = $valorDefecto;
            }
        }

        if (isset($data['nivelsocioeconomico_uif_id'])) {
            $id = (int) $data['nivelsocioeconomico_uif_id'];
            if ($id <= 0 || ! DB::table('nivelsocioeconomico_uif')->where('id', $id)->exists()) {
                $data['nivelsocioeconomico_uif_id'] = self::DEFECTOS_ALTA['nivelsocioeconomico_uif_id'];
            }
        }

        if (isset($data['actividadso']) && trim((string) $data['actividadso']) === '') {
            $data['actividadso'] = null;
        }

        foreach (['piso', 'departamento'] as $campoCadenaVacia) {
            $data[$campoCadenaVacia] = trim((string) ($data[$campoCadenaVacia] ?? ''));
        }

        return $data;
    }

    /**
     * Misma normalización en edición cuando el request envía null en campos NOT NULL.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function aplicarEnActualizacion(array $data): array
    {
        foreach (['piso', 'departamento'] as $campoCadenaVacia) {
            if (array_key_exists($campoCadenaVacia, $data)) {
                $data[$campoCadenaVacia] = trim((string) ($data[$campoCadenaVacia] ?? ''));
            }
        }

        if (isset($data['actividadso']) && trim((string) $data['actividadso']) === '') {
            $data['actividadso'] = null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function valorPresente(array $data, string $campo): bool
    {
        if (! array_key_exists($campo, $data)) {
            return false;
        }

        $valor = $data[$campo];

        if ($valor === null) {
            return false;
        }

        if (is_string($valor) && trim($valor) === '') {
            return false;
        }

        if ($campo === 'nivelsocioeconomico_uif_id' && (int) $valor <= 0) {
            return false;
        }

        return true;
    }
}
