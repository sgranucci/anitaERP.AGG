<?php

namespace App\Services\Contable;

use App\Models\Caja\RendicionEstacionamientoCaja;
use App\Models\Contable\Asiento;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Support\Contable\CierreRendicionEstacionamientoAsientoSupport;
use App\Support\Contable\CierreRendicionEstacionamientoConfigSupport;
use App\Support\Contable\CierreRendicionEstacionamientoConciliacionFlashSupport;
use App\Support\Contable\CierreRendicionEstacionamientoGrupoSupport;
use App\Support\Contable\CierreRendicionEstacionamientoListadoFiltros;
use App\Support\Contable\EstacionamientoDiarioPuntoventaReporteSupport;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Caja\RendicionEstacionamientoCajaListadoFiltros;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CierreRendicionEstacionamientoService
{
    public function __construct(
        private readonly AsientoRepositoryInterface $asientoRepository,
        private readonly Asiento_MovimientoRepositoryInterface $asientoMovimientoRepository,
        private readonly TipoasientoRepositoryInterface $tipoasientoRepository,
        private readonly CierreRendicionEstacionamientoConciliacionFlashSupport $conciliacionFlashSupport,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function listar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        $q = RendicionEstacionamientoCaja::query()
            ->with([
                'empresa:id,nombre',
                'caja:id,nombre',
                'puntoventaCae:id,codigo,nombre',
                'puntoventaCaea:id,codigo,nombre',
                'turnoOperativo.turno:id,nombre',
                'turnoOperativo.jornada:id,fecha_jornada',
                'jornada:id,fecha_jornada',
                'asiento:id,numeroasiento,fecha',
                'cierreContableUsuario:id,nombre',
                'creousuario:id,nombre',
                'movimientos.cuentacaja:id,codigo,nombre',
            ])
            ->orderByDesc('fecharendicion')
            ->orderByDesc('id');

        CierreRendicionEstacionamientoListadoFiltros::aplicarScopeTurno($q);
        RendicionEstacionamientoCajaListadoFiltros::aplicarScopeEmpresasAsignadas($q, $filtros);
        // Siempre aplicar estado, rango de fecha jornada y búsqueda; aplicar() es no-op si no hay criterios.
        CierreRendicionEstacionamientoListadoFiltros::aplicar($q, $filtros);

        return $paginar ? $q->paginate(10) : $q->get();
    }

    /**
     * Listado agrupado por fecha jornada + punto de venta (un asiento por grupo).
     *
     * @param  array<string, mixed>  $filtros
     */
    public function listarAgrupado(array $filtros, bool $paginar = true): LengthAwarePaginator|array
    {
        $rendiciones = $this->listar($filtros, false);
        if (! $rendiciones instanceof Collection) {
            $rendiciones = collect($rendiciones);
        }

        $grupos = CierreRendicionEstacionamientoGrupoSupport::agrupar(
            new EloquentCollection($rendiciones->all()),
        );

        if (! $paginar) {
            return $grupos;
        }

        return CierreRendicionEstacionamientoGrupoSupport::paginarGrupos(
            $grupos,
            CierreRendicionEstacionamientoGrupoSupport::GRUPOS_POR_PAGINA,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(int $rendicionId): array
    {
        $rendicion = $this->findParaCierre($rendicionId);
        CierreRendicionEstacionamientoAsientoSupport::assertRendicionCerrable($rendicion);

        $config = CierreRendicionEstacionamientoConfigSupport::paraEmpresa((int) $rendicion->empresa_id);
        CierreRendicionEstacionamientoConfigSupport::exigirCompleta($config, (int) $rendicion->empresa_id);

        $preview = CierreRendicionEstacionamientoAsientoSupport::generarPreview($rendicion, $config);
        $preview['fecha_asiento'] = CierreRendicionEstacionamientoAsientoSupport::fechaAsientoDesdeRendicion($rendicion);

        return $preview;
    }

    /**
     * Preview del asiento consolidado para un grupo fecha + punto de venta.
     *
     * @return array<string, mixed>
     */
    public function previewGrupo(int $empresaId, string $fechaDia, int $puntoventaCaeId): array
    {
        $rendiciones = $this->findRendicionesPendientesGrupo($empresaId, $fechaDia, $puntoventaCaeId);
        if ($rendiciones->isEmpty()) {
            throw new InvalidArgumentException('No hay rendiciones pendientes en el grupo indicado.');
        }

        $config = CierreRendicionEstacionamientoConfigSupport::paraEmpresa($empresaId);
        CierreRendicionEstacionamientoConfigSupport::exigirCompleta($config, $empresaId);

        $preview = CierreRendicionEstacionamientoAsientoSupport::generarPreviewGrupo($rendiciones, $config);
        $preview['fecha_asiento'] = CierreRendicionEstacionamientoGrupoSupport::fechaAsientoDesdeGrupo($fechaDia);
        $preview['empresa_id'] = $empresaId;
        $preview['fecha_dia'] = Carbon::parse($fechaDia)->toDateString();
        $preview['puntoventa_cae_id'] = $puntoventaCaeId;

        return $preview;
    }

    /**
     * @return array{asiento_id: int, numeroasiento: string, rendicion_ids: list<int>}
     */
    public function ejecutarCierreGrupo(int $empresaId, string $fechaDia, int $puntoventaCaeId): array
    {
        $rendiciones = $this->findRendicionesPendientesGrupo($empresaId, $fechaDia, $puntoventaCaeId);
        if ($rendiciones->isEmpty()) {
            throw new InvalidArgumentException('No hay rendiciones pendientes en el grupo indicado.');
        }

        $config = CierreRendicionEstacionamientoConfigSupport::paraEmpresa($empresaId);
        CierreRendicionEstacionamientoConfigSupport::exigirCompleta($config, $empresaId);

        $fecha = CierreRendicionEstacionamientoGrupoSupport::fechaAsientoDesdeGrupo($fechaDia);

        PeriodoContableCierreSupport::assertOperacionPermitida(
            $empresaId,
            $fecha,
            PeriodoContableCierreSupport::ALCANCE_CONTABLE,
            Auth::id(),
        );

        $preview = CierreRendicionEstacionamientoAsientoSupport::generarPreviewGrupo($rendiciones, $config);
        if (($preview['advertencias'] ?? []) !== []) {
            foreach ($preview['advertencias'] as $adv) {
                if (str_contains((string) $adv, 'no cuadra')) {
                    throw new InvalidArgumentException((string) $adv);
                }
            }
        }

        $ids = $rendiciones->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $pv = $rendiciones->first()?->puntoventaCae?->codigo ?? '';
        $observacion = CierreRendicionEstacionamientoAsientoSupport::DESCRIPCION_ASIENTO
            .' — '.Carbon::parse($fechaDia)->format('d/m/Y')
            .($pv !== '' ? ' — PV '.$pv : '')
            .' — rend. '.implode(', ', $ids);

        $payload = CierreRendicionEstacionamientoAsientoSupport::armarPayloadAsiento(
            $preview['lineas'],
            $empresaId,
            $config,
            $fecha,
            $observacion,
        );

        $tipoAsientoId = $this->resolverTipoAsientoId();

        return DB::transaction(function () use ($rendiciones, $payload, $tipoAsientoId, $ids) {
            $rendicionIds = $rendiciones->pluck('id')->map(fn ($id) => (int) $id)->all();
            $bloqueadas = RendicionEstacionamientoCaja::query()
                ->whereIn('id', $rendicionIds)
                ->lockForUpdate()
                ->get();

            foreach ($bloqueadas as $rendicion) {
                if ($rendicion->tieneCierreContable()) {
                    throw new InvalidArgumentException(
                        'La rendición #'.$rendicion->id.' ya fue cerrada contablemente.',
                    );
                }
            }

            $payload['tipoasiento_id'] = $tipoAsientoId;
            $asiento = $this->asientoRepository->create($payload);
            if ($asiento === 'Error' || $asiento === null) {
                throw new RuntimeException('Error al grabar asiento en Anita (bridge ctamov).');
            }

            $this->asientoMovimientoRepository->create($payload, $asiento->id);

            $ahora = now();
            $usuarioId = Auth::id();
            foreach ($bloqueadas as $rendicion) {
                $rendicion->update([
                    'asiento_id' => (int) $asiento->id,
                    'cierre_contable_en' => $ahora,
                    'cierre_contable_usuario_id' => $usuarioId,
                ]);
            }

            return [
                'asiento_id' => (int) $asiento->id,
                'numeroasiento' => (string) ($asiento->numeroasiento ?? ''),
                'rendicion_ids' => $ids,
            ];
        });
    }

    public function anularCierreGrupo(int $empresaId, string $fechaDia, int $puntoventaCaeId): void
    {
        $rendiciones = $this->findRendicionesGrupo($empresaId, $fechaDia, $puntoventaCaeId);
        if ($rendiciones->isEmpty()) {
            throw new InvalidArgumentException('No se encontraron rendiciones del grupo.');
        }

        $cerradas = $rendiciones->filter(fn (RendicionEstacionamientoCaja $r) => $r->tieneCierreContable());
        if ($cerradas->isEmpty()) {
            throw new InvalidArgumentException('El grupo no tiene cierre contable para anular.');
        }

        if ($cerradas->contains(fn (RendicionEstacionamientoCaja $r) => $r->esCierreContableLegacy())) {
            throw new InvalidArgumentException(
                'Hay rendiciones cerradas históricas (sin asiento en anitaERP). No admite anulación desde este módulo.',
            );
        }

        if ($rendiciones->contains(fn (RendicionEstacionamientoCaja $r) => $r->puedeCerrarContablemente())) {
            throw new InvalidArgumentException(
                'El grupo tiene rendiciones pendientes. Solo se puede anular un grupo totalmente cerrado.',
            );
        }

        $asientoIds = $cerradas
            ->pluck('asiento_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (count($asientoIds) !== 1) {
            throw new InvalidArgumentException(
                'El grupo tiene más de un asiento vinculado. Anule manualmente desde contabilidad.',
            );
        }

        $asientoId = $asientoIds[0];
        $asiento = Asiento::query()->find($asientoId);
        if ($asiento === null) {
            throw new InvalidArgumentException('No se encontró el asiento vinculado #'.$asientoId.'.');
        }

        PeriodoContableCierreSupport::assertOperacionPermitida(
            $empresaId,
            (string) ($asiento->fecha ?? now()->format('Y-m-d')),
            PeriodoContableCierreSupport::ALCANCE_CONTABLE,
            Auth::id(),
        );

        DB::transaction(function () use ($rendiciones, $asientoId) {
            $rendicionIds = $rendiciones->pluck('id')->map(fn ($id) => (int) $id)->all();
            $bloqueadas = RendicionEstacionamientoCaja::query()
                ->whereIn('id', $rendicionIds)
                ->lockForUpdate()
                ->get();

            $this->asientoRepository->delete($asientoId);

            foreach ($bloqueadas as $rendicion) {
                if ((int) ($rendicion->asiento_id ?? 0) === $asientoId) {
                    $rendicion->update([
                        'asiento_id' => null,
                        'cierre_contable_en' => null,
                        'cierre_contable_usuario_id' => null,
                    ]);
                }
            }
        });
    }

    /**
     * @return array{asiento_id: int, numeroasiento: string}
     */
    public function ejecutarCierre(int $rendicionId): array
    {
        $rendicion = $this->findParaCierre($rendicionId);
        CierreRendicionEstacionamientoAsientoSupport::assertRendicionCerrable($rendicion);

        $empresaId = (int) $rendicion->empresa_id;
        $config = CierreRendicionEstacionamientoConfigSupport::paraEmpresa($empresaId);
        CierreRendicionEstacionamientoConfigSupport::exigirCompleta($config, $empresaId);

        $fecha = CierreRendicionEstacionamientoAsientoSupport::fechaAsientoDesdeRendicion($rendicion);

        PeriodoContableCierreSupport::assertOperacionPermitida(
            $empresaId,
            $fecha,
            PeriodoContableCierreSupport::ALCANCE_CONTABLE,
            Auth::id(),
        );

        $preview = CierreRendicionEstacionamientoAsientoSupport::generarPreview($rendicion, $config);
        if (($preview['advertencias'] ?? []) !== []) {
            foreach ($preview['advertencias'] as $adv) {
                if (str_contains((string) $adv, 'no cuadra')) {
                    throw new InvalidArgumentException((string) $adv);
                }
            }
        }

        $payload = CierreRendicionEstacionamientoAsientoSupport::armarPayloadAsiento(
            $preview['lineas'],
            $empresaId,
            $config,
            $fecha,
            CierreRendicionEstacionamientoAsientoSupport::DESCRIPCION_ASIENTO
            .' #'.$rendicion->id.' '.$rendicion->codigo,
        );

        $tipoAsientoId = $this->resolverTipoAsientoId();

        return DB::transaction(function () use ($rendicion, $payload, $tipoAsientoId) {
            $rendicion = RendicionEstacionamientoCaja::query()
                ->lockForUpdate()
                ->findOrFail($rendicion->id);

            if ($rendicion->tieneCierreContable()) {
                throw new InvalidArgumentException('La rendición ya fue cerrada contablemente.');
            }

            $payload['tipoasiento_id'] = $tipoAsientoId;
            $asiento = $this->asientoRepository->create($payload);
            if ($asiento === 'Error' || $asiento === null) {
                throw new RuntimeException('Error al grabar asiento en Anita (bridge ctamov).');
            }

            $this->asientoMovimientoRepository->create($payload, $asiento->id);

            $rendicion->update([
                'asiento_id' => (int) $asiento->id,
                'cierre_contable_en' => now(),
                'cierre_contable_usuario_id' => Auth::id(),
            ]);

            return [
                'asiento_id' => (int) $asiento->id,
                'numeroasiento' => (string) ($asiento->numeroasiento ?? ''),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function conciliarFlash(int $empresaId, string $fechaDesde, string $fechaHasta): array
    {
        return $this->conciliacionFlashSupport->conciliar($empresaId, $fechaDesde, $fechaHasta);
    }

    /**
     * Rango por defecto en conciliación: última jornada con cierre contable → hoy.
     *
     * @return array{desde: string, hasta: string}
     */
    public function resolverRangoConciliacionDefault(int $empresaId): array
    {
        $hasta = Carbon::today()->toDateString();
        $desde = $this->resolverUltimaJornadaCierreContable($empresaId) ?? $hasta;

        return ['desde' => $desde, 'hasta' => $hasta];
    }

    /**
     * Diario por PV / medios (facturación estacionamiento ERP).
     *
     * @return array<string, mixed>
     */
    public function reporteDiarioPuntoventa(int $empresaId, string $fechaDesde, string $fechaHasta): array
    {
        return app(EstacionamientoDiarioPuntoventaReporteSupport::class)
            ->generar($empresaId, $fechaDesde, $fechaHasta);
    }

    private function resolverUltimaJornadaCierreContable(int $empresaId): ?string
    {
        if ($empresaId <= 0) {
            return null;
        }

        $q = RendicionEstacionamientoCaja::query()
            ->where('rendicion_estacionamiento_caja.empresa_id', $empresaId)
            ->leftJoin(
                'turno_operativo_estacionamiento as toe',
                'toe.id',
                '=',
                'rendicion_estacionamiento_caja.turno_operativo_estacionamiento_id',
            )
            ->leftJoin('jornada_estacionamiento as j', 'j.id', '=', 'toe.jornada_estacionamiento_id')
            ->where(function ($w) {
                $w->where(function ($q) {
                    $q->whereNotNull('rendicion_estacionamiento_caja.asiento_id')
                        ->where('rendicion_estacionamiento_caja.asiento_id', '>', 0);
                })->orWhere('rendicion_estacionamiento_caja.cierre_contable_legacy', true);
            });

        CierreRendicionEstacionamientoListadoFiltros::aplicarScopeTurno($q);

        $ultima = $q->max(DB::raw('COALESCE(DATE(j.fecha_jornada), DATE(rendicion_estacionamiento_caja.fecharendicion))'));

        if ($ultima === null || trim((string) $ultima) === '') {
            return null;
        }

        return Carbon::parse((string) $ultima)->toDateString();
    }

    /**
     * @return array{
     *   empresa_id: int,
     *   fecha_desde: string,
     *   fecha_hasta: string,
     *   cantidad: int,
     *   cantidad_grupos: int,
     *   total_cobrado: float,
     *   por_dia: list<array{fecha_jornada: string, cantidad: int, cantidad_grupos: int, total_cobrado: float}>,
     *   grupos: list<array{
     *     clave: string,
     *     fecha_dia: string,
     *     fecha_dia_fmt: string,
     *     puntoventa_label: string,
     *     cantidad_rendiciones: int,
     *     total_cobrado: float,
     *     rendiciones: list<array{id: int, codigo: string, total_cobrado: float, fecharendicion_fmt: string|null}>
     *   }>
     * }
     */
    public function previewCierreRango(int $empresaId, string $fechaDesde, string $fechaHasta): array
    {
        $desde = Carbon::parse($fechaDesde)->toDateString();
        $hasta = Carbon::parse($fechaHasta)->toDateString();
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        CierreRendicionEstacionamientoConfigSupport::exigirCompleta(
            CierreRendicionEstacionamientoConfigSupport::paraEmpresa($empresaId),
            $empresaId,
        );

        $rendiciones = $this->listarPendientesEnRango($empresaId, $desde, $hasta);
        $gruposRaw = CierreRendicionEstacionamientoGrupoSupport::agrupar(
            new EloquentCollection($rendiciones->all()),
        );
        $porDia = [];
        $total = 0.0;

        foreach ($rendiciones as $r) {
            $fecha = CierreRendicionEstacionamientoGrupoSupport::fechaDiaDesdeRendicion($r);
            if (! isset($porDia[$fecha])) {
                $porDia[$fecha] = [
                    'fecha_jornada' => $fecha,
                    'cantidad' => 0,
                    'cantidad_grupos' => 0,
                    'total_cobrado' => 0.0,
                ];
            }
            $monto = round((float) ($r->totalcobrado ?? 0), 2);
            $porDia[$fecha]['cantidad']++;
            $porDia[$fecha]['total_cobrado'] = round($porDia[$fecha]['total_cobrado'] + $monto, 2);
            $total = round($total + $monto, 2);
        }

        foreach ($gruposRaw as $grupo) {
            $fecha = (string) ($grupo['fecha_dia'] ?? '');
            if ($fecha !== '' && isset($porDia[$fecha])) {
                $porDia[$fecha]['cantidad_grupos']++;
            }
        }

        ksort($porDia);

        return [
            'empresa_id' => $empresaId,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'cantidad' => $rendiciones->count(),
            'cantidad_grupos' => count($gruposRaw),
            'total_cobrado' => $total,
            'por_dia' => array_values($porDia),
            'grupos' => $this->serializarGruposPreviewRango($gruposRaw),
        ];
    }

    /**
     * @return array{
     *   ok: list<array{grupo_clave: string, asiento_id: int, numeroasiento: string, rendicion_ids: list<int>}>,
     *   errores: list<array{grupo_clave: string, mensaje: string}>
     * }
     */
    public function ejecutarCierreRango(int $empresaId, string $fechaDesde, string $fechaHasta): array
    {
        $desde = Carbon::parse($fechaDesde)->toDateString();
        $hasta = Carbon::parse($fechaHasta)->toDateString();
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $rendiciones = $this->listarPendientesEnRango($empresaId, $desde, $hasta);
        if ($rendiciones->isEmpty()) {
            throw new InvalidArgumentException('No hay rendiciones pendientes de cierre en el rango indicado.');
        }

        $grupos = CierreRendicionEstacionamientoGrupoSupport::agrupar(
            new EloquentCollection($rendiciones->all()),
        );

        $ok = [];
        $errores = [];

        foreach ($grupos as $grupo) {
            $clave = (string) ($grupo['clave'] ?? '');
            try {
                $resultado = $this->ejecutarCierreGrupo(
                    (int) ($grupo['empresa_id'] ?? 0),
                    (string) ($grupo['fecha_dia'] ?? ''),
                    (int) ($grupo['puntoventa_cae_id'] ?? 0),
                );
                $ok[] = [
                    'grupo_clave' => $clave,
                    'asiento_id' => $resultado['asiento_id'],
                    'numeroasiento' => $resultado['numeroasiento'],
                    'rendicion_ids' => $resultado['rendicion_ids'],
                ];
            } catch (\Throwable $e) {
                $errores[] = [
                    'grupo_clave' => $clave,
                    'mensaje' => $e->getMessage(),
                ];
            }
        }

        if ($ok === [] && $errores !== []) {
            throw new InvalidArgumentException($errores[0]['mensaje']);
        }

        return ['ok' => $ok, 'errores' => $errores];
    }

    /**
     * Cierre contable de todos los grupos pendientes de una jornada (fecha + PV).
     *
     * @return array{
     *   ok: list<array{grupo_clave: string, asiento_id: int, numeroasiento: string, rendicion_ids: list<int>}>,
     *   errores: list<array{grupo_clave: string, mensaje: string}>
     * }
     */
    public function ejecutarCierreJornada(int $empresaId, string $fechaJornada): array
    {
        $fecha = Carbon::parse($fechaJornada)->toDateString();

        return $this->ejecutarCierreRango($empresaId, $fecha, $fecha);
    }

    /**
     * @return Collection<int, RendicionEstacionamientoCaja>
     */
    private function listarPendientesEnRango(int $empresaId, string $desde, string $hasta): Collection
    {
        $q = RendicionEstacionamientoCaja::query()
            ->with([
                'turnoOperativo.jornada:id,fecha_jornada',
                'puntoventaCae:id,codigo,nombre',
            ])
            ->where('empresa_id', $empresaId);

        CierreRendicionEstacionamientoListadoFiltros::aplicarScopeTurno($q);
        CierreRendicionEstacionamientoListadoFiltros::aplicarEstadoCierre($q, [
            'estado_cierre' => CierreRendicionEstacionamientoListadoFiltros::ESTADO_PENDIENTE,
        ]);
        $this->aplicarFiltroFechaJornadaRango($q, $desde, $hasta);

        return $q->orderBy('fecharendicion')->orderBy('id')->get();
    }

    /**
     * @param  Builder<RendicionEstacionamientoCaja>  $query
     */
    private function aplicarFiltroFechaJornadaRango(Builder $query, string $desde, string $hasta): void
    {
        $query->where(function ($w) use ($desde, $hasta) {
            $w->whereHas('turnoOperativo.jornada', function ($j) use ($desde, $hasta) {
                $j->whereDate('fecha_jornada', '>=', $desde)
                    ->whereDate('fecha_jornada', '<=', $hasta);
            })->orWhere(function ($q) use ($desde, $hasta) {
                $q->whereDoesntHave('turnoOperativo.jornada')
                    ->whereDate('fecharendicion', '>=', $desde)
                    ->whereDate('fecharendicion', '<=', $hasta);
            });
        });
    }

    public function anularCierre(int $rendicionId): void
    {
        $rendicion = RendicionEstacionamientoCaja::query()
            ->with('asiento')
            ->findOrFail($rendicionId);

        if (! $rendicion->tieneCierreContable()) {
            throw new InvalidArgumentException('La rendición no tiene cierre contable para anular.');
        }

        if ($rendicion->esCierreContableLegacy()) {
            throw new InvalidArgumentException(
                'Esta rendición fue marcada como cerrada histórica (sin asiento en anitaERP). No admite anulación desde este módulo.',
            );
        }

        $asientoId = (int) ($rendicion->asiento_id ?? 0);
        $asiento = Asiento::query()->find($asientoId);
        if ($asiento === null) {
            throw new InvalidArgumentException('No se encontró el asiento vinculado #'.$asientoId.'.');
        }

        PeriodoContableCierreSupport::assertOperacionPermitida(
            (int) $rendicion->empresa_id,
            (string) ($asiento->fecha ?? now()->format('Y-m-d')),
            PeriodoContableCierreSupport::ALCANCE_CONTABLE,
            Auth::id(),
        );

        DB::transaction(function () use ($rendicion, $asientoId) {
            $rendicion = RendicionEstacionamientoCaja::query()
                ->lockForUpdate()
                ->findOrFail($rendicion->id);

            $this->asientoRepository->delete($asientoId);

            $rendicion->update([
                'asiento_id' => null,
                'cierre_contable_en' => null,
                'cierre_contable_usuario_id' => null,
            ]);
        });
    }

    public function findParaCierre(int $id): RendicionEstacionamientoCaja
    {
        return RendicionEstacionamientoCaja::query()
            ->with([
                'empresa',
                'puntoventaCae',
                'puntoventaCaea',
                'turnoOperativo.jornada',
                'turnoOperativo.turno',
                'movimientos.cuentacaja',
            ])
            ->findOrFail($id);
    }

    private function resolverTipoAsientoId(): int
    {
        $abrev = (string) config('gastronomia.cierre_jornada_tipoasiento_abreviatura', 'VTA');
        $tipo = $this->tipoasientoRepository->findPorAbreviatura($abrev);
        if ($tipo === null) {
            throw new RuntimeException('Tipo de asiento «'.$abrev.'» no configurado.');
        }

        return (int) $tipo->id;
    }

    /**
     * @return EloquentCollection<int, RendicionEstacionamientoCaja>
     */
    private function findRendicionesGrupo(int $empresaId, string $fechaDia, int $puntoventaCaeId): EloquentCollection
    {
        $q = RendicionEstacionamientoCaja::query()
            ->with([
                'empresa',
                'puntoventaCae',
                'puntoventaCaea',
                'turnoOperativo.jornada',
                'turnoOperativo.turno',
                'movimientos.cuentacaja',
                'asiento',
            ]);

        CierreRendicionEstacionamientoListadoFiltros::aplicarScopeTurno($q);
        CierreRendicionEstacionamientoGrupoSupport::aplicarFiltroGrupo($q, $empresaId, $fechaDia, $puntoventaCaeId);

        return $q->orderBy('fecharendicion')->orderBy('id')->get();
    }

    /**
     * @param  list<array<string, mixed>>  $grupos
     * @return list<array<string, mixed>>
     */
    private function serializarGruposPreviewRango(array $grupos): array
    {
        $serializados = [];

        foreach ($grupos as $grupo) {
            /** @var EloquentCollection<int, RendicionEstacionamientoCaja>|null $rendiciones */
            $rendiciones = $grupo['rendiciones'] ?? null;
            $filasRendicion = [];

            if ($rendiciones !== null) {
                foreach ($rendiciones as $r) {
                    $filasRendicion[] = [
                        'id' => (int) $r->id,
                        'codigo' => (string) ($r->codigo ?? ''),
                        'total_cobrado' => round((float) ($r->totalcobrado ?? 0), 2),
                        'fecharendicion_fmt' => $r->fecharendicion?->format('d/m/Y H:i'),
                    ];
                }
            }

            $serializados[] = [
                'clave' => (string) ($grupo['clave'] ?? ''),
                'fecha_dia' => (string) ($grupo['fecha_dia'] ?? ''),
                'fecha_dia_fmt' => (string) ($grupo['fecha_dia_fmt'] ?? ''),
                'puntoventa_label' => (string) ($grupo['puntoventa_label'] ?? ''),
                'cantidad_rendiciones' => (int) ($grupo['cantidad_rendiciones'] ?? 0),
                'total_cobrado' => round((float) ($grupo['total_cobrado'] ?? 0), 2),
                'rendiciones' => $filasRendicion,
            ];
        }

        return $serializados;
    }

    /**
     * @return EloquentCollection<int, RendicionEstacionamientoCaja>
     */
    private function findRendicionesPendientesGrupo(
        int $empresaId,
        string $fechaDia,
        int $puntoventaCaeId,
    ): EloquentCollection {
        return $this->findRendicionesGrupo($empresaId, $fechaDia, $puntoventaCaeId)
            ->filter(fn (RendicionEstacionamientoCaja $r) => $r->puedeCerrarContablemente())
            ->values();
    }
}
