<?php

namespace App\Repositories\Solicitudpago;

use App\ApiAnita;
use App\Models\Solicitudpago\Sector_Solicitudpago;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Sectores de solicitudes de pago (Anita sueldos / tabla sector).
 * Sync pull desde bridge; CRUD local sin escritura en Anita.
 */
class Sector_SolicitudpagoRepository implements Sector_SolicitudpagoRepositoryInterface
{
    protected $model;

    protected string $tableAnita = 'sector';

    protected string $keyFieldAnita = 'sect_sector';

    public function __construct(Sector_Solicitudpago $model)
    {
        $this->model = $model;
    }

    public function all()
    {
        $this->sincronizarConAnita();

        return $this->model->newQuery()
            ->with('centrocostos')
            ->orderBy('codigo')
            ->get();
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

    public function sincronizarConAnita()
    {
        ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => $this->tableAnita,
            'campos' => 'sect_sector, sect_desc',
        ];
        $parsed = ApiAnita::parsearRespuestaLista($apiAnita->apiCall($data));
        foreach ($parsed['filas'] as $row) {
            $codigo = (int) ($row->sect_sector ?? 0);
            if ($codigo <= 0) {
                continue;
            }

            $attrs = [
                'codigo' => $codigo,
                'nombre' => $this->recortar(trim((string) ($row->sect_desc ?? '')), 30),
            ];
            if ($attrs['nombre'] === '') {
                $attrs['nombre'] = (string) $codigo;
            }

            if ($this->findPorCodigo($codigo)) {
                continue;
            }

            $this->model->create($attrs);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizarPayload(array $data, ?Sector_Solicitudpago $existente): array
    {
        $codigo = $existente !== null
            ? (int) $existente->codigo
            : (isset($data['codigo']) && (int) $data['codigo'] > 0
                ? (int) $data['codigo']
                : $this->proximoCodigo());

        $ccId = isset($data['centrocosto_id']) && (int) $data['centrocosto_id'] > 0
            ? (int) $data['centrocosto_id']
            : null;

        return [
            'codigo' => $codigo,
            'nombre' => $this->recortar(trim((string) ($data['nombre'] ?? '')), 30),
            'centrocosto_id' => $ccId,
        ];
    }

    private function proximoCodigo(): int
    {
        $maxLocal = (int) ($this->model->newQuery()->max('codigo') ?? 0);

        return $maxLocal + 1;
    }

    private function recortar(string $valor, int $len): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($valor, 0, $len);
        }

        return substr($valor, 0, $len);
    }
}
