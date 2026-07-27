<?php

namespace App\Repositories\Ventas;

use App\ApiAnita;
use App\Models\Ventas\Camion;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class CamionRepository implements CamionRepositoryInterface
{
    protected $model;

    protected $tableAnita = 'camion';

    protected $keyField = 'codigo';

    protected $keyFieldAnita = 'cam_camion';

    public function __construct(Camion $camion)
    {
        $this->model = $camion;
    }

    public function all()
    {
        $hay = Camion::first();
        if (! $hay) {
            self::sincronizarConAnita();
        }

        return $this->model->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))->orderBy('dominio')->get();
    }

    public function create(array $data)
    {
        $codigo = '';
        self::ultimoCodigo($codigo);
        $data['codigo'] = (string) $codigo;
        $data = $this->normalizarPayload($data);

        $camion = $this->model->create($data);
        self::guardarAnita($data);

        return $camion;
    }

    public function update(array $data, $id)
    {
        $camion = $this->model->findOrFail($id);
        $data['codigo'] = $camion->codigo;
        $data = $this->normalizarPayload($data);

        $camion->update($data);
        self::actualizarAnita($data, $data['codigo']);

        return $camion;
    }

    public function delete($id)
    {
        $camion = Camion::find($id);
        if (! $camion) {
            return false;
        }

        self::eliminarAnita($camion->codigo);
        $this->model->destroy($id);

        return true;
    }

    public function find($id)
    {
        if (null == $camion = $this->model->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $camion;
    }

    public function findOrFail($id)
    {
        if (null == $camion = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $camion;
    }

    public function findPorCodigo($codigo)
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return null;
        }

        $camion = $this->model->newQuery()->where('codigo', $codigo)->first();
        if ($camion) {
            return $camion;
        }

        $alt = ltrim($codigo, '0');
        if ($alt !== '' && $alt !== $codigo) {
            return $this->model->newQuery()->where('codigo', $alt)->first();
        }

        return null;
    }

    public function sincronizarConAnita()
    {
        ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'ventas',
            'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita",
            'tabla' => $this->tableAnita,
        ];
        $dataAnita = json_decode($apiAnita->apiCall($data));
        if (! is_array($dataAnita)) {
            return;
        }

        $datosLocalArray = Camion::query()->pluck($this->keyField)->map(fn ($c) => ltrim((string) $c, '0'))->all();

        foreach ($dataAnita as $value) {
            $codigo = ltrim((string) ($value->{$this->keyField} ?? ''), '0');
            if ($codigo === '') {
                $codigo = '0';
            }
            if (! in_array($codigo, $datosLocalArray, true)) {
                $this->traerRegistroDeAnita($value->{$this->keyFieldAnita});
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
            'campos' => '
                cam_camion,
                cam_dominio,
                cam_habilitacion,
                cam_tipo,
                cam_dom_acoplado,
                cam_cuit_chofer,
                cam_cant_precinto
            ',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' ",
        ];
        $dataAnita = json_decode($apiAnita->apiCall($data));
        if (! is_array($dataAnita) || count($dataAnita) === 0) {
            return;
        }

        $row = $dataAnita[0];
        $arr = [
            'codigo' => (string) ltrim((string) $row->cam_camion, '0'),
            'dominio' => trim((string) ($row->cam_dominio ?? '')),
            'habilitacion' => trim((string) ($row->cam_habilitacion ?? '')),
            'tipo' => trim((string) ($row->cam_tipo ?? '')),
            'dominio_acoplado' => trim((string) ($row->cam_dom_acoplado ?? '')),
            'cuit_chofer' => trim((string) ($row->cam_cuit_chofer ?? '')),
            'cantidad_precinto' => (int) ($row->cam_cant_precinto ?? 0),
        ];
        if ($arr['codigo'] === '') {
            $arr['codigo'] = '0';
        }

        if ($this->findPorCodigo($arr['codigo'])) {
            return;
        }

        $this->model->create($arr);
    }

    public function guardarAnita($request)
    {
        $apiAnita = new ApiAnita();
        $data = [
            'tabla' => $this->tableAnita,
            'sistema' => 'ventas',
            'acc' => 'insert',
            'campos' => '
                cam_camion,
                cam_dominio,
                cam_habilitacion,
                cam_tipo,
                cam_dom_acoplado,
                cam_cuit_chofer,
                cam_cant_precinto
            ',
            'valores' => "
                '".(int) $request['codigo']."',
                '".$this->esc($request['dominio'] ?? '')."',
                '".$this->esc($request['habilitacion'] ?? '')."',
                '".$this->esc($request['tipo'] ?? '')."',
                '".$this->esc($request['dominio_acoplado'] ?? '')."',
                '".$this->esc($request['cuit_chofer'] ?? '')."',
                '".(int) ($request['cantidad_precinto'] ?? 0)."'
            ",
        ];
        $apiAnita->apiCallEscritura($data);
    }

    public function actualizarAnita($request, $id)
    {
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'update',
            'sistema' => 'ventas',
            'tabla' => $this->tableAnita,
            'valores' => "
                cam_camion = '".(int) $request['codigo']."',
                cam_dominio = '".$this->esc($request['dominio'] ?? '')."',
                cam_habilitacion = '".$this->esc($request['habilitacion'] ?? '')."',
                cam_tipo = '".$this->esc($request['tipo'] ?? '')."',
                cam_dom_acoplado = '".$this->esc($request['dominio_acoplado'] ?? '')."',
                cam_cuit_chofer = '".$this->esc($request['cuit_chofer'] ?? '')."',
                cam_cant_precinto = '".(int) ($request['cantidad_precinto'] ?? 0)."'
            ",
            'whereArmado' => " WHERE cam_camion = '".(int) $id."' ",
        ];
        $apiAnita->apiCallEscritura($data);
    }

    public function eliminarAnita($id)
    {
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'delete',
            'sistema' => 'ventas',
            'tabla' => $this->tableAnita,
            'whereArmado' => " WHERE cam_camion = '".(int) $id."' ",
        ];
        $apiAnita->apiCallEscritura($data);
    }

    public function consultaCamion(string $consulta): string
    {
        ini_set('max_execution_time', '300');
        ini_set('memory_limit', '512M');

        $consulta = strtoupper(trim($consulta));
        $query = $this->model->newQuery()->select(
            'id',
            'codigo',
            'dominio',
            'habilitacion',
            'tipo',
            'cantidad_precinto'
        );
        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('id', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('codigo', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('dominio', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('habilitacion', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('tipo', 'LIKE', '%'.$consulta.'%');
            });
        }

        $data = $query->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))->limit(200)->get();
        $puedeAbrirAbm = can('editar-camion', false) || can('listar-camion', false);

        $output = ['data' => ''];
        if ($data->isEmpty()) {
            $output['data'] = '<tr><td colspan="6">Sin resultados</td></tr>';
        } else {
            foreach ($data as $row) {
                $output['data'] .= '<tr>';
                $output['data'] .= '<td class="id">'.e($row->id).'</td>';
                $output['data'] .= '<td class="codigo">'.e($row->codigo).'</td>';
                $output['data'] .= '<td class="dominio">'.e($row->dominio).'</td>';
                $output['data'] .= '<td class="habilitacion">'.e($row->habilitacion).'</td>';
                $output['data'] .= '<td class="tipo">'.e($row->tipo).'</td>';
                $output['data'] .= '<td class="text-nowrap">';
                $output['data'] .= '<a class="btn btn-warning btn-sm eligeconsultacamion">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $url = route('editar_camion', [
                        'id' => $row->id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ]);
                    $output['data'] .= ' <a class="btn btn-info btn-sm" href="'.e($url).'" target="_blank" rel="noopener">Consultar</a>';
                }
                $output['data'] .= '</td></tr>';
            }
        }

        return json_encode($output, JSON_UNESCAPED_UNICODE);
    }

    private function ultimoCodigo(&$codigo): void
    {
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => $this->tableAnita,
            'campos' => " max(cam_camion) as $this->keyFieldAnita ",
        ];
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $maxAnita = 0;
        if (is_array($dataAnita) && count($dataAnita) > 0) {
            $maxAnita = (int) ltrim((string) ($dataAnita[0]->{$this->keyFieldAnita} ?? '0'), '0');
        }

        $maxLocal = (int) Camion::query()->max(DB::raw(SqlDialectSupport::castEntero('codigo')));
        $codigo = (string) (max($maxAnita, $maxLocal) + 1);
    }

    /** @param array<string, mixed> $data */
    private function normalizarPayload(array $data): array
    {
        $data['dominio'] = strtoupper(trim((string) ($data['dominio'] ?? '')));
        $data['habilitacion'] = trim((string) ($data['habilitacion'] ?? ''));
        $data['tipo'] = strtoupper(trim((string) ($data['tipo'] ?? '')));
        $data['dominio_acoplado'] = strtoupper(trim((string) ($data['dominio_acoplado'] ?? '')));
        $data['cuit_chofer'] = trim((string) ($data['cuit_chofer'] ?? ''));
        $data['cantidad_precinto'] = (int) ($data['cantidad_precinto'] ?? 0);

        return $data;
    }

    private function esc(string $valor): string
    {
        return str_replace("'", "''", $valor);
    }
}
