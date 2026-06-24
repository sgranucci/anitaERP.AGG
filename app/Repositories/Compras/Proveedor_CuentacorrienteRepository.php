<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Proveedor_CuentacorrienteRepository implements Proveedor_CuentacorrienteRepositoryInterface
{
    protected Proveedor_Cuentacorriente $model;

    protected ProveedorQueryInterface $proveedorQuery;

    public function __construct(
        Proveedor_Cuentacorriente $proveedor_cuentacorriente,
        ProveedorQueryInterface $proveedorQuery,
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

    public function listarCuentaCorriente($busqueda, $proveedor_id, $paginar = true)
    {
        $busqueda = trim((string) $busqueda);

        $query = $this->model->query()
            ->with([
                'comprobante_proveedores.tipotransaccion_compras',
                'monedas',
                'empresas',
            ])
            ->where('proveedor_cuentacorriente.proveedor_id', $proveedor_id)
            ->whereNull('proveedor_cuentacorriente.deleted_at');

        if ($busqueda !== '') {
            $query->where(function ($q) use ($busqueda) {
                $q->where('proveedor_cuentacorriente.fecha', 'like', "%{$busqueda}%")
                    ->orWhere('proveedor_cuentacorriente.fechavencimiento', 'like', "%{$busqueda}%")
                    ->orWhereHas('monedas', function ($monedaQuery) use ($busqueda) {
                        $monedaQuery->where('abreviatura', 'like', "%{$busqueda}%");
                    })
                    ->orWhereHas('comprobante_proveedores', function ($comprobanteQuery) use ($busqueda) {
                        $comprobanteQuery->where('letra', 'like', "%{$busqueda}%")
                            ->orWhere('sucursal', 'like', "%{$busqueda}%")
                            ->orWhere('numerocomprobante', 'like', "%{$busqueda}%");
                    })
                    ->orWhereHas('comprobante_proveedores.tipotransaccion_compras', function ($tipoQuery) use ($busqueda) {
                        $tipoQuery->where('nombre', 'like', "%{$busqueda}%");
                    });
            });
        }

        $query->orderBy('proveedor_cuentacorriente.fecha', 'asc')
            ->orderBy('proveedor_cuentacorriente.id', 'asc');

        return $paginar ? $query->paginate(10) : $query->get();
    }

    public function listarDeudaProveedor($busqueda, $proveedor_id, $paginar = true)
    {
        $busqueda = trim((string) $busqueda);

        $query = $this->model->query()
            ->with([
                'comprobante_proveedores.tipotransaccion_compras',
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
            ->whereNull('proveedor_cuentacorriente.deleted_at')
            ->whereNotNull('proveedor_cuentacorriente.comprobante_proveedor_id')
            ->havingRaw('abs(IFNULL(aplicado, 0)) < abs(proveedor_cuentacorriente.total)');

        if ($busqueda !== '') {
            $query->where(function ($q) use ($busqueda) {
                $q->where('proveedor_cuentacorriente.fecha', 'like', "%{$busqueda}%")
                    ->orWhere('proveedor_cuentacorriente.fechavencimiento', 'like', "%{$busqueda}%")
                    ->orWhereHas('monedas', function ($monedaQuery) use ($busqueda) {
                        $monedaQuery->where('abreviatura', 'like', "%{$busqueda}%");
                    })
                    ->orWhereHas('comprobante_proveedores', function ($comprobanteQuery) use ($busqueda) {
                        $comprobanteQuery->where('letra', 'like', "%{$busqueda}%")
                            ->orWhere('sucursal', 'like', "%{$busqueda}%")
                            ->orWhere('numerocomprobante', 'like', "%{$busqueda}%");
                    })
                    ->orWhereHas('comprobante_proveedores.tipotransaccion_compras', function ($tipoQuery) use ($busqueda) {
                        $tipoQuery->where('nombre', 'like', "%{$busqueda}%");
                    });
            });
        }

        $query->orderBy('proveedor_cuentacorriente.fecha', 'asc')
            ->orderBy('proveedor_cuentacorriente.id', 'asc');

        return $paginar ? $query->paginate(10) : $query->get();
    }

    public function calcularSaldoCuentaCorriente(int $proveedor_id): float
    {
        return (float) $this->model->query()
            ->where('proveedor_id', $proveedor_id)
            ->whereNull('deleted_at')
            ->sum('total');
    }

    public function calcularTotalDeudaProveedor(int $proveedor_id): float
    {
        $filas = $this->model->query()
            ->select('proveedor_cuentacorriente.total')
            ->addSelect([
                'aplicado' => Proveedor_Cuentacorriente_Aplicacion::query()
                    ->selectRaw('SUM(total)')
                    ->whereColumn('proveedor_cuentacorriente_id', 'proveedor_cuentacorriente.id'),
            ])
            ->where('proveedor_cuentacorriente.proveedor_id', $proveedor_id)
            ->whereNull('proveedor_cuentacorriente.deleted_at')
            ->whereNotNull('proveedor_cuentacorriente.comprobante_proveedor_id')
            ->havingRaw('abs(IFNULL(aplicado, 0)) < abs(proveedor_cuentacorriente.total)')
            ->get();

        $total = 0.0;
        foreach ($filas as $fila) {
            $total += abs((float) $fila->total + (float) ($fila->aplicado ?? 0));
        }

        return $total;
    }

    public function saldoAnteriorPagina(int $proveedor_id, $primerRegistro): float
    {
        if ($primerRegistro === null) {
            return 0.0;
        }

        return (float) $this->model->query()
            ->where('proveedor_id', $proveedor_id)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($primerRegistro) {
                $q->where('fecha', '<', $primerRegistro->fecha)
                    ->orWhere(function ($q2) use ($primerRegistro) {
                        $q2->where('fecha', $primerRegistro->fecha)
                            ->where('id', '<', $primerRegistro->id);
                    });
            })
            ->sum('total');
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
            ->havingRaw('abs(aplicado) < abs(proveedor_cuentacorriente.total) or aplicado is null')
            ->whereNull('proveedor_cuentacorriente.deleted_at');

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
}
