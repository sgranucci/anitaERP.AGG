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
use App\Support\Contable\CierreRendicionBingoCierreLock;
use App\Support\Contable\CierreRendicionBingoConciliacionFlashSupport;
use App\Support\Contable\CierreRendicionBingoConfigSupport;
use App\Support\Contable\CierreRendicionBingoGrupoSupport;
use App\Support\Contable\CierreRendicionBingoListadoFiltros;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Caja\Bingo\BingoPozoAcumuladoSupport;
use App\Support\Caja\Bingo\RendicionBingoCajaListadoFiltros;
use App\Support\Ventas\BingoFbiTipoSupport;
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

class CierreRendicionBingoService
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
        CierreRendicionBingoConfigSupport::exigirCompleta($config, $empresaId);

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
     *   fbi: array{tipo: string, letra: string, sucursal: int, nro: int, monto: float, venta_id: int, codigo: string}
     * }
     */
    public function ejecutarCierreGrupo(int $empresaId, string $fechaDia): array
    {
        return CierreRendicionBingoCierreLock::conExclusividadEmpresa(
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
     *   rendicion_ids: list<int>,
     *   fbi: array{tipo: string, letra: string, sucursal: int, nro: int, monto: float, venta_id: int, codigo: string}
     * }
     */
    private function ejecutarCierreGrupoExclusivo(int $empresaId, string $fechaDia): array
    {
        $rendiciones = $this->findRendicionesPendientesGrupo($empresaId, $fechaDia);
        if ($rendiciones->isEmpty()) {
            throw new InvalidArgumentException('No hay rendiciones pendientes en el grupo indicado.');
        }

        $this->assertCorrelatividadCierre($empresaId, $fechaDia);

        $config = CierreRendicionBingoConfigSupport::paraEmpresa($empresaId);
        CierreRendicionBingoConfigSupport::exigirCompleta($config, $empresaId);

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
        $pvFbi = CierreRendicionBingoConfigSupport::puntoventaFbi($empresaId);

        return DB::transaction(function () use (
            $rendiciones,
            $preview,
            $tipoAsientoId,
            $fecha,
            $empresaId,
            $fechaDia,
            $ids,
            $pvFbi,
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
                if ((int) ($rendicion->venta_id ?? 0) > 0) {
                    throw new InvalidArgumentException(
                        'La rendición #'.$rendicion->id.' ya tiene FBI vinculado (venta #'.$rendicion->venta_id.').',
                    );
                }
            }

            $fbi = $this->exentaEmisionService->emitir(
                BingoFbiTipoSupport::tipo(),
                BingoFbiTipoSupport::LETRA,
                BingoFbiTipoSupport::nombreCliente(),
                $empresaId,
                $fechaDia,
                (float) ($preview['fbi_monto'] ?? 0),
                $pvFbi,
                'FBI bingo '.Carbon::parse($fechaDia)->format('d/m/Y')
                    .' — PV '.$pvFbi.' — rend. '.implode(', ', $ids),
            );

            $asientoIds = [];
            $numeroPrincipal = '';

            foreach ($preview['asientos'] ?? [] as $bloque) {
                // Paridad p-vtabingo.c arma_asiento: ctav_desc_mov = leyenda corta
                // ("Pago de premios", "Dev. pozo acum.", "Canon …"). No anteponer fecha:
                // ctav_desc_mov tiene 30 chars y truncaba a "Cierre rendicin bingo DDMMYYYY".
                $leyenda = (string) ($bloque['leyenda'] ?? CierreRendicionBingoAsientoSupport::DESCRIPCION_ASIENTO);
                $payload = CierreRendicionBingoAsientoSupport::armarPayloadAsiento(
                    $bloque['lineas'] ?? [],
                    $empresaId,
                    $fecha,
                    $leyenda,
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
            $estadoFac = (string) config('bingo.cierre_rendicion_contable.estado_facturado_anita', 'F');
            foreach ($bloqueadas as $rendicion) {
                $rendicion->update([
                    'venta_id' => $fbi['venta_id'],
                    'asiento_id' => $asientoIds[0],
                    'asientos_cierre_ids_json' => $asientoIds,
                    'cierre_contable_en' => $ahora,
                    'cierre_contable_usuario_id' => $usuarioId,
                    'factura_tipo' => $fbi['tipo'],
                    'factura_letra' => $fbi['letra'],
                    'factura_sucursal' => $fbi['sucursal'],
                    'factura_nro' => $fbi['nro'],
                    'factura_fecha' => $fechaDia,
                    'estado_facturacion' => $estadoFac,
                ]);
            }

            BingoPozoAcumuladoSupport::registrarCierreDia(
                $empresaId,
                $fechaDia,
                null,
                is_int($usuarioId) ? $usuarioId : null,
            );

            return [
                'asiento_id' => $asientoIds[0],
                'asiento_ids' => $asientoIds,
                'numeroasiento' => $numeroPrincipal,
                'rendicion_ids' => $ids,
                'fbi' => $fbi,
            ];
        });
    }

    /**
     * @return array{
     *   fecha_dia: string,
     *   rendicion_ids: list<int>,
     *   asiento_ids: list<int>,
     *   numeros_asiento: list<string>,
     *   venta_ids: list<int>
     * }
     */
    public function anularCierreGrupo(int $empresaId, string $fechaDia): array
    {
        return CierreRendicionBingoCierreLock::conExclusividadEmpresa(
            $empresaId,
            fn () => $this->anularCierreGrupoExclusivo($empresaId, $fechaDia),
            true,
        );
    }

    /**
     * @return array{
     *   fecha_dia: string,
     *   rendicion_ids: list<int>,
     *   asiento_ids: list<int>,
     *   numeros_asiento: list<string>,
     *   venta_ids: list<int>
     * }
     */
    private function anularCierreGrupoExclusivo(int $empresaId, string $fechaDia): array
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

        $numerosAsiento = Asiento::query()
            ->whereIn('id', $asientoIds)
            ->orderBy('id')
            ->pluck('numeroasiento')
            ->map(static fn ($n) => trim((string) $n))
            ->filter(static fn ($n) => $n !== '')
            ->values()
            ->all();

        $ventaIdsOut = [];
        $rendicionIdsOut = [];

        DB::transaction(function () use (
            $rendiciones,
            $asientoIds,
            $empresaId,
            $fechaDia,
            &$ventaIdsOut,
            &$rendicionIdsOut,
        ) {
            $rendicionIds = $rendiciones->pluck('id')->map(fn ($id) => (int) $id)->all();
            $bloqueadas = RendicionBingoCaja::query()
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

            // Baja física: AsientoObserver borra líneas ERP; eliminarAnita borra ctamov.
            foreach ($asientoIds as $asientoId) {
                $this->asientoRepository->delete($asientoId);
            }

            foreach ($ventaIds as $ventaId) {
                $this->exentaEmisionService->anularSiExiste(
                    $ventaId,
                    fn ($tipo) => BingoFbiTipoSupport::esFbi($tipo),
                    'FBI',
                );
            }

            RendicionBingoCaja::query()
                ->whereIn('id', $rendicionIds)
                ->update([
                    'asiento_id' => null,
                    'venta_id' => null,
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

            BingoPozoAcumuladoSupport::borrarDesdeFecha($empresaId, $fechaDia);

            $ventaIdsOut = $ventaIds;
            $rendicionIdsOut = $rendicionIds;
        });

        return [
            'fecha_dia' => Carbon::parse($fechaDia)->toDateString(),
            'rendicion_ids' => $rendicionIdsOut,
            'asiento_ids' => $asientoIds,
            'numeros_asiento' => $numerosAsiento,
            'venta_ids' => $ventaIdsOut,
        ];
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
            $empresaId,
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
     *   omitidos: list<array{grupo_clave: string, mensaje: string}>,
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

        return CierreRendicionBingoCierreLock::conExclusividadEmpresa(
            $empresaId,
            fn () => $this->ejecutarCierreRangoExclusivo($empresaId, $desde, $hasta),
            false,
        );
    }

    /**
     * @return array{
     *   ok: list<array{grupo_clave: string, asiento_id: int, numeroasiento: string, rendicion_ids: list<int>}>,
     *   omitidos: list<array{grupo_clave: string, mensaje: string}>,
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

        $grupos = CierreRendicionBingoGrupoSupport::agrupar(
            new EloquentCollection($rendiciones->all()),
        );
        usort($grupos, static function (array $a, array $b): int {
            return strcmp((string) ($a['fecha_dia'] ?? ''), (string) ($b['fecha_dia'] ?? ''));
        });

        $ok = [];
        $omitidos = [];
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
                $mensaje = $e->getMessage();
                if (str_contains($mensaje, 'ya fue cerrada contablemente')
                    || str_contains($mensaje, 'No hay rendiciones pendientes')) {
                    $omitidos[] = [
                        'grupo_clave' => $clave,
                        'mensaje' => $mensaje,
                    ];

                    continue;
                }

                $errores[] = [
                    'grupo_clave' => $clave,
                    'mensaje' => $mensaje,
                ];
            }
        }

        if ($ok === [] && $errores !== []) {
            throw new InvalidArgumentException($errores[0]['mensaje']);
        }

        return ['ok' => $ok, 'omitidos' => $omitidos, 'errores' => $errores];
    }

    /**
     * @return array<string, mixed>
     */
    public function previewAnularCierreRango(int $empresaId, string $fechaDesde, string $fechaHasta): array
    {
        [$desde, $hasta] = $this->normalizarRangoFechas($fechaDesde, $fechaHasta);
        $this->assertSinCierresPosterioresAlRango($empresaId, $hasta);

        $rendiciones = $this->listarCerradasEnRango($empresaId, $desde, $hasta);
        $gruposRaw = CierreRendicionBingoGrupoSupport::agrupar(
            new EloquentCollection($rendiciones->all()),
        );
        $gruposRaw = array_values(array_filter(
            $gruposRaw,
            static fn (array $g) => ($g['estado_grupo'] ?? '') === CierreRendicionBingoGrupoSupport::ESTADO_CERRADA,
        ));

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
            $asientoIds = [];
            foreach ($rends as $r) {
                $monto = round((float) ($r->total_cartones ?? 0), 2);
                $montoGrupo = round($montoGrupo + $monto, 2);
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
                $filas[] = [
                    'id' => (int) $r->id,
                    'codigo' => (string) ($r->codigo ?? ''),
                    'total_cobrado' => $monto,
                    'fecharendicion_fmt' => $r->fecharendicion?->format('d/m/Y H:i'),
                    'asiento_numero' => (string) ($r->asiento?->numeroasiento ?? ''),
                    'factura_nro' => (int) ($r->factura_nro ?? 0),
                ];
            }
            $asientoIds = array_values(array_unique($asientoIds));
            $grupos[] = [
                'clave' => (string) ($grupo['clave'] ?? ''),
                'fecha_dia' => (string) ($grupo['fecha_dia'] ?? ''),
                'fecha_dia_fmt' => (string) ($grupo['fecha_dia_fmt'] ?? ''),
                'puntoventa_label' => (string) ($grupo['factura_label'] ?? 'Cierre diario'),
                'cantidad_rendiciones' => $rends->count(),
                'total_cobrado' => $montoGrupo,
                'asiento_ids' => $asientoIds,
                'asiento_numero' => (string) ($grupo['asiento_numero'] ?? ''),
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
     *   ok: list<array{grupo_clave: string, fecha_dia: string, asiento_ids: list<int>, numeros_asiento: list<string>, rendicion_ids: list<int>}>,
     *   omitidos: list<array{grupo_clave: string, mensaje: string}>,
     *   errores: list<array{grupo_clave: string, mensaje: string}>
     * }
     */
    public function anularCierreRango(int $empresaId, string $fechaDesde, string $fechaHasta): array
    {
        [$desde, $hasta] = $this->normalizarRangoFechas($fechaDesde, $fechaHasta);

        return CierreRendicionBingoCierreLock::conExclusividadEmpresa(
            $empresaId,
            fn () => $this->anularCierreRangoExclusivo($empresaId, $desde, $hasta),
            false,
        );
    }

    /**
     * @return array{
     *   ok: list<array{grupo_clave: string, fecha_dia: string, asiento_ids: list<int>, numeros_asiento: list<string>, rendicion_ids: list<int>}>,
     *   omitidos: list<array{grupo_clave: string, mensaje: string}>,
     *   errores: list<array{grupo_clave: string, mensaje: string}>
     * }
     */
    private function anularCierreRangoExclusivo(int $empresaId, string $desde, string $hasta): array
    {
        $this->assertSinCierresPosterioresAlRango($empresaId, $hasta);

        $rendiciones = $this->listarCerradasEnRango($empresaId, $desde, $hasta);
        if ($rendiciones->isEmpty()) {
            throw new InvalidArgumentException('No hay cierres contables de bingo en el rango indicado.');
        }

        $grupos = CierreRendicionBingoGrupoSupport::agrupar(
            new EloquentCollection($rendiciones->all()),
        );
        $grupos = array_values(array_filter(
            $grupos,
            static fn (array $g) => ($g['estado_grupo'] ?? '') === CierreRendicionBingoGrupoSupport::ESTADO_CERRADA,
        ));
        usort($grupos, static function (array $a, array $b): int {
            return strcmp((string) ($b['fecha_dia'] ?? ''), (string) ($a['fecha_dia'] ?? ''));
        });

        $ok = [];
        $omitidos = [];
        $errores = [];

        foreach ($grupos as $grupo) {
            $clave = (string) ($grupo['clave'] ?? '');
            $fechaDia = (string) ($grupo['fecha_dia'] ?? '');
            try {
                $resultado = $this->anularCierreGrupoExclusivo($empresaId, $fechaDia);
                $ok[] = [
                    'grupo_clave' => $clave,
                    'fecha_dia' => $resultado['fecha_dia'],
                    'asiento_ids' => $resultado['asiento_ids'],
                    'numeros_asiento' => $resultado['numeros_asiento'],
                    'rendicion_ids' => $resultado['rendicion_ids'],
                ];
            } catch (\Throwable $e) {
                $mensaje = $e->getMessage();
                if (str_contains($mensaje, 'no tiene cierre contable')
                    || str_contains($mensaje, 'No se encontraron rendiciones')) {
                    $omitidos[] = [
                        'grupo_clave' => $clave,
                        'mensaje' => $mensaje,
                    ];

                    continue;
                }

                $errores[] = [
                    'grupo_clave' => $clave,
                    'mensaje' => $mensaje,
                ];
            }
        }

        if ($ok === [] && $errores !== []) {
            throw new InvalidArgumentException($errores[0]['mensaje']);
        }

        return ['ok' => $ok, 'omitidos' => $omitidos, 'errores' => $errores];
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
        $piso = CierreRendicionBingoConfigSupport::correlatividadDesde();
        if ($piso !== null && $fecha < $piso) {
            throw new InvalidArgumentException(
                'El cierre contable bingo ERP corre desde '
                .Carbon::parse($piso)->format('d/m/Y')
                .'. Las jornadas anteriores viven en Anita.',
            );
        }

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
     * @return Collection<int, RendicionBingoCaja>
     */
    private function listarCerradasEnRango(int $empresaId, string $desde, string $hasta): Collection
    {
        return $this->queryCerradasEmpresa($empresaId)
            ->with(['turnoOperativo.turno:id,nombre', 'asiento:id,numeroasiento,fecha'])
            ->whereDate('rendicion_bingo_caja.fecha_jornada', '>=', $desde)
            ->whereDate('rendicion_bingo_caja.fecha_jornada', '<=', $hasta)
            ->orderBy('fecha_jornada')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Builder<RendicionBingoCaja>
     */
    private function queryCerradasEmpresa(int $empresaId): Builder
    {
        $q = RendicionBingoCaja::query()->where('empresa_id', $empresaId);
        CierreRendicionBingoListadoFiltros::aplicarEstadoCierre($q, [
            'estado_cierre' => CierreRendicionBingoListadoFiltros::ESTADO_CERRADA,
        ]);

        return $q;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function normalizarRangoFechas(string $fechaDesde, string $fechaHasta): array
    {
        $desde = Carbon::parse($fechaDesde)->toDateString();
        $hasta = Carbon::parse($fechaHasta)->toDateString();
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [$desde, $hasta];
    }

    private function assertSinCierresPosterioresAlRango(int $empresaId, string $hasta): void
    {
        $posterior = $this->queryCerradasEmpresa($empresaId)
            ->whereDate('rendicion_bingo_caja.fecha_jornada', '>', $hasta)
            ->min(DB::raw(SqlDialectSupport::fecha('rendicion_bingo_caja.fecha_jornada')));
        if ($posterior === null || trim((string) $posterior) === '') {
            return;
        }

        throw new InvalidArgumentException(
            'Hay jornadas cerradas posteriores al rango (desde '
            .Carbon::parse((string) $posterior)->format('d/m/Y')
            .'). Extienda el «hasta» hasta la última jornada cerrada: anular borra el pozo acumulado desde la primera fecha del rango.',
        );
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
