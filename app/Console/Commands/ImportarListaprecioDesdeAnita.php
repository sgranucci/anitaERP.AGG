<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Services\Stock\ListaprecioAnitaSyncService;
use App\Services\Stock\PrecioAnitaSyncService;
use App\Support\Stock\PrecioAnitaFechaSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class ImportarListaprecioDesdeAnita extends Command
{
    protected $signature = 'listaprecio:importar-anita
                            {codigo : Código prem_lista / stkp_lista Anita (ej. 50)}
                            {--desde= : Fecha mínima stkp_fe_ult_act Ymd (default config)}
                            {--usuario= : ID usuario (default: primer usuario)}
                            {--sin-precios : Solo importa cabecera listaprecio, no stkpre}';

    protected $description = 'Importa listaprecio desde premae (Anita) y upsert de precios stkpre de esa lista.';

    public function handle(
        ListaprecioAnitaSyncService $listaSync,
        PrecioAnitaSyncService $precioSync,
    ): int {
        $codigo = trim((string) $this->argument('codigo'));
        $usuarioId = (int) ($this->option('usuario') ?: (Usuario::query()->orderBy('id')->value('id') ?? 1));

        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            $this->error('Usuario inválido.');

            return self::FAILURE;
        }

        try {
            $lista = $listaSync->importarPorCodigo($codigo, $usuarioId);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Lista {$lista['codigo']} — {$lista['nombre']} ({$lista['accion']}, id ERP {$lista['listaprecio_id']}).");
        foreach ($lista['advertencias'] as $w) {
            $this->warn($w);
        }

        if ($this->option('sin-precios')) {
            return self::SUCCESS;
        }

        $desdeOpt = trim((string) ($this->option('desde') ?? ''));
        $fechaDesde = $desdeOpt !== ''
            ? (int) preg_replace('/\D/', '', $desdeOpt)
            : PrecioAnitaFechaSupport::fechaDesdeConfig();

        $this->info("Importando precios stkpre lista {$codigo} (stkp_fe_ult_act >= {$fechaDesde})…");

        try {
            $ret = $precioSync->sincronizarDesdeAnita($fechaDesde, true, $usuarioId, $codigo);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Métrica', 'Valor'], [
            ['Filas Anita', $ret['filas_anita']],
            ['Filas únicas SKU', $ret['filas_unicas_sku_lista']],
            ['Insertados', $ret['insertados']],
            ['Actualizados', $ret['actualizados']],
            ['Omitidos sin artículo', $ret['omitidos_sin_articulo']],
            ['Obsoletos eliminados', $ret['obsoletos_eliminados']],
        ]);

        foreach ($ret['errores'] as $w) {
            $this->warn($w);
        }

        return self::SUCCESS;
    }
}
