<?php

namespace App\Services\Contable;

use App\Models\Caja\RendicionMaquinavendingCaja;
use App\Models\Contable\Asiento;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Support\Contable\CierreRendicionMaquinavendingAsientoSupport;
use App\Support\Contable\CierreRendicionMaquinavendingConfigSupport;
use App\Support\Contable\CierreRendicionMaquinavendingGrupoSupport;
use App\Support\Contable\CierreRendicionMaquinavendingListadoFiltros;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Caja\RendicionMaquinavendingCajaListadoFiltros;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CierreRendicionMaquinavendingService
{
    public function __construct(
        private readonly AsientoRepositoryInterface $asientoRepository,
        private readonly Asiento_MovimientoRepositoryInterface $asientoMovimientoRepository,
        private readonly TipoasientoRepositoryInterface $tipoasientoRepository,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function listar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        $q = RendicionMaquinavendingCaja::query()
            ->with([
                'empresa:id,nombre',
                'caja:id,nombre',
                'puntoventaCae:id,codigo,nombre',
                'puntoventaCaea:id,codigo,nombre',
                'maquinavending:id,nombre',
                'maquinavendingRendicion:id,fecha_jornada,numero_cierre',
                'asiento:id,numeroasiento,fecha',
                'cierreContableUsuario:id,nombre',
                'creousuario:id,nombre',
            ])
            ->orderByDesc('fecharendicion')
            ->orderByDesc('id');

        RendicionMaquinavendingCajaListadoFiltros::aplicarScopeEmpresasAsignadas($q, $filtros);
        CierreRendicionMaquinavendingListadoFiltros::aplicar($q, $filtros);

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

        $grupos = CierreRendicionMaquinavendingGrupoSupport::agrupar(
            new EloquentCollection($rendiciones->all()),
        );

        if (! $paginar) {
            return $grupos;
        }

        return CierreRendicionMaquinavendingGrupoSupport::paginarGrupos(
            $grupos,
            CierreRendicionMaquinavendingGrupoSupport::GRUPOS_POR_PAGINA,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(int $rendicionId): array
    {
        $rendicion = $this->findParaCierre($rendicionId);
        CierreRendicionMaquinavendingAsientoSupport::assertRendicionCerrable($rendicion);

        $config = CierreRendicionMaquinavendingConfigSupport::paraEmpresa((int) $rendicion->empresa_id);
        CierreRendicionMaquinavendingConfigSupport::exigirCompleta($config, (int) $rendicion->empresa_id);

        $preview = CierreRendicionMaquinavendingAsientoSupport::generarPreviewGrupo(
            new EloquentCollection([$rendicion]),
            $config,
        );
        $preview['fecha_asiento'] = CierreRendicionMaquinavendingGrupoSupport::fechaAsientoDesdeGrupo(
            CierreRendicionMaquinavendingGrupoSupport::fechaDiaDesdeRendicion($rendicion),
        );

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

        $config = CierreRendicionMaquinavendingConfigSupport::paraEmpresa($empresaId);
        CierreRendicionMaquinavendingConfigSupport::exigirCompleta($config, $empresaId);

        $preview = CierreRendicionMaquinavendingAsientoSupport::generarPreviewGrupo($rendiciones, $config);
        $preview['fecha_asiento'] = CierreRendicionMaquinavendingGrupoSupport::fechaAsientoDesdeGrupo($fechaDia);
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

        $config = CierreRendicionMaquinavendingConfigSupport::paraEmpresa($empresaId);
        CierreRendicionMaquinavendingConfigSupport::exigirCompleta($config, $empresaId);

        $fecha = CierreRendicionMaquinavendingGrupoSupport::fechaAsientoDesdeGrupo($fechaDia);

        PeriodoContableCierreSupport::assertOperacionPermitida(
            $empresaId,
            $fecha,
            PeriodoContableCierreSupport::ALCANCE_CONTABLE,
            Auth::id(),
        );

        $preview = CierreRendicionMaquinavendingAsientoSupport::generarPreviewGrupo($rendiciones, $config);
        if (($preview['advertencias'] ?? []) !== []) {
            foreach ($preview['advertencias'] as $adv) {
                if (str_contains((string) $adv, 'no cuadra')) {
                    throw new InvalidArgumentException((string) $adv);
                }
            }
        }

        $ids = $rendiciones->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $pv = $rendiciones->first()?->puntoventaCae?->codigo ?? '';
        $observacion = CierreRendicionMaquinavendingAsientoSupport::DESCRIPCION_ASIENTO
            .' — '.Carbon::parse($fechaDia)->format('d/m/Y')
            .($pv !== '' ? ' — PV '.$pv : '')
            .' — rend. '.implode(', ', $ids);

        $payload = CierreRendicionMaquinavendingAsientoSupport::armarPayloadAsiento(
            $preview['lineas'],
            $empresaId,
            $config,
            $fecha,
            $observacion,
        );

        $tipoAsientoId = $this->resolverTipoAsientoId();

        return DB::transaction(function () use ($rendiciones, $payload, $tipoAsientoId, $ids) {
            $rendicionIds = $rendiciones->pluck('id')->map(fn ($id) => (int) $id)->all();
            $bloqueadas = RendicionMaquinavendingCaja::query()
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

        $cerradas = $rendiciones->filter(fn (RendicionMaquinavendingCaja $r) => $r->tieneCierreContable());
        if ($cerradas->isEmpty()) {
            throw new InvalidArgumentException('El grupo no tiene cierre contable para anular.');
        }

        if ($cerradas->contains(fn (RendicionMaquinavendingCaja $r) => $r->esCierreContableLegacy())) {
            throw new InvalidArgumentException(
                'Hay rendiciones cerradas históricas (sin asiento en anitaERP). No admite anulación desde este módulo.',
            );
        }

        if ($rendiciones->contains(fn (RendicionMaquinavendingCaja $r) => $r->puedeCerrarContablemente())) {
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
            $bloqueadas = RendicionMaquinavendingCaja::query()
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
        $resultado = $this->ejecutarCierreGrupo(
            (int) $rendicion->empresa_id,
            CierreRendicionMaquinavendingGrupoSupport::fechaDiaDesdeRendicion($rendicion),
            (int) ($rendicion->puntoventa_cae_id ?? 0),
        );

        return [
            'asiento_id' => $resultado['asiento_id'],
            'numeroasiento' => $resultado['numeroasiento'],
        ];
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

        CierreRendicionMaquinavendingConfigSupport::exigirCompleta(
            CierreRendicionMaquinavendingConfigSupport::paraEmpresa($empresaId),
            $empresaId,
        );

        $rendiciones = $this->listarPendientesEnRango($empresaId, $desde, $hasta);
        $gruposRaw = CierreRendicionMaquinavendingGrupoSupport::agrupar(
            new EloquentCollection($rendiciones->all()),
        );
        $porDia = [];
        $total = 0.0;

        foreach ($rendiciones as $r) {
            $fecha = CierreRendicionMaquinavendingGrupoSupport::fechaDiaDesdeRendicion($r);
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

        $grupos = CierreRendicionMaquinavendingGrupoSupport::agrupar(
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
     * @return Collection<int, RendicionMaquinavendingCaja>
     */
    private function listarPendientesEnRango(int $empresaId, string $desde, string $hasta): Collection
    {
        $q = RendicionMaquinavendingCaja::query()
            ->with([
                'maquinavendingRendicion:id,fecha_jornada',
                'puntoventaCae:id,codigo,nombre',
            ])
            ->where('empresa_id', $empresaId);

        CierreRendicionMaquinavendingListadoFiltros::aplicarEstadoCierre($q, [
            'estado_cierre' => CierreRendicionMaquinavendingListadoFiltros::ESTADO_PENDIENTE,
        ]);
        $this->aplicarFiltroFechaJornadaRango($q, $desde, $hasta);

        return $q->orderBy('fecharendicion')->orderBy('id')->get();
    }

    /**
     * @param  Builder<RendicionMaquinavendingCaja>  $query
     */
    private function aplicarFiltroFechaJornadaRango(Builder $query, string $desde, string $hasta): void
    {
        $query->where(function ($w) use ($desde, $hasta) {
            $w->whereHas('maquinavendingRendicion', function ($mr) use ($desde, $hasta) {
                $mr->whereDate('fecha_jornada', '>=', $desde)
                    ->whereDate('fecha_jornada', '<=', $hasta);
            })->orWhere(function ($q) use ($desde, $hasta) {
                $q->whereDoesntHave('maquinavendingRendicion')
                    ->whereDate('fecharendicion', '>=', $desde)
                    ->whereDate('fecharendicion', '<=', $hasta);
            });
        });
    }

    public function anularCierre(int $rendicionId): void
    {
        $rendicion = RendicionMaquinavendingCaja::query()
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
            $rendicion = RendicionMaquinavendingCaja::query()
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

    public function findParaCierre(int $id): RendicionMaquinavendingCaja
    {
        return RendicionMaquinavendingCaja::query()
            ->with([
                'empresa',
                'puntoventaCae',
                'puntoventaCaea',
                'maquinavendingRendicion',
                'maquinavending',
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
     * @return EloquentCollection<int, RendicionMaquinavendingCaja>
     */
    private function findRendicionesGrupo(int $empresaId, string $fechaDia, int $puntoventaCaeId): EloquentCollection
    {
        $q = RendicionMaquinavendingCaja::query()
            ->with([
                'empresa',
                'puntoventaCae',
                'puntoventaCaea',
                'maquinavendingRendicion',
                'maquinavending',
                'movimientos.cuentacaja',
                'asiento',
            ]);

        CierreRendicionMaquinavendingGrupoSupport::aplicarFiltroGrupo($q, $empresaId, $fechaDia, $puntoventaCaeId);

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
            /** @var EloquentCollection<int, RendicionMaquinavendingCaja>|null $rendiciones */
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
     * @return EloquentCollection<int, RendicionMaquinavendingCaja>
     */
    private function findRendicionesPendientesGrupo(
        int $empresaId,
        string $fechaDia,
        int $puntoventaCaeId,
    ): EloquentCollection {
        return $this->findRendicionesGrupo($empresaId, $fechaDia, $puntoventaCaeId)
            ->filter(fn (RendicionMaquinavendingCaja $r) => $r->puedeCerrarContablemente())
            ->values();
    }
}
