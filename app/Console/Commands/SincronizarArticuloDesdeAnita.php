<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Services\Stock\ArticuloAnitaSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class SincronizarArticuloDesdeAnita extends Command
{
    protected $signature = 'articulo:sincronizar-anita
                            {--sku= : Importar/actualizar un solo artículo por SKU ERP (ej. V0432)}
                            {--resync : Re-sincroniza todos los artículos (altas nuevas + actualización de existentes, conserva id ERP)}
                            {--usuario= : ID usuario para altas (cuentas/estado; default: primer usuario)}';

    protected $description = 'Importa artículos desde Anita (stkmae) vía ApiAnita. Sin --sku: solo altas nuevas. Con --resync: altas + actualización de todos. Con --sku: importa o actualiza ese artículo.';

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
