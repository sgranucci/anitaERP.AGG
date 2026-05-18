<?php

namespace App\Services\Ventas;

use App\ApiAnita;
use App\Models\Ventas\DescuentoGastronomia;
use App\Repositories\Ventas\DescuentoGastronomiaRepositoryInterface;
use App\Support\Stock\AnitaSync\Descuento\DescuentoFieldMapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DescuentoGastronomiaAnitaSyncService
{
    public function __construct(
        private readonly DescuentoGastronomiaRepositoryInterface $descuentoGastronomiaRepository,
    ) {
    }

    /**
     * @return array{en_anita:int, importados:int, actualizados:int, omitidos:int, errores:list<string>}
     */
    public function sincronizarConAnita(): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $ret = ['en_anita' => 0, 'importados' => 0, 'actualizados' => 0, 'omitidos' => 0, 'errores' => []];
        $lista = $this->listarDescuentosDesdeAnita();
        $ret['en_anita'] = count($lista);

        foreach ($lista as $row) {
            $codigoAnita = DescuentoFieldMapper::mapCodigo($row);
            if ($codigoAnita === null) {
                $ret['omitidos']++;

                continue;
            }

            try {
                $estado = $this->importarFila($row, $codigoAnita);
                if ($estado === 'importado') {
                    $ret['importados']++;
                } elseif ($estado === 'actualizado') {
                    $ret['actualizados']++;
                } else {
                    $ret['omitidos']++;
                }
            } catch (\Throwable $e) {
                $msg = "Descuento Anita {$codigoAnita}: ".$e->getMessage();
                $ret['errores'][] = $msg;
                Log::warning('DescuentoGastronomiaAnitaSync: '.$msg, ['exception' => $e]);
            }
        }

        return $ret;
    }

    /**
     * @return 'importado'|'actualizado'|'omitido'
     */
    public function traerRegistroDeAnita(int $dtoCodigo): string
    {
        $row = $this->leerDescuentoEnAnita($dtoCodigo);
        if ($row === null) {
            return 'omitido';
        }

        $codigoAnita = DescuentoFieldMapper::mapCodigo($row);
        if ($codigoAnita === null) {
            return 'omitido';
        }

        return $this->importarFila($row, $codigoAnita);
    }

    /**
     * @return list<object>
     */
    public function listarDescuentosDesdeAnita(): array
    {
        $api = new ApiAnita;
        $payload = [
            'acc' => 'list',
            'tabla' => config('descuento_gastronomia_anita.tabla', 'descuento'),
            'campos' => config('descuento_gastronomia_anita.campos_listado'),
            'orderBy' => 'dto_codigo',
        ];
        $sistema = config('descuento_gastronomia_anita.sistema');
        if ($sistema !== null && $sistema !== '') {
            $payload['sistema'] = $sistema;
        }

        $rows = json_decode($api->apiCall($payload));

        return is_array($rows) ? $rows : [];
    }

    private function leerDescuentoEnAnita(int $dtoCodigo): ?object
    {
        $api = new ApiAnita;
        $payload = [
            'acc' => 'list',
            'tabla' => config('descuento_gastronomia_anita.tabla', 'descuento'),
            'campos' => config('descuento_gastronomia_anita.campos_listado'),
            'whereArmado' => " WHERE dto_codigo={$dtoCodigo}",
        ];
        $sistema = config('descuento_gastronomia_anita.sistema');
        if ($sistema !== null && $sistema !== '') {
            $payload['sistema'] = $sistema;
        }

        $rows = json_decode($api->apiCall($payload));

        return (is_array($rows) && count($rows) > 0) ? $rows[0] : null;
    }

    /**
     * @return 'importado'|'actualizado'|'omitido'
     */
    private function importarFila(object $row, string $codigoAnita): string
    {
        $payload = DescuentoFieldMapper::mapAll($row);
        $this->validarPayloadMinimo($payload, $codigoAnita);

        $datos = [
            'codigo' => $payload['codigo'],
            'nombre' => $payload['nombre'],
            'tipovalor' => $payload['tipovalor'],
            'valor' => $payload['valor'],
            'cliente_id' => $payload['cliente_id'] ?? null,
        ];

        $existente = DescuentoGastronomia::query()->where('codigo', $codigoAnita)->first();

        DB::beginTransaction();
        try {
            if ($existente) {
                $existente->update($datos);
                DB::commit();

                return 'actualizado';
            }

            $this->descuentoGastronomiaRepository->create($datos);
            DB::commit();

            return 'importado';
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validarPayloadMinimo(array $payload, string $codigoAnita): void
    {
        if (trim((string) ($payload['nombre'] ?? '')) === '') {
            throw new \InvalidArgumentException("nombre vacío (descuento {$codigoAnita}).");
        }
    }
}
