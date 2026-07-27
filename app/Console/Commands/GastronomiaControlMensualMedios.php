<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Ventas\Gastronomia\GastronomiaConciliacionDiariaReporteService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Control total del mes por empresa: venta ERP, contabilidad global, flash (rendgastro),
 * contabilizado por medio de pago y Z por medio de pago (con conciliación por cuenta).
 *
 * Reutiliza {@see GastronomiaConciliacionDiariaReporteService::generar()} (mismo motor que la
 * conciliación diaria) y agrega las jornadas del rango.
 */
class GastronomiaControlMensualMedios extends Command
{
    protected $signature = 'gastronomia:control-mensual-medios
                            {--fecha-desde= : Fecha jornada inicial Y-m-d (default: inicio de mes actual)}
                            {--fecha-hasta= : Fecha jornada final Y-m-d (default: fin de mes / hoy)}
                            {--empresas= : IDs empresa separados por coma (default: config conciliación diaria)}
                            {--tolerancia= : Tolerancia en pesos (default: config)}
                            {--csv= : Ruta opcional para exportar CSV detalle por medio}
                            {--enviar-mail : Envía el resultado por correo (como la auditoría diaria)}';

    protected $description = 'Control mensual gastronomía: venta ERP, contabilidad global, flash y por medio de pago (Z vs contabilizado)';

    public function handle(GastronomiaConciliacionDiariaReporteService $service): int
    {
        @ini_set('memory_limit', '2048M');
        @set_time_limit(0);

        $config = config('gastronomia.conciliacion_diaria_reporte', []);

        $fechaDesde = trim((string) ($this->option('fecha-desde') ?? ''));
        $fechaHasta = trim((string) ($this->option('fecha-hasta') ?? ''));
        if ($fechaHasta === '') {
            $diasAtras = max(1, (int) config('gastronomia.auditoria_medios_mensual.dias_atras', 2));
            $fechaHasta = Carbon::today()->subDays($diasAtras)->toDateString();
        }
        if ($fechaDesde === '') {
            $fechaDesde = Carbon::parse($fechaHasta)->startOfMonth()->toDateString();
        }

        $empresasOpt = trim((string) ($this->option('empresas') ?? ''));
        $empresas = $empresasOpt !== ''
            ? array_values(array_filter(array_map('intval', array_map('trim', explode(',', $empresasOpt)))))
            : array_values(array_filter(array_map('intval', $config['empresas_ids'] ?? [1, 2, 3])));
        if ($empresas === []) {
            $this->error('Indique al menos una empresa en --empresas o config');

            return self::FAILURE;
        }

        $toleranciaOpt = $this->option('tolerancia');
        $tolerancia = $toleranciaOpt !== null && $toleranciaOpt !== ''
            ? max(0.0, (float) $toleranciaOpt)
            : max(0.0, (float) ($config['tolerancia'] ?? 0.02));

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Control mensual por medio | empresas %s | jornada %s → %s | tolerancia %.2f',
            implode(', ', $empresas),
            $fechaDesde,
            $fechaHasta,
            $tolerancia,
        ));

        try {
            $resumen = $service->resumenMensualMediosDirecto($fechaDesde, $fechaHasta, $empresas, $tolerancia);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $hayDif = false;
        $filasCsv = [];

        foreach ($resumen as $emp) {
            $this->newLine();
            $this->info(sprintf('Empresa %d — %s  (%d jornadas)', $emp['empresa_id'], $emp['empresa_nombre'], $emp['jornadas']));
            $this->line('  Venta ERP (neto):      $ '.$this->fmt($emp['venta_erp']));
            $this->line('  Contabilidad global:   $ '.$this->fmt($emp['contabilidad_global']).'  (Δ ERP↔contab $ '.$this->fmtDiff($emp['diff_erp_contabilidad']).')');
            $this->line('  Flash (rendgastro):    $ '.$this->fmt($emp['flash']));

            $this->line('  <comment>Z / esperado por medio de pago</comment>:');
            foreach ($emp['z_por_medio'] as $m) {
                if (abs((float) $m['total']) < 0.005) {
                    continue;
                }
                $fuente = (string) ($m['fuente'] ?? '');
                $this->line(sprintf(
                    '    %s %s: $ %s%s',
                    $m['cuenta_codigo'],
                    $m['cuenta_nombre'],
                    $this->fmt($m['total']),
                    $fuente !== '' ? ' ('.$fuente.')' : '',
                ));
            }
            $this->line('  <comment>Contabilizado por medio de pago</comment>:');
            foreach ($emp['contabilizado_por_medio'] as $m) {
                if (abs((float) $m['total']) < 0.005) {
                    continue;
                }
                $this->line(sprintf('    %s %s: $ %s', $m['cuenta_codigo'], $m['cuenta_nombre'], $this->fmt($m['total'])));
            }

            if ($emp['jornadas_dif_medio'] === []) {
                $this->line('  <info>Conciliación por medio (medios del Z): OK en todas las jornadas</info>');
            } else {
                $hayDif = true;
                $this->warn('  Jornadas con DIF Z ↔ contabilizado por medio:');
                foreach ($emp['jornadas_dif_medio'] as $j) {
                    foreach ($j['medios'] as $m) {
                        $this->warn(sprintf(
                            '    %s · %s %s: Z $ %s vs contab $ %s · Δ $ %s',
                            $j['fecha_jornada'],
                            $m['cuenta_codigo'],
                            $m['cuenta_nombre'],
                            $this->fmt($m['z']),
                            $this->fmt($m['contabilizado']),
                            $this->fmtDiff($m['diff']),
                        ));
                    }
                }
            }

            foreach ($emp['contabilizado_por_medio'] as $m) {
                $cod = $m['cuenta_codigo'];
                $zMedio = 0.0;
                foreach ($emp['z_por_medio'] as $z) {
                    if ($z['cuenta_codigo'] === $cod) {
                        $zMedio = (float) $z['total'];
                        break;
                    }
                }
                $filasCsv[] = [
                    $emp['empresa_id'], $emp['empresa_nombre'], $fechaDesde, $fechaHasta,
                    $cod, $m['cuenta_nombre'],
                    number_format($zMedio, 2, '.', ''),
                    number_format((float) $m['total'], 2, '.', ''),
                    number_format($zMedio - (float) $m['total'], 2, '.', ''),
                    number_format((float) $emp['venta_erp'], 2, '.', ''),
                    number_format((float) $emp['contabilidad_global'], 2, '.', ''),
                    number_format((float) $emp['flash'], 2, '.', ''),
                ];
            }
        }

        $csvPath = trim((string) ($this->option('csv') ?? ''));
        if ($csvPath !== '') {
            $this->guardarCsv($csvPath, $filasCsv);
            $this->info('CSV: '.$csvPath);
        }

        if ((bool) $this->option('enviar-mail')) {
            $mail = $service->enviarCorreoAuditoriaMediosMensual($resumen, $fechaDesde, $fechaHasta, $tolerancia);
            if ($mail['enviado'] ?? false) {
                $this->info('Correo enviado a '.($mail['destino'] ?? ''));
            } else {
                $this->error('No se pudo enviar correo: '.($mail['error'] ?? 'error desconocido'));

                return self::FAILURE;
            }
        }

        return $hayDif ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<list<int|string>>  $filas
     */
    private function guardarCsv(string $ruta, array $filas): void
    {
        $handle = fopen($ruta, 'w');
        if ($handle === false) {
            $this->error('No se pudo escribir CSV: '.$ruta);

            return;
        }
        fputcsv($handle, [
            'empresa_id', 'empresa_nombre', 'fecha_desde', 'fecha_hasta',
            'cuenta_codigo', 'cuenta_nombre', 'z_medio', 'contabilizado_medio', 'diff_medio',
            'venta_erp_total', 'contabilidad_global_total', 'flash_total',
        ], ';');
        foreach ($filas as $fila) {
            fputcsv($handle, $fila, ';');
        }
        fclose($handle);
    }

    private function fmt(mixed $valor): string
    {
        return number_format((float) $valor, 2, ',', '.');
    }

    private function fmtDiff(mixed $valor): string
    {
        $n = (float) $valor;
        $s = number_format($n, 2, ',', '.');

        return $n > 0 ? '+'.$s : $s;
    }
}
