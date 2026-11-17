<?php

declare(strict_types=1);

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\ComprobanteProveedorFechaContableSupport;
use App\Support\Compras\ComprobanteProveedorModoCarga;
use App\Support\Compras\ComprobanteProveedorOrigenEntrada;
use App\Support\Compras\ComprobanteProveedorPagoSupport;
use App\Support\Compras\ComprobanteProveedorReversoContableSupport;
use App\Support\Compras\ProveedorCuentacorrienteGrillaSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Reverso contable de una factura de compra en período cerrado (o sin reeditar):
 * NC compensatoria en mes abierto + aplicación en cuenta corriente.
 */
final class ComprobanteProveedorReversoContableService
{
    public function __construct(
        private readonly ComprobanteProveedorPersistenciaService $persistenciaService,
        private readonly ComprobanteProveedorContabilizarService $contabilizarService,
        private readonly ProveedorCuentacorrienteAplicacionService $aplicacionService,
        private readonly ComprobanteProveedorEliminarService $eliminarService,
    ) {}

    /**
     * @return array{
     *   origen_id:int,
     *   nc_id:int,
     *   nc_etiqueta:string,
     *   asiento_id:int,
     *   aplicaciones:int,
     *   monto:float,
     *   mensaje:string
     * }
     */
    public function revertir(int $comprobanteId, ?string $fechaContable = null): array
    {
        $origen = Comprobante_Proveedor::query()
            ->with([
                'tipotransaccion_compras',
                'comprobante_proveedor_conceptos.concepto_ivacompras',
                'comprobante_proveedor_debe_gastos',
                'proveedores',
            ])
            ->findOrFail($comprobanteId);

        ComprobanteProveedorReversoContableSupport::assertPuedeRevertir($origen);

        $tipoNc = ComprobanteProveedorReversoContableSupport::resolverTipoNc();
        $fecha = $this->resolverFechaContable($origen, $fechaContable);
        $request = $this->armarRequestNc($origen, $tipoNc, $fecha);

        $nc = $this->persistenciaService->crearDesdeRequest($request);
        $ncId = (int) $nc->id;

        try {
            $this->contabilizarService->contabilizar($ncId);
            $nc = Comprobante_Proveedor::query()
                ->with('tipotransaccion_compras')
                ->findOrFail($ncId);

            if ((int) ($nc->asiento_id ?? 0) <= 0) {
                throw new RuntimeException(
                    'La NC se contabilizó pero no generó asiento. Revisá el tipo '
                    .((string) ($tipoNc->abreviatura ?? 'NC')).' (asientocontable).'
                );
            }

            $aplicacion = $this->aplicarNcSobreFactura($origen, $nc, $fecha);
        } catch (\Throwable $e) {
            $this->intentarLimpiarNcFallida($ncId);
            throw $e;
        }

        $etiqueta = sprintf(
            '%s %s-%04d-%d',
            (string) ($nc->tipotransaccion_compras?->abreviatura ?? 'NC'),
            (string) ($nc->letra ?? ''),
            (int) ($nc->sucursal ?? 0),
            (int) ($nc->numerocomprobante ?? 0)
        );

        return [
            'origen_id' => (int) $origen->id,
            'nc_id' => $ncId,
            'nc_etiqueta' => $etiqueta,
            'asiento_id' => (int) $nc->asiento_id,
            'aplicaciones' => (int) ($aplicacion['aplicadas'] ?? 0),
            'monto' => (float) ($aplicacion['monto'] ?? 0),
            'mensaje' => 'Reverso contable OK: se creó '.$etiqueta
                .' (asiento #'.(int) $nc->asiento_id.') y se aplicó a la factura #'.$origen->id.'.',
        ];
    }

    /**
     * Resumen para el modal de confirmación.
     *
     * @return array{
     *   origen_id:int,
     *   etiqueta:string,
     *   total:float,
     *   fecha_contable:string,
     *   tipo_nc:string,
     *   conceptos:int
     * }
     */
    public function previsualizar(int $comprobanteId, ?string $fechaContable = null): array
    {
        $origen = Comprobante_Proveedor::query()
            ->with(['tipotransaccion_compras', 'comprobante_proveedor_conceptos'])
            ->findOrFail($comprobanteId);

        ComprobanteProveedorReversoContableSupport::assertPuedeRevertir($origen);
        $tipoNc = ComprobanteProveedorReversoContableSupport::resolverTipoNc();
        $fecha = $this->resolverFechaContable($origen, $fechaContable);

        $tipo = strtoupper(trim((string) ($origen->tipotransaccion_compras?->abreviatura ?? 'FAC')));
        $etiqueta = sprintf(
            '%s %s-%04d-%d',
            $tipo,
            (string) ($origen->letra ?? ''),
            (int) ($origen->sucursal ?? 0),
            (int) ($origen->numerocomprobante ?? 0)
        );

        $conceptos = ($origen->comprobante_proveedor_conceptos ?? collect())->filter(
            static fn ($l) => (int) ($l->concepto_ivacompra_id ?? 0) > 0
                && abs((float) ($l->monto ?? 0)) >= 0.0001
        )->count();

        return [
            'origen_id' => (int) $origen->id,
            'etiqueta' => $etiqueta,
            'total' => round((float) ($origen->total ?? 0), 2),
            'fecha_contable' => $fecha,
            'tipo_nc' => (string) ($tipoNc->abreviatura ?? ''),
            'conceptos' => $conceptos,
        ];
    }

    private function resolverFechaContable(Comprobante_Proveedor $origen, ?string $fechaContable): string
    {
        $hoy = ComprobanteProveedorFechaContableSupport::fechaCargaHoy();
        $pedida = ComprobanteProveedorFechaContableSupport::formatear($fechaContable) ?? $hoy;
        if ($pedida > $hoy) {
            $pedida = $hoy;
        }

        $min = ComprobanteProveedorFechaContableSupport::fechaMinimaOperable((int) $origen->empresa_id);
        if ($min !== null && $pedida < $min) {
            $pedida = $min <= $hoy ? $min : $hoy;
        }

        ComprobanteProveedorFechaContableSupport::assertPeriodoContablePermitido(
            (int) $origen->empresa_id,
            $pedida
        );

        return $pedida;
    }

    private function armarRequestNc(
        Comprobante_Proveedor $origen,
        Tipotransaccion_Compra $tipoNc,
        string $fecha,
    ): Request {
        $cuit = (string) ($origen->identificacion_proveedor_cuit ?? '');
        $numero = $this->proximoNumeroInterno((int) $tipoNc->id, $cuit);

        $conceptoIds = [];
        $montos = [];
        $cuentas = [];
        $centros = [];
        foreach ($origen->comprobante_proveedor_conceptos->sortBy('orden') as $linea) {
            $conceptoId = (int) ($linea->concepto_ivacompra_id ?? 0);
            $monto = round((float) ($linea->monto ?? 0), 4);
            if ($conceptoId <= 0 || abs($monto) < 0.0001) {
                continue;
            }
            $conceptoIds[] = $conceptoId;
            $montos[] = $monto;
            $cuentas[] = (int) ($linea->cuentacontabledebe_id ?? 0) ?: null;
            $centros[] = (int) ($linea->centrocosto_id ?? 0) ?: null;
        }

        if ($conceptoIds === []) {
            throw new RuntimeException('No hay conceptos para clonar.');
        }

        $payload = [
            'empresa_id' => (int) $origen->empresa_id,
            'proveedor_id' => (int) $origen->proveedor_id,
            'tipotransaccion_compra_id' => (int) $tipoNc->id,
            'letra' => ComprobanteProveedorReversoContableSupport::LETRA_INTERNA,
            'sucursal' => ComprobanteProveedorReversoContableSupport::SUCURSAL_INTERNA,
            'numerocomprobante' => $numero,
            'fechacomprobante' => $fecha,
            'fechaiva' => $fecha,
            'fechavencimiento' => $fecha,
            'subtotal' => (float) ($origen->subtotal ?? $origen->total ?? 0),
            'total' => (float) ($origen->total ?? 0),
            'moneda_id' => (int) ($origen->moneda_id ?? 1),
            'cotizacion' => (float) ($origen->cotizacion ?? 1),
            'leyenda' => ComprobanteProveedorReversoContableSupport::leyendaPara($origen),
            'modo_carga' => ComprobanteProveedorModoCarga::SIN_RECEPCION,
            'origen_entrada' => ComprobanteProveedorOrigenEntrada::MANUAL,
            'es_fce' => false,
            'pararevisar' => false,
            'concepto_ivacompra_ids' => $conceptoIds,
            'montos' => $montos,
            'cuentacontabledebe_ids' => $cuentas,
            'concepto_centrocosto_ids' => $centros,
            'cuota_numero' => [1],
            'cuota_fechavencimiento' => [$fecha],
            'cuota_monto' => [(float) ($origen->total ?? 0)],
            'cuota_moneda_id' => [(int) ($origen->moneda_id ?? 1)],
            'cuota_cotizacion' => [(float) ($origen->cotizacion ?? 1)],
        ];

        if ((int) ($origen->provincia_destino_id ?? 0) > 0) {
            $payload['provincia_destino_id'] = (int) $origen->provincia_destino_id;
        }

        $debeCuentas = [];
        $debeImportes = [];
        $debeCentros = [];
        foreach ($origen->comprobante_proveedor_debe_gastos->sortBy('orden') as $dg) {
            $cuentaId = (int) ($dg->cuentacontable_id ?? 0);
            $importe = round((float) ($dg->importe ?? 0), 4);
            if ($cuentaId <= 0 || abs($importe) < 0.0001) {
                continue;
            }
            $debeCuentas[] = $cuentaId;
            $debeImportes[] = $importe;
            $debeCentros[] = (int) ($dg->centrocosto_id ?? 0) ?: null;
        }
        if ($debeCuentas !== []) {
            $payload['debe_gasto_cuenta_ids'] = $debeCuentas;
            $payload['debe_gasto_importes'] = $debeImportes;
            $payload['debe_gasto_centrocosto_ids'] = $debeCentros;
        }

        return Request::create('/compras/comprobante-proveedor/reverso-contable', 'POST', $payload);
    }

    /**
     * @return array{aplicadas:int, monto:float}
     */
    private function aplicarNcSobreFactura(
        Comprobante_Proveedor $origen,
        Comprobante_Proveedor $nc,
        string $fecha,
    ): array {
        $creditoIds = ComprobanteProveedorPagoSupport::idsCuentacorrienteDeuda((int) $nc->id);
        $deudaIds = ComprobanteProveedorPagoSupport::idsCuentacorrienteDeuda((int) $origen->id);
        if ($creditoIds === [] || $deudaIds === []) {
            throw new RuntimeException(
                'No se encontraron movimientos de cuenta corriente para aplicar la NC a la factura.'
            );
        }

        $todos = array_values(array_unique(array_merge($creditoIds, $deudaIds)));
        $ccs = Proveedor_Cuentacorriente::query()
            ->whereIn('id', $todos)
            ->get()
            ->keyBy('id');

        $aplicados = DB::table('proveedor_cuentacorriente_aplicacion')
            ->whereIn('proveedor_cuentacorriente_id', $todos)
            ->selectRaw('proveedor_cuentacorriente_id, SUM(total) as aplicado')
            ->groupBy('proveedor_cuentacorriente_id')
            ->pluck('aplicado', 'proveedor_cuentacorriente_id');

        $creditos = [];
        foreach ($creditoIds as $id) {
            $cc = $ccs->get($id);
            if ($cc === null) {
                continue;
            }
            $saldo = ProveedorCuentacorrienteGrillaSupport::saldoPendienteAbsoluto(
                (float) $cc->total,
                isset($aplicados[$id]) ? (float) $aplicados[$id] : 0.0
            );
            if ($saldo < 0.01) {
                continue;
            }
            $creditos[] = ['id' => $id, 'saldo' => $saldo];
        }

        $deudas = [];
        foreach ($deudaIds as $id) {
            $cc = $ccs->get($id);
            if ($cc === null) {
                continue;
            }
            $saldo = ProveedorCuentacorrienteGrillaSupport::saldoPendienteAbsoluto(
                (float) $cc->total,
                isset($aplicados[$id]) ? (float) $aplicados[$id] : 0.0
            );
            if ($saldo < 0.01) {
                continue;
            }
            $deudas[] = ['id' => $id, 'saldo' => $saldo];
        }

        if ($creditos === [] || $deudas === []) {
            throw new RuntimeException('No hay saldo abierto para aplicar entre la NC y la factura.');
        }

        $lineas = [];
        $ci = 0;
        $di = 0;
        while ($ci < count($creditos) && $di < count($deudas)) {
            $toma = round(min($creditos[$ci]['saldo'], $deudas[$di]['saldo']), 4);
            if ($toma < 0.01) {
                break;
            }
            $lineas[] = [
                'credito_id' => $creditos[$ci]['id'],
                'deuda_id' => $deudas[$di]['id'],
                'monto' => $toma,
            ];
            $creditos[$ci]['saldo'] = round($creditos[$ci]['saldo'] - $toma, 4);
            $deudas[$di]['saldo'] = round($deudas[$di]['saldo'] - $toma, 4);
            if ($creditos[$ci]['saldo'] < 0.01) {
                $ci++;
            }
            if ($deudas[$di]['saldo'] < 0.01) {
                $di++;
            }
        }

        if ($lineas === []) {
            throw new RuntimeException('No se armaron líneas de aplicación CC.');
        }

        $resultado = $this->aplicacionService->aplicar(
            (int) $origen->proveedor_id,
            $fecha,
            $lineas
        );

        return [
            'aplicadas' => (int) ($resultado['aplicadas'] ?? 0),
            'monto' => (float) ($resultado['monto'] ?? 0),
        ];
    }

    private function proximoNumeroInterno(int $tipoId, string $cuit): int
    {
        $letra = ComprobanteProveedorReversoContableSupport::LETRA_INTERNA;
        $sucursal = ComprobanteProveedorReversoContableSupport::SUCURSAL_INTERNA;

        $max = (int) Comprobante_Proveedor::query()
            ->where('tipotransaccion_compra_id', $tipoId)
            ->where('letra', $letra)
            ->where('sucursal', $sucursal)
            ->max('numerocomprobante');

        $numero = max(1, $max + 1);
        while (Comprobante_Proveedor::query()
            ->where('tipotransaccion_compra_id', $tipoId)
            ->where('letra', $letra)
            ->where('sucursal', $sucursal)
            ->where('numerocomprobante', $numero)
            ->when($cuit !== '', fn ($q) => $q->where('identificacion_proveedor_cuit', $cuit))
            ->exists()) {
            $numero++;
        }

        return $numero;
    }

    private function intentarLimpiarNcFallida(int $ncId): void
    {
        try {
            $nc = Comprobante_Proveedor::query()->find($ncId);
            if ($nc === null) {
                return;
            }
            if ((string) $nc->estado === ComprobanteProveedorEstados::CONTABILIZADO) {
                $this->contabilizarService->descontabilizarSinPagos($ncId);
            }
            $this->eliminarService->eliminar($ncId, false);
        } catch (\Throwable) {
            // La NC queda para revisión manual; el error original se relanza.
        }
    }
}
