<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\Mail\Ventas\GastronomiaAnitaAuditoriaDiaria;
use App\Models\Seguridad\Usuario;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Auditoría diaria ERP ↔ Anita por fecha calendario: detecta faltantes, replica vía bridge y alerta por mail.
 */
final class GastronomiaAnitaAuditoriaDiariaService
{
    public function __construct(
        private readonly GastronomiaChequeoVentasAnitaErpService $chequeoService,
        private readonly GastronomiaReplicarVentasAnitaErpService $replicarService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(
        ?string $fechaCalendario = null,
        bool $dryRun = false,
        bool $enviarMail = true,
        ?int $empresaId = null,
    ): array {
        $config = config('gastronomia.auditoria_anita_diaria', []);
        $fecha = $fechaCalendario ?? Carbon::yesterday()->toDateString();
        $empresaId = $empresaId ?? (int) ($config['empresa_id'] ?? 1);
        $tolerancia = max(0.0, (float) ($config['tolerancia'] ?? 0.02));
        $replicarInsumos = filter_var($config['replicar_insumos'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $this->autenticarUsuarioSistema($config);

        $pre = $this->chequeoService->auditoriaPorFechaCalendario($fecha, $empresaId, $tolerancia);
        $faltantesInicial = (int) ($pre['resumen_global']['conteo']['solo_erp'] ?? 0);

        $replicacion = [
            'combinaciones' => 0,
            'faltantes' => 0,
            'replicadas' => 0,
            'errores' => [],
            'detalle' => [],
            'fecha_calendario' => $fecha,
            'omitida' => true,
        ];

        if ($faltantesInicial > 0) {
            $replicacion = $this->replicarService->replicarFaltantesPorFechaCalendario(
                $fecha,
                $empresaId,
                null,
                $dryRun,
                0,
                $replicarInsumos,
            );
            $replicacion['omitida'] = false;
        }

        $post = $this->chequeoService->auditoriaPorFechaCalendario($fecha, $empresaId, $tolerancia);

        $informe = [
            'fecha_calendario' => $fecha,
            'empresa_id' => $empresaId,
            'dry_run' => $dryRun,
            'pre' => $pre,
            'replicacion' => $replicacion,
            'post' => $post,
            'requiere_alerta' => $this->requiereAlerta($pre, $post, $replicacion, $tolerancia, $config),
        ];

        if ($enviarMail && ! $dryRun && $informe['requiere_alerta']) {
            $destino = trim((string) ($config['email'] ?? ''));
            if ($destino !== '') {
                try {
                    Mail::to($destino)->send(new GastronomiaAnitaAuditoriaDiaria($informe));
                    $informe['mail_enviado'] = true;
                    $informe['mail_destino'] = $destino;
                } catch (\Throwable $e) {
                    $informe['mail_enviado'] = false;
                    $informe['mail_error'] = $e->getMessage();
                    Log::error('gastronomia.auditoria_anita_diaria.mail_fallo', [
                        'fecha' => $fecha,
                        'destino' => $destino,
                        'msg' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('gastronomia.auditoria_anita_diaria.ok', [
            'fecha_calendario' => $fecha,
            'faltantes_inicial' => $faltantesInicial,
            'faltantes_final' => (int) ($post['resumen_global']['conteo']['solo_erp'] ?? 0),
            'replicadas' => (int) ($replicacion['replicadas'] ?? 0),
            'delta_total' => (float) ($post['resumen_global']['delta_totales']['total'] ?? 0),
            'requiere_alerta' => $informe['requiere_alerta'],
        ]);

        return $informe;
    }

    /**
     * @param  array<string, mixed>  $pre
     * @param  array<string, mixed>  $post
     * @param  array<string, mixed>  $replicacion
     * @param  array<string, mixed>  $config
     */
    private function requiereAlerta(
        array $pre,
        array $post,
        array $replicacion,
        float $tolerancia,
        array $config,
    ): bool {
        if (filter_var($config['email_si_ok'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        $conteoPost = $post['resumen_global']['conteo'] ?? [];

        // Solo alertar por estado final (no por faltantes ya replicados con éxito).
        if ((int) ($conteoPost['solo_erp'] ?? 0) > 0) {
            return true;
        }

        if ((int) ($conteoPost['diferencia'] ?? 0) > 0) {
            return true;
        }

        if ((int) ($conteoPost['error'] ?? 0) > 0) {
            return true;
        }

        if (($replicacion['errores'] ?? []) !== []) {
            return true;
        }

        $delta = (float) ($post['resumen_global']['delta_totales']['total'] ?? 0);
        if (abs($delta) > $tolerancia) {
            return true;
        }

        foreach ($post['por_puntoventa'] ?? [] as $pv) {
            $deltaPv = (float) ($pv['resumen']['delta_totales']['total'] ?? 0);
            if (abs($deltaPv) > $tolerancia) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function autenticarUsuarioSistema(array $config): void
    {
        if (Auth::check()) {
            return;
        }

        $usuarioId = (int) ($config['usuario_id'] ?? 0);
        if ($usuarioId <= 0) {
            $usuarioId = (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);
        }

        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            throw new \RuntimeException('No se pudo autenticar usuario de sistema para auditoría Anita.');
        }
    }
}
