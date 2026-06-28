<?php

namespace App\Services\Caja\Estacionamiento;

use App\Models\Caja\Estacionamiento\ConfiguracionPuntoventaEstacionamiento;
use App\Models\Caja\Estacionamiento\CuentaEstacionamiento;
use App\Models\Caja\Estacionamiento\VentaEstacionamientoEmision;
use App\Models\Configuracion\Salida;
use App\Models\Seguridad\Usuario;
use App\Models\Ventas\Venta;
use App\Support\Caja\Estacionamiento\EstacionamientoFacturaPayloadSupport;
use App\Support\Caja\Estacionamiento\EstacionamientoVentaDisplaySupport;
use App\Support\Ventas\ArcaFacturaQrSupport;
use App\Support\Ventas\EscPosTicketWriter;
use App\Support\Ventas\NcjetdirectSalidaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Ticket fiscal térmico (ESC/POS) post-emisión estacionamiento.
 */
final class EstacionamientoFacturaTicketService
{
    private ?string $ultimaVistaPreviaTexto = null;

    /**
     * @return array{ok:bool,omitida?:bool,mensaje?:string}
     */
    public function imprimirTrasEmision(
        int $ventaId,
        ConfiguracionPuntoventaEstacionamiento $cfg,
        ?CuentaEstacionamiento $cuenta = null,
    ): array {
        if (! config('estacionamiento.ticket_impresion_automatica', true)) {
            return ['ok' => true, 'omitida' => true, 'mensaje' => 'Impresión automática de ticket deshabilitada.'];
        }

        return $this->imprimirTicketVenta($ventaId, $cfg, $cuenta);
    }

    /**
     * @return array{ok:bool,omitida?:bool,encolada?:bool,mensaje?:string}
     */
    public function imprimirTrasEmisionEncolado(
        int $ventaId,
        ConfiguracionPuntoventaEstacionamiento $cfg,
        ?CuentaEstacionamiento $cuenta = null,
    ): array {
        if (! config('estacionamiento.ticket_impresion_automatica', true)) {
            return ['ok' => true, 'omitida' => true, 'mensaje' => 'Impresión automática de ticket deshabilitada.'];
        }

        if (! config('estacionamiento.ticket_impresion_async', true)) {
            return $this->imprimirTicketVenta($ventaId, $cfg, $cuenta);
        }

        $cfgId = (int) $cfg->id;
        $cuentaId = $cuenta?->id !== null ? (int) $cuenta->id : null;

        defer(function () use ($ventaId, $cfgId, $cuentaId): void {
            try {
                $cfgDefer = ConfiguracionPuntoventaEstacionamiento::query()->find($cfgId);
                if ($cfgDefer === null) {
                    Log::warning('estacionamiento.ticket_factura.defer.cfg_inexistente', [
                        'venta_id' => $ventaId,
                        'cfg_id' => $cfgId,
                    ]);

                    return;
                }

                $cuentaDefer = $cuentaId !== null
                    ? CuentaEstacionamiento::query()->with(['categoriaAutomovil', 'turnoOperativo.usuarioHabilitado'])->find($cuentaId)
                    : null;

                $this->imprimirTicketVenta($ventaId, $cfgDefer, $cuentaDefer);
            } catch (Throwable $e) {
                Log::error('estacionamiento.ticket_factura.defer.excepcion', [
                    'venta_id' => $ventaId,
                    'msg' => $e->getMessage(),
                ]);
            }
        });

        return [
            'ok' => true,
            'encolada' => true,
            'mensaje' => 'Ticket en cola de impresión (defer post-respuesta).',
        ];
    }

    /**
     * @return array{ok:bool,omitida?:bool,mensaje?:string}
     */
    public function reimprimirTicketVenta(int $ventaId, ?ConfiguracionPuntoventaEstacionamiento $cfg = null): array
    {
        if ($cfg === null) {
            $cfg = $this->resolverConfiguracionDesdeEmision($ventaId);
        }
        if ($cfg === null) {
            return ['ok' => false, 'mensaje' => 'No se encontró configuración de PV estacionamiento para esta venta.'];
        }

        $cuenta = CuentaEstacionamiento::query()
            ->where('venta_id', $ventaId)
            ->with(['categoriaAutomovil', 'turnoOperativo.usuarioHabilitado'])
            ->first();

        return $this->imprimirTicketVenta($ventaId, $cfg, $cuenta);
    }

    public function generarBytesTicket(Venta $venta, ?CuentaEstacionamiento $cuenta = null): string
    {
        $ancho = max(32, (int) config('estacionamiento.ticket_ancho_caracteres', 42));
        $codificacion = (string) config('estacionamiento.ticket_codificacion', 'ISO-8859-1');

        $w = (new EscPosTicketWriter($ancho, $codificacion))->iniciar();

        $empresa = $venta->puntoventas->empresas;
        $pv = $venta->puntoventas;
        $nombreEmpresa = trim((string) ($empresa->nombre ?? config('app.empresa', 'Empresa')));
        $letra = $this->letraComprobante($venta);
        $codigoAfip = $this->codigoComprobanteAfip($venta, $letra);

        $w->titulo($nombreEmpresa);

        $lineaDomicilio = $this->lineaDomicilioTicket($pv, $empresa);
        if ($lineaDomicilio !== '') {
            $w->textoCentrado($lineaDomicilio);
        }

        $cuit = trim((string) ($empresa->nroinscripcion ?? ''));
        if ($cuit !== '') {
            $w->textoCentrado('C.U.I.T.: '.$cuit);
        }

        $w->textoCentradoNegrita('IVA RESPONSABLE INSCRIPTO');
        $w->textoCentrado($this->leyendaCategoriaReceptor($venta));

        $w->separador();

        $fechaHora = $venta->created_at
            ? Carbon::parse($venta->created_at)->format('d/m/y H:i')
            : Carbon::parse($venta->fecha)->format('d/m/y H:i');
        $w->linea($fechaHora);
        $w->linea('NRO.T.: '.$this->formatearNumeroTicket($venta));

        $w->separador();

        $esNotaCredito = $this->esNotaCredito($venta);
        $etiquetaComprobante = $this->etiquetaComprobante($esNotaCredito, $letra);
        $w->alinearCentro();
        $w->negrita(true);
        $w->dobleTamano(true);
        $w->linea($etiquetaComprobante);
        $w->dobleTamano(false);
        $w->negrita(false);
        $w->linea('ORIGINAL (Cod.'.str_pad((string) $codigoAfip, 3, '0', STR_PAD_LEFT).')');
        $w->alinearIzquierda();

        $w->separador();

        $subtotal = 0.;
        foreach ($venta->venta_emisiones as $item) {
            $cant = abs((float) $item->cantidad);
            $precio = (float) $item->precio;
            if ($item->descuento > 0) {
                $precio *= (1 - ((float) $item->descuento / 100));
            }
            if ($item->descuentointegrado) {
                foreach (explode('+', (string) $item->descuentointegrado) as $d) {
                    $precio *= (1 - ((float) $d / 100));
                }
            }
            $importeLinea = round($cant * $precio, 2);
            $subtotal += $importeLinea;

            $desc = EstacionamientoFacturaPayloadSupport::etiquetaItemDesdeDetalle(
                (string) ($item->detalle ?? '')
            );
            $cantTxt = abs($cant - round($cant)) < 0.0001
                ? (string) (int) round($cant)
                : number_format($cant, 2, '.', '');
            $w->lineaConImporte($cantTxt.' '.$desc, number_format($importeLinea, 2, '.', ''));
        }

        $totalAbs = round(abs((float) $venta->total), 2);
        $w->lineaConImporte('SUBTOT. SIN DESCUENTOS', number_format($subtotal, 2, '.', ''));
        $descuentoTotal = round($subtotal - $totalAbs, 2);
        if ($descuentoTotal > 0) {
            $pctCalculado = $subtotal > 0. ? round(100. * $descuentoTotal / $subtotal, 2) : null;
            $etiquetaDescuento = $pctCalculado !== null ? 'DESCUENTO '.$pctCalculado.'%' : 'DESCUENTO';
            $w->lineaConImporte($etiquetaDescuento, '-'.number_format($descuentoTotal, 2, '.', ''));
        }
        $w->separadorDoble();

        $w->alinearCentro();
        $w->negrita(true);
        $w->dobleTamano(true);
        $w->linea('TOTAL');
        $w->linea('$ '.number_format($totalAbs, 2, '.', ''));
        $w->dobleTamano(false);
        $w->negrita(false);
        $w->alinearIzquierda();

        $w->separadorDoble();

        if ($letra === 'B') {
            $ivaContenido = $this->ivaContenido($venta);
            $impuestoInterno = $this->impuestoInternoContenido($venta);
            $w->linea('REGIMEN DE TRANSPARENCIA FISCAL');
            $w->linea('AL CONSUMIDOR (LEY 27.743)');
            $w->lineaConImporte('IVA Contenido', number_format($ivaContenido, 2, '.', ''));
            $w->linea('Otros Trib.Nac.Incid.Precio');
            if ($impuestoInterno > 0) {
                $w->lineaConImporte('  Imp. Interno', number_format($impuestoInterno, 2, '.', ''));
            }
            $w->linea('LOS IMPUESTOS INFORMADOS SON SOLO');
            $w->linea('LOS QUE CORRESPONDEN A NIVEL NACIONAL');
            $w->separadorDoble();
        }

        $w->linea($esNotaCredito ? 'DEVOLUCION A CLIENTE CONTADO' : 'VENTA A CLIENTE CONTADO');
        $w->linea($this->referenciaComprobanteCompacta($venta));
        $w->linea('Cliente: '.EstacionamientoVentaDisplaySupport::nombreReceptorFactura($venta));

        $lineaEstacionamiento = $this->lineaCategoriaPatente($cuenta);
        if ($lineaEstacionamiento !== '') {
            $w->linea($lineaEstacionamiento);
        }

        $lineaOperador = $this->lineaOperadorTurno($venta, $cuenta);
        if ($lineaOperador !== '') {
            $w->linea($lineaOperador);
        }

        if (trim((string) ($venta->leyenda ?? '')) !== '') {
            $w->linea(trim((string) $venta->leyenda));
        }

        $w->separador();

        if ((int) ($venta->cae ?? 0) > 0) {
            $w->alinearCentro();
            $w->negrita(true);
            $w->linea('**REFERENCIA ELECTRONICA DEL');
            $w->linea('COMPROBANTE**');
            $w->negrita(false);
            $w->linea('C.A.E. Nro.: '.(string) $venta->cae);
            $vto = $venta->fechavencimientocae
                ? Carbon::parse($venta->fechavencimientocae)->format('d/m/y')
                : '';
            if ($vto !== '') {
                $w->linea('Vto.: '.$vto);
            }
            $w->alinearIzquierda();
            $w->separador();
        }

        try {
            $qrUrl = ArcaFacturaQrSupport::urlParaVenta($venta);
            $w->alinearCentro();
            $w->qr($qrUrl, (int) config('estacionamiento.ticket_qr_size', 6));
            $w->alinearIzquierda();
        } catch (Throwable $e) {
            Log::warning('estacionamiento.ticket_factura.qr', ['venta_id' => $venta->id, 'msg' => $e->getMessage()]);
        }

        $w->feed(3);
        $w->cortar();

        $this->ultimaVistaPreviaTexto = $w->textoPlanoVistaPrevia();

        return $w->bytes();
    }

    /**
     * @return array{ok:bool,omitida?:bool,mensaje?:string}
     */
    private function imprimirTicketVenta(
        int $ventaId,
        ConfiguracionPuntoventaEstacionamiento $cfg,
        ?CuentaEstacionamiento $cuenta = null,
    ): array {
        $cfg->loadMissing('salidaFactura');
        $salida = $cfg->salidaFactura;
        if (! $salida instanceof Salida) {
            return ['ok' => false, 'mensaje' => 'No hay salida de facturas configurada en el punto de venta estacionamiento.'];
        }

        $comando = trim((string) $salida->comando);
        if ($comando === '' || ! str_contains($comando, '%s')) {
            return ['ok' => false, 'mensaje' => 'El comando de salida de facturas debe incluir %s (ruta del ticket).'];
        }

        try {
            $venta = $this->cargarVenta($ventaId);
            if ($cuenta === null) {
                $cuenta = CuentaEstacionamiento::query()
                    ->where('venta_id', $ventaId)
                    ->with(['categoriaAutomovil', 'turnoOperativo.usuarioHabilitado'])
                    ->first();
            } else {
                $cuenta->loadMissing(['categoriaAutomovil', 'turnoOperativo.usuarioHabilitado']);
            }

            $bytes = $this->generarBytesTicket($venta, $cuenta);
            $this->guardarVistaPreviaTexto($ventaId);
            $ruta = $this->guardarArchivoTemporal($ventaId, $bytes);
            $resultadoImpresion = null;
            try {
                $resultadoImpresion = NcjetdirectSalidaSupport::ejecutar(
                    $comando,
                    $ruta,
                    (int) config('estacionamiento.ticket_comando_timeout_segundos', 30),
                );
            } finally {
                @unlink($ruta);
            }

            if ($resultadoImpresion !== null && ! $resultadoImpresion['ok']) {
                Log::warning('estacionamiento.ticket_factura.fallo', array_merge([
                    'venta_id' => $ventaId,
                    'salida_id' => (int) $salida->id,
                    'msg' => $resultadoImpresion['mensaje'],
                ], NcjetdirectSalidaSupport::contextoLog($resultadoImpresion)));

                return [
                    'ok' => false,
                    'mensaje' => 'No se pudo imprimir el ticket: '.$resultadoImpresion['mensaje'],
                ];
            }

            Log::info('estacionamiento.ticket_factura.ok', array_merge([
                'venta_id' => $ventaId,
                'salida_id' => (int) $salida->id,
            ], $resultadoImpresion !== null ? NcjetdirectSalidaSupport::contextoLog($resultadoImpresion) : []));

            return ['ok' => true];
        } catch (Throwable $e) {
            Log::warning('estacionamiento.ticket_factura.fallo', [
                'venta_id' => $ventaId,
                'salida_id' => $salida->id,
                'msg' => $e->getMessage(),
            ]);

            return ['ok' => false, 'mensaje' => 'No se pudo imprimir el ticket: '.$e->getMessage()];
        }
    }

    private function resolverConfiguracionDesdeEmision(int $ventaId): ?ConfiguracionPuntoventaEstacionamiento
    {
        $emision = VentaEstacionamientoEmision::query()
            ->where('venta_id', $ventaId)
            ->with('configuracionPuntoventa.salidaFactura')
            ->first();

        return $emision?->configuracionPuntoventa;
    }

    private function guardarVistaPreviaTexto(int $ventaId): void
    {
        if (! config('estacionamiento.ticket_guardar_preview', false)) {
            return;
        }

        $texto = trim((string) $this->ultimaVistaPreviaTexto);
        if ($texto === '') {
            return;
        }

        $dir = storage_path('app/estacionamiento/tickets/preview');
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return;
        }

        $rutaVenta = $dir.'/ticket-'.$ventaId.'.txt';
        $rutaUltimo = $dir.'/ultimo-ticket.txt';
        if (file_put_contents($rutaVenta, $texto."\n") !== false) {
            @copy($rutaVenta, $rutaUltimo);
        }
    }

    private function cargarVenta(int $ventaId): Venta
    {
        $venta = Venta::query()
            ->with([
                'puntoventas.empresas',
                'puntoventas.localidades',
                'tipotransacciones',
                'clientes.condicionivas',
                'clientes.tipodocumentos',
                'monedas',
                'venta_emisiones',
                'venta_impuestos',
            ])
            ->find($ventaId);

        if (! $venta) {
            throw new InvalidArgumentException('Venta '.$ventaId.' no encontrada.');
        }

        return $venta;
    }

    private function guardarArchivoTemporal(int $ventaId, string $bytes): string
    {
        $dir = storage_path('app/estacionamiento/tickets');
        if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
            throw new RuntimeException('No se pudo crear el directorio de tickets temporales.');
        }

        $ruta = $dir.'/ticket-'.$ventaId.'-'.time().'.bin';
        if (file_put_contents($ruta, $bytes) === false) {
            throw new RuntimeException('No se pudo escribir el archivo de ticket.');
        }

        return $ruta;
    }

    private function formatearNumeroTicket(Venta $venta): string
    {
        $pv = str_pad((string) (int) ($venta->puntoventas->codigo ?? 0), 5, '0', STR_PAD_LEFT);
        $nro = str_pad((string) (int) $venta->numerocomprobante, 8, '0', STR_PAD_LEFT);

        return $pv.'-'.$nro;
    }

    private function letraComprobante(Venta $venta): string
    {
        $partes = explode(' ', (string) $venta->codigo);

        return strtoupper(substr($partes[1] ?? '', 0, 1));
    }

    private function esNotaCredito(Venta $venta): bool
    {
        $signo = (string) ($venta->tipotransacciones->signo ?? '');

        return $signo !== '' && $signo !== 'S';
    }

    private function etiquetaComprobante(bool $esNotaCredito, string $letra): string
    {
        $base = $esNotaCredito ? 'NOTA DE CREDITO' : 'FACTURA';

        return $base.($letra !== '' ? ' '.$letra : '');
    }

    private function referenciaComprobanteCompacta(Venta $venta): string
    {
        $letra = $this->letraComprobante($venta);
        $prefijo = strtoupper(substr((string) ($venta->codigo ?? 'FAC'), 0, 3));
        $pv = str_pad((string) (int) ($venta->puntoventas->codigo ?? 0), 4, '0', STR_PAD_LEFT);
        $nro = str_pad((string) (int) $venta->numerocomprobante, 8, '0', STR_PAD_LEFT);

        return trim($prefijo.' '.$letra.$pv.'-'.$nro);
    }

    private function codigoComprobanteAfip(Venta $venta, string $letra): int
    {
        $codigo = (int) ($venta->tipotransacciones->codigo ?? 0);
        if ($letra === 'B') {
            $codigo += 5;
        }

        return $codigo;
    }

    private function lineaDomicilioTicket(object $pv, object $empresa): string
    {
        $dom = trim((string) ($pv->domicilio ?? $empresa->domicilio ?? ''));
        $loc = trim((string) ($pv->localidades->nombre ?? ''));
        if ($dom === '' && $loc === '') {
            return '';
        }
        if ($dom !== '' && $loc !== '') {
            return $dom.', '.$loc.'.';
        }

        return ($dom !== '' ? $dom : $loc).'.';
    }

    private function leyendaCategoriaReceptor(Venta $venta): string
    {
        $nombre = strtoupper(EstacionamientoVentaDisplaySupport::nombreReceptorFactura($venta));
        if (str_contains($nombre, 'CONSUMIDOR FINAL')) {
            return 'A CONSUMIDOR FINAL';
        }

        $cond = strtoupper(trim((string) ($venta->clientes->condicionivas->nombre ?? '')));
        if ($cond !== '') {
            return 'A '.$cond;
        }

        return 'A CONSUMIDOR FINAL';
    }

    private function ivaContenido(Venta $venta): float
    {
        $iva = 0.;
        foreach ($venta->venta_impuestos as $imp) {
            if (stripos((string) $imp->concepto, 'Iva') !== false) {
                $iva += abs((float) $imp->importe);
            }
        }

        return round($iva, 2);
    }

    private function impuestoInternoContenido(Venta $venta): float
    {
        $total = 0.;
        foreach ($venta->venta_impuestos as $imp) {
            if (stripos((string) $imp->concepto, 'Impuesto Interno') !== false) {
                $total += abs((float) $imp->importe);
            }
        }

        return round($total, 2);
    }

    private function lineaCategoriaPatente(?CuentaEstacionamiento $cuenta): string
    {
        if (! $cuenta) {
            return '';
        }

        $partes = [];
        $cat = trim((string) ($cuenta->categoriaAutomovil->nombre ?? ''));
        if ($cat !== '') {
            $partes[] = 'Categoria: '.$cat;
        }
        $patente = trim((string) ($cuenta->patente ?? ''));
        if ($patente !== '') {
            $partes[] = 'Patente: '.$patente;
        }

        return implode(' — ', $partes);
    }

    private function lineaOperadorTurno(Venta $venta, ?CuentaEstacionamiento $cuenta): string
    {
        $usuario = $this->resolverUsuarioOperadorTurno($venta, $cuenta);
        if ($usuario === null) {
            return '';
        }

        $nombre = trim((string) ($usuario->nombre ?? $usuario->usuario ?? ''));
        if ($nombre === '') {
            return '';
        }

        return 'Operador: '.$nombre;
    }

    private function resolverUsuarioOperadorTurno(Venta $venta, ?CuentaEstacionamiento $cuenta): ?Usuario
    {
        $emision = VentaEstacionamientoEmision::query()
            ->where('venta_id', $venta->id)
            ->with('turnoOperativo.usuarioHabilitado')
            ->first();
        $usuario = $emision?->turnoOperativo?->usuarioHabilitado;
        if ($usuario !== null) {
            return $usuario;
        }

        if ($cuenta === null) {
            return null;
        }

        $cuenta->loadMissing('turnoOperativo.usuarioHabilitado');

        return $cuenta->turnoOperativo?->usuarioHabilitado;
    }
}
