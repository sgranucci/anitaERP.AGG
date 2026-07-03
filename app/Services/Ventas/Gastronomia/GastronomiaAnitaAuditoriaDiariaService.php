<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\Mail\Ventas\GastronomiaAnitaAuditoriaDiaria;
use App\Models\Seguridad\Usuario;
use App\Services\Caja\Estacionamiento\EstacionamientoChequeoVentasAnitaErpService;
use App\Services\Caja\Estacionamiento\EstacionamientoReplicarVentasAnitaErpService;
use App\Services\Ventas\MaquinavendingRendicionAuditoriaAnitaService;
use App\Services\Ventas\MaquinavendingRendicionAnitaSyncService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Auditoría diaria ERP ↔ Anita por fecha de jornada: gastronomía + estacionamiento.
 * Detecta faltantes, replica vía bridge y alerta por mail.
 */
final class GastronomiaAnitaAuditoriaDiariaService
{
    public function __construct(
        private readonly GastronomiaChequeoVentasAnitaErpService $chequeoGastroService,
        private readonly GastronomiaReplicarVentasAnitaErpService $replicarGastroService,
        private readonly EstacionamientoChequeoVentasAnitaErpService $chequeoEstacionamientoService,
        private readonly EstacionamientoReplicarVentasAnitaErpService $replicarEstacionamientoService,
        private readonly MaquinavendingRendicionAuditoriaAnitaService $auditoriaVendingService,
        private readonly MaquinavendingRendicionAnitaSyncService $vendingAnitaSyncService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(
        ?string $fechaJornada = null,
        bool $dryRun = false,
        bool $enviarMail = true,
        ?int $empresaId = null,
    ): array {
        $config = config('gastronomia.auditoria_anita_diaria', []);
        if (! config('gastronomia.sincronizar_anita_al_facturar', true)
            && ! config('estacionamiento.sincronizar_anita_al_facturar', false)) {
            return [
                'fecha_jornada' => $fechaJornada ?? Carbon::yesterday()->toDateString(),
                'omitida' => true,
                'motivo' => 'Réplica venta Anita deshabilitada (ventas solo ERP).',
                'requiere_alerta' => false,
            ];
        }

        $fecha = $fechaJornada ?? Carbon::yesterday()->toDateString();
        $empresaId = $empresaId ?? (int) ($config['empresa_id'] ?? 1);
        $tolerancia = max(0.0, (float) ($config['tolerancia'] ?? 0.02));
        $replicarInsumos = filter_var($config['replicar_insumos'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $this->autenticarUsuarioSistema($config);

        $preGastro = $this->chequeoGastroService->auditoriaPorFechaJornada($fecha, $empresaId, $tolerancia);
        $preEstacionamiento = $this->chequeoEstacionamientoService->auditoriaPorFechaJornada(
            $fecha,
            $empresaId,
            $tolerancia,
        );

        $faltantesGastro = (int) ($preGastro['resumen_global']['conteo']['solo_erp'] ?? 0);
        $faltantesEstacionamiento = (int) ($preEstacionamiento['resumen_global']['conteo']['solo_erp'] ?? 0);

        $replicacionGastro = $this->replicacionVacia($fecha, 'gastro');
        if ($faltantesGastro > 0) {
            $replicacionGastro = $this->replicarGastroService->replicarFaltantes(
                $fecha,
                $fecha,
                $empresaId,
                null,
                $dryRun,
                0,
                $replicarInsumos,
            );
            $replicacionGastro['circuito'] = 'gastro';
            $replicacionGastro['fecha_jornada'] = $fecha;
            $replicacionGastro['omitida'] = false;
        }

        $replicacionEstacionamiento = $this->replicacionVacia($fecha, 'estacionamiento');
        if ($faltantesEstacionamiento > 0) {
            $replicacionEstacionamiento = $this->replicarEstacionamientoService->replicarFaltantes(
                $fecha,
                $fecha,
                $empresaId,
                null,
                $dryRun,
                0,
            );
            $replicacionEstacionamiento['circuito'] = 'estacionamiento';
            $replicacionEstacionamiento['fecha_jornada'] = $fecha;
            $replicacionEstacionamiento['omitida'] = false;
        }

        $bloqueVending = $this->auditoriaVendingJornada($empresaId, $fecha, $dryRun, $tolerancia);

        $postGastro = $this->chequeoGastroService->auditoriaPorFechaJornada($fecha, $empresaId, $tolerancia);
        $postEstacionamiento = $this->chequeoEstacionamientoService->auditoriaPorFechaJornada(
            $fecha,
            $empresaId,
            $tolerancia,
        );

        $informe = [
            'fecha_jornada' => $fecha,
            'fecha_calendario' => $fecha,
            'empresa_id' => $empresaId,
            'dry_run' => $dryRun,
            'gastro' => [
                'pre' => $preGastro,
                'post' => $postGastro,
                'replicacion' => $replicacionGastro,
            ],
            'estacionamiento' => [
                'pre' => $preEstacionamiento,
                'post' => $postEstacionamiento,
                'replicacion' => $replicacionEstacionamiento,
            ],
            'vending' => $bloqueVending,
            'pre' => $preGastro,
            'post' => $postGastro,
            'replicacion' => $replicacionGastro,
            'requiere_alerta' => $this->requiereAlertaInforme(
                $postGastro,
                $postEstacionamiento,
                $replicacionGastro,
                $replicacionEstacionamiento,
                $bloqueVending,
                $tolerancia,
                $config,
            ),
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
                        'fecha_jornada' => $fecha,
                        'destino' => $destino,
                        'msg' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('gastronomia.auditoria_anita_diaria.ok', [
            'fecha_jornada' => $fecha,
            'faltantes_gastro_inicial' => $faltantesGastro,
            'faltantes_estacionamiento_inicial' => $faltantesEstacionamiento,
            'faltantes_gastro_final' => (int) ($postGastro['resumen_global']['conteo']['solo_erp'] ?? 0),
            'faltantes_estacionamiento_final' => (int) ($postEstacionamiento['resumen_global']['conteo']['solo_erp'] ?? 0),
            'replicadas_gastro' => (int) ($replicacionGastro['replicadas'] ?? 0),
            'replicadas_estacionamiento' => (int) ($replicacionEstacionamiento['replicadas'] ?? 0),
            'reparadas_vending' => (int) ($bloqueVending['replicacion']['replicadas'] ?? 0),
            'diferencias_vending_final' => (int) ($bloqueVending['post']['resumen']['conteo']['requiere_reparacion'] ?? 0),
            'delta_total_gastro' => (float) ($postGastro['resumen_global']['delta_totales']['total'] ?? 0),
            'delta_total_estacionamiento' => (float) ($postEstacionamiento['resumen_global']['delta_totales']['total'] ?? 0),
            'requiere_alerta' => $informe['requiere_alerta'],
        ]);

        return $informe;
    }

    /**
     * @return array<string, mixed>
     */
    private function replicacionVacia(string $fechaJornada, string $circuito): array
    {
        return [
            'circuito' => $circuito,
            'combinaciones' => 0,
            'faltantes' => 0,
            'replicadas' => 0,
            'errores' => [],
            'detalle' => [],
            'fecha_jornada' => $fechaJornada,
            'omitida' => true,
        ];
    }

    /**
     * @return array{pre: array<string, mixed>, post: array<string, mixed>, replicacion: array<string, mixed>, omitida?: bool}
     */
    private function auditoriaVendingJornada(
        int $empresaId,
        string $fechaJornada,
        bool $dryRun,
        float $tolerancia,
    ): array {
        if (! $this->vendingAnitaSyncService->sincronizacionHabilitada()) {
            return [
                'omitida' => true,
                'motivo' => 'RENDICION_MAQUINAVENDING_SINCRONIZAR_ANITA deshabilitado.',
                'pre' => ['resumen_global' => ['ventas_erp' => 0, 'conteo' => []]],
                'post' => ['resumen_global' => ['ventas_erp' => 0, 'conteo' => []]],
                'replicacion' => $this->replicacionVacia($fechaJornada, 'vending'),
            ];
        }

        $resultado = $this->auditoriaVendingService->auditarYRepararFechaJornada(
            $empresaId,
            $fechaJornada,
            $dryRun,
            $tolerancia,
        );

        return [
            'pre' => $this->normalizarResumenVendingAuditoria($resultado['pre']),
            'post' => $this->normalizarResumenVendingAuditoria($resultado['post']),
            'replicacion' => $resultado['replicacion'],
        ];
    }

    /**
     * @param  array<string, mixed>  $auditoria
     * @return array{resumen_global: array<string, mixed>, filas: list<array<string, mixed>>}
     */
    private function normalizarResumenVendingAuditoria(array $auditoria): array
    {
        $conteo = $auditoria['resumen']['conteo'] ?? [];

        return [
            'resumen_global' => [
                'ventas_erp' => (int) ($conteo['rendiciones'] ?? 0),
                'conteo' => [
                    'solo_erp' => (int) ($conteo['sin_cabecera'] ?? 0),
                    'diferencia' => (int) ($conteo['requiere_reparacion'] ?? 0),
                    'error' => 0,
                ],
                'delta_totales' => [
                    'total' => 0.0,
                ],
            ],
            'filas' => $auditoria['filas'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $postGastro
     * @param  array<string, mixed>  $postEstacionamiento
     * @param  array<string, mixed>  $replicacionGastro
     * @param  array<string, mixed>  $replicacionEstacionamiento
     * @param  array<string, mixed>  $bloqueVending
     * @param  array<string, mixed>  $config
     */
    private function requiereAlertaInforme(
        array $postGastro,
        array $postEstacionamiento,
        array $replicacionGastro,
        array $replicacionEstacionamiento,
        array $bloqueVending,
        float $tolerancia,
        array $config,
    ): bool {
        if (filter_var($config['email_si_ok'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        foreach ([$postGastro, $postEstacionamiento] as $post) {
            if ($this->requiereAlertaResumen($post, $tolerancia)) {
                return true;
            }
        }

        $postVending = $bloqueVending['post']['resumen_global'] ?? [];
        if ((int) ($postVending['conteo']['diferencia'] ?? 0) > 0
            || (int) ($postVending['conteo']['solo_erp'] ?? 0) > 0) {
            return true;
        }

        if (($replicacionGastro['errores'] ?? []) !== []
            || ($replicacionEstacionamiento['errores'] ?? []) !== []
            || ($bloqueVending['replicacion']['errores'] ?? []) !== []) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $post
     */
    private function requiereAlertaResumen(array $post, float $tolerancia): bool
    {
        $conteoPost = $post['resumen_global']['conteo'] ?? [];

        if ((int) ($conteoPost['solo_erp'] ?? 0) > 0) {
            return true;
        }

        if ((int) ($conteoPost['diferencia'] ?? 0) > 0) {
            return true;
        }

        if ((int) ($conteoPost['error'] ?? 0) > 0) {
            return true;
        }

        $delta = (float) ($post['resumen_global']['delta_totales']['total'] ?? 0);
        if (abs($delta) > $tolerancia) {
            return true;
        }

        foreach (['gravado', 'iva', 'exento'] as $campo) {
            $deltaCampo = (float) ($post['resumen_global']['delta_totales'][$campo] ?? 0);
            if (abs($deltaCampo) > $tolerancia) {
                return true;
            }
        }

        foreach ($post['por_puntoventa'] ?? [] as $pv) {
            $deltaPv = (float) ($pv['resumen']['delta_totales']['total'] ?? 0);
            if (abs($deltaPv) > $tolerancia) {
                return true;
            }

            foreach (['gravado', 'iva', 'exento'] as $campo) {
                $deltaCampoPv = (float) ($pv['resumen']['delta_totales'][$campo] ?? 0);
                if (abs($deltaCampoPv) > $tolerancia) {
                    return true;
                }
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
