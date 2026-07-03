<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Ventas\Gastronomia\GastronomiaControlCtamovRendgDiaAnitaService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GastronomiaControlCtamovRendgDiaAnita extends Command
{
    protected $signature = 'gastronomia:control-ctamov-rendg-dia-anita
                            {--empresas=1 : IDs empresa separados por coma (default Biyemas=1)}
                            {--fecha-desde= : Y-m-d jornada inicial (default: primer día del mes)}
                            {--fecha-hasta= : Y-m-d jornada final (default: último día del mes)}
                            {--tolerancia= : Tolerancia en pesos (default: config GASTRONOMIA_CONTROL_CTAMOV_RENDG_TOLERANCIA)}
                            {--forzar-descarga : Re-descarga Anita aunque exista cache local}
                            {--solo-reporte : Solo reporte desde cache existente (sin tocar bridge)}
                            {--csv= : Ruta CSV (default: storage/app/reportes/gastronomia/cuadre_jornada/…)}
                            {--sin-csv : No guardar archivo CSV en disco}
                            {--mail : Envía el CSV por correo}
                            {--email= : Destino override (default: config GASTRONOMIA_CONTROL_CUADRE_JORNADA_EMAIL)}';

    protected $description = 'Cuadre jornada: contabilidad ctamov vs rendiciones vs venta Informix vs venta ERP vs flash (caja)';

    public function handle(GastronomiaControlCtamovRendgDiaAnitaService $service): int
    {
        $empresasOpt = trim((string) $this->option('empresas'));
        $empresas = array_values(array_filter(array_map('intval', array_map('trim', explode(',', $empresasOpt)))));
        if ($empresas === []) {
            $this->error('Indique al menos una empresa en --empresas');

            return self::FAILURE;
        }

        $hoy = Carbon::now();
        $fechaDesde = trim((string) ($this->option('fecha-desde') ?: $hoy->copy()->startOfMonth()->format('Y-m-d')));
        $fechaHasta = trim((string) ($this->option('fecha-hasta') ?: $hoy->copy()->endOfMonth()->format('Y-m-d')));
        $tolerancia = max(0.0, (float) ($this->option('tolerancia') ?: config('gastronomia.control_ctamov_rendg_dia_anita.tolerancia', 2.0)));
        $forzarDescarga = (bool) $this->option('forzar-descarga');
        $soloReporte = (bool) $this->option('solo-reporte');

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Cuadre jornada | empresas %s | %s → %s',
            implode(', ', $empresas),
            $fechaDesde,
            $fechaHasta,
        ));
        $this->comment('Los cinco totales deben coincidir (tolerancia $ '.$tolerancia.'):');
        $this->comment('  1. Contabilidad: Σ haber − debe en cuentas ventas + IVA débito + IVA crédito fiscal (ctamov Anita).');
        $this->comment('  2. Rendiciones: Σ rendg_total_z − Σ rendg_tot_nc (rendgastro Anita).');
        $this->comment('  3. Venta Informix: Σ venta Anita + RMV Z vending del día (rendgastro Anita; 0 si no hay filas).');
        $this->comment('  4. Venta ERP: Σ venta MySQL + maquinavending_rendicion (detalle en venta_erp_tabla / venta_erp_vending).');
        $this->comment('  5. Flash (caja): Σ flash_ayb + flash_estac por jornada (Informix tabla flash).');
        $dirReportes = config('gastronomia.control_ctamov_rendg_dia_anita.directorio_reportes', 'reportes/gastronomia/cuadre_jornada');
        $this->comment('Reporte CSV: storage/app/'.$dirReportes.'/cuadre_jornada_{desde}_{hasta}.csv');
        if ($soloReporte) {
            $this->warn('--solo-reporte: usa cache local; si falta venta.json o los totales no cuadran, ejecute con --forzar-descarga.');
        }

        try {
            $informe = $service->generar(
                $empresas,
                $fechaDesde,
                $fechaHasta,
                $tolerancia,
                $forzarDescarga,
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
                'Empresa %d — %s | cuentas ctamov: %s',
                $empresa['empresa_id'],
                $empresa['empresa_nombre'],
                implode(', ', $empresa['codigos_ctamov'] ?? []),
            ));
            $this->line('Cache: '.($cache['directorio'] ?? '—'));

            $filasTabla = [];

            foreach ($empresa['filas'] ?? [] as $fila) {
                if (($fila['estado'] ?? '') === '—') {
                    continue;
                }

                $filasTabla[] = [
                    $fila['fecha_jornada'],
                    number_format((float) ($fila['total_contabilidad'] ?? 0), 2, '.', ''),
                    number_format((float) ($fila['total_rendiciones_neto'] ?? 0), 2, '.', ''),
                    number_format((float) ($fila['total_venta_anita'] ?? 0), 2, '.', ''),
                    number_format((float) ($fila['total_venta_erp'] ?? 0), 2, '.', ''),
                    number_format((float) ($fila['total_flash'] ?? 0), 2, '.', ''),
                    $fila['estado'] ?? '',
                ];
            }

            if ($filasTabla === []) {
                $this->comment('Sin actividad en el rango.');

                continue;
            }

            $this->table(
                ['Jornada', 'Contabilidad', 'Rendiciones Z−NC', 'Venta Informix', 'Venta ERP', 'Flash', 'Estado'],
                $filasTabla,
            );

            $difs = array_filter($empresa['filas'] ?? [], fn (array $f): bool => ($f['estado'] ?? '') === 'DIF');
            if ($difs !== []) {
                $this->warn('Días con diferencia (primeros 15):');
                foreach (array_slice(array_values($difs), 0, 15) as $fila) {
                    $this->line(sprintf(
                        '  %s | cont $ %s | rend $ %s | anita $ %s | erp $ %s | flash $ %s | Δ cont↔flash $ %s',
                        $fila['fecha_jornada'],
                        number_format((float) ($fila['total_contabilidad'] ?? 0), 2, '.', ''),
                        number_format((float) ($fila['total_rendiciones_neto'] ?? 0), 2, '.', ''),
                        number_format((float) ($fila['total_venta_anita'] ?? 0), 2, '.', ''),
                        number_format((float) ($fila['total_venta_erp'] ?? 0), 2, '.', ''),
                        number_format((float) ($fila['total_flash'] ?? 0), 2, '.', ''),
                        number_format((float) ($fila['dif_cont_flash'] ?? 0), 2, '.', ''),
                    ));
                }
            }
        }

        $csv = trim((string) ($this->option('csv') ?? ''));
        $sinCsv = (bool) $this->option('sin-csv');
        if (! $sinCsv) {
            $rutaCsv = $csv !== '' ? $csv : $service->rutaCsvDefecto($informe);
            $service->guardarCsv($rutaCsv, $informe);
            $this->newLine();
            $this->info('Reporte CSV: '.$rutaCsv);
        } elseif ($csv !== '') {
            $service->guardarCsv($csv, $informe);
            $this->info('Reporte CSV: '.$csv);
        }

        if ((bool) $this->option('mail')) {
            $emailOpt = trim((string) ($this->option('email') ?? ''));
            $resultadoMail = $service->enviarCorreo($informe, $emailOpt !== '' ? $emailOpt : null);
            if ($resultadoMail['enviado'] ?? false) {
                $this->info('Correo enviado a '.($resultadoMail['destino'] ?? ''));
            } else {
                $this->error('Fallo al enviar correo: '.($resultadoMail['error'] ?? 'desconocido'));
            }
        }

        return ($informe['hay_diferencias'] ?? false) ? self::FAILURE : self::SUCCESS;
    }
}
