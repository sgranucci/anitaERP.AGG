<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Models\Ventas\LocalVenta;
use App\Services\Ventas\FacturacionLocal\PrecioLocalAnitaSyncService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\FacturacionLocal\PrecioListaLocalMapeoSupport;
use Illuminate\Console\Command;

class SincronizarPrecioLocalDesdeAnita extends Command
{
    protected $signature = 'facturacion-local:sync-precios
                            {--local= : ID de local_venta (opcional; usa su bridge Anita)}
                            {--desde= : Fecha Anita stkp_fe_ult_act mínima Ymd (default config)}
                            {--usuario= : ID usuario para usuarioultcambio_id}
                            {--sin-limpiar-vigente : No elimina filas antiguas del mismo artículo+lista ERP}
                            {--ejecutar : Persiste listas faltantes + upsert precios (sin esto = dry-run)}';

    protected $description = 'Importa precios stkpre del Anita Local al ERP remapeando listas (5→11, 6→12, 50→13). Dry-run por defecto.';

    public function handle(PrecioLocalAnitaSyncService $sync): int
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            $this->warn('Solo aplica en Calzados Ferli.');

            return self::SUCCESS;
        }

        $ejecutar = (bool) $this->option('ejecutar');
        if (! $ejecutar) {
            $this->info('Modo dry-run (no escribe). Use --ejecutar para persistir.');
        }

        $localId = (int) $this->option('local');
        $local = $localId > 0 ? LocalVenta::query()->find($localId) : null;
        if ($localId > 0 && ! $local) {
            $this->error("Local id {$localId} inexistente.");

            return self::FAILURE;
        }

        $usuarioId = (int) ($this->option('usuario') ?: (Usuario::query()->orderBy('id')->value('id') ?? 1));
        $desdeOpt = trim((string) ($this->option('desde') ?? ''));
        $fechaDesde = $desdeOpt !== ''
            ? (int) preg_replace('/\D/', '', $desdeOpt)
            : null;

        if ($fechaDesde !== null && $fechaDesde < 19000000) {
            $this->error('Fecha --desde inválida (use Ymd, ej. 20250101).');

            return self::FAILURE;
        }

        $mapeo = PrecioListaLocalMapeoSupport::mapa();
        $this->line('Mapeo listas Anita Local → ERP: '.collect($mapeo)->map(fn ($d, $o) => "{$o}→{$d}")->implode(', '));

        $ret = $sync->sincronizar(
            $local,
            $fechaDesde,
            $ejecutar,
            ! $this->option('sin-limpiar-vigente'),
            $usuarioId,
        );

        if (! empty($ret['error'])) {
            $this->error($ret['error']);

            return self::FAILURE;
        }

        $this->table(['Métrica', 'Valor'], [
            ['Bridge', $ret['servidor'].' / '.$ret['ifx_server']],
            ['Fecha desde Anita', $ret['fecha_desde_anita']],
            ['Filas Anita (filtro)', $ret['filas_anita']],
            ['Filas únicas SKU+lista Anita', $ret['filas_unicas_sku_lista']],
            ['A insertar', $ret['a_insertar']],
            ['A actualizar', $ret['a_actualizar']],
            ['Insertados', $ret['insertados']],
            ['Actualizados', $ret['actualizados']],
            ['Omitidos sin artículo ERP', $ret['omitidos_sin_articulo']],
            ['Omitidos sin lista ERP', $ret['omitidos_sin_lista']],
            ['Omitidos precio inválido', $ret['omitidos_precio_invalido']],
            ['Omitidos lista sin mapeo', $ret['omitidos_lista_sin_mapeo']],
            ['Listas ERP creadas', implode(', ', $ret['listas_creadas']) ?: '—'],
            ['Listas ERP (estado)', implode(', ', $ret['listas_ya_existentes']) ?: '—'],
            ['Obsoletos eliminados', $ret['obsoletos_eliminados']],
        ]);

        foreach ($ret['errores'] as $w) {
            $this->warn($w);
        }

        if (! $ejecutar && ($ret['a_insertar'] > 0 || $ret['a_actualizar'] > 0 || $ret['listas_ya_existentes'] !== [])) {
            $this->warn('Revise el impacto y autorice --ejecutar para persistir.');
        }

        return self::SUCCESS;
    }
}
