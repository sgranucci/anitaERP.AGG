<?php

namespace App\Support\Uif;

use App\ApiAnita;
use App\Support\Uif\ClienteUifArchivoStorage;
use Illuminate\Support\Facades\File;

/**
 * Descarga clientes_uif + premios_uif (y mapa profesion) a JSON local — patrón auditoría gastronomía.
 * Origen Biyemas: bridge default ANITA_IP (10.20.30.200).
 */
final class ClienteUifAnitaBulkCacheSupport
{
    public const CAMPOS_CLIENTE = '
        inroclienteid, ctipodocumento, inrodocumento, ccuit, cnombre, ifechanac,
        ilocalidadnac, ipaisnac, csexo, cestadocivil, cdomicilio, cpiso, cdepto,
        clocalidad, ccodigopostal, ctelefono, cemail, iprovincia, ipais, iprofesion,
        fpremio, cmoneda, cdescpremio, ifechaentrega, cobservfisicas, ifechaalta,
        choraalta, iusuarioalta, cestado, ifechabaja, iusuariobaja, ifechaultmodif,
        choraultmodif, iusuarioultmodif, ilocalidad, ipep, iparaiso, iexterior,
        ifechafirmapep, ifeconfirmapep, ifeinformepep, ifeinformenosis, ifevtodni,
        cso, cactividadso, ccumplenormativaso, criesgo, inivelsocecon, cdecljur, ifevtoactividad
    ';

    public const CAMPOS_PREMIO = '
        inropremioid, inroclienteid, ctipodocumento, inrodocumento, ifechaentrega,
        fpremio, cmoneda, cdescpremio, ifechaalta, choraalta, iusuarioalta,
        ifechaultmodif, choraultmodif, iusuarioultmodif, isupervisoralta,
        choraentrega, cnroticket, cposicion, ifechatito, ctipomov, cmediopago,
        crecibo_pago, cextfoto
    ';

    public function directorioCache(string $origen = 'biyemas'): string
    {
        $origen = preg_replace('/[^a-z0-9_\-]/i', '', $origen) ?: 'biyemas';

        return storage_path('app/anita_uif_bulk_cache/'.$origen);
    }

    public function cacheCompleta(string $origen = 'biyemas'): bool
    {
        $dir = $this->directorioCache($origen);

        return is_file($dir.'/manifest.json')
            && is_file($dir.'/clientes.json')
            && is_file($dir.'/premios.json')
            && is_file($dir.'/profesion.json');
    }

    /**
     * @return array<string, mixed> manifest
     */
    public function descargar(string $origen = 'biyemas', ?string $servidor = null, bool $forzar = false): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $dir = $this->directorioCache($origen);
        if (! $forzar && $this->cacheCompleta($origen)) {
            return $this->cargarManifest($origen);
        }

        File::ensureDirectoryExists($dir);

        $servidor = $servidor !== null && $servidor !== ''
            ? $servidor
            : ClienteUifArchivoStorage::servidorDefault($origen);

        $profesion = $this->listar($servidor, 'profesion', 'iprofesionid, nuevocodigo', ' WHERE 1=1 ');
        $mapaProfesion = [];
        foreach ($profesion as $row) {
            $pid = (int) ($row->iprofesionid ?? 0);
            if ($pid > 0) {
                $mapaProfesion[$pid] = (string) ($row->nuevocodigo ?? '');
            }
        }

        $clientes = $this->listar($servidor, 'clientes_uif', self::CAMPOS_CLIENTE, ' WHERE 1=1 ');
        foreach ($clientes as $cli) {
            $iprof = (int) ($cli->iprofesion ?? 0);
            $cli->codigoprofesion = $mapaProfesion[$iprof] ?? ($cli->iprofesion ?? 1);
        }

        $premios = $this->listar($servidor, 'premios_uif', self::CAMPOS_PREMIO, ' WHERE 1=1 ');

        $this->guardarJson($dir.'/profesion.json', $profesion);
        $this->guardarJson($dir.'/clientes.json', $clientes);
        $this->guardarJson($dir.'/premios.json', $premios);

        $manifest = [
            'origen' => $origen,
            'servidor' => $servidor,
            'bridge' => ApiAnita::urlBridge($servidor),
            'generado_at' => now()->toIso8601String(),
            'directorio' => $dir,
            'counts' => [
                'profesion' => count($profesion),
                'clientes' => count($clientes),
                'premios' => count($premios),
            ],
        ];
        $this->guardarJson($dir.'/manifest.json', $manifest);

        return $manifest;
    }

    /**
     * @return array{
     *   manifest: array<string, mixed>,
     *   clientes: list<object>,
     *   premiosPorCliente: array<int, list<object>>
     * }
     */
    public function cargar(string $origen = 'biyemas'): array
    {
        $dir = $this->directorioCache($origen);
        if (! $this->cacheCompleta($origen)) {
            throw new \RuntimeException('Cache UIF bulk incompleta en '.$dir.'. Ejecute descargar primero.');
        }

        $manifest = $this->cargarManifest($origen);
        $clientes = $this->leerJsonLista($dir.'/clientes.json');
        $premios = $this->leerJsonLista($dir.'/premios.json');

        $premiosPorCliente = [];
        foreach ($premios as $p) {
            $cid = (int) ($p->inroclienteid ?? 0);
            if ($cid <= 0) {
                continue;
            }
            $premiosPorCliente[$cid][] = $p;
        }

        return [
            'manifest' => $manifest,
            'clientes' => $clientes,
            'premiosPorCliente' => $premiosPorCliente,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function cargarManifest(string $origen = 'biyemas'): array
    {
        $path = $this->directorioCache($origen).'/manifest.json';
        $raw = file_get_contents($path);
        $data = json_decode($raw ?: 'null', true);

        return is_array($data) ? $data : [];
    }

    /**
     * @return list<object>
     */
    private function listar(string $servidor, string $tabla, string $campos, string $whereArmado): array
    {
        $api = new ApiAnita;
        $payload = [
            'acc' => 'list',
            'tabla' => $tabla,
            'sistema' => 'base_admin',
            'campos' => $campos,
            'whereArmado' => $whereArmado,
        ];
        if ($servidor !== '') {
            $payload['servidor'] = $servidor;
        }
        $rows = json_decode($api->apiCall($payload));
        if (! is_array($rows)) {
            throw new \RuntimeException('Respuesta Anita inválida al listar '.$tabla.' (servidor='.$servidor.')');
        }

        return $rows;
    }

    /**
     * @param  list<object>|array<string, mixed>  $data
     */
    private function guardarJson(string $path, $data): void
    {
        file_put_contents(
            $path,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * @return list<object>
     */
    private function leerJsonLista(string $path): array
    {
        $raw = file_get_contents($path);
        $rows = json_decode($raw ?: 'null');
        if (! is_array($rows)) {
            throw new \RuntimeException('JSON inválido: '.$path);
        }

        return $rows;
    }
}
