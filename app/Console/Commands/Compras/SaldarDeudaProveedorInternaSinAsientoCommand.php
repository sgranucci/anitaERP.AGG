<?php

declare(strict_types=1);

namespace App\Console\Commands\Compras;

use App\Services\Compras\ProveedorSaldarDeudaInternaSinAsientoService;
use Illuminate\Console\Command;

class SaldarDeudaProveedorInternaSinAsientoCommand extends Command
{
    protected $signature = 'compras:saldar-deuda-proveedor-interna-sin-asiento
                            {--codigo=3968 : Código de proveedor}
                            {--empresas=1,2,3 : empresa_id separadas por coma (1 Biyemas, 2 Kandiko, 3 Rebisco)}
                            {--fecha= : Fecha Y-m-d (default hoy)}
                            {--usuario=1 : usuario_id para el historial de estado}
                            {--ejecutar : Graba NCI/NDI, CC y aplicaciones. Sin este flag solo informa}';

    protected $description = 'Saldar deuda/ficha de un proveedor con NCI (y NDI residual) sin asiento ni sync Anita';

    public function handle(ProveedorSaldarDeudaInternaSinAsientoService $service): int
    {
        $codigo = trim((string) $this->option('codigo'));
        $empresas = array_values(array_filter(array_map(
            static fn ($v) => (int) trim((string) $v),
            explode(',', (string) $this->option('empresas'))
        )));
        $fecha = trim((string) ($this->option('fecha') ?: date('Y-m-d')));
        $usuarioId = max(1, (int) $this->option('usuario'));
        $ejecutar = (bool) $this->option('ejecutar');

        $this->line(sprintf(
            'Proveedor %s · empresas %s · fecha %s · %s',
            $codigo,
            implode(',', $empresas),
            $fecha,
            $ejecutar ? 'GRABA (sin asiento / sin Anita)' : 'solo informe (dry-run)'
        ));
        $this->line('NCI por moneda+cotización aplicada a cada cuota adeudada; créditos residuales se absorben con NDI interno.');

        try {
            $resultado = $service->saldar($codigo, $empresas, $fecha, $ejecutar, $usuarioId);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($resultado['empresas'] as $emp) {
            $this->newLine();
            $this->info(sprintf(
                'Empresa %d · %s (prov_id=%d)',
                $emp['empresa_id'],
                $emp['proveedor'],
                $emp['proveedor_id']
            ));
            $this->table(['Concepto', 'Valor'], [
                ['Deudas abiertas', (string) $emp['deudas_abiertas']],
                ['Créditos abiertos', (string) $emp['creditos_abiertos']],
                ['Apps crédito→deuda (misma mon/cot)', (string) $emp['aplicaciones_credito_existente']],
                ['NCI a crear', (string) $emp['nci_a_crear']],
                ['NDI a crear (créditos residuales)', (string) $emp['ndi_a_crear']],
                ['Totales NCI por moneda', json_encode($emp['totales_nci_por_moneda'])],
                ['Totales NDI por moneda', json_encode($emp['totales_ndi_por_moneda'])],
            ]);

            if ($emp['nci'] !== []) {
                $this->line('NCI:');
                foreach ($emp['nci'] as $nci) {
                    $this->line(sprintf(
                        '  mon=%d cot=%s total=%s cuotas=%d',
                        $nci['moneda_id'],
                        $nci['cotizacion'],
                        number_format((float) $nci['total'], 2, ',', '.'),
                        $nci['cuotas']
                    ));
                }
            }
            if ($emp['ndi'] !== []) {
                $this->line('NDI residual:');
                foreach ($emp['ndi'] as $ndi) {
                    $this->line(sprintf(
                        '  mon=%d cot=%s total=%s creditos=%d',
                        $ndi['moneda_id'],
                        $ndi['cotizacion'],
                        number_format((float) $ndi['total'], 2, ',', '.'),
                        $ndi['creditos']
                    ));
                }
            }

            if ($ejecutar) {
                $this->line('Comprobantes: '.implode(', ', $emp['comprobantes_creados'] ?: ['—']));
                $this->line(sprintf(
                    'Aplicaciones (pares): %d · deudas restantes: %s · créditos restantes: %s · total_deuda: %s',
                    $emp['aplicaciones_creadas'],
                    $emp['deudas_restantes'] ?? '?',
                    $emp['creditos_restantes'] ?? '?',
                    number_format((float) ($emp['total_deuda_final'] ?? 0), 2, ',', '.')
                ));
            }
        }

        if (! $ejecutar) {
            $this->newLine();
            $this->warn('Dry-run OK. Para grabar: agregá --ejecutar');
        } else {
            $this->newLine();
            $this->info('Listo: deuda y ficha en 0, sin asientos ni sync Anita.');
        }

        return self::SUCCESS;
    }
}
