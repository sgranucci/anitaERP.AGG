<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Services\Stock\PrecioAnitaSyncService;
use App\Support\Stock\PrecioAnitaFechaSupport;
use App\Support\Stock\PrecioConservarVigenteSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class SincronizarPrecioDesdeAnita extends Command
{
    protected $signature = 'precio:sincronizar-anita
                            {--desde= : Fecha Anita stkp_fe_ult_act mínima Ymd (default config stock.precio_anita_sync_desde = 20250101)}
                            {--usuario= : ID usuario para usuarioultcambio_id (default: primer usuario)}
                            {--sin-limpiar-vigente : No elimina filas antiguas del mismo artículo+lista (conserva historial)}
                            {--solo-limpiar-vigente : Solo deduplica ERP dejando la fechavigencia más reciente por artículo+lista}';

    protected $description = 'Upsert seguro de precios desde Anita (stkpre) con stkp_fe_ult_act >= enero/2025; deja una fila vigente por artículo+lista.';

    public function handle(
        PrecioAnitaSyncService $sync,
        PrecioConservarVigenteSupport $conservarVigente,
    ): int {
        if ($this->option('solo-limpiar-vigente')) {
            $this->info('Conservando solo precio vigente por artículo + lista…');
            $ret = $conservarVigente->conservarSoloVigente(null);
            $this->table(['Métrica', 'Valor'], [
                ['Pares con duplicado', $ret['pares_con_duplicado']],
                ['Filas eliminadas', $ret['eliminados']],
            ]);

            return self::SUCCESS;
        }

        $usuarioId = (int) ($this->option('usuario') ?: (Usuario::query()->orderBy('id')->value('id') ?? 1));
        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            $this->error('Usuario inválido.');

            return self::FAILURE;
        }

        $desdeOpt = trim((string) ($this->option('desde') ?? ''));
        $fechaDesde = $desdeOpt !== ''
            ? (int) preg_replace('/\D/', '', $desdeOpt)
            : PrecioAnitaFechaSupport::fechaDesdeConfig();

        if ($fechaDesde < 19000000) {
            $this->error('Fecha --desde inválida (use Ymd, ej. 20250101).');

            return self::FAILURE;
        }

        $this->info("Sincronizando stkpre desde Anita (stkp_fe_ult_act >= {$fechaDesde})…");

        try {
            $ret = $sync->sincronizarDesdeAnita(
                $fechaDesde,
                ! $this->option('sin-limpiar-vigente'),
                $usuarioId,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Métrica', 'Valor'], [
            ['Filas Anita (filtro fecha)', $ret['filas_anita']],
            ['Filas únicas SKU+lista', $ret['filas_unicas_sku_lista']],
            ['Insertados', $ret['insertados']],
            ['Actualizados', $ret['actualizados']],
            ['Omitidos sin artículo ERP', $ret['omitidos_sin_articulo']],
            ['Omitidos sin lista ERP', $ret['omitidos_sin_lista']],
            ['Omitidos precio inválido', $ret['omitidos_precio_invalido']],
            ['Obsoletos eliminados (misma lista)', $ret['obsoletos_eliminados']],
            ['Pares con duplicado limpiados', $ret['pares_con_duplicado']],
        ]);

        foreach ($ret['errores'] as $w) {
            $this->warn($w);
        }

        return self::SUCCESS;
    }
}
