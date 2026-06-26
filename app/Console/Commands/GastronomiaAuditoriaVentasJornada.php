<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Ventas\Gastronomia\GastronomiaChequeoVentasAnitaErpService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GastronomiaAuditoriaVentasJornada extends Command
{
    protected $signature = 'gastronomia:auditoria-ventas-jornada
                            {--fecha-desde= : Fecha jornada inicial Y-m-d}
                            {--fecha-hasta= : Fecha jornada final Y-m-d (default: hoy)}
                            {--empresa= : empresa_id (obligatorio)}
                            {--puntoventa= : Código PV CAE opcional}
                            {--tolerancia=0.02 : Tolerancia en pesos}
                            {--detalle : Lista comprobantes con diferencias}';

    protected $description = 'Audita ventas gastronomía ERP vs Anita por fecha de jornada (total, gravado, IVA, exento) y PV';

    public function handle(GastronomiaChequeoVentasAnitaErpService $service): int
    {
        $empresaId = (int) $this->option('empresa');
        $fechaDesde = trim((string) $this->option('fecha-desde'));
        $fechaHasta = trim((string) ($this->option('fecha-hasta') ?? ''));
        $fechaHasta = $fechaHasta !== '' ? $fechaHasta : Carbon::today()->toDateString();
        $pv = $this->option('puntoventa');
        $pv = is_string($pv) && trim($pv) !== '' ? trim($pv) : null;
        $tolerancia = max(0.0, (float) $this->option('tolerancia'));
        $mostrarDetalle = (bool) $this->option('detalle');

        if ($empresaId <= 0 || $fechaDesde === '') {
            $this->error('Indique --empresa=ID y --fecha-desde=Y-m-d');

            return self::FAILURE;
        }

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Empresa %d | jornada %s a %s | tolerancia %.2f',
            $empresaId,
            $fechaDesde,
            $fechaHasta,
            $tolerancia,
        ));

        $combinaciones = $service->listarCombinacionesPvJornada($fechaDesde, $fechaHasta, $empresaId, $pv);
        if ($combinaciones === []) {
            $this->comment('Sin ventas gastronomía en el rango.');

            return self::SUCCESS;
        }

        $hayProblemas = false;
        $resumenFilas = [];

        foreach ($combinaciones as $combo) {
            $resultado = $service->chequear(
                (int) $combo['puntoventa_id'],
                (string) $combo['fecha_jornada'],
                $tolerancia,
                true,
            );

            $res = $resultado['resumen'];
            $conteo = $res['conteo'] ?? [];
            $delta = $res['delta_totales']['total'] ?? 0;
            $deltaGravado = $res['delta_totales']['gravado'] ?? 0;
            $problema = ($conteo['solo_erp'] ?? 0) > 0
                || ($conteo['diferencia'] ?? 0) > 0
                || ($conteo['solo_anita'] ?? 0) > 0
                || abs((float) $delta) > $tolerancia
                || abs((float) $deltaGravado) > $tolerancia;

            if ($problema) {
                $hayProblemas = true;
            }

            $resumenFilas[] = [
                $combo['fecha_jornada'],
                $combo['codigo_pv'],
                (string) ($res['ventas_erp'] ?? 0),
                (string) ($res['cabeceras_anita'] ?? 0),
                (string) ($conteo['ok'] ?? 0),
                (string) ($conteo['solo_erp'] ?? 0),
                (string) ($conteo['diferencia'] ?? 0),
                number_format((float) $delta, 2, '.', ''),
                number_format((float) $deltaGravado, 2, '.', ''),
                $problema ? 'ALERTA' : 'OK',
            ];

            if ($mostrarDetalle && $problema && ($resultado['filas'] ?? []) !== []) {
                $this->newLine();
                $this->comment($combo['fecha_jornada'].' PV '.$combo['codigo_pv']);
                foreach ($resultado['filas'] as $fila) {
                    $this->line(sprintf(
                        '  %s %s | ERP %s | Anita %s | %s',
                        $fila['estado'] ?? '',
                        $fila['codigo_erp'] ?? '',
                        isset($fila['erp']['total']) ? number_format((float) $fila['erp']['total'], 2) : '—',
                        isset($fila['anita']['total']) ? number_format((float) $fila['anita']['total'], 2) : '—',
                        implode('; ', array_values($fila['diferencias'] ?? [])),
                    ));
                }
            }
        }

        $this->newLine();
        $this->table(
            ['Jornada', 'PV', 'Fac ERP', 'Cab Anita', 'OK', 'Solo ERP', 'Dif imp.', 'Δ total', 'Δ gravado', 'Estado'],
            $resumenFilas,
        );

        return $hayProblemas ? self::FAILURE : self::SUCCESS;
    }
}
