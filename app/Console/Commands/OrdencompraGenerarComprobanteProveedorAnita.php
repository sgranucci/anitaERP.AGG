<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Compras\Ordencompra;
use App\Services\Compras\OrdencompraAnitaBridgeService;
use App\Services\Compras\OrdencompraGestionService;
use Illuminate\Console\Command;

class OrdencompraGenerarComprobanteProveedorAnita extends Command
{
    protected $signature = 'ordencompra:generar-comprobante-proveedor-anita
                            {--desde=2026-07-01 : Fecha mínima created_at de OC en ERP (Y-m-d)}
                            {--hasta= : Fecha máxima created_at de OC en ERP (Y-m-d, opcional)}
                            {--numero= : Procesar solo esta numeroordencompra (opcional)}
                            {--todas : No filtrar por penvp_nro_interno (procesa también OC no generadas desde el ERP)}
                            {--dry-run : Lista OC a procesar sin generar comprobante ni escribir en Anita}';

    protected $description = 'Genera el comprobante a venir (desde condición/forma de pago del proveedor) para OC sin comprobante y lo envía a Anita (occuota/ocfpagocuota)';

    public function handle(OrdencompraGestionService $gestion, OrdencompraAnitaBridgeService $bridge): int
    {
        if (! $bridge->habilitado()) {
            $this->error('ORDENCOMPRA_ANITA_ESCRITURA_HABILITADA está desactivada.');

            return self::FAILURE;
        }

        $desde = trim((string) $this->option('desde'));
        if ($desde === '') {
            $this->error('Indique --desde=Y-m-d.');

            return self::FAILURE;
        }

        $hastaOpt = $this->option('hasta');
        $hasta = is_string($hastaOpt) && trim($hastaOpt) !== '' ? trim($hastaOpt) : null;
        $numeroOpt = $this->option('numero');
        $numeroFiltro = is_string($numeroOpt) && trim($numeroOpt) !== '' ? (int) $numeroOpt : null;
        $dryRun = (bool) $this->option('dry-run');
        $todas = (bool) $this->option('todas');
        $piso = (int) config('ordencompra_anita.escritura.piso_nro_interno', 500000);

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'OC ERP created_at >= %s%s%s%s%s | SIN comprobante',
            $desde,
            $hasta !== null ? ' <= '.$hasta : '',
            $numeroFiltro !== null ? ' | numero '.$numeroFiltro : '',
            $todas ? ' | TODAS' : ' | solo generadas desde ERP (penvp_nro_interno >= '.$piso.')',
            $dryRun ? ' | MODO SIMULACIÓN' : '',
        ));

        $query = Ordencompra::query()
            ->where('numeroordencompra', '>', 0)
            ->where('created_at', '>=', $desde.' 00:00:00')
            ->whereDoesntHave('ordencompra_comprobantes')
            ->orderBy('numeroordencompra');

        if ($hasta !== null) {
            $query->where('created_at', '<=', $hasta.' 23:59:59');
        }
        if ($numeroFiltro !== null) {
            $query->where('numeroordencompra', $numeroFiltro);
        } elseif (! $todas) {
            $query->whereHas('ordencompra_articulos', function ($q) use ($piso) {
                $q->where('penvp_nro_interno', '>=', $piso);
            });
        }

        $ocs = $query->get();
        if ($ocs->isEmpty()) {
            $this->warn('No hay órdenes de compra sin comprobante que coincidan con el filtro.');

            return self::SUCCESS;
        }

        $this->line('OC candidatas (sin comprobante): '.$ocs->count());

        $generadas = 0;
        $enviadas = 0;
        $sinDatos = 0;
        $errores = 0;
        $filas = [];

        foreach ($ocs as $oc) {
            $numero = (int) $oc->numeroordencompra;

            try {
                if ($dryRun) {
                    $filas[] = [(string) $numero, (string) ($oc->estadoordencompra ?? ''), 'pendiente', 'pendiente'];

                    continue;
                }

                $genero = $gestion->generarComprobanteDefaultDesdeProveedor((int) $oc->id);
                if (! $genero) {
                    continue;
                }
                $generadas++;

                $bridge->sincronizarComprobantesCuotasAnita($oc->fresh());
                $enviadas++;

                $filas[] = [(string) $numero, (string) ($oc->estadoordencompra ?? ''), 'sí', 'sí'];
            } catch (\InvalidArgumentException $e) {
                $sinDatos++;
                $this->warn("OC {$numero}: ".$e->getMessage());
            } catch (\Throwable $e) {
                $errores++;
                $this->error("OC {$numero}: ".$e->getMessage());
            }
        }

        if ($filas !== []) {
            $this->table(['OC', 'Estado ERP', 'Comprobante', 'Enviado Anita'], $filas);
        }

        if ($dryRun) {
            $this->info('Simulación: '.$ocs->count().' OC se procesarían. No se generó ni escribió nada.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Finalizado: %d comprobante(s) generado(s) | %d enviado(s) a Anita | %d sin datos para precargar | %d error(es).',
            $generadas,
            $enviadas,
            $sinDatos,
            $errores,
        ));

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }
}
