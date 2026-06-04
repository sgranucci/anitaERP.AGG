<?php

namespace App\Services\Ventas;

use App\ApiAnita;
use App\Models\Configuracion\Empresa;
use App\Models\Ventas\MozoGastronomia;
use App\Repositories\Ventas\MozoGastronomiaRepositoryInterface;
use App\Support\Stock\AnitaSync\Mozo\MozoFieldMapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MozoGastronomiaAnitaSyncService
{
    public function __construct(
        private readonly MozoGastronomiaRepositoryInterface $mozoGastronomiaRepository,
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
        $lista = $this->listarMozosDesdeAnita();
        $ret['en_anita'] = count($lista);

        foreach ($lista as $row) {
            $codigoAnita = MozoFieldMapper::mapCodigo($row);
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
                $msg = "Mozo Anita {$codigoAnita}: ".$e->getMessage();
                $ret['errores'][] = $msg;
                Log::warning('MozoGastronomiaAnitaSync: '.$msg, ['exception' => $e]);
            }
        }

        return $ret;
    }

    /**
     * @return 'importado'|'actualizado'|'omitido'
     */
    public function traerRegistroDeAnita(int $vendCodigo): string
    {
        $row = $this->leerMozoEnAnita($vendCodigo);
        if ($row === null) {
            return 'omitido';
        }

        $codigoAnita = MozoFieldMapper::mapCodigo($row);
        if ($codigoAnita === null) {
            return 'omitido';
        }

        return $this->importarFila($row, $codigoAnita);
    }

    /**
     * @return list<object>
     */
    public function listarMozosDesdeAnita(): array
    {
        $api = new ApiAnita;
        $payload = [
            'acc' => 'list',
            'tabla' => config('mozo_gastronomia_anita.tabla', 'vendedor'),
            'campos' => config('mozo_gastronomia_anita.campos_listado'),
            'orderBy' => 'vend_codigo',
        ];
        $sistema = config('mozo_gastronomia_anita.sistema');
        if ($sistema !== null && $sistema !== '') {
            $payload['sistema'] = $sistema;
        }

        $rows = json_decode($api->apiCall($payload));

        return is_array($rows) ? $rows : [];
    }

    private function leerMozoEnAnita(int $vendCodigo): ?object
    {
        $api = new ApiAnita;
        $payload = [
            'acc' => 'list',
            'tabla' => config('mozo_gastronomia_anita.tabla', 'vendedor'),
            'campos' => config('mozo_gastronomia_anita.campos_listado'),
            'whereArmado' => " WHERE vend_codigo={$vendCodigo}",
        ];
        $sistema = config('mozo_gastronomia_anita.sistema');
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
        $payload = MozoFieldMapper::mapAll($row);
        $this->validarPayloadMinimo($payload, $codigoAnita);

        $datos = [
            'codigo' => $payload['codigo'],
            'nombre' => $payload['nombre'],
            'empresa_id' => $payload['empresa_id'],
        ];

        $existente = MozoGastronomia::query()
            ->where('empresa_id', $datos['empresa_id'])
            ->where('codigo', $codigoAnita)
            ->first();

        DB::beginTransaction();
        try {
            if ($existente) {
                $existente->update($datos);
                DB::commit();

                return 'actualizado';
            }

            $this->mozoGastronomiaRepository->create($datos);
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
            throw new \InvalidArgumentException("nombre vacío (mozo {$codigoAnita}).");
        }
        $empresaId = (int) ($payload['empresa_id'] ?? 0);
        if ($empresaId <= 0 || ! Empresa::query()->where('id', $empresaId)->exists()) {
            throw new \InvalidArgumentException("empresa_id {$empresaId} inexistente (mozo {$codigoAnita}).");
        }
    }
}
