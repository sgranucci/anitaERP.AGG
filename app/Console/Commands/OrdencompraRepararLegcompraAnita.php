<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Compras\Ordencompra;
use App\Services\Compras\OrdencompraAnitaBridgeService;
use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaNumeracionSupport;
use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaWhereSupport;
use Illuminate\Console\Command;

class OrdencompraRepararLegcompraAnita extends Command
{
    protected $signature = 'ordencompra:reparar-legcompra-anita
                            {--desde=2026-06-30 : Fecha mínima created_at de OC en ERP (Y-m-d)}
                            {--numero= : Reparar solo esta numeroordencompra (opcional)}
                            {--dry-run : Lista OCs a reparar sin escribir en Informix}';

    protected $description = 'Inserta legcompra y pendfecha faltantes en Anita para OCs ERP ya grabadas en pendmaep';

    public function handle(OrdencompraAnitaBridgeService $bridge): int
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

        $numeroOpt = $this->option('numero');
        $numeroFiltro = is_string($numeroOpt) && trim($numeroOpt) !== '' ? (int) $numeroOpt : null;
        $dryRun = (bool) $this->option('dry-run');

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'OCs ERP created_at >= %s%s%s',
            $desde,
            $numeroFiltro !== null ? ' | numero '.$numeroFiltro : '',
            $dryRun ? ' | MODO SIMULACIÓN' : '',
        ));

        $query = Ordencompra::query()
            ->where('numeroordencompra', '>', 0)
            ->where('created_at', '>=', $desde.' 00:00:00')
            ->orderBy('numeroordencompra');

        if ($numeroFiltro !== null) {
            $query->where('numeroordencompra', $numeroFiltro);
        }

        $ocs = $query->get();
        if ($ocs->isEmpty()) {
            $this->warn('No hay órdenes de compra que coincidan con el filtro.');

            return self::SUCCESS;
        }

        $api = new ApiAnita;
        $sistema = OrdencompraAnitaNumeracionSupport::sistemaTComp();
        $pendientes = [];

        foreach ($ocs as $oc) {
            $numero = (int) $oc->numeroordencompra;
            $clave = OrdencompraAnitaWhereSupport::claveDesdeOrdencompra($oc);

            $rawPend = $api->apiCallEscritura([
                'acc' => 'list',
                'sistema' => $sistema,
                'tabla' => config('ordencompra_anita.tablas.cabecera'),
                'campos' => 'penmp_nro',
                'whereArmado' => OrdencompraAnitaWhereSupport::pendmaep($clave),
                'limit' => 'FIRST 1',
            ], 'ordencompra reparar pendmaep check');

            if (ApiAnita::primeraFilaLista((string) $rawPend) === null) {
                continue;
            }

            $rawLeg = $api->apiCallEscritura([
                'acc' => 'list',
                'sistema' => $sistema,
                'tabla' => config('ordencompra_anita.tablas.historia'),
                'campos' => 'legc_id',
                'whereArmado' => OrdencompraAnitaWhereSupport::legcompraPorNumeroOc($numero),
                'limit' => 'FIRST 1',
            ], 'ordencompra reparar legcompra check');

            $faltaLeg = ApiAnita::primeraFilaLista((string) $rawLeg) === null;

            $ctx = \App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaErpContext::desdeUsuarioId(
                $oc->creousuario_id !== null ? (int) $oc->creousuario_id : null
            );
            $proveedor6 = $ctx->codigoProveedor6((int) $oc->proveedor_id);

            $rawPf = $api->apiCallEscritura([
                'acc' => 'list',
                'sistema' => $sistema,
                'tabla' => config('ordencompra_anita.tablas.fecha_oc'),
                'campos' => 'penpf_nro',
                'whereArmado' => OrdencompraAnitaWhereSupport::pendfecha($clave, $proveedor6),
                'limit' => 'FIRST 1',
            ], 'ordencompra reparar pendfecha check');

            $faltaPf = ApiAnita::primeraFilaLista((string) $rawPf) === null;

            if (! $faltaLeg && ! $faltaPf) {
                continue;
            }

            $pendientes[] = [
                'oc' => $oc,
                'falta_legcompra' => $faltaLeg,
                'falta_pendfecha' => $faltaPf,
            ];
        }

        if ($pendientes === []) {
            $this->info('No hay OCs con pendmaep en Anita que requieran reparación.');

            return self::SUCCESS;
        }

        $this->table(
            ['OC', 'Estado ERP', 'Falta legcompra', 'Falta pendfecha'],
            array_map(static fn (array $row) => [
                (string) $row['oc']->numeroordencompra,
                (string) ($row['oc']->estadoordencompra ?? ''),
                $row['falta_legcompra'] ? 'Sí' : 'No',
                $row['falta_pendfecha'] ? 'Sí' : 'No',
            ], $pendientes)
        );

        if ($dryRun) {
            $this->info('Simulación: no se escribió nada en Anita.');

            return self::SUCCESS;
        }

        $ok = 0;
        $errores = 0;

        foreach ($pendientes as $row) {
            /** @var Ordencompra $oc */
            $oc = $row['oc'];
            $numero = (int) $oc->numeroordencompra;

            try {
                $result = $bridge->repararRegistrosAnitaFaltantes($oc);
                $this->line(sprintf(
                    'OC %d: legcompra=%s, pendfecha=%s',
                    $numero,
                    $result['legcompra'],
                    $result['pendfecha'],
                ));
                $ok++;
            } catch (\Throwable $e) {
                $this->error("OC {$numero}: ".$e->getMessage());
                $errores++;
            }
        }

        $this->info("Reparación finalizada: {$ok} OK, {$errores} error(es).");

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }
}
