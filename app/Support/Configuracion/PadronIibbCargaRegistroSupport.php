<?php

declare(strict_types=1);

namespace App\Support\Configuracion;

use App\Models\Configuracion\Padron_Iibb_Carga;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Alta y seguimiento de la fila de padron_iibb_carga que alimenta el panel de
 * estado de la pantalla de importación.
 *
 * Nada de lo que pasa acá puede tumbar una importación: si el registro de
 * auditoría falla, se loguea y la carga sigue.
 */
final class PadronIibbCargaRegistroSupport
{
    public const ORIGEN_PANTALLA = 'pantalla';

    public const ORIGEN_CONSOLA = 'consola';

    /**
     * @param  array<string,mixed>  $datos
     * @return int|null id de la carga, o null si no se pudo registrar
     */
    public static function iniciar(array $datos): ?int
    {
        try {
            return (int) DB::table('padron_iibb_carga')->insertGetId([
                'provincia_id' => $datos['provincia_id'] ?? null,
                'jurisdiccion' => $datos['jurisdiccion'] ?? null,
                'etiqueta' => mb_substr((string) ($datos['etiqueta'] ?? 'Padrón IIBB'), 0, 100),
                'tipopadron' => $datos['tipopadron'] ?? null,
                'origen' => $datos['origen'] ?? self::ORIGEN_PANTALLA,
                'estado' => Padron_Iibb_Carga::ESTADO_EN_PROCESO,
                'archivo' => mb_substr((string) ($datos['archivo'] ?? ''), 0, 500) ?: null,
                'usuario_id' => $datos['usuario_id'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('padron_iibb:carga_registro:iniciar_error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<string,mixed>  $stats
     */
    public static function progreso(?int $cargaId, array $stats): void
    {
        self::actualizar($cargaId, self::columnasDesdeStats($stats));
    }

    /**
     * @param  array<string,mixed>  $stats
     */
    public static function finalizar(?int $cargaId, array $stats, ?string $mensaje = null): void
    {
        self::actualizar($cargaId, self::columnasDesdeStats($stats) + [
            'estado' => Padron_Iibb_Carga::ESTADO_OK,
            'mensaje' => $mensaje,
        ]);
    }

    public static function fallar(?int $cargaId, string $error): void
    {
        self::actualizar($cargaId, [
            'estado' => Padron_Iibb_Carga::ESTADO_ERROR,
            'mensaje' => mb_substr($error, 0, 2000),
        ]);
    }

    /**
     * Cierra cargas que quedaron colgadas (por ejemplo, si se reinició el worker
     * en medio de una importación) para que el panel no las muestre eternamente
     * "en proceso".
     */
    public static function cerrarColgadas(int $horas = 12): int
    {
        try {
            return DB::table('padron_iibb_carga')
                ->where('estado', Padron_Iibb_Carga::ESTADO_EN_PROCESO)
                ->where('updated_at', '<', now()->subHours($horas))
                ->update([
                    'estado' => Padron_Iibb_Carga::ESTADO_ERROR,
                    'mensaje' => 'La importación quedó sin finalizar (proceso interrumpido).',
                    'updated_at' => now(),
                ]);
        } catch (Throwable $e) {
            Log::warning('padron_iibb:carga_registro:cerrar_colgadas_error', ['error' => $e->getMessage()]);

            return 0;
        }
    }

    /**
     * @param  array<string,mixed>  $stats
     * @return array<string,mixed>
     */
    private static function columnasDesdeStats(array $stats): array
    {
        $insertadas = (int) ($stats['insertadas_tasa'] ?? $stats['insertadas'] ?? 0);

        return [
            'desdefecha' => $stats['desdefecha'] ?? null,
            'hastafecha' => $stats['hastafecha'] ?? null,
            'filas_leidas' => (int) ($stats['leidas'] ?? 0),
            'filas_insertadas' => $insertadas,
            'filas_actualizadas' => (int) ($stats['actualizadas_tasa'] ?? 0),
            'filas_omitidas' => (int) ($stats['omitidas'] ?? 0),
            'filas_borradas' => (int) ($stats['borrados'] ?? 0),
            'errores' => (int) ($stats['errores'] ?? 0),
            'segundos' => isset($stats['segundos']) ? (int) round((float) $stats['segundos']) : null,
        ];
    }

    /**
     * @param  array<string,mixed>  $columnas
     */
    private static function actualizar(?int $cargaId, array $columnas): void
    {
        if ($cargaId === null) {
            return;
        }

        try {
            DB::table('padron_iibb_carga')
                ->where('id', $cargaId)
                ->update($columnas + ['updated_at' => now()]);
        } catch (Throwable $e) {
            Log::warning('padron_iibb:carga_registro:actualizar_error', [
                'carga_id' => $cargaId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
