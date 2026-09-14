<?php

namespace App\Repositories\Compras;

use App\ApiAnita;
use App\Models\Compras\Tipoempresa;
use App\Traits\AnitaBridgeEscritura;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class TipoempresaRepository implements TipoempresaRepositoryInterface
{
    use AnitaBridgeEscritura;

    protected $model;

    protected $tableAnita = 'tipoemp';

    protected $keyField = 'codigo';

    protected $keyFieldAnita = 'tipoe_codigo';

    private ?string $ultimoErrorSync = null;

    private ?string $sistemaAnitaResuelto = null;

    public function __construct(Tipoempresa $tipoempresa)
    {
        $this->model = $tipoempresa;
    }

    public function all()
    {
        $this->sincronizarConAnita();

        return $this->model->orderBy('nombre', 'ASC')->get();
    }

    public function ultimoErrorSync(): ?string
    {
        return $this->ultimoErrorSync;
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
        $registro = Tipoempresa::find($id);
        if ($registro === null) {
            return false;
        }

        $this->eliminarAnita($registro->codigo);

        return (bool) $this->model->destroy($id);
    }

    public function find($id)
    {
        if (null == $tipoempresa = $this->model->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $tipoempresa;
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
        $this->ultimoErrorSync = null;

        $parsed = $this->listarTipoempAnita();
        $filas = $parsed['filas'];

        if ($parsed['error_lectura'] !== null) {
            $this->ultimoErrorSync = $parsed['error_lectura'];
            Log::warning('tipoempresa.anita_sync', [
                'error' => $parsed['error_lectura'],
                'sistema' => $this->sistemaAnita(),
                'tabla' => $this->tableAnita,
            ]);

            return;
        }

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
            'sistema' => $this->sistemaAnita(),
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

    public function guardarAnita($request)
    {
        $apiAnita = new ApiAnita();
        $nombre = str_replace("'", "''", $request['nombre'] ?? '');

        $data = [
            'tabla' => $this->tableAnita,
            'sistema' => $this->sistemaAnita(),
            'acc' => 'insert',
            'campos' => 'tipoe_codigo, tipoe_desc',
            'valores' => "'".$request['codigo']."', '".$nombre."'",
        ];
        $this->apiCallAnitaEscritura($apiAnita, $data, 'tipoemp insert');
    }

    public function actualizarAnita($request, $id)
    {
        $apiAnita = new ApiAnita();
        $nombre = str_replace("'", "''", $request['nombre'] ?? '');

        $data = [
            'acc' => 'update',
            'tabla' => $this->tableAnita,
            'sistema' => $this->sistemaAnita(),
            'valores' => "tipoe_codigo = '".$request['codigo']."', tipoe_desc = '".$nombre."'",
            'whereArmado' => " WHERE tipoe_codigo = '".$id."' ",
        ];
        $this->apiCallAnitaEscritura($apiAnita, $data, 'tipoemp update');
    }

    public function eliminarAnita($id)
    {
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'delete',
            'tabla' => $this->tableAnita,
            'sistema' => $this->sistemaAnita(),
            'whereArmado' => " WHERE tipoe_codigo = '".$id."' ",
        ];
        $this->apiCallAnitaEscritura($apiAnita, $data, 'tipoemp delete');
    }

    /**
     * En Ferli tipoemp vive en ventas; en otros clientes puede estar en compras.
     * Prueba compras y, si no hay filas ni error, usa ventas.
     *
     * @return array{filas: list<object>, error_lectura: ?string}
     */
    private function listarTipoempAnita(): array
    {
        $apiAnita = new ApiAnita();
        $ultimoError = null;
        $vacioOk = null;

        foreach (['compras', 'ventas'] as $sistema) {
            $raw = $apiAnita->apiCall([
                'acc' => 'list',
                'sistema' => $sistema,
                'campos' => 'tipoe_codigo, tipoe_desc',
                'tabla' => $this->tableAnita,
            ]);
            $parsed = ApiAnita::parsearRespuestaLista($raw);

            if ($parsed['error_lectura'] !== null) {
                $ultimoError = $parsed['error_lectura'];
                continue;
            }

            if ($parsed['filas'] !== []) {
                $this->sistemaAnitaResuelto = $sistema;

                return $parsed;
            }

            $vacioOk = $parsed;
            $this->sistemaAnitaResuelto = $sistema;
        }

        if ($vacioOk !== null) {
            return $vacioOk;
        }

        $this->sistemaAnitaResuelto = 'compras';

        return ['filas' => [], 'error_lectura' => $ultimoError];
    }

    private function sistemaAnita(): string
    {
        if ($this->sistemaAnitaResuelto !== null) {
            return $this->sistemaAnitaResuelto;
        }

        $this->listarTipoempAnita();

        return $this->sistemaAnitaResuelto ?? 'compras';
    }

    private function ultimoCodigo(&$codigo): void
    {
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => $this->sistemaAnita(),
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
