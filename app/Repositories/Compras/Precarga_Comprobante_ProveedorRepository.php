<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\PrecargaComprobanteAnitaSyncService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class Precarga_Comprobante_ProveedorRepository implements Precarga_Comprobante_ProveedorRepositoryInterface
{
    protected $model;
    protected $empresaRepository;
    protected $anitaSync;

    public function __construct(
        Precarga_Comprobante_Proveedor $precarga_comprobante_proveedor,
        EmpresaRepositoryInterface $empresarepository,
        PrecargaComprobanteAnitaSyncService $anitaSync,
    ) {
        $this->model = $precarga_comprobante_proveedor;
        $this->empresaRepository = $empresarepository;
        $this->anitaSync = $anitaSync;
    }

    public function all()
    {
        return $this->model->orderBy('id', 'desc')->get();
    }

    public function create(array $data)
    {
        $data['pararevisar'] = $this->normalizarParaRevisar($data);

        $precarga_comprobante_proveedor = $this->model->create($data);

        $payloadAnita = $this->anitaSync->enriquecerPayloadParaAnita($data);
        $this->anitaSync->insertCabecera($precarga_comprobante_proveedor->id, $payloadAnita);

        return $precarga_comprobante_proveedor;
    }

    public function update(array $data, $id)
    {
        $data['pararevisar'] = $this->normalizarParaRevisar($data);

        $precarga_comprobante_proveedor = $this->model->findOrFail($id)
            ->update($data);

        $payloadAnita = $this->anitaSync->enriquecerPayloadParaAnita($data);
        $this->anitaSync->syncCabecera((int) $id, $payloadAnita);

        return $precarga_comprobante_proveedor;
    }

    public function delete($id)
    {
        $precarga_comprobante_proveedor = Precarga_Comprobante_Proveedor::find($id);

        $this->anitaSync->deleteCabecera((int) $id);

        $precarga_comprobante_proveedor = $this->model->destroy($id);

        return $precarga_comprobante_proveedor;
    }

    public function find($id)
    {
        $precarga_comprobante_proveedor = $this->model
            ->with([
                'empresas',
                'proveedores',
                'tipotransaccion_compras',
                'monedas',
                'precarga_comprobante_proveedor_conceptos',
            ])
            ->find($id);

        if ($precarga_comprobante_proveedor === null) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $precarga_comprobante_proveedor;
    }

    public function findOrFail($id)
    {
        return $this->find($id);
    }

    public function leePrecargaComprobanteProveedor($busqueda, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $usuario_id = Auth::user()->id;
        $empresas = $this->empresaRepository->traeEmpresasAsignadas();

        $select = ['precarga_comprobante_proveedor.id as id',
            'precarga_comprobante_proveedor.empresa_id as empresa_id',
            'precarga_comprobante_proveedor.proveedor_id as proveedor_id',
            'precarga_comprobante_proveedor.tipotransaccion_compra_id as tipotransaccion_compra_id',
            'empresa.nombre as nombreempresa',
            'proveedor.nombre as nombreproveedor',
            'tipotransaccion_compra.nombre as nombretipotransaccion_compra',
            'precarga_comprobante_proveedor.letra as letra',
            'precarga_comprobante_proveedor.sucursal as sucursal',
            'precarga_comprobante_proveedor.numerocomprobante as numerocomprobante',
            'precarga_comprobante_proveedor.fechafactura as fechafactura',
            'precarga_comprobante_proveedor.fecharecepcionemail as fecharecepcionemail',
            'precarga_comprobante_proveedor.numeroordencompra as numeroordencompra',
            'precarga_comprobante_proveedor.total as total',
            'precarga_comprobante_proveedor.estado as estado',
            'precarga_comprobante_proveedor.rutaalmacenamiento as rutaalmacenamiento',
        ];

        $precarga_comprobante_proveedors = $this->model->select($select)
            ->join('empresa', 'empresa.id', '=', 'precarga_comprobante_proveedor.empresa_id')
            ->leftjoin('proveedor', 'proveedor.id', '=', 'precarga_comprobante_proveedor.proveedor_id')
            ->join('tipotransaccion_compra', 'tipotransaccion_compra.id', '=', 'precarga_comprobante_proveedor.tipotransaccion_compra_id');

        $columns[] = ['columna' => 'precarga_comprobante_proveedor.id',
            'clausula' => 'LIKE'];
        $columns[] = ['columna' => 'empresa.nombre',
            'clausula' => 'LIKE'];
        $columns[] = ['columna' => 'proveedor.nombre',
            'clausula' => 'LIKE'];
        $columns[] = ['columna' => 'tipotransaccion_compra.nombre',
            'clausula' => 'LIKE'];
        $columns[] = ['columna' => 'precarga_comprobante_proveedor.letra',
            'clausula' => 'LIKE'];
        $columns[] = ['columna' => 'precarga_comprobante_proveedor.sucursal',
            'clausula' => 'LIKE'];
        $columns[] = ['columna' => 'precarga_comprobante_proveedor.numerocomprobante',
            'clausula' => 'LIKE'];
        $columns[] = ['columna' => 'precarga_comprobante_proveedor.numeroordencompra',
            'clausula' => 'LIKE'];
        $columns[] = ['columna' => 'precarga_comprobante_proveedor.fechafactura',
            'clausula' => 'LIKE'];
        $columns[] = ['columna' => 'precarga_comprobante_proveedor.fecharecepcionemail',
            'clausula' => 'LIKE'];
        $columns[] = ['columna' => 'precarga_comprobante_proveedor.estado',
            'clausula' => 'LIKE'];

        $count = count($columns);

        $precarga_comprobante_proveedors->whereIn('precarga_comprobante_proveedor.empresa_id', $empresas);

        $precarga_comprobante_proveedors->where(function ($query) use ($count, $busqueda, $columns, $usuario_id) {
            for ($i = 0; $i < $count; $i++) {
                if ($columns[$i]['clausula'] == 'LIKE') {
                    $query->orWhere($columns[$i]['columna'], 'LIKE', '%'.$busqueda.'%');
                } else {
                    $query->orWhere($columns[$i]['columna'], $columns[$i]['clausula'], $busqueda);
                }
            }
        });

        $precarga_comprobante_proveedors->orderBy('id', 'desc');

        if (isset($flPaginando)) {
            if ($flPaginando) {
                $precarga_comprobante_proveedors = $precarga_comprobante_proveedors->paginate(10);
            } else {
                $precarga_comprobante_proveedors = $precarga_comprobante_proveedors->get();
            }
        } else {
            $precarga_comprobante_proveedors = $precarga_comprobante_proveedors->get();
        }

        return $precarga_comprobante_proveedors;
    }

    private function normalizarParaRevisar(array $data): int
    {
        $valor = $data['pararevisar'] ?? $data['para_revisar'] ?? 0;

        if ($valor === 'PARA REVISAR' || $valor === '1' || $valor === 1 || $valor === true) {
            return 1;
        }

        return 0;
    }
}
