<?php

namespace App\Support\Contable\ReporteDefinible;

use App\Models\Contable\ReporteContable;
use App\Models\Contable\ReporteContableCcosto;
use App\Models\Contable\ReporteContableCuenta;
use App\Models\Contable\ReporteContableRubro;
use App\Models\Contable\ReporteContableVersion;
use Illuminate\Support\Facades\DB;

/**
 * Publicar / restaurar snapshots de estructura de informes definibles.
 */
class ReporteDefinibleVersionSupport
{
    public function publicar(ReporteContable $reporte, ?int $usuarioId, ?string $nombre = null): ReporteContableVersion
    {
        $reporte->load(['rubros.cuentas.ccostos']);
        $version = (int) $reporte->version_actual + 1;
        $snapshot = $this->armarSnapshot($reporte);

        $row = ReporteContableVersion::query()->create([
            'reporte_contable_id' => (int) $reporte->id,
            'version' => $version,
            'nombre' => $nombre ?: ('v'.$version),
            'snapshot' => $snapshot,
            'usuario_id' => $usuarioId,
        ]);

        $reporte->forceFill([
            'version_actual' => $version,
            'estado_publicacion' => 'publicado',
            'publicado_at' => now(),
            'publicado_por' => $usuarioId,
        ])->save();

        return $row;
    }

    /**
     * Diff entre dos snapshots (armarSnapshot / version.snapshot).
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     * @return array{
     *   rubros: array{added: list<string>, removed: list<string>, changed: list<array{codigo: string, campos: list<string>}>},
     *   cuentas: array{added: list<array{codigo_linea: string, codigo_cuenta: int}>, removed: list<array{codigo_linea: string, codigo_cuenta: int}>, changed: list<array{codigo_linea: string, codigo_cuenta: int, campos: list<string>}>}
     * }
     */
    public function diff(array $a, array $b): array
    {
        $rubrosA = $this->indexRubrosSnapshot($a['rubros'] ?? []);
        $rubrosB = $this->indexRubrosSnapshot($b['rubros'] ?? []);

        $codigosA = array_keys($rubrosA);
        $codigosB = array_keys($rubrosB);

        $addedRubros = array_values(array_diff($codigosB, $codigosA));
        $removedRubros = array_values(array_diff($codigosA, $codigosB));
        $changedRubros = [];
        foreach (array_intersect($codigosA, $codigosB) as $codigo) {
            $campos = $this->diffCampos(
                $this->camposComparablesRubro($rubrosA[$codigo]),
                $this->camposComparablesRubro($rubrosB[$codigo])
            );
            if ($campos !== []) {
                $changedRubros[] = ['codigo' => $codigo, 'campos' => $campos];
            }
        }

        $cuentasA = $this->indexCuentasSnapshot($rubrosA);
        $cuentasB = $this->indexCuentasSnapshot($rubrosB);
        $keysA = array_keys($cuentasA);
        $keysB = array_keys($cuentasB);

        $addedCuentas = [];
        foreach (array_diff($keysB, $keysA) as $key) {
            $addedCuentas[] = $this->parseCuentaKey($key);
        }
        $removedCuentas = [];
        foreach (array_diff($keysA, $keysB) as $key) {
            $removedCuentas[] = $this->parseCuentaKey($key);
        }
        $changedCuentas = [];
        foreach (array_intersect($keysA, $keysB) as $key) {
            $campos = $this->diffCampos(
                $this->camposComparablesCuenta($cuentasA[$key]),
                $this->camposComparablesCuenta($cuentasB[$key])
            );
            if ($campos !== []) {
                $parsed = $this->parseCuentaKey($key);
                $parsed['campos'] = $campos;
                $changedCuentas[] = $parsed;
            }
        }

        return [
            'rubros' => [
                'added' => $addedRubros,
                'removed' => $removedRubros,
                'changed' => $changedRubros,
            ],
            'cuentas' => [
                'added' => $addedCuentas,
                'removed' => $removedCuentas,
                'changed' => $changedCuentas,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rubros
     * @return array<string, array<string, mixed>>
     */
    private function indexRubrosSnapshot(array $rubros): array
    {
        $out = [];
        foreach ($rubros as $r) {
            $codigo = trim((string) ($r['codigo_linea'] ?? ''));
            if ($codigo === '') {
                $codigo = 'id:'.(int) ($r['id'] ?? 0);
            }
            $out[$codigo] = $r;
        }

        return $out;
    }

    /**
     * @param  array<string, array<string, mixed>>  $rubrosIndex
     * @return array<string, array<string, mixed>>
     */
    private function indexCuentasSnapshot(array $rubrosIndex): array
    {
        $out = [];
        foreach ($rubrosIndex as $codigoLinea => $r) {
            foreach ($r['cuentas'] ?? [] as $c) {
                $codigoCuenta = (int) ($c['codigo_cuenta'] ?? 0);
                $key = $codigoLinea.'|'.$codigoCuenta;
                $out[$key] = $c;
            }
        }

        return $out;
    }

    /**
     * @return array{codigo_linea: string, codigo_cuenta: int}
     */
    private function parseCuentaKey(string $key): array
    {
        $parts = explode('|', $key, 2);

        return [
            'codigo_linea' => (string) ($parts[0] ?? ''),
            'codigo_cuenta' => (int) ($parts[1] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>
     */
    private function camposComparablesRubro(array $r): array
    {
        return [
            'nombre' => (string) ($r['nombre'] ?? ''),
            'nivel' => (int) ($r['nivel'] ?? 0),
            'orden' => (int) ($r['orden'] ?? 0),
            'tipo' => (string) ($r['tipo'] ?? ''),
            'formula' => (string) ($r['formula'] ?? ''),
            'estilo_negrita' => (bool) ($r['estilo_negrita'] ?? false),
            'estilo_subrayado' => (bool) ($r['estilo_subrayado'] ?? false),
            'mostrar_total' => (bool) ($r['mostrar_total'] ?? true),
            'conjunto_id' => $r['conjunto_id'] ?? null,
            'lado_presentacion' => $r['lado_presentacion'] ?? null,
            'ocultar_si_cero' => (bool) ($r['ocultar_si_cero'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    private function camposComparablesCuenta(array $c): array
    {
        return [
            'codigo_hasta' => $c['codigo_hasta'] ?? null,
            'origen' => (string) ($c['origen'] ?? ''),
            'signo' => (int) ($c['signo'] ?? 1),
            'carga_ccosto' => (string) ($c['carga_ccosto'] ?? ''),
            'sucursal' => $c['sucursal'] ?? null,
            'empresa_id' => $c['empresa_id'] ?? null,
            'orden' => (int) ($c['orden'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $x
     * @param  array<string, mixed>  $y
     * @return list<string>
     */
    private function diffCampos(array $x, array $y): array
    {
        $changed = [];
        foreach ($x as $k => $vx) {
            $vy = $y[$k] ?? null;
            if ($vx != $vy) {
                $changed[] = $k;
            }
        }

        return $changed;
    }

    /**
     * @return array<string, mixed>
     */
    public function armarSnapshot(ReporteContable $reporte): array
    {
        $rubros = [];
        foreach ($reporte->rubros->sortBy(['orden', 'id']) as $r) {
            $cuentas = [];
            foreach ($r->cuentas as $c) {
                $ccs = [];
                foreach ($c->ccostos as $cc) {
                    $ccs[] = [
                        'ccosto_desde' => (int) $cc->ccosto_desde,
                        'ccosto_hasta' => (int) $cc->ccosto_hasta,
                        'centrocosto_id' => $cc->centrocosto_id,
                    ];
                }
                $cuentas[] = [
                    'empresa_id' => $c->empresa_id,
                    'cuentacontable_id' => $c->cuentacontable_id,
                    'codigo_cuenta' => (int) $c->codigo_cuenta,
                    'codigo_hasta' => $c->codigo_hasta !== null ? (int) $c->codigo_hasta : null,
                    'origen' => (string) $c->origen,
                    'signo' => (int) $c->signo,
                    'carga_ccosto' => (string) $c->carga_ccosto,
                    'sucursal' => $c->sucursal,
                    'orden' => (int) $c->orden,
                    'ccostos' => $ccs,
                ];
            }
            $rubros[] = [
                'id' => (int) $r->id,
                'parent_id' => $r->parent_id !== null ? (int) $r->parent_id : null,
                'codigo_linea' => $r->codigo_linea,
                'nombre' => (string) $r->nombre,
                'nivel' => (int) $r->nivel,
                'orden' => (int) $r->orden,
                'tipo' => (string) $r->tipo,
                'formula' => $r->formula,
                'estilo_negrita' => (bool) $r->estilo_negrita,
                'estilo_subrayado' => (bool) $r->estilo_subrayado,
                'mostrar_total' => (bool) $r->mostrar_total,
                'conjunto_id' => $r->conjunto_id,
                'lado_presentacion' => $r->lado_presentacion,
                'ocultar_si_cero' => (bool) ($r->ocultar_si_cero ?? false),
                'anita_rubro' => $r->anita_rubro,
                'cuentas' => $cuentas,
            ];
        }

        return [
            'cabecera' => [
                'codigo' => (int) $reporte->codigo,
                'nombre' => (string) $reporte->nombre,
                'titulo1' => $reporte->titulo1,
                'titulo2' => $reporte->titulo2,
                'tipo' => (string) $reporte->tipo,
                'layout_default_id' => $reporte->layout_default_id,
            ],
            'rubros' => $rubros,
        ];
    }

    public function restaurar(ReporteContable $reporte, ReporteContableVersion $version): void
    {
        if ((int) $version->reporte_contable_id !== (int) $reporte->id) {
            throw new \InvalidArgumentException('La versión no pertenece al informe.');
        }
        $snap = $version->snapshot ?? [];
        $rubrosSnap = $snap['rubros'] ?? [];

        DB::transaction(function () use ($reporte, $rubrosSnap, $snap) {
            ReporteContableRubro::query()
                ->where('reporte_contable_id', (int) $reporte->id)
                ->update(['parent_id' => null]);

            $rubrosActuales = ReporteContableRubro::query()
                ->where('reporte_contable_id', (int) $reporte->id)
                ->with('cuentas.ccostos')
                ->get();
            foreach ($rubrosActuales as $r) {
                foreach ($r->cuentas as $c) {
                    $c->ccostos()->delete();
                }
                $r->cuentas()->delete();
            }
            ReporteContableRubro::query()
                ->where('reporte_contable_id', (int) $reporte->id)
                ->delete();

            $idMap = [];
            foreach ($rubrosSnap as $rs) {
                $oldId = (int) ($rs['id'] ?? 0);
                $nuevo = ReporteContableRubro::query()->create([
                    'reporte_contable_id' => (int) $reporte->id,
                    'parent_id' => null,
                    'codigo_linea' => $rs['codigo_linea'] ?? null,
                    'nombre' => (string) ($rs['nombre'] ?? 'Rubro'),
                    'nivel' => (int) ($rs['nivel'] ?? 1),
                    'orden' => (int) ($rs['orden'] ?? 0),
                    'tipo' => (string) ($rs['tipo'] ?? 'cuentas'),
                    'formula' => $rs['formula'] ?? null,
                    'estilo_negrita' => (bool) ($rs['estilo_negrita'] ?? false),
                    'estilo_subrayado' => (bool) ($rs['estilo_subrayado'] ?? false),
                    'mostrar_total' => (bool) ($rs['mostrar_total'] ?? true),
                    'conjunto_id' => $rs['conjunto_id'] ?? null,
                    'lado_presentacion' => $rs['lado_presentacion'] ?? null,
                    'ocultar_si_cero' => (bool) ($rs['ocultar_si_cero'] ?? false),
                    'anita_rubro' => $rs['anita_rubro'] ?? null,
                ]);
                $idMap[$oldId] = (int) $nuevo->id;
                foreach ($rs['cuentas'] ?? [] as $cs) {
                    $cta = ReporteContableCuenta::query()->create([
                        'reporte_contable_rubro_id' => (int) $nuevo->id,
                        'empresa_id' => $cs['empresa_id'] ?? null,
                        'cuentacontable_id' => $cs['cuentacontable_id'] ?? null,
                        'codigo_cuenta' => (int) ($cs['codigo_cuenta'] ?? 0),
                        'codigo_hasta' => $cs['codigo_hasta'] ?? null,
                        'origen' => (string) ($cs['origen'] ?? 'R'),
                        'signo' => (int) ($cs['signo'] ?? 1),
                        'carga_ccosto' => (string) ($cs['carga_ccosto'] ?? 'S'),
                        'sucursal' => $cs['sucursal'] ?? null,
                        'orden' => (int) ($cs['orden'] ?? 0),
                    ]);
                    foreach ($cs['ccostos'] ?? [] as $cc) {
                        ReporteContableCcosto::query()->create([
                            'reporte_contable_cuenta_id' => (int) $cta->id,
                            'ccosto_desde' => (int) ($cc['ccosto_desde'] ?? 0),
                            'ccosto_hasta' => (int) ($cc['ccosto_hasta'] ?? 0),
                            'centrocosto_id' => $cc['centrocosto_id'] ?? null,
                        ]);
                    }
                }
            }
            foreach ($rubrosSnap as $rs) {
                $oldId = (int) ($rs['id'] ?? 0);
                $oldParent = $rs['parent_id'] ?? null;
                if ($oldParent === null || ! isset($idMap[$oldId])) {
                    continue;
                }
                $newParent = isset($idMap[(int) $oldParent]) ? $idMap[(int) $oldParent] : null;
                ReporteContableRubro::query()->where('id', $idMap[$oldId])->update([
                    'parent_id' => $newParent,
                ]);
            }

            $cab = $snap['cabecera'] ?? [];
            if ($cab !== []) {
                $reporte->fill([
                    'titulo1' => $cab['titulo1'] ?? $reporte->titulo1,
                    'titulo2' => $cab['titulo2'] ?? $reporte->titulo2,
                    'layout_default_id' => $cab['layout_default_id'] ?? $reporte->layout_default_id,
                ])->save();
            }
        });
    }
}
