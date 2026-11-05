<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\Ventas\ArcaCaeaInformeResultadoMail;
use App\Models\Ventas\ArcaCaea;
use App\Services\Arca\ArcaCaeaPresentacionService;
use App\Services\Arca\ArcaCaeaQuincenalOrquestadorService;
use App\Support\Configuracion\ParametroSistemaSupport;
use App\Support\Ventas\ArcaCaeaAnitaVencaeConsultaSupport;
use App\Support\Ventas\ArcaCaeaInformeUiSupport;
use App\Support\Ventas\ArcaCaeaPresentacionCalendarioSupport;
use App\Support\Ventas\CaeaQuincenaSupport;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class PresentarCaeaQuincena extends Command
{
    protected $signature = 'arca:presentar-caea-quincena {--fecha= : Fecha a evaluar (Y-m-d). Por defecto, hoy}';

    protected $description = 'Presenta el CAEA el primer día hábil después del fin de cada quincena';

    public function handle(
        ArcaCaeaQuincenalOrquestadorService $orquestador,
        ArcaCaeaPresentacionService $presentacion,
    ): int {
        $fecha = $this->option('fecha');
        $hoy = ($fecha !== null && $fecha !== '')
            ? Carbon::parse((string) $fecha)->startOfDay()
            : now()->startOfDay();

        $quincenas = ArcaCaeaPresentacionCalendarioSupport::quincenasDelDia($hoy);
        if ($quincenas === []) {
            $this->info($hoy->format('d/m/Y').' no es día de presentación CAEA.');

            return self::SUCCESS;
        }

        $empresas = $orquestador->empresasConPvCaea();
        if ($empresas->isEmpty()) {
            $this->info('No hay empresas con punto de venta CAEA.');

            return self::SUCCESS;
        }

        $fallos = 0;
        foreach ($empresas as $empresa) {
            foreach ($quincenas as $q) {
                $fallos += $this->presentarEmpresa(
                    $presentacion,
                    (int) $empresa->id,
                    (string) ($empresa->nombre ?? ''),
                    (int) $q['periodo'],
                    (int) $q['orden'],
                );
            }
        }

        return $fallos > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function presentarEmpresa(
        ArcaCaeaPresentacionService $presentacion,
        int $empresaId,
        string $empresaNombre,
        int $periodo,
        int $orden,
    ): int {
        $etiqueta = CaeaQuincenaSupport::etiquetaQuincena($periodo, $orden);
        $registro = ArcaCaea::query()
            ->with('empresa')
            ->where('empresa_id', $empresaId)
            ->where('periodo', $periodo)
            ->where('orden', $orden)
            ->first();

        if ($registro === null || ! $registro->estaAutorizado()) {
            $this->warn($empresaNombre.' — '.$etiqueta.': no hay CAEA autorizado.');
            Log::warning('arca.caea.presentacion.automatica.sin_caea', [
                'empresa_id' => $empresaId,
                'periodo' => $periodo,
                'orden' => $orden,
            ]);

            return 1;
        }

        $resumen = is_array($registro->informe_resumen) ? $registro->informe_resumen : [];
        $pendientes = (int) ($resumen['pendientes'] ?? 0);
        $estado = (string) ($registro->informe_estado ?? '');
        if (in_array($estado, [ArcaCaea::INFORME_ESTADO_OK, ArcaCaea::INFORME_ESTADO_OBSERVACION], true) && $pendientes === 0) {
            $this->line($empresaNombre.' — '.$etiqueta.': ya presentada.');

            return 0;
        }

        $this->info($empresaNombre.' — '.$etiqueta.': presentando.');
        ArcaCaeaAnitaVencaeConsultaSupport::resetCache();
        ArcaCaeaAnitaVencaeConsultaSupport::fijarTimeoutProceso(
            (int) config('arca.caea.informe_anita_timeout', 45),
        );

        try {
            $resultado = $presentacion->informarQuincenaCompleta($registro, null, false);
        } catch (Throwable $e) {
            $this->error($empresaNombre.' — '.$etiqueta.': '.$e->getMessage());
            Log::error('arca.caea.presentacion.automatica.fallo', [
                'arca_caea_id' => $registro->id,
                'msg' => $e->getMessage(),
            ]);

            return 1;
        }

        $this->line((string) ($resultado['mensaje'] ?? ''));
        $this->enviarMail($registro->fresh(['empresa']) ?? $registro, $resultado);

        return ($resultado['ok'] ?? false) ? 0 : 1;
    }

    /**
     * @param  array{ok?: bool, mensaje?: string, resumen?: array<string, mixed>, detalle?: array<string, mixed>}  $resultado
     */
    private function enviarMail(ArcaCaea $registro, array $resultado): void
    {
        $emails = ParametroSistemaSupport::caeaPresentacionMails();
        if ($emails === []) {
            $this->warn('Sin destinatarios en Configuración general. El acta quedó guardada.');

            return;
        }

        $detalle = is_array($resultado['detalle'] ?? null) ? $resultado['detalle'] : [];
        $resumen = is_array($resultado['resumen'] ?? null) ? $resultado['resumen'] : [];
        $payload = [
            'arca_caea_id' => (int) $registro->id,
            'empresa' => $registro->empresa->nombre ?? '',
            'empresa_id' => (int) ($registro->empresa_id ?? 0),
            'quincena' => ArcaCaeaInformeUiSupport::etiquetaQuincena($registro),
            'ok' => (bool) ($resultado['ok'] ?? false),
            'mensaje' => (string) ($resultado['mensaje'] ?? ''),
            'detalle' => $detalle,
            'resumen' => $resumen,
            'usuario_nombre' => '',
            'origen_mail' => 'cron',
        ];

        try {
            Mail::to($emails)->send(new ArcaCaeaInformeResultadoMail($payload));
            $this->info('Mail enviado a '.implode(', ', $emails));
        } catch (Throwable $e) {
            $this->error('No se pudo enviar el mail: '.$e->getMessage());
            Log::error('arca.caea.presentacion.automatica.mail_fallo', [
                'arca_caea_id' => $registro->id,
                'msg' => $e->getMessage(),
            ]);
        }
    }
}
