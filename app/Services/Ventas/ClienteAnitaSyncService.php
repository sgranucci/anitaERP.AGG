<?php

namespace App\Services\Ventas;

use App\ApiAnita;
use App\Repositories\Ventas\ClienteRepositoryInterface;
use Illuminate\Support\Facades\Log;

class ClienteAnitaSyncService
{
    public function __construct(
        private readonly ClienteRepositoryInterface $clienteRepository,
    ) {}

    /**
     * @return array{en_anita:int, importados:int, actualizados:int, omitidos:int, errores:list<string>}
     */
    public function sincronizarConAnita(): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $ret = ['en_anita' => 0, 'importados' => 0, 'actualizados' => 0, 'omitidos' => 0, 'errores' => []];
        $lista = $this->listarDesdeAnita();
        $ret['en_anita'] = count($lista);

        foreach ($lista as $row) {
            $codigo = $this->codigoDesdeFila($row);
            if ($codigo === null) {
                $ret['omitidos']++;

                continue;
            }

            try {
                // traerRegistroDeAnita lee climae en Anita y persiste en ERP (sin escribir en Anita).
                $estado = $this->clienteRepository->traerRegistroDeAnita(
                    trim((string) ($row->clim_cliente ?? $codigo))
                );
                if ($estado === 'importado') {
                    $ret['importados']++;
                } elseif ($estado === 'actualizado') {
                    $ret['actualizados']++;
                } else {
                    $ret['omitidos']++;
                }
            } catch (\Throwable $e) {
                $msg = "Cliente Anita clim_cliente={$codigo}: ".$e->getMessage();
                $ret['errores'][] = $msg;
                Log::warning('ClienteAnitaSync: '.$msg, ['exception' => $e]);
            }
        }

        return $ret;
    }

    /**
     * @return 'importado'|'actualizado'|'omitido'
     */
    public function traerRegistroDeAnita(string $codigo): string
    {
        return $this->clienteRepository->traerRegistroDeAnita($codigo);
    }

    /**
     * @return array{en_anita:int, actualizados:int, omitidos:int, sin_cliente:int, errores:list<string>}
     */
    public function actualizarDistribuidorIdDesdeAnita(): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        return $this->clienteRepository->actualizarDistribuidorIdDesdeAnita();
    }

    public function actualizarCobradorIdDesdeAnita(): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        return $this->clienteRepository->actualizarCobradorIdDesdeAnita();
    }

    /**
     * @return array{en_anita:int, actualizados:int, omitidos:int, sin_cliente:int, sin_mapeo:int, errores:list<string>, ejemplos:list<array<string, mixed>>}
     */
    public function actualizarCoeficienteIdDesdeAnita(bool $persistir = false): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        return $this->clienteRepository->actualizarCoeficienteIdDesdeAnita($persistir);
    }

    /**
     * @return list<object>
     */
    public function listarDesdeAnita(): array
    {
        $api = new ApiAnita;
        $payload = [
            'acc' => 'list',
            'tabla' => config('cliente_anita.tabla', 'climae'),
            'campos' => config('cliente_anita.campos_listado'),
        ];
        $sistema = config('cliente_anita.sistema');
        if ($sistema !== null && $sistema !== '') {
            $payload['sistema'] = $sistema;
        }

        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall($payload));
        if ($parsed['error_lectura'] !== null) {
            throw new \RuntimeException($parsed['error_lectura']);
        }

        return $parsed['filas'];
    }

    private function codigoDesdeFila(object $row): ?string
    {
        $raw = trim((string) ($row->clim_cliente ?? $row->codigo ?? ''));
        if ($raw === '') {
            return null;
        }

        if (ctype_digit($raw)) {
            $norm = ltrim($raw, '0');

            return $norm !== '' ? $norm : '0';
        }

        return $raw;
    }
}
