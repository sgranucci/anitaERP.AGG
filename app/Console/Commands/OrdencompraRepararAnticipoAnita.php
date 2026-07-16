<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Compras\Ordencompra;
use App\Services\Compras\OrdencompraAnitaBridgeService;
use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaNumeracionSupport;
use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaWhereSupport;
use App\Support\Stock\RecepcionProveedorAnitaEscrituraSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Corrige penmp_es_anticipo en Anita para OCs generadas nativamente en ERP.
 * Causa: mapTratamientoAnticipo usaba str_contains('ANTICIP') y mandaba S también para "NO ANTICIPADA".
 */
class OrdencompraRepararAnticipoAnita extends Command
{
    protected $signature = 'ordencompra:reparar-anticipo-anita
                            {--numero= : Reparar solo esta numeroordencompra (opcional)}
                            {--dry-run : Lista desfasadas sin escribir en Informix}
                            {--incluir-importadas : También OCs importadas desde Anita (no solo nativas)}
                            {--reintentos=5 : Reintentos ante lock Informix}';

    protected $description = 'Alinea penmp_es_anticipo (Anita) con tratamiento de OCs nativas del ERP';

    public function handle(OrdencompraAnitaBridgeService $bridge): int
    {
        if (! $bridge->habilitado()) {
            $this->error('ORDENCOMPRA_ANITA_ESCRITURA_HABILITADA está desactivada.');

            return self::FAILURE;
        }

        $numeroOpt = $this->option('numero');
        $numeroFiltro = is_string($numeroOpt) && trim($numeroOpt) !== '' ? (int) $numeroOpt : null;
        $dryRun = (bool) $this->option('dry-run');
        $incluirImportadas = (bool) $this->option('incluir-importadas');
        $maxReintentos = max(1, (int) $this->option('reintentos'));

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Alcance: %s%s%s',
            $incluirImportadas ? 'nativas + importadas' : 'solo nativas ERP',
            $numeroFiltro !== null ? " | OC {$numeroFiltro}" : '',
            $dryRun ? ' | MODO SIMULACIÓN' : '',
        ));

        $query = Ordencompra::query()
            ->where('numeroordencompra', '>', 0)
            ->orderBy('numeroordencompra');

        if ($numeroFiltro !== null) {
            $query->where('numeroordencompra', $numeroFiltro);
        }

        if (! $incluirImportadas) {
            $query->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('ordencompra_estado as oe')
                    ->whereColumn('oe.ordencompra_id', 'ordencompra.id')
                    ->where('oe.observacion', 'Alta de orden de compra');
            })->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('ordencompra_estado as oe')
                    ->whereColumn('oe.ordencompra_id', 'ordencompra.id')
                    ->where('oe.observacion', 'like', '%desde Anita%');
            });
        }

        $ocs = $query->get(['id', 'numeroordencompra', 'tratamiento', 'estadoordencompra']);
        if ($ocs->isEmpty()) {
            $this->warn('No hay órdenes de compra que coincidan con el filtro.');

            return self::SUCCESS;
        }

        $this->info('OCs a controlar: '.$ocs->count());

        $api = new ApiAnita;
        $sistema = OrdencompraAnitaNumeracionSupport::sistemaTComp();
        $desfasadas = [];
        $sinAnita = 0;
        $ok = 0;
        $bar = $this->output->createProgressBar($ocs->count());
        $bar->start();

        foreach ($ocs as $oc) {
            $clave = OrdencompraAnitaWhereSupport::claveDesdeOrdencompra($oc);
            $esperado = Ordencompra::anitaEsAnticipoDesdeTratamiento($oc->tratamiento);

            $raw = $api->apiCallEscritura([
                'acc' => 'list',
                'sistema' => $sistema,
                'tabla' => config('ordencompra_anita.tablas.cabecera'),
                'campos' => 'penmp_nro, penmp_es_anticipo',
                'whereArmado' => OrdencompraAnitaWhereSupport::pendmaep($clave),
            ], 'ordencompra reparar anticipo check');

            $fila = ApiAnita::primeraFilaLista((string) $raw);
            if ($fila === null) {
                $sinAnita++;
                $bar->advance();

                continue;
            }

            $actual = strtoupper(trim((string) ($fila->penmp_es_anticipo ?? 'N')));
            if ($actual === '') {
                $actual = 'N';
            }

            if ($actual === $esperado) {
                $ok++;
                $bar->advance();

                continue;
            }

            $desfasadas[] = [
                'oc' => $oc,
                'clave' => $clave,
                'actual' => $actual,
                'esperado' => $esperado,
            ];
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Alineadas', (string) $ok],
                ['Sin pendmaep en Anita', (string) $sinAnita],
                ['Desfasadas', (string) count($desfasadas)],
            ]
        );

        if ($desfasadas === []) {
            $this->info('No hay OCs con penmp_es_anticipo desfasado.');

            return self::SUCCESS;
        }

        $this->table(
            ['OC', 'Estado ERP', 'Tratamiento ERP', 'Anita actual', 'Anita esperado'],
            array_map(static fn (array $row) => [
                (string) $row['oc']->numeroordencompra,
                (string) ($row['oc']->estadoordencompra ?? ''),
                (string) ($row['oc']->tratamiento ?? ''),
                $row['actual'],
                $row['esperado'],
            ], $desfasadas)
        );

        if ($dryRun) {
            $this->info('Simulación: no se escribió nada en Anita.');

            return self::SUCCESS;
        }

        $reparadas = 0;
        $errores = 0;

        foreach ($desfasadas as $row) {
            /** @var Ordencompra $oc */
            $oc = $row['oc'];
            $numero = (int) $oc->numeroordencompra;
            $valores = 'penmp_es_anticipo = '.RecepcionProveedorAnitaEscrituraSupport::textoSql($row['esperado'], 1);
            $where = OrdencompraAnitaWhereSupport::pendmaep($row['clave']);

            try {
                $this->actualizarConReintentos($api, $sistema, $valores, $where, $maxReintentos);
                $this->assertAnticipoEnAnita($api, $sistema, $where, $row['esperado'], $numero);
                $reparadas++;
                $this->line("OK OC {$numero}: {$row['actual']} → {$row['esperado']}");
            } catch (\Throwable $e) {
                $errores++;
                $this->error("ERROR OC {$numero}: ".$e->getMessage());
            }

            usleep(50_000);
        }

        $this->newLine();
        $this->info("Reparadas: {$reparadas} | Errores: {$errores}");

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function actualizarConReintentos(
        ApiAnita $api,
        string $sistema,
        string $valores,
        string $where,
        int $maxReintentos,
    ): void {
        $ultimo = '';

        for ($intento = 1; $intento <= $maxReintentos; $intento++) {
            $resp = (string) $api->apiCall([
                'acc' => 'update',
                'sistema' => $sistema,
                'tabla' => config('ordencompra_anita.tablas.cabecera'),
                'valores' => $valores,
                'whereArmado' => $where,
            ]);
            $ultimo = $resp;

            if (ApiAnita::respuestaBridgeEscrituraExitosa($resp)) {
                return;
            }

            $err = ApiAnita::extraerMensajeError($resp);
            $esLock = $err !== null && (
                stripos($err, 'lock') !== false
                || stripos($resp, 'record is locked') !== false
                || stripos($resp, 'Could not lock') !== false
            );

            if ($esLock && $intento < $maxReintentos) {
                usleep(300_000 * $intento);

                continue;
            }

            if ($err !== null) {
                throw new \RuntimeException($err);
            }

            throw new \RuntimeException(
                'Update Anita no confirmó filas actualizadas: '.trim(ApiAnita::limpiarRespuestaBridgeEscritura($ultimo))
            );
        }
    }

    private function assertAnticipoEnAnita(
        ApiAnita $api,
        string $sistema,
        string $where,
        string $esperado,
        int $numero,
    ): void {
        $raw = $api->apiCallEscritura([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => config('ordencompra_anita.tablas.cabecera'),
            'campos' => 'penmp_es_anticipo',
            'whereArmado' => $where,
        ], 'ordencompra reparar anticipo verify');

        $fila = ApiAnita::primeraFilaLista((string) $raw);
        $actual = strtoupper(trim((string) ($fila->penmp_es_anticipo ?? '')));
        if ($actual !== $esperado) {
            throw new \RuntimeException(
                "Verificación falló OC {$numero}: Anita quedó '{$actual}', esperado '{$esperado}'"
            );
        }
    }
}
