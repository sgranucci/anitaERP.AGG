<?php

namespace App\Support\Solicitudpago;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Solicitudpago\Solicitudpago;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Contable\Anita\AnitaMayorAnaliticoSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Consulta del informe SP (Anita l-solpagomae.c) + conciliación mayor opcional.
 */
final class SolicitudpagoMaeReporteConsulta
{
    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function query(array $filtros, EmpresaRepositoryInterface $empresaRepository): Builder
    {
        $q = Solicitudpago::query()
            ->with([
                'empresas:id,nombre,codigo',
                'proveedores:id,codigo,nombre',
                'conceptos:id,codigo,nombre',
                'formapagosol:id,codigo,nombre',
                'monedas:id,abreviatura,nombre',
                'sectores:id,codigo,nombre',
                'madre:id,codigo,estado,monto,fecha',
                'cuotas.hijas:id,codigo,estado,monto,fecha,solicitudpago_madre_id',
                'estados',
                'cuentas.cuentacontables:id,codigo,nombre',
            ])
            ->whereDate('fecha', '>=', $filtros['fecha_desde'])
            ->whereDate('fecha', '<=', $filtros['fecha_hasta']);

        if (($filtros['empresa_scope'] ?? 'una') === 'todas') {
            $empresaRepository->aplicarFiltroEmpresasAsignadas($q, 'empresa_id');
        } elseif (! empty($filtros['empresa_id'])) {
            $q->where('empresa_id', (int) $filtros['empresa_id']);
        }

        // Alcance por CC/empresas del usuario (listar-todas-solicitud-pago omite CC).
        SolicitudpagoVisibilidadSupport::aplicarFiltroListado($q, 'empresa_id', 'centrocosto_id');

        $estado = $filtros['estado'] ?? SolicitudpagoMaeListadoFiltros::ESTADO_TODOS;
        if ($estado !== SolicitudpagoMaeListadoFiltros::ESTADO_TODOS) {
            $q->where('estado', $estado);
        }

        $sectorDesde = $filtros['sector_desde'] ?? null;
        $sectorHasta = $filtros['sector_hasta'] ?? null;
        if ($sectorDesde !== null || $sectorHasta !== null) {
            $q->whereHas('sectores', function (Builder $sq) use ($sectorDesde, $sectorHasta) {
                if ($sectorDesde !== null) {
                    $sq->where('codigo', '>=', (int) $sectorDesde);
                }
                if ($sectorHasta !== null) {
                    $sq->where('codigo', '<=', (int) $sectorHasta);
                }
            });
        }

        self::aplicarFiltroTratamiento($q, $filtros['filtro_tratamiento'] ?? SolicitudpagoMaeListadoFiltros::TRAT_TODAS);

        return $q->orderByDesc('fecha')->orderByDesc('codigo')->orderByDesc('id');
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{registros: int, monto: float, conciliadas_ok: int, conciliadas_dif: int}
     */
    public static function totales(Builder $query, array $filtros, ?Collection $filasEnriquecidas = null): array
    {
        $base = (clone $query)->toBase();
        $registros = (int) (clone $base)->count();
        $monto = (float) (clone $base)->sum('monto');

        $ok = 0;
        $dif = 0;
        if ($filasEnriquecidas !== null) {
            foreach ($filasEnriquecidas as $fila) {
                $estado = is_object($fila)
                    ? ($fila->concil_estado ?? '')
                    : ($fila['concil_estado'] ?? '');
                if ($estado === 'OK') {
                    $ok++;
                } elseif ($estado === 'DIF') {
                    $dif++;
                }
            }
        }

        return [
            'registros' => $registros,
            'monto' => $monto,
            'conciliadas_ok' => $ok,
            'conciliadas_dif' => $dif,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, object>
     */
    public static function paginar(array $filtros, EmpresaRepositoryInterface $empresaRepository, int $porPagina = 25): LengthAwarePaginator
    {
        $query = self::query($filtros, $empresaRepository);
        $paginator = $query->paginate($porPagina);
        $filas = self::enriquecerColeccion(collect($paginator->items()), $filtros);
        $paginator->setCollection($filas);

        return $paginator;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, object>
     */
    public static function listarTodas(array $filtros, EmpresaRepositoryInterface $empresaRepository): Collection
    {
        $items = self::query($filtros, $empresaRepository)->get();
        if (SolicitudpagoMaeListadoFiltros::expandirFamilia($filtros)) {
            $items = self::expandirColeccionFamilia($items, $filtros, $empresaRepository);
        }

        return self::enriquecerColeccion($items, $filtros);
    }

    /**
     * Página del informe (familia se resuelve en memoria).
     *
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, object>
     */
    public static function paginarInforme(
        array $filtros,
        EmpresaRepositoryInterface $empresaRepository,
        int $porPagina = 25,
        int $page = 1,
    ): LengthAwarePaginator {
        if (SolicitudpagoMaeListadoFiltros::expandirFamilia($filtros)) {
            $todas = self::listarTodas($filtros, $empresaRepository);
            $slice = $todas->forPage($page, $porPagina)->values();

            return new \Illuminate\Pagination\LengthAwarePaginator(
                $slice,
                $todas->count(),
                $porPagina,
                $page,
                ['path' => request()->url(), 'query' => request()->query()]
            );
        }

        $query = self::query($filtros, $empresaRepository);
        $paginator = $query->paginate($porPagina, ['*'], 'page', $page);
        $filas = self::enriquecerColeccion(collect($paginator->items()), $filtros);
        $paginator->setCollection($filas);

        return $paginator;
    }

    /**
     * Completa madres (aunque estén fuera del período) y oculta hijas ya cubiertas por la madre.
     *
     * @param  Collection<int, Solicitudpago>  $items
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, Solicitudpago>
     */
    private static function expandirColeccionFamilia(
        Collection $items,
        array $filtros,
        EmpresaRepositoryInterface $empresaRepository,
    ): Collection {
        if ($items->isEmpty()) {
            return $items;
        }

        $ids = $items->pluck('id')->map(fn ($id) => (int) $id)->all();
        $madreIds = $items->pluck('solicitudpago_madre_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        foreach ($items as $sp) {
            foreach ($sp->cuotas ?? [] as $cuota) {
                $hijaId = (int) ($cuota->solicitudpago_hija_id ?? 0);
                if ($hijaId > 0) {
                    $ids[] = $hijaId;
                }
            }
            if (($sp->cuotas ?? collect())->isNotEmpty()) {
                $madreIds[] = (int) $sp->id;
            }
        }

        $idsExtra = array_values(array_unique(array_merge($ids, $madreIds)));
        $faltantes = array_values(array_diff($idsExtra, $items->pluck('id')->map(fn ($id) => (int) $id)->all()));

        if ($faltantes !== []) {
            $extra = self::queryBaseSinFecha($filtros, $empresaRepository)
                ->whereIn('solicitudpago.id', $faltantes)
                ->get();
            $items = $items->concat($extra)->unique('id')->values();
        }

        // Madres presentes (con cuotas): no listar sus hijas como renglón suelto
        $madreIdsConCuotas = $items
            ->filter(fn (Solicitudpago $sp) => ($sp->cuotas ?? collect())->isNotEmpty())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $filtrados = $items->filter(function (Solicitudpago $sp) use ($madreIdsConCuotas) {
            $madreId = (int) ($sp->solicitudpago_madre_id ?? 0);
            if ($madreId > 0 && in_array($madreId, $madreIdsConCuotas, true)) {
                return false;
            }

            return true;
        })->values();

        return $filtrados->sortByDesc(fn (Solicitudpago $sp) => sprintf(
            '%s-%010d',
            optional($sp->fecha)?->format('Y-m-d') ?? '0000-00-00',
            (int) $sp->codigo
        ))->values();
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private static function queryBaseSinFecha(array $filtros, EmpresaRepositoryInterface $empresaRepository): Builder
    {
        $q = Solicitudpago::query()
            ->with([
                'empresas:id,nombre,codigo',
                'proveedores:id,codigo,nombre',
                'conceptos:id,codigo,nombre',
                'formapagosol:id,codigo,nombre',
                'monedas:id,abreviatura,nombre',
                'sectores:id,codigo,nombre',
                'madre:id,codigo,estado,monto,fecha',
                'cuotas.hijas:id,codigo,estado,monto,fecha,solicitudpago_madre_id',
                'estados',
                'cuentas.cuentacontables:id,codigo,nombre',
            ]);

        if (($filtros['empresa_scope'] ?? 'una') === 'todas') {
            $empresaRepository->aplicarFiltroEmpresasAsignadas($q, 'empresa_id');
        } elseif (! empty($filtros['empresa_id'])) {
            $q->where('empresa_id', (int) $filtros['empresa_id']);
        }

        SolicitudpagoVisibilidadSupport::aplicarFiltroListado($q, 'empresa_id', 'centrocosto_id');

        return $q;
    }

    /**
     * @param  Collection<int, Solicitudpago>  $items
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, object>
     */
    public static function enriquecerColeccion(Collection $items, array $filtros): Collection
    {
        $muestraCuota = SolicitudpagoMaeListadoFiltros::muestraColumnasCuota($filtros);
        $concilMap = [];
        if (! empty($filtros['incluir_conciliacion_mayor'])) {
            $concilMap = self::conciliarMayorPagadasErp($items, $filtros);
        }

        return $items->map(function (Solicitudpago $sp) use ($muestraCuota, $concilMap, $filtros) {
            return self::enriquecerFila($sp, $muestraCuota, $concilMap[$sp->id] ?? null, $filtros);
        })->values();
    }

    /**
     * @param  array<string, mixed>|null  $concil
     * @param  array<string, mixed>  $filtros
     */
    private static function enriquecerFila(Solicitudpago $sp, bool $muestraCuota, ?array $concil, array $filtros): object
    {
        $cuotas = $sp->cuotas ?? collect();
        $montoUltCuota = 0.0;
        $nroUltCuotaPaga = 0;
        if ($cuotas->isNotEmpty()) {
            $montoUltCuota = (float) $cuotas->first()->monto;
            foreach ($cuotas as $cuota) {
                if ((int) ($cuota->solicitudpago_hija_id ?? 0) > 0) {
                    $nroUltCuotaPaga = (int) $cuota->nro_cuota;
                    $montoUltCuota = (float) $cuota->monto;
                }
            }
        }

        $esMadrePlan = ($cuotas->isNotEmpty());
        $esHija = (int) ($sp->solicitudpago_madre_id ?? 0) > 0;
        $referencia = $esHija
            ? (string) (optional($sp->madre)->codigo ?? $sp->solicitudpago_madre_id)
            : '';

        $leyenda = self::resolverLeyenda($sp);

        $cuotasDetalle = [];
        if ($muestraCuota && $cuotas->isNotEmpty()) {
            foreach ($cuotas->sortBy('nro_cuota') as $cuota) {
                $hija = $cuota->hijas ?? null;
                $cuotasDetalle[] = (object) [
                    'nro_cuota' => (int) $cuota->nro_cuota,
                    'fecha_vencimiento' => $cuota->fecha_vencimiento,
                    'monto' => (float) $cuota->monto,
                    'hija_id' => (int) ($cuota->solicitudpago_hija_id ?? 0) ?: null,
                    'hija_codigo' => $hija?->codigo,
                    'hija_estado' => $hija?->estado,
                    'hija_estado_label' => $hija ? SolicitudpagoEstados::label($hija->estado) : 'Pendiente',
                    'pagada' => $hija !== null,
                ];
            }
        }

        $cuotasPagadas = collect($cuotasDetalle)->where('pagada', true)->count();
        $cuotasTotal = count($cuotasDetalle);

        $fila = (object) [
            'id' => (int) $sp->id,
            'codigo' => (int) $sp->codigo,
            'fecha' => $sp->fecha,
            'fecha_vencimiento' => $sp->fecha_vencimiento,
            'tratamiento' => (string) $sp->tratamiento,
            'tratamiento_label' => SolicitudpagoTratamientos::label($sp->tratamiento),
            'sector_codigo' => optional($sp->sectores)->codigo,
            'sector_nombre' => optional($sp->sectores)->nombre ?? '',
            'concepto_nombre' => optional($sp->conceptos)->nombre ?? '',
            'forma_pago_nombre' => optional($sp->formapagosol)->nombre ?? '',
            'proveedor_codigo' => optional($sp->proveedores)->codigo,
            'proveedor_nombre' => optional($sp->proveedores)->nombre ?? '',
            'proveedor_id' => (int) ($sp->proveedor_id ?? 0),
            'moneda' => optional($sp->monedas)->abreviatura
                ?? optional($sp->monedas)->nombre
                ?? '',
            'monto' => (float) $sp->monto,
            'monto_cuota' => $muestraCuota ? $montoUltCuota : null,
            'cuota_paga' => $muestraCuota ? $nroUltCuotaPaga : null,
            'estado' => (string) $sp->estado,
            'estado_label' => SolicitudpagoEstados::label($sp->estado),
            'referencia' => $referencia,
            'observacion' => $leyenda,
            'empresa_id' => (int) $sp->empresa_id,
            'nombreempresa' => optional($sp->empresas)->nombre ?? '',
            'empresa_codigo' => optional($sp->empresas)->codigo ?? '',
            'es_madre_plan' => $esMadrePlan,
            'es_hija' => $esHija,
            'vinculo_label' => $esMadrePlan ? 'Madre' : ($esHija ? 'Hija' : ''),
            'cuotas_detalle' => $cuotasDetalle,
            'cuotas_pagadas' => $cuotasPagadas,
            'cuotas_total' => $cuotasTotal,
            'madre_id' => (int) ($sp->solicitudpago_madre_id ?? 0) ?: null,
            'madre_codigo' => optional($sp->madre)->codigo,
            'muestra_cuota' => $muestraCuota,
            'incluir_conciliacion' => ! empty($filtros['incluir_conciliacion_mayor']),
            'concil_sp_debe' => $concil['sp_debe'] ?? null,
            'concil_sp_haber' => $concil['sp_haber'] ?? null,
            'concil_mayor_debe' => $concil['mayor_debe'] ?? null,
            'concil_mayor_haber' => $concil['mayor_haber'] ?? null,
            'concil_diff' => $concil['diff'] ?? null,
            'concil_estado' => $concil['estado'] ?? (empty($filtros['incluir_conciliacion_mayor']) ? null : 'N/A'),
            'concil_detalle' => $concil['detalle'] ?? '',
            'caja_movimiento_id' => $concil['caja_movimiento_id'] ?? null,
        ];

        return $fila;
    }

    private static function resolverLeyenda(Solicitudpago $sp): string
    {
        $estados = $sp->estados ?? collect();
        $buscar = $sp->estado === SolicitudpagoEstados::SUSPENDIDA
            ? SolicitudpagoEstados::SUSPENDIDA
            : SolicitudpagoEstados::PAGADA;

        $match = $estados
            ->filter(fn ($e) => strtoupper((string) $e->estado_actual) === $buscar)
            ->sortByDesc(fn ($e) => sprintf('%s-%06d', optional($e->fecha)?->format('Y-m-d') ?? '', (int) $e->id))
            ->first();

        $leyenda = trim((string) ($match->leyenda ?? ''));
        if ($leyenda !== '') {
            return $leyenda;
        }

        return trim((string) ($sp->observacion ?? ''));
    }

    private static function aplicarFiltroTratamiento(Builder $q, string $filtro): void
    {
        switch ($filtro) {
            case SolicitudpagoMaeListadoFiltros::TRAT_CON_PLAN:
                $q->whereIn('tratamiento', [
                    SolicitudpagoTratamientos::PLAN_DE_PAGO,
                    SolicitudpagoTratamientos::RECURRENTE,
                ])->whereHas('cuotas');
                break;

            case SolicitudpagoMaeListadoFiltros::TRAT_SOLO_HIJAS:
                $q->whereNotNull('solicitudpago_madre_id');
                break;

            case SolicitudpagoMaeListadoFiltros::TRAT_FAMILIA:
                // Semilla: hijas o madres con plan en el período; luego se expande la familia
                $q->where(function (Builder $w) {
                    $w->whereNotNull('solicitudpago_madre_id')
                        ->orWhereHas('cuotas')
                        ->orWhereIn('tratamiento', [
                            SolicitudpagoTratamientos::PLAN_DE_PAGO,
                            SolicitudpagoTratamientos::RECURRENTE,
                        ]);
                });
                break;

            case SolicitudpagoMaeListadoFiltros::TRAT_TODAS:
                break;

            case SolicitudpagoMaeListadoFiltros::TRAT_SIN_PLAN:
                $q->where(function (Builder $w) {
                    $w->whereNotIn('tratamiento', [
                        SolicitudpagoTratamientos::PLAN_DE_PAGO,
                        SolicitudpagoTratamientos::RECURRENTE,
                    ])->orWhereDoesntHave('cuotas');
                })->whereNull('solicitudpago_madre_id');
                break;

            default:
                // Ante valor desconocido, no restringir (mismo criterio que TODAS)
                break;
        }
    }

    /**
     * Conciliación SP pagadas vía IE de caja (anitaERP) vs mayor Anita (subdiario+ctamov).
     *
     * @param  Collection<int, Solicitudpago>  $items
     * @param  array<string, mixed>  $filtros
     * @return array<int, array<string, mixed>> keyed by solicitudpago_id
     */
    private static function conciliarMayorPagadasErp(Collection $items, array $filtros): array
    {
        $pagadas = $items->filter(
            fn (Solicitudpago $sp) => $sp->estado === SolicitudpagoEstados::PAGADA
        );
        if ($pagadas->isEmpty()) {
            return [];
        }

        $ids = $pagadas->pluck('id')->map(fn ($id) => (int) $id)->all();
        $movimientos = Caja_Movimiento::query()
            ->whereIn('solicitudpago_id', $ids)
            ->orderByDesc('id')
            ->get(['id', 'solicitudpago_id', 'fecha', 'empresa_id']);

        $iePorSp = [];
        foreach ($movimientos as $mov) {
            $spId = (int) $mov->solicitudpago_id;
            if (! isset($iePorSp[$spId])) {
                $iePorSp[$spId] = $mov;
            }
        }

        /** @var array<string, list<array{sp_id:int, cuentas:list<int>}>> $grupos */
        $grupos = [];
        $spDebeHaber = [];

        foreach ($pagadas as $sp) {
            $spId = (int) $sp->id;
            if (! isset($iePorSp[$spId])) {
                continue;
            }
            $ie = $iePorSp[$spId];
            $fechaPago = $ie->fecha
                ? Carbon::parse($ie->fecha)->format('Y-m-d')
                : ($sp->fecha ? Carbon::parse($sp->fecha)->format('Y-m-d') : null);
            if ($fechaPago === null) {
                continue;
            }

            $codigos = [];
            $debe = 0.0;
            $haber = 0.0;
            foreach ($sp->cuentas ?? [] as $cta) {
                $codigo = (int) preg_replace('/\D/', '', (string) (optional($cta->cuentacontables)->codigo ?? ''));
                if ($codigo > 0) {
                    $codigos[] = $codigo;
                }
                $monto = (float) $cta->monto;
                if (strtoupper((string) $cta->debe_haber) === 'H') {
                    $haber += $monto;
                } else {
                    $debe += $monto;
                }
            }
            $codigos = array_values(array_unique($codigos));
            $spDebeHaber[$spId] = [
                'debe' => $debe,
                'haber' => $haber,
                'caja_movimiento_id' => (int) $ie->id,
                'codigos' => $codigos,
            ];

            if ($codigos === []) {
                continue;
            }

            $empresaId = (int) ($ie->empresa_id ?: $sp->empresa_id);
            $clave = $empresaId.'|'.$fechaPago;
            $grupos[$clave][] = ['sp_id' => $spId, 'cuentas' => $codigos];
        }

        $mayorPorSp = [];
        $mayorSupport = app(AnitaMayorAnaliticoSupport::class);

        foreach ($grupos as $clave => $miembros) {
            [$empresaIdStr, $fechaIso] = explode('|', $clave, 2);
            $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita((int) $empresaIdStr);
            $ymd = (int) str_replace('-', '', $fechaIso);
            $todasCuentas = [];
            foreach ($miembros as $m) {
                foreach ($m['cuentas'] as $c) {
                    $todasCuentas[$c] = true;
                }
            }
            $movimientosMayor = $mayorSupport->listarMovimientosPeriodo(
                $empresaAnita,
                $ymd,
                $ymd,
                array_map('intval', array_keys($todasCuentas)),
            );

            foreach ($miembros as $m) {
                $set = array_fill_keys($m['cuentas'], true);
                $md = 0.0;
                $mh = 0.0;
                foreach ($movimientosMayor as $mov) {
                    $cta = (int) ($mov['cuenta_codigo'] ?? 0);
                    if (! isset($set[$cta])) {
                        continue;
                    }
                    $md += (float) ($mov['debe'] ?? 0);
                    $mh += (float) ($mov['haber'] ?? 0);
                }
                $mayorPorSp[$m['sp_id']] = ['debe' => $md, 'haber' => $mh];
            }
        }

        $out = [];
        foreach ($pagadas as $sp) {
            $spId = (int) $sp->id;
            if (! isset($iePorSp[$spId])) {
                $out[$spId] = [
                    'estado' => 'N/A',
                    'detalle' => 'Pagada sin IE de caja en ERP',
                    'sp_debe' => null,
                    'sp_haber' => null,
                    'mayor_debe' => null,
                    'mayor_haber' => null,
                    'diff' => null,
                    'caja_movimiento_id' => null,
                ];

                continue;
            }

            $spDh = $spDebeHaber[$spId] ?? ['debe' => 0.0, 'haber' => 0.0, 'caja_movimiento_id' => (int) $iePorSp[$spId]->id, 'codigos' => []];
            if (($spDh['codigos'] ?? []) === []) {
                $out[$spId] = [
                    'estado' => 'N/A',
                    'detalle' => 'SP sin cuentas contables',
                    'sp_debe' => $spDh['debe'],
                    'sp_haber' => $spDh['haber'],
                    'mayor_debe' => null,
                    'mayor_haber' => null,
                    'diff' => null,
                    'caja_movimiento_id' => $spDh['caja_movimiento_id'],
                ];

                continue;
            }

            $mayor = $mayorPorSp[$spId] ?? ['debe' => 0.0, 'haber' => 0.0];
            $diffDebe = abs($spDh['debe'] - $mayor['debe']);
            $diffHaber = abs($spDh['haber'] - $mayor['haber']);
            $diffNeto = abs(($spDh['debe'] - $spDh['haber']) - ($mayor['debe'] - $mayor['haber']));
            $diff = max($diffDebe, $diffHaber, $diffNeto);
            $ok = $diff < 0.05;

            $out[$spId] = [
                'estado' => $ok ? 'OK' : 'DIF',
                'detalle' => $ok ? 'Asiento SP vs mayor Anita' : 'Diferencia asiento SP vs mayor Anita',
                'sp_debe' => round($spDh['debe'], 2),
                'sp_haber' => round($spDh['haber'], 2),
                'mayor_debe' => round($mayor['debe'], 2),
                'mayor_haber' => round($mayor['haber'], 2),
                'diff' => round($diff, 2),
                'caja_movimiento_id' => $spDh['caja_movimiento_id'],
            ];
        }

        return $out;
    }
}
