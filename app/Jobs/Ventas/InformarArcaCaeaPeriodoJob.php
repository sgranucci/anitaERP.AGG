<?php

declare(strict_types=1);

namespace App\Jobs\Ventas;

use App\Mail\Ventas\ArcaCaeaInformeResultadoMail;
use App\Models\Seguridad\Usuario;
use App\Models\Ventas\ArcaCaea;
use App\Services\Arca\ArcaCaeaPresentacionService;
use App\Support\Ventas\ArcaCaeaInformeMailSupport;
use App\Support\Ventas\ArcaCaeaInformeUiSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Un solo job por quincena CAEA: informa todos los comprobantes pendientes en lotes
 * (no un job por factura) y avisa por mail al usuario al terminar o fallar.
 */
class InformarArcaCaeaPeriodoJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    /** @var list<int> */
    public array $backoff;

    public int $timeout;

    public int $uniqueFor;

    public function __construct(
        public readonly int $arcaCaeaId,
        public readonly int $usuarioId,
        public readonly bool $soloErrores = false,
    ) {
        $this->tries = max(1, (int) config('arca.caea.informe_job_tries', 1));
        $this->backoff = array_map('intval', (array) config('arca.caea.informe_job_backoff_segundos', [120]));
        $this->timeout = max(120, (int) config('arca.caea.informe_job_timeout', 1800));
        $this->uniqueFor = max(300, (int) config('arca.caea.informe_job_unique_for', 7200));
        $this->onQueue((string) config('arca.caea.informe_cola', 'default'));
    }

    public function uniqueId(): string
    {
        return 'arca-caea-informe-'.$this->arcaCaeaId.($this->soloErrores ? '-errores' : '');
    }

    public function handle(ArcaCaeaPresentacionService $presentacion): void
    {
        $registro = ArcaCaea::query()->with('empresa')->find($this->arcaCaeaId);
        if ($registro === null) {
            Log::warning('arca.caea.informe.cola.sin_registro', [
                'arca_caea_id' => $this->arcaCaeaId,
                'usuario_id' => $this->usuarioId,
            ]);

            return;
        }

        $this->guardarProgreso($registro, [
            'fase' => 'inicio',
            'informados' => 0,
            'lotes' => 0,
        ]);

        Log::info('arca.caea.informe.cola.inicio', [
            'arca_caea_id' => $registro->id,
            'usuario_id' => $this->usuarioId,
            'solo_errores' => $this->soloErrores,
            'intento' => $this->attempts(),
            'timeout' => $this->timeout,
        ]);

        $agregado = $this->ejecutarPresentacion($presentacion, $registro);
        $this->guardarProgreso($registro, [
            'fase' => 'fin',
            'ok' => (bool) ($agregado['ok'] ?? false),
            'mensaje' => (string) ($agregado['mensaje'] ?? ''),
            'detalle' => is_array($agregado['detalle'] ?? null) ? $agregado['detalle'] : [],
            'resumen' => is_array($agregado['resumen'] ?? null) ? $agregado['resumen'] : [],
        ]);

        $this->enviarMailResultado($registro, $agregado, 'resultado');

        Log::info('arca.caea.informe.cola.fin', [
            'arca_caea_id' => $registro->id,
            'usuario_id' => $this->usuarioId,
            'ok' => (bool) ($agregado['ok'] ?? false),
            'lotes' => (int) ($agregado['detalle']['lotes'] ?? 0),
            'informados' => (int) ($agregado['detalle']['informados'] ?? 0),
        ]);
    }

    /**
     * @return array{ok: bool, mensaje: string, resumen?: array<string, mixed>, detalle?: array<string, mixed>}
     */
    private function ejecutarPresentacion(ArcaCaeaPresentacionService $presentacion, ArcaCaea $registro): array
    {
        $vaciar = (bool) config('arca.caea.informe_vaciar_pendientes', true);
        $maxLotes = max(1, (int) config('arca.caea.informe_max_lotes_job', 50));

        $informados = 0;
        $erroresLote = 0;
        $sincronizados = 0;
        $omitidosHueco = 0;
        $conObservaciones = 0;
        $lotes = 0;
        $ultimo = null;
        $okGlobal = true;

        do {
            $lotes++;
            $resultado = $presentacion->informarPeriodo(
                $registro->fresh(['empresa']) ?? $registro,
                $this->usuarioId,
                $this->soloErrores,
            );
            $ultimo = $resultado;
            $detalle = is_array($resultado['detalle'] ?? null) ? $resultado['detalle'] : [];

            $informados += (int) ($detalle['informados'] ?? 0);
            $erroresLote += (int) ($detalle['errores_lote'] ?? 0);
            $sincronizados += (int) ($detalle['sincronizados_arca'] ?? 0);
            $omitidosHueco += (int) ($detalle['omitidos_hueco_numeracion'] ?? 0);
            $conObservaciones += (int) ($detalle['con_observaciones'] ?? 0);

            $this->guardarProgreso($registro, [
                'fase' => 'lote',
                'lotes' => $lotes,
                'informados' => $informados,
                'ok' => (bool) ($resultado['ok'] ?? false),
                'mensaje' => (string) ($resultado['mensaje'] ?? ''),
                'detalle' => $detalle,
                'resumen' => is_array($resultado['resumen'] ?? null) ? $resultado['resumen'] : [],
            ]);

            if (! ($resultado['ok'] ?? false)) {
                $okGlobal = false;
                break;
            }

            if ((int) ($detalle['errores_lote'] ?? 0) > 0) {
                break;
            }

            $pendientes = (int) ($detalle['pendientes_restantes'] ?? 0);
            $erroresRestantes = (int) ($detalle['errores_total'] ?? 0);
            $quedan = $this->soloErrores ? $erroresRestantes > 0 : ($pendientes > 0 || $erroresRestantes > 0);

            if (! $vaciar || ! $quedan || (int) ($detalle['informados'] ?? 0) === 0) {
                break;
            }
        } while ($lotes < $maxLotes);

        $detalleFinal = is_array($ultimo['detalle'] ?? null) ? $ultimo['detalle'] : [];
        $detalleFinal['informados'] = $informados;
        $detalleFinal['errores_lote'] = $erroresLote;
        $detalleFinal['sincronizados_arca'] = $sincronizados;
        $detalleFinal['omitidos_hueco_numeracion'] = $omitidosHueco;
        $detalleFinal['con_observaciones'] = $conObservaciones;
        $detalleFinal['lotes'] = $lotes;

        // Reconsultar ARCA al cerrar: el resumen del último lote suele traer ultimo_arca del inicio.
        $resumenFinal = $presentacion->actualizarResumenPeriodo($registro, $this->usuarioId, true);
        $mensaje = (string) ($ultimo['mensaje'] ?? 'Presentación CAEA finalizada.');
        if ($lotes > 1) {
            $mensaje = trim($mensaje.' ('.$lotes.' lotes; informados acumulados: '.$informados.')');
        }

        $freno = ArcaCaeaInformeMailSupport::resolverFreno($detalleFinal, $resumenFinal, $mensaje);
        if ($freno !== null) {
            $detalleFinal['freno'] = $freno;
        }
        $ultimoInformado = ArcaCaeaInformeMailSupport::ultimoInformadoResumen($resumenFinal);
        if ($ultimoInformado !== null) {
            $detalleFinal['ultimo_informado'] = $ultimoInformado;
        }
        $cierre = ArcaCaeaInformeMailSupport::resolverCierreQuincena($registro, $detalleFinal, $resumenFinal);
        if ($cierre !== null) {
            $detalleFinal['cierre_quincena'] = $cierre;
        }

        return [
            'ok' => $okGlobal && (bool) ($ultimo['ok'] ?? false),
            'mensaje' => $mensaje,
            'resumen' => $resumenFinal,
            'detalle' => $detalleFinal,
        ];
    }

    /**
     * @param  array{ok: bool, mensaje: string, resumen?: array<string, mixed>, detalle?: array<string, mixed>}  $resultado
     */
    private function enviarMailResultado(ArcaCaea $registro, array $resultado, string $origen): void
    {
        $mailKey = $this->claveMailEnviado();
        if (Cache::get($mailKey)) {
            Log::info('arca.caea.informe.cola.mail_omitido_duplicado', [
                'arca_caea_id' => $registro->id,
                'origen' => $origen,
            ]);

            return;
        }

        $usuario = Usuario::query()->find($this->usuarioId);
        $email = trim((string) ($usuario?->email ?? ''));
        if ($usuario === null || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('arca.caea.informe.cola.sin_email', [
                'arca_caea_id' => $registro->id,
                'usuario_id' => $this->usuarioId,
            ]);

            return;
        }

        $detalle = is_array($resultado['detalle'] ?? null) ? $resultado['detalle'] : [];
        $resumen = is_array($resultado['resumen'] ?? null) ? $resultado['resumen'] : [];
        if (! isset($detalle['freno'])) {
            $freno = ArcaCaeaInformeMailSupport::resolverFreno(
                $detalle,
                $resumen,
                (string) ($resultado['mensaje'] ?? ''),
            );
            if ($freno !== null) {
                $detalle['freno'] = $freno;
            }
        }
        if (! isset($detalle['cierre_quincena'])) {
            $cierre = ArcaCaeaInformeMailSupport::resolverCierreQuincena($registro, $detalle, $resumen);
            if ($cierre !== null) {
                $detalle['cierre_quincena'] = $cierre;
            }
        }

        $payload = [
            'arca_caea_id' => $registro->id,
            'empresa' => $registro->empresa->nombre ?? '',
            'empresa_id' => (int) ($registro->empresa_id ?? 0),
            'quincena' => ArcaCaeaInformeUiSupport::etiquetaQuincena($registro),
            'ok' => (bool) ($resultado['ok'] ?? false),
            'mensaje' => (string) ($resultado['mensaje'] ?? ''),
            'detalle' => $detalle,
            'resumen' => $resumen,
            'usuario_nombre' => (string) ($usuario->nombre ?? $usuario->usuario ?? ''),
            'origen_mail' => $origen,
        ];

        try {
            Mail::to($email)->send(new ArcaCaeaInformeResultadoMail($payload));
            Cache::put($mailKey, true, now()->addHours(6));
        } catch (Throwable $e) {
            Log::error('arca.caea.informe.cola.mail_fallo', [
                'arca_caea_id' => $registro->id,
                'usuario_id' => $this->usuarioId,
                'email' => $email,
                'origen' => $origen,
                'msg' => $e->getMessage(),
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $msgEx = $exception !== null ? $exception->getMessage() : 'Job informe CAEA falló en cola';
        Log::error('arca.caea.informe.cola.job_fallo', [
            'arca_caea_id' => $this->arcaCaeaId,
            'usuario_id' => $this->usuarioId,
            'solo_errores' => $this->soloErrores,
            'msg' => $msgEx,
        ]);

        if (Cache::get($this->claveMailEnviado())) {
            return;
        }

        try {
            $registro = ArcaCaea::query()->with('empresa')->find($this->arcaCaeaId);
            if ($registro === null) {
                return;
            }

            $progreso = Cache::get($this->claveProgreso(), []);
            $detalle = is_array($progreso['detalle'] ?? null) ? $progreso['detalle'] : [];
            $resumen = is_array($progreso['resumen'] ?? null) ? $progreso['resumen'] : [];
            $informados = (int) ($progreso['informados'] ?? $detalle['informados'] ?? 0);
            $lotes = (int) ($progreso['lotes'] ?? $detalle['lotes'] ?? 0);

            $motivoWorker = $this->explicarFalloWorker($msgEx);
            $mensajeProgreso = trim((string) ($progreso['mensaje'] ?? ''));
            $freno = ArcaCaeaInformeMailSupport::resolverFreno($detalle, $resumen, $mensajeProgreso);

            $mensaje = $motivoWorker;
            if ($informados > 0) {
                $mensaje .= ' Antes del corte se habían informado '.$informados.' comprobante(s)';
                if ($lotes > 0) {
                    $mensaje .= ' en '.$lotes.' lote(s)';
                }
                $mensaje .= '.';
            }
            if ($freno !== null) {
                $mensaje .= ' Último freno detectado: '.$freno['etiqueta'].' — '.$freno['mensaje'];
                $detalle['freno'] = $freno;
            } elseif ($mensajeProgreso !== '') {
                $mensaje .= ' Último estado: '.$mensajeProgreso;
            }

            $detalle['informados'] = $informados;
            $detalle['lotes'] = $lotes;
            $detalle['errores_lote'] = max(1, (int) ($detalle['errores_lote'] ?? 0));
            $detalle['fallo_worker'] = true;
            $detalle['fallo_worker_msg'] = $msgEx;
            $ultimoInformado = ArcaCaeaInformeMailSupport::ultimoInformadoResumen($resumen);
            if ($ultimoInformado !== null) {
                $detalle['ultimo_informado'] = $ultimoInformado;
            }

            $this->enviarMailResultado($registro, [
                'ok' => false,
                'mensaje' => $mensaje,
                'detalle' => $detalle,
                'resumen' => $resumen,
            ], 'failed');
        } catch (Throwable $e) {
            Log::error('arca.caea.informe.cola.mail_fallo_failed', [
                'arca_caea_id' => $this->arcaCaeaId,
                'msg' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function guardarProgreso(ArcaCaea $registro, array $data): void
    {
        $prev = Cache::get($this->claveProgreso(), []);
        if (! is_array($prev)) {
            $prev = [];
        }
        Cache::put($this->claveProgreso(), array_merge($prev, $data, [
            'arca_caea_id' => $registro->id,
            'updated_at' => now()->toIso8601String(),
        ]), now()->addHours(6));
    }

    private function claveProgreso(): string
    {
        return 'arca-caea-informe-progreso-'.$this->arcaCaeaId.'-'.$this->usuarioId;
    }

    private function claveMailEnviado(): string
    {
        return 'arca-caea-informe-mail-'.$this->arcaCaeaId.'-'.$this->usuarioId;
    }

    private function explicarFalloWorker(string $msgEx): string
    {
        if (str_contains($msgEx, 'attempted too many times') || str_contains($msgEx, 'MaxAttemptsExceeded')) {
            return 'El worker de cola cortó el proceso (timeout o reintento por retry_after). '
                .'No es un rechazo de ARCA: el job de la quincena sigue siendo uno solo; hay que subir el timeout del worker '
                .'y QUEUE_RETRY_AFTER por encima del timeout del job.';
        }

        if (stripos($msgEx, 'timeout') !== false) {
            return 'El proceso superó el tiempo máximo del worker. Revisá ARCA_CAEA_INFORME_JOB_TIMEOUT y el --timeout del queue:work.';
        }

        return 'El proceso en segundo plano falló: '.$msgEx;
    }
}
