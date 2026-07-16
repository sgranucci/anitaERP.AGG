<?php

namespace App\Support\Caja;

/**
 * Mapeo campo a campo: clivip (Anita base_admin) → cliente_vip_caja (ERP).
 */
final class ClivipFieldMapper
{
    public static function mapNumeroid(object $row): ?int
    {
        $numeroid = (int) ($row->inumeroid ?? $row->INUMEROID ?? 0);

        return $numeroid > 0 ? $numeroid : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function mapAll(object $row, int $empresaId): array
    {
        return [
            'empresa_id' => $empresaId,
            'numeroid' => self::mapNumeroid($row),
            'nrodocumento' => self::mapTexto($row, 'cnrodocumento', 20),
            'apellido' => self::mapTexto($row, 'capellido', 40),
            'nombre' => self::mapTexto($row, 'cnombre', 40),
            'usualta_id' => self::mapEntero($row, 'iusualtaid'),
            'fecha_alta' => self::mapEntero($row, 'ifechaalta'),
            'hora_alta' => self::mapTexto($row, 'choraalta', 5),
            'usumod_id' => self::mapEntero($row, 'iusuumodid'),
            'fecha_mod' => self::mapEntero($row, 'ifechaumod'),
            'hora_mod' => self::mapTexto($row, 'choraumod', 5),
            'nickname' => self::mapTexto($row, 'clivi_nickname', 30),
            'localidad' => self::mapTexto($row, 'clivi_localidad', 15),
        ];
    }

    private static function mapTexto(object $row, string $campo, int $maxLen): ?string
    {
        $upper = strtoupper($campo);
        $valor = trim((string) ($row->{$campo} ?? $row->{$upper} ?? ''));

        if ($valor === '') {
            return null;
        }

        return mb_substr($valor, 0, $maxLen);
    }

    private static function mapEntero(object $row, string $campo): ?int
    {
        $upper = strtoupper($campo);
        $valor = (int) ($row->{$campo} ?? $row->{$upper} ?? 0);

        return $valor !== 0 ? $valor : null;
    }
}
