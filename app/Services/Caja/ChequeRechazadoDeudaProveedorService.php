<?php

namespace App\Services\Caja;

use App\Models\Caja\Cheque;
use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Concepto;
use App\Models\Compras\Comprobante_Proveedor_Cuota;
use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Models\Configuracion\Provincia;
use App\Models\Configuracion\Provincia_Cuentacontableiibb;
use App\Models\Contable\Cuentacontable;
use App\Models\Ventas\Puntoventa;
use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Impuesto;
use App\Services\Compras\ComprobanteProveedorContabilizarService;
use App\Support\Compras\ComprobanteProveedorConceptoIvaTipos;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\ComprobanteProveedorModoCarga;
use App\Support\Compras\ComprobanteProveedorOrigenEntrada;
use App\Support\Compras\ComprobanteProveedorUnicidadSupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Si el cheque rechazado estaba entregado a un proveedor, arma un débito interno
 * (tipo NDR) por el total de la nota de débito del cliente: importe del cheque,
 * gastos bancarios e IVA. Queda en la cuenta corriente del proveedor para
 * aplicarlo en otra orden de pago.
 *
 * El Debe copia cada cuenta que esa nota acreditó.
 */
final class ChequeRechazadoDeudaProveedorService
{
    public function __construct(
        private readonly ComprobanteProveedorContabilizarService $contabilizarService,
    ) {}

    /**
     * @return array{
     *   comprobante_proveedor_id:int,
     *   codigo:string,
     *   proveedor_id:int,
     *   proveedor:string,
     *   total:float,
     *   contabilizado:bool,
     *   error:?string
     * }|null
     */
    public function generarSiCorresponde(Cheque $cheque, Venta $ventaNd): ?array
    {
        $proveedorId = (int) ($cheque->proveedor_id ?? 0);
        if ($proveedorId <= 0) {
            return null;
        }

        $existenteId = (int) ($cheque->comprobante_proveedor_id ?? 0);
        if ($existenteId > 0) {
            return $this->cerrarExistente($cheque, $existenteId);
        }

        $comprobante = $this->crearBorrador($cheque, $ventaNd, $proveedorId);
        $cheque->comprobante_proveedor_id = (int) $comprobante->id;
        $cheque->save();

        return $this->contabilizarYResponder($comprobante);
    }

    /**
     * @return array{
     *   comprobante_proveedor_id:int,
     *   codigo:string,
     *   proveedor_id:int,
     *   proveedor:string,
     *   total:float,
     *   contabilizado:bool,
     *   error:?string
     * }
     */
    private function cerrarExistente(Cheque $cheque, int $comprobanteId): array
    {
        $comprobante = Comprobante_Proveedor::query()->find($comprobanteId);
        if (! $comprobante) {
            throw new RuntimeException('El cheque apunta a un comprobante de proveedor que no existe.');
        }
        if ((string) $comprobante->estado === ComprobanteProveedorEstados::CONTABILIZADO) {
            return $this->respuesta($comprobante, null);
        }

        return $this->contabilizarYResponder($comprobante);
    }

    private function crearBorrador(Cheque $cheque, Venta $ventaNd, int $proveedorId): Comprobante_Proveedor
    {
        $tipo = Tipotransaccion_Compra::query()->where('abreviatura', 'NDR')->first();
        if (! $tipo) {
            throw new RuntimeException('No existe el tipo de comprobante NDR (débito por cheque rechazado).');
        }

        $proveedor = Proveedor::query()->find($proveedorId);
        if (! $proveedor) {
            throw new RuntimeException('El proveedor del cheque no existe.');
        }

        $cuit = ComprobanteProveedorUnicidadSupport::resolverCuitDigitos($proveedorId, null);
        if ($cuit === '') {
            throw new RuntimeException('El proveedor '.$proveedor->nombre.' no tiene CUIT. No se puede armar el débito interno.');
        }

        $lineas = $this->lineasDebeDesdeNotaCliente($ventaNd);
        $total = round(array_sum(array_column($lineas, 'importe')), 2);
        if ($total <= 0) {
            throw new RuntimeException('La nota de débito del cliente no tiene importe para trasladar al proveedor.');
        }

        $concepto = $this->conceptoNeto();
        $empresaId = (int) $cheque->empresa_id;
        $fecha = (string) ($cheque->fecha_rechazo ?: date('Y-m-d'));
        $monedaId = (int) ($cheque->moneda_id ?: 1);
        $cotizacion = (float) ($cheque->cotizacion ?: 1);
        if ($cotizacion <= 0) {
            $cotizacion = 1;
        }

        $numero = $this->proximoNumero($empresaId, (int) $tipo->id, $cuit);
        $leyenda = $this->leyenda($cheque, $ventaNd);

        return DB::transaction(function () use (
            $proveedor, $tipo, $cuit, $lineas, $total, $concepto,
            $empresaId, $fecha, $monedaId, $cotizacion, $numero, $leyenda, $ventaNd
        ) {
            $comprobante = Comprobante_Proveedor::query()->create([
                'empresa_id' => $empresaId,
                'proveedor_id' => (int) $proveedor->id,
                'identificacion_proveedor_cuit' => $cuit,
                'tipotransaccion_compra_id' => (int) $tipo->id,
                'provincia_destino_id' => (int) ($proveedor->provincia_id ?? 0) ?: null,
                'letra' => 'A',
                'sucursal' => 0,
                'numerocomprobante' => $numero,
                'fechacomprobante' => $fecha,
                'fechaiva' => $fecha,
                'fechavencimiento' => $fecha,
                'subtotal' => $total,
                'total' => $total,
                'moneda_id' => $monedaId,
                'cotizacion' => $cotizacion,
                'es_fce' => false,
                'leyenda' => $leyenda,
                'modo_carga' => ComprobanteProveedorModoCarga::SIN_RECEPCION,
                'origen_entrada' => ComprobanteProveedorOrigenEntrada::MANUAL,
                'estado' => ComprobanteProveedorEstados::BORRADOR,
                'pararevisar' => false,
                'creousuario_id' => (int) (Auth::id() ?: $ventaNd->usuario_id),
            ]);

            $orden = 1;
            foreach ($lineas as $linea) {
                Comprobante_Proveedor_Concepto::query()->create([
                    'comprobante_proveedor_id' => $comprobante->id,
                    'concepto_ivacompra_id' => (int) $concepto->id,
                    'orden' => $orden,
                    'monto' => $linea['importe'],
                    'cuentacontabledebe_id' => $linea['cuenta_id'],
                ]);
                $orden++;
            }

            Comprobante_Proveedor_Cuota::query()->create([
                'comprobante_proveedor_id' => $comprobante->id,
                'numero_cuota' => 1,
                'fechavencimiento' => $fecha,
                'monto' => $total,
                'moneda_id' => $monedaId,
                'cotizacion' => $cotizacion,
                'formapago_id' => 1,
                'total_pagado' => 0,
            ]);

            return $comprobante;
        });
    }

    /**
     * Cada cuenta al Haber de la ND del cliente (cheque, gastos e IVA) es un Debe
     * del débito al proveedor. El total coincide con el de la nota.
     *
     * @return list<array{cuenta_id:int, importe:float}>
     */
    private function lineasDebeDesdeNotaCliente(Venta $ventaNd): array
    {
        $asientoId = (int) (Asiento::query()->where('venta_id', $ventaNd->id)->value('id') ?? 0);
        if ($asientoId <= 0) {
            throw new RuntimeException('La nota de débito del cliente no tiene asiento. No se puede armar la deuda del proveedor.');
        }

        $lineas = [];
        $movimientos = Asiento_Movimiento::query()->where('asiento_id', $asientoId)->get(['cuentacontable_id', 'monto']);
        foreach ($movimientos as $movimiento) {
            $monto = round((float) $movimiento->monto, 2);
            if ($monto >= -0.009) {
                continue;
            }
            $cuentaId = (int) $movimiento->cuentacontable_id;
            if ($cuentaId <= 0) {
                continue;
            }
            $lineas[] = [
                'cuenta_id' => $cuentaId,
                'importe' => round(abs($monto), 2),
            ];
        }

        if ($lineas === []) {
            throw new RuntimeException('El asiento de la nota de débito no tiene contrapartida para el proveedor.');
        }

        $suma = round(array_sum(array_column($lineas, 'importe')), 2);
        $totalNd = round(abs((float) $ventaNd->total), 2);
        if ($totalNd - $suma > 0.05) {
            $lineas = array_merge($lineas, $this->lineasPercepcionFueraDeAsiento($ventaNd, $totalNd - $suma));
            $suma = round(array_sum(array_column($lineas, 'importe')), 2);
        }
        if (abs($suma - $totalNd) > 0.05) {
            throw new RuntimeException(
                'El asiento de la nota de débito ('.$suma.') no cierra con el total '.$totalNd.'. No se armó la deuda del proveedor.'
            );
        }

        return $lineas;
    }

    /**
     * Percepciones del pie que no entraron al asiento de la nota (p. ej. IIBB).
     *
     * @return list<array{cuenta_id:int, importe:float}>
     */
    private function lineasPercepcionFueraDeAsiento(Venta $ventaNd, float $faltante): array
    {
        $empresaId = (int) (Puntoventa::query()->whereKey((int) $ventaNd->puntoventa_id)->value('empresa_id') ?? 0);
        $lineas = [];
        $cubierto = 0.0;
        $percepciones = Venta_Impuesto::query()
            ->where('venta_id', $ventaNd->id)
            ->where('concepto', 'like', 'Perc.%')
            ->get();

        foreach ($percepciones as $percepcion) {
            $importe = round((float) $percepcion->importe, 2);
            $provinciaId = (int) ($percepcion->provincia_id ?? 0);
            if ($importe <= 0.009 || $provinciaId <= 0) {
                continue;
            }
            $cuentaId = (int) (Provincia_Cuentacontableiibb::query()
                ->where('empresa_id', $empresaId)
                ->where('provincia_id', $provinciaId)
                ->value('cuentacontable_id') ?? 0);
            if ($cuentaId <= 0) {
                $cuentaId = $this->cuentaPercepcionIibbCliente($empresaId, $provinciaId);
            }
            if ($cuentaId <= 0) {
                throw new RuntimeException(
                    'Falta la cuenta de percepción IIBB de la provincia '.$provinciaId.' para sumar '.$importe.' al débito del proveedor.'
                );
            }
            $lineas[] = [
                'cuenta_id' => $cuentaId,
                'importe' => $importe,
            ];
            $cubierto = round($cubierto + $importe, 2);
        }

        if (abs($cubierto - round($faltante, 2)) > 0.05) {
            throw new RuntimeException(
                'La nota de débito tiene '.$faltante.' fuera del asiento y las percepciones suman '.$cubierto.'.'
            );
        }

        return $lineas;
    }

    /**
     * El maestro provincia_cuentacontableiibb puede venir vacío. CABA (901)
     * usa la cuenta de percepción IIBB clientes del plan.
     */
    private function cuentaPercepcionIibbCliente(int $empresaId, int $provinciaId): int
    {
        $jurisdiccion = (string) (Provincia::query()->whereKey($provinciaId)->value('jurisdiccion') ?? '');
        if ($jurisdiccion !== '901') {
            return 0;
        }

        return (int) (Cuentacontable::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', '213100016')
            ->value('id') ?? 0);
    }

    /**
     * Si la NDR del ERP quedó solo por el nominal, la reabre y la vuelve a
     * contabilizar por el total de la nota del cliente.
     *
     * @return array{
     *   comprobante_proveedor_id:int,
     *   codigo:string,
     *   proveedor_id:int,
     *   proveedor:string,
     *   total:float,
     *   contabilizado:bool,
     *   error:?string
     * }|null
     */
    public function realinearConNotaCliente(Cheque $cheque): ?array
    {
        $comprobanteId = (int) ($cheque->comprobante_proveedor_id ?? 0);
        $ventaNdId = (int) ($cheque->venta_nd_id ?? 0);
        if ($comprobanteId <= 0 || $ventaNdId <= 0) {
            return null;
        }

        $ventaNd = Venta::query()->find($ventaNdId);
        $comprobante = Comprobante_Proveedor::query()->find($comprobanteId);
        if (! $ventaNd || ! $comprobante) {
            throw new RuntimeException('No se encontró la nota del cliente o el débito del proveedor.');
        }

        $totalNd = round(abs((float) $ventaNd->total), 2);
        if (abs(round((float) $comprobante->total, 2) - $totalNd) <= 0.05
            && (string) $comprobante->estado === ComprobanteProveedorEstados::CONTABILIZADO) {
            return $this->respuesta($comprobante, null);
        }

        if ((string) $comprobante->estado === ComprobanteProveedorEstados::CONTABILIZADO) {
            $this->contabilizarService->descontabilizarSinPagos($comprobanteId);
            $comprobante = $comprobante->fresh();
        }

        $lineas = $this->lineasDebeDesdeNotaCliente($ventaNd);
        $total = round(array_sum(array_column($lineas, 'importe')), 2);
        $concepto = $this->conceptoNeto();

        DB::transaction(function () use ($comprobante, $lineas, $total, $concepto) {
            EloquentAuditDeleteSupport::each(
                Comprobante_Proveedor_Concepto::query()->where('comprobante_proveedor_id', $comprobante->id)
            );

            $orden = 1;
            foreach ($lineas as $linea) {
                Comprobante_Proveedor_Concepto::query()->create([
                    'comprobante_proveedor_id' => $comprobante->id,
                    'concepto_ivacompra_id' => (int) $concepto->id,
                    'orden' => $orden,
                    'monto' => $linea['importe'],
                    'cuentacontabledebe_id' => $linea['cuenta_id'],
                ]);
                $orden++;
            }

            $comprobante->forceFill([
                'subtotal' => $total,
                'total' => $total,
            ])->save();

            $cuota = Comprobante_Proveedor_Cuota::query()
                ->where('comprobante_proveedor_id', $comprobante->id)
                ->orderBy('numero_cuota')
                ->first();
            if ($cuota) {
                $cuota->forceFill(['monto' => $total])->save();
            }
        });

        return $this->contabilizarYResponder($comprobante->fresh());
    }

    private function conceptoNeto(): Concepto_Ivacompra
    {
        $concepto = Concepto_Ivacompra::query()
            ->where('codigo', '1')
            ->whereIn('tipoconcepto', ComprobanteProveedorConceptoIvaTipos::NETO)
            ->orderBy('id')
            ->first();

        if (! $concepto) {
            $concepto = Concepto_Ivacompra::query()
                ->where('tipoconcepto', 'N')
                ->whereNotIn('codigo', ComprobanteProveedorConceptoIvaTipos::CODIGOS_IMPUESTO_INTERNO)
                ->orderBy('id')
                ->first();
        }

        if (! $concepto) {
            throw new RuntimeException('No hay un concepto de IVA compra no gravado para el débito interno del cheque rechazado.');
        }

        return $concepto;
    }

    private function proximoNumero(int $empresaId, int $tipoId, string $cuit): int
    {
        $max = (int) Comprobante_Proveedor::query()
            ->where('empresa_id', $empresaId)
            ->where('tipotransaccion_compra_id', $tipoId)
            ->where('letra', 'A')
            ->where('sucursal', 0)
            ->max('numerocomprobante');

        $numero = $max + 1;
        $ocupado = Comprobante_Proveedor::query()
            ->where('empresa_id', $empresaId)
            ->where('tipotransaccion_compra_id', $tipoId)
            ->where('letra', 'A')
            ->where('sucursal', 0)
            ->where('numerocomprobante', $numero)
            ->where('identificacion_proveedor_cuit', $cuit)
            ->exists();
        if ($ocupado) {
            $numero++;
        }

        return $numero;
    }

    private function leyenda(Cheque $cheque, Venta $ventaNd): string
    {
        $partes = ['Cheque rechazado'];
        $nro = trim((string) ($cheque->numerocheque ?? ''));
        if ($nro !== '') {
            $partes[] = 'N° '.$nro;
        }
        if ($cheque->nro_interno_anita) {
            $partes[] = 'int. '.$cheque->nro_interno_anita;
        }
        $codigo = trim((string) ($ventaNd->codigo ?? ''));
        if ($codigo !== '') {
            $partes[] = 'ND cliente '.$codigo;
        }
        $texto = implode(' — ', $partes);

        return mb_strlen($texto) > 255 ? mb_substr($texto, 0, 255) : $texto;
    }

    /**
     * @return array{
     *   comprobante_proveedor_id:int,
     *   codigo:string,
     *   proveedor_id:int,
     *   proveedor:string,
     *   total:float,
     *   contabilizado:bool,
     *   error:?string
     * }
     */
    private function contabilizarYResponder(Comprobante_Proveedor $comprobante): array
    {
        try {
            $contabilizado = $this->contabilizarService->contabilizar((int) $comprobante->id);

            return $this->respuesta($contabilizado, null);
        } catch (\Throwable $e) {
            return $this->respuesta($comprobante->fresh() ?? $comprobante, $e->getMessage());
        }
    }

    /**
     * @return array{
     *   comprobante_proveedor_id:int,
     *   codigo:string,
     *   proveedor_id:int,
     *   proveedor:string,
     *   total:float,
     *   contabilizado:bool,
     *   error:?string
     * }
     */
    private function respuesta(Comprobante_Proveedor $comprobante, ?string $error): array
    {
        $comprobante->loadMissing(['tipotransaccion_compras', 'proveedores']);
        $abrev = (string) ($comprobante->tipotransaccion_compras->abreviatura ?? 'NDR');
        $codigo = $abrev.' '.$comprobante->letra.'-'.$comprobante->sucursal.'-'.$comprobante->numerocomprobante;

        return [
            'comprobante_proveedor_id' => (int) $comprobante->id,
            'codigo' => $codigo,
            'proveedor_id' => (int) $comprobante->proveedor_id,
            'proveedor' => (string) ($comprobante->proveedores->nombre ?? ''),
            'total' => round(abs((float) $comprobante->total), 2),
            'contabilizado' => (string) $comprobante->estado === ComprobanteProveedorEstados::CONTABILIZADO && $error === null,
            'error' => $error,
        ];
    }
}
