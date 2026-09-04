<?php

declare(strict_types=1);

namespace App\Jobs\Contable;

use App\Mail\Contable\MayorPlanoCuentaListoMail;
use App\Models\Seguridad\Usuario;
use App\Services\Contable\MayorPlanoCuentaReporteService;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaCacheSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaCsvExportSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaRuntimeSupport;
use App\Support\Contable\MayorPlanoCuentaListadoFiltros;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Mayor plano de período largo: genera en worker el Excel plano (CSV enriquecido) y avisa por mail.
 * Un mes (o rango corto) sigue saliendo en pantalla; no usa este job.
 */
class GenerarMayorPlanoCuentaJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public int $uniqueFor;

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function __construct(
        public readonly array $filtros,
        public readonly int $usuarioId,
    ) {
        $this->timeout = max(600, (int) config('contable.mayor_plano_cuenta.async_job_timeout', 1800));
        $this->uniqueFor = max(600, (int) config('contable.mayor_plano_cuenta.async_unique_for', 7200));
        $this->onQueue((string) config('contable.mayor_plano_cuenta.async_cola', 'reports'));
    }

    public function uniqueId(): string
    {
        return 'mayor-plano-'.$this->usuarioId.'-'.MayorPlanoCuentaListadoFiltros::firma($this->filtros);
    }

    public function handle(MayorPlanoCuentaReporteService $reporteService): void
    {
        MayorPlanoCuentaRuntimeSupport::elevarLimites();
        @ini_set('memory_limit', (string) config('contable.mayor_plano_cuenta.memory_limit', '4096M'));
        @set_time_limit($this->timeout);

        $usuario = Usuario::query()->find($this->usuarioId);
        $email = trim((string) ($usuario->email ?? ''));
        $periodo = $reporteService->formatearPeriodoTexto($this->filtros);
        $empresas = $reporteService->formatearEmpresasTexto($this->filtros);

        Log::info('mayor_plano_cuenta.async.inicio', [
            'usuario_id' => $this->usuarioId,
            'firma' => MayorPlanoCuentaListadoFiltros::firma($this->filtros),
            'periodo' => $periodo,
        ]);

        try {
            $resultado = MayorPlanoCuentaCacheSupport::recuperar($this->filtros, $this->usuarioId);
            if ($resultado === null) {
                $resultado = $reporteService->generarDesdeFiltros($this->filtros);
                MayorPlanoCuentaCacheSupport::guardar($resultado, $this->filtros, $this->usuarioId);
            } else {
                Log::info('mayor_plano_cuenta.async.cache_hit', [
                    'usuario_id' => $this->usuarioId,
                    'lineas' => (int) ($resultado['totales']['lineas'] ?? 0),
                ]);
            }

            $lineas = (int) ($resultado['totales']['lineas'] ?? 0);
            $stamp = now()->format('Ymd_His');
            $firmaCorta = substr(MayorPlanoCuentaListadoFiltros::firma($this->filtros), 0, 8);
            $nombreArchivo = 'mayor_plano_excel_'.$stamp.'_u'.$this->usuarioId.'_'.$firmaCorta.'.csv';
            $rutaRelativa = 'exports/mayor_plano_async/'.$nombreArchivo;
            $rutaAbsoluta = storage_path('app/public/'.$rutaRelativa);

            // Mismo layout que “Excel plano” en pantalla (emisor, OC, CAPEX, facturas; sin IA).
            $export = MayorPlanoCuentaCsvExportSupport::escribirCsvExcelPlano(
                $reporteService,
                $resultado,
                $this->filtros,
                $rutaAbsoluta,
            );

            $url = urlAppAbsoluta('storage/'.$rutaRelativa);
            $maxAdjunto = max(1, (int) config('contable.mayor_plano_cuenta.async_adjunto_max_mb', 8)) * 1048576;
            $adjunto = ($export['bytes'] > 0 && $export['bytes'] <= $maxAdjunto)
                ? $export['path']
                : '';

            $this->enviarMail($email, [
                'ok' => true,
                'periodo' => $periodo,
                'empresas' => $empresas,
                'lineas' => $lineas,
                'mensaje' => $adjunto !== ''
                    ? 'Adjuntamos el Excel plano en CSV (emisor, OC, CAPEX, facturas); también está el enlace.'
                    : 'Excel plano en CSV listo (emisor, OC, CAPEX, facturas). El archivo supera el límite de adjunto; descargalo con el enlace.',
                'url_descarga' => $url,
                'nombre_archivo' => $nombreArchivo,
                'adjunto_path' => $adjunto,
                'usuario_nombre' => (string) ($usuario->nombre ?? ''),
            ]);

            Log::info('mayor_plano_cuenta.async.fin', [
                'usuario_id' => $this->usuarioId,
                'lineas' => $lineas,
                'bytes' => $export['bytes'],
                'archivo' => $rutaRelativa,
            ]);
        } catch (Throwable $e) {
            Log::error('mayor_plano_cuenta.async.error', [
                'usuario_id' => $this->usuarioId,
                'error' => $e->getMessage(),
            ]);

            $this->enviarMail($email, [
                'ok' => false,
                'periodo' => $periodo,
                'empresas' => $empresas,
                'lineas' => 0,
                'mensaje' => 'Error: '.$e->getMessage(),
                'usuario_nombre' => (string) ($usuario->nombre ?? ''),
            ]);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function enviarMail(string $email, array $datos): void
    {
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('mayor_plano_cuenta.async.sin_email', [
                'usuario_id' => $this->usuarioId,
            ]);

            return;
        }

        Mail::to($email)->send(new MayorPlanoCuentaListoMail($datos));
    }

    public function failed(?Throwable $e): void
    {
        Log::error('mayor_plano_cuenta.async.failed', [
            'usuario_id' => $this->usuarioId,
            'error' => $e?->getMessage(),
        ]);
    }
}
