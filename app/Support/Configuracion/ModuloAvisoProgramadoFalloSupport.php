<?php

namespace App\Support\Configuracion;

use App\Mail\Configuracion\ModuloAvisoMail;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Services\Configuracion\AnitaNotificacionService;
use App\Support\Seguridad\UsuarioOperativoSupport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Avisa (campanita + mail) cuando falla una corrida del Programador.
 * Una alerta por clave y día (anti-spam). Destinatarios: usuarios del tipo + roles con permiso del módulo.
 */
final class ModuloAvisoProgramadoFalloSupport
{
    /**
     * @param  array{exit: int, salida: string, error: string|null, log_id: int|null}  $resultado
     */
    public static function notificarSiCorresponde(
        ModuloAvisoTipo $tipo,
        string $comando,
        string $origen,
        array $resultado
    ): void {
        if ((int) ($resultado['exit'] ?? 1) === 0) {
            return;
        }
        if (! (bool) config('modulo_aviso.alerta_fallo_habilitada', true)) {
            return;
        }

        $clave = $tipo->claveCompleta();
        $hoy = now()->timezone('America/Argentina/Buenos_Aires')->toDateString();
        $cacheKey = 'modulo_aviso_fallo:'.$clave.':'.$hoy;
        if (! Cache::add($cacheKey, 1, now()->endOfDay())) {
            return;
        }

        $detalle = trim((string) ($resultado['error'] ?: $resultado['salida'] ?: 'sin detalle'));
        $detalle = mb_substr($detalle, 0, 500);
        $titulo = 'Falló tarea programada: '.$tipo->nombre;
        $cuerpo = 'Clave '.$clave.' · origen '.$origen.' · comando '.$comando
            ."\nExit ".(int) $resultado['exit'].': '.$detalle;
        $url = url('configuracion/modulo-aviso/historial').'?clave='.urlencode($clave).'&estado=error';

        $usuarioIds = self::resolverUsuarioIds($tipo);
        if ($usuarioIds !== []) {
            try {
                app(AnitaNotificacionService::class)->avisarSistemaAUsuarios(
                    $usuarioIds,
                    $titulo,
                    $cuerpo,
                    $url,
                    [
                        'modulo_aviso_fallo' => true,
                        'clave' => $clave,
                        'log_id' => $resultado['log_id'] ?? null,
                    ]
                );
            } catch (Throwable $e) {
                Log::warning('ModuloAvisoProgramadoFallo: campanita', ['error' => $e->getMessage()]);
            }
        }

        $emails = self::resolverEmails($tipo, $usuarioIds);
        foreach ($emails as $email) {
            try {
                Mail::to($email)->queue(new ModuloAvisoMail(
                    $titulo,
                    $cuerpo,
                    'Programador de avisos',
                    $url,
                    null
                ));
            } catch (Throwable $e) {
                Log::warning('ModuloAvisoProgramadoFallo: mail', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return list<int>
     */
    private static function resolverUsuarioIds(ModuloAvisoTipo $tipo): array
    {
        $ids = [];
        $tipo->loadMissing('destinatarios');
        foreach ($tipo->destinatarios as $d) {
            if ($d->activo && $d->usuario_id) {
                $ids[] = (int) $d->usuario_id;
            }
        }

        if (Schema::hasTable('permiso') && Schema::hasTable('permiso_rol') && Schema::hasTable('usuario_rol')) {
            $permisoIds = DB::table('permiso')
                ->whereIn('slug', [
                    'actualizar-modulo-aviso',
                    'ejecutar-modulo-aviso-programado',
                    'listar-modulo-aviso',
                ])
                ->pluck('id');
            if ($permisoIds->isNotEmpty()) {
                $rolIds = DB::table('permiso_rol')
                    ->whereIn('permiso_id', $permisoIds)
                    ->pluck('rol_id');
                if ($rolIds->isNotEmpty()) {
                    $fromPerm = DB::table('usuario_rol')
                        ->whereIn('rol_id', $rolIds)
                        ->pluck('usuario_id')
                        ->map(static fn ($id) => (int) $id)
                        ->all();
                    $ids = array_merge($ids, $fromPerm);
                }
            }
        }

        $ids = array_values(array_unique(array_filter($ids, static fn ($id) => $id > 0)));

        return UsuarioOperativoSupport::filtrarIdsActivos($ids);
    }

    /**
     * @param  list<int>  $usuarioIds
     * @return list<string>
     */
    private static function resolverEmails(ModuloAvisoTipo $tipo, array $usuarioIds): array
    {
        $emails = [];
        foreach ($tipo->destinatarios as $d) {
            if (! $d->activo) {
                continue;
            }
            $email = trim((string) ($d->email ?? ''));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[strtolower($email)] = $email;
            }
        }
        if ($usuarioIds !== [] && Schema::hasTable('usuario')) {
            $rows = DB::table('usuario')
                ->whereIn('id', $usuarioIds)
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->pluck('email');
            foreach ($rows as $email) {
                $email = trim((string) $email);
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $emails[strtolower($email)] = $email;
                }
            }
        }

        return array_values($emails);
    }
}
