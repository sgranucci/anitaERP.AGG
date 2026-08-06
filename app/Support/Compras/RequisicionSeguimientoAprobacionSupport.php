<?php

namespace App\Support\Compras;

use App\Models\Compras\Requisicion;
use App\Models\Compras\Requisicion_Estado;
use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Tablero de seguimiento: requisiciones trabadas en el circuito de aprobación.
 *
 * - Pendientes de aprobación (EN ARBOL / PENDIENTE / EN COMPRAS).
 * - Responsable actual (firmantes con movimiento Pendiente, o rol implícito del estado).
 * - Días desde creación y alerta por demora en el nivel actual (umbral configurable, default 48 hs).
 */
final class RequisicionSeguimientoAprobacionSupport
{
    public const PERMISO = 'seguimiento-aprobacion-requisicion';

    public const POR_PAGINA = 25;

    /** @return list<string> */
    public static function nombresEstadosEnCircuito(): array
    {
        $enum = Requisicion_Estado::$enumEstado;
        // K = EN COMPRAS (retome), R = EN ARBOL APROBACION (firma pendiente).
        // No incluye PENDIENTE: suele haber volumen histórico sin firmante activo.
        $valores = ['K', 'R'];
        $nombres = [];
        foreach ($enum as $row) {
            if (in_array($row['valor'], $valores, true)) {
                $nombres[] = $row['nombre'];
            }
        }

        return $nombres;
    }

    public static function umbralAlertaHoras(): int
    {
        $horas = (int) config('requisicion.seguimiento_aprobacion_alerta_horas', 48);

        return $horas > 0 ? $horas : 48;
    }

    public static function nombreEstadoPendienteMovimiento(): string
    {
        return Arbolaprobacion_Movimiento::$enumEstado[0]['nombre']; // Pendiente
    }

    /**
     * @return array{filas: LengthAwarePaginator|Collection<int, object>, total: int, con_alerta: int, umbral_horas: int}
     */
    public static function armarTablero(?int $empresaId = null, bool $paginar = true): array
    {
        $umbral = self::umbralAlertaHoras();
        $base = self::queryBase($empresaId);
        $total = (clone $base)->count();

        if ($paginar) {
            $page = $base->paginate(self::POR_PAGINA)->appends(request()->query());
            $filasRaw = collect($page->items());
        } else {
            $filasRaw = $base->get();
            $page = $filasRaw;
        }

        $ids = $filasRaw->pluck('id')->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->values()->all();
        $pendientesPorRq = self::movimientosPendientesPorRequisicion($ids);

        $ahora = Carbon::now();
        $filas = $filasRaw->map(function ($row) use ($pendientesPorRq, $umbral, $ahora) {
            return self::enriquecerFila($row, $pendientesPorRq[(int) $row->id] ?? collect(), $umbral, $ahora);
        });

        if ($paginar && $page instanceof LengthAwarePaginator) {
            $page->setCollection($filas);
            $filasOut = $page;
        } else {
            $filasOut = $filas->values();
        }

        $conAlerta = self::contarConAlerta($empresaId, $umbral);

        return [
            'filas' => $filasOut,
            'total' => $total,
            'con_alerta' => $conAlerta,
            'umbral_horas' => $umbral,
        ];
    }

    /**
     * @param  Builder<\App\Models\Compras\Requisicion>  $query
     */
    public static function aplicarFiltroEmpresa(Builder $query, ?int $empresaId): void
    {
        if ($empresaId !== null && $empresaId > 0) {
            $query->where('requisicion.empresa_id', $empresaId);
        }
    }

    /** @return Builder<\App\Models\Compras\Requisicion> */
    private static function queryBase(?int $empresaId): Builder
    {
        $estados = self::nombresEstadosEnCircuito();

        $query = Requisicion::query()
            ->from('requisicion')
            ->select([
                'requisicion.id',
                'requisicion.numerorequisicion',
                'requisicion.fecha',
                'requisicion.created_at',
                'requisicion.estado',
                'requisicion.empresa_id',
                'requisicion.centrocosto_id',
                'requisicion.creousuario_id',
                'empresa.nombre as nombreempresa',
                'centrocosto.nombre as nombrecentrocosto',
                'usuario.nombre as nombresolicitante',
            ])
            ->leftJoin('empresa', 'empresa.id', '=', 'requisicion.empresa_id')
            ->leftJoin('centrocosto', 'centrocosto.id', '=', 'requisicion.centrocosto_id')
            ->leftJoin('usuario', 'usuario.id', '=', 'requisicion.creousuario_id')
            ->whereIn('requisicion.estado', $estados)
            ->orderBy('requisicion.created_at')
            ->orderBy('requisicion.id');

        RequisicionVisibilidadSupport::aplicarFiltroListado($query);
        self::aplicarFiltroEmpresa($query, $empresaId);

        return $query;
    }

    /**
     * @param  list<int>  $requisicionIds
     * @return Collection<int, Collection<int, object>>
     */
    private static function movimientosPendientesPorRequisicion(array $requisicionIds): Collection
    {
        if ($requisicionIds === []) {
            return collect();
        }

        $estadoPendiente = self::nombreEstadoPendienteMovimiento();

        $rows = Arbolaprobacion_Movimiento::query()
            ->from('arbolaprobacion_movimiento')
            ->select([
                'arbolaprobacion_movimiento.id',
                'arbolaprobacion_movimiento.requisicion_id',
                'arbolaprobacion_movimiento.nivel',
                'arbolaprobacion_movimiento.fechaenvio',
                'arbolaprobacion_movimiento.destinatariousuario_id',
                'usuario.nombre as nombreresponsable',
                'usuario.email as emailresponsable',
            ])
            ->leftJoin('usuario', 'usuario.id', '=', 'arbolaprobacion_movimiento.destinatariousuario_id')
            ->whereIn('arbolaprobacion_movimiento.requisicion_id', $requisicionIds)
            ->where('arbolaprobacion_movimiento.estado', $estadoPendiente)
            ->whereNull('arbolaprobacion_movimiento.deleted_at')
            ->orderBy('arbolaprobacion_movimiento.nivel')
            ->orderBy('arbolaprobacion_movimiento.id')
            ->get();

        return $rows->groupBy(fn ($r) => (int) $r->requisicion_id);
    }

    /**
     * @param  Collection<int, object>  $movimientos
     */
    private static function enriquecerFila(object $row, Collection $movimientos, int $umbralHoras, Carbon $ahora): object
    {
        $creacion = self::resolverFechaCreacion($row);
        $diasDesdeCreacion = $creacion ? (int) $creacion->diffInDays($ahora) : 0;

        $nivelActual = null;
        $fechaEnvioNivel = null;
        $responsables = [];

        if ($movimientos->isNotEmpty()) {
            $nivelActual = (int) $movimientos->first()->nivel;
            $delNivel = $movimientos->filter(fn ($m) => (int) $m->nivel === $nivelActual);
            foreach ($delNivel as $mov) {
                $nombre = trim((string) ($mov->nombreresponsable ?? ''));
                if ($nombre !== '' && ! in_array($nombre, $responsables, true)) {
                    $responsables[] = $nombre;
                }
                $envio = self::parseFecha($mov->fechaenvio ?? null);
                if ($envio !== null && ($fechaEnvioNivel === null || $envio->lt($fechaEnvioNivel))) {
                    $fechaEnvioNivel = $envio;
                }
            }
        }

        $etiquetaResponsable = self::etiquetaResponsable(
            (string) ($row->estado ?? ''),
            $responsables
        );

        $referenciaDemora = $fechaEnvioNivel ?? $creacion;
        $horasEnNivel = $referenciaDemora ? (int) $referenciaDemora->diffInHours($ahora) : 0;
        $alerta = $horasEnNivel >= $umbralHoras;

        $row->dias_desde_creacion = $diasDesdeCreacion;
        $row->horas_en_nivel = $horasEnNivel;
        $row->nivel_actual = $nivelActual;
        $row->fecha_envio_nivel = $fechaEnvioNivel;
        $row->responsables = $responsables;
        $row->responsable_etiqueta = $etiquetaResponsable;
        $row->alerta_demora = $alerta;
        $row->fecha_creacion = $creacion;

        return $row;
    }

    /** @param  list<string>  $responsables */
    private static function etiquetaResponsable(string $estado, array $responsables): string
    {
        if ($responsables !== []) {
            return implode(', ', $responsables);
        }

        $enCompras = Requisicion_Estado::$enumEstado[array_search('K', array_column(Requisicion_Estado::$enumEstado, 'valor'), true)]['nombre'] ?? 'EN COMPRAS';
        $pendiente = Requisicion_Estado::$enumEstado[array_search('P', array_column(Requisicion_Estado::$enumEstado, 'valor'), true)]['nombre'] ?? 'PENDIENTE';

        if ($estado === $enCompras) {
            return 'Oficina de compras (retome al árbol)';
        }
        if ($estado === $pendiente) {
            return 'Pendiente de ingreso al árbol';
        }

        return 'Sin firmante asignado';
    }

    private static function resolverFechaCreacion(object $row): ?Carbon
    {
        $created = self::parseFecha($row->created_at ?? null);
        if ($created !== null) {
            return $created;
        }

        return self::parseFecha($row->fecha ?? null);
    }

    private static function parseFecha(mixed $valor): ?Carbon
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if ($valor instanceof Carbon) {
            return $valor;
        }
        try {
            return Carbon::parse($valor);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function contarConAlerta(?int $empresaId, int $umbralHoras): int
    {
        $estadoPendiente = self::nombreEstadoPendienteMovimiento();
        $estados = self::nombresEstadosEnCircuito();
        $limite = Carbon::now()->subHours($umbralHoras);

        // Alerta: movimiento pendiente con fechaenvio antigua, o RQ sin movimiento pendiente
        // cuya creación supera el umbral.
        $queryRq = Requisicion::query()
            ->from('requisicion')
            ->whereIn('requisicion.estado', $estados);
        RequisicionVisibilidadSupport::aplicarFiltroListado($queryRq);
        self::aplicarFiltroEmpresa($queryRq, $empresaId);

        $ids = (clone $queryRq)->pluck('requisicion.id')->map(fn ($id) => (int) $id)->all();
        if ($ids === []) {
            return 0;
        }

        $conMovimientoAlerta = Arbolaprobacion_Movimiento::query()
            ->whereIn('requisicion_id', $ids)
            ->where('estado', $estadoPendiente)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($limite) {
                $q->where(function ($q2) use ($limite) {
                    $q2->whereNotNull('fechaenvio')
                        ->where('fechaenvio', '<=', $limite);
                })->orWhere(function ($q2) use ($limite) {
                    $q2->whereNull('fechaenvio')
                        ->where('created_at', '<=', $limite);
                });
            })
            ->distinct()
            ->count('requisicion_id');

        $idsConPendiente = Arbolaprobacion_Movimiento::query()
            ->whereIn('requisicion_id', $ids)
            ->where('estado', $estadoPendiente)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('requisicion_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $sinMovimientoAlerta = (clone $queryRq)
            ->whereNotIn('requisicion.id', $idsConPendiente ?: [0])
            ->where(function ($q) use ($limite) {
                $q->where('requisicion.created_at', '<=', $limite)
                    ->orWhere(function ($q2) use ($limite) {
                        $q2->whereNull('requisicion.created_at')
                            ->where('requisicion.fecha', '<=', $limite->toDateString());
                    });
            })
            ->count();

        return $conMovimientoAlerta + $sinMovimientoAlerta;
    }
}
