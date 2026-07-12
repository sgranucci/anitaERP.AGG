<?php

namespace App\Services\Contable;

use App\Models\Caja\Bingo\RendicionBingoCaja;
use App\Models\Contable\Asiento;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Support\Contable\CierreRendicionBingoAsientoSupport;
use App\Support\Contable\CierreRendicionBingoConfigSupport;
use App\Support\Contable\CierreRendicionBingoFbiAnitaSupport;
use App\Support\Contable\CierreRendicionBingoGrupoSupport;
use App\Support\Contable\CierreRendicionBingoListadoFiltros;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Caja\Bingo\RendicionBingoCajaListadoFiltros;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
                throw new RuntimeException('No se generaron asientos contables.');
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
