<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Support\Stock\ArticuloSkuMapeoSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Recodifica SKU de artículos inactivos no facturables con "M", los activa y asigna uso MANTENIMIENTO.
 * Persiste el mapeo en articulo_sku_mapeo para imports/migraciones futuras.
 */
class RecodificarSkuNofacturableConMCommand extends Command
{
    protected $signature = 'stock:recodificar-sku-nofacturable-con-m
                            {--dry-run : Solo analiza (default si no hay --ejecutar)}
                            {--ejecutar : Persiste SKU nuevos, activa, uso MANTENIMIENTO + mapeo}
                            {--usuario= : ID usuario auditoría (obligatorio con --ejecutar; default: primer usuario)}
                            {--base= : Primer número a usar (default '.ArticuloSkuMapeoSupport::BASE_SKU_NUMERICA.')}
                            {--csv= : Ruta CSV preview (default storage/app/sku_mapeo_nofacturable_m_preview.csv)}
                            {--mostrar=20 : Filas de muestra en consola}';

    protected $description = 'Inactivos no facturables con M: recodifica SKU, activa y asigna uso MANTENIMIENTO';

    public function handle(): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        if ($ejecutar && $this->option('dry-run')) {
            $this->error('No combine --ejecutar con --dry-run.');

            return self::FAILURE;
        }

        if (! $ejecutar) {
            $this->warn('Modo dry-run (pase --ejecutar para persistir).');
        }

        if ($ejecutar) {
            $usuarioId = (int) ($this->option('usuario') ?: Usuario::query()->orderBy('id')->value('id') ?? 0);
            if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
                $this->error('No se pudo autenticar usuario_id='.$usuarioId.' (requerido para articulo_estado).');

                return self::FAILURE;
            }
            $this->line('Auditoría usuario_id: '.$usuarioId);
        }

        $baseOpt = $this->option('base');
        $base = $baseOpt !== null && $baseOpt !== ''
            ? (int) $baseOpt
            : ArticuloSkuMapeoSupport::BASE_SKU_NUMERICA;

        if ($base < 1) {
            $this->error('La base debe ser un entero positivo.');

            return self::FAILURE;
        }

        try {
            $resultado = ArticuloSkuMapeoSupport::recodificarNofacturablesConM($ejecutar, $base);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info($resultado['mensaje']);
        if (! empty($resultado['usoarticulo_id'])) {
            $this->line('usoarticulo_id MANTENIMIENTO: '.$resultado['usoarticulo_id']);
        }

        $csv = (string) ($this->option('csv') ?: storage_path('app/sku_mapeo_nofacturable_m_preview.csv'));
        ArticuloSkuMapeoSupport::exportarCsvPreview($resultado['filas'], $csv);
        $this->line('CSV: '.$csv);

        $mostrar = max(0, (int) $this->option('mostrar'));
        if ($mostrar > 0 && $resultado['filas'] !== []) {
            $muestra = array_slice($resultado['filas'], 0, $mostrar);
            $this->table(
                ['ID', 'SKU anterior', 'SKU nuevo', 'Uso ant.', 'Descripción'],
                array_map(
                    static fn (array $f) => [
                        $f['articulo_id'],
                        $f['sku_anterior'],
                        $f['sku_nuevo'],
                        $f['uso_anterior_id'] ?? '',
                        mb_substr($f['descripcion'], 0, 45),
                    ],
                    $muestra
                )
            );
            if (count($resultado['filas']) > $mostrar) {
                $this->line('… y '.(count($resultado['filas']) - $mostrar).' más (ver CSV).');
            }
        }

        return self::SUCCESS;
    }
}
