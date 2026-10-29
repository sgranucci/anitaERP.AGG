<?php

namespace App\Support\Stock;

use App\Support\Database\SqlDialectSupport;
use App\Support\Produccion\OrdentrabajoTareaFechaSupport;
use Illuminate\Support\Facades\DB;

/**
 * Leyendas de la columna Situación del Excel Stock por OT (Ferli).
 *
 * Regla de negocio: al stock solo van OT del cliente STOCK (`consprod.CLIENTE_STOCK`).
 * OT de clientes comerciales no entran al reporte ni reciben ALTAP al terminar.
 *
 * | Situación | Criterio |
 * |---|---|
 * | PENDIENTE DE FABRICACION | Solo tarea pendiente (sin avance de planta ni cierre) |
 * | EN PRODUCCION | Avance de planta sin cierre y sin EMPAQUE finalizado |
 * | ENTREGA INMEDIATA | Cierre (Terminada / Terminada stock / Facturada), EMPAQUE con fecha de fin, o lote listo en depósito |
 */
final class ReporteStockOtSituacionSupport
{
    public const ENTREGA_INMEDIATA = 'ENTREGA INMEDIATA';

    public const EN_PRODUCCION = 'EN PRODUCCION';

    public const PENDIENTE_DE_FABRICACION = 'PENDIENTE DE FABRICACION';

    public static function clienteStockId(): int
    {
        return (int) config('consprod.CLIENTE_STOCK');
    }

    public static function esClienteStock(mixed $clienteId): bool
    {
        $id = (int) $clienteId;

        return $id > 0 && $id === self::clienteStockId();
    }

    public static function idTareaPendienteFabricacion(): int
    {
        return (int) config('consprod.TAREA_PENDIENTE_FABRICACION');
    }

    public static function idTareaEmpaque(): int
    {
        return (int) config('consprod.TAREA_EMPAQUE');
    }

    /**
     * @return list<int>
     */
    public static function idsTareasCierre(): array
    {
        return array_values(array_unique(array_filter([
            (int) config('consprod.TAREA_TERMINADA'),
            (int) config('consprod.TAREA_TERMINADA_STOCK'),
            (int) config('consprod.TAREA_FACTURADA'),
        ])));
    }

    /**
     * Tareas que no cuentan como avance de fabricación en planta.
     *
     * @return list<int>
     */
    public static function idsTareasSinAvanceFabricacion(): array
    {
        return array_values(array_unique(array_filter(array_merge(
            [self::idTareaPendienteFabricacion()],
            self::idsTareasCierre()
        ))));
    }

    public static function esTareaCierre(int $tareaId): bool
    {
        return in_array($tareaId, self::idsTareasCierre(), true);
    }

    public static function esTipootStock(mixed $tipoot): bool
    {
        return strtoupper(trim((string) $tipoot)) === 'S';
    }

    /**
     * Situación de cada OT según sus tareas (misma lectura que Stock por OT).
     *
     * @param  list<int>  $ordentrabajoIds
     * @return array<int, array{situacion: string, en_produccion: bool}>
     */
    public static function situacionesPorOrdentrabajoIds(array $ordentrabajoIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ordentrabajoIds))));
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = self::desdeTareaIds([]);
        }
        if ($ids === []) {
            return $out;
        }

        $rows = DB::table('ordentrabajo_tarea')
            ->whereIn('ordentrabajo_id', $ids)
            ->get(['ordentrabajo_id', 'tarea_id', 'hastafecha']);
        $porOt = [];
        foreach ($rows as $row) {
            $porOt[(int) $row->ordentrabajo_id][] = $row;
        }
        foreach ($porOt as $otId => $tareas) {
            $out[$otId] = self::desdeTareas($tareas);
        }

        return $out;
    }

    /**
     * EMPAQUE cerrado (fecha de fin real en todas sus filas).
     * Referencia: OT 29885, en stock con empaque terminado.
     *
     * @param  iterable<int, array{tarea_id?: int, hastafecha?: mixed}|object|int|string>  $tareas
     */
    public static function empaqueFinalizado(iterable $tareas, ?int $empaqueId = null): bool
    {
        $empaqueId ??= self::idTareaEmpaque();
        if ($empaqueId <= 0) {
            return false;
        }

        $empaques = 0;
        $abiertos = 0;
        foreach ($tareas as $tarea) {
            [$id, $hasta] = self::tareaIdYHastafecha($tarea);
            if ($id !== $empaqueId) {
                continue;
            }
            $empaques++;
            if (! OrdentrabajoTareaFechaSupport::tieneValor($hasta)) {
                $abiertos++;
            }
        }

        return $empaques > 0 && $abiertos === 0;
    }

    /**
     * @param  iterable<int, array{tarea_id?: int, hastafecha?: mixed}|object|int|string>  $tareas
     * @return array{situacion: string, en_produccion: bool}
     */
    public static function desdeTareas(iterable $tareas, ?int $empaqueId = null): array
    {
        $lista = [];
        $ids = [];
        foreach ($tareas as $tarea) {
            $lista[] = $tarea;
            $ids[] = self::tareaIdYHastafecha($tarea)[0];
        }

        if (self::empaqueFinalizado($lista, $empaqueId)) {
            return [
                'situacion' => self::ENTREGA_INMEDIATA,
                'en_produccion' => false,
            ];
        }

        return self::desdeTareaIds($ids);
    }

    /**
     * El overlay de planta no incluye una OT cuyo EMPAQUE ya está cerrado.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function aplicarExcluirEmpaqueFinalizado($query, string $otIdColumn): void
    {
        $empaqueId = self::idTareaEmpaque();
        if ($empaqueId <= 0 || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*\.[A-Za-z_][A-Za-z0-9_]*$/', $otIdColumn)) {
            return;
        }

        $fin = SqlDialectSupport::castTexto('otte.hastafecha');
        $query->where(function ($q) use ($empaqueId, $otIdColumn, $fin) {
            $q->whereNotExists(function ($sub) use ($empaqueId, $otIdColumn, $fin) {
                $sub->select(DB::raw('1'))
                    ->from('ordentrabajo_tarea as otte')
                    ->whereColumn('otte.ordentrabajo_id', $otIdColumn)
                    ->where('otte.tarea_id', $empaqueId)
                    ->whereNotNull('otte.hastafecha')
                    ->whereRaw($fin." NOT LIKE '0000%'");
            })->orWhereExists(function ($sub) use ($empaqueId, $otIdColumn) {
                $sub->select(DB::raw('1'))
                    ->from('ordentrabajo_tarea as otte')
                    ->whereColumn('otte.ordentrabajo_id', $otIdColumn)
                    ->where('otte.tarea_id', $empaqueId);
                OrdentrabajoTareaFechaSupport::aplicarSinFechaFin($sub, 'otte.hastafecha');
            });
        });
    }

    /**
     * @return array{0: int, 1: mixed}
     */
    private static function tareaIdYHastafecha(mixed $tarea): array
    {
        if (is_int($tarea) || is_string($tarea)) {
            return [(int) $tarea, null];
        }
        if (is_array($tarea)) {
            return [(int) ($tarea['tarea_id'] ?? 0), $tarea['hastafecha'] ?? null];
        }

        return [(int) ($tarea->tarea_id ?? 0), $tarea->hastafecha ?? null];
    }

    public static function desdeTareaIds(iterable $tareaIds): array
    {
        $ids = [];
        foreach ($tareaIds as $id) {
            $ids[] = (int) $id;
        }
        if ($ids === []) {
            return [
                'situacion' => self::ENTREGA_INMEDIATA,
                'en_produccion' => false,
            ];
        }
        foreach ($ids as $id) {
            if (self::esTareaCierre($id)) {
                return [
                    'situacion' => self::ENTREGA_INMEDIATA,
                    'en_produccion' => false,
                ];
            }
        }

        $sinAvance = self::idsTareasSinAvanceFabricacion();
        foreach ($ids as $id) {
            if (! in_array($id, $sinAvance, true)) {
                return [
                    'situacion' => self::EN_PRODUCCION,
                    'en_produccion' => true,
                ];
            }
        }

        // Solo pendiente (u otras sin avance): no mentir ENTREGA / EN PRODUCCION.
        return [
            'situacion' => self::PENDIENTE_DE_FABRICACION,
            'en_produccion' => false,
        ];
    }

    /**
     * TODAS: el reporte completo.
     * ENTREGA: solo lo que se puede entregar (filas que el Excel no pinta de rojo).
     * PRODUCCION: solo lo rojo (en producción y pendiente de fabricación).
     */
    public static function pasaFiltroEstadoOt(string $estadoOt, string $situacion, bool $enProduccion): bool
    {
        return match ($estadoOt) {
            'ENTREGA' => self::listaParaEntrega($situacion, $enProduccion),
            'PRODUCCION' => self::colorearRojoEnExcel($situacion, $enProduccion),
            default => true,
        };
    }

    /**
     * Filas en rojo en el Excel Stock por OT: no están listas para entrega inmediata.
     * Incluye EN PRODUCCION y PENDIENTE DE FABRICACION (misma señal visual).
     */
    public static function colorearRojoEnExcel(string $situacion, bool $enProduccion): bool
    {
        return $enProduccion
            || $situacion === self::EN_PRODUCCION
            || $situacion === self::PENDIENTE_DE_FABRICACION;
    }

    /**
     * Lista para entregar o consumir: la consulta Stock por OT la muestra como entrega inmediata.
     * El aviso reutilizable está en OtListaEntregaSupport.
     */
    public static function listaParaEntrega(string $situacion, bool $enProduccion): bool
    {
        return ! self::colorearRojoEnExcel($situacion, $enProduccion);
    }

    public static function esLoteImportado(mixed $lote): bool
    {
        $lote = trim((string) $lote);

        return $lote !== '' && $lote !== '0';
    }

    /**
     * Columna final del Excel: lote importado si hay; si no, número de OT.
     */
    public static function identificadorExcel(mixed $lote, mixed $ordentrabajoCodigo): string
    {
        if (self::esLoteImportado($lote)) {
            return trim((string) $lote);
        }

        return trim((string) $ordentrabajoCodigo);
    }

    /**
     * Corte de filas: lote importado ≠ OT (no agrupar por el mismo número).
     */
    public static function claveAgrupacion(mixed $lote, int $ordentrabajoId, int $depositoId = 0): string
    {
        $base = self::esLoteImportado($lote)
            ? 'L:'.trim((string) $lote)
            : 'OT:'.$ordentrabajoId;

        return $base.'|D:'.$depositoId;
    }

    /**
     * SKU artesanal del Excel original: 71603001 → 71-6030-01
     */
    public static function skuConGuiones(mixed $sku): string
    {
        $digitos = preg_replace('/\D+/', '', (string) $sku) ?? '';
        if (preg_match('/^(\d{2})(\d{4})(\d{2})$/', $digitos, $m)) {
            return $m[1].'-'.$m[2].'-'.$m[3];
        }
        if (preg_match('/^(\d{2})(\d{3})(\d{2})$/', $digitos, $m)) {
            return $m[1].'-'.$m[2].'-'.$m[3];
        }

        return trim((string) $sku);
    }

    /**
     * Situación de una fila del reporte Stock por OT.
     *
     * - Overlay sin movimiento: EN PRODUCCION forzada.
     * - OT solo pendiente: PENDIENTE DE FABRICACION (aunque haya depósito / Alta / Restaura).
     * - OT con avance de planta sin Terminada y sin EMPAQUE finalizado: EN PRODUCCION (aunque haya Alta/Restaura).
     * - EMPAQUE con fecha de fin (OT 29885): no es producción; con depósito queda ENTREGA INMEDIATA.
     * - ALTAP del import Excel con depósito: ENTREGA INMEDIATA (aunque la OT no tenga cierre).
     * - Resto con depósito: ENTREGA INMEDIATA.
     *
     * @param  array{situacion?: string, en_produccion?: bool}|null  $metaOt
     * @return array{situacion: string, en_produccion: bool}
     */
    public static function situacionFila(
        bool $enProduccionForzada,
        int $depositoId,
        ?array $metaOt,
        bool $esAltapExcel = false
    ): array {
        if ($enProduccionForzada) {
            return [
                'situacion' => self::EN_PRODUCCION,
                'en_produccion' => true,
            ];
        }

        $situacionMeta = is_array($metaOt) ? (string) ($metaOt['situacion'] ?? '') : '';
        $otEnProduccion = $metaOt && ! empty($metaOt['en_produccion']);
        $otPendiente = $situacionMeta === self::PENDIENTE_DE_FABRICACION;

        // Pendiente manda sobre depósito / ALTAP excel (caso OT 30813).
        if ($otPendiente) {
            return [
                'situacion' => self::PENDIENTE_DE_FABRICACION,
                'en_produccion' => false,
            ];
        }

        // OT todavía en planta (APARADO, etc.): no mentir ENTREGA por tener Alta/Restaura.
        if ($otEnProduccion) {
            return [
                'situacion' => self::EN_PRODUCCION,
                'en_produccion' => true,
            ];
        }

        // Import Excel listo en estantería: depósito manda (caso identificador 8021).
        if ($depositoId > 0 && $esAltapExcel) {
            return [
                'situacion' => self::ENTREGA_INMEDIATA,
                'en_produccion' => false,
            ];
        }

        if ($depositoId > 0) {
            return [
                'situacion' => self::ENTREGA_INMEDIATA,
                'en_produccion' => false,
            ];
        }

        if ($metaOt !== null) {
            return [
                'situacion' => (string) ($metaOt['situacion'] ?? self::ENTREGA_INMEDIATA),
                'en_produccion' => (bool) ($metaOt['en_produccion'] ?? false),
            ];
        }

        return [
            'situacion' => self::ENTREGA_INMEDIATA,
            'en_produccion' => false,
        ];
    }

    /**
     * Descripción artesanal: "1-NEGRO"
     */
    public static function descripcionCombinacion(mixed $codigo, mixed $nombre): string
    {
        $cod = trim((string) $codigo);
        $nom = trim((string) $nombre);
        if ($cod === '') {
            return $nom;
        }
        if ($nom === '') {
            return $cod;
        }
        if (str_starts_with($nom, $cod.'-') || str_starts_with($nom, $cod.' ')) {
            return $nom;
        }

        return $cod.'-'.$nom;
    }
}
