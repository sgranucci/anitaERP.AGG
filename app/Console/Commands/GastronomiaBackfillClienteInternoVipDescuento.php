<?php

namespace App\Console\Commands;

use App\Services\Ventas\Gastronomia\GastronomiaBackfillClienteInternoVipDescuentoService;
use Illuminate\Console\Command;

class GastronomiaBackfillClienteInternoVipDescuento extends Command
{
    protected $signature = 'gastronomia:backfill-cliente-interno-vip-descuento
                            {--fecha-desde=2026-06-01 : Fecha jornada desde (Y-m-d)}
                            {--fecha-hasta=2026-06-30 : Fecha jornada hasta (Y-m-d)}
                            {--empresas=1,2,3 : empresa_id separados por coma}
                            {--dry-run : Simular sin grabar}
                            {--sin-cache-anita : Consultar resvta en Anita por comprobante (más lento)}';

    protected $description = 'Corrige cliente_interno_descuento: desc. 40 sin CANJE PLATINO; desc. 10 según resv_cliente Anita';

    public function handle(GastronomiaBackfillClienteInternoVipDescuentoService $service): int
    {
        $fechaDesde = trim((string) $this->option('fecha-desde'));
        $fechaHasta = trim((string) $this->option('fecha-hasta'));
        if ($fechaDesde === '' || $fechaHasta === '') {
            $this->error('Indique --fecha-desde y --fecha-hasta.');

            return self::FAILURE;
        }

        $empresaIds = $this->parseEmpresas((string) $this->option('empresas'));
        $dryRun = (bool) $this->option('dry-run');
        $usarCache = ! (bool) $this->option('sin-cache-anita');

        $this->line("Período {$fechaDesde} — {$fechaHasta}, empresas: ".implode(',', $empresaIds)
            .($dryRun ? ' [dry-run]' : '')
            .($usarCache ? ' [cache Anita]' : ' [Anita por comprobante]'));

        try {
            $resultado = $service->ejecutar($fechaDesde, $fechaHasta, $empresaIds, $dryRun, $usarCache);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Concepto', 'Valor'], [
            ['Desc. 40: quitado CANJE PLATINO (1500)', (string) ($resultado['desc40_limpiadas_platino'] ?? 0)],
            ['Desc. 40: reasignado cliente real Anita', (string) ($resultado['desc40_reasignadas'] ?? 0)],
            ['Desc. 10: corregido desde Anita', (string) ($resultado['desc10_corregidas'] ?? 0)],
            ['Import Anita: descuento asignado desde resvta', (string) ($resultado['import_desc_asignadas'] ?? 0)],
            ['Omitidas (sin cambio)', (string) ($resultado['omitidas'] ?? 0)],
            ['Sin resvta Anita', (string) ($resultado['sin_resvta'] ?? 0)],
            ['Errores', (string) count($resultado['errores'] ?? [])],
        ]);

        foreach ($resultado['por_empresa'] ?? [] as $empresaId => $stats) {
            $this->line("Empresa {$empresaId}: desc40 limpiadas ".($stats['desc40_limpiadas_platino'] ?? 0)
                .', desc40 reasignadas '.($stats['desc40_reasignadas'] ?? 0)
                .', desc10 corregidas '.($stats['desc10_corregidas'] ?? 0)
                .', import desc asignadas '.($stats['import_desc_asignadas'] ?? 0));
        }

        foreach ($resultado['errores'] ?? [] as $err) {
            $this->warn($err);
        }

        return ($resultado['errores'] ?? []) === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return list<int>
     */
    private function parseEmpresas(string $raw): array
    {
        $ids = [];
        foreach (explode(',', $raw) as $part) {
            $id = (int) trim($part);
            if ($id > 0 && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids !== [] ? $ids : [1, 2, 3];
    }
}
