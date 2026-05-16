<?php

namespace App\Services\Stock;

use App\ApiAnita;
use App\Models\Configuracion\Empresa;
use App\Models\Stock\MesaGastronomia;
use App\Repositories\Stock\MesaGastronomiaRepositoryInterface;
use App\Repositories\Stock\UbicacionGastronomiaRepositoryInterface;
use App\Support\Stock\AnitaSync\Mesa\MesaFieldMapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MesaGastronomiaAnitaSyncService
{
    public function __construct(
        private readonly MesaGastronomiaRepositoryInterface $mesaGastronomiaRepository,
        private readonly UbicacionGastronomiaRepositoryInterface $ubicacionGastronomiaRepository,
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
        $lista = $this->listarMesasDesdeAnita();
        $ret['en_anita'] = count($lista);

        foreach ($lista as $row) {
            $codigoAnita = MesaFieldMapper::mapCodigo($row);
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
                $msg = "Mesa Anita {$codigoAnita}: ".$e->getMessage();
                $ret['errores'][] = $msg;
                Log::warning('MesaGastronomiaAnitaSync: '.$msg, ['exception' => $e]);
            }
        }

        return $ret;
    }

    /**
     * @return 'importado'|'actualizado'|'omitido'
     */
    public function traerRegistroDeAnita(int $mesCodigo): string
    {
        $row = $this->leerMesaEnAnita($mesCodigo);
        if ($row === null) {
            return 'omitido';
        }

        $codigoAnita = MesaFieldMapper::mapCodigo($row);
        if ($codigoAnita === null) {
            return 'omitido';
        }

        return $this->importarFila($row, $codigoAnita);
    }

    /**
     * @return list<object>
     */
    public function listarMesasDesdeAnita(): array
    {
        $api = new ApiAnita;
        $payload = [
            'acc' => 'list',
            'tabla' => config('mesa_gastronomia_anita.tabla', 'mesa'),
            'campos' => config('mesa_gastronomia_anita.campos_listado'),
            'orderBy' => 'mes_codigo',
        ];
        $sistema = config('mesa_gastronomia_anita.sistema');
        if ($sistema !== null && $sistema !== '') {
            $payload['sistema'] = $sistema;
        }

        $rows = json_decode($api->apiCall($payload));

        return is_array($rows) ? $rows : [];
    }

    private function leerMesaEnAnita(int $mesCodigo): ?object
    {
        $api = new ApiAnita;
        $payload = [
            'acc' => 'list',
            'tabla' => config('mesa_gastronomia_anita.tabla', 'mesa'),
            'campos' => config('mesa_gastronomia_anita.campos_listado'),
            'whereArmado' => " WHERE mes_codigo={$mesCodigo}",
        ];
        $sistema = config('mesa_gastronomia_anita.sistema');
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
        $payload = MesaFieldMapper::mapAll($row);
        $this->validarPayloadMinimo($payload, $codigoAnita);

        $ubicacionId = $this->ubicacionGastronomiaRepository->resolverId(
            $payload['ubicacion_nombre'] ?? null,
            (int) $payload['empresa_id'],
        );

        $datosMesa = [
            'codigo' => $payload['codigo'],
            'nombre' => $payload['nombre'],
            'ubicacion_id' => $ubicacionId,
            'numeromesa' => $payload['numeromesa'],
            'empresa_id' => $payload['empresa_id'],
        ];

        $existente = MesaGastronomia::query()->where('codigo', $codigoAnita)->first();

        DB::beginTransaction();
        try {
            if ($existente) {
                $existente->update($datosMesa);
                DB::commit();

                return 'actualizado';
            }

            $this->mesaGastronomiaRepository->create($datosMesa);
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
            throw new \InvalidArgumentException("nombre vacío (mesa {$codigoAnita}).");
        }
        if (trim((string) ($payload['numeromesa'] ?? '')) === '') {
            throw new \InvalidArgumentException("numeromesa vacío (mesa {$codigoAnita}).");
        }
        $empresaId = (int) ($payload['empresa_id'] ?? 0);
        if ($empresaId <= 0 || ! Empresa::query()->where('id', $empresaId)->exists()) {
            throw new \InvalidArgumentException("empresa_id {$empresaId} inexistente (mesa {$codigoAnita}).");
        }
    }
}
