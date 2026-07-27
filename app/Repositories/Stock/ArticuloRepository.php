<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Articulo_Caja;
use App\Models\Stock\Articulo;
use App\Support\Stock\ArticuloListadoFiltros;
use App\Models\Stock\Color;
use App\Models\Stock\Tipoliquido;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use App\ApiAnita;
use Auth;

class ArticuloRepository implements ArticuloRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Articulo $articulo)
    {
        $this->model = $articulo;
    }

    public function all()
    {
        $hay_articulo_cajas = $this->model->first();

        $ret = $this->model->get();

        return $ret;
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function find($id)
    {
        $query = $this->model->where('id', $id)
                                ->with('categorias')->with('unidadesdemedidas')
                                ->with('categorias')->with('subcategorias')->with('lineas')->with('mventas')->with('impuestos')
								->with('unidadesdemedidas')->with('unidadesdemedidasalternativas')->with('usoarticulos')
                                ->with('articulo_estados')->with('articulo_archivos')->with('articulo_cuentacontables')
                                ->with('articulo_proveedores.proveedores')
                                ->with('articulo_proveedores.unidadesmedidacompra');

        if (config('app.empresa') === 'FRASLE') {
            $query = $query->with('tipoproductos')->with('capacidades')->with('colores')->with('tipoliquidos');
        }

        if (null == $articulo = $query->first()) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $articulo;
    }

   	public function findPorSku($codigo)
    {
        $query = $this->model->where('sku', $codigo)
            ->with('categorias')
            ->with('unidadesdemedidas')
            ->with('unidadesdemedidasalternativas')
            ->with('impuestos')
            ->with('subcategorias')
            ->with('lineas')
            ->with('mventas');

        if (config('app.empresa') === 'FRASLE') {
            $query = $query->with('tipoproductos')->with('capacidades')->with('colores')->with('tipoliquidos');
        }

        return $query->first();
    }

	public function leeArticulo($filtros, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => ArticuloListadoFiltros::MODO_TODOS,
                'campo' => 'descripcion',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
                'estado' => ArticuloListadoFiltros::ESTADO_ACTIVO,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = ArticuloListadoFiltros::filtrosVacios();
        } elseif (! array_key_exists('estado', $filtros)) {
            $filtros['estado'] = ArticuloListadoFiltros::ESTADO_ACTIVO;
        }

        $articulo = $this->model->select(
                                'articulo.id as id',
                                'articulo.sku as codigoarticulo',
                                'articulo.codigobarra as codigobarra',
                                'articulo.descripcion as descripcion',
                                'unidadmedida.nombre as nombreunidadmedida',
                                'categoria.nombre as nombrecategoria',
                                'tipoarticulo.nombre as nombretipoarticulo',
                                'usoarticulo.nombre as nombreusoarticulo',
                                'articulo.numeroparte as numeroparte',
                                'articulo.ubicacionparte as ubicacionparte',
                                'articulo.depositoentrega_id as depositoentrega_id',
                                'articulo.nofactura',
                                'articulo.fl_precio_promedio_transferencia',
                                'articulo.estado as estado')
                                ->leftJoin('categoria', 'articulo.categoria_id', '=', 'categoria.id')
                                ->leftJoin('unidadmedida', 'articulo.unidadmedida_id', '=', 'unidadmedida.id')
                                ->leftJoin('tipoarticulo', 'articulo.tipoarticulo_id', '=', 'tipoarticulo.id')
                                ->leftJoin('usoarticulo', 'articulo.usoarticulo_id', '=', 'usoarticulo.id')
                                ->orderby('articulo.sku', 'asc');

        ArticuloListadoFiltros::aplicar($articulo, $filtros);

        if (isset($flPaginando)) {
            if ($flPaginando) {
                $articulo = $articulo->paginate(10);
            } else {
                $articulo = $articulo->get();
            }
        } else {
            $articulo = $articulo->get();
        }

        return $articulo;
    }

    public function leeColores()
    {
        return Color::orderBy('nombre')->get();
    }

    public function leeTipoliquidos()
    {
        return Tipoliquido::orderBy('nombre')->get();
    }
}

