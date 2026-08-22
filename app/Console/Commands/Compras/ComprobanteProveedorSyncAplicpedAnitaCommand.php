<?php

namespace App\Console\Commands\Compras;

use App\ApiAnita;
use App\Models\Compras\Comprobante_Proveedor;
use App\Services\Compras\ComprobanteProveedorAnitaSyncService;
use App\Support\Compras\AnitaSync\ComprobanteProveedor\AplicpedFacturaAnitaMapper;
use App\Support\Compras\AnitaSync\ComprobanteProveedor\ComprobanteProveedorAnitaContext;
use App\Support\Compras\ComprobanteProveedorAnitaSyncEstado;
use Illuminate\Console\Command;
use Throwable;

/**
 * Backfill aplicped factura→PEP para comprobantes ERP ya sincronizados a Anita.
 */
class ComprobanteProveedorSyncAplicpedAnitaCommand extends Command
{
    protected $signature = 'comprobante-proveedor:sync-aplicped-anita
                            {--id= : Solo un comprobante_proveedor.id}
                            {--incluir-error : Incluye anita_sync_estado=ERROR (default: solo SYNC_OK)}
                            {--dry-run : Lista sin escribir (default si no hay --ejecutar)}
                            {--ejecutar : Borra/reinserta aplicped en Anita}';

    protected $description = 'Regraba aplicped (factura→PEP) en Anita para facturas ERP con OC ya sincronizadas';

    public function handle(ComprobanteProveedorAnitaSyncService $sync): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = ! $ejecutar || (bool) $this->option('dry-run');
        if ($ejecutar && (bool) $this->option('dry-run')) {
            $this->error('No combine --ejecutar con --dry-run.');

            return self::FAILURE;
        }

        $idOpt = $this->option('id');
        $soloId = ($idOpt !== null && $idOpt !== '') ? (int) $idOpt : null;
        $incluirError = (bool) $this->option('incluir-error');

        $estados = [ComprobanteProveedorAnitaSyncEstado::SYNC_OK];
        if ($incluirError) {
            $estados[] = ComprobanteProveedorAnitaSyncEstado::ERROR;
        }

        $query = Comprobante_Proveedor::query()
            ->with([
                'proveedores',
                'tipotransaccion_compras',
                'ordencompras',
                'comprobante_proveedor_articulos.articulos',
                'ordencompras.ordencompra_articulos.articulos',
            ])
            ->whereNotNull('anita_nro_interno')
            ->where('anita_nro_interno', '>', 0)
            ->whereNotNull('ordencompra_id')
            ->where('ordencompra_id', '>', 0)
            ->whereIn('anita_sync_estado', $estados)
            ->orderBy('id');

        if ($soloId !== null) {
            $query->whereKey($soloId);
        }

        $coleccion = $query->get();

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line($dryRun ? 'DRY-RUN: no se escribe Anita.' : 'EJECUTAR: delete+insert aplicped.');
        $this->line('Estados: '.implode(', ', $estados));
        $this->line('Candidatos: '.$coleccion->count());

        $ok = 0;
        $omitidos = 0;
        $errores = 0;
        $filas = [];

        foreach ($coleccion as $cp) {
            $ctx = new ComprobanteProveedorAnitaContext($cp, (int) $cp->anita_nro_interno);
            $clavePep = AplicpedFacturaAnitaMapper::clavePepDesdeContexto($ctx);
            $claveFac = AplicpedFacturaAnitaMapper::claveFactura($ctx);
            $nroOc = (int) ($clavePep['nro'] ?? 0);
            $lineas = $clavePep !== null ? count(AplicpedFacturaAnitaMapper::lineas($cp)) : 0;
            $etiqueta = sprintf(
                '%s %s-%s-%s',
                $claveFac['tipo'] ?: '???',
                $claveFac['letra'] ?: '?',
                $claveFac['sucursal'],
                $claveFac['nro']
            );

            if ($clavePep === null || $nroOc <= 0 || $claveFac['tipo'] === '' || $claveFac['nro'] <= 0) {
                $filas[] = [$cp->id, $etiqueta, $nroOc ?: '—', $lineas, 'omitido', 'sin clave factura/PEP'];
                $omitidos++;
                continue;
            }

            if ($dryRun) {
                $filas[] = [$cp->id, $etiqueta, $nroOc, $lineas, 'dry-run', 'insertaría aplicped'];
                $ok++;
                continue;
            }

            try {
                $sync->resyncAplicped($cp);
                $filas[] = [$cp->id, $etiqueta, $nroOc, $lineas, 'ok', 'aplicped grabado'];
                $ok++;
            } catch (Throwable $e) {
                $filas[] = [$cp->id, $etiqueta, $nroOc, $lineas, 'error', mb_substr($e->getMessage(), 0, 120)];
                $errores++;
                $this->error('#'.$cp->id.' '.$e->getMessage());
            }
        }

        if ($filas !== []) {
            $this->table(
                ['ERP id', 'Factura', 'OC (PEP)', 'Líneas', 'Acción', 'Detalle'],
                $filas
            );
        }

        $this->table(['Métrica', 'Cantidad'], [
            ['Candidatos', $coleccion->count()],
            [$dryRun ? 'A grabar' : 'Grabados', $ok],
            ['Omitidos', $omitidos],
            ['Errores', $errores],
        ]);

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }
}
