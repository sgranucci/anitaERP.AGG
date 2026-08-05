<?php

namespace App\Repositories\Admin;

use App\Models\Seguridad\Usuario;
use App\Support\Seguridad\UsuarioOperativoSupport;
use Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

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

    public function findOperativo(int $id): ?Usuario
    {
        return UsuarioOperativoSupport::find($id);
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
        return UsuarioOperativoSupport::findPorIdOCodigo(
            $valor,
            UsuarioOperativoSupport::normalizarEmpresaId($empresa_id)
        );
    }

    /**
     * @param  list<int>  $usuarioIds
     * @return list<int>
     */
    public function filtrarIdsOperativos(array $usuarioIds): array
    {
        return UsuarioOperativoSupport::filtrarIdsActivos($usuarioIds);
    }

    /**
     * @param  list<int>  $usuarioIds
     * @return list<int>
     */
    public function filtrarIdsOperativosPorEmpresa(array $usuarioIds, int $empresaId): array
    {
        return UsuarioOperativoSupport::filtrarIdsOperativosPorEmpresa($usuarioIds, $empresaId);
    }

    public function listadoOperativoParaSelector(
        ?int $empresaId = null,
        ?int $centrocostoId = null,
        array $columnas = ['id', 'nombre', 'email', 'usuario'],
        bool $soloConEmail = false,
        array $with = [],
        ?int $sectorLegajocompraId = null,
    ): Collection {
        return UsuarioOperativoSupport::listadoParaSelector(
            $empresaId,
            $centrocostoId,
            $columnas,
            $soloConEmail,
            $with,
            $sectorLegajocompraId,
        );
    }

    public function consultaUsuario($consulta, $empresa_id = null, $centrocosto_id = null)
    {
        $columnsOut = ['id', 'usuariologin', 'nombre', 'email', 'nombrecentrocosto', 'nombresectorlegajocompra'];

        $data = UsuarioOperativoSupport::queryConsulta(
            UsuarioOperativoSupport::normalizarEmpresaId($empresa_id),
            UsuarioOperativoSupport::normalizarEmpresaId($centrocosto_id)
        );

        UsuarioOperativoSupport::aplicarFiltroTextoConsulta($data, (string) ($consulta ?? ''));

        $data = $data->orderBy('usuario.nombre')->get();

        $output = [];
        $output['data'] = '';
        $flSinDatos = true;
        $countCols = count($columnsOut);
        $columnasTabla = $countCols + 2;
        if (count($data) > 0) {
            foreach ($data as $row) {
                $flSinDatos = false;
                $empresasTexto = UsuarioOperativoSupport::etiquetaEmpresasUsuario($row);
                $output['data'] .= '<tr>';
                $output['data'] .= '<td class="empresas">'.e($empresasTexto).'</td>';
                for ($i = 0; $i < $countCols; $i++) {
                    $output['data'] .= '<td class="'.$columnsOut[$i].'">'.e($row->{$columnsOut[$i]} ?? '').'</td>';
                }
                $output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultausuario">Elegir</a></td>';
                $output['data'] .= '</tr>';
            }
        }

        if ($flSinDatos) {
            $output['data'] .= '<tr>';
            $output['data'] .= '<td colspan="'.$columnasTabla.'">Sin resultados</td>';
            $output['data'] .= '</tr>';
        }

        return json_encode($output, JSON_UNESCAPED_UNICODE);
    }
}
