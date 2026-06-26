<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Configuracion\Empresa;
use App\Services\Ventas\Gastronomia\GastronomiaAuditoriaMesBulkAnitaErpService;
use Illuminate\Console\Command;

class GastronomiaAuditoriaMesBulkAnitaErp extends Command
{
    protected $signature = 'gastronomia:auditoria-mes-bulk-anita-erp
                            {--empresa=2 : empresa_id ERP}
                            {--fecha-desde= : Y-m-d jornada inicial (default: primer día del mes)}
                            {--fecha-hasta= : Y-m-d jornada final (default: último día del mes)}
                            {--puntoventa= : Código PV opcional}
                            {--tolerancia=0.02 : Tolerancia en pesos}
                            {--erp-desde= : Jornada mínima ERP (pre-ERP en Anita no alerta; default: primera jornada ERP del rango)}
                            {--forzar-descarga : Re-descarga Anita a cache local (venta, vengrav, ctamov, rendgastro) antes de auditar}
                            {--solo-cache : Solo usa cache existente del rango (sin consultar bridge)}
                            {--csv= : CSV resumen por jornada}
                            {--csv-detalle= : CSV detalle de problemas}';

    protected $description = 'Audita gastronomía ERP vs Anita por mes (bulk en memoria desde cache local). Exige coincidencia de total, gravado, IVA y exento en cabecera venta.';

    public function handle(GastronomiaAuditoriaMesBulkAnitaErpService $service): int
    {
        $empresaId = (int) $this->option('empresa');
        $empresa = Empresa::query()->find($empresaId);
        if (! $empresa) {
            $this->error("Empresa {$empresaId} inexistente.");

            return self::FAILURE;
        }

        $fechaDesde = trim((string) ($this->option('fecha-desde') ?: date('Y-m-01')));
        $fechaHasta = trim((string) ($this->option('fecha-hasta') ?: date('Y-m-t', strtotime($fechaDesde))));
        $tolerancia = max(0.0, (float) $this->option('tolerancia'));
        $pv = $this->option('puntoventa');
        $pv = is_string($pv) && trim($pv) !== '' ? trim($pv) : null;
        $erpDesde = trim((string) ($this->option('erp-desde') ?? ''));
        $forzarDescarga = (bool) $this->option('forzar-descarga');
        $soloCache = (bool) $this->option('solo-cache');

        if ($soloCache && $forzarDescarga) {
            $this->error('No combine --solo-cache con --forzar-descarga.');

            return self::FAILURE;
        }

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Bulk Anita ↔ ERP | %s (id %d) | jornada %s → %s | tolerancia %.2f%s',
            $empresa->nombre ?? '',
            $empresaId,
            $fechaDesde,
            $fechaHasta,
            $tolerancia,
            $soloCache ? ' | solo cache' : ($forzarDescarga ? ' | re-descarga cache' : ' | cache si existe'),
        ));
        $this->comment('Cache: storage/app/anita_audit_cache/empresa_{id}_{desde}_{hasta}/');

        try {
            $informe = $service->auditar(
                $empresaId,
                $fechaDesde,
                $fechaHasta,
                $tolerancia,
                $pv,
                $erpDesde !== '' ? $erpDesde : null,
                $forzarDescarga,
                $soloCache,
            );
            $service->finalizarResumenJornadas($informe);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $c = $informe['conteo'];
        $cache = $informe['cache'] ?? [];
        $this->newLine();
        if ($cache !== []) {
            $this->line(sprintf(
                'Cache Anita | generado %s | venta %d | vengrav %d | ctamov %d | rendg %d filas',
                $cache['generado_at'] ?? '—',
                $cache['counts']['venta'] ?? 0,
                $cache['counts']['vengrav'] ?? 0,
                $cache['counts']['ctamov'] ?? 0,
                $cache['counts']['rendgastro_filas'] ?? 0,
            ));
            $this->line('  → '.($cache['directorio'] ?? '—'));
        }
        $this->info('Cabeceras venta Anita (cache): '.($informe['cabeceras_anita_bulk'] ?? 0));
        $this->line('Ventas gastronomía ERP: '.($informe['ventas_erp_gastronomia'] ?? 0));
        $this->line('Jornada ERP activa desde: '.($informe['fecha_jornada_erp_desde'] ?? '—').' (Anita anterior = pre-ERP, no alerta)');

        $this->table(
            ['Concepto', 'Cantidad'],
            [
                ['OK (montos)', (string) ($c['ok'] ?? 0)],
                ['Solo ERP (falta Anita)', (string) ($c['solo_erp'] ?? 0)],
                ['Diferencia importes (total/gravado/IVA/exento)', (string) ($c['diferencia'] ?? 0)],
                ['Solo Anita (sin ERP, alerta)', (string) ($c['solo_anita'] ?? 0)],
                ['Anita FSL / slots (excl.)', (string) ($c['excluido_estacionamiento'] ?? 0)],
                ['Anita pre-ERP (excl.)', (string) ($c['excluido_pre_erp'] ?? 0)],
                ['Anita otro tipo (excl.)', (string) ($c['excluido_otro_tipo'] ?? 0)],
                ['Error clave ERP', (string) ($c['error'] ?? 0)],
            ],
        );

        $filasJornada = [];
        foreach ($informe['resumen_por_jornada'] ?? [] as $dia) {
            if (($dia['ventas_erp'] ?? 0) === 0 && ($dia['solo_anita'] ?? 0) === 0 && ($dia['excluido_pre_erp'] ?? 0) === 0) {
                continue;
            }
            $filasJornada[] = [
                $dia['fecha_jornada'],
                (string) ($dia['ventas_erp'] ?? 0),
                (string) ($dia['ok'] ?? 0),
                (string) ($dia['solo_erp'] ?? 0),
                (string) ($dia['diferencia'] ?? 0),
                (string) ($dia['solo_anita'] ?? 0),
                number_format((float) ($dia['total_erp'] ?? 0), 2, '.', ''),
                $dia['estado'] ?? '—',
            ];
        }

        if ($filasJornada !== []) {
            $this->newLine();
            $this->table(
                ['Jornada', 'ERP', 'OK', 'Solo ERP', 'Dif', 'Solo Anita', 'Total ERP', 'Estado'],
                $filasJornada,
            );
        }

        $problemas = array_filter(
            $informe['detalle_problemas'] ?? [],
            static fn (array $f): bool => in_array($f['estado'] ?? '', ['solo_erp', 'diferencia', 'solo_anita'], true),
        );
        if ($problemas !== []) {
            $this->newLine();
            $this->warn('Detalle problemas (primeras 25)');
            foreach (array_slice(array_values($problemas), 0, 25) as $f) {
                $this->line(sprintf(
                    '  %s | %s | %s | %s | ERP %s | Anita %s',
                    $f['estado'] ?? '',
                    $f['fecha_jornada'] ?? '',
                    $f['puntoventa'] ?? '',
                    $f['clave'] ?? ($f['numero'] ?? ''),
                    isset($f['total_erp']) ? number_format((float) $f['total_erp'], 2) : '—',
                    isset($f['total_anita']) ? number_format((float) $f['total_anita'], 2) : '—',
                ));
            }
        }

        $csv = trim((string) ($this->option('csv') ?? ''));
        if ($csv !== '') {
            $service->guardarCsv($csv, $informe);
            $this->info('CSV resumen: '.$csv);
        }

        $csvDet = trim((string) ($this->option('csv-detalle') ?? ''));
        if ($csvDet !== '') {
            $service->guardarDetalleCsv($csvDet, $informe);
            $this->info('CSV detalle: '.$csvDet);
        }

        $hayAlerta = ($c['solo_erp'] ?? 0) > 0 || ($c['diferencia'] ?? 0) > 0;

        return $hayAlerta ? self::FAILURE : self::SUCCESS;
    }
}
