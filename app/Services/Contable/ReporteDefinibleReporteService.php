<?php

namespace App\Services\Contable;

use App\Models\Contable\ReporteContable;
use App\Repositories\Contable\ReporteContableRepository;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleAlertaSupport;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleCoberturaSupport;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleNotaSupport;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleProcesador;
use App\Support\Contable\SumasSaldos\SumasSaldosRuntimeSupport;

class ReporteDefinibleReporteService
{
    public function __construct(
        private readonly ReporteContableRepository $repository,
        private readonly ReporteDefinibleProcesador $procesador,
        private readonly ReporteDefinibleAlertaSupport $alertaSupport,
        private readonly ReporteDefinibleCoberturaSupport $coberturaSupport,
        private readonly ReporteDefinibleNotaSupport $notaSupport,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function ejecutar(int $reporteId, array $filtros): array
    {
        SumasSaldosRuntimeSupport::elevarLimites();

        $reporte = $this->repository->findConEstructura($reporteId);
        if (! $reporte) {
            return [
                'columnas' => [],
                'filas' => [],
                'advertencias' => ['Informe no encontrado.'],
                'alertas' => [],
                'notas' => [],
                'notas_marcas' => [],
                'fuente' => 'ninguna',
                'reporte' => null,
            ];
        }

        $resultado = $this->procesador->ejecutar($reporte, $filtros);
        $resultado['reporte'] = $reporte;

        $notas = $this->notaSupport->paraResultado((int) $reporte->id, $resultado);
        $resultado['notas'] = $notas['notas'];
        $resultado['notas_marcas'] = $notas['marcas'];

        if ($this->alertaSupport->requiereCobertura((int) $reporte->id)) {
            $cobertura = $this->coberturaSupport->analizar($reporte, (array) ($filtros['empresa_ids'] ?? []));
            $resultado['cobertura_rota'] = ((int) ($cobertura['huerfanas_total'] ?? 0)) > 0
                || ($cobertura['duplicadas'] ?? []) !== [];
        }
        $alertas = $this->alertaSupport->evaluar($reporte, $resultado);
        $resultado['alertas'] = $alertas;
        if ($alertas !== []) {
            foreach ($alertas as $msg) {
                $resultado['advertencias'][] = $msg;
            }
            $resultado['advertencias'] = array_values(array_unique($resultado['advertencias'] ?? []));
        }

        return $resultado;
    }

    public function formatearPeriodoTexto(array $filtros): string
    {
        if (($filtros['modo_periodo'] ?? '') === \App\Support\Contable\SumasSaldosListadoFiltros::MODO_RANGO) {
            $fd = trim((string) ($filtros['fecha_desde'] ?? ''));
            $fh = trim((string) ($filtros['fecha_hasta'] ?? ''));
            if ($fd !== '' && $fh !== '') {
                $d = \DateTime::createFromFormat('Y-m-d', $fd);
                $h = \DateTime::createFromFormat('Y-m-d', $fh);

                return ($d && $h)
                    ? $d->format('d/m/Y').' — '.$h->format('d/m/Y')
                    : $fd.' — '.$fh;
            }
        }

        $md = (int) ($filtros['mes_desde'] ?? 0);
        $ad = (int) ($filtros['anio_desde'] ?? 0);
        $mh = (int) ($filtros['mes_hasta'] ?? 0);
        $ah = (int) ($filtros['anio_hasta'] ?? 0);
        if ($md === $mh && $ad === $ah) {
            return sprintf('%02d/%04d', $md, $ad);
        }

        return sprintf('%02d/%04d — %02d/%04d', $md, $ad, $mh, $ah);
    }
}
