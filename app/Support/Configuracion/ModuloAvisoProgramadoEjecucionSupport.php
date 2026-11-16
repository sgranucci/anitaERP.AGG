<?php

namespace App\Support\Configuracion;

use App\Models\Configuracion\ModuloAvisoProgramadoLog;
use App\Models\Configuracion\ModuloAvisoTipo;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Ejecuta un comando de aviso programado y deja bitácora (cron o «Ejecutar ahora»).
 */
class ModuloAvisoProgramadoEjecucionSupport
{
    private const FAILURE = 1;

    /**
     * @param  array<string, mixed>  $opcionesArtisan  Flags para Artisan::call (ej. ['--ejecutar' => true])
     * @return array{exit: int, salida: string, error: string|null, log_id: int|null}
     */
    public static function ejecutar(
        ModuloAvisoTipo $tipo,
        string $comando,
        string $origen = 'cron',
        array $opcionesArtisan = [],
        bool $omitirLogSiIdle = false
    ): array {
        $clave = $tipo->claveCompleta();
        $inicio = now();
        $comandoLog = $comando.($opcionesArtisan !== [] ? ' '.self::formatoOpciones($opcionesArtisan) : '');
        $log = null;
        if (Schema::hasTable('modulo_aviso_programado_log') && ! $omitirLogSiIdle) {
            $log = ModuloAvisoProgramadoLog::query()->create([
                'modulo_aviso_tipo_id' => $tipo->id,
                'clave' => $clave,
                'comando' => substr($comandoLog, 0, 255),
                'origen' => substr($origen, 0, 40),
                'inicio_en' => $inicio,
            ]);
        }

        $exit = self::FAILURE;
        $salida = '';
        $error = null;
        try {
            $exit = (int) Artisan::call($comando, $opcionesArtisan);
            $salida = trim(Artisan::output());
        } catch (Throwable $e) {
            $error = $e->getMessage();
            $exit = self::FAILURE;
            Log::error('ModuloAvisoProgramadoEjecucion: excepción', [
                'clave' => $clave,
                'comando' => $comandoLog,
                'error' => $error,
            ]);
        }

        $idle = $omitirLogSiIdle && $exit === 0 && $error === null && self::pareceIdle($salida);
        if ($omitirLogSiIdle && ! $idle && Schema::hasTable('modulo_aviso_programado_log') && $log === null) {
            $log = ModuloAvisoProgramadoLog::query()->create([
                'modulo_aviso_tipo_id' => $tipo->id,
                'clave' => $clave,
                'comando' => substr($comandoLog, 0, 255),
                'origen' => substr($origen, 0, 40),
                'inicio_en' => $inicio,
            ]);
        }

        if ($log) {
            $log->update([
                'fin_en' => now(),
                'exit_code' => $exit,
                'salida' => $salida !== '' ? mb_substr($salida, 0, 65000) : null,
                'error' => $error !== null ? mb_substr($error, 0, 65000) : null,
            ]);
        }

        $resultado = [
            'exit' => $exit,
            'salida' => $salida,
            'error' => $error,
            'log_id' => $log?->id,
        ];

        if ($exit !== 0) {
            ModuloAvisoProgramadoFalloSupport::notificarSiCorresponde($tipo, $comandoLog, $origen, $resultado);
        }

        return $resultado;
    }

    /** @param  array<string, mixed>  $opciones */
    private static function formatoOpciones(array $opciones): string
    {
        $partes = [];
        foreach ($opciones as $k => $v) {
            if ($v === true || $v === 1 || $v === '1') {
                $partes[] = (string) $k;
            } elseif ($v !== false && $v !== null && $v !== '') {
                $partes[] = $k.'='.$v;
            }
        }

        return implode(' ', $partes);
    }

    private static function pareceIdle(string $salida): bool
    {
        if ($salida === '') {
            return true;
        }

        return (bool) preg_match(
            '/No hay env[ií]os|Pendientes:\s*0\b|Enviados:\s*0\b|0 suscripci|sin destinatarios|deshabilitada por configuraci|Avisos aging cheques deshabilitados/iu',
            $salida
        );
    }
}
