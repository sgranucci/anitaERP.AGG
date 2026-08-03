<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Services\Uif\ClienteUifAnitaBulkSyncService;
use App\Support\Uif\ClienteUifAnitaBulkCacheSupport;
use App\Support\Uif\ClienteUifArchivoStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Throwable;

class SincronizarClienteUifAnitaBulk extends Command
{
    protected $signature = 'cliente-uif:sincronizar-anita-bulk
                            {--origen=biyemas : Etiqueta de cache (biyemas|kandiko|rebisco)}
                            {--servidor= : Host bridge Anita (default: ANITA_IP / config anita.ip)}
                            {--solo-descargar : Solo baja JSON a storage; no persiste}
                            {--solo-persistir : Usa cache existente; no vuelve a bajar}
                            {--forzar-descarga : Re-descarga aunque exista cache}
                            {--desde= : Filtrar persistencia por inroclienteid mínimo}
                            {--hasta= : Filtrar persistencia por inroclienteid máximo}
                            {--usuario= : ID usuario para creousuario_id / usuario_id}';

    protected $description = 'Sync masivo UIF: 2–3 lecturas Anita → cache JSON → persistencia (archivos en /scan, sin copy a /var).';

    public function handle(
        ClienteUifAnitaBulkCacheSupport $cache,
        ClienteUifAnitaBulkSyncService $sync,
    ): int {
        $origen = strtolower(trim((string) $this->option('origen'))) ?: 'biyemas';
        try {
            ClienteUifArchivoStorage::configOrigen($origen);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $servidor = $this->option('servidor');
        $servidor = is_string($servidor) && $servidor !== ''
            ? $servidor
            : ClienteUifArchivoStorage::servidorDefault($origen);

        $usuarioId = $this->option('usuario');
        $usuarioId = ($usuarioId !== null && $usuarioId !== '')
            ? (int) $usuarioId
            : (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);

        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            $this->error("Usuario id {$usuarioId} inválido.");

            return self::FAILURE;
        }

        $soloDescargar = (bool) $this->option('solo-descargar');
        $soloPersistir = (bool) $this->option('solo-persistir');
        $forzar = (bool) $this->option('forzar-descarga');

        try {
            if (! $soloPersistir) {
                $this->info("Descargando cache UIF origen={$origen} servidor={$servidor} sala_id=".ClienteUifArchivoStorage::salaId($origen).'…');
                $manifest = $cache->descargar($origen, $servidor, $forzar);
                $this->line('Cache en: '.($manifest['directorio'] ?? ''));
                $this->line('Counts: '.json_encode($manifest['counts'] ?? [], JSON_UNESCAPED_UNICODE));
                if ($soloDescargar) {
                    $this->info('Solo descarga: listo.');

                    return self::SUCCESS;
                }
            } elseif (! $cache->cacheCompleta($origen)) {
                $this->error('No hay cache completa. Quite --solo-persistir o ejecute sin ese flag.');

                return self::FAILURE;
            }

            $desde = $this->option('desde');
            $hasta = $this->option('hasta');
            $desdeInro = ($desde !== null && $desde !== '') ? (int) $desde : null;
            $hastaInro = ($hasta !== null && $hasta !== '') ? (int) $hasta : null;

            $this->info('Persistiendo desde cache (archivos vía /scan, sin copiar a /var)…');
            $resultado = $sync->sincronizarDesdeCache(
                $origen,
                $desdeInro,
                $hastaInro,
                fn (string $msg) => $this->line($msg),
            );

            $this->info(sprintf(
                'Finalizado: ok=%d err=%d omitidos=%d',
                $resultado['ok'],
                $resultado['err'],
                $resultado['omitidos']
            ));

            return $resultado['err'] > 0 ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
