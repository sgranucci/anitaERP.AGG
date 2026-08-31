<?php

namespace App\Support\Sueldos\Lsd;

use App\Support\Sueldos\ConceptoTipo;
use App\Support\Sueldos\Formula\ParametroSueldosResolver;

/**
 * Bases 1–10 del registro 04.
 * Si hay mapeo Anita (lsd_bases) se suma tal cual (el tope ya viene en 1000).
 * Si no, heurística por tipo/código AFIP + topes SIPA (SAC/vacaciones).
 * La detracción (base 10 / importe a detraer) la cierra LsdDetraccionSupport:
 * no hace falta liquidar el 1002.
 */
class LsdBasesImponiblesSupport
{
    /**
     * @param  list<array{tipo: string, concepto_afip: ?string, importe: float, cantidad: float, lsd_bases?: array<string,int>|null}>  $lineas
     * @param  array{modalidad_sijp?: int, dias?: int}  $ctx
     * @return array<string, float|int>
     */
    public static function calcular(
        array $lineas,
        ParametroSueldosResolver $parametros,
        int $diasTope,
        string $condicionSijp = '01',
        array $ctx = [],
    ): array {
        $usaAnita = false;
        foreach ($lineas as $l) {
            if (LsdBases04Support::tieneMapeo($l['lsd_bases'] ?? null)) {
                $usaAnita = true;
                break;
            }
        }

        $out = $usaAnita
            ? self::desdeMapeoAnita($lineas)
            : self::desdeHeuristica($lineas, $parametros, $diasTope);

        if ($usaAnita) {
            $out = self::rellenarHuecosConHeuristica($out, $lineas, $parametros, $diasTope);
        }

        if (($out['dias_trabajados'] ?? 0) <= 0) {
            $out['dias_trabajados'] = max(0, min(30, $diasTope > 0 ? $diasTope : 30));
        }
        $out['dias_tope'] = $diasTope > 0 ? $diasTope : (int) $out['dias_trabajados'];

        $ctxDetraccion = array_merge($ctx, [
            'condicion_sijp' => $condicionSijp,
            'dias' => (int) ($ctx['dias'] ?? $out['dias_trabajados'] ?? $diasTope),
        ]);
        $out = LsdDetraccionSupport::aplicarSobreBases($out, $parametros, $ctxDetraccion);

        $jubilado = LsdDetraccionSupport::esJubilado($condicionSijp);
        if ($jubilado) {
            foreach (['base_2', 'base_3', 'base_4', 'base_5', 'base_8', 'base_10'] as $k) {
                $out[$k] = 0.0;
            }
            $out['importe_detraer'] = 0.0;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return array<string, float|int>
     */
    private static function desdeMapeoAnita(array $lineas): array
    {
        $out = self::vacias();
        foreach ($lineas as $l) {
            $map = LsdBases04Support::normalizar($l['lsd_bases'] ?? null);
            if ($map === []) {
                continue;
            }
            $imp = round((float) ($l['importe'] ?? 0), 2);
            $cant = (float) ($l['cantidad'] ?? 0);
            foreach ($map as $clave => $signo) {
                $valor = LsdBases04Support::esCantidad($clave) ? $cant : $imp;
                $out[$clave] = round(((float) $out[$clave]) + ($valor * $signo), 2);
            }
        }
        foreach (LsdBases04Support::CLAVES_CANTIDAD as $k) {
            $out[$k] = (int) round((float) $out[$k]);
        }

        return $out;
    }

    /**
     * Si Anita no liquidó 1000/3630, completa bases vacías con tope SIPA / heurística.
     *
     * @param  array<string, float|int>  $anita
     * @param  list<array<string, mixed>>  $lineas
     * @return array<string, float|int>
     */
    private static function rellenarHuecosConHeuristica(
        array $anita,
        array $lineas,
        ParametroSueldosResolver $parametros,
        int $diasTope,
    ): array {
        $heu = self::desdeHeuristica($lineas, $parametros, $diasTope);
        foreach (['rem_bruta', 'base_1', 'base_2', 'base_3', 'base_4', 'base_5', 'base_8', 'base_9', 'base_10', 'importe_detraer'] as $k) {
            if (abs((float) ($anita[$k] ?? 0)) < 0.005 && abs((float) ($heu[$k] ?? 0)) >= 0.005) {
                $anita[$k] = $heu[$k];
            }
        }

        return $anita;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return array<string, float|int>
     */
    private static function desdeHeuristica(
        array $lineas,
        ParametroSueldosResolver $parametros,
        int $diasTope,
    ): array {
        $remBruta = 0.0;
        $remunerativo = 0.0;
        $sac = 0.0;
        $vacaciones = 0.0;
        $osNr = 0.0;
        $maternidad = 0.0;

        foreach ($lineas as $l) {
            $imp = round((float) ($l['importe'] ?? 0), 2);
            $tipo = (string) ($l['tipo'] ?? '');
            $afip = LsdConceptoAfipCatalogo::normalizarCodigo($l['concepto_afip'] ?? null);
            $afipN = (int) ($afip ?? 0);

            if (in_array($tipo, ['remunerativo', 'no_remunerativo', 'asignacion'], true)
                || ($afipN >= 110000 && $afipN <= 799999)) {
                $remBruta += $imp;
            }
            if ($tipo === 'remunerativo' || ($afipN >= 110000 && $afipN <= 499999)) {
                $remunerativo += $imp;
            }
            if ($afipN >= 120000 && $afipN <= 129999) {
                $sac += $imp;
            }
            if ($afipN >= 150000 && $afipN <= 151999) {
                $vacaciones += $imp;
            }
            if (in_array($afip, ['530000', '540000'], true)) {
                $osNr += $imp;
            }
            if ($afip === '510003' || $afip === '510004' || $afipN === 110008) {
                $maternidad += $imp;
            }
        }

        $tope = (float) $parametros->valor('TOPE_SIPA');
        $minimo = (float) $parametros->valor('MINIMO_SIPA');
        $dias = max(0, min(30, $diasTope > 0 ? $diasTope : 30));
        $factor = $dias / 30.0;
        $topeAjustado = $tope > 0 ? round($tope * $factor, 2) : 0.0;

        $baseSueldo = max(0.0, $remunerativo - $sac - $vacaciones);
        if ($topeAjustado > 0 && $baseSueldo > $topeAjustado) {
            $baseSueldo = $topeAjustado;
        }
        $baseSac = $sac;
        if ($tope > 0 && $baseSac > $tope) {
            $baseSac = $tope;
        }
        $baseVac = $vacaciones;
        if ($topeAjustado > 0 && $baseVac > $topeAjustado) {
            $baseVac = $topeAjustado;
        }

        $baseSipa = round($baseSueldo + $baseSac + $baseVac, 2);
        if ($minimo > 0 && $baseSipa > 0 && $baseSipa < $minimo && $dias >= 30) {
            $baseSipa = $minimo;
        }

        $out = self::vacias();
        $out['dias_trabajados'] = $dias;
        $out['rem_bruta'] = round($remBruta, 2);
        $out['rem_maternidad'] = round($maternidad, 2);
        $out['base_1'] = $baseSipa;
        $out['base_2'] = $baseSipa;
        $out['base_3'] = $baseSipa;
        $out['base_4'] = round($baseSipa + $osNr, 2);
        $out['base_5'] = $baseSipa;
        $out['base_8'] = round($baseSipa + $osNr, 2);
        $out['base_9'] = $baseSipa;
        // base_10 / importe_detraer: los cierra LsdDetraccionSupport (no inventar 1002).
        $out['base_10'] = $baseSipa;
        $out['importe_detraer'] = 0.0;

        return $out;
    }

    /** @return array<string, float|int> */
    private static function vacias(): array
    {
        $out = [
            'dias_tope' => 0,
            'dias_trabajados' => 0,
            'horas_trabajadas' => 0,
            'adherentes' => 0,
        ];
        foreach (LsdBases04Support::CLAVES_IMPORTE as $k) {
            $out[$k] = 0.0;
        }

        return $out;
    }

    public static function excluyeDelLibro(?string $tipoConcepto): bool
    {
        return in_array((string) $tipoConcepto, ConceptoTipo::TIPOS_SIN_IMPACTO_TOTALES, true)
            || $tipoConcepto === 'contribucion';
    }
}
