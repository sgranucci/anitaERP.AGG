<?php

namespace App\Console\Commands;

use App\Services\Arca\ArcaCertificadoCsrService;
use Exception;
use Illuminate\Console\Command;

class ProbarCertificadoArca extends Command
{
    protected $signature = 'arca:probar-certificado
                            {--servicio= : wsfe, mtxca, wsremcarne, padron, wscdc o wsapoc}
                            {--empresa_id= : Obligatorio para wsfe/mtxca/wsremcarne}
                            {--id= : Id de inventario (ej. mtxca:1, wsapoc)}';

    protected $description = 'Prueba WSAA + Dummy del certificado ARCA vigente (no emite comprobantes)';

    public function handle(ArcaCertificadoCsrService $svc): int
    {
        try {
            $entrada = $this->resolverEntrada($svc);
            $r = $svc->probarConexion($entrada);
        } catch (Exception $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(($r['ok'] ? 'OK' : 'FALLÓ').' — '.$r['etiqueta'].(! empty($r['alias']) ? ' ['.$r['alias'].']' : ''));
        foreach ($r['pasos'] as $paso) {
            $pref = $paso['ok'] ? '<info>OK</info>' : '<error>ERROR</error>';
            $this->line($pref.' '.$paso['nombre'].': '.$paso['detalle']);
        }

        return $r['ok'] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverEntrada(ArcaCertificadoCsrService $svc): array
    {
        $id = trim((string) ($this->option('id') ?? ''));
        if ($id !== '') {
            return $svc->buscarPorId($id);
        }

        $servicio = strtolower(trim((string) ($this->option('servicio') ?? '')));
        if ($servicio === '') {
            throw new Exception('Indique --id=mtxca:1 o --servicio=mtxca --empresa_id=1');
        }

        $empresaOpt = $this->option('empresa_id');
        if ($empresaOpt !== null && $empresaOpt !== '') {
            return $svc->buscarPorId($servicio.':'.(int) $empresaOpt);
        }

        return $svc->buscarPorId($servicio);
    }
}
