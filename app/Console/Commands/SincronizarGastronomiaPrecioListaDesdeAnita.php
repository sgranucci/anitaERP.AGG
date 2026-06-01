<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Services\Ventas\Gastronomia\GastronomiaVentaPrecioListaAnitaSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class SincronizarGastronomiaPrecioListaDesdeAnita extends Command
{
    protected $signature = 'gastronomia:sincronizar-precios-lista-anita
                            {--listaprecio-id= : ID lista ERP (default: LISTAPRECIO_DEFAULT_ID / gastronomía)}
                            {--sku= : Solo un SKU de catálogo (ej. V00123)}
                            {--usuario= : ID usuario para usuarioultcambio_id (default: primer usuario)}
                            {--dry-run : Solo contar candidatos sin importar}';

    protected $description = 'Importa desde Anita (stkpre) el precio de lista para artículos de venta gastronomía (SKU catálogo V…) sin precio vigente en el ERP.';

    public function handle(GastronomiaVentaPrecioListaAnitaSyncService $sync): int
    {
        $usuarioOpt = $this->option('usuario');
        $usuarioId = ($usuarioOpt !== null && $usuarioOpt !== '')
            ? (int) $usuarioOpt
            : (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);

        if ($usuarioId > 0 && ! Auth::check()) {
            if (! Auth::loginUsingId($usuarioId)) {
                $this->error("No existe usuario id {$usuarioId}.");

                return self::FAILURE;
            }
        }

        $listaOpt = $this->option('listaprecio-id');
        $listaprecioId = ($listaOpt !== null && $listaOpt !== '') ? (int) $listaOpt : null;
        $sku = $this->option('sku');
        $dryRun = (bool) $this->option('dry-run');

        try {
            if ($dryRun) {
                $this->info('Modo dry-run: no se graban precios en el ERP.');
            }
            $this->info('Buscando artículos de catálogo gastronomía sin precio vigente y consultando Anita (stkpre)…');
            $ret = $sync->sincronizarDesdeAnita($listaprecioId, $sku, $dryRun, $usuarioId > 0 ? $usuarioId : null);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Lista ERP id {$ret['listaprecio_id']} (código Anita: {$ret['listaprecio_codigo']}).");
        $this->info("Candidatos sin precio vigente: {$ret['candidatos_sin_precio_vigente']}.");
        if (! $dryRun) {
            $this->info("Filas recibidas de Anita: {$ret['filas_anita']}; importadas: {$ret['importados']}.");
            $this->info(
                "Omitidos — sin fila Anita: {$ret['omitidos_sin_fila_anita']}; precio inválido: {$ret['omitidos_precio_invalido']}; duplicado: {$ret['omitidos_duplicado']}."
            );
        }
        foreach ($ret['errores'] as $w) {
            $this->warn($w);
        }

        return self::SUCCESS;
    }
}
