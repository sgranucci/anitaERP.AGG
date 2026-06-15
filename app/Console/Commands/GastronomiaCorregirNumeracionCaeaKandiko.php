<?php

namespace App\Console\Commands;

use App\Services\Ventas\Gastronomia\GastronomiaCaeaCorregirNumeracionDesfasadaService;
use Illuminate\Console\Command;

class GastronomiaCorregirNumeracionCaeaKandiko extends Command
{
    protected $signature = 'gastronomia:corregir-numeracion-caea
                            {--empresa=2 : empresa_id (default Kandiko)}
                            {--puntoventa=104 : puntoventa_id CAEA (default KSA 00031)}
                            {--tipo=1 : tipotransaccion_id (default FAC)}
                            {--umbral=100000 : numerocomprobante >= umbral se considera desfasado}
                            {--sin-anita : No actualizar tablas Anita}
                            {--force : Ejecutar cambios (sin esto solo preview)}
                            {--yes : Sin confirmación interactiva}';

    protected $description = 'Renumera ventas CAEA desfasadas al correlativo compemis (Kandiko PV 00031 por defecto)';

    public function handle(GastronomiaCaeaCorregirNumeracionDesfasadaService $service): int
    {
        $empresaId = (int) $this->option('empresa');
        $puntoventaId = (int) $this->option('puntoventa');
        $tipoId = (int) $this->option('tipo');
        $umbral = (int) $this->option('umbral');
        $dryRun = ! $this->option('force');
        $actualizarAnita = ! $this->option('sin-anita');

        try {
            $resultado = $service->preview(
                $puntoventaId,
                $empresaId,
                $tipoId,
                $umbral,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'PV %d · empresa %d · compemis=%d · max válido=%d · ventas=%d',
            $resultado['contexto']['puntoventa_id'] ?? $puntoventaId,
            $resultado['contexto']['empresa_id'] ?? $empresaId,
            $resultado['ultimo_compemis'] ?? 0,
            $resultado['max_correlativo_valido'] ?? 0,
            $resultado['cantidad_ventas'] ?? 0,
        ));

        $correcciones = $resultado['correcciones'] ?? [];
        if ($correcciones === []) {
            $this->comment('No hay ventas desfasadas para corregir.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Correcciones planificadas:');
        foreach ($correcciones as $corr) {
            $this->line(sprintf(
                '  venta_id=%d  ERP %d → %d  (codigo %d, Anita origen %d)  %s',
                $corr['venta_id'],
                $corr['numero_erp_actual'],
                $corr['numero_nuevo'],
                $corr['numero_codigo_actual'],
                $corr['anita_nro_origen'],
                $corr['factura_nueva'] ?? '',
            ));
        }

        if ($dryRun) {
            $this->newLine();
            $this->comment('Simulación. Agregue --force para aplicar (opcional: --sin-anita).');

            return self::SUCCESS;
        }

        if (! $this->option('yes') && ! $this->confirm('¿Aplicar '.count($correcciones).' corrección(es)?', false)) {
            $this->comment('Cancelado.');

            return self::SUCCESS;
        }

        try {
            $aplicado = $service->ejecutar(
                $puntoventaId,
                $empresaId,
                $tipoId,
                $umbral,
                false,
                $actualizarAnita,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Aplicadas '.($aplicado['aplicadas'] ?? 0).' corrección(es).');

        return self::SUCCESS;
    }
}
