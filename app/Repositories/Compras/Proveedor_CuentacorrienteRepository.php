<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Queries\Compras\ProveedorQueryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\ProveedorCuentacorrienteAplicacionService;
use App\Support\Compras\ProveedorCuentacorrienteListadoFiltros;
use App\Support\Cuentacorriente\CuentacorrienteSaldosPorMoneda;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Proveedor_CuentacorrienteRepository implements Proveedor_CuentacorrienteRepositoryInterface
{
    protected Proveedor_Cuentacorriente $model;

    protected ProveedorQueryInterface $proveedorQuery;

    public function __construct(
        Proveedor_Cuentacorriente $proveedor_cuentacorriente,
        ProveedorQueryInterface $proveedorQuery,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->model = $proveedor_cuentacorriente;
        $this->proveedorQuery = $proveedorQuery;
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
        return (bool) $this->model->destroy($id);
    }

    public function find($id)
    {
        if (null == $proveedor_cuentacorriente = $this->model->with([
            'comprobante_proveedores.tipotransaccion_compras',
            'monedas',
            'proveedores',
            'empresas',
        ])->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $proveedor_cuentacorriente;
    }

    public function findOrFail($id)
    {
        return $this->model->with([
            'comprobante_proveedores.tipotransaccion_compras',
            'monedas',
            'proveedores',
            'empresas',
        ])->findOrFail($id);
    }

    public function findPorComprobanteProveedor(int $comprobanteProveedorId)
    {
        return $this->model->where('comprobante_proveedor_id', $comprobanteProveedorId)
            ->with(['monedas', 'comprobante_proveedores.tipotransaccion_compras'])
            ->get();
    }

    public function listarCuentaCorriente($busqueda, $proveedor_id, $paginar = true, array $filtros = [])
    {
        $filtros = $this->normalizarFiltrosListado($busqueda, $filtros);

        $query = $this->model->query()
            ->select('proveedor_cuentacorriente.*')
            ->with([
                'comprobante_proveedores.tipotransaccion_compras',
                'comprobante_proveedores.precarga_comprobante_proveedores',
                'comprobante_proveedores.comprobante_proveedor_archivos',
                'pagoproveedores',
                'monedas',
                'empresas',
            ])
            ->where('proveedor_cuentacorriente.proveedor_id', $proveedor_id);

        $this->aplicarJoinsListado($query);
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'proveedor_cuentacorriente.empresa_id');
        ProveedorCuentacorrienteListadoFiltros::aplicar($query, $filtros);

        $query->orderBy('proveedor_cuentacorriente.fecha', 'asc')
            ->orderBy('proveedor_cuentacorriente.id', 'asc');

        return $paginar ? $query->paginate(10) : $query->get();
    }

    public function listarDeudaProveedor($busqueda, $proveedor_id, $paginar = true, array $filtros = [])
    {
        $filtros = $this->normalizarFiltrosListado($busqueda, $filtros);

        $query = $this->model->query()
            ->select('proveedor_cuentacorriente.*')
            ->with([
                'comprobante_proveedores.tipotransaccion_compras',
                'comprobante_proveedores.precarga_comprobante_proveedores',
                'comprobante_proveedores.comprobante_proveedor_archivos',
                'comprobante_proveedores.ordencompras.ordencompra_articulos',
                'pagoproveedores',
                'monedas',
                'empresas',
            ])
            ->addSelect([
                'aplicado' => Proveedor_Cuentacorriente_Aplicacion::query()
                    ->selectRaw('SUM(total)')
                    ->whereColumn('proveedor_cuentacorriente_id', 'proveedor_cuentacorriente.id'),
            ])
            ->where('proveedor_cuentacorriente.proveedor_id', $proveedor_id)
            ->whereNotNull('proveedor_cuentacorriente.comprobante_proveedor_id')
            ->whereRaw(SqlDialectSupport::sqlSaldoPendienteProveedorCc());

        $this->aplicarJoinsListado($query);
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'proveedor_cuentacorriente.empresa_id');
        ProveedorCuentacorrienteListadoFiltros::aplicar($query, $filtros);

        $query->orderBy('proveedor_cuentacorriente.fecha', 'asc')
            ->orderBy('proveedor_cuentacorriente.id', 'asc');

        return $paginar ? $query->paginate(10) : $query->get();
    }

    public function calcularSaldoCuentaCorriente(int $proveedor_id, array $filtros = []): float
    {
        $query = $this->model->query()
            ->where('proveedor_cuentacorriente.proveedor_id', $proveedor_id);

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'proveedor_cuentacorriente.empresa_id');
        ProveedorCuentacorrienteListadoFiltros::aplicarEmpresa($query, $filtros);

        return (float) $query->sum('proveedor_cuentacorriente.total');
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array{moneda_id: int, abreviatura: string, saldo_cc: float}>
     */
    public function calcularSaldosPorMoneda(int $proveedor_id, array $filtros = []): array
    {
        $query = $this->model->query()
            ->selectRaw('proveedor_cuentacorriente.moneda_id as moneda_id')
            ->selectRaw('MAX(moneda.abreviatura) as abreviatura')
            ->selectRaw('SUM(proveedor_cuentacorriente.total) as saldo_cc')
            ->leftJoin('moneda', 'moneda.id', '=', 'proveedor_cuentacorriente.moneda_id')
            ->where('proveedor_cuentacorriente.proveedor_id', $proveedor_id)
            ->groupBy('proveedor_cuentacorriente.moneda_id')
            ->orderBy('proveedor_cuentacorriente.moneda_id');

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'proveedor_cuentacorriente.empresa_id');
        ProveedorCuentacorrienteListadoFiltros::aplicarEmpresa($query, $filtros);

        $saldos = [];
        foreach ($query->get() as $fila) {
            $saldos[] = [
                'moneda_id' => (int) $fila->moneda_id,
                'abreviatura' => (string) ($fila->abreviatura ?? ''),
                'saldo_cc' => (float) $fila->saldo_cc,
            ];
        }

        return $saldos;
    }

    public function calcularTotalDeudaProveedor(int $proveedor_id, array $filtros = []): float
    {
        $total = 0.0;
        foreach ($this->filasDeudaProveedor($proveedor_id, $filtros) as $fila) {
            $total += abs((float) $fila->total + (float) ($fila->aplicado ?? 0));
        }

        return $total;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array{moneda_id: int, abreviatura: string, deuda: float}>
     */
    public function calcularDeudasPorMoneda(int $proveedor_id, array $filtros = []): array
    {
        return CuentacorrienteSaldosPorMoneda::deudaDesdeFilas(
            $this->filasDeudaProveedor($proveedor_id, $filtros)
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array{moneda_id: int, abreviatura: string, saldo_cc: float, deuda: float}>
     */
    public function calcularSaldosYDeudasPorMoneda(int $proveedor_id, array $filtros = []): array
    {
        return CuentacorrienteSaldosPorMoneda::consolidar(
            $this->calcularSaldosPorMoneda($proveedor_id, $filtros),
            $this->calcularDeudasPorMoneda($proveedor_id, $filtros),
        );
    }

    public function saldoAnteriorPagina(int $proveedor_id, $primerRegistro, array $filtros = []): float
    {
        if ($primerRegistro === null) {
            return 0.0;
        }

        $query = $this->model->query()
            ->where('proveedor_cuentacorriente.proveedor_id', $proveedor_id)
            ->where(function ($q) use ($primerRegistro) {
                $q->where('proveedor_cuentacorriente.fecha', '<', $primerRegistro->fecha)
                    ->orWhere(function ($q2) use ($primerRegistro) {
                        $q2->where('proveedor_cuentacorriente.fecha', $primerRegistro->fecha)
                            ->where('proveedor_cuentacorriente.id', '<', $primerRegistro->id);
                    });
            });

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'proveedor_cuentacorriente.empresa_id');
        ProveedorCuentacorrienteListadoFiltros::aplicarEmpresa($query, $filtros);
        ProveedorCuentacorrienteListadoFiltros::aplicarMoneda($query, $filtros);

        return (float) $query->sum('proveedor_cuentacorriente.total');
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<int, float>
     */
    public function saldosAnterioresPorMoneda(int $proveedor_id, $primerRegistro, array $filtros = []): array
    {
        if ($primerRegistro === null) {
            return [];
        }

        $query = $this->model->query()
            ->selectRaw('proveedor_cuentacorriente.moneda_id as moneda_id')
            ->selectRaw('SUM(proveedor_cuentacorriente.total) as saldo')
            ->where('proveedor_cuentacorriente.proveedor_id', $proveedor_id)
            ->where(function ($q) use ($primerRegistro) {
                $q->where('proveedor_cuentacorriente.fecha', '<', $primerRegistro->fecha)
                    ->orWhere(function ($q2) use ($primerRegistro) {
                        $q2->where('proveedor_cuentacorriente.fecha', $primerRegistro->fecha)
                            ->where('proveedor_cuentacorriente.id', '<', $primerRegistro->id);
                    });
            })
            ->groupBy('proveedor_cuentacorriente.moneda_id');

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'proveedor_cuentacorriente.empresa_id');
        ProveedorCuentacorrienteListadoFiltros::aplicarEmpresa($query, $filtros);
        ProveedorCuentacorrienteListadoFiltros::aplicarMoneda($query, $filtros);

        $saldos = [];
        foreach ($query->get() as $fila) {
            $saldos[(int) $fila->moneda_id] = (float) $fila->saldo;
        }

        return $saldos;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{saldo_cc: float, deuda: float, abreviatura: string}
     */
    public function calcularEquivalentePesos(int $proveedor_id, array $filtros = []): array
    {
        $query = $this->model->query()
            ->select('proveedor_cuentacorriente.total', 'proveedor_cuentacorriente.moneda_id', 'proveedor_cuentacorriente.cotizacion')
            ->where('proveedor_cuentacorriente.proveedor_id', $proveedor_id);

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'proveedor_cuentacorriente.empresa_id');
        ProveedorCuentacorrienteListadoFiltros::aplicarEmpresa($query, $filtros);

        $filtrosDeuda = $filtros;
        unset($filtrosDeuda['moneda_id']);

        return CuentacorrienteSaldosPorMoneda::equivalenteDesdeFilas(
            $query->get(),
            $this->filasDeudaProveedor($proveedor_id, $filtrosDeuda),
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function saldoAnteriorPaginaEnPesos(int $proveedor_id, $primerRegistro, array $filtros = []): float
    {
        if ($primerRegistro === null) {
            return 0.0;
        }

        $query = $this->model->query()
            ->select('proveedor_cuentacorriente.total', 'proveedor_cuentacorriente.moneda_id', 'proveedor_cuentacorriente.cotizacion')
            ->where('proveedor_cuentacorriente.proveedor_id', $proveedor_id)
            ->where(function ($q) use ($primerRegistro) {
                $q->where('proveedor_cuentacorriente.fecha', '<', $primerRegistro->fecha)
                    ->orWhere(function ($q2) use ($primerRegistro) {
                        $q2->where('proveedor_cuentacorriente.fecha', $primerRegistro->fecha)
                            ->where('proveedor_cuentacorriente.id', '<', $primerRegistro->id);
                    });
            });

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'proveedor_cuentacorriente.empresa_id');
        ProveedorCuentacorrienteListadoFiltros::aplicarEmpresa($query, $filtros);
        ProveedorCuentacorrienteListadoFiltros::aplicarMoneda($query, $filtros);

        return CuentacorrienteSaldosPorMoneda::saldoAnteriorEnPesosDe($query->get(), $primerRegistro);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return \Illuminate\Support\Collection<int, Proveedor_Cuentacorriente>
     */
    private function filasDeudaProveedor(int $proveedor_id, array $filtros)
    {
        $query = $this->model->query()
            ->select(
                'proveedor_cuentacorriente.total',
                'proveedor_cuentacorriente.moneda_id',
                'proveedor_cuentacorriente.cotizacion',
                'moneda.abreviatura as abreviatura'
            )
            ->addSelect([
                'aplicado' => Proveedor_Cuentacorriente_Aplicacion::query()
                    ->selectRaw('SUM(total)')
                    ->whereColumn('proveedor_cuentacorriente_id', 'proveedor_cuentacorriente.id'),
            ])
            ->leftJoin('moneda', 'moneda.id', '=', 'proveedor_cuentacorriente.moneda_id')
            ->where('proveedor_cuentacorriente.proveedor_id', $proveedor_id)
            ->whereNotNull('proveedor_cuentacorriente.comprobante_proveedor_id')
            ->whereRaw(SqlDialectSupport::sqlSaldoPendienteProveedorCc());

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'proveedor_cuentacorriente.empresa_id');
        ProveedorCuentacorrienteListadoFiltros::aplicarEmpresa($query, $filtros);

        return $query->get();
    }

    /**
     * @param  Builder<\App\Models\Compras\Proveedor_Cuentacorriente>  $query
     */
    private function aplicarJoinsListado(Builder $query): void
    {
        $query->leftJoin('empresa', 'empresa.id', '=', 'proveedor_cuentacorriente.empresa_id')
            ->leftJoin('moneda', 'moneda.id', '=', 'proveedor_cuentacorriente.moneda_id')
            ->leftJoin('comprobante_proveedor', 'comprobante_proveedor.id', '=', 'proveedor_cuentacorriente.comprobante_proveedor_id')
            ->leftJoin('tipotransaccion_compra', 'tipotransaccion_compra.id', '=', 'comprobante_proveedor.tipotransaccion_compra_id')
            ->leftJoin('pagoproveedor', 'pagoproveedor.id', '=', 'proveedor_cuentacorriente.pagoproveedor_id');
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function normalizarFiltrosListado($busqueda, array $filtros): array
    {
        if ($filtros !== []) {
            return $filtros;
        }

        return ProveedorCuentacorrienteListadoFiltros::desdeBusquedaLegacy((string) $busqueda);
    }

    public function consultarDeuda($proveedor_id, $empresa_id, $comprobante_proveedor_id = null)
    {
        $cuentacorriente = $this->model->query()
            ->select(
                'comprobante_proveedor.id as idcomprobante',
                'proveedor_cuentacorriente.id as idcuentacorriente',
                'proveedor_cuentacorriente.fecha as fecha',
                'proveedor_cuentacorriente.fechavencimiento as fechavencimiento',
                'proveedor_cuentacorriente.proveedor_id as proveedor_id',
                'proveedor_cuentacorriente.total as total',
                'proveedor_cuentacorriente.moneda_id as moneda_id',
                'proveedor_cuentacorriente.cotizacion as cotizacion',
                'proveedor_cuentacorriente.empresa_id as empresa_id',
                'comprobante_proveedor.letra as letra',
                'comprobante_proveedor.sucursal as sucursal',
                'comprobante_proveedor.numerocomprobante as numerocomprobante',
                'moneda.abreviatura as abreviaturamoneda',
                'proveedor.nombre as nombreproveedor',
                'proveedor.codigo as codigoproveedor',
            )
            ->leftJoin('comprobante_proveedor', 'comprobante_proveedor.id', 'proveedor_cuentacorriente.comprobante_proveedor_id')
            ->join('moneda', 'moneda.id', 'proveedor_cuentacorriente.moneda_id')
            ->join('proveedor', 'proveedor.id', 'proveedor_cuentacorriente.proveedor_id')
            ->addSelect([
                'aplicado' => Proveedor_Cuentacorriente_Aplicacion::query()
                    ->selectRaw('SUM(total)')
                    ->whereColumn('proveedor_cuentacorriente_id', 'proveedor_cuentacorriente.id'),
            ])
            ->whereRaw(SqlDialectSupport::sqlSaldoPendienteProveedorCc());

        if ($comprobante_proveedor_id) {
            $cuentacorriente = $cuentacorriente->where('comprobante_proveedor.id', $comprobante_proveedor_id);
        } else {
            $cuentacorriente = $cuentacorriente
                ->where('proveedor_cuentacorriente.proveedor_id', $proveedor_id)
                ->where('proveedor_cuentacorriente.empresa_id', $empresa_id);
        }

        return $cuentacorriente->orderBy('fecha', 'asc')->get();
    }

    public function consultarAplicacion($proveedor_cuentacorriente_id, $comprobante = null, $codigoproveedor = null)
    {
        if ((int) $proveedor_cuentacorriente_id <= 0) {
            return collect();
        }

        return Proveedor_Cuentacorriente_Aplicacion::query()
            ->select(
                'proveedor_cuentacorriente_aplicacion.id as id',
                'proveedor_cuentacorriente_aplicacion.proveedor_cuentacorriente_id as cuentacorriente_id',
                'proveedor_cuentacorriente_aplicacion.fecha as fechaaplicacion',
                'proveedor_cuentacorriente_aplicacion.comprobanteaplicado as comprobante',
                'proveedor_cuentacorriente_aplicacion.total as total',
                'proveedor_cuentacorriente_aplicacion.moneda_id as moneda_id',
                'proveedor_cuentacorriente_aplicacion.cotizacion as cotizacion',
                'proveedor_cuentacorriente_aplicacion.proveedor_cuentacorriente_aplicado_id as aplicado_id',
            )
            ->where('proveedor_cuentacorriente_id', $proveedor_cuentacorriente_id)
            ->orderBy('fecha')
            ->get();
    }

    public function listarPendientesAplicacion(int $proveedor_id, string $lado, ?int $empresa_id = null)
    {
        $query = $this->model->query()
            ->with([
                'comprobante_proveedores.tipotransaccion_compras',
                'pagoproveedores',
                'monedas',
                'empresas',
            ])
            ->select('proveedor_cuentacorriente.*')
            ->addSelect([
                'aplicado' => Proveedor_Cuentacorriente_Aplicacion::query()
                    ->selectRaw('SUM(total)')
                    ->whereColumn('proveedor_cuentacorriente_id', 'proveedor_cuentacorriente.id'),
            ])
            ->where('proveedor_cuentacorriente.proveedor_id', $proveedor_id)
            ->whereRaw($lado === 'credito'
                ? ProveedorCuentacorrienteAplicacionService::sqlLadoCredito()
                : ProveedorCuentacorrienteAplicacionService::sqlLadoDeuda());

        if ($empresa_id !== null && $empresa_id > 0) {
            $query->where('proveedor_cuentacorriente.empresa_id', $empresa_id);
        }

        if ($lado === 'deuda') {
            $query->orderBy('proveedor_cuentacorriente.fechavencimiento', 'asc')
                ->orderBy('proveedor_cuentacorriente.fecha', 'asc')
                ->orderBy('proveedor_cuentacorriente.id', 'asc');
        } else {
            $query->orderBy('proveedor_cuentacorriente.fecha', 'asc')
                ->orderBy('proveedor_cuentacorriente.id', 'asc');
        }

        return $query->get();
    }

    public function listarAplicacionesManualesRecientes(int $proveedor_id, ?int $empresa_id = null, int $limite = 30)
    {
        $query = Proveedor_Cuentacorriente_Aplicacion::query()
            ->select('proveedor_cuentacorriente_aplicacion.*')
            ->join(
                'proveedor_cuentacorriente as cc',
                'cc.id',
                '=',
                'proveedor_cuentacorriente_aplicacion.proveedor_cuentacorriente_id'
            )
            ->where('cc.proveedor_id', $proveedor_id)
            ->whereNull('proveedor_cuentacorriente_aplicacion.pagoproveedor_id')
            ->where('proveedor_cuentacorriente_aplicacion.total', '<', 0)
            ->with([
                'proveedor_cuentacorrientes.comprobante_proveedores.tipotransaccion_compras',
                'proveedor_cuentacorrientes.pagoproveedores',
                'proveedor_cuentacorriente_aplicados.comprobante_proveedores.tipotransaccion_compras',
                'proveedor_cuentacorriente_aplicados.pagoproveedores',
                'proveedor_cuentacorriente_aplicados.monedas',
                'monedas',
            ]);

        if ($empresa_id !== null && $empresa_id > 0) {
            $query->where('proveedor_cuentacorriente_aplicacion.empresa_id', $empresa_id);
        }

        return $query->orderByDesc('proveedor_cuentacorriente_aplicacion.fecha')
            ->orderByDesc('proveedor_cuentacorriente_aplicacion.id')
            ->limit($limite)
            ->get();
    }
}
