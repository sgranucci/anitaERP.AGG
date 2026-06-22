<?php

namespace App\Repositories\Stock;

use App\Models\Stock\MovimientoStock;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Stock\MovimientoStockListadoFiltros;
use App\Support\Stock\MovimientoStockListadoFila;
use App\Support\Stock\MovimientoStockListadoUnificadoSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class MovimientoStockRepository implements MovimientoStockRepositoryInterface
{
    private MovimientoStockListadoUnificadoSupport $listadoUnificado;

    public function __construct(
        private readonly MovimientoStock $model,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->listadoUnificado = new MovimientoStockListadoUnificadoSupport($this->empresaRepository);
    }

    public function estadoEnum()
    {
        return $this->model->estadoEnum();
    }

    public function latest($campo)
    {
        return $this->model->latest($campo)->first();
    }

    public function all()
    {
        return $this->leeMovimientoStock(MovimientoStockListadoFiltros::filtrosVacios(), false);
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return LengthAwarePaginator<int, MovimientoStockListadoFila>|Collection<int, MovimientoStockListadoFila>
     */
    public function leeMovimientoStock($filtros, bool $paginar = false)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'empresa_id' => 0,
                'deposito_id' => 0,
                'modo' => MovimientoStockListadoFiltros::MODO_TODOS,
                'campo' => 'codigo',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = MovimientoStockListadoFiltros::filtrosVacios();
        }

        $resultado = $this->listadoUnificado->listar($filtros, $paginar);

        if ($resultado instanceof LengthAwarePaginator) {
            return $resultado->appends(MovimientoStockListadoFiltros::paraQueryString($filtros));
        }

        return $resultado;
    }

    public function find($id)
    {
        if (null == $movimientostock = $this->model->where('id', $id)->with('articulos_movimiento')->with('tipotransaccion_stock')->first()) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $movimientostock;
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
        $movimientostock = $this->model->destroy($id);

        return $movimientostock;
    }

    public function deletePorId($id)
    {
        return $this->model->where('id', $id)->delete();
    }
}
