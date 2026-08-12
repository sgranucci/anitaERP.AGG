<?php

namespace App\Support\Contable\ReporteDefinible;

/**
 * Convierte nivel secuencial Anita (1..n sin saltos >1) en parent_id / árbol.
 */
class ReporteDefinibleJerarquiaSupport
{
    /**
     * @param  list<array{rubro: int, desc: string, nivel: int}>  $rubrosOrdenados
     * @return list<array{rubro: int, desc: string, nivel: int, parent_rubro: int|null, orden: int}>
     */
    public static function enriquecerConPadre(array $rubrosOrdenados): array
    {
        /** @var array<int, int> $stack nivel => rubro id anita */
        $stack = [];
        $out = [];
        $orden = 0;

        foreach ($rubrosOrdenados as $row) {
            $nivel = max(1, (int) ($row['nivel'] ?? 1));
            $rubro = (int) ($row['rubro'] ?? 0);
            $parent = null;
            if ($nivel > 1) {
                $parent = $stack[$nivel - 1] ?? null;
            }

            $out[] = [
                'rubro' => $rubro,
                'desc' => (string) ($row['desc'] ?? ''),
                'nivel' => $nivel,
                'parent_rubro' => $parent,
                'orden' => $orden++,
            ];

            $stack[$nivel] = $rubro;
            foreach (array_keys($stack) as $n) {
                if ($n > $nivel) {
                    unset($stack[$n]);
                }
            }
        }

        return $out;
    }

    /**
     * Aplana árbol Eloquent a filas de UI con indent.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Contable\ReporteContableRubro>  $rubros
     * @return list<array<string, mixed>>
     */
    public static function aplanarParaUi($rubros): array
    {
        $byParent = [];
        foreach ($rubros as $r) {
            $pid = $r->parent_id !== null ? (int) $r->parent_id : 0;
            $byParent[$pid][] = $r;
        }

        $out = [];
        $walk = function (int $parentId, int $depth) use (&$walk, &$out, $byParent): void {
            foreach ($byParent[$parentId] ?? [] as $r) {
                $out[] = [
                    'id' => (int) $r->id,
                    'parent_id' => $r->parent_id !== null ? (int) $r->parent_id : null,
                    'codigo_linea' => (string) ($r->codigo_linea ?? ''),
                    'nombre' => (string) $r->nombre,
                    'nivel' => (int) $r->nivel,
                    'orden' => (int) $r->orden,
                    'tipo' => (string) $r->tipo,
                    'tipo_label' => ReporteDefinibleSupport::etiquetaTipoRubro((string) $r->tipo),
                    'formula' => (string) ($r->formula ?? ''),
                    'estilo_negrita' => (bool) $r->estilo_negrita,
                    'estilo_subrayado' => (bool) $r->estilo_subrayado,
                    'mostrar_total' => (bool) $r->mostrar_total,
                    'conjunto_id' => $r->conjunto_id !== null ? (int) $r->conjunto_id : null,
                    'lado_presentacion' => $r->lado_presentacion,
                    'ocultar_si_cero' => (bool) ($r->ocultar_si_cero ?? false),
                    'depth' => $depth,
                    'cuentas_count' => $r->relationLoaded('cuentas') ? $r->cuentas->count() : (int) ($r->cuentas_count ?? 0),
                    'hijos_count' => isset($byParent[(int) $r->id]) ? count($byParent[(int) $r->id]) : 0,
                ];
                $walk((int) $r->id, $depth + 1);
            }
        };
        $walk(0, 0);

        return $out;
    }
}
