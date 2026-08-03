<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Concepto_Elegibilidad_Sueldos;
use App\Models\Sueldos\Concepto_Sueldos;
use App\Models\Sueldos\Empleado_Concepto_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Grupo_Concepto_Item_Sueldos;
use App\Models\Sueldos\Grupo_Concepto_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Support\Sueldos\ConceptoElegibilidadCatalogo;
use App\Support\Sueldos\NovedadSueldosCatalogo;
use App\Support\Sueldos\NovedadSueldosVigencia;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resuelve el set efectivo de conceptos por empleado:
 * grupos|sap → +novedades → elegibilidad (AND de grupos OR) → explícitos +/-.
 */
class ConceptoSetEfectivoService
{
    /**
     * @return array{
     *   conceptos: Collection<int, Concepto_Sueldos>,
     *   meta: array<int, array{origen: string, detalle: string, origen_label?: string, origen_badge?: string}>,
     *   modo: string,
     *   modo_label: string,
     *   grupos: list<array{id: int, codigo: int, descripcion: ?string, orden: int, origen: string, pivot_id: int}>,
     *   excluidos: list<array{concepto_id: int, codigo: int, descripcion: string, motivo: string, etapa: string}>
     * }
     */
    public function resolver(Empleado_Sueldos $empleado, ?Liquidacion_Sueldos $liquidacion = null): array
    {
        $fechaRef = $this->fechaReferencia($liquidacion);
        $contextoEmp = $this->contextoEmpleado($empleado);

        $grupos = $this->gruposDelEmpleado($empleado);
        $meta = [];
        $excluidos = [];
        $candidatosIds = [];
        /** @var array<int, string> origen previo al filtro de elegibilidad (para narrativa) */
        $origenPreEleg = [];

        if ($grupos !== []) {
            $modo = ConceptoElegibilidadCatalogo::MODO_GRUPOS;
            foreach ($grupos as $g) {
                $items = Grupo_Concepto_Item_Sueldos::query()
                    ->where('grupo_concepto_id', $g['id'])
                    ->where('activo', true)
                    ->pluck('concepto_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
                foreach ($items as $cid) {
                    $candidatosIds[$cid] = true;
                    if (! isset($meta[$cid])) {
                        $meta[$cid] = [
                            'origen' => ConceptoElegibilidadCatalogo::ORIGEN_GRUPO,
                            'detalle' => 'Grupo '.$g['codigo'].($g['descripcion'] ? ' · '.$g['descripcion'] : '').' (#'.$g['orden'].')',
                        ];
                    }
                }
            }
        } else {
            // Sin grupo: catálogo activo + elegibilidad (SAP). No es “Anita todos”.
            $modo = ConceptoElegibilidadCatalogo::MODO_SAP;
            $candidatosIds = Concepto_Sueldos::query()
                ->where('activo', true)
                ->where('momento', '!=', 'no_liquida')
                ->pluck('id')
                ->mapWithKeys(fn ($id) => [(int) $id => true])
                ->all();
            foreach (array_keys($candidatosIds) as $cid) {
                $meta[$cid] = [
                    'origen' => ConceptoElegibilidadCatalogo::ORIGEN_SAP,
                    'detalle' => 'Sin grupo: catálogo activo; filtrado por elegibilidad del concepto',
                ];
            }
        }

        foreach ($meta as $cid => $m) {
            $origenPreEleg[(int) $cid] = (string) ($m['origen'] ?? '');
        }

        // Novedad vigente suma al set (grupos y SAP) antes de elegibilidad.
        $this->incorporarConceptosPorNovedad($empleado, $liquidacion, $candidatosIds, $meta, $origenPreEleg);

        $this->aplicarElegibilidad(
            $candidatosIds,
            $meta,
            $excluidos,
            $origenPreEleg,
            $fechaRef,
            $contextoEmp
        );

        // Explícitos vigentes
        $explicatos = Empleado_Concepto_Sueldos::query()
            ->where('empleado_id', $empleado->id)
            ->with('concepto:id,codigo,descripcion,activo,momento')
            ->get();

        foreach ($explicatos as $ex) {
            if (! $this->vigenteEnFecha($ex->fecha_desde, $ex->fecha_hasta, $fechaRef)) {
                continue;
            }
            $cid = (int) $ex->concepto_id;
            if ($ex->accion === ConceptoElegibilidadCatalogo::ACCION_EXCLUIR) {
                if (isset($candidatosIds[$cid])) {
                    unset($candidatosIds[$cid], $meta[$cid]);
                    $excluidos[] = [
                        'concepto_id' => $cid,
                        'codigo' => (int) optional($ex->concepto)->codigo,
                        'descripcion' => (string) optional($ex->concepto)->descripcion,
                        'motivo' => 'Exclusión explícita del legajo'
                            .($ex->fecha_desde || $ex->fecha_hasta
                                ? ' (vigencia '
                                    .($ex->fecha_desde ? Carbon::parse($ex->fecha_desde)->format('d/m/Y') : '…')
                                    .'–'
                                    .($ex->fecha_hasta ? Carbon::parse($ex->fecha_hasta)->format('d/m/Y') : '∞')
                                    .')'
                                : ''),
                        'etapa' => 'explicito',
                    ];
                }
            } elseif ($ex->accion === ConceptoElegibilidadCatalogo::ACCION_INCLUIR) {
                $candidatosIds[$cid] = true;
                $meta[$cid] = [
                    'origen' => ConceptoElegibilidadCatalogo::ORIGEN_EXPLICITO_MAS,
                    'detalle' => 'Asignación explícita en legajo',
                ];
            }
        }

        $conceptos = Concepto_Sueldos::query()
            ->whereIn('id', array_keys($candidatosIds) ?: [0])
            ->where('activo', true)
            ->where('momento', '!=', 'no_liquida')
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get();

        $this->completarExcluidos($excluidos);

        foreach ($meta as $cid => $m) {
            $origen = (string) ($m['origen'] ?? '');
            $meta[$cid]['origen_label'] = ConceptoElegibilidadCatalogo::origenLabel($origen);
            $meta[$cid]['origen_badge'] = ConceptoElegibilidadCatalogo::origenBadge($origen);
        }

        return [
            'conceptos' => $conceptos,
            'meta' => $meta,
            'modo' => $modo,
            'modo_label' => ConceptoElegibilidadCatalogo::modoLabel($modo),
            'grupos' => $grupos,
            'excluidos' => $excluidos,
        ];
    }

    /**
     * @param  array<int, true>  $candidatosIds
     * @param  array<int, array{origen: string, detalle: string}>  $meta
     * @param  list<array<string, mixed>>  $excluidos
     * @param  array<int, string>  $origenPreEleg
     * @param  array<string, mixed>  $contextoEmp
     */
    private function aplicarElegibilidad(
        array &$candidatosIds,
        array &$meta,
        array &$excluidos,
        array $origenPreEleg,
        Carbon $fechaRef,
        array $contextoEmp
    ): void {
        $ids = array_keys($candidatosIds);
        if ($ids === []) {
            return;
        }

        $q = Concepto_Elegibilidad_Sueldos::query()
            ->where('activo', true)
            ->whereIn('concepto_id', $ids);

        if (Schema::hasColumn('concepto_elegibilidad_sueldos', 'vigente_desde')) {
            $q->where(function ($w) use ($fechaRef) {
                $w->whereNull('vigente_desde')->orWhereDate('vigente_desde', '<=', $fechaRef->toDateString());
            })->where(function ($w) use ($fechaRef) {
                $w->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $fechaRef->toDateString());
            });
        }

        if (Schema::hasColumn('concepto_elegibilidad_sueldos', 'grupo_or')) {
            $q->orderBy('grupo_or');
        }
        $reglasPorConcepto = $q->orderBy('id')->get()->groupBy('concepto_id');

        foreach ($ids as $cid) {
            $reglas = $reglasPorConcepto->get($cid);
            if (! $reglas || $reglas->isEmpty()) {
                continue;
            }

            $fallo = $this->evaluarGruposOr($reglas, $contextoEmp);
            if ($fallo !== null) {
                $origenPrev = $origenPreEleg[$cid] ?? (string) ($meta[$cid]['origen'] ?? '');
                $prefijo = match ($origenPrev) {
                    ConceptoElegibilidadCatalogo::ORIGEN_GRUPO => 'Venía por grupo, pero ',
                    ConceptoElegibilidadCatalogo::ORIGEN_NOVEDAD => 'Venía por novedad, pero ',
                    ConceptoElegibilidadCatalogo::ORIGEN_SAP,
                    ConceptoElegibilidadCatalogo::ORIGEN_LEGACY => 'En catálogo sin grupo, pero ',
                    default => '',
                };
                unset($candidatosIds[$cid], $meta[$cid]);
                $excluidos[] = [
                    'concepto_id' => $cid,
                    'codigo' => 0,
                    'descripcion' => '',
                    'motivo' => $prefijo.'no cumple elegibilidad: '.$fallo,
                    'etapa' => 'elegibilidad',
                ];

                continue;
            }

            if (isset($meta[$cid])) {
                $origenPrev = (string) ($meta[$cid]['origen'] ?? '');
                if ($origenPrev !== ConceptoElegibilidadCatalogo::ORIGEN_NOVEDAD
                    && $origenPrev !== ConceptoElegibilidadCatalogo::ORIGEN_GRUPO
                    && $origenPrev !== ConceptoElegibilidadCatalogo::ORIGEN_SAP) {
                    $meta[$cid]['origen'] = ConceptoElegibilidadCatalogo::ORIGEN_REGLA;
                }
                $meta[$cid]['detalle'] = trim(($meta[$cid]['detalle'] ?? '').' · elegibilidad OK', ' ·');
            }
        }
    }

    /**
     * AND entre grupos OR: dentro del mismo grupo_or basta 1 regla; entre grupos todas.
     *
     * @param  Collection<int, Concepto_Elegibilidad_Sueldos>  $reglas
     * @param  array<string, mixed>  $ctx
     */
    private function evaluarGruposOr(Collection $reglas, array $ctx): ?string
    {
        // Sin columna grupo_or: cada regla es su propio grupo (AND estricto, comportamiento histórico).
        $tieneGrupoOr = Schema::hasColumn('concepto_elegibilidad_sueldos', 'grupo_or');
        $porGrupo = $reglas->groupBy(fn ($r) => $tieneGrupoOr ? (int) ($r->grupo_or ?? 1) : (int) $r->id);

        foreach ($porGrupo as $grupoOr => $regs) {
            $ok = false;
            $fallos = [];
            foreach ($regs as $regla) {
                if ($this->cumpleRegla($regla, $ctx)) {
                    $ok = true;
                    break;
                }
                $fallos[] = $regla->campoLabel().' '.$regla->operadorLabel()
                    .(in_array($regla->operador, ['vacio', 'no_vacio'], true) ? '' : ' '.$regla->valor)
                    .' (legajo: '.$this->valorContextoLegible($regla->campo, $ctx).')';
            }
            if (! $ok) {
                $pref = $porGrupo->count() > 1 || $regs->count() > 1
                    ? 'grupo OR #'.$grupoOr.' — ninguna regla: '
                    : '';

                return $pref.implode(' · ', $fallos);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function valorContextoLegible(string $campo, array $ctx): string
    {
        $v = $ctx[$campo] ?? null;
        if ($v === null || $v === '' || (int) $v === 0) {
            return 'vacío';
        }

        return (string) $v;
    }

    /**
     * @param  array<int, true>  $candidatosIds
     * @param  array<int, array{origen: string, detalle: string}>  $meta
     * @param  array<int, string>  $origenPreEleg
     */
    private function incorporarConceptosPorNovedad(
        Empleado_Sueldos $empleado,
        ?Liquidacion_Sueldos $liquidacion,
        array &$candidatosIds,
        array &$meta,
        array &$origenPreEleg
    ): void {
        $liqId = (int) ($liquidacion->id ?? 0);
        $periodoYm = (int) ($liquidacion->periodo ?? 0);
        if ($periodoYm <= 0) {
            $periodoYm = (int) Carbon::now()->format('Ym');
        }

        try {
            $rows = DB::table('novedad_sueldos')
                ->where('empleado_id', $empleado->id)
                ->where('estado', '!=', NovedadSueldosCatalogo::ESTADO_ANULADA)
                ->get([
                    'liquidacion_id', 'concepto_id', 'concepto_codigo',
                    'periodo', 'fecha_desde', 'fecha_hasta', 'origen',
                ]);
        } catch (\Throwable $e) {
            return;
        }

        $codigos = [];
        $idsDirectos = [];
        foreach ($rows as $r) {
            if (! NovedadSueldosVigencia::aplicaACorrida($r, $liqId, $periodoYm)) {
                continue;
            }
            $cid = (int) ($r->concepto_id ?? 0);
            $cod = (int) ($r->concepto_codigo ?? 0);
            if ($cid > 0) {
                $idsDirectos[$cid] = true;
            } elseif ($cod > 0) {
                $codigos[$cod] = true;
            }
        }

        if ($codigos !== []) {
            Concepto_Sueldos::query()
                ->whereIn('codigo', array_keys($codigos))
                ->where('activo', true)
                ->where('momento', '!=', 'no_liquida')
                ->pluck('id')
                ->each(function ($id) use (&$idsDirectos) {
                    $idsDirectos[(int) $id] = true;
                });
        }

        foreach (array_keys($idsDirectos) as $cid) {
            if (isset($candidatosIds[$cid])) {
                $meta[$cid]['detalle'] = trim(($meta[$cid]['detalle'] ?? '').' · también novedad vigente', ' ·');

                continue;
            }
            $candidatosIds[$cid] = true;
            $meta[$cid] = [
                'origen' => ConceptoElegibilidadCatalogo::ORIGEN_NOVEDAD,
                'detalle' => 'Sumado al set por novedad vigente (luego aplica elegibilidad)',
            ];
            $origenPreEleg[$cid] = ConceptoElegibilidadCatalogo::ORIGEN_NOVEDAD;
        }
    }

    /**
     * @param  list<array{concepto_id: int, codigo: int, descripcion: string, motivo: string, etapa?: string}>  $excluidos
     */
    private function completarExcluidos(array &$excluidos): void
    {
        $faltantes = collect($excluidos)->where('codigo', 0)->pluck('concepto_id')->unique()->all();
        if ($faltantes === []) {
            return;
        }
        $map = Concepto_Sueldos::query()->whereIn('id', $faltantes)->get(['id', 'codigo', 'descripcion'])->keyBy('id');
        foreach ($excluidos as &$e) {
            if (($e['codigo'] ?? 0) === 0 && isset($map[$e['concepto_id']])) {
                $e['codigo'] = (int) $map[$e['concepto_id']]->codigo;
                $e['descripcion'] = (string) $map[$e['concepto_id']]->descripcion;
            }
        }
        unset($e);
    }

    /**
     * @return list<int>
     */
    public function idsEfectivos(Empleado_Sueldos $empleado, ?Liquidacion_Sueldos $liquidacion = null): array
    {
        $r = $this->resolver($empleado, $liquidacion);

        return $r['conceptos']->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    /**
     * @return list<array{id: int, codigo: int, descripcion: ?string, orden: int, origen: string, pivot_id: int}>
     */
    public function gruposDelEmpleado(Empleado_Sueldos $empleado): array
    {
        $empleado->loadMissing(['gruposConcepto' => fn ($q) => $q->where('grupo_concepto_sueldos.activo', true)]);

        $out = [];
        foreach ($empleado->gruposConcepto as $grupo) {
            $out[] = [
                'id' => (int) $grupo->id,
                'codigo' => (int) $grupo->codigo,
                'descripcion' => $grupo->descripcion,
                'orden' => (int) ($grupo->pivot->orden ?? 0),
                'origen' => (string) ($grupo->pivot->origen ?? 'manual'),
                'pivot_id' => (int) ($grupo->pivot->id ?? 0),
            ];
        }

        if ($out === []) {
            $orden = 0;
            foreach ([1, 2, 3] as $slot) {
                $codigo = (int) ($empleado->{'grupo_concepto_'.$slot.'_codigo'} ?? 0);
                if ($codigo <= 0) {
                    continue;
                }
                $grupo = Grupo_Concepto_Sueldos::query()
                    ->where('codigo', $codigo)
                    ->where('activo', true)
                    ->where(function ($q) use ($empleado) {
                        $q->whereNull('empresa_id')->orWhere('empresa_id', $empleado->empresa_id);
                    })
                    ->orderByRaw('CASE WHEN empresa_id IS NULL THEN 1 ELSE 0 END')
                    ->first();
                if ($grupo) {
                    $orden++;
                    $out[] = [
                        'id' => (int) $grupo->id,
                        'codigo' => (int) $grupo->codigo,
                        'descripcion' => $grupo->descripcion,
                        'orden' => $orden,
                        'origen' => 'sync_anita',
                        'pivot_id' => 0,
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function contextoEmpleado(Empleado_Sueldos $emp): array
    {
        $emp->loadMissing(['sindicato:id,codigo', 'obrasocial:id,codigo', 'categoria:id,codigo', 'agrupamiento:id,codigo']);

        return [
            'sindicato_id' => (int) ($emp->sindicato_id ?? 0),
            'obrasocial_id' => (int) ($emp->obrasocial_id ?? 0),
            'categoria_id' => (int) ($emp->categoria_id ?? 0),
            'agrupamiento_id' => (int) ($emp->agrupamiento_id ?? 0),
            'empresa_id' => (int) ($emp->empresa_id ?? 0),
            'sindicato_codigo' => (int) (optional($emp->sindicato)->codigo ?? 0),
            'obrasocial_codigo' => (int) (optional($emp->obrasocial)->codigo ?? 0),
            'categoria_codigo' => (int) (optional($emp->categoria)->codigo ?? 0),
            'agrupamiento_codigo' => (int) (optional($emp->agrupamiento)->codigo ?? 0),
        ];
    }

    private function cumpleRegla(Concepto_Elegibilidad_Sueldos $regla, array $ctx): bool
    {
        $campo = (string) $regla->campo;
        $actual = $ctx[$campo] ?? null;
        $op = (string) $regla->operador;
        $valor = trim((string) ($regla->valor ?? ''));

        if ($op === 'vacio') {
            return $actual === null || $actual === '' || (int) $actual === 0;
        }
        if ($op === 'no_vacio') {
            return ! ($actual === null || $actual === '' || (int) $actual === 0);
        }
        if ($op === 'en') {
            $lista = array_filter(array_map('trim', explode(',', $valor)), fn ($v) => $v !== '');

            return in_array((string) $actual, $lista, true) || in_array((string) (int) $actual, $lista, true);
        }
        if ($op === 'distinto') {
            return (string) $actual !== $valor && (string) (int) $actual !== $valor;
        }

        return (string) $actual === $valor || (string) (int) $actual === $valor;
    }

    private function vigenteEnFecha($desde, $hasta, Carbon $ref): bool
    {
        if ($desde) {
            $d = $desde instanceof Carbon ? $desde : Carbon::parse($desde);
            if ($d->gt($ref)) {
                return false;
            }
        }
        if ($hasta) {
            $h = $hasta instanceof Carbon ? $hasta : Carbon::parse($hasta);
            if ($h->lt($ref)) {
                return false;
            }
        }

        return true;
    }

    private function fechaReferencia(?Liquidacion_Sueldos $liq): Carbon
    {
        if ($liq && $liq->fecha_liquidacion) {
            return Carbon::parse($liq->fecha_liquidacion)->startOfDay();
        }
        if ($liq && $liq->periodo) {
            [$ini] = NovedadSueldosVigencia::limitesPeriodo((int) $liq->periodo);

            return $ini;
        }

        return Carbon::now()->startOfDay();
    }
}
