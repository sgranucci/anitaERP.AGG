<?php

namespace App\Services\Uif;

use App\Repositories\Uif\AnitaUifArchivosSync;
use App\Repositories\Uif\Cliente_UifRepository;
use App\Repositories\Uif\Cliente_UifRepositoryInterface;
use App\Support\Uif\ClienteUifAnitaBulkCacheSupport;
use App\Support\Uif\ClienteUifArchivoStorage;
use Throwable;

/**
 * Persiste clientes/premios/archivos UIF desde cache Anita (sin N+1 al bridge).
 */
final class ClienteUifAnitaBulkSyncService
{
    public function __construct(
        private readonly ClienteUifAnitaBulkCacheSupport $cache,
        private readonly Cliente_UifRepositoryInterface $clienteUifRepository,
    ) {
    }

    /**
     * @param  callable(string):void|null  $log
     * @return array{ok:int, err:int, omitidos:int, manifest:array<string,mixed>, fs_index:array<string,int|string>}
     */
    public function sincronizarDesdeCache(
        string $origen = 'biyemas',
        ?int $desdeInro = null,
        ?int $hastaInro = null,
        ?callable $log = null,
        int $progresoCada = 50,
    ): array {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $origen = strtolower(trim($origen)) ?: 'biyemas';
        $salaId = ClienteUifArchivoStorage::salaId($origen);
        $servidor = ClienteUifArchivoStorage::servidorDefault($origen);

        return ClienteUifArchivoStorage::withOrigen($origen, function () use (
            $origen,
            $salaId,
            $servidor,
            $desdeInro,
            $hastaInro,
            $log,
            $progresoCada
        ) {
            $pack = $this->cache->cargar($origen);
            $fsIndex = AnitaUifArchivosSync::warmIndicesDesdeDirectorios(
                ClienteUifArchivoStorage::dirClientes(),
                ClienteUifArchivoStorage::dirPremios()
            );
            $fotoIndex = ClientePremioUifFotoTesoreria::warmIndicePago();
            $fsIndex = array_merge($fsIndex, $fotoIndex);
            if ($log) {
                $log(sprintf(
                    'Origen=%s sala_id=%d servidor=%s | adjuntos_cli=%d adjuntos_pre=%d fotos_pago=%d',
                    $origen,
                    $salaId,
                    $servidor,
                    $fsIndex['clientes_archivos'] ?? 0,
                    $fsIndex['premios_archivos'] ?? 0,
                    $fsIndex['fotos_pago'] ?? 0
                ));
                $log(sprintf(
                    'Paths: cli=%s pre=%s fotos=%s',
                    ClienteUifArchivoStorage::dirClientes(),
                    ClienteUifArchivoStorage::dirPremios(),
                    ClienteUifArchivoStorage::dirFotosPremio()
                ));
            }

            /** @var Cliente_UifRepository $repo */
            $repo = $this->clienteUifRepository;

            $ok = 0;
            $err = 0;
            $omitidos = 0;
            $total = count($pack['clientes']);
            $i = 0;
            $opciones = [
                'anita_origen' => $origen,
                'sala_id' => $salaId,
                'servidor' => $servidor,
            ];

            foreach ($pack['clientes'] as $cli) {
                $i++;
                $inro = (int) ($cli->inroclienteid ?? 0);
                if ($inro <= 0) {
                    $omitidos++;
                    continue;
                }
                if ($desdeInro !== null && $inro < $desdeInro) {
                    $omitidos++;
                    continue;
                }
                if ($hastaInro !== null && $inro > $hastaInro) {
                    $omitidos++;
                    continue;
                }

                $premios = $pack['premiosPorCliente'][$inro] ?? [];

                try {
                    $repo->traerRegistroDeAnita($inro, $cli, $premios, $opciones);
                    $ok++;
                } catch (Throwable $e) {
                    $err++;
                    if ($log) {
                        $log("Error inroclienteid {$inro}: ".$e->getMessage());
                    }
                }

                if ($log && ($i % $progresoCada === 0 || $i === $total)) {
                    $log(sprintf('[%s] Persistidos %d/%d (ok=%d err=%d)', date('H:i:s'), $i, $total, $ok, $err));
                }
            }

            AnitaUifArchivosSync::clearIndices();
            ClientePremioUifFotoTesoreria::clearIndicePago();

            return [
                'ok' => $ok,
                'err' => $err,
                'omitidos' => $omitidos,
                'manifest' => $pack['manifest'],
                'fs_index' => $fsIndex,
            ];
        });
    }
}
