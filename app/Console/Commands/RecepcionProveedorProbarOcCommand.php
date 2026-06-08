<?php

namespace App\Console\Commands;

use App\Models\Compras\Ordencompra;
use App\Services\Stock\RecepcionProveedorOrdencompraResolverService;
use App\Support\Stock\RecepcionProveedorDiferenciaSupport;
use App\Support\Stock\RecepcionProveedorToleranciaSupport;
use Illuminate\Console\Command;

class RecepcionProveedorProbarOcCommand extends Command
{
    protected $signature = 'recepcion-proveedor:probar-oc
                            {numero_oc : Número de orden de compra (penmp_nro)}
                            {--simular-items : Simula recepción total de la OC con precios originales}';

    protected $description = 'Prueba end-to-end la resolución de OC y el análisis de diferencias (sin grabar recepción)';

    public function handle(RecepcionProveedorOrdencompraResolverService $resolver): int
    {
        $numeroOc = (int) $this->argument('numero_oc');
        if ($numeroOc <= 0) {
            $this->error('Número de OC inválido.');

            return self::FAILURE;
        }

        $this->info("Resolviendo OC {$numeroOc} (Anita → anitaERP si hace falta)...");

        try {
            $data = $resolver->resolverPorNumeroOc($numeroOc, 1);
        } catch (\Throwable $e) {
            $this->error('Error: '.$e->getMessage());

            return self::FAILURE;
        }

        /** @var Ordencompra $oc */
        $oc = $data['cabecera'];
        $lineas = $data['lineas'];

        $this->table(
            ['Campo', 'Valor'],
            [
                ['ID ERP', $oc->id],
                ['Nº OC', $oc->numeroordencompra],
                ['Empresa', optional($oc->empresas)->nombre],
                ['Proveedor', optional($oc->proveedores)->nombre],
                ['Tratamiento', $oc->tratamiento],
                ['Centro costo', optional($oc->centrocostos)->nombre],
                ['Líneas OC', count($lineas)],
            ]
        );

        $tol = RecepcionProveedorToleranciaSupport::resolver((int) $oc->empresa_id, (int) $oc->centrocosto_id);
        $this->info('Tolerancias activas: cant '.$tol['cantidad_pct'].'% · precio '.$tol['precio_pct'].'% · abs '.$tol['precio_abs']);

        if ($this->option('simular-items')) {
            $oc->loadMissing('ordencompra_articulos.articulos');
            $analisis = RecepcionProveedorDiferenciaSupport::analizar($oc, $lineas);
            $this->info('Simulación recepción total sin diferencias:');
            $this->line('  Precio diff: '.($analisis['fl_precio_diferencia'] ? 'Sí' : 'No'));
            $this->line('  Cantidad diff: '.($analisis['fl_diferencia_cantidad'] ? 'Sí' : 'No'));
            $this->line('  Extra/sustituto: '.($analisis['fl_articulo_extra'] ? 'Sí' : 'No'));
            $this->line('  Faltante OC: '.($analisis['fl_faltante_oc'] ? 'Sí' : 'No'));
            $this->line('  Laboratorio: '.($analisis['fl_laboratorio'] ? 'Sí' : 'No'));
        }

        $rows = [];
        foreach ($lineas as $l) {
            $rows[] = [
                $l['sku'] ?? $l['articulo_id'],
                $l['cantidad_oc'] ?? $l['cantidad'],
                $l['precio_ordencompra'] ?? $l['precio'],
                $l['tipo_linea'] ?? 'OC',
            ];
        }
        $this->table(['SKU', 'Cant OC', 'Precio', 'Tipo línea'], $rows);

        $this->info('OK — OC resuelta. Use la pantalla stock/recepcion-proveedor/crear para recepcionar.');

        return self::SUCCESS;
    }
}
