<?php

namespace App\Repositories\Ventas;

use App\Support\Cuentacorriente\CuentacorrienteSaldosPorMoneda;
use App\Support\Database\SqlDialectSupport;
use App\Models\Ventas\Cliente_Cuentacorriente;
use App\Models\Ventas\Cliente_Cuentacorriente_Aplicacion;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Carbon\Carbon;
use Auth;

class Cliente_CuentacorrienteRepository implements Cliente_CuentacorrienteRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cliente_Cuentacorriente $cliente_cuentacorriente)
    {
        $this->model = $cliente_cuentacorriente;
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $cliente_cuentacorriente = $this->model->findOrFail($id)->update($data);

		return $cliente_cuentacorriente;
    }

    public function delete($id)
    {
    	$cliente_cuentacorriente = $this->model->destroy($id);

		return $cliente_cuentacorriente;
    }

    public function find($id)
    {
        if (null == $cliente_cuentacorriente = $this->model->with('ventas')->with('monedas')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente_cuentacorriente;
    }

    public function findOrFail($id)
    {
        if (null == $cliente_cuentacorriente = $this->model->with('ventas')->with('monedas')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente_cuentacorriente;
    }

    public function findPorVenta($venta_id)
    {
        return $this->model->where('venta_id', $venta_id)->with('ventas')->with('monedas')->get();
    }

    public function buscaPorVentaCobranza($venta_id, $cobranza_id)
    {
        if (!isset($venta_id))
            return $this->model->where('venta_id', null)
                    ->where('cobranza_id', $cobranza_id)->with('ventas')->with('monedas')->get();
        else
            return $this->model->where('venta_id', $venta_id)
                    ->where('cobranza_id', $cobranza_id)->with('ventas')->with('monedas')->get();
    }

    public function listarCuentaCorriente($busqueda, $cliente_id, $paginar = true, ?int $monedaId = null)
    {
        $busqueda = trim((string) $busqueda);

        $query = $this->model->query()
            ->with(['ventas', 'cobranzas', 'monedas', 'empresas'])
            ->where('cliente_cuentacorriente.cliente_id', $cliente_id);

        $this->aplicarFiltroMoneda($query, $monedaId);

        if ($busqueda !== '') {
            $query->where(function ($q) use ($busqueda) {
                $q->where('cliente_cuentacorriente.fecha', 'like', "%{$busqueda}%")
                    ->orWhere('cliente_cuentacorriente.fechavencimiento', 'like', "%{$busqueda}%")
                    ->orWhereHas('monedas', function ($monedaQuery) use ($busqueda) {
                        $monedaQuery->where('abreviatura', 'like', "%{$busqueda}%");
                    })
                    ->orWhereHas('ventas', function ($ventaQuery) use ($busqueda) {
                        $ventaQuery->where('codigo', 'like', "%{$busqueda}%");
                    })
                    ->orWhereHas('cobranzas', function ($cobranzaQuery) use ($busqueda) {
                        $cobranzaQuery->where('detalle', 'like', "%{$busqueda}%");
                    });
            });
        }

        $query->orderBy('cliente_cuentacorriente.fecha', 'asc')
            ->orderBy('cliente_cuentacorriente.id', 'asc');

        return $paginar ? $query->paginate(10) : $query->get();
    }

    public function listarDeudaCliente($busqueda, $cliente_id, $paginar = true, ?int $monedaId = null)
    {
        $busqueda = trim((string) $busqueda);

        $query = $this->model->query()
            ->with(['ventas', 'monedas', 'empresas'])
            ->select('cliente_cuentacorriente.*')
            ->addSelect([
                'aplicado' => Cliente_Cuentacorriente_Aplicacion::query()
                    ->selectRaw('SUM(total)')
                    ->whereColumn('cliente_cuentacorriente_id', 'cliente_cuentacorriente.id'),
            ])
            ->where('cliente_cuentacorriente.cliente_id', $cliente_id)
            ->whereNotNull('cliente_cuentacorriente.venta_id')
            ->whereNull('cliente_cuentacorriente.cobranza_id')
            ->whereRaw(SqlDialectSupport::sqlSaldoPendienteClienteCc());

        $this->aplicarFiltroMoneda($query, $monedaId);

        if ($busqueda !== '') {
            $query->where(function ($q) use ($busqueda) {
                $q->where('cliente_cuentacorriente.fecha', 'like', "%{$busqueda}%")
                    ->orWhere('cliente_cuentacorriente.fechavencimiento', 'like', "%{$busqueda}%")
                    ->orWhereHas('monedas', function ($monedaQuery) use ($busqueda) {
                        $monedaQuery->where('abreviatura', 'like', "%{$busqueda}%");
                    })
                    ->orWhereHas('ventas', function ($ventaQuery) use ($busqueda) {
                        $ventaQuery->where('codigo', 'like', "%{$busqueda}%");
                    });
            });
        }

        $query->orderBy('cliente_cuentacorriente.fecha', 'asc')
            ->orderBy('cliente_cuentacorriente.id', 'asc');

        return $paginar ? $query->paginate(10) : $query->get();
    }

    public function calcularSaldoCuentaCorriente(int $cliente_id): float
    {
        return (float) $this->model->query()
            ->where('cliente_id', $cliente_id)
            ->sum('total');
    }

    /**
     * @return list<array{moneda_id: int, abreviatura: string, saldo_cc: float}>
     */
    public function calcularSaldosPorMoneda(int $cliente_id): array
    {
        $filas = $this->model->query()
            ->selectRaw('cliente_cuentacorriente.moneda_id as moneda_id')
            ->selectRaw('MAX(moneda.abreviatura) as abreviatura')
            ->selectRaw('SUM(cliente_cuentacorriente.total) as saldo_cc')
            ->leftJoin('moneda', 'moneda.id', '=', 'cliente_cuentacorriente.moneda_id')
            ->where('cliente_cuentacorriente.cliente_id', $cliente_id)
            ->groupBy('cliente_cuentacorriente.moneda_id')
            ->orderBy('cliente_cuentacorriente.moneda_id')
            ->get();

        $saldos = [];
        foreach ($filas as $fila) {
            $saldos[] = [
                'moneda_id' => (int) $fila->moneda_id,
                'abreviatura' => (string) ($fila->abreviatura ?? ''),
                'saldo_cc' => (float) $fila->saldo_cc,
            ];
        }

        return $saldos;
    }

    public function calcularTotalDeudaCliente(int $cliente_id): float
    {
        $total = 0.0;
        foreach ($this->filasDeudaCliente($cliente_id) as $fila) {
            $total += abs((float) $fila->total + (float) ($fila->aplicado ?? 0));
        }

        return $total;
    }

    /**
     * @return list<array{moneda_id: int, abreviatura: string, deuda: float}>
     */
    public function calcularDeudasPorMoneda(int $cliente_id): array
    {
        return CuentacorrienteSaldosPorMoneda::deudaDesdeFilas($this->filasDeudaCliente($cliente_id));
    }

    /**
     * @return list<array{moneda_id: int, abreviatura: string, saldo_cc: float, deuda: float}>
     */
    public function calcularSaldosYDeudasPorMoneda(int $cliente_id): array
    {
        return CuentacorrienteSaldosPorMoneda::consolidar(
            $this->calcularSaldosPorMoneda($cliente_id),
            $this->calcularDeudasPorMoneda($cliente_id),
        );
    }

    public function saldoAnteriorPagina(int $cliente_id, $primerRegistro, ?int $monedaId = null): float
    {
        if ($primerRegistro === null) {
            return 0.0;
        }

        $query = $this->model->query()
            ->where('cliente_id', $cliente_id)
            ->where(function ($q) use ($primerRegistro) {
                $q->where('fecha', '<', $primerRegistro->fecha)
                    ->orWhere(function ($q2) use ($primerRegistro) {
                        $q2->where('fecha', $primerRegistro->fecha)
                            ->where('id', '<', $primerRegistro->id);
                    });
            });

        $this->aplicarFiltroMoneda($query, $monedaId);

        return (float) $query->sum('total');
    }

    /**
     * @return array<int, float>
     */
    public function saldosAnterioresPorMoneda(int $cliente_id, $primerRegistro, ?int $monedaId = null): array
    {
        if ($primerRegistro === null) {
            return [];
        }

        $query = $this->model->query()
            ->selectRaw('cliente_cuentacorriente.moneda_id as moneda_id')
            ->selectRaw('SUM(cliente_cuentacorriente.total) as saldo')
            ->where('cliente_cuentacorriente.cliente_id', $cliente_id)
            ->where(function ($q) use ($primerRegistro) {
                $q->where('cliente_cuentacorriente.fecha', '<', $primerRegistro->fecha)
                    ->orWhere(function ($q2) use ($primerRegistro) {
                        $q2->where('cliente_cuentacorriente.fecha', $primerRegistro->fecha)
                            ->where('cliente_cuentacorriente.id', '<', $primerRegistro->id);
                    });
            })
            ->groupBy('cliente_cuentacorriente.moneda_id');

        $this->aplicarFiltroMoneda($query, $monedaId);

        $saldos = [];
        foreach ($query->get() as $fila) {
            $saldos[(int) $fila->moneda_id] = (float) $fila->saldo;
        }

        return $saldos;
    }

    /**
     * @return array{saldo_cc: float, deuda: float, abreviatura: string}
     */
    public function calcularEquivalentePesos(int $cliente_id, ?int $monedaId = null): array
    {
        $movimientos = $this->model->query()
            ->select('cliente_cuentacorriente.total', 'cliente_cuentacorriente.moneda_id', 'cliente_cuentacorriente.cotizacion')
            ->where('cliente_cuentacorriente.cliente_id', $cliente_id);

        $this->aplicarFiltroMoneda($movimientos, $monedaId);

        return CuentacorrienteSaldosPorMoneda::equivalenteDesdeFilas(
            $movimientos->get(),
            $this->filasDeudaCliente($cliente_id, $monedaId),
        );
    }

    public function saldoAnteriorPaginaEnPesos(int $cliente_id, $primerRegistro, ?int $monedaId = null): float
    {
        if ($primerRegistro === null) {
            return 0.0;
        }

        $query = $this->model->query()
            ->select('cliente_cuentacorriente.total', 'cliente_cuentacorriente.moneda_id', 'cliente_cuentacorriente.cotizacion')
            ->where('cliente_cuentacorriente.cliente_id', $cliente_id)
            ->where(function ($q) use ($primerRegistro) {
                $q->where('cliente_cuentacorriente.fecha', '<', $primerRegistro->fecha)
                    ->orWhere(function ($q2) use ($primerRegistro) {
                        $q2->where('cliente_cuentacorriente.fecha', $primerRegistro->fecha)
                            ->where('cliente_cuentacorriente.id', '<', $primerRegistro->id);
                    });
            });

        $this->aplicarFiltroMoneda($query, $monedaId);

        return CuentacorrienteSaldosPorMoneda::saldoAnteriorEnPesosDe($query->get(), $primerRegistro, $monedaId);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Cliente_Cuentacorriente>
     */
    private function filasDeudaCliente(int $cliente_id, ?int $monedaId = null)
    {
        $query = $this->model->query()
            ->select(
                'cliente_cuentacorriente.total',
                'cliente_cuentacorriente.moneda_id',
                'cliente_cuentacorriente.cotizacion',
                'moneda.abreviatura as abreviatura'
            )
            ->addSelect([
                'aplicado' => Cliente_Cuentacorriente_Aplicacion::query()
                    ->selectRaw('SUM(total)')
                    ->whereColumn('cliente_cuentacorriente_id', 'cliente_cuentacorriente.id'),
            ])
            ->leftJoin('moneda', 'moneda.id', '=', 'cliente_cuentacorriente.moneda_id')
            ->where('cliente_cuentacorriente.cliente_id', $cliente_id)
            ->whereNotNull('cliente_cuentacorriente.venta_id')
            ->whereNull('cliente_cuentacorriente.cobranza_id')
            ->whereRaw(SqlDialectSupport::sqlSaldoPendienteClienteCc());

        $this->aplicarFiltroMoneda($query, $monedaId);

        return $query->get();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Ventas\Cliente_Cuentacorriente>  $query
     */
    private function aplicarFiltroMoneda($query, ?int $monedaId): void
    {
        if ($monedaId !== null && $monedaId > 0) {
            $query->where('cliente_cuentacorriente.moneda_id', $monedaId);
        }
    }

    public function consultarDeuda($cliente_id, $empresa_id, $venta_id = null)
    {
        $cuentacorriente = $this->model->select('venta.id as idventa',
                                                'cliente_cuentacorriente.id as idcuentacorriente',
                                                'cliente_cuentacorriente.fecha as fecha',
                                                'cliente_cuentacorriente.fechavencimiento as fechavencimiento',
                                                'cliente_cuentacorriente.cliente_id as cliente_id',
                                                'cliente_cuentacorriente.total as total',
                                                'cliente_cuentacorriente.moneda_id as moneda_id',
                                                'cliente_cuentacorriente.cotizacion as cotizacion',
                                                'cliente_cuentacorriente.empresa_id as empresa_id',
                                                'cliente_cuentacorriente.cobranza_id as cobranza_id',
                                                'venta.codigo as codigo',
                                                'puntoventa.empresa_id as empresa_id',
                                                'moneda.abreviatura as abreviaturamoneda',
                                                'cliente.nombre as nombrecliente',
                                                'cliente.codigo as codigocliente'
                                                )
                                                ->leftJoin('venta', 'venta.id', 'cliente_cuentacorriente.venta_id')
                                                ->join('puntoventa', 'puntoventa.id', 'venta.puntoventa_id')
                                                ->join('moneda', 'moneda.id', 'cliente_cuentacorriente.moneda_id')
                                                ->join('cliente', 'cliente.id', 'venta.cliente_id')
                                                ->addSelect([
                                                    'aplicado' => Cliente_Cuentacorriente_Aplicacion::query()
                                                        ->selectRaw('SUM(total)')
                                                        ->whereColumn('cliente_cuentacorriente_id', 'cliente_cuentacorriente.id')
                                                ])
                                                ->whereRaw(SqlDialectSupport::sqlSaldoPendienteClienteCc());
        if (isset($venta_id))
            $cuentacorriente = $cuentacorriente->where('venta.id', $venta_id);
        else
            $cuentacorriente = $cuentacorriente->where('cliente_cuentacorriente.cliente_id', $cliente_id)
                                                ->where('puntoventa.empresa_id', $empresa_id);
        
        $cuentacorriente = $cuentacorriente->orderBy('fecha', 'asc')->get();

        return $cuentacorriente;
    }

    public function consultarAplicacion($cliente_cuentacorriente_id)
    {
        $cuentacorriente = $this->model->select('cliente_cuentacorriente.id as cuentacorriente_id',
                                                'cliente_cuentacorriente_aplicacion.id as id',
                                                'cliente_cuentacorriente_aplicacion.fecha as fechaaplicacion',
                                                'cliente_cuentacorriente_aplicacion.comprobanteaplicado as comprobante',
                                                'cliente_cuentacorriente_aplicacion.total as total',
                                                'cliente_cuentacorriente_aplicacion.moneda_id as moneda_id',
                                                'cliente_cuentacorriente_aplicacion.cotizacion as cotizacion',
                                                'cliente_cuentacorriente_aplicacion.ventaaplicado_id as ventaaplicado_id',
                                                'cliente_cuentacorriente_aplicacion.cobranza_id as cobranza_id',
                                                'cliente_cuentacorriente_aplicacion.cliente_cuentacorriente_aplicado_id as aplicado_id'
                                                )
                                                ->join('cliente_cuentacorriente_aplicacion', 'cliente_cuentacorriente_aplicacion.cliente_cuentacorriente_id', 'cliente_cuentacorriente.id')
                                                ->where('cliente_cuentacorriente_aplicacion.cliente_cuentacorriente_id', $cliente_cuentacorriente_id)
                                                ->orderBy('cliente_cuentacorriente_aplicacion.fecha')
                                                ->get();

        return $cuentacorriente;
    }
}

