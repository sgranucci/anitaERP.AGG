<?php

namespace App\Services\Contable;

use App\Support\Database\SqlDialectSupport;
use App\Models\Caja\Bingo\RendicionBingoCaja;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Asiento;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Support\Contable\CierreRendicionBingoAsientoSupport;
use App\Support\Contable\CierreRendicionBingoConciliacionFlashSupport;
use App\Support\Contable\CierreRendicionBingoConfigSupport;
use App\Support\Contable\CierreRendicionBingoFbiAnitaSupport;
use App\Support\Contable\CierreRendicionBingoGrupoSupport;
use App\Support\Contable\CierreRendicionBingoListadoFiltros;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Caja\Bingo\RendicionBingoCajaListadoFiltros;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CierreRendicionBingoService
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
        $q = RendicionBingoCaja::query()
            ->with([
                'empresa:id,nombre',
                'turnoOperativo.turno:id,nombre',
                'jornada:id,fecha_jornada',
                'asiento:id,numeroasiento,fecha',
                'cierreContableUsuario:id,nombre',
                'creousuario:id,nombre',
            ])
            ->orderByDesc('fecha_jornada')
            ->orderByDesc('id');

        RendicionBingoCajaListadoFiltros::aplicarScopeEmpresasAsignadas($q, $filtros);
        CierreRendicionBingoListadoFiltros::aplicar($q, $filtros);

        return $paginar ? $q->paginate(10) : $q->get();
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function listarAgrupado(array $filtros, bool $paginar = true): LengthAwarePaginator|array
    {
        $rendiciones = $this->listar($filtros, false);
        if (! $rendiciones instanceof Collection) {
            $rendiciones = collect($rendiciones);
        }

        $grupos = CierreRendicionBingoGrupoSupport::agrupar(
            new EloquentCollection($rendiciones->all()),
        );

        if (! $paginar) {
            return $grupos;
        }

        return CierreRendicionBingoGrupoSupport::paginarGrupos(
            $grupos,
            CierreRendicionBingoGrupoSupport::GRUPOS_POR_PAGINA,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function previewGrupo(int $empresaId, string $fechaDia): array
    {
        $rendiciones = $this->findRendicionesPendientesGrupo($empresaId, $fechaDia);
        if ($rendiciones->isEmpty()) {
            throw new InvalidArgumentException('No hay rendiciones pendientes en el grupo indicado.');
        }

        $this->assertCorrelatividadCierre($empresaId, $fechaDia);

        $config = CierreRendicionBingoConfigSupport::paraEmpresa($empresaId);
        CierreRendicionBingoConfigSupport::exigirCompleta($config);

        $preview = CierreRendicionBingoAsientoSupport::generarPreviewGrupo(
            $rendiciones,
            $config,
            $empresaId,
            $fechaDia,
        );
        $preview['fecha_asiento'] = CierreRendicionBingoGrupoSupport::fechaAsientoDesdeGrupo($fechaDia);
        $preview['empresa_id'] = $empresaId;
        $preview['fecha_dia'] = Carbon::parse($fechaDia)->toDateString();
        $preview['puntoventa_fbi'] = CierreRendicionBingoConfigSupport::puntoventaFbi($empresaId);

        return $preview;
    }

    /**
     * @return array{
     *   asiento_id: int,
     *   asiento_ids: list<int>,
     *   numeroasiento: string,
     *   rendicion_ids: list<int>,
     *   fbi: array{tipo: string, letra: string, sucursal: int, nro: int, monto: float}
     * }
     */
    public function ejecutarCierreGrupo(int $empresaId, string $fechaDia): array
    {
        $rendiciones = $this->findRendicionesPendientesGrupo($empresaId, $fechaDia);
        if ($rendiciones->isEmpty()) {
            throw new InvalidArgumentException('No hay rendiciones pendientes en el grupo indicado.');
        }

        $this->assertCorrelatividadCierre($empresaId, $fechaDia);

        $config = CierreRendicionBingoConfigSupport::paraEmpresa($empresaId);
        CierreRendicionBingoConfigSupport::exigirCompleta($config);

        $fecha = CierreRendicionBingoGrupoSupport::fechaAsientoDesdeGrupo($fechaDia);

        PeriodoContableCierreSupport::assertOperacionPermitida(
            $empresaId,
            $fecha,
            PeriodoContableCierreSupport::ALCANCE_CONTABLE,
            Auth::id(),
        );

        $preview = CierreRendicionBingoAsientoSupport::generarPreviewGrupo(
            $rendiciones,
            $config,
            $empresaId,
            $fechaDia,
        );

        foreach ($preview['advertencias'] ?? [] as $adv) {
            if (str_contains((string) $adv, 'no cuadr')) {
                throw new InvalidArgumentException((string) $adv);
            }
        }

        $ids = $rendiciones->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $tipoAsientoId = $this->resolverTipoAsientoId();
        $obsBase = CierreRendicionBingoAsientoSupport::DESCRIPCION_ASIENTO
            .' — '.Carbon::parse($fechaDia)->format('d/m/Y')
            .' — PV FBI '.CierreRendicionBingoConfigSupport::puntoventaFbi($empresaId)
            .' — rend. '.implode(', ', $ids);

        return DB::transaction(function () use (
            $rendiciones,
            $preview,
            $tipoAsientoId,
            $obsBase,
            $fecha,
            $empresaId,
            $fechaDia,
            $ids,
        ) {
            $rendicionIds = $rendiciones->pluck('id')->map(fn ($id) => (int) $id)->all();
            $bloqueadas = RendicionBingoCaja::query()
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

            $fbi = CierreRendicionBingoFbiAnitaSupport::emitirFbiExenta(
                $empresaId,
                $fechaDia,
                (float) ($preview['fbi_monto'] ?? 0),
            );

            $asientoIds = [];
            $numeroPrincipal = '';

            foreach ($preview['asientos'] ?? [] as $bloque) {
                $leyenda = (string) ($bloque['leyenda'] ?? CierreRendicionBingoAsientoSupport::DESCRIPCION_ASIENTO);
                $payload = CierreRendicionBingoAsientoSupport::armarPayloadAsiento(
                    $bloque['lineas'] ?? [],
                    $empresaId,
                    $fecha,
                    $obsBase.' — '.$leyenda,
                );
                // Bloque sin líneas netas (montos ~0): no crear asiento vacío.
                if (($payload['cuentacontable_ids'] ?? []) === []) {
                    continue;
                }
                $payload['tipoasiento_id'] = $tipoAsientoId;

                $asiento = $this->asientoRepository->create($payload);
                if ($asiento === 'Error' || $asiento === null) {
                    throw new RuntimeException('Error al grabar asiento «'.$leyenda.'» en Anita (bridge ctamov).');
                }

                $this->asientoMovimientoRepository->create($payload, $asiento->id);
                $asientoIds[] = (int) $asiento->id;
                if ($numeroPrincipal === '') {
                    $numeroPrincipal = (string) ($asiento->numeroasiento ?? '');
                }
            }

            if ($asientoIds === []) {
                throw new InvalidArgumentException(
                    'Sin movimientos contables para el grupo (montos en cero). No se genera asiento.',
                );
            }

            $ahora = now();
            $usuarioId = Auth::id();
            foreach ($bloqueadas as $rendicion) {
                CierreRendicionBingoFbiAnitaSupport::marcarRendicionesFacturadas($rendicion, $fbi, $fechaDia);
                $rendicion->update([
                    'asiento_id' => $asientoIds[0],
                    'asientos_cierre_ids_json' => $asientoIds,
                    'cierre_contable_en' => $ahora,
                    'cierre_contable_usuario_id' => $usuarioId,
                ]);
            }

            return [
                'asiento_id' => $asientoIds[0],
                'asiento_ids' => $asientoIds,
                'numeroasiento' => $numeroPrincipal,
                'rendicion_ids' => $ids,
                'fbi' => $fbi,
            ];
        });
    }

    public function anularCierreGrupo(int $empresaId, string $fechaDia): void
    {
        $rendiciones = $this->findRendicionesGrupo($empresaId, $fechaDia);
        if ($rendiciones->isEmpty()) {
            throw new InvalidArgumentException('No se encontraron rendiciones del grupo.');
        }

        $cerradas = $rendiciones->filter(fn (RendicionBingoCaja $r) => $r->tieneCierreContable());
        if ($cerradas->isEmpty()) {
            throw new InvalidArgumentException('El grupo no tiene cierre contable para anular.');
        }

        if ($rendiciones->contains(fn (RendicionBingoCaja $r) => $r->puedeCerrarContablemente())) {
            throw new InvalidArgumentException(
                'El grupo tiene rendiciones pendientes. Solo se puede anular un grupo totalmente cerrado.',
            );
        }

        /** @var list<int> $asientoIds */
        $asientoIds = [];
        foreach ($cerradas as $r) {
            $jsonIds = is_array($r->asientos_cierre_ids_json) ? $r->asientos_cierre_ids_json : [];
            foreach ($jsonIds as $aid) {
                $aid = (int) $aid;
                if ($aid > 0) {
                    $asientoIds[] = $aid;
                }
            }
            $principal = (int) ($r->asiento_id ?? 0);
            if ($principal > 0) {
                $asientoIds[] = $principal;
            }
        }
        $asientoIds = array_values(array_unique($asientoIds));

        if ($asientoIds === []) {
            throw new InvalidArgumentException('No se encontraron asientos vinculados al grupo.');
        }

        $asiento = Asiento::query()->find($asientoIds[0]);
        if ($asiento === null) {
            throw new InvalidArgumentException('No se encontró el asiento vinculado.');
        }

        PeriodoContableCierreSupport::assertOperacionPermitida(
            $empresaId,
            (string) ($asiento->fecha ?? now()->format('Y-m-d')),
            PeriodoContableCierreSupport::ALCANCE_CONTABLE,
            Auth::id(),
        );

        DB::transaction(function () use ($rendiciones, $asientoIds) {
            $rendicionIds = $rendiciones->pluck('id')->map(fn ($id) => (int) $id)->all();
            RendicionBingoCaja::query()
                ->whereIn('id', $rendicionIds)
                ->lockForUpdate()
                ->get();

            foreach ($asientoIds as $asientoId) {
                $this->asientoRepository->delete($asientoId);
            }

            RendicionBingoCaja::query()
                ->whereIn('id', $rendicionIds)
                ->update([
                    'asiento_id' => null,
                    'asientos_cierre_ids_json' => null,
                    'cierre_contable_en' => null,
                    'cierre_contable_usuario_id' => null,
                    'factura_tipo' => null,
                    'factura_letra' => null,
                    'factura_sucursal' => null,
                    'factura_nro' => null,
                    'factura_fecha' => null,
                    'estado_facturacion' => null,
                ]);
        });
    }

    /**
     * Resumen vivo de pendientes de cierre contable (sin filtro de fechas).
     *
     * @return array<string, mixed>
     */
    public function resumenPendientesCierre(int $empresaId): array
    {
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Indique empresa.');
        }

        $empresaNombre = (string) (Empresa::query()->whereKey($empresaId)->value('nombre') ?? ('Empresa #'.$empresaId));
        $rendiciones = $this->listarPendientesEmpresa($empresaId);
        $gruposRaw = CierreRendicionBingoGrupoSupport::agrupar(
            new EloquentCollection($rendiciones->all()),
        );

        $totalCobrado = 0.0;
        $porDia = [];
        $fechas = [];

        foreach ($rendiciones as $r) {
            $fecha = CierreRendicionBingoGrupoSupport::fechaDiaDesdeRendicion($r);
            if ($fecha !== '') {
                $fechas[$fecha] = true;
            }
            if (! isset($porDia[$fecha])) {
                $porDia[$fecha] = [
                    'fecha_jornada' => $fecha,
                    'fecha_jornada_fmt' => $fecha !== '' ? Carbon::parse($fecha)->format('d/m/Y') : '',
                    'cantidad' => 0,
                    'cantidad_grupos' => 0,
                    'total_cobrado' => 0.0,
                ];
            }
            $monto = round((float) ($r->total_cartones ?? 0), 2);
            $porDia[$fecha]['cantidad']++;
            $porDia[$fecha]['total_cobrado'] = round($porDia[$fecha]['total_cobrado'] + $monto, 2);
            $totalCobrado = round($totalCobrado + $monto, 2);
        }

        $grupos = [];
        foreach ($gruposRaw as $grupo) {
            $fecha = (string) ($grupo['fecha_dia'] ?? '');
            if ($fecha !== '' && isset($porDia[$fecha])) {
                $porDia[$fecha]['cantidad_grupos']++;
            }

            $montoGrupo = 0.0;
            /** @var Collection<int, RendicionBingoCaja> $rends */
            $rends = $grupo['rendiciones'] ?? collect();
            foreach ($rends as $r) {
                $montoGrupo = round($montoGrupo + (float) ($r->total_cartones ?? 0), 2);
            }

            $grupos[] = [
                'clave' => (string) ($grupo['clave'] ?? ''),
                'empresa_id' => (int) ($grupo['empresa_id'] ?? $empresaId),
                'fecha_dia' => $fecha,
                'fecha_dia_fmt' => $fecha !== '' ? Carbon::parse($fecha)->format('d/m/Y') : '',
                'puntoventa_cae_id' => 0,
                'puntoventa_label' => '',
                'cantidad_rendiciones' => $rends->count(),
                'total_cobrado' => $montoGrupo,
                'total_factura' => $montoGrupo,
            ];
        }

        usort($grupos, static function (array $a, array $b): int {
            return strcmp((string) ($a['fecha_dia'] ?? ''), (string) ($b['fecha_dia'] ?? ''));
        });

        ksort($porDia);
        $fechasOrden = array_keys($fechas);
        sort($fechasOrden);
        $fechaDesde = $fechasOrden[0] ?? null;
        $fechaHasta = $fechasOrden === [] ? null : $fechasOrden[array_key_last($fechasOrden)];
        $ahora = now();

        return [
            'empresa_id' => $empresaId,
            'empresa_nombre' => $empresaNombre,
            'cantidad_rendiciones' => $rendiciones->count(),
            'cantidad_grupos' => count($grupos),
            'cantidad_jornadas' => count($porDia),
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'fecha_desde_fmt' => $fechaDesde ? Carbon::parse($fechaDesde)->format('d/m/Y') : null,
            'fecha_hasta_fmt' => $fechaHasta ? Carbon::parse($fechaHasta)->format('d/m/Y') : null,
            'total_cobrado' => $totalCobrado,
            'total_factura' => $totalCobrado,
            'exige_correlatividad' => true,
            'generado_en' => $ahora->toDateTimeString(),
            'generado_en_fmt' => $ahora->format('d/m/Y H:i:s'),
            'grupos' => $grupos,
            'por_dia' => array_values($porDia),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function previewCierreRango(int $empresaId, string $fechaDesde, string $fechaHasta): array
    {
        $desde = Carbon::parse($fechaDesde)->toDateString();
        $hasta = Carbon::parse($fechaHasta)->toDateString();
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        CierreRendicionBingoConfigSupport::exigirCompleta(
            CierreRendicionBingoConfigSupport::paraEmpresa($empresaId),
        );

        $this->assertCorrelatividadCierre($empresaId, $desde);

        $rendiciones = $this->listarPendientesEnRango($empresaId, $desde, $hasta);
        $gruposRaw = CierreRendicionBingoGrupoSupport::agrupar(
            new EloquentCollection($rendiciones->all()),
        );
        $porDia = [];
        $total = 0.0;

        foreach ($rendiciones as $r) {
            $fecha = CierreRendicionBingoGrupoSupport::fechaDiaDesdeRendicion($r);
            if (! isset($porDia[$fecha])) {
                $porDia[$fecha] = [
                    'fecha_jornada' => $fecha,
                    'cantidad' => 0,
                    'cantidad_grupos' => 0,
                    'total_cobrado' => 0.0,
                ];
            }
            $monto = round((float) ($r->total_cartones ?? 0), 2);
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

        $grupos = [];
        foreach ($gruposRaw as $grupo) {
            /** @var Collection<int, RendicionBingoCaja> $rends */
            $rends = $grupo['rendiciones'] ?? collect();
            $filas = [];
            $montoGrupo = 0.0;
            foreach ($rends as $r) {
                $monto = round((float) ($r->total_cartones ?? 0), 2);
                $montoGrupo = round($montoGrupo + $monto, 2);
                $filas[] = [
                    'id' => (int) $r->id,
                    'codigo' => (string) ($r->codigo ?? ''),
                    'total_cobrado' => $monto,
                    'fecharendicion_fmt' => $r->fecharendicion?->format('d/m/Y H:i'),
                ];
            }
            $grupos[] = [
                'clave' => (string) ($grupo['clave'] ?? ''),
                'fecha_dia' => (string) ($grupo['fecha_dia'] ?? ''),
                'fecha_dia_fmt' => (string) ($grupo['fecha_dia_fmt'] ?? ''),
                'puntoventa_label' => 'Cierre diario',
                'cantidad_rendiciones' => $rends->count(),
                'total_cobrado' => $montoGrupo,
                'rendiciones' => $filas,
            ];
        }

        usort($grupos, static function (array $a, array $b): int {
            return strcmp((string) ($a['fecha_dia'] ?? ''), (string) ($b['fecha_dia'] ?? ''));
        });

        return [
            'empresa_id' => $empresaId,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'cantidad' => $rendiciones->count(),
            'cantidad_grupos' => count($grupos),
            'total_cobrado' => $total,
            'por_dia' => array_values($porDia),
            'grupos' => $grupos,
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

        $this->assertCorrelatividadCierre($empresaId, $desde);

        $rendiciones = $this->listarPendientesEnRango($empresaId, $desde, $hasta);
        if ($rendiciones->isEmpty()) {
            throw new InvalidArgumentException('No hay rendiciones pendientes de cierre en el rango indicado.');
        }

        $grupos = CierreRendicionBingoGrupoSupport::agrupar(
            new EloquentCollection($rendiciones->all()),
        );
        usort($grupos, static function (array $a, array $b): int {
            return strcmp((string) ($a['fecha_dia'] ?? ''), (string) ($b['fecha_dia'] ?? ''));
        });

        $ok = [];
        $errores = [];

        foreach ($grupos as $grupo) {
            $clave = (string) ($grupo['clave'] ?? '');
            try {
                $resultado = $this->ejecutarCierreGrupo(
                    (int) ($grupo['empresa_id'] ?? 0),
                    (string) ($grupo['fecha_dia'] ?? ''),
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
     * @return array<string, mixed>
     */
    public function conciliarFlash(int $empresaId, string $fechaDesde, string $fechaHasta, ?float $tolerancia = null): array
    {
        return app(CierreRendicionBingoConciliacionFlashSupport::class)
            ->conciliar($empresaId, $fechaDesde, $fechaHasta, $tolerancia);
    }

    /**
     * @return array{desde: string, hasta: string}
     */
    public function resolverRangoConciliacionDefault(int $empresaId): array
    {
        $hasta = Carbon::today()->toDateString();
        $desde = Carbon::today()->startOfMonth()->toDateString();

        return ['desde' => $desde, 'hasta' => $hasta];
    }

    /**
     * Impide cerrar una jornada si hay pendientes anteriores (FBI + acumulado mensual hospital).
     */
    public function assertCorrelatividadCierre(int $empresaId, string $fechaDia): void
    {
        $fecha = Carbon::parse($fechaDia)->toDateString();
        $anterior = $this->fechaPendienteMasAntiguaAnteriorA($empresaId, $fecha);
        if ($anterior === null) {
            return;
        }

        throw new InvalidArgumentException(
            'Hay jornadas pendientes anteriores (desde '
            .Carbon::parse($anterior)->format('d/m/Y')
            .'). En bingo el cierre debe ser correlativo por fecha: numeración FBI y acumulación mensual del canon hospital.',
        );
    }

    public function fechaPendienteMasAntigua(int $empresaId): ?string
    {
        $q = $this->queryPendientesEmpresa($empresaId);

        $min = $q->min(DB::raw(SqlDialectSupport::fecha('rendicion_bingo_caja.fecha_jornada')));
        if ($min === null || trim((string) $min) === '') {
            return null;
        }

        return Carbon::parse((string) $min)->toDateString();
    }

    private function fechaPendienteMasAntiguaAnteriorA(int $empresaId, string $fechaDia): ?string
    {
        $q = $this->queryPendientesEmpresa($empresaId)
            ->whereDate('rendicion_bingo_caja.fecha_jornada', '<', $fechaDia);

        $min = $q->min(DB::raw(SqlDialectSupport::fecha('rendicion_bingo_caja.fecha_jornada')));
        if ($min === null || trim((string) $min) === '') {
            return null;
        }

        return Carbon::parse((string) $min)->toDateString();
    }

    /**
     * @return Collection<int, RendicionBingoCaja>
     */
    private function listarPendientesEmpresa(int $empresaId): Collection
    {
        return $this->queryPendientesEmpresa($empresaId)
            ->with(['turnoOperativo.turno:id,nombre'])
            ->orderBy('fecha_jornada')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, RendicionBingoCaja>
     */
    private function listarPendientesEnRango(int $empresaId, string $desde, string $hasta): Collection
    {
        return $this->queryPendientesEmpresa($empresaId)
            ->with(['turnoOperativo.turno:id,nombre'])
            ->whereDate('rendicion_bingo_caja.fecha_jornada', '>=', $desde)
            ->whereDate('rendicion_bingo_caja.fecha_jornada', '<=', $hasta)
            ->orderBy('fecha_jornada')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Builder<RendicionBingoCaja>
     */
    private function queryPendientesEmpresa(int $empresaId): Builder
    {
        $q = RendicionBingoCaja::query()->where('empresa_id', $empresaId);
        CierreRendicionBingoListadoFiltros::aplicarEstadoCierre($q, [
            'estado_cierre' => CierreRendicionBingoListadoFiltros::ESTADO_PENDIENTE,
        ]);

        return $q;
    }

    /**
     * @return EloquentCollection<int, RendicionBingoCaja>
     */
    private function findRendicionesGrupo(int $empresaId, string $fechaDia): EloquentCollection
    {
        $q = RendicionBingoCaja::query()
            ->with(['empresa', 'asiento', 'turnoOperativo.turno']);

        CierreRendicionBingoGrupoSupport::aplicarFiltroGrupo($q, $empresaId, $fechaDia);

        return $q->orderBy('fecharendicion')->orderBy('id')->get();
    }

    /**
     * @return EloquentCollection<int, RendicionBingoCaja>
     */
    private function findRendicionesPendientesGrupo(int $empresaId, string $fechaDia): EloquentCollection
    {
        return $this->findRendicionesGrupo($empresaId, $fechaDia)
            ->filter(fn (RendicionBingoCaja $r) => $r->puedeCerrarContablemente())
            ->values();
    }

    private function resolverTipoAsientoId(): int
    {
        $abrev = (string) config('bingo.cierre_rendicion_contable.tipoasiento_abreviatura', 'BIN');
        $tipo = $this->tipoasientoRepository->findPorAbreviatura($abrev);
        if ($tipo === null) {
            throw new RuntimeException('Tipo de asiento «'.$abrev.'» no configurado.');
        }

        return (int) $tipo->id;
    }
}
