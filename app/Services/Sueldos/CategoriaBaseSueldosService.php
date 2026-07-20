<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Categoria_Base_Sueldos;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Gestión de las bases de cálculo de una categoría con manejo de vigencia
 * (versiones por fecha, como los precios de venta) e histórico consultable.
 */
class CategoriaBaseSueldosService
{
    /**
     * Formato de valor para mostrar: miles con punto, decimales con coma,
     * recortando decimales en cero (3000 => "3.000"; 12,3 => "12,3").
     */
    public static function formatoValor($valor): string
    {
        $s = number_format((float) $valor, 4, ',', '.');
        $s = preg_replace('/(,\d*?)0+$/', '$1', (string) $s);

        return rtrim((string) $s, ',');
    }

    /**
     * Set vigente de bases para una categoría a una fecha de referencia:
     * por cada nombrebase, la fila con MAX(fecha_vigencia) <= fecha, desempate por MAX(id).
     *
     * @return array<int, array<string, mixed>> indexado por nombrebase_id
     */
    public function basesVigentes(int $categoriaId, ?string $fechaReferencia = null): array
    {
        $fechaRef = $fechaReferencia ?: Carbon::today()->toDateString();

        $rows = DB::table('categoria_base_sueldos as cb')
            ->select([
                'cb.id', 'cb.categoria_id', 'cb.nombrebase_id', 'cb.valor',
                'cb.valor_anterior', 'cb.fecha_vigencia', 'cb.usuario_id',
                'nb.codigo as nombrebase_codigo', 'nb.descripcion as nombrebase_descripcion',
            ])
            ->join('nombrebase_sueldos as nb', 'nb.id', '=', 'cb.nombrebase_id')
            ->where('cb.categoria_id', $categoriaId)
            ->where('cb.fecha_vigencia', '<=', $fechaRef)
            ->orderBy('cb.nombrebase_id')
            ->orderByDesc('cb.fecha_vigencia')
            ->orderByDesc('cb.id')
            ->get();

        $vigentes = [];
        foreach ($rows as $row) {
            $nb = (int) $row->nombrebase_id;
            if (isset($vigentes[$nb])) {
                continue; // ya tomamos la más nueva por el orden desc
            }
            $vigentes[$nb] = [
                'id' => (int) $row->id,
                'nombrebase_id' => $nb,
                'nombrebase_codigo' => (int) $row->nombrebase_codigo,
                'nombrebase_descripcion' => (string) $row->nombrebase_descripcion,
                'valor' => (float) $row->valor,
                'valor_anterior' => $row->valor_anterior !== null ? (float) $row->valor_anterior : null,
                'fecha_vigencia' => Carbon::parse($row->fecha_vigencia)->format('Y-m-d'),
                'fecha_vigencia_fmt' => Carbon::parse($row->fecha_vigencia)->format('d/m/Y'),
            ];
        }

        return $vigentes;
    }

    /**
     * Bases vigentes (a una fecha de referencia) para un conjunto de categorías,
     * en una sola consulta. Pensado para listados/exports (consulta rápida).
     *
     * @param  list<int>  $categoriaIds
     * @return array<int, list<array<string, mixed>>> indexado por categoria_id, en orden de nombrebase
     */
    public function basesVigentesParaCategorias(array $categoriaIds, ?string $fechaReferencia = null): array
    {
        $categoriaIds = array_values(array_unique(array_map('intval', $categoriaIds)));
        if ($categoriaIds === []) {
            return [];
        }

        $fechaRef = $fechaReferencia ?: Carbon::today()->toDateString();

        $rows = DB::table('categoria_base_sueldos as cb')
            ->select([
                'cb.id', 'cb.categoria_id', 'cb.nombrebase_id', 'cb.valor',
                'cb.valor_anterior', 'cb.fecha_vigencia',
                'nb.codigo as nombrebase_codigo', 'nb.descripcion as nombrebase_descripcion',
            ])
            ->join('nombrebase_sueldos as nb', 'nb.id', '=', 'cb.nombrebase_id')
            ->whereIn('cb.categoria_id', $categoriaIds)
            ->where('cb.fecha_vigencia', '<=', $fechaRef)
            ->orderBy('cb.categoria_id')
            ->orderBy('cb.nombrebase_id')
            ->orderByDesc('cb.fecha_vigencia')
            ->orderByDesc('cb.id')
            ->get();

        $mapa = [];
        $vistos = [];
        foreach ($rows as $row) {
            $cat = (int) $row->categoria_id;
            $nb = (int) $row->nombrebase_id;
            if (isset($vistos[$cat][$nb])) {
                continue;
            }
            $vistos[$cat][$nb] = true;
            $mapa[$cat][] = [
                'nombrebase_id' => $nb,
                'nombrebase_codigo' => (int) $row->nombrebase_codigo,
                'nombrebase_descripcion' => (string) $row->nombrebase_descripcion,
                'valor' => (float) $row->valor,
                'valor_fmt' => self::formatoValor($row->valor),
                'fecha_vigencia' => Carbon::parse($row->fecha_vigencia)->format('Y-m-d'),
                'fecha_vigencia_fmt' => Carbon::parse($row->fecha_vigencia)->format('d/m/Y'),
            ];
        }

        return $mapa;
    }

    /**
     * Resumen por base para la grilla de la solapa: una fila por nombrebase que tenga
     * alguna versión, con el valor vigente a la fecha de referencia y la próxima
     * vigencia programada (si hay versiones con fecha futura).
     *
     * @return list<array<string, mixed>>
     */
    public function resumenBasesGrilla(int $categoriaId, ?string $fechaReferencia = null): array
    {
        $fechaRef = $fechaReferencia ?: Carbon::today()->toDateString();

        $rows = DB::table('categoria_base_sueldos as cb')
            ->select([
                'cb.id', 'cb.nombrebase_id', 'cb.valor', 'cb.fecha_vigencia',
                'nb.codigo as nombrebase_codigo', 'nb.descripcion as nombrebase_descripcion',
            ])
            ->join('nombrebase_sueldos as nb', 'nb.id', '=', 'cb.nombrebase_id')
            ->where('cb.categoria_id', $categoriaId)
            ->orderBy('nb.codigo')
            ->orderBy('cb.nombrebase_id')
            ->orderByDesc('cb.fecha_vigencia')
            ->orderByDesc('cb.id')
            ->get();

        $porBase = [];
        foreach ($rows as $row) {
            $porBase[(int) $row->nombrebase_id][] = $row;
        }

        $result = [];
        foreach ($porBase as $nbId => $versiones) {
            $vigente = null;
            foreach ($versiones as $v) {
                if (Carbon::parse($v->fecha_vigencia)->format('Y-m-d') <= $fechaRef) {
                    $vigente = $v; // primera <= ref (orden desc) = última vigente
                    break;
                }
            }

            $futuras = [];
            foreach ($versiones as $v) {
                if (Carbon::parse($v->fecha_vigencia)->format('Y-m-d') > $fechaRef) {
                    $futuras[] = $v;
                }
            }
            $proxima = $futuras !== [] ? end($futuras) : null; // orden desc => end = menor fecha futura

            $primera = $versiones[0];
            $valorEditar = $vigente ?: ($proxima ?: $primera);

            $result[] = [
                'nombrebase_id' => $nbId,
                'nombrebase_codigo' => (int) $primera->nombrebase_codigo,
                'nombrebase_descripcion' => (string) $primera->nombrebase_descripcion,
                'id' => (int) ($vigente->id ?? ($proxima->id ?? $primera->id)),
                'tiene_vigente' => $vigente !== null,
                'valor' => $vigente !== null ? (float) $vigente->valor : null,
                'valor_fmt' => $vigente !== null ? self::formatoValor($vigente->valor) : null,
                'fecha_vigencia' => $vigente !== null ? Carbon::parse($vigente->fecha_vigencia)->format('Y-m-d') : null,
                'fecha_vigencia_fmt' => $vigente !== null ? Carbon::parse($vigente->fecha_vigencia)->format('d/m/Y') : null,
                'editar_valor' => (float) $valorEditar->valor,
                'futuras_count' => count($futuras),
                'proxima' => $proxima !== null ? [
                    'valor' => (float) $proxima->valor,
                    'valor_fmt' => self::formatoValor($proxima->valor),
                    'fecha_vigencia' => Carbon::parse($proxima->fecha_vigencia)->format('Y-m-d'),
                    'fecha_vigencia_fmt' => Carbon::parse($proxima->fecha_vigencia)->format('d/m/Y'),
                ] : null,
            ];
        }

        return $result;
    }

    /**
     * Histórico completo de versiones de bases de una categoría (todas las fechas),
     * marcando la versión vigente por nombrebase a la fecha de referencia.
     *
     * @return list<array<string, mixed>>
     */
    public function historial(int $categoriaId, ?int $nombrebaseId = null, ?string $fechaReferencia = null): array
    {
        $fechaRef = $fechaReferencia ?: Carbon::today()->toDateString();

        $q = DB::table('categoria_base_sueldos as cb')
            ->select([
                'cb.id', 'cb.nombrebase_id', 'cb.valor', 'cb.valor_anterior',
                'cb.fecha_vigencia', 'cb.created_at', 'cb.updated_at',
                'nb.codigo as nombrebase_codigo', 'nb.descripcion as nombrebase_descripcion',
                'u.nombre as usuario_nombre',
            ])
            ->join('nombrebase_sueldos as nb', 'nb.id', '=', 'cb.nombrebase_id')
            ->leftJoin('usuario as u', 'u.id', '=', 'cb.usuario_id')
            ->where('cb.categoria_id', $categoriaId);

        if ($nombrebaseId !== null && $nombrebaseId > 0) {
            $q->where('cb.nombrebase_id', $nombrebaseId);
        }

        $rows = $q->orderBy('nb.codigo')
            ->orderByDesc('cb.fecha_vigencia')
            ->orderByDesc('cb.id')
            ->get();

        // Determinar la fila vigente por nombrebase.
        $vigentePorBase = [];
        foreach ($rows as $row) {
            $nb = (int) $row->nombrebase_id;
            $fv = Carbon::parse($row->fecha_vigencia)->format('Y-m-d');
            if ($fv > $fechaRef) {
                continue;
            }
            if (! isset($vigentePorBase[$nb])) {
                $vigentePorBase[$nb] = (int) $row->id; // primera por orden desc = vigente
            }
        }

        $filas = [];
        foreach ($rows as $row) {
            $nb = (int) $row->nombrebase_id;
            $filas[] = [
                'id' => (int) $row->id,
                'nombrebase_id' => $nb,
                'nombrebase_codigo' => (int) $row->nombrebase_codigo,
                'nombrebase_descripcion' => (string) $row->nombrebase_descripcion,
                'valor' => (float) $row->valor,
                'valor_fmt' => self::formatoValor($row->valor),
                'valor_anterior' => $row->valor_anterior !== null ? (float) $row->valor_anterior : null,
                'valor_anterior_fmt' => $row->valor_anterior !== null ? self::formatoValor($row->valor_anterior) : '',
                'fecha_vigencia' => Carbon::parse($row->fecha_vigencia)->format('Y-m-d'),
                'fecha_vigencia_fmt' => Carbon::parse($row->fecha_vigencia)->format('d/m/Y'),
                'usuario_nombre' => (string) ($row->usuario_nombre ?? ''),
                'registrado_fmt' => $row->created_at ? Carbon::parse($row->created_at)->format('d/m/Y H:i') : '',
                'es_vigente' => isset($vigentePorBase[$nb]) && $vigentePorBase[$nb] === (int) $row->id,
                'es_futura' => Carbon::parse($row->fecha_vigencia)->format('Y-m-d') > $fechaRef,
            ];
        }

        return $filas;
    }

    /**
     * Guarda una base: si la fecha de vigencia coincide con una versión existente
     * (misma categoría+nombrebase+fecha) actualiza en el lugar; si no, crea versión nueva
     * preservando la anterior como histórico.
     *
     * @return array{base: Categoria_Base_Sueldos, creo_version: bool}
     */
    public function guardarBase(
        int $categoriaId,
        int $nombrebaseId,
        float $valor,
        string $fechaVigencia,
        ?int $usuarioId = null
    ): array {
        $fecha = Carbon::parse($fechaVigencia)->format('Y-m-d');

        $existenteMismaFecha = Categoria_Base_Sueldos::query()
            ->where('categoria_id', $categoriaId)
            ->where('nombrebase_id', $nombrebaseId)
            ->whereDate('fecha_vigencia', $fecha)
            ->orderByDesc('id')
            ->first();

        // Valor vigente actual (para trazar como valor_anterior en la versión nueva).
        $vigentes = $this->basesVigentes($categoriaId, Carbon::today()->toDateString());
        $valorVigenteActual = isset($vigentes[$nombrebaseId]) ? (float) $vigentes[$nombrebaseId]['valor'] : null;

        if ($existenteMismaFecha !== null) {
            $existenteMismaFecha->update([
                'valor' => $valor,
                'usuario_id' => $usuarioId,
            ]);

            return ['base' => $existenteMismaFecha->fresh(), 'creo_version' => false];
        }

        $nueva = Categoria_Base_Sueldos::create([
            'categoria_id' => $categoriaId,
            'nombrebase_id' => $nombrebaseId,
            'valor' => $valor,
            'fecha_vigencia' => $fecha,
            'valor_anterior' => $valorVigenteActual,
            'usuario_id' => $usuarioId,
        ]);

        return ['base' => $nueva, 'creo_version' => true];
    }

    /**
     * Actualiza en el lugar una vigencia (fecha + valor) de una versión existente.
     * Rechaza si otra versión de la misma base ya usa esa fecha.
     *
     * @return array{ok: bool, error?: string, base?: Categoria_Base_Sueldos}
     */
    public function actualizarVigencia(int $baseId, float $valor, string $fechaVigencia, ?int $usuarioId = null): array
    {
        $base = Categoria_Base_Sueldos::find($baseId);
        if ($base === null) {
            return ['ok' => false, 'error' => 'no_encontrada'];
        }

        $fecha = Carbon::parse($fechaVigencia)->format('Y-m-d');

        $colision = Categoria_Base_Sueldos::query()
            ->where('categoria_id', $base->categoria_id)
            ->where('nombrebase_id', $base->nombrebase_id)
            ->whereDate('fecha_vigencia', $fecha)
            ->where('id', '!=', $base->id)
            ->exists();
        if ($colision) {
            return ['ok' => false, 'error' => 'fecha_duplicada'];
        }

        $base->update([
            'valor' => $valor,
            'fecha_vigencia' => $fecha,
            'usuario_id' => $usuarioId,
        ]);

        return ['ok' => true, 'base' => $base->fresh()];
    }

    /**
     * Guarda en lote las vigencias de una base desde el modal: crea/actualiza las filas
     * de $items (cada una ['id'?, 'valor', 'fecha']) y elimina las de $eliminarIds, todo
     * en una transacción. Valida que no queden dos vigencias con la misma fecha.
     *
     * @param  list<array{id?: int|string|null, valor: mixed, fecha: string}>  $items
     * @param  list<int>  $eliminarIds
     * @return array{ok: bool, error?: string}
     */
    public function guardarVigenciasLote(int $categoriaId, int $nombrebaseId, array $items, array $eliminarIds, ?int $usuarioId = null): array
    {
        $eliminarIds = array_values(array_unique(array_map('intval', $eliminarIds)));

        $fechasVistas = [];
        $idsItems = [];
        foreach ($items as $it) {
            $fecha = Carbon::parse($it['fecha'])->format('Y-m-d');
            if (isset($fechasVistas[$fecha])) {
                return ['ok' => false, 'error' => 'Hay dos vigencias con la misma fecha ('.Carbon::parse($fecha)->format('d/m/Y').').'];
            }
            $fechasVistas[$fecha] = true;
            if (! empty($it['id'])) {
                $idsItems[] = (int) $it['id'];
            }
        }

        $excluir = array_merge($idsItems, $eliminarIds);
        foreach (array_keys($fechasVistas) as $fecha) {
            $q = Categoria_Base_Sueldos::query()
                ->where('categoria_id', $categoriaId)
                ->where('nombrebase_id', $nombrebaseId)
                ->whereDate('fecha_vigencia', $fecha);
            if ($excluir !== []) {
                $q->whereNotIn('id', $excluir);
            }
            if ($q->exists()) {
                return ['ok' => false, 'error' => 'Ya existe una vigencia con la fecha '.Carbon::parse($fecha)->format('d/m/Y').'.'];
            }
        }

        DB::transaction(function () use ($categoriaId, $nombrebaseId, $items, $eliminarIds, $usuarioId) {
            if ($eliminarIds !== []) {
                Categoria_Base_Sueldos::query()
                    ->whereIn('id', $eliminarIds)
                    ->where('categoria_id', $categoriaId)
                    ->delete();
            }

            foreach ($items as $it) {
                $fecha = Carbon::parse($it['fecha'])->format('Y-m-d');
                $valor = (float) $it['valor'];

                if (! empty($it['id'])) {
                    $base = Categoria_Base_Sueldos::query()
                        ->where('id', (int) $it['id'])
                        ->where('categoria_id', $categoriaId)
                        ->first();
                    if ($base !== null) {
                        $base->update([
                            'valor' => $valor,
                            'fecha_vigencia' => $fecha,
                            'usuario_id' => $usuarioId,
                        ]);
                    }

                    continue;
                }

                Categoria_Base_Sueldos::create([
                    'categoria_id' => $categoriaId,
                    'nombrebase_id' => $nombrebaseId,
                    'valor' => $valor,
                    'fecha_vigencia' => $fecha,
                    'valor_anterior' => null,
                    'usuario_id' => $usuarioId,
                ]);
            }
        });

        return ['ok' => true];
    }

    public function eliminarBase(int $baseId): bool
    {
        $base = Categoria_Base_Sueldos::find($baseId);
        if ($base === null) {
            return false;
        }

        return (bool) $base->delete();
    }

    /**
     * Elimina la base completa de una categoría: todas las vigencias (histórico incluido)
     * de esa combinación categoría + nombre de base. Devuelve la cantidad de filas borradas.
     */
    public function eliminarBaseCompleta(int $categoriaId, int $nombrebaseId): int
    {
        return (int) Categoria_Base_Sueldos::query()
            ->where('categoria_id', $categoriaId)
            ->where('nombrebase_id', $nombrebaseId)
            ->delete();
    }
}
