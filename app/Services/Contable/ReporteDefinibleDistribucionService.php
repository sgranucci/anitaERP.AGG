<?php

namespace App\Services\Contable;

use App\Exports\Contable\ReporteDefinibleExport;
use App\Mail\Contable\ReporteDefinibleDistribucion;
use App\Models\Contable\ReporteContableSuscripcion;
use App\Support\Contable\ReporteDefinible\ReporteDefiniblePublicacionSupport;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleSuscripcionSupport;
use App\Support\Contable\SumasSaldos\SumasSaldosRuntimeSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Excel;

/**
 * Distribución automática: ejecuta el informe, arma los adjuntos y lo manda por mail.
 */
class ReporteDefinibleDistribucionService
{
    public function __construct(
        private ReporteDefinibleReporteService $reporteService,
        private ReporteDefinibleSuscripcionSupport $suscripcionSupport,
        private ReporteDefiniblePublicacionSupport $publicacionSupport,
    ) {}

    /**
     * @return array{estado: string, mensaje: string, destinatarios: list<string>, filas: int, adjuntos: list<string>}
     */
    public function enviar(ReporteContableSuscripcion $suscripcion, bool $dryRun = false, ?Carbon $ahora = null): array
    {
        $ahora = $ahora ?? Carbon::now();
        $destinatarios = $this->suscripcionSupport->destinatariosResueltos($suscripcion);

        if ($destinatarios === []) {
            return $this->resultado(
                ReporteContableSuscripcion::ESTADO_ERROR,
                'Sin destinatarios válidos: revisá los mails y los usuarios elegidos.',
                []
            );
        }

        SumasSaldosRuntimeSupport::elevarLimites();

        $filtros = $this->suscripcionSupport->filtrosEfectivos($suscripcion, $ahora);
        $filtros['reporte_contable_id'] = (int) $suscripcion->reporte_contable_id;

        $resultado = $this->reporteService->ejecutar((int) $suscripcion->reporte_contable_id, $filtros);
        $reporte = $resultado['reporte'] ?? null;
        $filas = is_array($resultado['filas'] ?? null) ? count($resultado['filas']) : 0;

        if ($reporte === null || $filas === 0) {
            return $this->resultado(
                ReporteContableSuscripcion::ESTADO_ERROR,
                'El informe no devolvió filas para el período; no se envió nada.',
                $destinatarios
            );
        }

        $avisos = array_values(array_filter((array) ($resultado['advertencias'] ?? [])));
        if ($suscripcion->solo_si_alertas && $avisos === []) {
            return $this->resultado(
                ReporteContableSuscripcion::ESTADO_OMITIDA,
                'Sin avisos en la corrida: la suscripción está configurada para enviar solo cuando hay algo que mirar.',
                $destinatarios,
                $filas
            );
        }

        $periodoTexto = $this->reporteService->formatearPeriodoTexto($filtros);
        $publicacion = null;

        if ($suscripcion->publicar && ! $dryRun) {
            $publicacion = $this->publicacionSupport->publicar(
                $reporte,
                $filtros,
                $resultado,
                $suscripcion->usuario_id !== null ? (int) $suscripcion->usuario_id : null,
                'Envío automático '.$suscripcion->nombre.' '.$ahora->format('d/m/Y'),
                'Generado por la distribución automática «'.$suscripcion->nombre.'».',
                $periodoTexto,
            );
        }

        $adjuntos = $this->adjuntos($suscripcion, $reporte, $resultado, $filtros, $ahora);

        if ($dryRun) {
            $this->limpiar($adjuntos);

            return $this->resultado(
                ReporteContableSuscripcion::ESTADO_OK,
                sprintf('Simulación: %d fila(s), %d adjunto(s), iría a %s.', $filas, count($adjuntos), implode(', ', $destinatarios)),
                $destinatarios,
                $filas,
                array_map(fn (array $a) => (string) $a['nombre'], $adjuntos)
            );
        }

        try {
            Mail::to($destinatarios)->send(new ReporteDefinibleDistribucion(
                $suscripcion,
                $reporte,
                $periodoTexto,
                $resultado,
                $adjuntos,
                $publicacion,
            ));
        } catch (\Throwable $e) {
            Log::error('Distribución reporte definible falló', [
                'suscripcion_id' => $suscripcion->id,
                'error' => $e->getMessage(),
            ]);

            $this->limpiar($adjuntos);

            return $this->resultado(
                ReporteContableSuscripcion::ESTADO_ERROR,
                'No se pudo enviar el mail: '.$e->getMessage(),
                $destinatarios,
                $filas
            );
        }

        $nombres = array_map(fn (array $a) => (string) $a['nombre'], $adjuntos);
        $this->limpiar($adjuntos);

        return $this->resultado(
            ReporteContableSuscripcion::ESTADO_OK,
            sprintf(
                'Enviado a %s (%d fila(s), %s)%s.',
                implode(', ', $destinatarios),
                $filas,
                $nombres === [] ? 'sin adjuntos' : implode(' + ', $nombres),
                $publicacion !== null ? ' y publicado como «'.$publicacion->nombre.'»' : ''
            ),
            $destinatarios,
            $filas,
            $nombres
        );
    }

    /**
     * Archivos temporales a adjuntar.
     *
     * @param  array<string, mixed>  $resultado
     * @param  array<string, mixed>  $filtros
     * @return list<array{path: string, nombre: string, mime: string}>
     */
    private function adjuntos(
        ReporteContableSuscripcion $suscripcion,
        mixed $reporte,
        array $resultado,
        array $filtros,
        Carbon $ahora,
    ): array {
        $dir = storage_path('app/reporte_definible_envios');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $base = 'informe_'.$reporte->codigo.'_'.$ahora->format('Ymd_His');
        $formato = (string) $suscripcion->formato;
        $adjuntos = [];

        if (in_array($formato, [ReporteContableSuscripcion::FORMATO_PDF, ReporteContableSuscripcion::FORMATO_AMBOS], true)) {
            $path = $dir.'/'.$base.'.pdf';
            \PDF::loadView('contable.reporte_definible.listado', [
                'reporte' => $reporte,
                'resultado' => $resultado,
                'filtros' => $filtros,
                'periodo_texto' => $this->reporteService->formatearPeriodoTexto($filtros),
            ])->setPaper('legal', 'landscape')->save($path);

            $adjuntos[] = ['path' => $path, 'nombre' => $base.'.pdf', 'mime' => 'application/pdf'];
        }

        if (in_array($formato, [ReporteContableSuscripcion::FORMATO_EXCEL, ReporteContableSuscripcion::FORMATO_AMBOS], true)) {
            $relativo = 'reporte_definible_envios/'.$base.'.xlsx';
            (new ReporteDefinibleExport())
                ->parametros($filtros, $resultado, $reporte)
                ->store($relativo, 'local', Excel::XLSX);

            $adjuntos[] = [
                'path' => storage_path('app/'.$relativo),
                'nombre' => $base.'.xlsx',
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ];
        }

        return array_values(array_filter($adjuntos, fn (array $a) => is_file($a['path'])));
    }

    /**
     * @param  list<array{path: string, nombre: string, mime: string}>  $adjuntos
     */
    private function limpiar(array $adjuntos): void
    {
        foreach ($adjuntos as $adjunto) {
            if (is_file($adjunto['path'])) {
                @unlink($adjunto['path']);
            }
        }
    }

    /**
     * @param  list<string>  $destinatarios
     * @param  list<string>  $adjuntos
     * @return array{estado: string, mensaje: string, destinatarios: list<string>, filas: int, adjuntos: list<string>}
     */
    private function resultado(
        string $estado,
        string $mensaje,
        array $destinatarios,
        int $filas = 0,
        array $adjuntos = [],
    ): array {
        return [
            'estado' => $estado,
            'mensaje' => $mensaje,
            'destinatarios' => $destinatarios,
            'filas' => $filas,
            'adjuntos' => $adjuntos,
        ];
    }
}
