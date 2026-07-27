<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Support\Compras\ComprobanteProveedorUnicidadSupport;
use App\Support\Compras\PrecargaComprobanteProveedorListadoFiltros;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\PrecargaComprobanteAnitaSyncService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

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

        ComprobanteProveedorUnicidadSupport::assertUnicoPrecarga(
            (int) $data['empresa_id'],
            (int) $data['tipotransaccion_compra_id'],
            (string) ($data['letra'] ?? ''),
            (int) ($data['sucursal'] ?? 0),
            (int) ($data['numerocomprobante'] ?? 0),
            (int) $data['proveedor_id'],
        );

        $data['identificacion_proveedor_cuit'] = ComprobanteProveedorUnicidadSupport::resolverCuitDigitos(
            (int) $data['proveedor_id'],
            null,
        );

        $precarga_comprobante_proveedor = $this->model->create($data);

        $precargaId = (int) $precarga_comprobante_proveedor->id;
        $payloadAnita = $this->anitaSync->enriquecerPayloadParaAnita($data);
        DB::afterCommit(function () use ($precargaId, $payloadAnita) {
            $this->anitaSync->insertCabecera($precargaId, $payloadAnita);
        });

        return $precarga_comprobante_proveedor;
    }

    public function update(array $data, $id)
    {
        $data['pararevisar'] = $this->normalizarParaRevisar($data);

        ComprobanteProveedorUnicidadSupport::assertUnicoPrecarga(
            (int) $data['empresa_id'],
            (int) $data['tipotransaccion_compra_id'],
            (string) ($data['letra'] ?? ''),
            (int) ($data['sucursal'] ?? 0),
            (int) ($data['numerocomprobante'] ?? 0),
            (int) $data['proveedor_id'],
            (int) $id,
        );

        $data['identificacion_proveedor_cuit'] = ComprobanteProveedorUnicidadSupport::resolverCuitDigitos(
            (int) $data['proveedor_id'],
            null,
        );

        $precarga_comprobante_proveedor = $this->model->findOrFail($id)
            ->update($data);

        $precargaId = (int) $id;
        $payloadAnita = $this->anitaSync->enriquecerPayloadParaAnita($data);
        DB::afterCommit(function () use ($precargaId, $payloadAnita) {
            $this->anitaSync->syncCabecera($precargaId, $payloadAnita);
        });

        return $precarga_comprobante_proveedor;
    }

    public function delete($id)
    {
        $precargaId = (int) $id;
        Precarga_Comprobante_Proveedor::find($id);

        $precarga_comprobante_proveedor = $this->model->destroy($id);

        DB::afterCommit(function () use ($precargaId) {
            $this->anitaSync->deleteCabecera($precargaId);
        });

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

    public function leePrecargaComprobanteProveedor($filtros, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => PrecargaComprobanteProveedorListadoFiltros::MODO_TODOS,
                'campo' => 'nombreproveedor',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = PrecargaComprobanteProveedorListadoFiltros::filtrosVacios();
        }

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
            'precarga_comprobante_proveedor.origen_entrada as origen_entrada',
            'precarga_comprobante_proveedor.rutaalmacenamiento as rutaalmacenamiento',
        ];

        $precarga_comprobante_proveedors = $this->model->select($select)
            ->join('empresa', 'empresa.id', '=', 'precarga_comprobante_proveedor.empresa_id')
            ->leftjoin('proveedor', 'proveedor.id', '=', 'precarga_comprobante_proveedor.proveedor_id')
            ->join('tipotransaccion_compra', 'tipotransaccion_compra.id', '=', 'precarga_comprobante_proveedor.tipotransaccion_compra_id');

        $precarga_comprobante_proveedors->whereIn('precarga_comprobante_proveedor.empresa_id', $empresas);

        if (PrecargaComprobanteProveedorListadoFiltros::tieneCriteriosAplicados($filtros)) {
            PrecargaComprobanteProveedorListadoFiltros::aplicar($precarga_comprobante_proveedors, $filtros);
        }

        $precarga_comprobante_proveedors->orderBy('precarga_comprobante_proveedor.id', 'desc');

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

    /**
     * Seguimiento acotado al proveedor del portal.
     *
     * En el MVP interno el proveedor se selecciona en pantalla; en el portal externo
     * el controller deberá obtener este ID de la sesión autenticada, nunca del request.
     */
    public function listarPortalProveedor(int $proveedorId, bool $paginar = true)
    {
        $query = $this->model
            ->with([
                'empresas:id,nombre',
                'proveedores:id,codigo,nombre,nroinscripcion',
                'tipotransaccion_compras:id,nombre,abreviatura',
                'monedas:id,nombre',
            ])
            ->where('proveedor_id', $proveedorId)
            ->whereIn('empresa_id', $this->empresaRepository->traeEmpresasAsignadas())
            ->orderByDesc('id');

        return $paginar ? $query->paginate(15) : $query->get();
    }

    public function findDuplicadoPrecarga(
        int $empresaId,
        int $proveedorId,
        int $tipotransaccionCompraId,
        string $letra,
        $sucursal,
        $numerocomprobante,
        ?int $excluirId = null
    ) {
        $cuit = ComprobanteProveedorUnicidadSupport::resolverCuitDigitos($proveedorId, null);
        if ($cuit === '') {
            return null;
        }

        return ComprobanteProveedorUnicidadSupport::findDuplicadoPrecarga(
            $empresaId,
            $tipotransaccionCompraId,
            $letra,
            (int) $sucursal,
            (int) $numerocomprobante,
            $cuit,
            $excluirId,
        );
    }

    public function mensajeFacturaDuplicada(
        Precarga_Comprobante_Proveedor $existente,
        string $tipoAbreviatura
    ): string {
        return ComprobanteProveedorUnicidadSupport::mensajeDuplicadoPrecarga($existente, $tipoAbreviatura);
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
