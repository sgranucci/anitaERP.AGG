<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Ventas\Gastronomia\GastronomiaConciliacionDiariaReporteService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GastronomiaConciliacionDiariaReporte extends Command
{
    protected $signature = 'gastronomia:conciliacion-diaria-reporte
                            {--fecha-desde= : Fecha jornada inicial Y-m-d (obligatorio salvo corrida nocturna implícita)}
                            {--fecha-hasta= : Fecha jornada final Y-m-d (default: fecha-desde)}
                            {--empresas= : IDs empresa separados por coma (default: config)}
                            {--tolerancia= : Tolerancia en pesos (default: config)}
                            {--csv= : Ruta opcional para exportar CSV}
                            {--enviar-mail : Envía el CSV por correo}
                            {--sin-mail : No envía correo (solo consola/CSV)}
                            {--requiere-jornada-cerrada : Solo empresas con jornada cerrada en el rango (schedule diario)}';

    protected $description = 'Auditoría día a día: ERP/Anita/rendgastro por PC, PV (CAE/CAEA), post-cierre y total general';

    public function handle(GastronomiaConciliacionDiariaReporteService $service): int
    {
        $config = config('gastronomia.conciliacion_diaria_reporte', []);

        $fechaDesde = trim((string) ($this->option('fecha-desde') ?? ''));
        $fechaHasta = trim((string) ($this->option('fecha-hasta') ?? ''));
        if ($fechaDesde === '') {
            $fechaDesde = Carbon::yesterday()->toDateString();
        }
        $fechaHasta = $fechaHasta !== '' ? $fechaHasta : $fechaDesde;

        $empresasOpt = trim((string) ($this->option('empresas') ?? ''));
        $empresas = $empresasOpt !== ''
            ? array_values(array_filter(array_map('intval', array_map('trim', explode(',', $empresasOpt)))))
            : array_values(array_filter(array_map('intval', $config['empresas_ids'] ?? [1, 2, 3])));
        if ($empresas === []) {
            $this->error('Indique al menos una empresa en --empresas o config');

            return self::FAILURE;
        }

        if ((bool) $this->option('requiere-jornada-cerrada')) {
            $empresasAntes = $empresas;
            $empresas = $service->filtrarEmpresasJornadaCerrada($empresas, $fechaDesde, $fechaHasta);
            $omitidas = array_values(array_diff($empresasAntes, $empresas));
            if ($omitidas !== []) {
                $this->comment(sprintf(
                    'Omitidas (jornada no cerrada en %s → %s): %s',
                    $fechaDesde,
                    $fechaHasta,
                    implode(', ', $omitidas),
                ));
            }
            if ($empresas === []) {
                $this->comment('Ninguna empresa con jornada cerrada en el rango; no se genera reporte ni mail.');

                return self::SUCCESS;
            }
        }

        $toleranciaOpt = $this->option('tolerancia');
        $tolerancia = $toleranciaOpt !== null && $toleranciaOpt !== ''
            ? max(0.0, (float) $toleranciaOpt)
            : max(0.0, (float) ($config['tolerancia'] ?? 0.02));

        $enviarMail = (bool) $this->option('enviar-mail');
        if ($enviarMail && (bool) $this->option('sin-mail')) {
            $this->error('Use --enviar-mail o --sin-mail, no ambos');

            return self::FAILURE;
        }

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Auditoría conciliación | empresas %s | jornada %s → %s | tolerancia %.2f',
            implode(', ', $empresas),
            $fechaDesde,
            $fechaHasta,
            $tolerancia,
        ));
        $this->comment('Detalle: PV CAE + PV CAEA (salón) → total PC vs rendg Z → post-cierre → control día empresa.');

        try {
            $informe = $service->generar($fechaDesde, $fechaHasta, $empresas, $tolerancia);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $csvFilas = $service->construirFilasCsv($informe);
        $hayDiferencias = $service->hayDiferencias($informe);

        foreach ($informe['empresas'] as $empresa) {
            $this->newLine();
            $this->info(sprintf(
                'Empresa %d — %s',
                $empresa['empresa_id'],
                $empresa['empresa_nombre'],
            ));

            foreach ($empresa['dias'] as $dia) {
                if (($dia['filas'] ?? []) === []) {
                    continue;
                }

                $this->newLine();
                $this->comment('Jornada '.$dia['fecha_jornada']);

                $pcActual = null;
                foreach ($dia['filas'] as $fila) {
                    $tipo = (string) ($fila['tipo_fila'] ?? '');

                    if ($tipo === 'pv_cae' || $tipo === 'pv_caea') {
                        if ($pcActual !== ($fila['identificador_pc'] ?? null)) {
                            $pcActual = $fila['identificador_pc'] ?? null;
                            $this->line('');
                            $this->line('  PC <info>'.$pcActual.'</info> (PV CAE '.($fila['pv_cae'] ?? '').' / CAEA '.($fila['pv_caea'] ?? '').')');
                        }
                        $this->line(sprintf(
                            '    PV %s %s: ERP $ %s | Anita $ %s | %d fc | %s',
                            $fila['pv_codigo'] ?? '—',
                            $fila['tipo_pv'] ?? '',
                            $this->fmt($fila['ventas_erp'] ?? 0),
                            $this->fmt($fila['ventas_anita'] ?? 0),
                            (int) ($fila['cantidad_facturas_erp'] ?? 0),
                            $fila['estado'] ?? '—',
                        ));
                        continue;
                    }

                    if ($tipo === 'pc_total') {
                        $this->line(sprintf(
                            '    → Total PC: ERP $ %s (CAE $ %s + CAEA $ %s) | Anita $ %s | Rendg Z $ %s | Δ rendg $ %s | %s',
                            $this->fmt($fila['ventas_erp'] ?? 0),
                            $this->fmt($fila['ventas_erp_cae'] ?? 0),
                            $this->fmt($fila['ventas_erp_caea'] ?? 0),
                            $this->fmt($fila['ventas_anita'] ?? 0),
                            $this->fmt($fila['rendgastro_z'] ?? null),
                            $this->fmtDiff($fila['diff_erp_rendg'] ?? null),
                            $fila['estado'] ?? '—',
                        ));
                        continue;
                    }

                    if ($tipo === 'total_salon' || $tipo === 'total_dia') {
                        $this->line('');
                        $etiqueta = $tipo === 'total_dia'
                            ? '<comment>TOTAL DÍA</comment> (salón + post-cierre CAEA)'
                            : '<comment>TOTAL SALÓN</comment> (todas las PCs, sin post-cierre)';
                        $this->line(sprintf(
                            '  %s: ERP $ %s | Anita $ %s | Rendg ΣZ $ %s | Δ $ %s | %s',
                            $etiqueta,
                            $this->fmt($fila['ventas_erp'] ?? 0),
                            $this->fmt($fila['ventas_anita'] ?? 0),
                            $this->fmt($fila['rendgastro_z'] ?? null),
                            $this->fmtDiff($fila['diff_erp_rendg'] ?? null),
                            $fila['estado'] ?? '—',
                        ));
                        continue;
                    }

                    if ($tipo === 'post_cierre_caea') {
                        $this->line(sprintf(
                            '  Post-cierre CAEA (PV %s): ERP $ %s | Anita $ %s | Rendg CIERRE-WAITRY $ %s | %d fc | %s',
                            $fila['pv_caea'] ?? '—',
                            $this->fmt($fila['ventas_erp'] ?? 0),
                            $this->fmt($fila['ventas_anita'] ?? 0),
                            $this->fmt($fila['rendgastro_z'] ?? null),
                            (int) ($fila['cantidad_facturas_erp'] ?? 0),
                            $fila['estado'] ?? '—',
                        ));
                    }
                }

                $ctrl = $dia['control_gastro_total'] ?? null;
                if (is_array($ctrl)) {
                    $lineaCtrl = sprintf(
                        '  <comment>CONTROL DÍA EMPRESA</comment>: ERP neto $ %s (bruto $ %s − NC $ %s) | rendg neto $ %s (Z $ %s − NC $ %s) | Δ $ %s | %s',
                        $this->fmt($ctrl['ventas_erp'] ?? 0),
                        $this->fmt($ctrl['ventas_erp_bruto'] ?? 0),
                        $this->fmt($ctrl['notas_credito_erp'] ?? 0),
                        $this->fmt($ctrl['rendgastro_neto'] ?? null),
                        $this->fmt($ctrl['rendgastro_z'] ?? null),
                        $this->fmt($ctrl['notas_credito_rendg'] ?? null),
                        $this->fmtDiff($ctrl['diff_erp_rendg'] ?? null),
                        $ctrl['estado'] ?? '—',
                    );
                    if (($ctrl['rendg_legacy_z'] ?? null) !== null && (float) ($ctrl['rendg_legacy_z'] ?? 0) > 0.02) {
                        $lineaCtrl .= sprintf(' | <error>legacy Z $ %s</error>', $this->fmt($ctrl['rendg_legacy_z']));
                    }
                    if (($ctrl['fc_caea_duplicado'] ?? null) !== null && (float) ($ctrl['fc_caea_duplicado'] ?? 0) > 0.02) {
                        $lineaCtrl .= sprintf(' | <error>fc_caea dup $ %s</error>', $this->fmt($ctrl['fc_caea_duplicado']));
                    }
                    $this->line($lineaCtrl);
                }
            }
        }

        $csvPath = trim((string) ($this->option('csv') ?? ''));
        if ($csvPath !== '') {
            $service->guardarCsv($csvPath, $informe);
            $this->info('CSV: '.$csvPath);
        }

        if ($enviarMail) {
            $mail = $service->enviarCorreo($informe);
            if ($mail['enviado'] ?? false) {
                $this->info('Correo enviado a '.($mail['destino'] ?? ''));
            } else {
                $this->error('No se pudo enviar correo: '.($mail['error'] ?? 'error desconocido'));

                return self::FAILURE;
            }
        }

        if ($csvFilas === []) {
            $this->comment('Sin actividad en el rango indicado.');

            return self::SUCCESS;
        }

        return $hayDiferencias ? self::FAILURE : self::SUCCESS;
    }

    private function fmt(mixed $valor): string
    {
        if ($valor === null) {
            return '—';
        }

        return number_format((float) $valor, 2, '.', '');
    }

    private function fmtDiff(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        $n = (float) $valor;
        $s = number_format($n, 2, '.', '');

        return $n > 0 ? '+'.$s : $s;
    }
}
