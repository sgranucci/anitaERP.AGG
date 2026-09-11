<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Contable\AnitaAsientoImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AuditarAsientosAnitaCommand extends Command
{
    protected $signature = 'contable:auditar-asientos-anita
                            {--desde=2026-01-01 : Fecha inicial Y-m-d}
                            {--hasta=2026-08-31 : Fecha final Y-m-d}
                            {--empresas=1,2,3 : Códigos Anita de empresa}
                            {--meses-bloque=1 : Meses por lectura bridge (ctamov+subdiario+subhist)}
                            {--tolerancia=0.01 : Tolerancia absoluta de suma de montos}
                            {--incluir-resumen-sin-detalle : Incluye ctamov V/C/T sin detalle en el mes}
                            {--limite-consola=40 : Máximo de filas de diferencia en consola}
                            {--salida= : Ruta JSON (default storage/logs/anita_asiento_auditoria_*.json)}';

    protected $description = 'Audita asiento por asiento ERP vs Anita (ctamov+subdiario/subhist): fecha, cuentas, montos y centros de costo. Solo lectura.';

    public function handle(AnitaAsientoImportService $service): int
    {
        $desde = trim((string) $this->option('desde'));
        $hasta = trim((string) $this->option('hasta'));
        $mesesBloque = max(1, (int) $this->option('meses-bloque'));
        $tolerancia = max(0.0, (float) $this->option('tolerancia'));
        $incluirResumen = (bool) $this->option('incluir-resumen-sin-detalle');
        $limiteConsola = max(0, (int) $this->option('limite-consola'));

        $empresas = array_values(array_filter(array_map(
            'intval',
            explode(',', (string) $this->option('empresas')),
        )));

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Auditoría %s → %s | empresas %s | bloque %d mes(es) | tolerancia %.4f | SOLO LECTURA',
            $desde,
            $hasta,
            implode(',', $empresas),
            $mesesBloque,
            $tolerancia,
        ));
        $this->line('Criterio: fecha + firma cuenta:centrocosto:monto (misma lógica de planes que importar-asientos-anita).');
        $this->line('Lectura Anita: 1 query por tabla/empresa/bloque (ctamov, subdiario, subhist).');
        $this->line('Excluye ctamov resumen V/C/T con asi_mon_ref≠-1'
            .($incluirResumen ? ' (salvo --incluir-resumen-sin-detalle)' : ''));

        try {
            $r = $service->auditarRango(
                $desde,
                $hasta,
                $empresas,
                $mesesBloque,
                $incluirResumen,
                $tolerancia,
                fn (string $m) => $this->line($m),
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $totalDif = (int) $r['solo_anita'] + (int) $r['solo_erp'] + (int) $r['diferencias'];

        $this->newLine();
        $this->info('Auditoría finalizada (solo lectura)');
        $this->table(
            ['Concepto', 'Valor'],
            [
                ['ctamov filas', (string) $r['ctamov_filas_leidas']],
                ['subdiario filas', (string) $r['subdiario_filas_leidas']],
                ['subhist filas', (string) $r['subhist_filas_leidas']],
                ['ctamov excluidos V/C/T', (string) $r['ctamov_excluidos_cierre']],
                ['ctamov resumen sin detalle', (string) $r['ctamov_resumen_sin_detalle']],
                ['Asientos Anita (planes)', (string) $r['anita_asientos']],
                ['Asientos ERP en rango', (string) $r['erp_asientos_rango']],
                ['OK (fecha+cuenta+cc+monto)', (string) $r['ok']],
                ['Solo Anita', (string) $r['solo_anita']],
                ['Solo ERP', (string) $r['solo_erp']],
                ['Con diferencias', (string) $r['diferencias']],
                ['Total a revisar', (string) $totalDif],
                ['Cuentas faltantes (distintas)', (string) count($r['cuentas_faltantes'] ?? [])],
                ['Errores bridge', (string) count($r['errores'] ?? [])],
            ],
        );

        $detalle = $r['diferencias_detalle'] ?? [];
        if ($detalle !== [] && $limiteConsola > 0) {
            $this->newLine();
            $this->warn('Asientos con diferencias (primeros '.$limiteConsola.'):');
            $filas = [];
            foreach (array_slice($detalle, 0, $limiteConsola) as $d) {
                $filas[] = [
                    (string) ($d['tipo'] ?? ''),
                    (string) ($d['empresa_id'] ?? ''),
                    (string) ($d['numeroasiento'] ?? ''),
                    (string) ($d['erp_id'] ?? '—'),
                    (string) ($d['erp_fecha'] ?? '—'),
                    (string) ($d['anita_fecha'] ?? '—'),
                    (string) ($d['origen_anita'] ?? ''),
                    mb_substr((string) ($d['motivo'] ?? ''), 0, 80),
                ];
            }
            $this->table(
                ['Tipo', 'Emp', 'Nro', 'ERP id', 'Fecha ERP', 'Fecha Anita', 'Origen', 'Motivo'],
                $filas,
            );
            if (count($detalle) > $limiteConsola) {
                $this->line('… y '.(count($detalle) - $limiteConsola).' más (ver JSON/CSV).');
            }
        }

        $stamp = str_replace('-', '', $desde).'_'.str_replace('-', '', $hasta);
        $salidaOpt = trim((string) ($this->option('salida') ?? ''));
        $jsonPath = $salidaOpt !== ''
            ? $salidaOpt
            : storage_path('logs/anita_asiento_auditoria_'.$stamp.'.json');
        $csvPath = preg_replace('/\.json$/i', '.csv', $jsonPath) ?: ($jsonPath.'.csv');

        File::ensureDirectoryExists(dirname($jsonPath));

        $paraJson = $r;
        // Compactar movimientos en JSON para no inflar de más (quedan en CSV resumidos).
        foreach ($paraJson['diferencias_detalle'] as &$fila) {
            $fila['erp_movimientos'] = implode(' || ', $fila['erp_movimientos'] ?? []);
            $fila['anita_movimientos'] = implode(' || ', $fila['anita_movimientos'] ?? []);
        }
        unset($fila);

        File::put($jsonPath, json_encode($paraJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->escribirCsv($csvPath, $detalle);
        $this->line('JSON: '.$jsonPath);
        $this->line('CSV:  '.$csvPath);

        foreach ($r['errores'] ?? [] as $error) {
            $this->error($error);
        }

        return $totalDif > 0 || ($r['errores'] ?? []) !== [] ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $detalle
     */
    private function escribirCsv(string $path, array $detalle): void
    {
        $fh = fopen($path, 'wb');
        if ($fh === false) {
            throw new \RuntimeException('No se pudo escribir CSV: '.$path);
        }

        fprintf($fh, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($fh, [
            'tipo',
            'empresa_id',
            'empresa_anita',
            'numeroasiento',
            'erp_id',
            'erp_fecha',
            'anita_fecha',
            'origen_anita',
            'erp_lineas',
            'anita_lineas',
            'erp_suma',
            'anita_suma',
            'motivo',
            'erp_movimientos',
            'anita_movimientos',
        ], ';');

        foreach ($detalle as $d) {
            fputcsv($fh, [
                $d['tipo'] ?? '',
                $d['empresa_id'] ?? '',
                $d['empresa_anita'] ?? '',
                $d['numeroasiento'] ?? '',
                $d['erp_id'] ?? '',
                $d['erp_fecha'] ?? '',
                $d['anita_fecha'] ?? '',
                $d['origen_anita'] ?? '',
                $d['erp_lineas'] ?? '',
                $d['anita_lineas'] ?? '',
                $d['erp_suma'] ?? '',
                $d['anita_suma'] ?? '',
                $d['motivo'] ?? '',
                implode(' || ', $d['erp_movimientos'] ?? []),
                implode(' || ', $d['anita_movimientos'] ?? []),
            ], ';');
        }

        fclose($fh);
    }
}
