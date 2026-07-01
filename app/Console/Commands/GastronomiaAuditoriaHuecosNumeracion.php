<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Ventas\Gastronomia\GastronomiaAuditoriaHuecosNumeracionService;
use Illuminate\Console\Command;

class GastronomiaAuditoriaHuecosNumeracion extends Command
{
    protected $signature = 'gastronomia:auditoria-huecos-numeracion
                            {--fecha-desde= : Fecha jornada inicial Y-m-d (obligatorio)}
                            {--fecha-hasta= : Fecha jornada final Y-m-d (default: fecha-desde)}
                            {--empresas= : IDs empresa separados por coma (default: config conciliación)}
                            {--puntoventa= : Código PV opcional}
                            {--sin-anita : Solo huecos ERP (no consulta cache Anita)}
                            {--forzar-cache-anita : Refresca cache Anita bulk del rango}
                            {--export-huecos= : Ruta TSV detalle huecos ERP+Anita}';

    protected $description = 'Auditoría de huecos en numeración de comprobantes gastronomía (ERP y Anita) por rango de jornadas';

    public function handle(GastronomiaAuditoriaHuecosNumeracionService $service): int
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        $config = config('gastronomia.conciliacion_diaria_reporte', []);

        $fechaDesde = trim((string) ($this->option('fecha-desde') ?? ''));
        if ($fechaDesde === '') {
            $this->error('Indique --fecha-desde (Y-m-d).');

            return self::FAILURE;
        }

        $fechaHasta = trim((string) ($this->option('fecha-hasta') ?? ''));
        $fechaHasta = $fechaHasta !== '' ? $fechaHasta : $fechaDesde;

        $empresasOpt = trim((string) ($this->option('empresas') ?? ''));
        $empresas = $empresasOpt !== ''
            ? array_values(array_filter(array_map('intval', array_map('trim', explode(',', $empresasOpt)))))
            : array_values(array_filter(array_map('intval', $config['empresas_ids'] ?? [1, 2, 3])));

        if ($empresas === []) {
            $this->error('Indique al menos una empresa en --empresas o config');

            return self::FAILURE;
        }

        $pvFiltro = $this->option('puntoventa');
        $pvFiltro = is_string($pvFiltro) && trim($pvFiltro) !== '' ? trim($pvFiltro) : null;

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Huecos numeración | empresas %s | jornada %s → %s%s',
            implode(', ', $empresas),
            $fechaDesde,
            $fechaHasta,
            $pvFiltro !== null ? ' | PV '.$pvFiltro : '',
        ));

        try {
            $informe = $service->auditarRango(
                $fechaDesde,
                $fechaHasta,
                $empresas,
                $pvFiltro,
                ! (bool) $this->option('sin-anita'),
                (bool) $this->option('forzar-cache-anita'),
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $res = $informe['resumen'];
        $this->table(['Concepto', 'Valor'], [
            ['Tramos huecos ERP', (string) ($res['huecos_erp'] ?? 0)],
            ['Números faltantes ERP', (string) ($res['numeros_faltantes_erp'] ?? 0)],
            ['PV con huecos ERP', (string) ($res['puntos_venta_con_huecos_erp'] ?? 0)],
            ['Jornadas con huecos ERP', (string) ($res['jornadas_con_huecos_erp'] ?? 0)],
        ]);

        foreach ($informe['empresas'] as $empresa) {
            $this->newLine();
            $this->info(sprintf('Empresa %d — %s', $empresa['empresa_id'], $empresa['empresa_nombre']));

            $tablaPv = [];
            foreach ($empresa['por_puntoventa'] as $row) {
                $tablaPv[] = [
                    $row['pv_codigo'],
                    $row['tramos_erp'],
                    $row['faltantes_erp'],
                ];
            }

            if ($tablaPv !== []) {
                $this->comment('PV con huecos en el rango (por jornada)');
                $this->table(['PV', 'Tramos ERP', 'Falt. ERP'], $tablaPv);
            }

            $tablaJornada = [];
            foreach ($empresa['por_jornada'] ?? [] as $row) {
                $tablaJornada[] = [
                    $row['fecha_jornada'],
                    $row['huecos_corr_erp'],
                    $row['numeros_faltantes_erp'],
                    $row['solo_erp'],
                    $row['solo_anita'],
                ];
            }
            if ($tablaJornada !== []) {
                $this->comment('Jornadas con huecos');
                $this->table(['Jornada', 'Tramos', 'Falt.', 'Solo ERP', 'Solo Anita'], $tablaJornada);
            }

            $huecosDetalle = $empresa['huecos_erp'] ?? [];
            if ($huecosDetalle !== []) {
                $this->warn('Detalle huecos (primeros 30)');
                $this->table(
                    ['Jornada', 'PV', 'Después de', 'Siguiente', 'Cant', 'Faltantes'],
                    array_map(fn (array $h) => [
                        $h['fecha_jornada'] ?? '',
                        $h['pv_codigo'] ?? '',
                        $h['desde'] ?? '',
                        $h['hasta'] ?? '',
                        $h['cantidad'] ?? '',
                        $this->resumirFaltantes((string) ($h['faltantes'] ?? '')),
                    ], array_slice($huecosDetalle, 0, 30)),
                );
            }
        }

        $exportHuecos = trim((string) ($this->option('export-huecos') ?? ''));
        if ($exportHuecos !== '') {
            $this->exportarHuecos($exportHuecos, $informe);
            $this->info('Exportado: '.$exportHuecos);
        }

        if (($informe['hay_huecos'] ?? false) === true) {
            $this->warn('Se detectaron huecos en numeración.');

            return self::FAILURE;
        }

        $this->info('Sin huecos en numeración en el rango.');

        return self::SUCCESS;
    }

    /** @param  array<string, mixed>  $informe */
    private function exportarHuecos(string $path, array $informe): void
    {
        $h = fopen($path, 'w');
        if ($h === false) {
            throw new \RuntimeException('No se pudo escribir '.$path);
        }

        fputcsv($h, [
            'fecha_desde', 'fecha_hasta', 'fecha_jornada', 'empresa_id', 'pv_codigo',
            'despues_de', 'siguiente', 'cantidad', 'faltantes',
        ], "\t");

        foreach ($informe['empresas'] ?? [] as $empresa) {
            foreach ($empresa['huecos_erp'] ?? [] as $row) {
                fputcsv($h, [
                    $informe['fecha_desde'] ?? '',
                    $informe['fecha_hasta'] ?? '',
                    $row['fecha_jornada'] ?? '',
                    $row['empresa_id'] ?? $empresa['empresa_id'] ?? '',
                    $row['pv_codigo'] ?? '',
                    $row['desde'] ?? '',
                    $row['hasta'] ?? '',
                    $row['cantidad'] ?? '',
                    $row['faltantes'] ?? '',
                ], "\t");
            }
        }

        fclose($h);
    }

    private function resumirFaltantes(string $faltantes, int $max = 8): string
    {
        if ($faltantes === '') {
            return '';
        }
        $partes = explode(',', $faltantes);
        if (count($partes) <= $max) {
            return $faltantes;
        }

        return implode(',', array_slice($partes, 0, $max)).',… (+'.(count($partes) - $max).')';
    }
}
