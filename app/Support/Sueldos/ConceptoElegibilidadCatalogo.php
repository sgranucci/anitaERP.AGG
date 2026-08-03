<?php

namespace App\Support\Sueldos;

class ConceptoElegibilidadCatalogo
{
    public const ACCION_INCLUIR = 'incluir';

    public const ACCION_EXCLUIR = 'excluir';

    /** @var array<string, string> */
    public const ACCIONES = [
        self::ACCION_INCLUIR => 'Forzar incluir',
        self::ACCION_EXCLUIR => 'Forzar excluir',
    ];

    /** @var array<string, string> */
    public const CAMPOS = [
        'sindicato_codigo' => 'Sindicato (código)',
        'obrasocial_codigo' => 'Obra social (código)',
        'categoria_codigo' => 'Categoría (código)',
        'agrupamiento_codigo' => 'Agrupamiento (código)',
        'empresa_id' => 'Empresa (ID)',
        'sindicato_id' => 'Sindicato (ID)',
        'obrasocial_id' => 'Obra social (ID)',
        'categoria_id' => 'Categoría (ID)',
    ];

    /** @var array<string, string> */
    public const OPERADORES = [
        'igual' => 'Igual a',
        'distinto' => 'Distinto de',
        'en' => 'En lista (1,2,3)',
        'vacio' => 'Vacío / sin valor',
        'no_vacio' => 'Con valor',
    ];

    public const MODO_GRUPOS = 'grupos';

    /** Sin grupo: catálogo activo + elegibilidad (+ novedades). Estilo SAP permissibility. */
    public const MODO_SAP = 'sap_elegibilidad';

    /** @deprecated usar MODO_SAP */
    public const MODO_LEGACY = 'legacy_todos';

    public const ORIGEN_GRUPO = 'grupo';

    public const ORIGEN_REGLA = 'regla';

    public const ORIGEN_EXPLICITO_MAS = 'explicito+';

    public const ORIGEN_EXPLICITO_MENOS = 'explicito-';

    /** Alias histórico del modo sin grupo. */
    public const ORIGEN_LEGACY = 'legacy_todos';

    public const ORIGEN_SAP = 'sap_elegibilidad';

    public const ORIGEN_NOVEDAD = 'novedad';

    public const ORIGEN_PLAN_CUOTA = 'plan_cuota';

    public const ORIGEN_SISTEMA = 'sistema';

    /** Etiquetas cortas para badges UI. */
    public const ORIGEN_LABELS = [
        self::ORIGEN_GRUPO => 'Grupo',
        self::ORIGEN_REGLA => 'Elegibilidad',
        self::ORIGEN_EXPLICITO_MAS => 'Explícito +',
        self::ORIGEN_EXPLICITO_MENOS => 'Explícito −',
        self::ORIGEN_LEGACY => 'Catálogo + elegib.',
        self::ORIGEN_SAP => 'Catálogo + elegib.',
        self::ORIGEN_NOVEDAD => 'Novedad',
        self::ORIGEN_PLAN_CUOTA => 'Plan de cuotas',
        self::ORIGEN_SISTEMA => 'Sistema',
    ];

    /** Clases Bootstrap badge por origen. */
    public const ORIGEN_BADGES = [
        self::ORIGEN_GRUPO => 'primary',
        self::ORIGEN_REGLA => 'info',
        self::ORIGEN_EXPLICITO_MAS => 'success',
        self::ORIGEN_EXPLICITO_MENOS => 'danger',
        self::ORIGEN_LEGACY => 'secondary',
        self::ORIGEN_SAP => 'secondary',
        self::ORIGEN_NOVEDAD => 'warning',
        self::ORIGEN_PLAN_CUOTA => 'dark',
        self::ORIGEN_SISTEMA => 'light',
    ];

    public static function modoLabel(?string $modo): string
    {
        return match ((string) $modo) {
            self::MODO_GRUPOS => 'Modo grupos',
            self::MODO_SAP, self::MODO_LEGACY => 'Sin grupo (catálogo + elegibilidad)',
            default => (string) $modo,
        };
    }

    public static function origenLabel(?string $origen): string
    {
        $origen = (string) $origen;

        return self::ORIGEN_LABELS[$origen] ?? ($origen !== '' ? $origen : '—');
    }

    public static function origenBadge(?string $origen): string
    {
        return self::ORIGEN_BADGES[(string) $origen] ?? 'secondary';
    }
}
