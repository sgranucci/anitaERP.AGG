<?php

namespace App\Repositories\Admin;

use App\Models\Seguridad\Usuario;
use Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

class UsuarioRepository implements UsuarioRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param  Post  $post
     */
    public function __construct(Usuario $usuario
    ) {
        $this->model = $usuario;
    }

    public function all()
    {
        $usuario = $this->model;

        // Lee la empresa
        $usuario_id = Auth::user()->id;

        $empresa_id = 0;
        if (count($tecnico) > 0) {
            $empresa_id = $tecnico[0]->empresa_id;

            if ($empresa_id != 0) {
                $usuario = $usuario->with('empresas')->with('usuarios')->where('empresa_id', $empresa_id)
                    ->get();
            } else {
                $usuario = $usuario->with('empresas')->with('usuarios')->get();
            }
        } else {
            $usuario = $usuario->with('empresas')->with('usuarios')->get();
        }

        return $usuario;
    }

    public function create(array $data)
    {
        $usuario = $this->model->create($data);

        return $usuario;
    }

    public function update(array $data, $id)
    {
        $usuario = $this->model->findOrFail($id)->update($data);

        return $usuario;
    }

    public function delete($id)
    {
        $usuario = $this->model->find($id);

        $usuario = $this->model->destroy($id);

        return $usuario;
    }

    public function find($id)
    {
        if (null == $usuario = $this->model->with('usuario_empresas')->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $usuario;
    }

    public function leePorUsuarioId($id)
    {
        if (null == $usuario = $this->model->with('usuario_empresas')->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $usuario;
    }

    public function findOrFail($id)
    {
        if (null == $usuario = $this->model->with('usuario_empresas')->findOrFail($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $usuario;
    }

    public function findPorIdOCodigo(string $valor, $empresa_id = null)
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        $q = $this->model->with('usuario_empresas');

        if (preg_match('/^\d+$/', $valor)) {
            $q->where('usuario.id', (int) $valor);
        } else {
            $q->whereRaw('UPPER(TRIM(usuario.usuario)) = ?', [Str::upper($valor)]);
        }

        if ($empresa_id) {
            $q->whereHas('usuario_empresas', function ($query) use ($empresa_id) {
                $query->where('empresa_id', (int) $empresa_id);
            });
        }

        return $q->first();
    }

    public function consultaUsuario($consulta, $empresa_id = null, $centrocosto_id = null)
    {
        $columns = ['usuario.id', 'usuario.usuario', 'usuario.nombre', 'usuario.email', 'centrocosto.nombre', 'sector_legajocompra.nombre'];
        $columnsOut = ['id', 'usuariologin', 'nombre', 'email', 'nombrecentrocosto', 'nombresectorlegajocompra'];

        $consulta = strtoupper($consulta);

        $count = count($columns);
        $data = $this->model->select('usuario.id as id',
            'usuario.usuario as usuariologin',
            'usuario.nombre as nombre',
            'usuario.email as email',
            'usuario.centrocosto_id as idcentrocosto',
            'centrocosto.nombre as nombrecentrocosto',
            'sector_legajocompra.nombre as nombresectorlegajocompra')
            ->leftjoin('centrocosto', 'centrocosto.id', '=', 'usuario.centrocosto_id')
            ->leftjoin('sector_legajocompra', 'sector_legajocompra.id', '=', 'usuario.sector_legajocompra_id')
            ->with('usuario_empresas');

        if (isset($empresa_id)) {
            $data->whereHas('usuario_empresas', function ($query) use ($empresa_id) {
                $query->where('empresa_id', $empresa_id);
            });
        }

        if (isset($centrocosto_id)) {
            $data->where('usuario.centrocosto_id', $centrocosto_id);
        }

        $data = $data->Where(function ($query) use ($count, $consulta, $columns) {
            for ($i = 0; $i < $count; $i++) {
                $query->orWhere($columns[$i], 'LIKE', '%'.$consulta.'%');
            }
        })
            ->get();

        $output = [];
        $output['data'] = '';
        $flSinDatos = true;
        $countCols = count($columnsOut);
        if (count($data) > 0) {
            foreach ($data as $row) {
                $flSinDatos = false;
                $output['data'] .= '<tr>';
                for ($i = 0; $i < $countCols; $i++) {
                    $output['data'] .= '<td class="'.$columnsOut[$i].'">'.e($row->{$columnsOut[$i]} ?? '').'</td>';
                }
                $output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultausuario">Elegir</a></td>';
                $output['data'] .= '</tr>';
            }
        }

        if ($flSinDatos) {
            $output['data'] .= '<tr>';
            $output['data'] .= '<td>Sin resultados</td>';
            $output['data'] .= '</tr>';
        }

        return json_encode($output, JSON_UNESCAPED_UNICODE);
    }
}
