<?php

declare(strict_types=1);

namespace App\Services\Contable\CanonEntidades;

use App\Models\Configuracion\Empresa;
use App\Support\Contable\CanonEntidades\CanonEntidadesCalculoSupport;
use App\Support\Contable\CanonEntidades\CanonEntidadesCuentaSupport;
use App\Support\Contable\CanonEntidades\CanonEntidadesListadoFiltros;
use App\Support\Contable\CanonEntidades\CanonEntidadesReglasSupport;
use App\Support\Contable\FlashContableReporteSupport;
use Carbon\Carbon;

final class CanonEntidadesReporteService
{
    public function __construct(
        private readonly CanonEntidadesConciliacionService $conciliacionService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generar(array $filtros): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $desde = (string) ($filtros['fecha_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_hasta'] ?? '');

        $empresa = $empresaId > 0 ? Empresa::query()->find($empresaId) : null;
        $nombre = trim((string) ($empresa->nombre ?? ''));
        $cuit = trim((string) ($empresa->nroinscripcion ?? ''));
        $identidad = CanonEntidadesReglasSupport::resolver($cuit, $nombre);
        $identidad['empresa_id'] = $empresaId;
        $identidad['nombre'] = $nombre !== '' ? $nombre : ('#'.$empresaId);
        $identidad['cuit_formato'] = CanonEntidadesReglasSupport::formatearCuit($cuit);
        $identidad['domicilio'] = trim((string) ($empresa?->domicilio ?? ''));

        if ($empresa === null) {
            return [
                'mensaje' => 'Empresa no encontrada.',
                'identidad' => $identidad,
                'filas' => [],
                'totales' => [],
                'conciliacion' => ['habilitada' => false],
            ];
        }

        $flashes = FlashContableReporteSupport::cargarRango([$empresaId], $desde, $hasta);
        $flashReporte = FlashContableReporteSupport::armarDesdeFlashes(
            $flashes,
            [$empresaId],
            [$empresaId => $nombre],
            Carbon::parse($desde)->startOfDay(),
            Carbon::parse($hasta)->startOfDay(),
        );
        $dias = CanonEntidadesCalculoSupport::diasDesdeFlashContable(
            $flashReporte['filas'] ?? [],
            $empresaId,
        );
        $calculo = CanonEntidadesCalculoSupport::calcular($dias, (string) $identidad['regla']);
        $totales = $calculo['totales'];
        $cuenta = CanonEntidadesCuentaSupport::resolver($empresaId);
        $conciliacion = $this->conciliacionService->conciliar(
            $empresaId,
            $desde,
            $hasta,
            (float) $totales['canon_total'],
            $cuenta,
        );
        $calculo['filas'] = CanonEntidadesCalculoSupport::anexarHaberDiario(
            $calculo['filas'],
            $conciliacion['movimientos'] ?? [],
        );

        $mensaje = null;
        if (! $identidad['reconocida']) {
            $mensaje = 'La empresa no coincide con Biyemas, Kandiko ni Rebisco: se aplica bingo 1% plano.';
        }

        return [
            'mensaje' => $mensaje,
            'identidad' => $identidad,
            'filas' => $calculo['filas'],
            'totales' => $totales,
            'conciliacion' => $conciliacion,
            'periodo_texto' => CanonEntidadesListadoFiltros::formatearPeriodoTexto($filtros),
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
        ];
    }
}
