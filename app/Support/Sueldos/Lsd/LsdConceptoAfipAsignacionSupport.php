<?php

namespace App\Support\Sueldos\Lsd;

use App\Support\Sueldos\ConceptoTipo;

/**
 * Sugiere código AFIP (LSD) a partir del tipo y la descripción del concepto ERP.
 * No inventa códigos fuera del catálogo / rangos oficiales.
 * Los acumuladores y cantidades del 04 (1000, 491, etc.) se omiten a propósito.
 */
class LsdConceptoAfipAsignacionSupport
{
    /** @var list<int> */
    public const OMITIR_CODIGOS = [
        230, 484, 490, 491, 997, 998, 999, 1000, 1001, 1002, 1501, 1502, 1550,
    ];

    /**
     * @return array{codigo: string, motivo: string, confianza: string}|null
     */
    public static function sugerir(int $codigoInterno, string $descripcion, string $tipo): ?array
    {
        if (self::debeOmitir($codigoInterno, $descripcion, $tipo)) {
            return null;
        }

        $norm = self::normalizar($descripcion);
        foreach (self::reglas() as $regla) {
            $tipos = $regla['tipos'] ?? null;
            if (is_array($tipos) && ! in_array($tipo, $tipos, true)) {
                continue;
            }
            if (! self::tipoCompatible($tipo, $regla['afip'])) {
                continue;
            }
            if (! self::coincide($norm, $regla)) {
                continue;
            }

            return [
                'codigo' => $regla['afip'],
                'motivo' => $regla['motivo'],
                'confianza' => $regla['confianza'],
            ];
        }

        return self::fallback($tipo);
    }

    public static function debeOmitir(int $codigoInterno, string $descripcion, string $tipo): bool
    {
        if (in_array($tipo, array_merge(ConceptoTipo::TIPOS_SIN_IMPACTO_TOTALES, ['neto']), true)) {
            return true;
        }
        if (in_array($codigoInterno, self::OMITIR_CODIGOS, true)) {
            return true;
        }
        $norm = self::normalizar($descripcion);
        foreach ([
            'BRUTO SIN TOPE', 'BRUTO TOPEADO', 'BASE RESTA BRUTO', 'BASE PARA TOPE',
            'BASE NO IMPONIBLE', 'SUMA DE CONCEPTOS', 'REMUNERACION BASICA',
            'DIAS TRABAJADOS', 'HS JORNALES', 'LEY 27430',
        ] as $needle) {
            if (str_contains($norm, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function tipoCompatible(string $tipoConcepto, string $codigoAfip): bool
    {
        $tipoAfip = LsdConceptoAfipCatalogo::tipoDesdeCodigo($codigoAfip);
        if ($tipoAfip === null) {
            return false;
        }

        return match ($tipoConcepto) {
            'remunerativo' => $tipoAfip === LsdConceptoAfipCatalogo::TIPO_REMUNERATIVO,
            'no_remunerativo', 'asignacion' => $tipoAfip === LsdConceptoAfipCatalogo::TIPO_NO_REMUNERATIVO,
            'descuento', 'aporte', 'retencion' => $tipoAfip === LsdConceptoAfipCatalogo::TIPO_DESCUENTO,
            default => false,
        };
    }

    /**
     * @param  array<string, int>|null  $flagsActuales
     * @return array<string, int>
     */
    public static function flagsParaCodigo(string $codigoAfip, ?array $flagsActuales = null): array
    {
        $tipo = LsdConceptoAfipCatalogo::tipoDesdeCodigo($codigoAfip) ?? 'descuento';
        $n = (int) $codigoAfip;
        $flags = LsdSubsistemaSupport::normalizar(null, $tipo);
        if ($n >= 510000 && $n <= 529999) {
            $flags = LsdSubsistemaSupport::defaultsParaTipo('descuento');
        }
        if ($n >= 530000 && $n <= 539999) {
            $flags = LsdSubsistemaSupport::defaultsParaTipo('descuento');
            $flags['os_ap'] = 1;
        }
        if ($n >= 540000 && $n <= 549999) {
            $flags = LsdSubsistemaSupport::defaultsParaTipo('descuento');
            $flags['os_ap'] = 1;
            $flags['os_co'] = 1;
        }
        if (is_array($flagsActuales) && $flagsActuales !== []) {
            foreach ($flags as $k => $v) {
                if (array_key_exists($k, $flagsActuales)) {
                    $flags[$k] = ((int) $flagsActuales[$k]) === 1 ? 1 : 0;
                }
            }
        }

        return $flags;
    }

    /**
     * @return list<array{any?: list<string>, all?: list<string>, tipos?: list<string>, afip: string, motivo: string, confianza: string}>
     */
    private static function reglas(): array
    {
        return [
            ['all' => ['SAC PROPORCIONAL'], 'tipos' => ['remunerativo'], 'afip' => '120003', 'motivo' => 'SAC proporcional', 'confianza' => 'alta'],
            ['all' => ['SAC', 'VACACIONES NO GOZ'], 'tipos' => ['no_remunerativo'], 'afip' => '520018', 'motivo' => 'SAC s/ vacaciones no gozadas', 'confianza' => 'alta'],
            ['all' => ['SAC PREAVISO'], 'tipos' => ['no_remunerativo'], 'afip' => '520017', 'motivo' => 'SAC s/ preaviso', 'confianza' => 'alta'],
            ['all' => ['SAC', 'INTEGRACION'], 'tipos' => ['no_remunerativo'], 'afip' => '520017', 'motivo' => 'SAC s/ integración despido', 'confianza' => 'alta'],
            ['all' => ['SAC', 'INDEMNIZ'], 'tipos' => ['no_remunerativo'], 'afip' => '520017', 'motivo' => 'SAC s/ indemnización', 'confianza' => 'alta'],
            ['any' => ['SAC 1ER', 'SAC 1ER SEMESTRE'], 'tipos' => ['remunerativo'], 'afip' => '120001', 'motivo' => 'SAC 1er semestre', 'confianza' => 'alta'],
            ['any' => ['SAC 2DO', 'SAC 2DO SEMESTRE'], 'tipos' => ['remunerativo'], 'afip' => '120002', 'motivo' => 'SAC 2do semestre', 'confianza' => 'alta'],
            ['any' => ['SAC', 'AGUINALDO'], 'tipos' => ['remunerativo'], 'afip' => '120000', 'motivo' => 'Sueldo anual complementario', 'confianza' => 'alta'],
            ['any' => ['SAC'], 'tipos' => ['no_remunerativo'], 'afip' => '550000', 'motivo' => 'SAC no remunerativo (especial)', 'confianza' => 'media'],

            ['all' => ['HORAS EXTRAS', '50'], 'afip' => '130001', 'motivo' => 'Horas extras 50%', 'confianza' => 'alta'],
            ['all' => ['HORAS EXTRAS', '100'], 'afip' => '130002', 'motivo' => 'Horas extras 100%', 'confianza' => 'alta'],
            ['all' => ['HORAS EXTRAS', '200'], 'afip' => '130003', 'motivo' => 'Horas extras 200%', 'confianza' => 'alta'],
            ['any' => ['HORAS EXTRAS', 'HS EXTRAS'], 'afip' => '130000', 'motivo' => 'Horas extras', 'confianza' => 'alta'],

            ['any' => ['PLUS VACACIONAL'], 'tipos' => ['remunerativo'], 'afip' => '151000', 'motivo' => 'Plus vacacional', 'confianza' => 'alta'],
            ['any' => ['ADELANTO DE VACACIONES', 'ADELANTO VACACIONAL'], 'tipos' => ['remunerativo'], 'afip' => '150000', 'motivo' => 'Adelanto vacacional', 'confianza' => 'alta'],
            ['any' => ['VACACIONES NO GOZADAS'], 'tipos' => ['no_remunerativo'], 'afip' => '520012', 'motivo' => 'Vacaciones no gozadas', 'confianza' => 'alta'],
            ['any' => ['VACACIONES'], 'tipos' => ['no_remunerativo'], 'afip' => '520012', 'motivo' => 'Vacaciones no remunerativas', 'confianza' => 'media'],
            ['any' => ['VACACIONES'], 'tipos' => ['remunerativo'], 'afip' => '150000', 'motivo' => 'Vacaciones / adelanto vacacional', 'confianza' => 'alta'],

            ['any' => ['PREAVISO'], 'tipos' => ['no_remunerativo'], 'afip' => '520015', 'motivo' => 'Indemnización sustitutiva de preaviso', 'confianza' => 'alta'],
            ['any' => ['PREAVISO'], 'tipos' => ['remunerativo'], 'afip' => '110001', 'motivo' => 'Preaviso remunerativo', 'confianza' => 'alta'],
            ['any' => ['INTEGRACION MES'], 'tipos' => ['no_remunerativo'], 'afip' => '520016', 'motivo' => 'Integración mes de despido', 'confianza' => 'alta'],
            ['any' => ['INDEMNIZ', 'INDEMN.'], 'tipos' => ['no_remunerativo'], 'afip' => '520014', 'motivo' => 'Indemnización por despido', 'confianza' => 'alta'],
            ['any' => ['GRATIFICACION POR EGRESO', 'GRATIFICACION POR CESE', 'CESE LABORAL'], 'tipos' => ['no_remunerativo'], 'afip' => '520010', 'motivo' => 'Gratificación por cese', 'confianza' => 'alta'],

            ['any' => ['LICENCIA POR ART ASEGURADORA', 'LICENCIA ART ASEGURADORA'], 'afip' => '110009', 'motivo' => 'Prest. dineraria ART', 'confianza' => 'alta'],
            ['any' => ['LICENCIA ART', 'LICENCIA POR ART'], 'afip' => '110008', 'motivo' => 'Prest. dineraria Ley 24577', 'confianza' => 'alta'],
            ['any' => ['LICENCIA POR EXAMEN', 'LICENCIA ESTUDIO'], 'afip' => '110005', 'motivo' => 'Licencia por estudio', 'confianza' => 'alta'],
            ['any' => ['DONACION DE SANGRE'], 'afip' => '110006', 'motivo' => 'Donación de sangre', 'confianza' => 'alta'],
            ['any' => ['FERIADO'], 'tipos' => ['remunerativo'], 'afip' => '110007', 'motivo' => 'Feriado', 'confianza' => 'alta'],
            ['any' => ['MATERNIDAD'], 'tipos' => ['asignacion'], 'afip' => '510003', 'motivo' => 'Asignación por maternidad', 'confianza' => 'alta'],
            ['any' => ['MATERNIDAD'], 'tipos' => ['remunerativo'], 'afip' => '110000', 'motivo' => 'Licencia maternidad (días sueldo)', 'confianza' => 'media'],
            ['any' => ['LICENCIA', 'DIAS EGRESO', 'DIAS INGRESO', 'DIAS NO TRABAJADOS', 'DIAS JUSTIFICADOS', 'INASISTENCIA', 'SUSPENSION', 'HS. NO TRABAJADAS', 'HORAS NO TRABAJADAS'], 'tipos' => ['remunerativo'], 'afip' => '110000', 'motivo' => 'Días / licencia (sueldo)', 'confianza' => 'media'],

            ['any' => ['ZONA DESFAVORABLE', 'ADICIONAL ZONAL', 'ADIC ZONAL', 'ADIC. ZONAL'], 'afip' => '140000', 'motivo' => 'Zona desfavorable', 'confianza' => 'alta'],
            ['any' => ['ANTIGUEDAD'], 'tipos' => ['remunerativo'], 'afip' => '160001', 'motivo' => 'Adicional por antigüedad', 'confianza' => 'alta'],
            ['any' => ['PRESENTISMO'], 'tipos' => ['remunerativo'], 'afip' => '170001', 'motivo' => 'Premio por presentismo', 'confianza' => 'alta'],
            ['any' => ['PREMIO POR PRODUCCION'], 'afip' => '170002', 'motivo' => 'Premio por producción', 'confianza' => 'alta'],
            ['any' => ['COMISION'], 'afip' => '170003', 'motivo' => 'Comisiones', 'confianza' => 'alta'],
            ['any' => ['HORAS NOC', 'HS NOC', 'HS. NOC', 'ADIC. HORAS NOC'], 'tipos' => ['remunerativo'], 'afip' => '160003', 'motivo' => 'Adicional por tarea (nocturnidad)', 'confianza' => 'alta'],
            ['any' => ['VIATICO'], 'afip' => '170005', 'motivo' => 'Viáticos sin comprobante', 'confianza' => 'media'],

            ['any' => ['GUARDERIA'], 'tipos' => ['no_remunerativo'], 'afip' => '520004', 'motivo' => 'Guardería', 'confianza' => 'alta'],
            ['any' => ['GUARDERIA'], 'tipos' => ['remunerativo'], 'afip' => '160000', 'motivo' => 'Adicional guardería remunerativo', 'confianza' => 'media'],
            ['any' => ['TICKET', 'CANASTA'], 'tipos' => ['no_remunerativo'], 'afip' => '520000', 'motivo' => 'Beneficios sociales', 'confianza' => 'media'],
            ['any' => ['MOVILIDAD'], 'tipos' => ['no_remunerativo'], 'afip' => '520000', 'motivo' => 'Beneficios sociales (movilidad)', 'confianza' => 'media'],

            ['any' => ['SUELDO BASICO', 'SUELDO BÁSICO', 'BASICO JORN'], 'tipos' => ['remunerativo'], 'afip' => '110000', 'motivo' => 'Sueldo', 'confianza' => 'alta'],
            ['any' => ['PLURIEMPLEO'], 'tipos' => ['remunerativo'], 'afip' => '110000', 'motivo' => 'Sueldo (pluriempleo)', 'confianza' => 'alta'],
            ['any' => ['REDONDEO'], 'tipos' => ['remunerativo'], 'afip' => '499999', 'motivo' => 'Redondeo remunerativo', 'confianza' => 'alta'],
            ['any' => ['REDONDEO', 'AJUSTE POR REDONDEO'], 'tipos' => ['no_remunerativo'], 'afip' => '799999', 'motivo' => 'Redondeo no remunerativo', 'confianza' => 'alta'],

            ['any' => ['AYUDA ESCOLAR', 'AY. ESCOLAR'], 'tipos' => ['asignacion'], 'afip' => '510001', 'motivo' => 'Ayuda escolar', 'confianza' => 'alta'],
            ['any' => ['HIJO DISCAP', 'HIJO CON DISCAP'], 'tipos' => ['asignacion'], 'afip' => '510002', 'motivo' => 'Asignación hijo con discapacidad', 'confianza' => 'alta'],
            ['any' => ['PRENATAL'], 'tipos' => ['asignacion'], 'afip' => '510007', 'motivo' => 'Asignación prenatal', 'confianza' => 'alta'],
            ['any' => ['ASIGNACION POR HIJO', 'ASIGNACION HIJO', 'DIFERENCIA HIJO'], 'tipos' => ['asignacion'], 'afip' => '510002', 'motivo' => 'Asignación por hijo', 'confianza' => 'alta'],
            ['any' => ['ASIGNACION', 'AJUSTE ASIGNACION'], 'tipos' => ['asignacion'], 'afip' => '510000', 'motivo' => 'Asignaciones familiares', 'confianza' => 'media'],

            ['any' => ['JUBILACION'], 'afip' => '810000', 'motivo' => 'Sistema previsional', 'confianza' => 'alta'],
            ['any' => ['LEY 19.032', 'LEY 19032', 'INSSJYP', 'PAMI'], 'afip' => '810001', 'motivo' => 'INSSJyP', 'confianza' => 'alta'],
            ['any' => ['FAMILIARES A CARGO', 'ADHERENTE'], 'afip' => '810009', 'motivo' => 'Obra social adherentes', 'confianza' => 'alta'],
            ['any' => ['OBRA SOCIAL', 'OSUTHGRA'], 'afip' => '810002', 'motivo' => 'Obra social', 'confianza' => 'alta'],
            ['any' => ['SINDICATO', 'CUOTA SINDICAL', 'APORTE SIND', 'CONTRIBUCION SOLIDARIA'], 'afip' => '810004', 'motivo' => 'Cuota sindical', 'confianza' => 'alta'],
            ['any' => ['SEG. DE VIDA', 'SEGURO DE VIDA', 'SEGURO VIDA', 'VIDA Y SEPELIO', 'SCVO'], 'afip' => '810005', 'motivo' => 'Seguro de vida', 'confianza' => 'alta'],
            ['any' => ['IMPUESTO A LAS GANANCIAS', 'IMP.GANANCIAS', 'IMP.GANNACIAS', 'RETENCION IMP. GANANCIAS', 'GANNACIAS'], 'afip' => '810008', 'motivo' => 'Impuesto a las ganancias', 'confianza' => 'alta'],
            ['any' => ['PRESTAMO'], 'tipos' => ['retencion', 'descuento', 'aporte'], 'afip' => '810007', 'motivo' => 'Préstamos', 'confianza' => 'alta'],
            ['any' => ['EMBARGO', 'MUTUAL', 'VOUCHER', 'FINANCIACION', 'AYUDA ECONOMICA', 'ORTODONCIA', 'PROVEEDURIA'], 'afip' => '820000', 'motivo' => 'Otros descuentos', 'confianza' => 'media'],

            ['any' => ['CAPACITACION'], 'tipos' => ['no_remunerativo'], 'afip' => '520007', 'motivo' => 'Cursos de capacitación', 'confianza' => 'alta'],
            ['any' => ['GANANCIAS', 'R.G.3770'], 'tipos' => ['no_remunerativo'], 'afip' => '550000', 'motivo' => 'Devolución / ajuste IGA', 'confianza' => 'media'],
            ['any' => ['PRESTAMO', 'EMBARGO'], 'tipos' => ['no_remunerativo'], 'afip' => '550000', 'motivo' => 'Importe no remunerativo especial', 'confianza' => 'media'],
            ['any' => ['NO REM', 'ASIG. NO REM', 'ASIG.NO REM', 'INCREMENTO NO REM', 'ACUERDO', 'ACTA COPAR', 'REPRO', 'ATP', 'DTO.', 'DTO ', 'DECRETO', 'PREMIO'], 'tipos' => ['no_remunerativo'], 'afip' => '530000', 'motivo' => 'Incremento no remunerativo (OS)', 'confianza' => 'media'],
            ['any' => ['GRATIFICACION', 'GRAT NO REM', 'GRAT.EXT'], 'tipos' => ['no_remunerativo'], 'afip' => '530000', 'motivo' => 'Gratificación no remunerativa', 'confianza' => 'media'],
            ['any' => ['GRATIFICACION', 'PREMIO'], 'tipos' => ['remunerativo'], 'afip' => '170000', 'motivo' => 'Gratificaciones y/o premios', 'confianza' => 'media'],

            ['any' => ['ADELANTO DE SUELDO', 'DESCUENTO POR ADELANTO'], 'tipos' => ['no_remunerativo'], 'afip' => '550000', 'motivo' => 'Importe no remunerativo especial', 'confianza' => 'media'],
        ];
    }

    /**
     * @return array{codigo: string, motivo: string, confianza: string}|null
     */
    private static function fallback(string $tipo): ?array
    {
        return match ($tipo) {
            'remunerativo' => ['codigo' => '160000', 'motivo' => 'Adicionales (resto remunerativo)', 'confianza' => 'baja'],
            'no_remunerativo' => ['codigo' => '530000', 'motivo' => 'Incrementos no remunerativos (resto)', 'confianza' => 'baja'],
            'asignacion' => ['codigo' => '510000', 'motivo' => 'Asignaciones familiares (resto)', 'confianza' => 'baja'],
            'descuento', 'aporte', 'retencion' => ['codigo' => '820000', 'motivo' => 'Otros descuentos (resto)', 'confianza' => 'baja'],
            default => null,
        };
    }

    /**
     * @param  array{any?: list<string>, all?: list<string>}  $regla
     */
    private static function coincide(string $norm, array $regla): bool
    {
        if (! empty($regla['all'])) {
            foreach ($regla['all'] as $n) {
                if (! str_contains($norm, self::normalizar($n))) {
                    return false;
                }
            }

            return true;
        }
        foreach ($regla['any'] ?? [] as $n) {
            if (str_contains($norm, self::normalizar($n))) {
                return true;
            }
        }

        return false;
    }

    public static function normalizar(string $texto): string
    {
        $s = mb_strtoupper($texto, 'UTF-8');
        $s = strtr($s, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
            'Ä' => 'A', 'Ö' => 'O',
        ]);
        $s = preg_replace('/[^A-Z0-9. %\/-]/', ' ', $s) ?? $s;
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;

        return trim($s);
    }
}
