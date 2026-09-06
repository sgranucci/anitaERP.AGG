<?php

namespace App\Services\Configuracion;

use App\Mail\Configuracion\MailDigestMisAprobaciones;
use App\Repositories\Admin\UsuarioRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Digest matutino unificado: un mail por usuario con pendientes de todas las fuentes.
 */
class ArbolAprobacionDigestService
{
    public function __construct(
        private UserTaskBandejaService $userTaskBandejaService,
        private UsuarioRepositoryInterface $usuarioRepository,
    ) {}

    /**
     * @return array{enviados: int, omitidos: int, errores: int, firmantes: int}
     */
    public function enviarDigestDiario(?Carbon $hoy = null): array
    {
        $hoy = ($hoy ?? Carbon::now())->startOfDay();
        $stats = ['enviados' => 0, 'omitidos' => 0, 'errores' => 0, 'firmantes' => 0];

        $usuarioIds = $this->userTaskBandejaService->usuarioIdsCandidatosDigest();
        $stats['firmantes'] = count($usuarioIds);

        foreach ($usuarioIds as $usuarioId) {
            try {
                $items = $this->userTaskBandejaService->listarPendientesParaUsuario($usuarioId)
                    ->filter(fn (array $row) => ! empty($row['documento_existe']))
                    ->values();

                if ($items->isEmpty()) {
                    $stats['omitidos']++;

                    continue;
                }

                $cacheKey = 'mis_aprobaciones_digest_'.$usuarioId.'_'.$hoy->toDateString();
                if (! Cache::add($cacheKey, 1, $hoy->copy()->endOfDay())) {
                    $stats['omitidos']++;

                    continue;
                }

                $usuario = $this->usuarioRepository->findOperativo($usuarioId);
                $email = trim((string) ($usuario->email ?? ''));
                if ($email === '') {
                    $stats['omitidos']++;

                    continue;
                }

                $urgentes = $items->where('urgencia', 'urgente')->count();
                Mail::to($email)->send(new MailDigestMisAprobaciones(
                    nombreUsuario: (string) ($usuario->nombre ?? $usuario->usuario ?? 'Usuario'),
                    items: $items->take(30)->all(),
                    total: $items->count(),
                    urgentes: $urgentes,
                    linkBandeja: urlAppAbsoluta('mis-aprobaciones'),
                    fecha: $hoy->copy(),
                ));

                try {
                    app(\App\Services\Configuracion\AnitaNotificacionService::class)->avisarDigest(
                        $usuarioId,
                        $items->count(),
                        $urgentes,
                        urlAppAbsoluta('mis-aprobaciones')
                    );
                } catch (\Throwable) {
                }

                $stats['enviados']++;
            } catch (\Throwable $e) {
                $stats['errores']++;
                Log::warning('mis_aprobaciones_digest_error', [
                    'usuario_id' => $usuarioId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }
}
