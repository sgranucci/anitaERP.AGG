<?php

namespace App\Support\Ventas;

/**
 * Días de la semana en el ERP (ISO-8601 / Carbon::isoWeekday): 1 = lunes … 7 = domingo.
 *
 * Anita artm_dia NO usa ISO en todos los bridges:
 * - Biyemas y Rebisco: artm_dia 1 = domingo, 2 = lunes, …, 7 = sábado.
 * - Kandiko: permutación legacy distinta (ver MAPEO_KANDIKO).
 */
final class ViandaDiaSemanaSupport
{
    /** @var array<int, string> 1 = lunes … 7 = domingo (ISO, uso interno ERP) */
    public const ETIQUETAS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    public const MODO_DOMINGO_PRIMERO = 'domingo_primero';

    public const MODO_KANDIKO = 'kandiko';

    /** @var array<int, int> artm_dia Anita Kandiko → ISO ERP */
    private const MAPEO_KANDIKO = [
        1 => 7, // domingo
        2 => 6, // sábado
        3 => 2, // martes
        4 => 3, // miércoles
        5 => 4, // jueves
        6 => 1, // lunes
        7 => 5, // viernes
    ];

    /** @return list<int> */
    public static function diasValidos(): array
    {
        return array_keys(self::ETIQUETAS);
    }

    public static function etiqueta(int $dia): string
    {
        return self::ETIQUETAS[$dia] ?? ('Día '.$dia);
    }

    public static function diaValido(int $dia): bool
    {
        return isset(self::ETIQUETAS[$dia]);
    }

    /**
     * Convierte artm_dia del bridge Anita al día ISO almacenado en vianda_tipo_menu_articulo.
     */
    public static function desdeAnita(int $artmDia, ?int $empresaId = null): ?int
    {
        if ($artmDia <= 0) {
            return null;
        }

        return match (self::modoMapeoAnita($empresaId)) {
            self::MODO_KANDIKO => self::MAPEO_KANDIKO[$artmDia] ?? null,
            self::MODO_DOMINGO_PRIMERO => self::domingoPrimeroDesdeAnita($artmDia),
            default => null,
        };
    }

    /**
     * Convierte día ISO ERP a artm_dia para escribir en Anita (sync inverso / futuro).
     */
    public static function haciaAnita(int $diaIso, ?int $empresaId = null): ?int
    {
        if (! self::diaValido($diaIso)) {
            return null;
        }

        return match (self::modoMapeoAnita($empresaId)) {
            self::MODO_KANDIKO => self::invertirMapeo(self::MAPEO_KANDIKO)[$diaIso] ?? null,
            self::MODO_DOMINGO_PRIMERO => self::domingoPrimeroHaciaAnita($diaIso),
            default => null,
        };
    }

    private static function modoMapeoAnita(?int $empresaId): string
    {
        $mapa = (array) config('vianda_anita.mapeo_artm_dia_por_empresa', []);
        $empresaId = $empresaId ?? (int) config('vianda_anita.empresa_sync', 1);

        $modo = (string) ($mapa[$empresaId] ?? self::MODO_DOMINGO_PRIMERO);

        return in_array($modo, [self::MODO_DOMINGO_PRIMERO, self::MODO_KANDIKO], true)
            ? $modo
            : self::MODO_DOMINGO_PRIMERO;
    }

    /** Anita 1=dom … 7=sáb → ISO 1=lun … 7=dom */
    private static function domingoPrimeroDesdeAnita(int $artmDia): ?int
    {
        if ($artmDia < 1 || $artmDia > 7) {
            return null;
        }

        return $artmDia === 1 ? 7 : $artmDia - 1;
    }

    private static function domingoPrimeroHaciaAnita(int $diaIso): ?int
    {
        return $diaIso === 7 ? 1 : $diaIso + 1;
    }

    /**
     * @param  array<int, int>  $mapa
     * @return array<int, int>
     */
    private static function invertirMapeo(array $mapa): array
    {
        $invertido = [];
        foreach ($mapa as $artm => $iso) {
            $invertido[$iso] = $artm;
        }

        return $invertido;
    }
}
