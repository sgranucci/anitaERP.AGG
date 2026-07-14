<?php

namespace App\Repositories\Ventas;

use App\ApiAnita;
use App\Models\Ventas\Cobrador;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CobradorRepository implements CobradorRepositoryInterface
{
    protected $model;

    protected $tableAnita = 'cobrador';

    protected $keyField = 'codigo';

    protected $keyFieldAnita = 'cobr_codigo';

    public function __construct(
        Cobrador $cobrador,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->model = $cobrador;
    }

    public function all()
    {
        $hay = Cobrador::first();
        if (! $hay) {
            self::sincronizarConAnita();
        }

        $query = $this->model->with('empresas')->orderBy('nombre', 'ASC');
        // Anita suele traer cobr_empresa=0 → empresa_id null (aplica a cualquier empresa)
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id', true);

        return $query->get();
    }

    public function create(array $data)
    {
        $codigo = '';
        self::ultimoCodigo($codigo);
        $data['codigo'] = (string) $codigo;
        $data = $this->normalizarPayload($data);

        $cobrador = $this->model->create($data);
        self::guardarAnita($data);

        return $cobrador;
    }

    public function update(array $data, $id)
    {
        $cobrador = $this->model->findOrFail($id);
        $data['codigo'] = $cobrador->codigo;
        $data = $this->normalizarPayload($data);

        $cobrador->update($data);
        self::actualizarAnita($data, $data['codigo']);

        return $cobrador;
    }

    public function delete($id)
    {
        $cobrador = Cobrador::find($id);
        if (! $cobrador) {
            return false;
        }

        self::eliminarAnita($cobrador->codigo);
        $this->model->destroy($id);

        return true;
    }

    public function find($id)
    {
        if (null == $cobrador = $this->model->with('empresas')->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $cobrador;
    }

    public function findOrFail($id)
    {
        if (null == $cobrador = $this->model->with('empresas')->findOrFail($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $cobrador;
    }

    public function findPorCodigo($codigo)
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return null;
        }

        $query = $this->model->newQuery()->where('codigo', $codigo);
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id', true);
        $cobrador = $query->first();
        if ($cobrador) {
            return $cobrador;
        }

        $alt = ltrim($codigo, '0');
        if ($alt !== '' && $alt !== $codigo) {
            $query = $this->model->newQuery()->where('codigo', $alt);
            $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id', true);

            return $query->first();
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

        $datosLocalArray = Cobrador::query()->pluck('codigo')->map(function ($c) {
            $c = trim((string) $c);
            if ($c !== '' && ctype_digit($c)) {
                $norm = ltrim($c, '0');

                return $norm !== '' ? $norm : '0';
            }

            return $c;
        })->all();

        foreach ($dataAnita as $value) {
            $codigo = trim((string) ($value->{$this->keyField} ?? $value->{$this->keyFieldAnita} ?? ''));
            if ($codigo === '') {
                continue;
            }
            $norm = ctype_digit($codigo) ? (ltrim($codigo, '0') !== '' ? ltrim($codigo, '0') : '0') : $codigo;
            if (! in_array($norm, $datosLocalArray, true)) {
                $this->traerRegistroDeAnita($codigo);
                $datosLocalArray[] = $norm;
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
                cobr_codigo,
                cobr_nombre,
                cobr_comision,
                cobr_empresa,
                cobr_legajo
            ',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' ",
        ];
        $dataAnita = json_decode($apiAnita->apiCall($data));
        if (! is_array($dataAnita) || count($dataAnita) === 0) {
            return;
        }

        $row = $dataAnita[0];
        $codigo = trim((string) ($row->cobr_codigo ?? ''));
        if ($codigo === '') {
            return;
        }
        if (ctype_digit($codigo)) {
            $codigo = ltrim($codigo, '0');
            $codigo = $codigo !== '' ? $codigo : '0';
        }

        $empresaId = (int) ($row->cobr_empresa ?? 0);
        // Anita suele mandar cobr_empresa=0; en ERP el maestro queda en empresa 1
        if ($empresaId <= 0) {
            $empresaId = 1;
        }

        $legajoId = (int) ($row->cobr_legajo ?? 0);
        if ($legajoId <= 0) {
            $legajoId = null;
        }

        $existente = $this->model->newQuery()->where('codigo', $codigo)->first();
        $payload = [
            'nombre' => trim((string) ($row->cobr_nombre ?? '')),
            'comision' => (float) ($row->cobr_comision ?? 0),
            'empresa_id' => $empresaId,
            'legajo_id' => $legajoId,
            'codigo' => $codigo,
        ];

        if ($existente) {
            $existente->update($payload);
        } else {
            $this->model->create($payload);
        }
    }

    public function guardarAnita($request)
    {
        $apiAnita = new ApiAnita();
        $empresa = (int) ($request['empresa_id'] ?? 0);
        $legajo = (int) ($request['legajo_id'] ?? 0);

        $data = [
            'tabla' => $this->tableAnita,
            'sistema' => 'ventas',
            'acc' => 'insert',
            'campos' => 'cobr_codigo, cobr_nombre, cobr_comision, cobr_empresa, cobr_legajo',
            'valores' => " '".$request['codigo']."',
                '".$this->sqlLit($request['nombre'] ?? '')."',
                '".((float) ($request['comision'] ?? 0))."',
                '".$empresa."',
                '".$legajo."' ",
        ];
        $apiAnita->apiCallEscritura($data);
    }

    public function actualizarAnita($request, $id)
    {
        $apiAnita = new ApiAnita();
        $empresa = (int) ($request['empresa_id'] ?? 0);
        $legajo = (int) ($request['legajo_id'] ?? 0);

        $data = [
            'acc' => 'update',
            'sistema' => 'ventas',
            'tabla' => $this->tableAnita,
            'valores' => "
                cobr_nombre = '".$this->sqlLit($request['nombre'] ?? '')."',
                cobr_comision = '".((float) ($request['comision'] ?? 0))."',
                cobr_empresa = '".$empresa."',
                cobr_legajo = '".$legajo."' ",
            'whereArmado' => " WHERE cobr_codigo = '".$id."' ",
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
            'whereArmado' => " WHERE cobr_codigo = '".$id."' ",
        ];
        $apiAnita->apiCallEscritura($data);
    }

    public function consultaCobrador(string $consulta): string
    {
        ini_set('max_execution_time', '300');
        ini_set('memory_limit', '512M');

        $consulta = strtoupper(trim($consulta));
        $query = $this->model->newQuery()->select('id', 'nombre', 'codigo');
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id', true);
        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('id', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('nombre', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('codigo', 'LIKE', '%'.$consulta.'%');
            });
        }

        $data = $query->orderBy('nombre')->orderBy('codigo')->limit(200)->get();
        $puedeAbrirAbm = can('editar-cobrador', false) || can('listar-cobrador', false);

        $output = ['data' => ''];
        if ($data->isEmpty()) {
            $output['data'] = '<tr><td colspan="4">Sin resultados</td></tr>';
        } else {
            foreach ($data as $row) {
                $output['data'] .= '<tr>';
                $output['data'] .= '<td class="id">'.e($row->id).'</td>';
                $output['data'] .= '<td class="nombre">'.e($row->nombre).'</td>';
                $output['data'] .= '<td class="codigo">'.e($row->codigo).'</td>';
                $output['data'] .= '<td class="text-nowrap">';
                $output['data'] .= '<a class="btn btn-warning btn-sm eligeconsultacobrador">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $url = route('editar_cobrador', [
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

    private function ultimoCodigo(&$codigo)
    {
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'tabla' => $this->tableAnita,
            'sistema' => 'ventas',
            'campos' => " max(cobr_codigo) as $this->keyFieldAnita ",
        ];
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (is_array($dataAnita) && count($dataAnita) > 0 && ($dataAnita[0]->{$this->keyFieldAnita} ?? '') !== '') {
            $numero = (int) ltrim((string) $dataAnita[0]->{$this->keyFieldAnita}, '0');
            $codigo = (string) ($numero + 1);
        } else {
            $codigo = '1';
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizarPayload(array $data): array
    {
        $data['nombre'] = mb_substr(trim((string) ($data['nombre'] ?? '')), 0, 30);
        $data['comision'] = (float) ($data['comision'] ?? 0);
        $empresaId = (int) ($data['empresa_id'] ?? 0);
        $data['empresa_id'] = $empresaId > 0 ? $empresaId : null;
        $legajoId = (int) ($data['legajo_id'] ?? 0);
        $data['legajo_id'] = $legajoId > 0 ? $legajoId : null;

        return $data;
    }

    private function sqlLit(string $valor): string
    {
        return str_replace("'", "''", $valor);
    }
}
