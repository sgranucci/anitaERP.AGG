<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Compras\ComprobanteProveedorImportarDesdeAnitaService;
use Illuminate\Console\Command;

class ComprobanteProveedorImportarDesdeAnitaCommand extends Command
{
    protected $signature = 'comprobante-proveedor:importar-desde-anita
                            {--codigo= : Código de proveedor Anita (ej. 3593 AGT)}
                            {--empresa= : Código empresa Anita (opcional)}
                            {--desde= : Fecha ISO desde, inclusive (com_fecha)}
                            {--hasta= : Fecha ISO hasta, inclusive}
                            {--limite= : Máximo de comprobantes nuevos a crear}
                            {--usuario-id=1 : usuario_id de auditoría}
                            {--dry-run : Solo analiza (default si no hay --ejecutar)}
                            {--ejecutar : Persiste en ERP (no escribe Anita)}';

    protected $description = 'Importa compra/promov/aplmovp de un proveedor Anita → ERP (omite facturas ya cargadas)';

    public function handle(ComprobanteProveedorImportarDesdeAnitaService $service): int
    {
        $codigo = trim((string) $this->option('codigo'));
        if ($codigo === '') {
            $this->error('Indique --codigo de proveedor (ej. --codigo=3593).');

            return self::FAILURE;
        }

        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = ! $ejecutar || (bool) $this->option('dry-run');
        if ($ejecutar && (bool) $this->option('dry-run')) {
            $this->error('No combine --ejecutar con --dry-run.');

            return self::FAILURE;
        }

        $empresaOpt = $this->option('empresa');
        $empresaCodigo = ($empresaOpt !== null && $empresaOpt !== '') ? (int) $empresaOpt : null;
        $limiteOpt = $this->option('limite');
        $limite = ($limiteOpt !== null && $limiteOpt !== '') ? (int) $limiteOpt : null;
        $desde = $this->option('desde') ? (string) $this->option('desde') : null;
        $hasta = $this->option('hasta') ? (string) $this->option('hasta') : null;
        $usuarioId = max(1, (int) $this->option('usuario-id'));

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Proveedor %s | empresa %s | %s → %s | %s',
            $codigo,
            $empresaCodigo ?: 'todas',
            $desde ?: 'sin desde',
            $hasta ?: 'sin hasta',
            $dryRun ? 'DRY-RUN' : 'EJECUTAR'
        ));
        $this->line('Fuente Anita: compra + promov + aplmovp (concmov solo para conceptos IVA).');
        $this->line('Las facturas ya cargadas en ERP no se importan. OPA sin aplicar sí se traen.');

        try {
            $stats = $service->importar(
                $codigo,
                $dryRun,
                $desde,
                $hasta,
                $empresaCodigo,
                $usuarioId,
                $limite,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Proveedor ERP #'.$stats['proveedor_id'].' '.$stats['proveedor_nombre']);
        $this->table(['Métrica', 'Cantidad'], [
            ['compra Anita', $stats['anita_compra']],
            ['promov Anita', $stats['anita_promov']],
            ['aplmovp Anita', $stats['anita_aplmovp']],
            ['concmov (líneas)', $stats['anita_concmov']],
            ['A crear', $stats['a_crear']],
            ['Creadas', $stats['creadas']],
            ['Ya en ERP (omitidas)', $stats['omitidas_ya_en_erp']],
            ['Duplicadas en lote Anita', $stats['duplicadas_lote']],
            ['Sin tipo ERP', $stats['sin_tipo']],
            ['Sin empresa ERP', $stats['sin_empresa']],
            ['Cuotas (plan)', $stats['cuotas']],
            ['Conceptos (plan)', $stats['conceptos']],
            ['CC creadas', $stats['cc']],
            ['Aplicaciones Anita (pares)', $stats['aplicaciones_anita']],
            ['Aplicaciones a grabar/grabadas', $stats['aplicaciones']],
            ['Pagos sintéticos (OP)', $stats['aplicaciones_pago_sintetico']],
            ['Aplicaciones omitidas', $stats['aplicaciones_omitidas']],
            ['OPA Anita', $stats['adelantos_anita']],
            ['OPA sin aplicar a crear', $stats['adelantos_a_crear']],
            ['OPA creados', $stats['adelantos_creados']],
            ['OPA ya en ERP', $stats['adelantos_omitidos_ya_en_erp']],
            ['Errores', count($stats['errores'])],
        ]);

        if ($stats['muestra'] !== []) {
            $this->newLine();
            $this->line('Muestra a crear (hasta 20):');
            $this->table(
                ['Comprobante', 'Emp', 'Fecha', 'Total', 'Cuotas', 'Nro interno'],
                array_map(static fn (array $r) => [
                    $r['etiqueta'],
                    $r['empresa_id'] ?? '—',
                    $r['fecha'],
                    number_format((float) $r['total'], 2, ',', '.'),
                    $r['cuotas'],
                    $r['nro_interno'] ?: '—',
                ], $stats['muestra'])
            );
        }

        if ($stats['muestra_adelantos'] !== []) {
            $this->newLine();
            $this->line('OPA sin aplicar (hasta 20):');
            $this->table(
                ['Adelanto', 'Emp', 'Fecha', 'Pendiente'],
                array_map(static fn (array $r) => [
                    $r['etiqueta'],
                    $r['empresa_id'] ?? '—',
                    $r['fecha'],
                    number_format((float) $r['total'], 2, ',', '.'),
                ], $stats['muestra_adelantos'])
            );
        }

        if ($stats['omitidas_detalle'] !== []) {
            $muestraOmitidas = array_slice($stats['omitidas_detalle'], 0, 15);
            $this->newLine();
            $this->line('Ya en ERP (muestra, '.count($stats['omitidas_detalle']).' total):');
            $this->table(
                ['Comprobante', 'ERP id', 'Motivo'],
                array_map(static fn (array $o) => [
                    $o['etiqueta'],
                    $o['id'],
                    $o['motivo'],
                ], $muestraOmitidas)
            );
        }

        foreach ($stats['errores'] as $error) {
            $this->warn((string) $error);
        }

        if ($dryRun) {
            $this->comment('Dry-run: no se grabó nada. Para persistir: mismo comando con --ejecutar.');
        }

        $fallos = (int) $stats['sin_tipo'] + (int) $stats['sin_empresa'] + (int) $stats['sin_fecha'];

        return $fallos === 0 ? self::SUCCESS : self::FAILURE;
    }
}
