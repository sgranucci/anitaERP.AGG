<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Arbolaprobacion;
use App\Support\Configuracion\ArbolaprobacionListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ArbolaprobacionRepository implements ArbolaprobacionRepositoryInterface
{
    protected $model;

    protected $empresaRepository;

    /**
     * PostRepository constructor.
     *
     * @param  Post  $post
     */
    public function __construct(Arbolaprobacion $arbolaprobacion,
        EmpresaRepositoryInterface $empresarepository)
    {
        $this->model = $arbolaprobacion;
        $this->empresaRepository = $empresarepository;
    }

    public function all()
    {
        return $this->leeArbolaprobacion(ArbolaprobacionListadoFiltros::filtrosVacios());
    }

    public function leeArbolaprobacion(array $filtros)
    {
        $query = $this->model->newQuery()
            ->with(['arbolaprobacion_niveles', 'empresas']);

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id');

        $tipoarbol = $this->tiposArbolPermitidosListado();
        if (count($tipoarbol) > 0) {
            $query->whereIn('tipoarbol', $tipoarbol);
        }

        ArbolaprobacionListadoFiltros::aplicar($query, $filtros);

        return $query->orderBy('id')->get();
    }

    /**
     * Tipos visibles en el ABM según permisos (administrador: todos).
     *
     * @return list<string>
     */
    private function tiposArbolPermitidosListado(): array
    {
        $tipoarbol = [];
        if (session()->get('rol_nombre') == 'administrador') {
            return $tipoarbol;
        }

        $permisos = traePermisosUsuario();

        // Slugs reales en tabla permiso (singular / orden-de-compra).
        if (in_array('actualiza-arbol-requisicion', $permisos['permisos']) ||
            in_array('consulta-arbol-requisicion', $permisos['permisos'])) {
            $tipoarbol[] = Arbolaprobacion::$enumTipoArbol[0]['nombre'];
        }

        if (in_array('actualiza-arbol-orden-de-compra', $permisos['permisos']) ||
            in_array('consulta-arbol-orden-de-compra', $permisos['permisos'])) {
            $tipoarbol[] = Arbolaprobacion::$enumTipoArbol[1]['nombre'];
        }

        if (in_array('actualiza-arbol-solicitudes-de-pago', $permisos['permisos']) ||
            in_array('consulta-arbol-solicitudes-de-pago', $permisos['permisos'])) {
            $tipoarbol[] = Arbolaprobacion::$enumTipoArbol[2]['nombre'];
        }

        if (in_array('actualiza-arbol-ordenes-de-venta', $permisos['permisos']) ||
            in_array('consulta-arbol-ordenes-de-venta', $permisos['permisos'])) {
            $tipoarbol[] = Arbolaprobacion::$enumTipoArbol[3]['nombre'];
        }

        return $tipoarbol;
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        return $this->model->findOrFail($id)->update($data);
    }

    public function delete($id)
    {
        return $this->model->destroy($id);
    }

    public function find($id)
    {
        if (null == $arbolaprobacion = $this->model->with(['arbolaprobacion_niveles' => function ($query) {
            $query->orderBy('centrocosto_id', 'asc');
            $query->orderBy('nivel', 'asc');
        },
        ], 'oc_triggers', 'cuenta_excepciones.cuentacontables', 'cuenta_excepciones.centrocostos', 'cuenta_excepciones.empresas', 're_triggers.cuentacontables', 're_triggers.monedas', 're_triggers.centrocostos')->findOrFail($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $arbolaprobacion;
    }

    public function findOrFail($id)
    {
        if (null == $arbolaprobacion = $this->model
            ->with(['arbolaprobacion_niveles' => function ($query) {
                $query->orderBy('centrocosto_id', 'asc');
                $query->orderBy('nivel', 'asc');
            },
            ], 'oc_triggers', 'cuenta_excepciones.cuentacontables', 'cuenta_excepciones.centrocostos', 'cuenta_excepciones.empresas', 're_triggers.cuentacontables', 're_triggers.monedas', 're_triggers.centrocostos')->findOrFail($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $arbolaprobacion;
    }

    public function findPorTipoArbol($tipoarbol)
    {
        $arbolaprobacion = $this->model->where('tipoarbol', $tipoarbol)
            ->where('estado', 'ACTIVO')
            ->with(['arbolaprobacion_niveles' => function ($query) {
                $query->orderBy('nivel', 'asc'); // O el nombre de la columna que necesites ordenar
            },
            ])->get();

        return $arbolaprobacion;
    }

    public function findPorTipoArbolYEmpresa(string $tipoarbol, int $empresa_id)
    {
        return $this->model->where('tipoarbol', $tipoarbol)
            ->where('estado', 'ACTIVO')
            ->where('empresa_id', $empresa_id)
            ->with(['arbolaprobacion_niveles' => function ($query) {
                $query->orderBy('nivel', 'asc');
            }])
            ->orderBy('id')
            ->get();
    }
}
