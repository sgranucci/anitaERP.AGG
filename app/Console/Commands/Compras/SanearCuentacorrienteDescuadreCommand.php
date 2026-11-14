<?php

namespace App\Console\Commands\Compras;

use App\Support\Compras\ProveedorCuentacorrienteDescuadreSanearSupport;
use Illuminate\Console\Command;

/**
 * Sanea causas de descuadre deuda vs ficha (apps duplicadas, facturas total 0, vínculo OP).
 * Default dry-run. No graba «Ajuste saldo CC».
 */
class SanearCuentacorrienteDescuadreCommand extends Command
{
    protected $signature = 'compras:sanear-cuentacorriente-descuadre
                            {--empresa= : Solo esta empresa_id}
                            {--ejecutar : Persiste. Sin este flag solo informa}';

    protected $description = 'Sanea huérfanos/apps duplicadas/facturas CC en 0 (deuda vs ficha). Dry-run por defecto.';

    public function handle(): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $empresaOpt = $this->option('empresa');
        $empresaId = ($empresaOpt !== null && $empresaOpt !== '') ? (int) $empresaOpt : null;

        $this->info(($ejecutar ? 'EJECUTAR' : 'DRY-RUN').' saneamiento deuda vs ficha'
            .($empresaId ? ' · empresa '.$empresaId : ' · todas las empresas'));
        $this->comment('1) Borra apps sintéticas que duplican un par ERP');
        $this->comment('2) Restaura total de facturas CC en 0 desde comprobante_proveedor');
        $this->comment('3) Vincula créditos huérfanos a OP cuando hay etiqueta OPP/OPA');

        $plan = ProveedorCuentacorrienteDescuadreSanearSupport::planear($empresaId);

        $appsN = count($plan['apps_duplicadas']);
        $appsIds = 0;
        foreach ($plan['apps_duplicadas'] as $par) {
            $appsIds += count($par['app_ids']);
        }
        $cerosN = count($plan['facturas_cero']);
        $h = $plan['huerfanos'];
        $v = $plan['vinculo'];

        $this->table(['Métrica', 'Cantidad'], [
            ['Pares apps sintéticas duplicadas', $appsN],
            ['Apps sintéticas a borrar', $appsIds],
            ['Facturas CC total=0 a restaurar', $cerosN],
            ['Huérfanos (crédito sin OP/factura)', $h['total']],
            ['Suma huérfanos', number_format((float) $h['suma'], 2, ',', '.')],
            ['Huérfanos vinculables a OP', $v['a_vincular'] ?? $h['vinculables']],
            ['Huérfanos sin OP importada', $v['sin_op'] ?? $h['sin_op']],
            ['Etiqueta OP ambigua', $v['ambiguo_etiqueta'] ?? 0],
            ['OP ya tiene otra CC', $v['omitidos_op_ya_tiene_cc'] ?? 0],
        ]);

        if ($plan['muestra_apps'] !== []) {
            $this->line('Muestra apps duplicadas:');
            $this->table(['CC A', 'CC B', 'Monto', 'Apps'], array_map(
                static fn (array $f) => [$f['cc_a'], $f['cc_b'], $f['monto'], $f['apps']],
                $plan['muestra_apps']
            ));
        }
        if ($plan['muestra_ceros'] !== []) {
            $this->line('Muestra facturas total=0:');
            $this->table(['CC', 'CP', 'Total nuevo'], array_map(
                static fn (array $f) => [$f['cc_id'], $f['cp_id'], $f['total_nuevo']],
                $plan['muestra_ceros']
            ));
        }
        if ($plan['muestra_huerfanos'] !== []) {
            $this->line('Muestra huérfanos (por |importe|):');
            $this->table(['CC', 'Código', 'Proveedor', 'Fecha', 'Total'], array_map(
                static fn (array $f) => [$f['cc_id'], $f['codigo'], $f['nombre'], $f['fecha'], $f['total']],
                $plan['muestra_huerfanos']
            ));
        }

        if (! $ejecutar) {
            $this->comment('No se grabó nada. Para persistir: php artisan compras:sanear-cuentacorriente-descuadre'
                .($empresaId ? ' --empresa='.$empresaId : '')
                .' --ejecutar');

            return self::SUCCESS;
        }

        $res = ProveedorCuentacorrienteDescuadreSanearSupport::ejecutar($plan);
        $this->info(sprintf(
            'Listo: apps borradas=%d · facturas restauradas=%d · CC vinculadas=%d · apps vinculadas=%d',
            $res['apps_borradas'],
            $res['facturas_restauradas'],
            $res['vinculo']['vinculados_cc'] ?? 0,
            $res['vinculo']['vinculados_app'] ?? 0
        ));
        $this->comment('Los huérfanos sin OP siguen: `pagoproveedor:importar-desde-anita --solo-huerfanos-cc --empresa=… --ejecutar` y re-correr este saneamiento.');

        return self::SUCCESS;
    }
}
