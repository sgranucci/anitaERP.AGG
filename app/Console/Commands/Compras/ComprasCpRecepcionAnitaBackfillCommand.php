<?php

namespace App\Console\Commands\Compras;

use App\Support\Compras\AnitaImport\ComprasCpRecepcionAnitaBackfillSupport;
use Illuminate\Console\Command;

/**
 * Llena comprobante_proveedor_recepcion desde Anita (aplicped → COM).
 * Default dry-run; --ejecutar persiste. Solo ANITA_IMPORT, insert-only.
 */
class ComprasCpRecepcionAnitaBackfillCommand extends Command
{
    protected $signature = 'compras:backfill-cp-recepcion-anita
                            {--desde=2025-01-01 : Fecha ISO desde (fechacomprobante)}
                            {--hasta=2025-12-31 : Fecha ISO hasta}
                            {--limite= : Máximo de CP candidatos (para prueba)}
                            {--ejecutar : Persiste; sin esto solo dry-run}
                            {--sin-actualizar-modo : No cambia modo_carga a ASIGNA_RECEPCION}';

    protected $description = 'Backfill pivot factura↔recepción (aplicped Anita) sin pisar nativos';

    public function handle(): int
    {
        $desde = (string) $this->option('desde');
        $hasta = (string) $this->option('hasta');
        $limiteOpt = $this->option('limite');
        $limite = ($limiteOpt !== null && $limiteOpt !== '') ? max(1, (int) $limiteOpt) : null;
        $ejecutar = (bool) $this->option('ejecutar');
        $actualizarModo = ! (bool) $this->option('sin-actualizar-modo');

        $this->info(($ejecutar ? 'EJECUTAR' : 'DRY-RUN')
            ." CP↔recepción {$desde}..{$hasta}"
            .($limite !== null ? " limite={$limite}" : ''));

        $this->line('Indexando recepciones ERP (anita_sucursal|anita_nro)…');
        $indice = ComprasCpRecepcionAnitaBackfillSupport::indexarRecepciones();
        $this->line('  claves COM: '.number_format(count($indice['por_com'])));

        $this->line('Consultando aplicped / planificando vínculos…');
        $plan = ComprasCpRecepcionAnitaBackfillSupport::planificar($desde, $hasta, $indice, $limite);
        $stats = $plan['stats'];

        $this->table(['Métrica', 'Cantidad'], [
            ['CP candidatos (sin pivot)', $stats['candidatas']],
            ['CP a vincular', $stats['vincular_cps']],
            ['Links pivot a crear', $stats['vincular_links']],
            ['Vía COM directa', $stats['via_com']],
            ['Vía PEP→COM hermana', $stats['via_pep']],
            ['Sin aplicped', $stats['sin_aplicped']],
            ['Aplicped sin COM', $stats['sin_com_en_aplicped']],
            ['COM faltante en ERP', $stats['com_faltante_erp']],
            ['Ambiguo (varias OC)', $stats['ambiguo_oc']],
            ['Ambiguo (varias recepción)', $stats['ambiguo_recepcion']],
            ['Sin proveedor/clave', $stats['sin_proveedor']],
            ['Errores bridge', count($stats['errores_bridge'])],
            ['modo_carga a ASIGNA_RECEPCION', count($plan['actualizar_modo'])],
        ]);

        if ($plan['muestra'] !== []) {
            $this->line('Muestra (hasta 15):');
            $this->table(
                ['CP', 'Factura', 'Vía', 'COMs', 'Rec IDs'],
                array_map(static fn (array $m) => [
                    $m['cp_id'],
                    $m['factura'],
                    $m['via'],
                    $m['coms'],
                    $m['rec_ids'],
                ], $plan['muestra'])
            );
        }

        if ($stats['errores_bridge'] !== []) {
            foreach (array_slice($stats['errores_bridge'], 0, 10) as $err) {
                $this->warn((string) $err);
            }
        }

        if (! $ejecutar) {
            $this->comment('Dry-run: no se grabó nada. Relanzá con --ejecutar para persistir.');

            return self::SUCCESS;
        }

        $res = ComprasCpRecepcionAnitaBackfillSupport::aplicar(
            $plan['vincular'],
            $plan['actualizar_modo'],
            $actualizarModo,
        );
        $this->info(sprintf(
            'Insertados %d links pivot; modo_carga actualizado en %d CP.',
            $res['links'],
            $res['modos'],
        ));

        return self::SUCCESS;
    }
}
