<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Ventas\Gastronomia\GastronomiaControlCorrelatividadAnitaErpService;
use Illuminate\Console\Command;

class GastronomiaControlCorrelatividadAnitaErp extends Command
{
    protected $signature = 'gastronomia:control-correlatividad-anita-erp
                            {--fecha-jornada= : Fecha jornada (Y-m-d), obligatoria}
                            {--corte-inicio= : Instantáneo corte inicio gap (ej. 2026-06-14 18:00:00)}
                            {--corte-fin= : Instantáneo corte fin gap (ej. 2026-06-15 01:08:00)}
                            {--empresas=1,2,3 : empresa_id separados por coma}
                            {--export= : Ruta TSV detalle}
                            {--export-huecos= : Ruta TSV huecos correlativos}';

    protected $description = 'Concilia jornada ERP (codigo) vs Anita (ven_tipo+letra+sucursal+nro) por PV';

    public function handle(GastronomiaControlCorrelatividadAnitaErpService $service): int
    {
        $fechaJornada = trim((string) $this->option('fecha-jornada'));
        if ($fechaJornada === '') {
            $this->error('Indique --fecha-jornada (Y-m-d).');

            return self::FAILURE;
        }

        $empresaIds = $this->parseEmpresas((string) $this->option('empresas'));
        $corteInicio = $this->opcionNullable('corte-inicio');
        $corteFin = $this->opcionNullable('corte-fin');

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line("Jornada {$fechaJornada}");
        if ($corteInicio !== null || $corteFin !== null) {
            $this->line('Cortes ERP created_at: inicio='.($corteInicio ?? '—').' fin='.($corteFin ?? '—'));
        }

        try {
            $resultado = $service->ejecutar($fechaJornada, $empresaIds, $corteInicio, $corteFin);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $res = $resultado['resumen'];
        $this->table(['Concepto', 'Valor'], [
            ['Ventas ERP jornada', (string) ($res['ventas_erp'] ?? 0)],
            ['Cabeceras Anita jornada', (string) ($res['cabeceras_anita'] ?? 0)],
            ['Pares OK', (string) ($res['pares_ok'] ?? 0)],
            ['Solo ERP', (string) ($res['solo_erp'] ?? 0)],
            ['Solo Anita', (string) ($res['solo_anita'] ?? 0)],
            ['Excl. Anita legacy (resvta)', (string) ($res['excluido_resvta_legacy'] ?? 0)],
            ['Dif. monto', (string) ($res['dif_monto'] ?? 0)],
            ['Huecos correlativos ERP', (string) ($res['huecos_corr_erp'] ?? 0)],
            ['ERP en corte inicio', (string) ($res['erp_corte_inicio'] ?? 0)],
            ['ERP en corte fin', (string) ($res['erp_corte_fin'] ?? 0)],
        ]);

        $this->newLine();
        $this->comment('Por punto de venta');
        $tabla = [];
        foreach ($resultado['por_puntoventa'] as $row) {
            $tabla[] = [
                $row['pv_codigo'], $row['empresa_id'], $row['ventas_erp'], $row['cabeceras_anita'],
                $row['pares_ok'], $row['solo_erp'], $row['solo_anita'], $row['dif_monto'] ?? 0,
                $row['huecos_corr'],
                ($row['min_numero'] ?? '—').'–'.($row['max_numero'] ?? '—'),
            ];
        }
        $this->table(
            ['PV', 'Emp', 'ERP', 'Anita', 'OK', 'SoloERP', 'SoloAnita', 'Dif$', 'Huecos', 'Rango nro'],
            $tabla,
        );

        if (($resultado['huecos'] ?? []) !== []) {
            $this->newLine();
            $this->warn('Huecos correlativos ERP');
            $this->table(
                ['PV', 'Emp', 'Después de', 'Siguiente', 'Cant', 'Faltantes'],
                array_map(static fn (array $h) => [
                    $h['pv_codigo'] ?? '', $h['empresa_id'] ?? '',
                    $h['desde'] ?? '', $h['hasta'] ?? '', $h['cantidad'] ?? '',
                    $h['faltantes'] ?? '',
                ], $resultado['huecos']),
            );
        }

        $problemas = (int) ($res['solo_erp'] ?? 0) + (int) ($res['solo_anita'] ?? 0) + (int) ($res['dif_monto'] ?? 0) + (int) ($res['huecos_corr_erp'] ?? 0);
        $filasProb = array_filter($resultado['filas'], static fn (array $f) => ($f['estado'] ?? 'ok') !== 'ok');

        if ($filasProb !== []) {
            $this->newLine();
            $this->comment('Detalle problemas (primeras 40)');
            $tablaDet = [];
            foreach (array_slice($filasProb, 0, 40) as $f) {
                $tablaDet[] = [
                    $f['estado'], $f['pv_codigo'], $f['codigo_erp'] ?? ('Anita '.$f['anita_orden']),
                    $f['total_erp'] ?? '—', $f['anita_monto'] ?? '—',
                    ($f['erp_en_corte_inicio'] ?? false) ? 'S' : 'N',
                    ($f['erp_en_corte_fin'] ?? false) ? 'S' : 'N',
                    $f['observaciones'] ?? '',
                ];
            }
            $this->table(['Estado', 'PV', 'Comprobante', 'ERP', 'Anita', 'C18', 'C01', 'Obs'], $tablaDet);
        }

        $export = trim((string) ($this->option('export') ?? ''));
        if ($export !== '') {
            $this->exportarTsv($export, $resultado['filas']);
            $this->info('Exportado: '.$export);
        }

        $exportHuecos = trim((string) ($this->option('export-huecos') ?? ''));
        if ($exportHuecos !== '') {
            $this->exportarHuecos($exportHuecos, $resultado['huecos']);
            $this->info('Huecos: '.$exportHuecos);
        }

        return $problemas > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return list<int> */
    private function parseEmpresas(string $raw): array
    {
        $ids = [];
        foreach (explode(',', $raw) as $part) {
            $id = (int) trim($part);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function opcionNullable(string $nombre): ?string
    {
        $valor = trim((string) ($this->option($nombre) ?? ''));

        return $valor !== '' ? $valor : null;
    }

    /** @param  list<array<string, mixed>>  $filas */
    private function exportarTsv(string $path, array $filas): void
    {
        $h = fopen($path, 'w');
        if ($h === false) {
            throw new \RuntimeException('No se pudo escribir '.$path);
        }
        fputcsv($h, [
            'pv_codigo', 'empresa_id', 'numero', 'estado', 'codigo_erp', 'clave', 'venta_id', 'numero_erp',
            'total_erp', 'created_at', 'erp_en_corte_inicio', 'erp_en_corte_fin',
            'anita_orden', 'anita_tipo', 'anita_nro', 'anita_monto', 'anita_empresa', 'observaciones',
        ], "\t");
        foreach ($filas as $f) {
            fputcsv($h, [
                $f['pv_codigo'] ?? '', $f['empresa_id'] ?? '', $f['numero'] ?? '', $f['estado'] ?? '',
                $f['codigo_erp'] ?? '', $f['clave'] ?? '', $f['venta_id'] ?? '', $f['numero_erp'] ?? '',
                $f['total_erp'] ?? '', $f['created_at'] ?? '',
                ($f['erp_en_corte_inicio'] ?? false) ? '1' : '0',
                ($f['erp_en_corte_fin'] ?? false) ? '1' : '0',
                $f['anita_orden'] ?? '', $f['anita_tipo'] ?? '', $f['anita_nro'] ?? '', $f['anita_monto'] ?? '',
                $f['anita_empresa'] ?? '', $f['observaciones'] ?? '',
            ], "\t");
        }
        fclose($h);
    }

    /** @param  list<array<string, mixed>>  $huecos */
    private function exportarHuecos(string $path, array $huecos): void
    {
        $h = fopen($path, 'w');
        if ($h === false) {
            throw new \RuntimeException('No se pudo escribir '.$path);
        }
        fputcsv($h, ['pv_codigo', 'empresa_id', 'despues_de', 'siguiente', 'cantidad', 'faltantes'], "\t");
        foreach ($huecos as $row) {
            fputcsv($h, [
                $row['pv_codigo'] ?? '', $row['empresa_id'] ?? '',
                $row['desde'] ?? '', $row['hasta'] ?? '', $row['cantidad'] ?? '', $row['faltantes'] ?? '',
            ], "\t");
        }
        fclose($h);
    }
}
