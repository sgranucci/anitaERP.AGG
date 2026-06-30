<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ventas\Gastronomia\GastronomiaCircuitosFacturacionIntegridadService;
use Illuminate\Console\Command;

class GastronomiaAuditoriaCircuitosFacturacion extends Command
{
    protected $signature = 'gastronomia:auditoria-circuitos-facturacion
                            {--empresa=1 : ID empresa ERP}
                            {--fecha-desde=2026-06-22 : Jornada inicial}
                            {--fecha-hasta= : Jornada final (default: hoy)}
                            {--tolerancia=0.02 : Tolerancia en pesos}
                            {--corregir : Quita emisión gastronomía errónea en ventas estacionamiento dual-tag}
                            {--dry-run : Simula --corregir sin grabar}';

    protected $description = 'Auditoría integridad gastronomía/estacionamiento, asientos Waitry y rendgastro';

    public function handle(GastronomiaCircuitosFacturacionIntegridadService $service): int
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(0);

        $empresaId = max(1, (int) $this->option('empresa'));
        $fechaDesde = trim((string) $this->option('fecha-desde'));
        $fechaHasta = trim((string) ($this->option('fecha-hasta') ?? ''));
        if ($fechaHasta === '') {
            $fechaHasta = date('Y-m-d');
        }
        $tolerancia = max(0.0, (float) $this->option('tolerancia'));
        $dryRun = (bool) $this->option('dry-run');

        $this->info(sprintf(
            'Auditoría circuitos facturación | empresa %d | jornada %s → %s',
            $empresaId,
            $fechaDesde,
            $fechaHasta,
        ));

        $dual = $service->listarEtiquetadoDualErroneo($empresaId, $fechaDesde, $fechaHasta);
        $this->line('');
        $this->comment('Etiquetado dual erróneo (estacionamiento + gastronomía): '.count($dual));
        foreach ($dual as $fila) {
            $this->line(sprintf(
                '  %s | $ %s | estac %s | gastro %s | %s',
                $fila['codigo'],
                number_format((float) $fila['total'], 2, ',', '.'),
                $fila['pc_estac'],
                $fila['pc_gastro'],
                mb_substr((string) $fila['leyenda'], 0, 45),
            ));
        }

        if ((bool) $this->option('corregir')) {
            $this->line('');
            $resultado = $service->corregirEtiquetadoDualErroneo($empresaId, $fechaDesde, $fechaHasta, $dryRun);
            $this->info(($dryRun ? '[dry-run] ' : '').'Corregidas: '.($resultado['corregidas'] ?? 0));
            if (($resultado['errores'] ?? []) !== []) {
                foreach ($resultado['errores'] as $err) {
                    $this->error($err);
                }
            }
        }

        $this->line('');
        $this->comment('Control diario ERP / rendg / asientos Waitry:');
        $dias = $service->auditarAsientosVsRendgPorRango($empresaId, $fechaDesde, $fechaHasta, $tolerancia);
        foreach ($dias as $dia) {
            if (($dia['estado'] ?? '') === 'jornada_abierta_o_inexistente') {
                $this->line('  '.$dia['fecha_jornada'].' — jornada abierta o inexistente');

                continue;
            }

            $this->line(sprintf(
                '  %s | %s | ERP $ %s | rendg $ %s | asientos $ %s | Δ rendg-asientos $ %s | dual-tag %d',
                $dia['fecha_jornada'],
                $dia['estado'],
                number_format((float) ($dia['erp_gastro_neto'] ?? 0), 2, ',', '.'),
                number_format((float) ($dia['rendg_total'] ?? 0), 2, ',', '.'),
                number_format((float) ($dia['asientos_total'] ?? 0), 2, ',', '.'),
                $dia['diff_rendg_asientos'] !== null
                    ? number_format((float) $dia['diff_rendg_asientos'], 2, ',', '.')
                    : '—',
                (int) ($dia['dual_tag'] ?? 0),
            ));
        }

        $difs = array_filter($dias, static fn (array $d): bool => in_array($d['estado'] ?? '', ['DIF_rendg_asientos', 'DIF_erp_rendg'], true));

        return $difs === [] && $dual === [] ? self::SUCCESS : self::FAILURE;
    }
}
