<?php

namespace App\Services\Configuracion;

use App\Contracts\Configuracion\WhatsappDriverInterface;
use App\Models\Configuracion\WhatsappEnvioLog;
use App\Models\Seguridad\Usuario;
use App\Services\Configuracion\Whatsapp\MetaCloudWhatsappDriver;
use App\Services\Configuracion\Whatsapp\NullWhatsappDriver;
use App\Support\Configuracion\UsuarioAvisoPreferenciaSupport;
use App\Support\Configuracion\WhatsappCanalSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Envía WhatsApp solo si la empresa tiene cuenta operativa asociada.
 */
class WhatsappEnvioService
{
    public function enviarAUsuario(
        int $usuarioId,
        ?int $empresaId,
        string $texto,
        ?string $moduloAviso = null,
        ?int $entityId = null
    ): bool {
        if (! UsuarioAvisoPreferenciaSupport::permite($usuarioId, UsuarioAvisoPreferenciaSupport::CANAL_WHATSAPP)) {
            $this->registrarLog($empresaId, $usuarioId, null, $moduloAviso, $entityId, 'omitido', null, 'preferencia_usuario', false);

            return false;
        }

        $cuenta = WhatsappCanalSupport::cuentaActivaParaEmpresa($empresaId);
        if (! $cuenta) {
            Log::info('WhatsApp: sin cuenta activa para empresa', [
                'empresa_id' => $empresaId,
                'usuario_id' => $usuarioId,
            ]);
            $this->registrarLog($empresaId, $usuarioId, null, $moduloAviso, $entityId, 'omitido', null, 'sin_cuenta_empresa', false);

            return false;
        }

        $usuario = Usuario::query()->find($usuarioId);
        $celular = WhatsappCanalSupport::normalizarCelular($usuario->celular ?? null);
        if ($celular === null) {
            Log::info('WhatsApp: usuario sin celular válido', ['usuario_id' => $usuarioId]);
            $this->registrarLog($empresaId, $usuarioId, null, $moduloAviso, $entityId, 'error', null, 'sin_celular', false);

            return false;
        }

        $texto = trim($texto);
        if ($texto === '') {
            return false;
        }

        $usoPlantilla = (bool) config('whatsapp.usar_plantilla', false);
        if ($usoPlantilla) {
            $nombre = (string) config('whatsapp.plantilla_nombre', 'anita_aviso');
            $idioma = (string) config('whatsapp.plantilla_idioma', 'es_AR');
            $params = [mb_substr($texto, 0, 1024)];
            $resultado = $this->driver()->enviarPlantilla($cuenta, $celular, $nombre, $idioma, $params);
        } else {
            $resultado = $this->driver()->enviarTexto($cuenta, $celular, $texto);
        }

        $ok = (bool) ($resultado['ok'] ?? false);
        $this->registrarLog(
            $empresaId,
            $usuarioId,
            $celular,
            $moduloAviso,
            $entityId,
            $ok ? 'ok' : 'error',
            $resultado['provider_id'] ?? null,
            $ok ? null : (string) ($resultado['error'] ?? 'fallo'),
            $usoPlantilla,
            mb_strlen($texto)
        );

        return $ok;
    }

    /**
     * Prueba operativa: envía un texto corto al usuario indicado con la cuenta de la empresa.
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function enviarPrueba(int $empresaId, int $usuarioId): array
    {
        if ($empresaId <= 0 || $usuarioId <= 0) {
            return ['ok' => false, 'mensaje' => 'Empresa o usuario inválidos.'];
        }

        $cuenta = WhatsappCanalSupport::cuentaActivaParaEmpresa($empresaId);
        if (! $cuenta) {
            return ['ok' => false, 'mensaje' => 'No hay cuenta WhatsApp activa y completa para esta empresa.'];
        }

        $driverNombre = config('whatsapp.driver');
        if ($driverNombre === null || $driverNombre === '' || $driverNombre === false
            || in_array(strtolower(trim((string) $driverNombre)), ['null', 'log', 'dry', 'none'], true)) {
            return ['ok' => false, 'mensaje' => 'WHATSAPP_DRIVER no está en modo Meta (hoy: '.var_export($driverNombre, true).').'];
        }

        $texto = 'Anita ERP — prueba WhatsApp empresa #'.$empresaId.' '.now()->format('d/m/Y H:i');
        $ok = $this->enviarAUsuario($usuarioId, $empresaId, $texto, 'prueba.manual', null);

        return [
            'ok' => $ok,
            'mensaje' => $ok
                ? 'Prueba enviada. Revisá el celular y el log de envíos.'
                : 'No se pudo enviar (sin celular, preferencia off o error Meta). Ver log de envíos.',
        ];
    }

    /**
     * @param  iterable<int|string>  $usuarioIds
     */
    public function enviarAUsuarios(
        iterable $usuarioIds,
        ?int $empresaId,
        string $texto,
        ?string $moduloAviso = null,
        ?int $entityId = null
    ): int {
        if (! WhatsappCanalSupport::empresaPuedeEnviar($empresaId)) {
            return 0;
        }

        $filtrados = UsuarioAvisoPreferenciaSupport::filtrarIds(
            $usuarioIds,
            UsuarioAvisoPreferenciaSupport::CANAL_WHATSAPP
        );

        $ok = 0;
        foreach ($filtrados as $usuarioId) {
            if ($this->enviarAUsuario($usuarioId, $empresaId, $texto, $moduloAviso, $entityId)) {
                $ok++;
            }
        }

        return $ok;
    }

    private function registrarLog(
        ?int $empresaId,
        ?int $usuarioId,
        ?string $celular,
        ?string $moduloAviso,
        ?int $entityId,
        string $estado,
        ?string $providerId,
        ?string $error,
        bool $usoPlantilla,
        int $chars = 0
    ): void {
        if (! Schema::hasTable('whatsapp_envio_log')) {
            return;
        }
        try {
            WhatsappEnvioLog::query()->create([
                'empresa_id' => $empresaId ?: null,
                'usuario_id' => $usuarioId ?: null,
                'celular' => $celular,
                'modulo_aviso' => $moduloAviso ? mb_substr($moduloAviso, 0, 120) : null,
                'entity_id' => $entityId,
                'estado' => $estado,
                'provider_id' => $providerId ? mb_substr($providerId, 0, 120) : null,
                'error' => $error ? mb_substr($error, 0, 255) : null,
                'driver' => (string) (config('whatsapp.driver') ?? 'null'),
                'chars' => max(0, $chars),
                'uso_plantilla' => $usoPlantilla,
                'enviado_en' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp: no se pudo auditar envío', ['error' => $e->getMessage()]);
        }
    }

    private function driver(): WhatsappDriverInterface
    {
        $nombre = config('whatsapp.driver');
        if ($nombre === null || $nombre === '' || $nombre === false) {
            return app(NullWhatsappDriver::class);
        }
        $nombre = strtolower(trim((string) $nombre));

        return match ($nombre) {
            'null', 'log', 'dry', 'none' => app(NullWhatsappDriver::class),
            default => app(MetaCloudWhatsappDriver::class),
        };
    }
}
