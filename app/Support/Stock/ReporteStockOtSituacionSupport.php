<?php

namespace App\Support\Stock;

/**
 * Leyendas de la columna Situación del Excel Stock por OT (Ferli).
 *
 * OT abierta (sin tarea Terminada / Terminada stock / Facturada) → EN PRODUCCION.
 * Al terminar la OT → ENTREGA INMEDIATA (stock listo).
 */
final class ReporteStockOtSituacionSupport
{
    public const ENTREGA_INMEDIATA = 'ENTREGA INMEDIATA';

    public const EN_PRODUCCION = 'EN PRODUCCION';

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

    public static function esTareaCierre(int $tareaId): bool
    {
        return in_array($tareaId, self::idsTareasCierre(), true);
    }

    /**
     * @param  iterable<int|string>  $tareaIds
     * @return array{situacion: string, en_produccion: bool}
     */
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

        return [
            'situacion' => self::EN_PRODUCCION,
            'en_produccion' => true,
        ];
    }

    public static function pasaFiltroEstadoOt(string $estadoOt, string $situacion, bool $enProduccion): bool
    {
        return match ($estadoOt) {
            'ENTREGA' => ! $enProduccion && $situacion === self::ENTREGA_INMEDIATA,
            'PRODUCCION' => $enProduccion || $situacion === self::EN_PRODUCCION,
            default => true,
        };
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
    public static function claveAgrupacion(mixed $lote, int $ordentrabajoId): string
    {
        if (self::esLoteImportado($lote)) {
            return 'L:'.trim((string) $lote);
        }

        return 'OT:'.$ordentrabajoId;
    }
}
