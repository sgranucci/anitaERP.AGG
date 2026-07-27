<?php

namespace App\Support\Configuracion;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Uso de disco y estadísticas de proceso de la bitácora / logs.
 */
class BitacoraAccesoDiscoSupport
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function resumenCompleto(array $filtros = []): array
    {
        return [
            'bitacora_habilitada' => (bool) config('bitacora_acceso.habilitado', false),
            'auditing_habilitado' => (bool) config('audit.enabled', true),
            'panel_datos_habilitado' => (bool) config('auditoria_datos.panel_habilitado', true),
            'retencion_meses' => (int) config('bitacora_acceso.retencion_meses', 12),
            'tabla' => self::tamanoTablaBitacora(),
            'tabla_audits' => self::tamanoTabla('audits'),
            'proceso_filtro' => self::statsProcesoFiltro($filtros),
            'proceso_global' => self::statsProcesoGlobal(),
            'archivos_log' => ArchivoLogSupport::resumenDisco(),
            'generado_en' => now()->format('d/m/Y H:i:s'),
        ];
    }

    /** @return array<string, mixed> */
    public static function tamanoTabla(string $tabla): array
    {
        if (! Schema::hasTable($tabla)) {
            return [
                'existe' => false,
                'filas' => 0,
                'data_bytes' => 0,
                'index_bytes' => 0,
                'total_bytes' => 0,
                'total_humano' => '0 B',
                'data_humano' => '0 B',
                'index_humano' => '0 B',
                'promedio_fila_bytes' => 0,
            ];
        }

        $db = DB::getDatabaseName();
        $meta = DB::selectOne(
            'SELECT TABLE_ROWS AS filas_aprox,
                    DATA_LENGTH AS data_bytes,
                    INDEX_LENGTH AS index_bytes,
                    (DATA_LENGTH + INDEX_LENGTH) AS total_bytes,
                    AVG_ROW_LENGTH AS promedio_fila_bytes
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$db, $tabla]
        );

        $data = (int) ($meta->data_bytes ?? 0);
        $index = (int) ($meta->index_bytes ?? 0);
        $total = $data + $index;

        return [
            'existe' => true,
            'filas' => (int) ($meta->filas_aprox ?? 0),
            'data_bytes' => $data,
            'index_bytes' => $index,
            'total_bytes' => $total,
            'total_humano' => self::bytesHumanos($total),
            'data_humano' => self::bytesHumanos($data),
            'index_humano' => self::bytesHumanos($index),
            'promedio_fila_bytes' => (int) ($meta->promedio_fila_bytes ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    public static function tamanoTablaBitacora(): array
    {
        if (! Schema::hasTable('bitacora_acceso')) {
            return [
                'existe' => false,
                'filas' => 0,
                'data_bytes' => 0,
                'index_bytes' => 0,
                'total_bytes' => 0,
                'total_humano' => '0 B',
                'promedio_fila_bytes' => 0,
            ];
        }

        $db = DB::getDatabaseName();
        $meta = DB::selectOne(
            'SELECT TABLE_ROWS AS filas_aprox,
                    DATA_LENGTH AS data_bytes,
                    INDEX_LENGTH AS index_bytes,
                    (DATA_LENGTH + INDEX_LENGTH) AS total_bytes,
                    AVG_ROW_LENGTH AS promedio_fila_bytes
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$db, 'bitacora_acceso']
        );

        $filasReales = (int) DB::table('bitacora_acceso')->count();
        $data = (int) ($meta->data_bytes ?? 0);
        $index = (int) ($meta->index_bytes ?? 0);
        $total = $data + $index;

        return [
            'existe' => true,
            'filas' => $filasReales,
            'filas_aprox_motor' => (int) ($meta->filas_aprox ?? 0),
            'data_bytes' => $data,
            'index_bytes' => $index,
            'total_bytes' => $total,
            'total_humano' => self::bytesHumanos($total),
            'data_humano' => self::bytesHumanos($data),
            'index_humano' => self::bytesHumanos($index),
            'promedio_fila_bytes' => (int) ($meta->promedio_fila_bytes ?? 0),
        ];
    }

    /**
     * Stats de proceso (duración / memoria) del filtro actual.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function statsProcesoFiltro(array $filtros): array
    {
        if (! Schema::hasTable('bitacora_acceso')) {
            return self::statsVacias();
        }

        $q = BitacoraAccesoListadoFiltros::aplicar(DB::table('bitacora_acceso'), $filtros);

        $row = (clone $q)->selectRaw(
            'COUNT(*) AS total,
             AVG(duracion_ms) AS duracion_promedio_ms,
             MAX(duracion_ms) AS duracion_max_ms,
             AVG(memoria_pico_kb) AS memoria_promedio_kb,
             MAX(memoria_pico_kb) AS memoria_max_kb,
             SUM(memoria_pico_kb) AS memoria_suma_kb'
        )->first();

        return self::mapStats($row);
    }

    /** @return array<string, mixed> */
    public static function statsProcesoGlobal(): array
    {
        if (! Schema::hasTable('bitacora_acceso')) {
            return self::statsVacias();
        }

        $row = DB::table('bitacora_acceso')->selectRaw(
            'COUNT(*) AS total,
             AVG(duracion_ms) AS duracion_promedio_ms,
             MAX(duracion_ms) AS duracion_max_ms,
             AVG(memoria_pico_kb) AS memoria_promedio_kb,
             MAX(memoria_pico_kb) AS memoria_max_kb,
             SUM(memoria_pico_kb) AS memoria_suma_kb'
        )->first();

        return self::mapStats($row);
    }

    /** @return array<string, mixed> */
    private static function statsVacias(): array
    {
        return [
            'total' => 0,
            'duracion_promedio_ms' => null,
            'duracion_max_ms' => null,
            'memoria_promedio_kb' => null,
            'memoria_max_kb' => null,
            'memoria_suma_kb' => null,
            'memoria_suma_humano' => '0 B',
        ];
    }

    /** @param  object|null  $row */
    private static function mapStats($row): array
    {
        $sumaKb = $row ? (int) ($row->memoria_suma_kb ?? 0) : 0;

        return [
            'total' => $row ? (int) ($row->total ?? 0) : 0,
            'duracion_promedio_ms' => $row && $row->duracion_promedio_ms !== null
                ? (float) $row->duracion_promedio_ms : null,
            'duracion_max_ms' => $row && $row->duracion_max_ms !== null
                ? (int) $row->duracion_max_ms : null,
            'memoria_promedio_kb' => $row && $row->memoria_promedio_kb !== null
                ? (float) $row->memoria_promedio_kb : null,
            'memoria_max_kb' => $row && $row->memoria_max_kb !== null
                ? (int) $row->memoria_max_kb : null,
            'memoria_suma_kb' => $sumaKb,
            'memoria_suma_humano' => self::bytesHumanos($sumaKb * 1024),
        ];
    }

    public static function bytesHumanos(int|float $bytes): string
    {
        $bytes = max(0, (float) $bytes);
        if ($bytes < 1024) {
            return number_format($bytes, 0, ',', '.').' B';
        }
        $u = ['KB', 'MB', 'GB', 'TB'];
        $i = -1;
        do {
            $bytes /= 1024;
            $i++;
        } while ($bytes >= 1024 && $i < count($u) - 1);

        $dec = $bytes < 10 ? 2 : 1;

        return number_format($bytes, $dec, ',', '.').' '.$u[$i];
    }
}
