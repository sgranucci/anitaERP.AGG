<?php

namespace App\Services\Wigos;

use App\Support\Wigos\WigosActiveServerStore;
use App\Support\Wigos\WigosConfigResolver;
use App\Support\Wigos\WigosSqlServerProcess;
use RuntimeException;
use Throwable;

/**
 * Probe SQL (SELECT @@VERSION) por empresa y publicación del alias ONLINE.
 */
final class WigosServidorActivoMonitorService
{
    /**
     * @return list<array{
     *     empresa_id: int,
     *     preferido: string,
     *     activo: ?string,
     *     aliases: array<string, array{ok:bool,skipped:bool,host:?string,error:?string}>
     * }>
     */
    public function ejecutarChequeos(): array
    {
        $empresas = $this->empresasAMonitorear();
        $resultados = [];

        foreach ($empresas as $empresaId) {
            $resultados[] = $this->chequearEmpresa($empresaId);
        }

        return $resultados;
    }

    /**
     * @return list<int>
     */
    public function empresasAMonitorear(): array
    {
        $raw = config('wigos.monitor_servidor_activo.empresas');
        if (is_array($raw) && $raw !== []) {
            $ids = [];
            foreach ($raw as $id) {
                $ids[] = max(0, (int) $id);
            }

            return array_values(array_unique($ids));
        }

        $ids = [0];
        foreach (array_keys((array) config('wigos.por_empresa', [])) as $empresaId) {
            $ids[] = (int) $empresaId;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array{
     *     empresa_id: int,
     *     preferido: string,
     *     activo: ?string,
     *     aliases: array<string, array{ok:bool,skipped:bool,host:?string,error:?string}>
     * }
     */
    private function chequearEmpresa(int $empresaId): array
    {
        $preferido = WigosConfigResolver::currWigosConfigurado($empresaId);
        $probe = [];
        $aliasesOut = [];

        foreach (['A', 'B'] as $alias) {
            $cfg = WigosConfigResolver::conexion($alias, $empresaId);
            $host = trim((string) ($cfg['host'] ?? ''));
            if ($host === '') {
                $aliasesOut[$alias] = [
                    'ok' => false,
                    'skipped' => true,
                    'host' => null,
                    'error' => 'sin host configurado',
                ];

                continue;
            }

            try {
                WigosSqlServerProcess::consultarVersion($alias, $empresaId);
                $probe[$alias] = ['ok' => true, 'error' => null, 'host' => $host];
                $aliasesOut[$alias] = [
                    'ok' => true,
                    'skipped' => false,
                    'host' => $host,
                    'error' => null,
                ];
            } catch (Throwable $e) {
                $msg = $e instanceof RuntimeException ? $e->getMessage() : $e->getMessage();
                $probe[$alias] = ['ok' => false, 'error' => $msg, 'host' => $host];
                $aliasesOut[$alias] = [
                    'ok' => false,
                    'skipped' => false,
                    'host' => $host,
                    'error' => $msg,
                ];
            }
        }

        if ($probe !== []) {
            WigosActiveServerStore::registrarChequeos($empresaId, $probe, $preferido);
        }

        return [
            'empresa_id' => $empresaId,
            'preferido' => $preferido,
            'activo' => WigosActiveServerStore::aliasActivo($empresaId),
            'aliases' => $aliasesOut,
        ];
    }
}
