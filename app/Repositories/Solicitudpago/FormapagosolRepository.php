<?php

namespace App\Repositories\Solicitudpago;

use App\ApiAnita;
use App\Models\Solicitudpago\Formapagosol;
use App\Traits\AnitaBridgeEscritura;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Formas de pago de solicitudes (Anita che_ban / tabla formapagosol).
 * Sync pull + escritura create/update/delete en bridge.
 */
class FormapagosolRepository implements FormapagosolRepositoryInterface
{
    use AnitaBridgeEscritura;

    protected $model;

    protected string $tableAnita = 'formapagosol';

    protected string $keyFieldAnita = 'fpsol_id';

    public function __construct(Formapagosol $model)
    {
        $this->model = $model;
    }

    public function all()
    {
        $this->sincronizarConAnita();

        return $this->model->newQuery()->orderBy('codigo')->get();
    }

    public function create(array $data)
    {
        $payload = $this->normalizarPayload($data, null);
        $registro = $this->model->create($payload);
        $this->guardarAnita($payload);

        return $registro;
    }

    public function update(array $data, $id)
    {
        $registro = $this->model->findOrFail($id);
        $payload = $this->normalizarPayload($data, $registro);
        $registro->update($payload);
        $this->actualizarAnita($payload, (int) $registro->codigo);

        return $registro;
    }

    public function delete($id)
    {
        $registro = $this->model->find($id);
        if ($registro === null) {
            return false;
        }

        $this->eliminarAnita((int) $registro->codigo);

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
            'sistema' => 'che_ban',
            'tabla' => $this->tableAnita,
            'campos' => 'fpsol_id, fpsol_desc',
        ];
        $parsed = ApiAnita::parsearRespuestaLista($apiAnita->apiCall($data));
        foreach ($parsed['filas'] as $row) {
            $codigo = (int) ($row->fpsol_id ?? 0);
            if ($codigo <= 0) {
                continue;
            }

            $attrs = [
                'codigo' => $codigo,
                'nombre' => $this->recortar(trim((string) ($row->fpsol_desc ?? '')), 40),
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

    public function guardarAnita(array $data): void
    {
        $apiAnita = new ApiAnita();
        $nombre = $this->escaparAnita((string) ($data['nombre'] ?? ''));
        $payload = [
            'tabla' => $this->tableAnita,
            'sistema' => 'che_ban',
            'acc' => 'insert',
            'campos' => 'fpsol_id, fpsol_desc',
            'valores' => (int) $data['codigo'].", '".$nombre."'",
        ];
        $this->apiCallAnitaEscritura($apiAnita, $payload, 'formapagosol insert');
    }

    public function actualizarAnita(array $data, int $codigo): void
    {
        $apiAnita = new ApiAnita();
        $nombre = $this->escaparAnita((string) ($data['nombre'] ?? ''));
        $payload = [
            'acc' => 'update',
            'tabla' => $this->tableAnita,
            'sistema' => 'che_ban',
            'valores' => "fpsol_desc = '".$nombre."'",
            'whereArmado' => ' WHERE fpsol_id = '.$codigo.' ',
        ];
        $this->apiCallAnitaEscritura($apiAnita, $payload, 'formapagosol update');
    }

    public function eliminarAnita(int $codigo): void
    {
        $apiAnita = new ApiAnita();
        $payload = [
            'acc' => 'delete',
            'tabla' => $this->tableAnita,
            'sistema' => 'che_ban',
            'whereArmado' => ' WHERE fpsol_id = '.$codigo.' ',
        ];
        $this->apiCallAnitaEscritura($apiAnita, $payload, 'formapagosol delete');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizarPayload(array $data, ?Formapagosol $existente): array
    {
        $codigo = $existente !== null
            ? (int) $existente->codigo
            : $this->proximoCodigo();

        return [
            'codigo' => $codigo,
            'nombre' => $this->recortar(trim((string) ($data['nombre'] ?? '')), 40),
        ];
    }

    private function proximoCodigo(): int
    {
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'che_ban',
            'tabla' => $this->tableAnita,
            'campos' => 'max(fpsol_id) as max_codigo',
        ];
        $parsed = ApiAnita::parsearRespuestaLista($apiAnita->apiCall($data));
        $maxAnita = 0;
        if ($parsed['filas'] !== []) {
            $maxAnita = (int) ($parsed['filas'][0]->max_codigo ?? 0);
        }

        $maxLocal = (int) ($this->model->newQuery()->max('codigo') ?? 0);

        return max($maxAnita, $maxLocal) + 1;
    }

    private function escaparAnita(string $valor): string
    {
        return str_replace("'", "''", $valor);
    }

    private function recortar(string $valor, int $len): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($valor, 0, $len);
        }

        return substr($valor, 0, $len);
    }
}
