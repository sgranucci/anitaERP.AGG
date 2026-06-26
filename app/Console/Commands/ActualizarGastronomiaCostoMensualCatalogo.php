<?php

namespace App\Console\Commands;

use App\Services\Ventas\Gastronomia\GastronomiaCostoMensualCatalogoService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ActualizarGastronomiaCostoMensualCatalogo extends Command
{
    protected $signature = 'gastronomia:actualizar-costo-mensual-catalogo
                            {--fecha= : Fecha de referencia Y-m-d (default: hoy; define mes → lista 5000+mes)}
                            {--sku= : Solo un SKU de catálogo (ej. V0365)}
                            {--usuario= : ID usuario para usuarioultcambio_id}
                            {--sin-anita : No replica en Anita (stkpre); solo ERP precio}
                            {--dry-run : Solo contar candidatos sin calcular ni grabar}';

    protected $description = 'Calcula costo por fórmula (última compra Anita) de artículos catálogo V… y graba en lista 5000+mes (ej. junio → 5006).';

    public function handle(GastronomiaCostoMensualCatalogoService $service): int
    {
        $fechaOpt = trim((string) $this->option('fecha'));
        $fecha = $fechaOpt !== ''
            ? Carbon::parse($fechaOpt)->startOfDay()
            : Carbon::today();

        $usuarioOpt = $this->option('usuario');
        $usuarioId = ($usuarioOpt !== null && $usuarioOpt !== '')
            ? (int) $usuarioOpt
            : null;

        $dryRun = (bool) $this->option('dry-run');
        $sinAnita = (bool) $this->option('sin-anita');
        $sku = $this->option('sku');

        if ($dryRun) {
            $this->info('Modo dry-run: no se calculan costos ni se graban precios.');
        }

        try {
            $ret = $service->procesar(
                $fecha,
                is_string($sku) ? $sku : null,
                $dryRun,
                $usuarioId,
                ! $sinAnita,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Fecha vigencia: {$ret['fecha_vigencia']} ({$ret['mes_label']}).");
        $this->info("Lista ERP id {$ret['listaprecio_id']} — código Anita {$ret['listaprecio_codigo']}.");
        $this->info("Artículos catálogo candidatos: {$ret['candidatos']}.");

        if (! $dryRun) {
            $this->info("Grabados: {$ret['grabados']}; actualizados: {$ret['actualizados']}.");
            $this->info(
                "Omitidos — duplicado: {$ret['omitidos_duplicado']}; sin fórmula: {$ret['omitidos_sin_formula']}; "
                ."costo incompleto: {$ret['omitidos_costo_incompleto']}; costo cero: {$ret['omitidos_costo_cero']}."
            );
        }

        foreach ($ret['errores'] as $w) {
            $this->warn($w);
        }

        return $ret['errores'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
