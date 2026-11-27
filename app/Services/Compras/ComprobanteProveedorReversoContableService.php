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
use App\Support\Compras\ComprobanteProveedorReversoAsientoEspejoSupport;
use App\Support\Compras\ComprobanteProveedorReversoContableSupport;
use App\Support\Compras\ComprobanteProveedorReversoFiscalSupport;
use App\Support\Compras\ProveedorCuentacorrienteGrillaSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Reverso contable de una factura de compra en período cerrado (o sin reeditar):
 * NC interna en mes abierto cuyo asiento es el espejo del de la factura.
 *
 * - Factura sin pagos: la NC se aplica en cuenta corriente a la factura.
 * - Factura pagada en total: la NC queda como crédito abierto del proveedor (a cuenta).
 *
 * El comprobante de la NC es exento / no gravado: el IVA se da vuelta solo en el asiento.
 * La NC interna (subdiario N) no va al libro de IVA compras ni a IVA Digital.
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
     *   modo:string,
     *   aplicaciones:int,
     *   monto:float,
     *   mensaje:string
     * }
     */
    public function revertir(int $comprobanteId, ?string $fechaContable = null): array
    {
        $origen = Comprobante_Proveedor::query()
            ->with(['tipotransaccion_compras', 'proveedores'])
            ->findOrFail($comprobanteId);

        $modo = ComprobanteProveedorReversoContableSupport::assertPuedeRevertir($origen);

        $tipoNc = ComprobanteProveedorReversoContableSupport::resolverTipoNc();
        $fecha = $this->resolverFechaContable($origen, $fechaContable);
        $request = $this->armarRequestNc($origen, $tipoNc, $fecha);

        return $this->ejecutarReverso($origen, $modo, $request, $fecha);
    }

    /**
     * Reverso fiscal: NC (origen factura/ND) o ND (origen NC) de tipo fiscal, que entra al IVA
     * compras y al Libro IVA Digital del período. El asiento sigue siendo el espejo.
     *
     * @param  string  $tratamiento  copiar | no_computable (ver ComprobanteProveedorReversoFiscalSupport)
     *
     * @return array<string, mixed> mismo formato que revertir()
     */
    public function revertirFiscal(
        int $comprobanteId,
        string $tipoAbrev,
        string $tratamiento,
        ?string $fechaContable = null,
    ): array {
        $origen = Comprobante_Proveedor::query()
            ->with(['tipotransaccion_compras', 'proveedores'])
            ->findOrFail($comprobanteId);

        $modo = ComprobanteProveedorReversoFiscalSupport::modo($origen);
        $tipo = ComprobanteProveedorReversoFiscalSupport::resolverTipo($tipoAbrev, $origen);
        $conceptos = ComprobanteProveedorReversoFiscalSupport::conceptosNc($origen, $tratamiento);
        ComprobanteProveedorReversoFiscalSupport::assertConceptosPermitidosPorTipo($tipo, $conceptos);

        $fecha = $this->resolverFechaContable($origen, $fechaContable);
        $numero = ComprobanteProveedorReversoFiscalSupport::proximoNumero(
            $origen,
            strtoupper(substr(trim((string) $origen->letra), 0, 1)),
            (int) $origen->sucursal,
        );

        $request = $this->armarRequestFiscal($origen, $tipo, $numero, $fecha, $conceptos);

        return $this->ejecutarReverso($origen, $modo, $request, $fecha);
    }

    /**
     * Simulación de solo lectura del reverso fiscal: no crea comprobante, asiento ni aplicación.
     *
     * @param  array<string, int>  $reservados  numeración ya simulada en esta corrida
     * @return array<string, mixed>
     */
    public function previsualizarFiscal(
        int $comprobanteId,
        string $tipoAbrev,
        string $tratamiento,
        ?string $fechaContable,
        array &$reservados,
    ): array {
        $origen = Comprobante_Proveedor::query()
            ->with(['tipotransaccion_compras', 'proveedores'])
            ->findOrFail($comprobanteId);

        $errores = [];
        $modo = '';
        try {
            $modo = ComprobanteProveedorReversoFiscalSupport::modo($origen);
        } catch (\Throwable $e) {
            $errores[] = 'Origen: '.$e->getMessage();
        }

        $tipo = null;
        try {
            $tipo = ComprobanteProveedorReversoFiscalSupport::resolverTipo($tipoAbrev, $origen);
        } catch (\Throwable $e) {
            $errores[] = 'Tipo: '.$e->getMessage();
        }

        $conceptos = [];
        try {
            $conceptos = ComprobanteProveedorReversoFiscalSupport::conceptosNc($origen, $tratamiento);
            if ($tipo !== null) {
                ComprobanteProveedorReversoFiscalSupport::assertConceptosPermitidosPorTipo($tipo, $conceptos);
            }
        } catch (\Throwable $e) {
            $errores[] = 'Conceptos: '.$e->getMessage();
        }

        $fecha = $this->fechaContableSimulada($origen, $fechaContable);

        $letra = strtoupper(substr(trim((string) $origen->letra), 0, 1));
        $sucursal = (int) $origen->sucursal;
        $numero = ComprobanteProveedorReversoFiscalSupport::proximoNumero($origen, $letra, $sucursal, $reservados);

        $total = round(array_sum(array_column($conceptos, 'monto')), 2);
        if (abs($total - round((float) $origen->total, 2)) > 0.01) {
            $errores[] = 'La suma de conceptos ('.number_format($total, 2, ',', '.')
                .') no coincide con el total del original ('.number_format((float) $origen->total, 2, ',', '.').').';
        }

        $conceptosInfo = [];
        if ($conceptos !== []) {
            $nombres = DB::table('concepto_ivacompra')
                ->whereIn('id', array_column($conceptos, 'concepto_ivacompra_id'))
                ->get(['id', 'codigo', 'nombre'])
                ->keyBy('id');
            foreach ($conceptos as $c) {
                $n = $nombres->get($c['concepto_ivacompra_id']);
                $conceptosInfo[] = [
                    'codigo' => (string) ($n->codigo ?? ''),
                    'nombre' => (string) ($n->nombre ?? ''),
                    'monto' => (float) $c['monto'],
                ];
            }
        }

        $informable = $tipo !== null && ComprobanteProveedorReversoFiscalSupport::informableIvaCompras($tipo, $letra);
        if ($tipo !== null && ! $informable) {
            $errores[] = 'El tipo '.$tipo->abreviatura.' no entra al IVA compras (letra '.$letra.').';
        }

        $espejo = [];
        try {
            if ((int) $origen->asiento_id > 0) {
                $espejo = ComprobanteProveedorReversoAsientoEspejoSupport::lineasParaPreview((int) $origen->asiento_id);
            } else {
                $errores[] = 'Asiento: el original no tiene asiento contable para dar vuelta.';
            }
        } catch (\Throwable $e) {
            $errores[] = 'Asiento: '.$e->getMessage();
        }

        $periodoOk = true;
        try {
            ComprobanteProveedorFechaContableSupport::assertPeriodoContablePermitido(
                (int) $origen->empresa_id,
                $fecha
            );
        } catch (\Throwable $e) {
            $periodoOk = false;
            $errores[] = 'Período: '.$e->getMessage();
        }

        return [
            'origen_id' => (int) $origen->id,
            'origen_etiqueta' => sprintf(
                '%s %s-%04d-%d',
                strtoupper(trim((string) ($origen->tipotransaccion_compras?->abreviatura ?? ''))),
                $origen->letra,
                (int) $origen->sucursal,
                (int) $origen->numerocomprobante
            ),
            'empresa_id' => (int) $origen->empresa_id,
            'tipo' => $tipo?->abreviatura ?? $tipoAbrev,
            'letra' => $letra,
            'sucursal' => $sucursal,
            'numero' => $numero,
            'fecha' => $fecha,
            'modo' => $modo,
            'modo_texto' => $this->textoModoFiscal($origen, $modo),
            'moneda_id' => (int) ($origen->moneda_id ?? 1),
            'cotizacion' => (float) ($origen->cotizacion ?? 1),
            'total' => (float) $origen->total,
            'conceptos' => $conceptosInfo,
            'espejo' => $espejo,
            'informable_iva' => $informable,
            'periodo_ok' => $periodoOk,
            'errores' => $errores,
        ];
    }

    private function textoModoFiscal(Comprobante_Proveedor $origen, string $modo): string
    {
        $esNd = ComprobanteProveedorReversoFiscalSupport::signoRaw($origen) === -1;
        if ($modo === ComprobanteProveedorReversoContableSupport::MODO_A_CUENTA) {
            return $esNd
                ? 'La ND queda abierta a favor del proveedor (no se aplica automáticamente).'
                : 'La NC queda a cuenta del proveedor (no se aplica automáticamente).';
        }

        return 'Se aplica en cuenta corriente a la factura.';
    }

    /**
     * Misma fecha que resolverFechaContable, sin validar período (lo hace la simulación aparte).
     */
    private function fechaContableSimulada(Comprobante_Proveedor $origen, ?string $fechaContable): string
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

        return $pedida;
    }

    /**
     * Pasos comunes del reverso: crear la NC/ND, contabilizar, verificar el espejo y aplicar.
     *
     * @return array<string, mixed>
     */
    private function ejecutarReverso(
        Comprobante_Proveedor $origen,
        string $modo,
        Request $request,
        string $fecha,
    ): array {
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

            // El reverso siempre da vuelta el asiento de la factura: se verifica línea por línea.
            ComprobanteProveedorReversoAsientoEspejoSupport::assertEsEspejo(
                (int) $origen->asiento_id,
                (int) $nc->asiento_id
            );

            $aplicacion = $modo === ComprobanteProveedorReversoContableSupport::MODO_A_CUENTA
                ? ['aplicadas' => 0, 'monto' => 0.0]
                : $this->aplicarNcSobreFactura($origen, $nc, $fecha);
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

        $mensaje = 'Reverso contable OK: se creó '.$etiqueta
            .' (asiento #'.(int) $nc->asiento_id.', espejo del asiento de la factura #'.$origen->id.')';
        if ($modo === ComprobanteProveedorReversoContableSupport::MODO_A_CUENTA) {
            // NC (origen factura pagada): crédito a cuenta. ND (origen NC): deuda abierta.
            $esNd = ComprobanteProveedorReversoFiscalSupport::signoRaw($nc) === 1;
            $mensaje .= $esNd
                ? '. La ND quedó abierta a favor del proveedor por '
                    .number_format((float) $nc->total, 2, ',', '.').' (no se aplicó automáticamente).'
                : '. La NC quedó a cuenta del proveedor por '
                    .number_format((float) $nc->total, 2, ',', '.')
                    .': se aplica a otros pagos desde la cuenta corriente.';
        } else {
            $mensaje .= ' y se aplicó a la factura.';
        }

        return [
            'origen_id' => (int) $origen->id,
            'nc_id' => $ncId,
            'nc_etiqueta' => $etiqueta,
            'asiento_id' => (int) $nc->asiento_id,
            'modo' => $modo,
            'aplicaciones' => (int) ($aplicacion['aplicadas'] ?? 0),
            'monto' => (float) ($aplicacion['monto'] ?? 0),
            'mensaje' => $mensaje,
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
     *   modo:string,
     *   modo_texto:string,
     *   lineas:list<array{cuenta_codigo:string, cuenta_nombre:string, debe:float, haber:float}>
     * }
     */
    public function previsualizar(int $comprobanteId, ?string $fechaContable = null): array
    {
        $origen = Comprobante_Proveedor::query()
            ->with(['tipotransaccion_compras'])
            ->findOrFail($comprobanteId);

        $modo = ComprobanteProveedorReversoContableSupport::assertPuedeRevertir($origen);
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

        return [
            'origen_id' => (int) $origen->id,
            'etiqueta' => $etiqueta,
            'total' => round((float) ($origen->total ?? 0), 2),
            'fecha_contable' => $fecha,
            'tipo_nc' => (string) ($tipoNc->abreviatura ?? ''),
            'modo' => $modo,
            'modo_texto' => ComprobanteProveedorReversoContableSupport::textoModo($modo),
            'lineas' => ComprobanteProveedorReversoAsientoEspejoSupport::lineasParaPreview(
                (int) $origen->asiento_id
            ),
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

        // NC interna: un solo concepto exento / no gravado por el total. El IVA y las demás
        // piernas se dan vuelta en el asiento espejo, no en el comprobante.
        $total = round((float) ($origen->total ?? 0), 4);
        $conceptoExento = ComprobanteProveedorReversoContableSupport::resolverConceptoExento();

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
            'subtotal' => $total,
            'total' => $total,
            'moneda_id' => (int) ($origen->moneda_id ?? 1),
            'cotizacion' => (float) ($origen->cotizacion ?? 1),
            'leyenda' => ComprobanteProveedorReversoContableSupport::leyendaPara($origen),
            'modo_carga' => ComprobanteProveedorModoCarga::SIN_RECEPCION,
            'origen_entrada' => ComprobanteProveedorOrigenEntrada::MANUAL,
            'es_fce' => false,
            'pararevisar' => false,
            'concepto_ivacompra_ids' => [(int) $conceptoExento->id],
            'montos' => [$total],
            'cuentacontabledebe_ids' => [null],
            'concepto_centrocosto_ids' => [null],
            'cuota_numero' => [1],
            'cuota_fechavencimiento' => [$fecha],
            'cuota_monto' => [$total],
            'cuota_moneda_id' => [(int) ($origen->moneda_id ?? 1)],
            'cuota_cotizacion' => [(float) ($origen->cotizacion ?? 1)],
        ];

        if ((int) ($origen->provincia_destino_id ?? 0) > 0) {
            $payload['provincia_destino_id'] = (int) $origen->provincia_destino_id;
        }

        return Request::create('/compras/comprobante-proveedor/reverso-contable', 'POST', $payload);
    }

    /**
     * Request del reverso fiscal: tipo fiscal, letra y punto de venta del original, número de la
     * serie propia y los conceptos ya armados (copiados o colapsados desde el original).
     *
     * @param  list<array{concepto_ivacompra_id:int, monto:float, cuentacontabledebe_id:?int, centrocosto_id:?int}>  $conceptos
     */
    private function armarRequestFiscal(
        Comprobante_Proveedor $origen,
        Tipotransaccion_Compra $tipo,
        int $numero,
        string $fecha,
        array $conceptos,
    ): Request {
        $total = round(array_sum(array_column($conceptos, 'monto')), 4);
        $letra = strtoupper(substr(trim((string) $origen->letra), 0, 1));
        $moneda = (int) ($origen->moneda_id ?? 1);
        $cotizacion = (float) ($origen->cotizacion ?? 1);

        if (abs($total - round((float) $origen->total, 4)) > 0.01) {
            throw new RuntimeException(
                'La suma de conceptos del reverso ('.number_format($total, 2, ',', '.')
                .') no coincide con el total del original ('.number_format((float) $origen->total, 2, ',', '.').').'
            );
        }

        $payload = [
            'empresa_id' => (int) $origen->empresa_id,
            'proveedor_id' => (int) $origen->proveedor_id,
            'tipotransaccion_compra_id' => (int) $tipo->id,
            'letra' => $letra,
            'sucursal' => (int) $origen->sucursal,
            'numerocomprobante' => $numero,
            'fechacomprobante' => $fecha,
            'fechaiva' => $fecha,
            'fechavencimiento' => $fecha,
            'subtotal' => $total,
            'total' => $total,
            'moneda_id' => $moneda,
            'cotizacion' => $cotizacion,
            'leyenda' => ComprobanteProveedorReversoContableSupport::leyendaPara($origen),
            'modo_carga' => ComprobanteProveedorModoCarga::SIN_RECEPCION,
            'origen_entrada' => ComprobanteProveedorOrigenEntrada::MANUAL,
            'es_fce' => false,
            'pararevisar' => false,
            'concepto_ivacompra_ids' => array_column($conceptos, 'concepto_ivacompra_id'),
            'montos' => array_column($conceptos, 'monto'),
            'cuentacontabledebe_ids' => array_column($conceptos, 'cuentacontabledebe_id'),
            'concepto_centrocosto_ids' => array_column($conceptos, 'centrocosto_id'),
            'cuota_numero' => [1],
            'cuota_fechavencimiento' => [$fecha],
            'cuota_monto' => [$total],
            'cuota_moneda_id' => [$moneda],
            'cuota_cotizacion' => [$cotizacion],
        ];

        if ((int) ($origen->provincia_destino_id ?? 0) > 0) {
            $payload['provincia_destino_id'] = (int) $origen->provincia_destino_id;
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
