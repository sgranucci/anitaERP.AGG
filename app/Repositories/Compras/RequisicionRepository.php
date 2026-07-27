<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Requisicion;
use App\Support\Compras\RequisicionAnitaColisionSupport;
use App\Support\Compras\RequisicionAnitaNumeracionSupport;
use App\Support\Compras\RequisicionProvisorioSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

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
            $data['colores_id'],
            $data['talles_id'],
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
            $data['modo_stock_color_talle'],
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
        ])->with(['requisicion_articulos.articulos.unidadesdemedidasalternativas', 'requisicion_articulos.monedas',
            'requisicion_articulos.partidagastos', 'requisicion_articulos.capexs',
            'requisicion_articulos.color', 'requisicion_articulos.talle',
            'requisicion_articulos.proveedores',
            'requisicion_articulos.articulo_proveedor.unidadesmedidacompra',
            'requisicion_articulos.articulo_proveedor.proveedores',
        ])->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $req;
    }

    public function findOrFail($id)
    {
        return $this->find($id);
    }

    /**
     * Renumera un provisorio cuyo número ya existe en Anita u otra fila ERP.
     */
    public function renumerarProvisorioSiColisionaGlobal(int $id): int
    {
        $req = $this->model->findOrFail($id);
        if (! RequisicionProvisorioSupport::esEstadoProvisorio($req->estado)) {
            return (int) $req->numerorequisicion;
        }

        $actual = (int) $req->numerorequisicion;
        if (! RequisicionAnitaColisionSupport::numeroOcupadoParaNuevaAsignacion($actual, $id)) {
            return $actual;
        }

        $nuevo = RequisicionAnitaNumeracionSupport::asignarNumeroGlobalLibre($id, $actual);
        $req->update(['numerorequisicion' => $nuevo]);

        Log::info('Requisicion: provisorio renumerado (número único global)', [
            'requisicion_id' => $id,
            'anterior' => $actual,
            'nuevo' => $nuevo,
        ]);

        return $nuevo;
    }

}
