<?php

namespace App\Services\Contable;

use App\Support\Database\SqlDialectSupport;
use App\Models\Caja\RendicionMaquina;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Asiento;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Support\Contable\CierreRendicionMaquinaAsientoSupport;
use App\Support\Contable\CierreRendicionMaquinaCierreLock;
use App\Support\Contable\CierreRendicionMaquinaConciliacionFlashSupport;
use App\Support\Contable\CierreRendicionMaquinaConfigSupport;
use App\Support\Contable\CierreRendicionMaquinaGrupoSupport;
use App\Support\Contable\CierreRendicionMaquinaListadoFiltros;
use App\Support\Contable\CierreRendicionMaquinaVentaListadoSupport;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Ventas\MaquinaFslTipoSupport;
use App\Services\Ventas\CierreSalaExentaEmisionService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CierreRendicionMaquinaService
{
    public function __construct(
        private readonly AsientoRepositoryInterface $asientoRepository,
        private readonly Asiento_MovimientoRepositoryInterface $asientoMovimientoRepository,
        private readonly TipoasientoRepositoryInterface $tipoasientoRepository,
        private readonly CierreSalaExentaEmisionService $exentaEmisionService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function listar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        $q = RendicionMaquina::query()
            ->with([
                'empresa:id,nombre',
                'asiento:id,numeroasiento,fecha',
                'cierreContableUsuario:id,nombre',
                'valores.cuentacaja',
                'gastos',
            ])
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        CierreRendicionMaquinaListadoFiltros::aplicar($q, $filtros);

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

        $grupos = CierreRendicionMaquinaGrupoSupport::agrupar(
            new EloquentCollection($rendiciones->all()),
        );

        if (! $paginar) {
            return $grupos;
        }

        return CierreRendicionMaquinaGrupoSupport::paginarGrupos(
            $grupos,
            CierreRendicionMaquinaGrupoSupport::GRUPOS_POR_PAGINA,
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

        $config = CierreRendicionMaquinaConfigSupport::paraEmpresa($empresaId);

        $preview = CierreRendicionMaquinaAsientoSupport::generarPreviewGrupo(
            $rendiciones,
            $config,
            $empresaId,
            $fechaDia,
        );
        $preview['fecha_asiento'] = CierreRendicionMaquinaGrupoSupport::fechaAsientoDesdeGrupo($fechaDia);
        $preview['empresa_id'] = $empresaId;
        $preview['fecha_dia'] = Carbon::parse($fechaDia)->toDateString();
        $preview['puntoventa_fsl'] = CierreRendicionMaquinaConfigSupport::puntoventaFsl($empresaId);

        return $preview;
    }

    /**
     * @return array{
     *   asiento_id: int,
     *   asiento_ids: list<int>,
     *   numeroasiento: string,
     *   numeros_asiento: list<string>,
     *   rendicion_ids: list<int>,
     *   fsl: array{tipo: string, letra: string, sucursal: int, nro: int, monto: float}
     * }
     */
    public function ejecutarCierreGrupo(int $empresaId, string $fechaDia): array
    {
        return CierreRendicionMaquinaCierreLock::conExclusividadEmpresa(
            $empresaId,
            fn () => $this->ejecutarCierreGrupoExclusivo($empresaId, $fechaDia),
            true,
        );
    }

    /**
     * @return array{
     *   asiento_id: int,
     *   asiento_ids: list<int>,
     *   numeroasiento: string,
     *   numeros_asiento: list<string>,
     *   rendicion_ids: list<int>,
     *   fsl: array{tipo: string, letra: string, sucursal: int, nro: int, monto: float}
     * }
     */
    private function ejecutarCierreGrupoExclusivo(int $empresaId, string $fechaDia): array
    {
        $rendiciones = $this->findRendicionesPendientesGrupo($empresaId, $fechaDia);
        if ($rendiciones->isEmpty()) {
            throw new InvalidArgumentException('No hay rendiciones pendientes en el grupo indicado.');
        }

        $this->assertCorrelatividadCierre($empresaId, $fechaDia);

        $config = CierreRendicionMaquinaConfigSupport::paraEmpresa($empresaId);

        $fecha = CierreRendicionMaquinaGrupoSupport::fechaAsientoDesdeGrupo($fechaDia);

        PeriodoContableCierreSupport::assertOperacionPermitida(
            $empresaId,
            $fecha,
            PeriodoContableCierreSupport::ALCANCE_CONTABLE,
            Auth::id(),
        );

        $preview = CierreRendicionMaquinaAsientoSupport::generarPreviewGrupo(
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
        $pvFsl = CierreRendicionMaquinaConfigSupport::puntoventaFsl($empresaId);
        $obsBase = CierreRendicionMaquinaAsientoSupport::DESCRIPCION_ASIENTO
            .' — '.Carbon::parse($fechaDia)->format('d/m/Y')
            .' — PV FSL '.$pvFsl
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
            $pvFsl,
        ) {
            $rendicionIds = $rendiciones->pluck('id')->map(fn ($id) => (int) $id)->all();
            $bloqueadas = RendicionMaquina::query()
                ->whereIn('id', $rendicionIds)
                ->lockForUpdate()
                ->get();

            foreach ($bloqueadas as $rendicion) {
                if ($rendicion->tieneCierreContable()) {
                    throw new InvalidArgumentException(
                        'La rendición #'.$rendicion->id.' ya fue cerrada contablemente.',
                    );
                }
                if ((int) ($rendicion->venta_id ?? 0) > 0) {
                    throw new InvalidArgumentException(
                        'La rendición #'.$rendicion->id.' ya tiene FSL vinculado (venta #'.$rendicion->venta_id.').',
                    );
                }
            }

            $fsl = $this->exentaEmisionService->emitir(
                MaquinaFslTipoSupport::tipo(),
                MaquinaFslTipoSupport::LETRA,
                MaquinaFslTipoSupport::nombreCliente(),
                $empresaId,
                $fechaDia,
                (float) ($preview['fsl_monto'] ?? 0),
                $pvFsl,
                'FSL máquinas '.Carbon::parse($fechaDia)->format('d/m/Y')
                    .' — PV '.$pvFsl.' — rend. '.implode(', ', $ids),
            );

            $asientoIds = [];
            $numerosAsiento = [];
            $numeroPrincipal = '';

            foreach ($preview['asientos'] ?? [] as $bloque) {
                $leyenda = (string) ($bloque['leyenda'] ?? CierreRendicionMaquinaAsientoSupport::DESCRIPCION_ASIENTO);
                $payload = CierreRendicionMaquinaAsientoSupport::armarPayloadAsiento(
                    $bloque['lineas'] ?? [],
                    $empresaId,
                    $fecha,
                    $obsBase.' — '.$leyenda,
                );
                if (($payload['cuentacontable_ids'] ?? []) === []) {
                    continue;
                }
                $payload['tipoasiento_id'] = $tipoAsientoId;

                $asiento = $this->asientoRepository->create($payload);
                if ($asiento === 'Error' || $asiento === null) {
                    throw new RuntimeException('Error al grabar asiento «'.$leyenda.'» en ERP/ctamov.');
                }

                $this->asientoMovimientoRepository->create($payload, $asiento->id);
                $asientoIds[] = (int) $asiento->id;
                $nro = (string) ($asiento->numeroasiento ?? '');
                if ($nro !== '') {
                    $numerosAsiento[] = $nro;
                }
                if ($numeroPrincipal === '') {
                    $numeroPrincipal = $nro;
                }
            }

            if ($asientoIds === []) {
                throw new InvalidArgumentException(
                    'Sin movimientos contables para el grupo (montos en cero). No se genera asiento.',
                );
            }

            $ahora = now();
            $usuarioId = Auth::id();
            $estadoFac = (string) config(
                'rendicion_maquina_anita.cierre_rendicion_contable.estado_facturado_anita',
                'F',
            );
            foreach ($bloqueadas as $rendicion) {
                $rendicion->update([
                    'venta_id' => $fsl['venta_id'],
                    'asiento_id' => $asientoIds[0],
                    'asientos_cierre_ids_json' => $asientoIds,
                    'cierre_contable_en' => $ahora,
                    'cierre_contable_usuario_id' => $usuarioId,
                    'factura_tipo' => $fsl['tipo'],
                    'factura_letra' => $fsl['letra'],
                    'factura_sucursal' => $fsl['sucursal'],
                    'factura_nro' => $fsl['nro'],
                    'factura_fecha' => $fechaDia,
                    'estado_facturacion' => $estadoFac,
                ]);
            }

            return [
                'asiento_id' => $asientoIds[0],
                'asiento_ids' => $asientoIds,
                'numeroasiento' => $numeroPrincipal,
                'numeros_asiento' => $numerosAsiento,
                'rendicion_ids' => $ids,
                'fsl' => $fsl,
            ];
        });
    }

    public function anularCierreGrupo(int $empresaId, string $fechaDia): void
    {
        CierreRendicionMaquinaCierreLock::conExclusividadEmpresa(
            $empresaId,
            function () use ($empresaId, $fechaDia): void {
                $this->anularCierreGrupoExclusivo($empresaId, $fechaDia);
            },
            true,
        );
    }

    private function anularCierreGrupoExclusivo(int $empresaId, string $fechaDia): void
    {
        $rendiciones = $this->findRendicionesGrupo($empresaId, $fechaDia);
        if ($rendiciones->isEmpty()) {
            throw new InvalidArgumentException('No se encontraron rendiciones del grupo.');
        }

        $cerradas = $rendiciones->filter(fn (RendicionMaquina $r) => $r->tieneCierreContable());
        if ($cerradas->isEmpty()) {
            throw new InvalidArgumentException('El grupo no tiene cierre contable para anular.');
        }

        if ($cerradas->contains(fn (RendicionMaquina $r) => $r->esCierreContableLegacy())) {
            throw new InvalidArgumentException(
                'Hay rendiciones cerradas históricas (sin asiento en anitaERP). No admite anulación desde este módulo.',
            );
        }

        if ($rendiciones->contains(fn (RendicionMaquina $r) => $r->puedeCerrarContablemente())) {
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
            $bloqueadas = RendicionMaquina::query()
                ->whereIn('id', $rendicionIds)
                ->lockForUpdate()
                ->get();

            $ventaIds = $bloqueadas
                ->pluck('venta_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            foreach ($asientoIds as $asientoId) {
                $this->asientoRepository->delete($asientoId);
            }

            foreach ($ventaIds as $ventaId) {
                $this->exentaEmisionService->anularSiExiste(
                    $ventaId,
                    fn ($tipo) => MaquinaFslTipoSupport::esFsl($tipo),
                    'FSL',
                );
            }

            RendicionMaquina::query()
                ->whereIn('id', $rendicionIds)
                ->update([
                    'asiento_id' => null,
                    'venta_id' => null,
                    'asientos_cierre_ids_json' => null,
                    'cierre_contable_en' => null,
                    'cierre_contable_usuario_id' => null,
                    'cierre_contable_legacy' => false,
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
     * @return array<string, mixed>
     */
    public function resumenPendientesCierre(int $empresaId): array
    {
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Indique empresa.');
        }

        $empresaNombre = (string) (Empresa::query()->whereKey($empresaId)->value('nombre') ?? ('Empresa #'.$empresaId));
        $rendiciones = $this->listarPendientesEmpresa($empresaId);
        $gruposRaw = CierreRendicionMaquinaGrupoSupport::agrupar(
            new EloquentCollection($rendiciones->all()),
        );

        $totalResultado = 0.0;
        $porDia = [];
        $fechas = [];

        foreach ($rendiciones as $r) {
            $fecha = CierreRendicionMaquinaGrupoSupport::fechaDiaDesdeRendicion($r);
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
            $monto = self::resultadoDesdeRendicion($r);
            $porDia[$fecha]['cantidad']++;
            $porDia[$fecha]['total_cobrado'] = round($porDia[$fecha]['total_cobrado'] + $monto, 2);
            $totalResultado = round($totalResultado + $monto, 2);
        }

        $grupos = [];
        foreach ($gruposRaw as $grupo) {
            $fecha = (string) ($grupo['fecha_dia'] ?? '');
            if ($fecha !== '' && isset($porDia[$fecha])) {
                $porDia[$fecha]['cantidad_grupos']++;
            }

            $montoGrupo = round((float) ($grupo['total_resultado'] ?? 0), 2);

            $grupos[] = [
                'clave' => (string) ($grupo['clave'] ?? ''),
                'empresa_id' => (int) ($grupo['empresa_id'] ?? $empresaId),
                'fecha_dia' => $fecha,
                'fecha_dia_fmt' => $fecha !== '' ? Carbon::parse($fecha)->format('d/m/Y') : '',
                'puntoventa_cae_id' => 0,
                'puntoventa_label' => 'PV FSL '.(string) ($grupo['puntoventa_fsl'] ?? ''),
                'cantidad_rendiciones' => (int) ($grupo['cantidad_rendiciones'] ?? 0),
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
            'total_cobrado' => $totalResultado,
            'total_factura' => $totalResultado,
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

        $this->assertCorrelatividadCierre($empresaId, $desde);

        $rendiciones = $this->listarPendientesEnRango($empresaId, $desde, $hasta);
        $gruposRaw = CierreRendicionMaquinaGrupoSupport::agrupar(
            new EloquentCollection($rendiciones->all()),
        );
        $porDia = [];
        $total = 0.0;

        foreach ($rendiciones as $r) {
            $fecha = CierreRendicionMaquinaGrupoSupport::fechaDiaDesdeRendicion($r);
            if (! isset($porDia[$fecha])) {
                $porDia[$fecha] = [
                    'fecha_jornada' => $fecha,
                    'cantidad' => 0,
                    'cantidad_grupos' => 0,
                    'total_cobrado' => 0.0,
                ];
            }
            $monto = self::resultadoDesdeRendicion($r);
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
            /** @var Collection<int, RendicionMaquina> $rends */
            $rends = $grupo['rendiciones'] ?? collect();
            $filas = [];
            $montoGrupo = round((float) ($grupo['total_resultado'] ?? 0), 2);
            foreach ($rends as $r) {
                $monto = self::resultadoDesdeRendicion($r);
                $filas[] = [
                    'id' => (int) $r->id,
                    'codigo' => (string) ($r->codigo ?? ''),
                    'total_cobrado' => $monto,
                    'fecharendicion_fmt' => $r->fecha?->format('d/m/Y'),
                ];
            }
            $grupos[] = [
                'clave' => (string) ($grupo['clave'] ?? ''),
                'fecha_dia' => (string) ($grupo['fecha_dia'] ?? ''),
                'fecha_dia_fmt' => (string) ($grupo['fecha_dia_fmt'] ?? ''),
                'puntoventa_label' => 'PV FSL '.(string) ($grupo['puntoventa_fsl'] ?? ''),
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

        return CierreRendicionMaquinaCierreLock::conExclusividadEmpresa(
            $empresaId,
            fn () => $this->ejecutarCierreRangoExclusivo($empresaId, $desde, $hasta),
            false,
        );
    }

    /**
     * @return array{
     *   ok: list<array{grupo_clave: string, asiento_id: int, numeroasiento: string, rendicion_ids: list<int>}>,
     *   errores: list<array{grupo_clave: string, mensaje: string}>
     * }
     */
    private function ejecutarCierreRangoExclusivo(int $empresaId, string $desde, string $hasta): array
    {
        $this->assertCorrelatividadCierre($empresaId, $desde);

        $rendiciones = $this->listarPendientesEnRango($empresaId, $desde, $hasta);
        if ($rendiciones->isEmpty()) {
            throw new InvalidArgumentException('No hay rendiciones pendientes de cierre en el rango indicado.');
        }

        $grupos = CierreRendicionMaquinaGrupoSupport::agrupar(
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
                $resultado = $this->ejecutarCierreGrupoExclusivo(
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
        return app(CierreRendicionMaquinaConciliacionFlashSupport::class)
            ->conciliar($empresaId, $fechaDesde, $fechaHasta, $tolerancia);
    }

    /**
     * Listado «Venta de máquinas» (p-vtamaquina.c).
     *
     * @return array<string, mixed>
     */
    public function reporteVentaListado(int $empresaId, string $fechaDesde, string $fechaHasta): array
    {
        return app(CierreRendicionMaquinaVentaListadoSupport::class)
            ->generar($empresaId, $fechaDesde, $fechaHasta);
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
            .'). En máquinas el cierre debe ser correlativo por fecha: numeración FSL y acumulación mensual.',
        );
    }

    public function fechaPendienteMasAntigua(int $empresaId): ?string
    {
        $q = $this->queryPendientesEmpresa($empresaId);

        $min = $q->min(DB::raw(SqlDialectSupport::fecha('rendicion_maquina.fecha')));
        if ($min === null || trim((string) $min) === '') {
            return null;
        }

        return Carbon::parse((string) $min)->toDateString();
    }

    private function fechaPendienteMasAntiguaAnteriorA(int $empresaId, string $fechaDia): ?string
    {
        $q = $this->queryPendientesEmpresa($empresaId)
            ->whereDate('rendicion_maquina.fecha', '<', $fechaDia);

        $min = $q->min(DB::raw(SqlDialectSupport::fecha('rendicion_maquina.fecha')));
        if ($min === null || trim((string) $min) === '') {
            return null;
        }

        return Carbon::parse((string) $min)->toDateString();
    }

    /**
     * @return Collection<int, RendicionMaquina>
     */
    private function listarPendientesEmpresa(int $empresaId): Collection
    {
        return $this->queryPendientesEmpresa($empresaId)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, RendicionMaquina>
     */
    private function listarPendientesEnRango(int $empresaId, string $desde, string $hasta): Collection
    {
        return $this->queryPendientesEmpresa($empresaId)
            ->whereDate('rendicion_maquina.fecha', '>=', $desde)
            ->whereDate('rendicion_maquina.fecha', '<=', $hasta)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Builder<RendicionMaquina>
     */
    private function queryPendientesEmpresa(int $empresaId): Builder
    {
        $q = RendicionMaquina::query()->where('empresa_id', $empresaId);
        CierreRendicionMaquinaListadoFiltros::aplicarEstadoCierre($q, [
            'estado_cierre' => CierreRendicionMaquinaListadoFiltros::ESTADO_PENDIENTE,
        ]);
        CierreRendicionMaquinaListadoFiltros::aplicarTurnoCierre($q);

        return $q;
    }

    /**
     * @return EloquentCollection<int, RendicionMaquina>
     */
    private function findRendicionesGrupo(int $empresaId, string $fechaDia): EloquentCollection
    {
        $q = RendicionMaquina::query()
            ->with(['empresa', 'asiento']);

        CierreRendicionMaquinaGrupoSupport::aplicarFiltroGrupo($q, $empresaId, $fechaDia);

        return $q->orderBy('fecha')->orderBy('id')->get();
    }

    /**
     * @return EloquentCollection<int, RendicionMaquina>
     */
    private function findRendicionesPendientesGrupo(int $empresaId, string $fechaDia): EloquentCollection
    {
        return $this->findRendicionesGrupo($empresaId, $fechaDia)
            ->filter(fn (RendicionMaquina $r) => $r->puedeCerrarContablemente())
            ->values();
    }

    private function resolverTipoAsientoId(): int
    {
        $abrev = (string) config('rendicion_maquina_anita.cierre_rendicion_contable.tipoasiento_abreviatura', 'MAQ');
        $tipo = $this->tipoasientoRepository->findPorAbreviatura($abrev);
        if ($tipo === null) {
            throw new RuntimeException('Tipo de asiento «'.$abrev.'» no configurado.');
        }

        return (int) $tipo->id;
    }

    private static function resultadoDesdeRendicion(RendicionMaquina $rendicion): float
    {
        $calc = is_array($rendicion->calc_json['variables'] ?? null) ? $rendicion->calc_json['variables'] : [];
        $resultado = round(
            (float) ($calc['calc.resultado_rodillo'] ?? $calc['resultado_rodillo'] ?? 0)
            + (float) ($calc['calc.resultado_ruleta'] ?? $calc['resultado_ruleta'] ?? 0),
            2,
        );
        if (abs($resultado) <= 0.0001) {
            $resultado = round((float) ($rendicion->resultado_turno ?? 0), 2);
        }

        return $resultado;
    }
}
