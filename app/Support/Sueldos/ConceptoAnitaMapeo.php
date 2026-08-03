<?php

namespace App\Support\Sueldos;

/**
 * Mapeo Anita (haberes) → campos del ERP (concepto_sueldos).
 *
 * Rangos desde infohab (Biyemas):
 *   phab–uhab 1–1000 remuneración
 *   pret–uret 1001–2000 retención
 *   pasi–uasi 2001–2999 asignación
 *   papo–uapo 3000–999999 p/listados (contribuciones + auxiliares)
 *
 * Dentro de papo–uapo, hab_va_recibo diferencia:
 *   != 1 → va al recibo Anexo III (contribución empleador, salvo código 3000)
 *   == 1 → solo reportes / AS / bases (no imprime ni afecta neto)
 *
 * Nota: el help de a-concepto.c dice "1=va al recibo", pero l-recibolargo.c
 * salta cuando hab_va_recibo=='1' con comentario "no va al recibo". En datos
 * reales Biyemas usa 0/1 (nunca 2); se sigue el comportamiento del listado.
 */
class ConceptoAnitaMapeo
{
    /** @var array<int, string> hab_momento → ConceptoTipo::MOMENTOS */
    public const MOMENTOS = [
        1 => 'mensual',
        2 => 'quincena_1',
        3 => 'quincena_2',
        4 => 'mensual_2q',
        5 => 'no_liquida',
        6 => 'vacaciones',
        7 => 'vacaciones_1q',
        8 => 'vacaciones_2q',
    ];

    /** Defaults alineados a infohab Biyemas (sobreescribibles vía sync). */
    public const RANGO_REM_HASTA = 1000;

    public const RANGO_RETENCION_DESDE = 1001;

    public const RANGO_RETENCION_HASTA = 2000;

    public const RANGO_ASIGNACION_DESDE = 2001;

    public const RANGO_ASIGNACION_HASTA = 2999;

    public const RANGO_PAPO = 3000;

    public const RANGO_UAPO = 999999;

    /** Excluido de es_contribucion_empleador() en l-recibolargo_anexoIII.fc */
    public const CODIGO_AJUSTE_REDONDEO = 3000;

    public static function momento(int $habMomento): string
    {
        return self::MOMENTOS[$habMomento] ?? ConceptoTipo::MOMENTO_DEFAULT;
    }

    /**
     * Tipo ERP a partir del código (infohab), hab_tipo y hab_va_recibo.
     */
    public static function tipo(int $codigo, int $habTipo, mixed $habVaRecibo = 0): string
    {
        if ($habTipo === 3) {
            return 'informativo';
        }

        if ($codigo >= self::RANGO_PAPO && $codigo <= self::RANGO_UAPO) {
            return self::tipoEnRangoPapo($codigo, $habTipo, $habVaRecibo);
        }

        if ($codigo >= self::RANGO_ASIGNACION_DESDE && $codigo <= self::RANGO_ASIGNACION_HASTA) {
            return 'asignacion';
        }

        if ($codigo >= self::RANGO_RETENCION_DESDE && $codigo <= self::RANGO_RETENCION_HASTA) {
            return 'retencion';
        }

        // Debajo de pret: tipo Anita 2 = sin descuentos → no remunerativo; 1 = remunerativo.
        return $habTipo === 2 ? 'no_remunerativo' : 'remunerativo';
    }

    /**
     * papo–uapo: contribución al recibo vs auxiliar solo-reporte.
     */
    public static function tipoEnRangoPapo(int $codigo, int $habTipo, mixed $habVaRecibo): string
    {
        // Código 3000: imprime en conceptos liquidados (norem), no en CE.
        if ($codigo === self::CODIGO_AJUSTE_REDONDEO) {
            return $habTipo === 2 ? 'no_remunerativo' : 'remunerativo';
        }

        if (! self::vaRecibo($habVaRecibo)) {
            return 'informativo';
        }

        return 'contribucion';
    }

    public static function sumaA(string $tipoErp): ?string
    {
        return match ($tipoErp) {
            'remunerativo' => 'remunerativo',
            'no_remunerativo', 'asignacion' => 'no_remunerativo',
            'descuento', 'aporte', 'retencion' => 'descuentos',
            'neto' => 'neto',
            // Contribución empleador e informativos no alimentan bruto/neto/dto.
            'contribucion', 'informativo' => null,
            default => null,
        };
    }

    /**
     * Anita listado: salta si hab_va_recibo == '1' ("no va al recibo").
     * Datos Biyemas: 0 = va, 1 = no va; vacío/otro se trata como va.
     */
    public static function vaRecibo(mixed $habVaRecibo): bool
    {
        return (int) $habVaRecibo !== 1;
    }

    public static function factor(mixed $habFactor): ?float
    {
        if ($habFactor === null || $habFactor === '') {
            return null;
        }
        $f = (float) $habFactor;

        return abs($f) < 0.0000001 ? null : $f;
    }

    public static function textoFormula(mixed $valor): ?string
    {
        $t = trim((string) ($valor ?? ''));

        return $t === '' ? null : $t;
    }

    /**
     * ¿Es contribución empleador para sección CE / torta? (espejo Anita).
     */
    public static function esContribucionEmpleador(int $codigo, mixed $habVaRecibo): bool
    {
        if ($codigo < self::RANGO_PAPO || $codigo > self::RANGO_UAPO) {
            return false;
        }
        if ($codigo === self::CODIGO_AJUSTE_REDONDEO || $codigo === 999999) {
            return false;
        }

        return self::vaRecibo($habVaRecibo);
    }
}
