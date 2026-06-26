<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Ventas\Gastronomia\GastronomiaAuditoriaMesTotalesAnitaService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GastronomiaAuditoriaMesTotalesAnita extends Command
{
    protected $signature = 'gastronomia:auditoria-mes-totales-anita
                            {--empresas=1,2 : IDs empresa separados por coma}
                            {--fecha-desde= : Y-m-d jornada inicial (default: primer día del mes)}
                            {--fecha-hasta= : Y-m-d jornada final (default: último día del mes)}
                            {--tolerancia=0.02 : Tolerancia en pesos (días vacíos)}
                            {--forzar-descarga : Re-descarga Anita aunque exista cache local}
                            {--solo-descarga : Solo descarga cache Anita, sin reporte}
                            {--solo-reporte : Solo reporte desde cache existente (sin tocar bridge)}
                            {--mail : Envía el Excel por correo}
                            {--email= : Destino override (default: config GASTRONOMIA_AUDITORIA_ANITA_EMAIL)}
                            {--csv= : Ruta CSV del reporte día a día}
                            {--excel= : Ruta Excel (.xlsx) del reporte día a día}';

    protected $description = 'Auditoría mensual solo Anita: venta, vengrav, ctamov, rendgastro por día (incl. estacionamiento/marketing) + correlatividad Anita';

    public function handle(
        GastronomiaAuditoriaMesTotalesAnitaService $service,
    ): int {
        $empresasOpt = trim((string) $this->option('empresas'));
        $empresas = array_values(array_filter(array_map('intval', array_map('trim', explode(',', $empresasOpt)))));
        if ($empresas === []) {
            $this->error('Indique al menos una empresa en --empresas');

            return self::FAILURE;
        }

        $hoy = Carbon::now();
        $fechaDesde = trim((string) ($this->option('fecha-desde') ?: $hoy->copy()->startOfMonth()->format('Y-m-d')));
        $fechaHasta = trim((string) ($this->option('fecha-hasta') ?: $hoy->copy()->endOfMonth()->format('Y-m-d')));
        $tolerancia = max(0.0, (float) $this->option('tolerancia'));
        $forzarDescarga = (bool) $this->option('forzar-descarga');
        $soloDescarga = (bool) $this->option('solo-descarga');
        $soloReporte = (bool) $this->option('solo-reporte');

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Auditoría mes SOLO Anita | empresas %s | jornada %s → %s',
            implode(', ', $empresas),
            $fechaDesde,
            $fechaHasta,
        ));
        $this->comment('Columna venta: neto (NC restan). Columna rendg: neto (rendg_total_z − rendg_tot_nc). Excluye FSL/FBI en venta.');
        $this->comment('Cache: storage/app/anita_audit_cache/empresa_{id}_{desde}_{hasta}/');

        try {
            if ($soloDescarga) {
                foreach ($empresas as $empresaId) {
                    $manifest = app(\App\Support\Ventas\Gastronomia\GastronomiaAnitaMesCacheSupport::class)
                        ->descargar($empresaId, $fechaDesde, $fechaHasta, $forzarDescarga);
                    $this->info(sprintf(
                        'Empresa %d cache OK | venta %d | vengrav %d | ctamov %d | rendg %d filas',
                        $empresaId,
                        $manifest['counts']['venta'] ?? 0,
                        $manifest['counts']['vengrav'] ?? 0,
                        $manifest['counts']['ctamov'] ?? 0,
                        $manifest['counts']['rendgastro_filas'] ?? 0,
                    ));
                    $this->line('  → '.($manifest['directorio'] ?? ''));
                }

                return self::SUCCESS;
            }

            $informe = $service->generar(
                $empresas,
                $fechaDesde,
                $fechaHasta,
                $tolerancia,
                $forzarDescarga && ! $soloReporte,
                $soloReporte,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($informe['empresas'] as $empresa) {
            $cache = $empresa['cache'] ?? [];
            $this->newLine();
            $this->info(sprintf(
                'Empresa %d — %s | cache venta %d vengrav %d ctamov %d rendg %d',
                $empresa['empresa_id'],
                $empresa['empresa_nombre'],
                $cache['counts']['venta'] ?? 0,
                $cache['counts']['vengrav'] ?? 0,
                $cache['counts']['ctamov'] ?? 0,
                $cache['counts']['rendgastro_filas'] ?? 0,
            ));
            $this->line('Cache: '.($cache['directorio'] ?? '—'));

            $filasTabla = [];
            $totalMesVenta = 0.0;
            $totalMesVengrav = 0.0;
            $totalMesCtamov = 0.0;
            $totalMesRendg = 0.0;

            foreach ($empresa['filas'] ?? [] as $fila) {
                if (($fila['estado'] ?? '') === '—') {
                    continue;
                }

                $totalMesVenta += (float) ($fila['total_venta_anita'] ?? 0);
                $totalMesVengrav += (float) ($fila['total_vengrav_anita'] ?? 0);
                $totalMesCtamov += (float) ($fila['total_ctamov_anita'] ?? 0);
                $totalMesRendg += (float) ($fila['total_rendg_anita'] ?? 0);

                $filasTabla[] = [
                    $fila['fecha_jornada'],
                    number_format((float) ($fila['total_venta_anita'] ?? 0), 2, '.', ''),
                    number_format((float) ($fila['total_vengrav_anita'] ?? 0), 2, '.', ''),
                    number_format((float) ($fila['total_ctamov_anita'] ?? 0), 2, '.', ''),
                    number_format((float) ($fila['total_rendg_anita'] ?? 0), 2, '.', ''),
                    (string) ($fila['cant_cabeceras_venta_anita'] ?? 0),
                    (string) ($fila['huecos_corr_anita'] ?? 0),
                    $fila['estado'] ?? '',
                ];
            }

            if ($filasTabla === []) {
                $this->comment('Sin actividad Anita en el rango.');

                continue;
            }

            $this->table(
                ['Jornada', 'venta', 'vengrav', 'ctamov', 'rendg', 'Cab.', 'Huecos Anita', 'Estado'],
                $filasTabla,
            );

            $this->line(sprintf(
                'TOTAL mes | venta $ %s | vengrav $ %s | ctamov $ %s | rendg $ %s',
                number_format($totalMesVenta, 2, '.', ''),
                number_format($totalMesVengrav, 2, '.', ''),
                number_format($totalMesCtamov, 2, '.', ''),
                number_format($totalMesRendg, 2, '.', ''),
            ));

            $alertas = array_filter($empresa['filas'] ?? [], fn (array $f): bool => ($f['estado'] ?? '') === 'ALERTA');
            if ($alertas !== []) {
                $this->warn('Días con huecos correlativos Anita (primeros 10):');
                foreach (array_slice(array_values($alertas), 0, 10) as $fila) {
                    $this->line(sprintf(
                        '  %s | huecos %d | cabeceras %d',
                        $fila['fecha_jornada'],
                        $fila['huecos_corr_anita'] ?? 0,
                        $fila['cant_cabeceras_venta_anita'] ?? 0,
                    ));
                }
            }
        }

        $csv = trim((string) ($this->option('csv') ?? ''));
        if ($csv !== '') {
            $service->guardarCsv($csv, $informe);
            $this->info('CSV: '.$csv);
        }

        $excel = trim((string) ($this->option('excel') ?? ''));
        if ($excel !== '') {
            $service->guardarExcel($excel, $informe);
            $this->info('Excel: '.$excel);
        }

        $enviarMail = (bool) $this->option('mail');
        if ($enviarMail) {
            $emailOpt = trim((string) ($this->option('email') ?? ''));
            $resultadoMail = $service->enviarCorreo($informe, $emailOpt !== '' ? $emailOpt : null);
            if ($resultadoMail['enviado'] ?? false) {
                $this->info('Correo enviado a '.($resultadoMail['destino'] ?? ''));
            } else {
                $this->error('Fallo al enviar correo: '.($resultadoMail['error'] ?? 'desconocido'));
            }
        }

        return ($informe['hay_alertas'] ?? false) ? self::FAILURE : self::SUCCESS;
    }
}
