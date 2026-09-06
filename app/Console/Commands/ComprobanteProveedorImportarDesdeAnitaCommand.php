<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Compras\ComprobanteProveedorImportarDesdeAnitaService;
use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportBridgeReader;
use Illuminate\Console\Command;

class ComprobanteProveedorImportarDesdeAnitaCommand extends Command
{
    protected $signature = 'comprobante-proveedor:importar-desde-anita
                            {--codigo= : Código de proveedor Anita (ej. 3593 AGT); omitir con --todos}
                            {--todos : Recorre todos los proveedores con compra en el rango}
                            {--empresa= : Código empresa Anita (opcional)}
                            {--desde= : Fecha ISO desde, inclusive (com_fecha)}
                            {--hasta= : Fecha ISO hasta, inclusive}
                            {--limite= : Máximo de comprobantes nuevos a crear (por proveedor)}
                            {--usuario-id=1 : usuario_id de auditoría}
                            {--sin-cuenta-corriente : Solo documentos (CP/OPA); no crea CC ni aplicaciones}
                            {--dry-run : Solo analiza (default si no hay --ejecutar)}
                            {--ejecutar : Persiste en ERP (no escribe Anita)}';

    protected $description = 'Importa compra/promov/aplmovp de un proveedor Anita → ERP (omite facturas ya cargadas)';

    public function handle(ComprobanteProveedorImportarDesdeAnitaService $service): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = ! $ejecutar || (bool) $this->option('dry-run');
        if ($ejecutar && (bool) $this->option('dry-run')) {
            $this->error('No combine --ejecutar con --dry-run.');

            return self::FAILURE;
        }

        $sinCc = (bool) $this->option('sin-cuenta-corriente');
        $todos = (bool) $this->option('todos');
        $codigo = trim((string) $this->option('codigo'));
        if (! $todos && $codigo === '') {
            $this->error('Indique --codigo de proveedor (ej. --codigo=3593) o --todos.');

            return self::FAILURE;
        }
        if ($todos && $codigo !== '') {
            $this->error('No combine --codigo con --todos.');

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
            '%s | empresa %s | %s → %s | %s%s',
            $todos ? 'Proveedores: TODOS con compra en rango' : 'Proveedor '.$codigo,
            $empresaCodigo ?: 'todas',
            $desde ?: 'sin desde',
            $hasta ?: 'sin hasta',
            $dryRun ? 'DRY-RUN' : 'EJECUTAR',
            $sinCc ? ' | SIN cuenta corriente' : '',
        ));
        $this->line('Fuente Anita: compra + promov + aplmovp (concmov solo para conceptos IVA).');
        if ($sinCc) {
            $this->comment('Modo documento: crea CP (+OPA cabecera). Omite CC, aplicaciones y pagos sintéticos.');
        } else {
            $this->line('Las facturas ya cargadas en ERP no se importan. OPA sin aplicar sí se traen.');
        }

        $codigos = $todos
            ? $this->listarCodigosProveedores($desde, $hasta, $empresaCodigo)
            : [$codigo];

        if ($codigos === []) {
            $this->warn('No hay proveedores con compra Anita en el rango.');

            return self::SUCCESS;
        }

        $this->line('Proveedores a procesar: '.count($codigos));

        $totales = [
            'proveedores_ok' => 0,
            'proveedores_error' => 0,
            'a_crear' => 0,
            'creadas' => 0,
            'omitidas_ya_en_erp' => 0,
            'adelantos_a_crear' => 0,
            'adelantos_creados' => 0,
            'aplicaciones_omitidas' => 0,
            'cc' => 0,
            'sin_proveedor_erp' => 0,
        ];
        $erroresGlobales = [];

        foreach ($codigos as $i => $cod) {
            $this->newLine();
            $this->info(sprintf('[%d/%d] Proveedor %s', $i + 1, count($codigos), $cod));
            try {
                $stats = $service->importar(
                    $cod,
                    $dryRun,
                    $desde,
                    $hasta,
                    $empresaCodigo,
                    $usuarioId,
                    $limite,
                    $sinCc,
                );
            } catch (\Throwable $e) {
                $totales['proveedores_error']++;
                if (str_contains($e->getMessage(), 'no está en el ERP')) {
                    $totales['sin_proveedor_erp']++;
                }
                $erroresGlobales[] = $cod.': '.$e->getMessage();
                $this->error($e->getMessage());

                continue;
            }

            $totales['proveedores_ok']++;
            $totales['a_crear'] += (int) $stats['a_crear'];
            $totales['creadas'] += (int) $stats['creadas'];
            $totales['omitidas_ya_en_erp'] += (int) $stats['omitidas_ya_en_erp'];
            $totales['adelantos_a_crear'] += (int) ($stats['adelantos_a_crear'] ?? 0);
            $totales['adelantos_creados'] += (int) ($stats['adelantos_creados'] ?? 0);
            $totales['aplicaciones_omitidas'] += (int) ($stats['aplicaciones_omitidas'] ?? 0);
            $totales['cc'] += (int) ($stats['cc'] ?? 0);

            $this->table(['Métrica', 'Cantidad'], [
                ['compra Anita', $stats['anita_compra']],
                ['A crear CP', $stats['a_crear']],
                ['Creadas', $stats['creadas']],
                ['Ya en ERP', $stats['omitidas_ya_en_erp']],
                ['OPA a crear', $stats['adelantos_a_crear']],
                ['OPA creados', $stats['adelantos_creados']],
                ['CC', $stats['cc']],
                ['Aplicaciones omitidas', $stats['aplicaciones_omitidas']],
                ['Sin CC', ! empty($stats['sin_cuenta_corriente']) ? 'sí' : 'no'],
            ]);

            if ($stats['muestra'] !== []) {
                $this->line('Muestra CP (hasta 5):');
                $this->table(
                    ['Comprobante', 'Emp', 'Fecha', 'Total'],
                    array_map(static fn (array $r) => [
                        $r['etiqueta'],
                        $r['empresa_id'] ?? '—',
                        $r['fecha'],
                        number_format((float) $r['total'], 2, ',', '.'),
                    ], array_slice($stats['muestra'], 0, 5))
                );
            }

            foreach ($stats['errores'] as $error) {
                $this->warn((string) $error);
            }
        }

        $this->newLine();
        $this->info($dryRun ? 'Dry-run consolidado' : 'Importación consolidada');
        $this->table(['Total', 'Valor'], [
            ['Proveedores OK', $totales['proveedores_ok']],
            ['Proveedores error', $totales['proveedores_error']],
            ['Sin proveedor en ERP', $totales['sin_proveedor_erp']],
            ['CP a crear / creados', $totales['a_crear'].' / '.$totales['creadas']],
            ['CP ya en ERP', $totales['omitidas_ya_en_erp']],
            ['OPA a crear / creados', $totales['adelantos_a_crear'].' / '.$totales['adelantos_creados']],
            ['CC creadas', $totales['cc']],
            ['Aplicaciones omitidas (sin CC)', $totales['aplicaciones_omitidas']],
        ]);

        foreach (array_slice($erroresGlobales, 0, 30) as $err) {
            $this->warn($err);
        }

        if ($dryRun) {
            $this->comment('Dry-run: no se grabó nada. Para persistir: mismo comando con --ejecutar.');
        }

        return $totales['proveedores_error'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function listarCodigosProveedores(?string $desde, ?string $hasta, ?int $empresaCodigo): array
    {
        $reader = new ComprobanteProveedorAnitaImportBridgeReader;
        $desdeYmd = $desde ? (int) str_replace('-', '', $desde) : null;
        $hastaYmd = $hasta ? (int) str_replace('-', '', $hasta) : null;
        $this->line('Listando proveedores Anita con compra en el rango…');
        $codigos = $reader->listarProveedoresConCompra($desdeYmd, $hastaYmd, $empresaCodigo);
        sort($codigos, SORT_STRING);

        return $codigos;
    }
}
