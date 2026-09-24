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
                            {--cerrar-sin-deuda-anita : (default) Salda CC ERP pendiente que Anita ya canceló}
                            {--sin-cerrar-sin-deuda-anita : No salda extras; solo importa climov abierto}
                            {--reparar-contrapartidas : Reemplaza el cierre sin movimiento por el comprobante que aplica la factura}
                            {--reparar-cob-multifila : Amplía COB/COA cortos (varias filas climov) y completa FAC+aplicaciones}
                            {--todos : Repara todos los clientes. Obligatorio si no se pasa --cliente}
                            {--ajuste-anita : Cierra la ficha con un AJU por cada aplicación Anita sin comprobante}
                            {--dry-run : Solo analiza (default si no hay --ejecutar)}
                            {--ejecutar : Persiste en ERP (no escribe Anita)}';

    protected $description = 'Alinea deuda clientes Anita (venta+climov+aplmov) → ERP: importa ventas faltantes, CC/aplicaciones y cierra lo que Anita ya saldó';

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
        // Default: cerrar extras. Solo se omite con --sin-cerrar-sin-deuda-anita.
        $cerrarSinDeudaAnita = ! (bool) $this->option('sin-cerrar-sin-deuda-anita');
        $repararContrapartidas = (bool) $this->option('reparar-contrapartidas');
        $repararCobMultifila = (bool) $this->option('reparar-cob-multifila');
        $usuarioId = max(1, (int) $this->option('usuario-id'));

        if ($repararCobMultifila) {
            return $this->repararCobMultifila(
                $service,
                $dryRun,
                $cliente,
                $usuarioId,
                (bool) $this->option('todos'),
                $limite,
            );
        }

        if ($repararContrapartidas) {
            return $this->repararContrapartidas(
                $service,
                $dryRun,
                $cliente,
                $usuarioId,
                (bool) $this->option('todos'),
                $limite,
            );
        }

        if ((bool) $this->option('ajuste-anita')) {
            $this->line(($dryRun ? 'DRY-RUN' : 'EJECUTAR').' | ajuste Anita para igualar ficha y deuda');
            set_time_limit(0);
            try {
                $stats = $service->cerrarFichaConAjusteAnita($dryRun, $usuarioId);
            } catch (\Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
            $this->table(['Métrica', 'Cantidad'], [
                ['Ajustes', $stats['documentos']],
                ['Importe', number_format((float) $stats['importe'], 2, ',', '.')],
            ]);
            foreach (array_slice($stats['errores'], 0, 20) as $error) {
                $this->warn((string) $error);
            }
            if ($dryRun) {
                $this->comment('Dry-run: no se grabó nada.');
            }

            return self::SUCCESS;
        }

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Entorno %s | climov_empresa=%s | %s → %s | cliente %s | %s%s%s',
            $perfil['entorno'],
            $perfil['climov_tiene_empresa'] ? 'sí' : 'no',
            $desde ?: 'sin desde',
            $hasta ?: 'sin hasta',
            $cliente !== '' ? $cliente : 'todos',
            $dryRun ? 'DRY-RUN' : 'EJECUTAR',
            $importarVentas ? ' | importa ventas faltantes' : ' | sin importar ventas',
            $cerrarSinDeudaAnita ? ' | cierra extras sin deuda Anita' : '',
        ));
        $this->line('Filtro: Anita venta + créditos climov sin venta (COA). Luego climov+aplmov → cliente_cuentacorriente.');
        $this->line($cerrarSinDeudaAnita
            ? 'Cierre de extras: ON (CC ERP que Anita ya canceló se saldan).'
            : 'Cierre de extras: OFF (--sin-cerrar-sin-deuda-anita).');
        if ($cerrarSinDeudaAnita && $cliente === '') {
            $this->warn('Cierre de extras para TODOS los clientes con deuda ERP abierta.');
        }

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
                $cerrarSinDeudaAnita,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Métrica', 'Cantidad'], [
            ['climov Anita', $stats['anita_climov']],
            ['Anita venta (deuda)', $stats['anita_venta']],
            ['Crédito sin venta Anita (COA…)', $stats['credito_sin_venta_anita'] ?? 0],
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
            ['Anita climov abiertos', $stats['anita_climov_abiertos']],
            ['Clientes con extras', $stats['extras_clientes']],
            ['Extras a cerrar (sin deuda Anita)', $stats['extras_a_cerrar']],
            ['Importe extras', number_format((float) $stats['extras_importe'], 2, ',', '.')],
            ['Extras cerrados', $stats['extras_cerrados']],
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

        if (($stats['muestra_extras'] ?? []) !== []) {
            $this->line('Muestra extras a saldar (hasta 25):');
            $this->table(
                ['Comprobante', 'CC', 'Fecha', 'Total', 'A saldar', 'Clave'],
                array_map(static fn (array $r) => [
                    $r['etiqueta'],
                    $r['cc_id'],
                    $r['fecha'],
                    number_format((float) $r['total'], 2, ',', '.'),
                    number_format((float) $r['faltante'], 2, ',', '.'),
                    $r['clave'],
                ], $stats['muestra_extras'])
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

    private function repararContrapartidas(
        ClienteCuentacorrienteImportarDesdeAnitaService $service,
        bool $dryRun,
        string $cliente,
        int $usuarioId,
        bool $todos,
        ?int $limite,
    ): int {
        if ($cliente === '' && ! $todos) {
            $this->error('Indicá --cliente o --todos.');

            return self::FAILURE;
        }

        $alcance = $cliente !== '' ? 'cliente '.$cliente : 'todos los clientes';
        $this->line(($dryRun ? 'DRY-RUN' : 'EJECUTAR').' | contrapartidas de '.$alcance);
        set_time_limit(0);

        try {
            $stats = $service->repararContrapartidasSinMovimiento(
                $dryRun,
                $cliente !== '' ? $cliente : null,
                $usuarioId,
                $limite,
                function (int $hechos, int $total) {
                    $this->line('Procesados '.$hechos.' / '.$total);
                },
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Métrica', 'Cantidad'], [
            ['Facturas a reparar', $stats['fantasmas']],
            ['Reparadas', $stats['reparados']],
            ['Parciales', $stats['parciales'] ?? 0],
            ['Sin contrapartida en Anita', $stats['omitidos']],
            ['Importe aplicado', number_format((float) $stats['importe'], 2, ',', '.')],
            ['Quedó descubierto', number_format((float) ($stats['descubierto'] ?? 0), 2, ',', '.')],
        ]);
        if (($stats['muestra'] ?? []) !== []) {
            $this->table(
                ['Factura', 'CC', 'Importe', 'Contrapartida'],
                array_map(static fn (array $r) => [
                    $r['factura'] ?? '',
                    $r['cc_id'] ?? '',
                    number_format((float) ($r['importe'] ?? 0), 2, ',', '.'),
                    $r['contrapartida'] ?? '',
                ], $stats['muestra'])
            );
        }
        foreach (array_slice($stats['errores'] ?? [], 0, 40) as $error) {
            $this->warn((string) $error);
        }
        if (count($stats['errores'] ?? []) > 40) {
            $this->warn('… y '.(count($stats['errores']) - 40).' avisos más.');
        }
        if ($dryRun) {
            $this->comment('Dry-run: no se grabó nada.');
        }

        return self::SUCCESS;
    }

    private function repararCobMultifila(
        ClienteCuentacorrienteImportarDesdeAnitaService $service,
        bool $dryRun,
        string $cliente,
        int $usuarioId,
        bool $todos,
        ?int $limite,
    ): int {
        if ($cliente === '' && ! $todos) {
            $this->error('Indicá --cliente o --todos.');

            return self::FAILURE;
        }

        $alcance = $cliente !== '' ? 'cliente '.$cliente : 'todos los clientes';
        $this->line(($dryRun ? 'DRY-RUN' : 'EJECUTAR').' | COB multifila de '.$alcance);
        set_time_limit(0);

        try {
            $stats = $service->repararCobMultifila(
                $dryRun,
                $cliente !== '' ? $cliente : null,
                $usuarioId,
                $limite,
                function (int $hechos, int $total) {
                    if ($hechos === $total || $hechos % 10 === 0) {
                        $this->line('Procesados '.$hechos.' / '.$total);
                    }
                },
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Métrica', 'Cantidad'], [
            ['COB/COA a reparar', $stats['candidatos']],
            ['Reparados', $stats['reparados']],
            ['COB ampliados', $stats['cob_ampliados']],
            ['CC deuda creadas', $stats['cc_deuda_creadas']],
            ['Aplicaciones', $stats['aplicaciones']],
            ['Haber ampliado', number_format((float) $stats['importe_ampliado'], 2, ',', '.')],
        ]);
        if (($stats['muestra'] ?? []) !== []) {
            $this->table(
                ['Cliente', 'COB', 'ERP', 'Anita', 'Diff', 'Apps', 'CC+'],
                array_map(static fn (array $r) => [
                    $r['cliente'] ?? '',
                    $r['cob'] ?? '',
                    number_format((float) ($r['erp'] ?? 0), 2, ',', '.'),
                    number_format((float) ($r['anita'] ?? 0), 2, ',', '.'),
                    number_format((float) ($r['diff'] ?? 0), 2, ',', '.'),
                    $r['apps'] ?? 0,
                    $r['cc_nuevas'] ?? 0,
                ], $stats['muestra'])
            );
        }
        foreach (array_slice($stats['errores'] ?? [], 0, 40) as $error) {
            $this->warn((string) $error);
        }
        if (count($stats['errores'] ?? []) > 40) {
            $this->warn('… y '.(count($stats['errores']) - 40).' avisos más.');
        }
        if ($dryRun) {
            $this->comment('Dry-run: no se grabó nada. Para persistir: mismo comando con --ejecutar.');
        }

        return self::SUCCESS;
    }
}
