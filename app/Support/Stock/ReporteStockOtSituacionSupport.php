<?php

namespace App\Support\Stock;

/**
 * Leyendas de la columna Situación del Excel Stock por OT (Ferli).
 *
 * OT abierta con fabricación real (sin Terminada / Terminada stock / Facturada) → EN PRODUCCION.
 * Al terminar la OT → ENTREGA INMEDIATA (stock listo).
 *
 * OT de stock (tipoot S o tarea Terminada stock): ya no se usan; no entran al overlay EN PRODUCCION.
 * OT solo con «Pendiente de fabricación» (sin avance de planta): tampoco — suelen ser
 * importados / stock OT incompletas (ej. Fragola 22150).
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

    /**
     * Tareas que no cuentan como avance de fabricación en planta.
     *
     * @return list<int>
     */
    public static function idsTareasSinAvanceFabricacion(): array
    {
        return array_values(array_unique(array_filter(array_merge(
            [(int) config('consprod.TAREA_PENDIENTE_FABRICACION')],
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
     * - ALTAP del import Excel con depósito: ENTREGA INMEDIATA (aunque la OT no tenga cierre).
     * - OT con avance de planta sin Terminada: EN PRODUCCION (aunque haya Alta/Restaura con depósito).
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

        $otEnProduccion = $metaOt && ! empty($metaOt['en_produccion']);

        // Import Excel listo en estantería: depósito manda (caso identificador 8021).
        if ($depositoId > 0 && $esAltapExcel) {
            return [
                'situacion' => self::ENTREGA_INMEDIATA,
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
