<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Ventas\Gastronomia\GastronomiaControlVentasCobranzasAnitaService;
use Illuminate\Console\Command;

class GastronomiaControlVentasCobranzasAnita extends Command
{
    protected $signature = 'gastronomia:control-ventas-cobranzas-anita
                            {--desde= : Inicio ventana (Y-m-d H:i:s), inclusive}
                            {--hasta= : Fin ventana (Y-m-d H:i:s), exclusivo}
                            {--empresas=1,2,3 : empresa_id separados por coma (vacío = todas)}
                            {--tolerancia=0.02 : Tolerancia en pesos}
                            {--todas : Incluir ventas OK en detalle}
                            {--export= : Ruta TSV/CSV para exportar detalle}';

    protected $description = 'Control venta a venta: total ERP, cobrado y cabecera Anita (bridge) en ventana horaria';

    public function handle(GastronomiaControlVentasCobranzasAnitaService $service): int
    {
        $desde = trim((string) $this->option('desde'));
        $hasta = trim((string) $this->option('hasta'));
        if ($desde === '' || $hasta === '') {
            $this->error('Indique --desde y --hasta (ej. "2026-06-14 18:00:00" "2026-06-15 00:54:00").');

            return self::FAILURE;
        }

        $empresasRaw = trim((string) $this->option('empresas'));
        $empresaIds = [];
        if ($empresasRaw !== '') {
            foreach (explode(',', $empresasRaw) as $part) {
                $id = (int) trim($part);
                if ($id > 0) {
                    $empresaIds[] = $id;
                }
            }
        }

        $tolerancia = max(0.0, (float) $this->option('tolerancia'));
        $soloProblemas = ! (bool) $this->option('todas');

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Ventana %s → %s | empresas %s | tolerancia %s',
            $desde,
            $hasta,
            $empresaIds === [] ? 'todas' : implode(',', $empresaIds),
            number_format($tolerancia, 2, '.', ''),
        ));

        try {
            $resultado = $service->ejecutar($desde, $hasta, $empresaIds, $tolerancia, $soloProblemas);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $res = $resultado['resumen'];
        $this->newLine();
        $this->info('Resumen');
        $this->table(
            ['Concepto', 'Valor'],
            [
                ['Ventas gastronomía ERP', (string) ($res['ventas'] ?? 0)],
                ['OK', (string) ($res['conteo']['ok'] ?? 0)],
                ['Solo ERP (sin Anita)', (string) ($res['conteo']['solo_erp'] ?? 0)],
                ['Diferencia Anita (total)', (string) ($res['conteo']['diferencia_anita'] ?? 0)],
                ['Solo desglose grav/IVA', (string) ($res['conteo']['desglose_anita'] ?? 0)],
                ['Cobranza desfasada', (string) ($res['conteo']['cobranza_desfasada'] ?? 0)],
                ['Error parse/clave', (string) ($res['conteo']['error'] ?? 0)],
            ],
        );

        $tot = $res['totales'] ?? [];
        $this->table(
            ['', 'Monto'],
            [
                ['Total venta ERP', number_format((float) ($tot['venta_erp'] ?? 0), 2, '.', '')],
                ['Total cobrado ERP', number_format((float) ($tot['cobrado_erp'] ?? 0), 2, '.', '')],
                ['Total Anita ven_monto', number_format((float) ($tot['anita_ven_monto'] ?? 0), 2, '.', '')],
                ['Δ venta − Anita', number_format((float) ($res['delta_venta_anita'] ?? 0), 2, '.', '')],
                ['Δ venta − cobrado', number_format((float) ($res['delta_venta_cobrado'] ?? 0), 2, '.', '')],
            ],
        );

        if (($resultado['por_empresa'] ?? []) !== []) {
            $this->newLine();
            $this->comment('Por empresa');
            $tablaEmp = [];
            foreach ($resultado['por_empresa'] as $row) {
                $tablaEmp[] = [
                    $row['empresa_id'] ?? '',
                    $row['ventas'] ?? 0,
                    number_format((float) ($row['venta_erp'] ?? 0), 2, '.', ''),
                    number_format((float) ($row['cobrado_erp'] ?? 0), 2, '.', ''),
                    number_format((float) ($row['anita_ven_monto'] ?? 0), 2, '.', ''),
                    $row['problemas'] ?? 0,
                ];
            }
            $this->table(
                ['Empresa', 'Ventas', 'Tot ERP', 'Cobrado', 'Anita', 'Problemas'],
                $tablaEmp,
            );
        }

        $filas = $resultado['filas'];
        if ($filas === []) {
            $this->info('Sin problemas en el detalle (use --todas para listar OK).');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->comment('Detalle'.($soloProblemas ? ' (solo problemas)' : ''));

        $tabla = [];
        foreach (array_slice($filas, 0, 50) as $fila) {
            $tabla[] = [
                $fila['estado'] ?? '',
                $fila['venta_id'] ?? '',
                $fila['codigo'] ?? '',
                $fila['pv_codigo'] ?? '',
                $fila['created_at'] ?? '',
                number_format((float) ($fila['total_erp'] ?? 0), 2, '.', ''),
                number_format((float) ($fila['cobrado_erp'] ?? 0), 2, '.', ''),
                $fila['anita_ven_monto'] !== null ? number_format((float) $fila['anita_ven_monto'], 2, '.', '') : '—',
                $fila['observaciones'] ?? '',
            ];
        }
        $this->table(
            ['Estado', 'Venta', 'Comprobante', 'PV', 'Creado', 'Tot ERP', 'Cobrado', 'Anita', 'Obs.'],
            $tabla,
        );
        if (count($filas) > 50) {
            $this->line('… '.(count($filas) - 50).' filas más (use --export).');
        }

        $export = trim((string) ($this->option('export') ?? ''));
        if ($export !== '') {
            $this->exportarTsv($export, $filas);
            $this->info('Exportado: '.$export);
        }

        $problemas = (int) ($res['conteo']['solo_erp'] ?? 0)
            + (int) ($res['conteo']['diferencia_anita'] ?? 0)
            + (int) ($res['conteo']['cobranza_desfasada'] ?? 0)
            + (int) ($res['conteo']['error'] ?? 0);

        return $problemas > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function exportarTsv(string $path, array $filas): void
    {
        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo escribir '.$path);
        }

        fputcsv($handle, [
            'estado', 'venta_id', 'codigo', 'clave', 'empresa_id', 'pv_codigo', 'fecha_jornada', 'created_at',
            'total_erp', 'cobrado_erp', 'anita_ven_monto', 'anita_gravado', 'anita_iva',
            'delta_anita', 'delta_cobranza', 'exento_cobranza', 'observaciones',
        ], "\t");

        foreach ($filas as $fila) {
            fputcsv($handle, [
                $fila['estado'] ?? '',
                $fila['venta_id'] ?? '',
                $fila['codigo'] ?? '',
                $fila['clave'] ?? '',
                $fila['empresa_id'] ?? '',
                $fila['pv_codigo'] ?? '',
                $fila['fecha_jornada'] ?? '',
                $fila['created_at'] ?? '',
                $fila['total_erp'] ?? '',
                $fila['cobrado_erp'] ?? '',
                $fila['anita_ven_monto'] ?? '',
                $fila['anita_gravado'] ?? '',
                $fila['anita_iva'] ?? '',
                $fila['delta_anita'] ?? '',
                $fila['delta_cobranza'] ?? '',
                ! empty($fila['exento_cobranza']) ? '1' : '0',
                $fila['observaciones'] ?? '',
            ], "\t");
        }

        fclose($handle);
    }
}
