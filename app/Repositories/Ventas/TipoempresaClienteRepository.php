<?php

namespace App\Repositories\Ventas;

use App\ApiAnita;
use App\Models\Ventas\TipoempresaCliente;
use App\Traits\AnitaBridgeEscritura;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TipoempresaClienteRepository implements TipoempresaClienteRepositoryInterface
{
    use AnitaBridgeEscritura;

    protected $model;

    protected $tableAnita = 'tipoemp';

    protected $keyField = 'codigo';

    protected $keyFieldAnita = 'tipoe_codigo';

    public function __construct(TipoempresaCliente $tipoempresaCliente)
    {
        $this->model = $tipoempresaCliente;
    }

    public function all()
    {
        $this->sincronizarConAnita();

        return $this->model->orderBy('nombre', 'ASC')->get();
    }

    public function create(array $data)
    {
        $codigo = '';
        $this->ultimoCodigo($codigo);
        $data['codigo'] = $codigo;

        $this->model->create($data);
        $this->guardarAnita($data);
    }

    public function update(array $data, $id)
    {
        $registro = $this->model->findOrFail($id);
        $data['codigo'] = $registro->codigo;
        $registro->update($data);
        $this->actualizarAnita($data, $data['codigo']);

        return $registro;
    }

    public function delete($id)
    {
        $registro = TipoempresaCliente::find($id);
        if ($registro === null) {
            return false;
        }

        $this->eliminarAnita($registro->codigo);

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

    public function findPorCodigo($codigo)
    {
        return $this->model->where('codigo', $codigo)->first();
    }

    public function findPorId($id)
    {
        return $this->model->where('id', $id)->first();
    }

    public function sincronizarConAnita()
    {
        ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'ventas',
            'campos' => 'tipoe_codigo, tipoe_desc',
            'tabla' => $this->tableAnita,
        ];
        $respuesta = $apiAnita->apiCall($data);
        $parsed = ApiAnita::parsearRespuestaLista($respuesta);
        $filas = $parsed['filas'];

        foreach ($filas as $row) {
            $codigo = trim((string) ($row->tipoe_codigo ?? ''));
            if ($codigo === '') {
                continue;
            }

            $nombre = trim((string) ($row->tipoe_desc ?? ''));
            $existente = $this->model->newQuery()
                ->where('codigo', $codigo)
                ->orWhere('codigo', ltrim($codigo, '0'))
                ->first();

            if ($existente) {
                if ($nombre !== '' && $existente->nombre !== $nombre) {
                    $existente->update(['nombre' => $nombre]);
                }
            } else {
                $this->model->create([
                    'nombre' => $nombre !== '' ? $nombre : $codigo,
                    'codigo' => $codigo,
                ]);
            }
        }
    }

    public function traerRegistroDeAnita($key)
    {
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'tabla' => $this->tableAnita,
            'sistema' => 'ventas',
            'campos' => 'tipoe_codigo, tipoe_desc',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' ",
        ];
        $parsed = ApiAnita::parsearRespuestaLista($apiAnita->apiCall($data));
        $filas = $parsed['filas'];

        if ($filas !== []) {
            $row = $filas[0];
            $this->model->create([
                'nombre' => trim((string) ($row->tipoe_desc ?? '')),
                'codigo' => $row->tipoe_codigo,
            ]);
        }
    }

    protected function guardarAnita(array $request): void
    {
        $apiAnita = new ApiAnita();
        $nombre = str_replace("'", "''", $request['nombre'] ?? '');

        $data = [
            'tabla' => $this->tableAnita,
            'sistema' => 'ventas',
            'acc' => 'insert',
            'campos' => 'tipoe_codigo, tipoe_desc',
            'valores' => "'".$request['codigo']."', '".$nombre."'",
        ];
        $this->apiCallAnitaEscritura($apiAnita, $data, 'tipoemp insert');
    }

    protected function actualizarAnita(array $request, $id): void
    {
        $apiAnita = new ApiAnita();
        $nombre = str_replace("'", "''", $request['nombre'] ?? '');

        $data = [
            'acc' => 'update',
            'tabla' => $this->tableAnita,
            'sistema' => 'ventas',
            'valores' => "tipoe_codigo = '".$request['codigo']."', tipoe_desc = '".$nombre."'",
            'whereArmado' => " WHERE tipoe_codigo = '".$id."' ",
        ];
        $this->apiCallAnitaEscritura($apiAnita, $data, 'tipoemp update');
    }

    protected function eliminarAnita($id): void
    {
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'delete',
            'tabla' => $this->tableAnita,
            'sistema' => 'ventas',
            'whereArmado' => " WHERE tipoe_codigo = '".$id."' ",
        ];
        $this->apiCallAnitaEscritura($apiAnita, $data, 'tipoemp delete');
    }

    private function ultimoCodigo(&$codigo): void
    {
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => $this->tableAnita,
            'campos' => " max(tipoe_codigo) as {$this->keyFieldAnita} ",
        ];
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (is_array($dataAnita) && count($dataAnita) > 0 && $dataAnita[0]->{$this->keyFieldAnita} !== null) {
            $codigo = ltrim($dataAnita[0]->{$this->keyFieldAnita}, '0');
            $codigo = ((int) $codigo) + 1;
        } else {
            $codigo = 1;
        }
    }
}
