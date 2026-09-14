<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Ventas\ClienteCuentacorrienteImportarDesdeAnitaService;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportFormatoSupport;
use Illuminate\Console\Command;

class ClienteCuentacorrienteImportarDesdeAnitaCommand extends Command
{
    protected $signature = 'cliente-cuentacorriente:importar-desde-anita
                            {--cliente= : Código de cliente Anita/ERP (opcional)}
                            {--empresa= : Código empresa Anita (solo si climov tiene cliv_empresa)}
                            {--desde= : Fecha ISO desde, inclusive (cliv_fecha)}
                            {--hasta= : Fecha ISO hasta, inclusive}
                            {--limite= : Máximo de comprobantes CC a procesar}
                            {--usuario-id=1 : usuario_id de auditoría en ventas importadas}
                            {--incluir-saldados : Incluye climov no cancelados aunque monto = t_cobrado}
                            {--forzar-aplicaciones : Reaplica aplicaciones sintéticas Anita sync si el aplicado no cierra}
                            {--sin-importar-ventas : No crea cabeceras venta ERP faltantes (solo CC de las que ya existen)}
                            {--dry-run : Solo analiza (default si no hay --ejecutar)}
                            {--ejecutar : Persiste en ERP (no escribe Anita)}';

    protected $description = 'Alinea deuda clientes Anita (venta+climov+aplmov) → ERP: importa ventas faltantes y CC/aplicaciones';

    public function handle(ClienteCuentacorrienteImportarDesdeAnitaService $service): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        if ($ejecutar && (bool) $this->option('dry-run')) {
            $this->error('No combine --ejecutar con --dry-run.');

            return self::FAILURE;
        }
        $dryRun = ! $ejecutar;

        $perfil = ClienteCuentacorrienteAnitaImportFormatoSupport::perfil();
        $cliente = trim((string) $this->option('cliente'));
        $empresaOpt = $this->option('empresa');
        $empresaCodigo = ($empresaOpt !== null && $empresaOpt !== '') ? (int) $empresaOpt : null;
        $limiteOpt = $this->option('limite');
        $limite = ($limiteOpt !== null && $limiteOpt !== '') ? (int) $limiteOpt : null;
        $desde = $this->option('desde') ? (string) $this->option('desde') : null;
        $hasta = $this->option('hasta') ? (string) $this->option('hasta') : null;
        $soloConSaldo = ! (bool) $this->option('incluir-saldados');
        $forzar = (bool) $this->option('forzar-aplicaciones');
        $importarVentas = ! (bool) $this->option('sin-importar-ventas');
        $usuarioId = max(1, (int) $this->option('usuario-id'));

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Entorno %s | climov_empresa=%s | %s → %s | cliente %s | %s%s',
            $perfil['entorno'],
            $perfil['climov_tiene_empresa'] ? 'sí' : 'no',
            $desde ?: 'sin desde',
            $hasta ?: 'sin hasta',
            $cliente !== '' ? $cliente : 'todos',
            $dryRun ? 'DRY-RUN' : 'EJECUTAR',
            $importarVentas ? ' | importa ventas faltantes' : ' | sin importar ventas',
        ));
        $this->line('Filtro: Anita venta (no PRE/COB). Luego climov+aplmov → cliente_cuentacorriente.');

        try {
            $stats = $service->importar(
                $dryRun,
                $cliente !== '' ? $cliente : null,
                $desde,
                $hasta,
                $empresaCodigo,
                $soloConSaldo,
                $forzar,
                $limite,
                $usuarioId,
                $importarVentas,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Métrica', 'Cantidad'], [
            ['climov Anita', $stats['anita_climov']],
            ['Anita venta (deuda)', $stats['anita_venta']],
            ['Sin Anita venta', $stats['omitidas_sin_anita_venta']],
            ['Tipo no deuda / PRE', $stats['omitidas_tipo_no_deuda']],
            ['Ventas faltantes ERP', $stats['ventas_faltantes_erp']],
            ['Ventas a crear / creadas', $stats['ventas_a_crear'].' / '.$stats['ventas_creadas']],
            ['Ventas sin cliente', $stats['ventas_sin_cliente']],
            ['Sin venta ERP (post)', $stats['omitidas_sin_venta_erp']],
            ['Ya al día', $stats['omitidas_al_dia']],
            ['CC a procesar', $stats['a_procesar']],
            ['CC a crear', $stats['a_crear_cc']],
            ['CC creadas', $stats['cc_creadas']],
            ['aplmov Anita', $stats['anita_aplmov']],
            ['Aplicaciones creadas', $stats['aplicaciones_creadas']],
        ]);

        if (($stats['muestra_ventas'] ?? []) !== []) {
            $this->line('Muestra ventas a importar (hasta 25):');
            $this->table(
                ['Comprobante', 'Fecha', 'Total', 'Cliente', 'CAE'],
                array_map(static fn (array $r) => [
                    $r['etiqueta'],
                    $r['fecha'],
                    number_format((float) $r['total'], 2, ',', '.'),
                    $r['cliente_id'],
                    $r['cae'],
                ], $stats['muestra_ventas'])
            );
        }

        if ($stats['muestra'] !== []) {
            $this->line('Muestra CC (hasta 25):');
            $this->table(
                ['Comprobante', 'Venta', 'Fecha', 'Total', 'Apl.Anita', 'Apl.ERP', 'CC', 'Apl'],
                array_map(static fn (array $r) => [
                    $r['etiqueta'],
                    $r['venta_id'],
                    $r['fecha'],
                    number_format((float) $r['total'], 2, ',', '.'),
                    number_format((float) $r['aplicado_anita'], 2, ',', '.'),
                    number_format((float) $r['aplicado_erp'], 2, ',', '.'),
                    $r['accion_cc'],
                    $r['accion_apl'],
                ], $stats['muestra'])
            );
        }

        foreach (array_slice(array_merge($stats['ventas_errores'] ?? [], $stats['errores']), 0, 40) as $error) {
            $this->warn((string) $error);
        }

        if ($dryRun) {
            $this->comment('Dry-run: no se grabó nada. Para persistir: mismo comando con --ejecutar.');
        }

        return self::SUCCESS;
    }
}
