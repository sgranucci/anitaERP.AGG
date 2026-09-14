<?php

namespace App\Jobs\Arca;

use App\Services\Arca\ArcaCertificadoCsrService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProbarCertificadoArcaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public function __construct(
        public string $certificadoId,
        public string $resultadoPath,
    ) {}

    public function handle(ArcaCertificadoCsrService $svc): void
    {
        try {
            $entrada = $svc->buscarPorId($this->certificadoId);
            $r = $svc->probarConexion($entrada);
            $lines = [
                ($r['ok'] ? 'OK' : 'FALLÓ').' — '.$r['etiqueta'].' alias='.($r['alias'] ?? ''),
            ];
            foreach ($r['pasos'] as $p) {
                $lines[] = ($p['ok'] ? 'OK' : 'ERROR').' '.$p['nombre'].': '.$p['detalle'];
            }
            @file_put_contents($this->resultadoPath, implode("\n", $lines)."\n");
        } catch (Throwable $e) {
            @file_put_contents(
                $this->resultadoPath,
                'EXCEPTION: '.$e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n"
            );
            throw $e;
        }
    }
}
