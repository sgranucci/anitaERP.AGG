<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Precarga_Comprobante_Proveedor_Concepto;
use App\Services\Compras\PrecargaComprobanteAnitaSyncService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

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

        $linea = $precarga_comprobante_proveedor_concepto;
        DB::afterCommit(function () use ($linea, $data) {
            $this->anitaSync->insertConcepto($linea, $data);
        });

        return $precarga_comprobante_proveedor_concepto;
    }

    public function update(array $data, $id)
    {
        $linea = $this->model->findOrFail($id);
        $linea->update($data);

        $preccId = (int) $id;
        $precargaId = (int) $linea->precarga_comprobante_proveedor_id;
        $payloadAnita = array_merge($data, ['concepto_ivacompra_id' => $linea->concepto_ivacompra_id]);
        DB::afterCommit(function () use ($preccId, $precargaId, $payloadAnita) {
            $this->anitaSync->updateConcepto($preccId, $precargaId, $payloadAnita);
        });

        return $linea;
    }

    public function delete($id)
    {
        Precarga_Comprobante_Proveedor_Concepto::find($id);

        $preccId = (int) $id;
        $destroyed = $this->model->destroy($id);

        DB::afterCommit(function () use ($preccId) {
            $this->anitaSync->deleteConcepto($preccId);
        });

        return $destroyed;
    }

    public function deletePorPrecargaComprobanteProveedor($id)
    {
        $precargaId = (int) $id;
        Precarga_Comprobante_Proveedor_Concepto::where('precarga_comprobante_proveedor_id', $id)->delete();

        DB::afterCommit(function () use ($precargaId) {
            $this->anitaSync->deleteConceptosPorPrecarga($precargaId);
        });
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
