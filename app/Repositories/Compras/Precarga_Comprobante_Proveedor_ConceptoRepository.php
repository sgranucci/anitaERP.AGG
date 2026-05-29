<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Precarga_Comprobante_Proveedor_Concepto;
use App\Services\Compras\PrecargaComprobanteAnitaSyncService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Precarga_Comprobante_Proveedor_ConceptoRepository implements Precarga_Comprobante_Proveedor_ConceptoRepositoryInterface
{
    protected $model;
    protected $anitaSync;

    public function __construct(
        Precarga_Comprobante_Proveedor_Concepto $precarga_comprobante_proveedor_concepto,
        PrecargaComprobanteAnitaSyncService $anitaSync,
    ) {
        $this->model = $precarga_comprobante_proveedor_concepto;
        $this->anitaSync = $anitaSync;
    }

    public function all()
    {
        return $this->model->orderBy('id', 'ASC')->get();
    }

    public function create(array $data)
    {
        $precarga_comprobante_proveedor_concepto = $this->model->create($data);

        $this->anitaSync->insertConcepto($precarga_comprobante_proveedor_concepto, $data);

        return $precarga_comprobante_proveedor_concepto;
    }

    public function update(array $data, $id)
    {
        $linea = $this->model->findOrFail($id);
        $linea->update($data);

        $this->anitaSync->updateConcepto(
            (int) $id,
            (int) $linea->precarga_comprobante_proveedor_id,
            array_merge($data, ['concepto_ivacompra_id' => $linea->concepto_ivacompra_id])
        );

        return $linea;
    }

    public function delete($id)
    {
        $precarga_comprobante_proveedor_concepto = Precarga_Comprobante_Proveedor_Concepto::find($id);

        $this->anitaSync->deleteConcepto((int) $id);

        return $this->model->destroy($id);
    }

    public function deletePorPrecargaComprobanteProveedor($id)
    {
        Precarga_Comprobante_Proveedor_Concepto::where('precarga_comprobante_proveedor_id', $id)->delete();

        $this->anitaSync->deleteConceptosPorPrecarga((int) $id);
    }

    public function find($id)
    {
        if (null == $precarga_comprobante_proveedor_concepto = $this->model->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $precarga_comprobante_proveedor_concepto;
    }

    public function findOrFail($id)
    {
        if (null == $precarga_comprobante_proveedor_concepto = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $precarga_comprobante_proveedor_concepto;
    }
}
