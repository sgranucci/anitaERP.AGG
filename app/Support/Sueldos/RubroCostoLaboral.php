<?php

namespace App\Support\Sueldos;

/**
 * Rubros de composición del costo laboral (Anexo III Dto. 407 / Bejerman-Onvio).
 * Usados en la torta y el detalle "Costo total empleador".
 */
class RubroCostoLaboral
{
    public const SEGURIDAD_SOCIAL = 'seguridad_social';

    public const INSSJP = 'inssjp';

    public const OBRA_SOCIAL = 'obra_social';

    public const SINDICAL = 'sindical';

    public const CAMARAS = 'camaras';

    public const ART = 'art';

    public const SCVO = 'scvo';

    public const OTROS = 'otros';

    /** @var array<string, string> */
    public const ETIQUETAS = [
        self::SEGURIDAD_SOCIAL => 'Seguridad social',
        self::INSSJP => 'I.N.S.S.J.P. / PAMI',
        self::OBRA_SOCIAL => 'Obra social',
        self::SINDICAL => 'Sindical',
        self::CAMARAS => 'Cámaras / entidades',
        self::ART => 'A.R.T.',
        self::SCVO => 'SCVO',
        self::OTROS => 'Otros rubros',
    ];

    /** Etiquetas cortas para leyenda de torta (como Anita C). */
    public const ETIQUETAS_TORTA = [
        self::SEGURIDAD_SOCIAL => 'Seg. Social Empl.',
        self::INSSJP => 'PAMI',
        self::OBRA_SOCIAL => 'Obra Social',
        self::SINDICAL => 'Costo Sindical',
        self::CAMARAS => 'Cámaras',
        self::ART => 'ART',
        self::SCVO => 'SCVO',
        self::OTROS => 'Otros',
    ];

    /** @return list<string> */
    public static function todos(): array
    {
        return array_keys(self::ETIQUETAS);
    }

    public static function etiqueta(?string $rubro): string
    {
        return self::ETIQUETAS[$rubro] ?? (string) $rubro;
    }

    /**
     * Clasificación por descripción (espejo clasifica_concepto de l-recibolargo_anexoIII.fc).
     */
    public static function inferirDesdeDescripcion(?string $desc): string
    {
        $d = self::normalizar($desc);

        if ($d === '') {
            return self::OTROS;
        }

        if (
            str_contains($d, 'SCVO') || str_contains($d, 'SEGURO DE VIDA') || str_contains($d, 'VIDA OBLIG')
            || str_contains($d, 'SEG VIDA') || str_contains($d, 'SEGURO VIDA')
        ) {
            return self::SCVO;
        }

        if (
            str_contains($d, ' ART') || str_starts_with($d, 'ART') || str_contains($d, 'A R T')
            || str_contains($d, 'RIESGO') || str_contains($d, 'ACCIDENT') || str_contains($d, 'FFEP')
            || str_contains($d, 'CARGO ART')
        ) {
            return self::ART;
        }

        if (str_contains($d, 'PAMI') || str_contains($d, 'INSSJP')) {
            return self::INSSJP;
        }

        if (
            str_contains($d, 'OBRA SOC') || str_contains($d, ' O S ') || str_contains($d, 'O S ')
            || str_contains($d, 'OSDE') || str_contains($d, 'SWISS') || str_contains($d, 'MEDIFE')
            || str_contains($d, 'GALENO') || str_contains($d, 'CONTRIBUCION O S')
            || preg_match('/\bOS\b/', $d)
        ) {
            return self::OBRA_SOCIAL;
        }

        if (
            str_contains($d, 'SINDIC') || str_contains($d, 'GREMIAL') || str_contains($d, 'SIND ')
            || str_contains($d, 'CUOTA SOLID') || str_contains($d, 'APORTE SOLID')
            || str_contains($d, 'CAMARA') || str_contains($d, 'ENTIDAD EMPRES')
        ) {
            if (str_contains($d, 'CAMARA') || str_contains($d, 'ENTIDAD EMPRES')) {
                return self::CAMARAS;
            }

            return self::SINDICAL;
        }

        // SIPA / FNDE / Asig. familiares / previsional → seguridad social (empleador)
        if (
            str_contains($d, 'SIPA') || str_contains($d, 'FNDE') || str_contains($d, 'F N D E')
            || str_contains($d, 'PREVISIONAL') || str_contains($d, 'SIJP')
            || str_contains($d, 'ASIG FAM') || str_contains($d, 'ASIG. FAM')
            || str_contains($d, 'ASIGNACIONES FAM') || str_contains($d, 'ANSES')
            || str_contains($d, 'JUBIL') || str_contains($d, 'PENSION')
        ) {
            return self::SEGURIDAD_SOCIAL;
        }

        return self::SEGURIDAD_SOCIAL;
    }

    private static function normalizar(?string $texto): string
    {
        $t = mb_strtoupper(trim((string) $texto), 'UTF-8');
        $t = strtr($t, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
            '.' => ' ',
        ]);
        $t = preg_replace('/\s+/', ' ', $t) ?? $t;

        return $t;
    }
}
