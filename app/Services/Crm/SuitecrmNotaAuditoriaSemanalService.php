<?php

namespace App\Services\Crm;

use App\Mail\Ventas\SuitecrmNotaAuditoriaSemanalMail;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\SuitecrmNotaAuditoriaListadoFiltros;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SuitecrmNotaAuditoriaSemanalService
{
    public function __construct(
        private readonly SuitecrmNotaAuditoriaService $auditoriaService,
    ) {}

    /**
     * @return array{
     *     omitido: bool,
     *     motivo:?string,
     *     fecha_desde:?string,
     *     fecha_hasta:?string,
     *     total: int,
     *     destinos: list<string>,
     *     mail_enviado: bool,
     *     mail_destino:?string,
     *     mail_error:?string,
     *     pdf_generado: bool,
     *     dry_run: bool
     * }
     */
    public function ejecutar(
        ?string $desde = null,
        ?string $hasta = null,
        bool $enviarMail = true,
        bool $dryRun = false,
    ): array {
        $base = [
            'omitido' => false,
            'motivo' => null,
            'fecha_desde' => null,
            'fecha_hasta' => null,
            'total' => 0,
            'destinos' => [],
            'mail_enviado' => false,
            'mail_destino' => null,
            'mail_error' => null,
            'pdf_generado' => false,
            'dry_run' => $dryRun,
        ];

        if (! EntornoEmpresaSupport::esInterforming()) {
            return array_merge($base, [
                'omitido' => true,
                'motivo' => 'Solo aplica en entorno INTERFORMING (EMPRESA='.EntornoEmpresaSupport::codigo().').',
            ]);
        }

        if (! (bool) config('suitecrm.auditoria_semanal.habilitada', true)) {
            return array_merge($base, [
                'omitido' => true,
                'motivo' => 'Auditoría semanal deshabilitada (suitecrm.auditoria_semanal.habilitada).',
            ]);
        }

        if (! $this->auditoriaService->isHabilitado()) {
            return array_merge($base, [
                'omitido' => true,
                'motivo' => 'Integración SuiteCRM deshabilitada (SUITECRM_HABILITADO).',
            ]);
        }

        [$fechaDesde, $fechaHasta] = $this->resolverRango($desde, $hasta);
        $filtros = array_merge(SuitecrmNotaAuditoriaListadoFiltros::filtrosVacios(), [
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
        ]);

        // Mail gerencial: todas las notas de vendedores, incl. creadas por supervisor.
        $resultado = $this->auditoriaService->generar($filtros, true);
        $filas = $resultado['filas'];
        $agrupadas = $resultado['agrupadas_por_fecha'];
        $total = $resultado['total'];
        $titulo = 'Auditoría de notas CRM';
        $subtitulo = $this->auditoriaService->armarRangoFechasSubtitulo($filtros, $filas);

        $base['fecha_desde'] = $fechaDesde;
        $base['fecha_hasta'] = $fechaHasta;
        $base['total'] = $total;

        $enviarSiVacio = (bool) config('suitecrm.auditoria_semanal.enviar_si_vacio', true);
        if ($total === 0 && ! $enviarSiVacio) {
            return array_merge($base, [
                'omitido' => true,
                'motivo' => 'Sin notas en el rango y SUITECRM_AUDITORIA_SEMANAL_ENVIAR_SI_VACIO=false.',
            ]);
        }

        $pdfBinario = $this->generarPdf($filas, $agrupadas, $titulo, $subtitulo, $total);
        $base['pdf_generado'] = $pdfBinario !== '';
        $nombrePdf = sprintf(
            'auditoria_notas_crm_%s_%s.pdf',
            str_replace('-', '', $fechaDesde),
            str_replace('-', '', $fechaHasta)
        );

        $destinos = $this->destinos();
        $base['destinos'] = $destinos;

        if ($dryRun || ! $enviarMail) {
            return $base;
        }

        if ($destinos === []) {
            return array_merge($base, [
                'omitido' => true,
                'motivo' => 'Sin emails en SUITECRM_AUDITORIA_SEMANAL_EMAILS.',
            ]);
        }

        try {
            Mail::to($destinos)->send(new SuitecrmNotaAuditoriaSemanalMail(
                fechaDesde: $fechaDesde,
                fechaHasta: $fechaHasta,
                totalNotas: $total,
                pdfContenido: $pdfBinario,
                nombreArchivoPdf: $nombrePdf,
            ));
            $base['mail_enviado'] = true;
            $base['mail_destino'] = implode(', ', $destinos);
        } catch (\Throwable $e) {
            Log::error('suitecrm:auditoria-notas-semanal mail falló', [
                'error' => $e->getMessage(),
                'desde' => $fechaDesde,
                'hasta' => $fechaHasta,
                'total' => $total,
            ]);
            $base['mail_error'] = $e->getMessage();
        }

        return $base;
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function resolverRango(?string $desde, ?string $hasta): array
    {
        $ventana = max(1, (int) config('suitecrm.auditoria_semanal.ventana_dias', 7));
        $hastaCarbon = ($hasta !== null && $hasta !== '')
            ? Carbon::parse($hasta)->startOfDay()
            : Carbon::today();
        $desdeCarbon = ($desde !== null && $desde !== '')
            ? Carbon::parse($desde)->startOfDay()
            : $hastaCarbon->copy()->subDays($ventana - 1);

        return [$desdeCarbon->toDateString(), $hastaCarbon->toDateString()];
    }

    /**
     * @return list<string>
     */
    public function destinos(): array
    {
        $raw = (string) config('suitecrm.auditoria_semanal.emails', '');
        $out = [];
        foreach (explode(',', $raw) as $email) {
            $email = trim($email);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $out[] = $email;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, list<array<string, mixed>>>  $agrupadas
     */
    private function generarPdf(
        array $filas,
        array $agrupadas,
        string $titulo,
        string $subtitulo,
        int $totalFilas,
    ): string {
        $view = \View::make('ventas.suitecrm_nota_auditoria.listado', compact(
            'filas',
            'agrupadas',
            'titulo',
            'subtitulo',
            'totalFilas',
        ))->render();

        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('a4', 'landscape');
        $pdf->loadHTML($view);

        return $pdf->output();
    }
}
