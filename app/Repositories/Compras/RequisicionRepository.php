<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Requisicion;
use App\Support\Compras\RequisicionAnitaNumeracionSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RequisicionRepository implements RequisicionRepositoryInterface
{
    protected $model;

    public function __construct(Requisicion $requisicion)
    {
        $this->model = $requisicion;
    }

    public function create(array $data)
    {
        $data = self::limpiaPayloadCabecera($data);
        $data['numerorequisicion'] = RequisicionAnitaNumeracionSupport::asignarNumeroGlobalLibre();

        return $this->model->create($data);
    }

    public function createDesdeAnita(array $data)
    {
        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $data = self::limpiaPayloadCabecera($data);

        return $this->model->findOrFail($id)->update($data);
    }

    private static function limpiaPayloadCabecera(array $data)
    {
        unset(
            $data['monto'],
            $data['moneda_id'],
            $data['articulo_ids'],
            $data['cantidades'],
            $data['precios'],
            $data['moneda_linea_ids'],
            $data['fechaentrega_articulos'],
            $data['cantidadalternativas'],
            $data['detalle_articulos'],
            $data['centrocostodestino_ids'],
            $data['preciooriginales'],
            $data['motivoahorros'],
            $data['partidagasto_ids'],
            $data['capex_ids'],
            $data['fechas'],
            $data['estados'],
            $data['usuario_ids'],
            $data['observacionestados'],
            $data['_token'],
            $data['_method']
        );

        return $data;
    }

    public function delete($id)
    {
        $req = $this->model->findOrFail($id);
        if ($req) {
            return $this->model->destroy($id);
        }

        return false;
    }

    public function find($id)
    {
        if (null == $req = $this->model->with(['requisicion_estados', 'requisicion_archivos',
            'empresas', 'centrocostos', 'oficinacompras', 'proveedores.condicionpagos', 'formapagos', 'usuarios',
        ])->with(['requisicion_articulos.articulos', 'requisicion_articulos.monedas',
            'requisicion_articulos.partidagastos', 'requisicion_articulos.capexs',
        ])->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $req;
    }

    public function findOrFail($id)
    {
        return $this->find($id);
    }

}
