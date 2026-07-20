<?php

namespace App\Repositories\Sueldos;

use App\ApiAnita;
use App\Models\Sueldos\Nombrebase_Sueldos;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Nombres de bases de sueldos (Anita sueldos / tabla nombase).
 * Sync pull desde el bridge solo para llenado inicial; el CRUD vive completo en el ERP
 * y no replica altas/bajas/modificaciones hacia Anita.
 */
class Nombrebase_SueldosRepository implements Nombrebase_SueldosRepositoryInterface
{
    protected $model;

    protected string $tableAnita = 'nombase';

    public function __construct(Nombrebase_Sueldos $model)
    {
        $this->model = $model;
    }

    public function all()
    {
        if (! $this->model->newQuery()->exists()) {
            $this->sincronizarConAnita();
        }

        return $this->model->newQuery()->orderBy('codigo')->get();
    }

    public function create(array $data)
    {
        $payload = $this->normalizarPayload($data, null);

        return $this->model->create($payload);
    }

    public function update(array $data, $id)
    {
        $registro = $this->model->findOrFail($id);
        $payload = $this->normalizarPayload($data, $registro);
        $registro->update($payload);

        return $registro;
    }

    public function delete($id)
    {
        $registro = $this->model->find($id);
        if ($registro === null) {
            return false;
        }

        return (bool) $this->model->destroy($id);
    }

    public function find($id)
    {
        if (null == $registro = $this->model->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $registro;
    }

    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }

    public function findPorCodigo(int $codigo)
    {
        return $this->model->newQuery()->where('codigo', $codigo)->first();
    }

    /**
     * Trae desde Anita (bridge) las bases y las inserta si no existen (llenado inicial).
     * No actualiza ni borra: el maestro vive en el ERP.
     *
     * @return array{en_anita: int, importados: int, omitidos: int, errores: list<string>}
     */
    public function sincronizarConAnita()
    {
        ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => $this->tableAnita,
            'campos' => 'nomb_base, nomb_desc',
            'orderBy' => 'nomb_base',
        ];
        $parsed = ApiAnita::parsearRespuestaLista($apiAnita->apiCall($data));

        $resultado = ['en_anita' => 0, 'importados' => 0, 'omitidos' => 0, 'errores' => []];
        if (! empty($parsed['error_lectura'])) {
            $resultado['errores'][] = (string) $parsed['error_lectura'];

            return $resultado;
        }

        // El llenado inicial no debe generar registros de auditoría (solo audita el CRUD del ERP).
        Nombrebase_Sueldos::withoutAuditing(function () use ($parsed, &$resultado) {
            foreach ($parsed['filas'] as $row) {
                $resultado['en_anita']++;
                $codigo = (int) ($row->nomb_base ?? 0);
                if ($codigo <= 0) {
                    $resultado['omitidos']++;
                    continue;
                }

                if ($this->findPorCodigo($codigo)) {
                    $resultado['omitidos']++;
                    continue;
                }

                $descripcion = $this->recortar(trim((string) ($row->nomb_desc ?? '')), 30);
                if ($descripcion === '') {
                    $descripcion = (string) $codigo;
                }

                $this->model->create([
                    'codigo' => $codigo,
                    'descripcion' => $descripcion,
                ]);
                $resultado['importados']++;
            }
        });

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizarPayload(array $data, ?Nombrebase_Sueldos $existente): array
    {
        $codigo = $existente !== null
            ? (int) $existente->codigo
            : (isset($data['codigo']) && (int) $data['codigo'] > 0
                ? (int) $data['codigo']
                : $this->proximoCodigo());

        return [
            'codigo' => $codigo,
            'descripcion' => $this->recortar(trim((string) ($data['descripcion'] ?? '')), 30),
        ];
    }

    private function proximoCodigo(): int
    {
        return (int) ($this->model->newQuery()->max('codigo') ?? 0) + 1;
    }

    private function recortar(string $valor, int $len): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($valor, 0, $len);
        }

        return substr($valor, 0, $len);
    }
}
