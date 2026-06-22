<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Queries\Compras\ProveedorQueryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

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
            'comprobante_proveedores',
            'monedas',
            'proveedores',
        ])->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $proveedor_cuentacorriente;
    }

    public function findOrFail($id)
    {
        return $this->model->with([
            'comprobante_proveedores',
            'monedas',
            'proveedores',
        ])->findOrFail($id);
    }

    public function findPorComprobanteProveedor(int $comprobanteProveedorId)
    {
        return $this->model->where('comprobante_proveedor_id', $comprobanteProveedorId)
            ->with(['monedas', 'comprobante_proveedores'])
            ->get();
    }

    public function listarCuentaCorriente($busqueda, $proveedor_id)
    {
        $proveedor = $this->proveedorQuery->traeProveedorporId($proveedor_id);

        $apiAnita = new ApiAnita();
        $leeAnita = [
            'acc' => 'list',
            'sistema' => 'compras',
            'tabla' => 'promov, promae, emprmae, t_comp',
            'campos' => '
                prov_tipo as tipo,
                prov_letra as letra,
                prov_sucursal as sucursal,
                prov_nro as numero,
                prov_fecha as fecha,
                prov_fecha_vto as fechavencimiento,
                prom_nombre as nombreproveedor,
                prom_cuit as cuit,
                prov_empresa as empresa_id,
                empm_nombre as nombreempresa,
                prov_cotizacion as cotizacion,
                prov_cod_mon as codigomoneda,
                prov_monto as total,
                prov_t_pagado as totalpagado,
                prov_nro_interno as numerointerno,
                prov_nro_cuota as numerocuota,
                tcomp_oper as signo,
                0 as saldo
            ',
            'whereArmado' => " WHERE
                prov_proveedor='".str_pad($proveedor->codigo, 6, '0', STR_PAD_LEFT)."' and
                prov_proveedor=prom_proveedor and
                prov_empresa=empm_empresa and
                prov_tipo=tcomp_clave",
            'orderBy' => 'prov_fecha, prov_tipo, prov_letra, prov_sucursal, prov_nro',
        ];
        $cuentacorriente = json_decode($apiAnita->apiCall($leeAnita));

        $saldo = 0;
        for ($i = 0; $i < count($cuentacorriente); $i++) {
            $coeficiente = calculaCoeficienteMoneda(1, $cuentacorriente[$i]->codigomoneda, $cuentacorriente[$i]->cotizacion);

            if ($cuentacorriente[$i]->signo == 'S') {
                $saldo += ($cuentacorriente[$i]->total * $coeficiente);
            }

            if ($cuentacorriente[$i]->signo == 'R') {
                $saldo -= ($cuentacorriente[$i]->total * $coeficiente);
            }

            $cuentacorriente[$i]->saldo = $saldo;
        }

        return $cuentacorriente;
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

    public function consultarAplicacion($proveedor_cuentacorriente_id, $comprobante, $codigoproveedor)
    {
        $campos = explode(' ', $comprobante);
        $tipo = $campos[0];
        $letra = substr($campos[1], 0, 1);

        $resto = substr($campos[1], 1);

        $comprobante = explode('-', $resto);
        $sucursal = $comprobante[0];
        $numero = $comprobante[1];

        $apiAnita = new ApiAnita();
        $leeAnita = [
            'acc' => 'list',
            'sistema' => 'compras',
            'tabla' => 'aplmovp, promae',
            'campos' => '
                aplvp_nro_interno as cuentacorriente_id,
                0 as id,
                aplvp_fecha as fechaaplicacion,'.
                ($proveedor_cuentacorriente_id == 0 ?
                '
                aplvp_tipo as tipoaplicado,
                aplvp_letra as letraaplicado,
                aplvp_sucursal as sucursalaplicado,
                aplvp_nro as numeroaplicado,'
                :
                '
                aplvp_ref_tipo as tipoaplicado,
                aplvp_ref_letra as letraaplicado,
                aplvp_ref_sucursal as sucursalaplicado,
                aplvp_ref_nro as numeroaplicado,').
                '
                aplvp_monto as total,
                aplvp_cod_mon as moneda_id,
                aplvp_cotizacion as cotizacion
            ',
            'whereArmado' => " WHERE
                aplvp_proveedor=prom_proveedor and
                aplvp_proveedor='".str_pad($codigoproveedor, 6, '0', STR_PAD_LEFT)."' and ".
                ($proveedor_cuentacorriente_id == 0 ?
                "aplvp_tipo_cob='".$tipo."' and
                aplvp_letra_cob='".$letra."' and
                aplvp_sucursal_cob='".$sucursal."' and
                aplvp_nro_cob='".$numero."' "
                :
                "aplvp_tipo='".$tipo."' and
                aplvp_letra='".$letra."' and
                aplvp_sucursal='".$sucursal."' and
                aplvp_nro='".$numero."' "),
        ];

        return json_decode($apiAnita->apiCall($leeAnita));
    }
}
