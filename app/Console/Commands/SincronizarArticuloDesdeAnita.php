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
                            {--usuario= : ID usuario para altas (cuentas/estado; default: primer usuario)}';

    protected $description = 'Importa artículos desde Anita (stkmae) vía ApiAnita. Sin --sku: solo altas nuevas. Con --sku: importa o actualiza ese artículo.';

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

            $this->info('Sincronizando artículos desde Anita (puede tardar varios minutos)…');
            $ret = $sync->sincronizarDesdeAnita();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Códigos listados en Anita: {$ret['en_anita']}; altas ejecutadas: {$ret['importados']}; ya existían en ERP: {$ret['omitidos_ya_en_erp']}.");
        foreach ($ret['advertencias'] as $w) {
            $this->warn($w);
        }

        return self::SUCCESS;
    }
}
