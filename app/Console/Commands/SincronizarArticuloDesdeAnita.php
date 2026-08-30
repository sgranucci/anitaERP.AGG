<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Services\Stock\ArticuloAnitaSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class SincronizarArticuloDesdeAnita extends Command
{
    protected $signature = 'articulo:sincronizar-anita
                            {--sku= : Importar/actualizar un solo artículo por SKU ERP (ej. V0432 / Y108)}
                            {--resync : Re-sincroniza todos los artículos (altas nuevas + actualización de existentes, conserva id ERP)}
                            {--solo-vencimiento : Solo actualiza articulo.vencimientoendia desde stkmae.stkm_vto_en_dias}
                            {--solo-unidadmedida : Solo actualiza unidadmedida_id / alternativa desde stkmae}
                            {--dry-run : Con --solo-unidadmedida: informe sin escribir}
                            {--ejecutar : Con --solo-unidadmedida: persiste}
                            {--usuario= : ID usuario para altas (cuentas/estado; default: primer usuario)}';

    protected $description = 'Importa artículos desde Anita (stkmae) vía ApiAnita. Sin --sku: solo altas nuevas. Con --resync: altas + actualización de todos. Con --solo-vencimiento: solo vencimientoendia. Con --solo-unidadmedida: solo UM (default dry-run). Con --sku: importa o actualiza ese artículo.';

    public function handle(ArticuloAnitaSyncService $sync): int
    {
        $usuarioId = $this->option('usuario');
        $usuarioId = ($usuarioId !== null && $usuarioId !== '')
            ? (int) $usuarioId
            : (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);

        if ($usuarioId <= 0) {
            $this->error('usuario inválido.');

            return self::FAILURE;
        }

        if (! Auth::loginUsingId($usuarioId)) {
            $this->error("No existe usuario id {$usuarioId}.");

            return self::FAILURE;
        }

        $sku = $this->option('sku');
        $sku = is_string($sku) ? trim($sku) : '';

        try {
            if ($this->option('solo-unidadmedida')) {
                if ($this->option('dry-run') && $this->option('ejecutar')) {
                    $this->error('No combine --ejecutar con --dry-run.');

                    return self::FAILURE;
                }
                $dryRun = ! $this->option('ejecutar');
                $this->info($dryRun
                    ? 'Dry-run: unidad de medida de artículos desde Anita (no escribe).'
                    : 'Persistiendo unidad de medida de artículos desde Anita…');
                $ret = $sync->sincronizarUnidadMedidaDesdeAnita($dryRun, $sku !== '' ? $sku : null);
                $this->info(
                    'Anita: '.$ret['en_anita']
                    .' · a actualizar: '.$ret['actualizados']
                    .' · iguales: '.$ret['sin_cambio']
                    .' · no en ERP: '.$ret['no_encontrados_erp']
                    .' · Anita sin UM: '.$ret['sin_um_anita']
                    .' · UM Anita sin catálogo: '.$ret['um_anita_sin_catalogo']
                    .' · catálogo UM: '.$ret['catalogo_antes'].' → '.$ret['catalogo_despues']
                    .($ret['dry_run'] ? ' · DRY-RUN' : ' · GRABADO')
                );
                $porDestino = [];
                foreach ($ret['cambios'] as $c) {
                    $porDestino[$c['a']] = ($porDestino[$c['a']] ?? 0) + 1;
                }
                if ($porDestino !== []) {
                    ksort($porDestino);
                    $this->line('Destino de los cambios: '.implode(', ', array_map(
                        static fn ($k, $n) => "{$k}={$n}",
                        array_keys($porDestino),
                        $porDestino
                    )));
                }
                $muestra = array_slice($ret['cambios'], 0, 40);
                if ($muestra !== []) {
                    $this->table(
                        ['SKU', 'UM actual', 'UM Anita', 'Alt actual', 'Alt Anita'],
                        array_map(static fn (array $c) => [$c['sku'], $c['de'], $c['a'], $c['de_alt'], $c['a_alt']], $muestra)
                    );
                    if (count($ret['cambios']) > 40) {
                        $this->line('… y '.(count($ret['cambios']) - 40).' más.');
                    }
                }
                foreach ($ret['advertencias'] as $w) {
                    $this->warn($w);
                }

                return self::SUCCESS;
            }

            if ($this->option('solo-vencimiento')) {
                $this->info($sku !== ''
                    ? "Sincronizando vencimientoendia de {$sku} desde Anita…"
                    : 'Sincronizando vencimientoendia de todos los artículos desde Anita…');
                $ret = $sync->sincronizarVencimientoEnDiasDesdeAnita($sku !== '' ? $sku : null);
                $this->info(
                    "Anita: {$ret['en_anita']}; actualizados: {$ret['actualizados']}; "
                    ."sin cambio: {$ret['sin_cambio']}; no en ERP: {$ret['no_encontrados_erp']}."
                );
                foreach ($ret['advertencias'] as $w) {
                    $this->warn($w);
                }

                return self::SUCCESS;
            }

            if ($sku !== '') {
                $this->info("Sincronizando artículo {$sku} desde Anita…");
                $ret = $sync->sincronizarSkuDesdeAnita($sku);
                $this->info("SKU {$ret['sku']} (Anita {$ret['codigo_anita']}): {$ret['accion']}.");
                foreach ($ret['advertencias'] as $w) {
                    $this->warn($w);
                }

                return self::SUCCESS;
            }

            if ($this->option('resync')) {
                $this->info('Re-sincronizando todos los artículos desde Anita por lotes (altas + actualizaciones; puede tardar varios minutos)…');
                $ret = $sync->resincronizarDesdeAnita();
            } else {
                $this->info('Sincronizando artículos desde Anita por lotes (solo altas nuevas; puede tardar varios minutos)…');
                $ret = $sync->sincronizarDesdeAnita();
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('resync')) {
            $this->info("Códigos en Anita: {$ret['en_anita']}; altas: {$ret['importados']}; actualizados: {$ret['actualizados']}; errores: {$ret['errores']}.");
        } else {
            $this->info("Códigos listados en Anita: {$ret['en_anita']}; altas ejecutadas: {$ret['importados']}; ya existían en ERP: {$ret['omitidos_ya_en_erp']}.");
        }
        if (isset($ret['lotes_bridge'], $ret['tamano_lote'])) {
            $this->info("Llamadas detalle al bridge: {$ret['lotes_bridge']} lotes de {$ret['tamano_lote']} artículos.");
        }
        foreach ($ret['advertencias'] as $w) {
            $this->warn($w);
        }

        return self::SUCCESS;
    }
}
