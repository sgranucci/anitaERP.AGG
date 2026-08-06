<?php

namespace App\Support\Sueldos;

/**
 * Elegibilidad de concepto por momento vs tipo de corrida.
 *
 * Espejo de cheq_momento() + filtro de grupos en p-liquidacion.c (Anita).
 *
 * Hab_momento Anita → ERP ({@see ConceptoAnitaMapeo::MOMENTOS}):
 *   1 SIEMPRE → mensual
 *   2 PQUINCENA → quincena_1
 *   3 SQUINCENA → quincena_2
 *   4 MENSUAL → mensual_2q
 *   5 (no_liquida) → nunca
 *   6 VACACIONES → vacaciones
 *   7 VAC_P_QUINCENA → vacaciones_1q
 *   8 VAC_S_QUINCENA → vacaciones_2q
 */
class ConceptoMomentoCorrida
{
    /**
     * ¿El concepto con este momento se liquida en la corrida? (cheq_momento).
     */
    public static function aplica(string $momento, string $tipoCorrida): bool
    {
        $momento = ConceptoTipo::normalizarMomento($momento);
        $tipo = self::normalizarTipoCorrida($tipoCorrida);

        if ($momento === 'no_liquida') {
            return false;
        }

        // SIEMPRE: todas las corridas (mensual, vacaciones, SAC, final, …).
        if ($momento === 'mensual') {
            return true;
        }

        // Momentos exclusivos de vacaciones: solo en corrida vacaciones.
        if ($momento === 'vacaciones') {
            return $tipo === 'vacaciones';
        }
        if ($momento === 'vacaciones_1q') {
            // ERP unifica vacaciones 1q/2q/plenas en tipo "vacaciones".
            return $tipo === 'vacaciones';
        }
        if ($momento === 'vacaciones_2q') {
            return $tipo === 'vacaciones';
        }

        // Momento "sac" (extensión ERP; Anita no tiene hab_momento SAC).
        if ($momento === 'sac') {
            return $tipo === 'sac';
        }

        if ($momento === 'quincena_1') {
            return $tipo === 'quincena_1';
        }

        // SQUINCENA: 2da quincena o mensual_squin (mensual/final en Biyemas, TLQ=3).
        if ($momento === 'quincena_2') {
            return in_array($tipo, ['quincena_2', 'mensual', 'final'], true);
        }

        // MENSUAL (hab 4): semanal / 2da quincena / mensual / mensual_squin.
        if ($momento === 'mensual_2q') {
            return in_array($tipo, ['quincena_2', 'mensual', 'final', 'semanal'], true);
        }

        // Especial / final / etc. en Anita: si no matcheó arriba → no.
        // "especial" ERP: permitir solo SIEMPRE (ya retornó true arriba).
        if ($momento === 'especial' || $momento === 'final') {
            return $tipo === $momento || $tipo === 'final';
        }

        return false;
    }

    /**
     * Momentos que el armado por grupos aporta en esta corrida.
     *
     * En Anita, corrida VACACIONES lee del grupo solo hab_momento==VACACIONES
     * (p-liquidacion ~2432). El resto (SIEMPRE vía novedad) entra por otro camino.
     *
     * null = sin recorte extra (aplica solo {@see aplica()}).
     *
     * @return list<string>|null
     */
    public static function momentosPermitidosEnGrupo(string $tipoCorrida): ?array
    {
        $tipo = self::normalizarTipoCorrida($tipoCorrida);

        if ($tipo === 'vacaciones') {
            return ['vacaciones', 'vacaciones_1q', 'vacaciones_2q'];
        }

        if ($tipo === 'sac') {
            // Anita: en aguinaldo el grupo solo toma ciertos hab_forma; en ERP
            // acotamos a momento sac + SIEMPRE lo aporta cheq vía novedades/set.
            return ['sac', 'mensual'];
        }

        return null;
    }

    /**
     * @param  iterable<int, object|array<string, mixed>>  $conceptos  con clave/atributo momento
     * @return list<object|array<string, mixed>>
     */
    public static function filtrarConceptos(iterable $conceptos, string $tipoCorrida): array
    {
        $out = [];
        foreach ($conceptos as $c) {
            $momento = is_array($c)
                ? (string) ($c['momento'] ?? ConceptoTipo::MOMENTO_DEFAULT)
                : (string) ($c->momento ?? ConceptoTipo::MOMENTO_DEFAULT);
            if (self::aplica($momento, $tipoCorrida)) {
                $out[] = $c;
            }
        }

        return $out;
    }

    public static function normalizarTipoCorrida(string $tipoCorrida): string
    {
        $tipo = trim($tipoCorrida);

        return isset(\App\Models\Sueldos\Liquidacion_Sueldos::TIPOS[$tipo]) ? $tipo : 'mensual';
    }
}
