<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Configuracion\Salida;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\MozoGastronomia;
use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Support\Ventas\ArcaFacturaQrSupport;
use App\Support\Ventas\EscPosTicketWriter;
use App\Support\Ventas\GastronomiaVentaDisplaySupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Ticket fiscal térmico (ESC/POS) post-emisión gastronomía. El PDF queda para consulta/reimpresión.
 */
final class GastronomiaFacturaTicketService
{
    private ?string $ultimaVistaPreviaTexto = null;
    /**
     * @return array{ok:bool,omitida?:bool,mensaje?:string}
     */
    public function imprimirTrasEmision(
        int $ventaId,
        ConfiguracionPuntoventaGastronomia $cfg,
        ?CuentaGastronomia $cuenta = null,
    ): array {
        if (! config('gastronomia.ticket_impresion_automatica', true)) {
            return ['ok' => true, 'omitida' => true, 'mensaje' => 'Impresión automática de ticket deshabilitada.'];
        }

        return $this->imprimirTicketVenta($ventaId, $cfg, $cuenta);
    }

    /**
     * Reimpresión manual (Facturas del día u otro origen).
     *
     * @return array{ok:bool,omitida?:bool,mensaje?:string}
     */
    public function reimprimirTicketVenta(int $ventaId, ?ConfiguracionPuntoventaGastronomia $cfg = null): array
    {
        if ($cfg === null) {
            $cfg = $this->resolverConfiguracionDesdeEmision($ventaId);
        }
        if ($cfg === null) {
            return ['ok' => false, 'mensaje' => 'No se encontró configuración de PV gastronomía para esta venta.'];
        }

        $cuenta = CuentaGastronomia::query()
            ->where('venta_id', $ventaId)
            ->with('mozo')
            ->first();

        return $this->imprimirTicketVenta($ventaId, $cfg, $cuenta);
    }

    /**
     * @return array{ok:bool,omitida?:bool,mensaje?:string}
     */
    private function imprimirTicketVenta(
        int $ventaId,
        ConfiguracionPuntoventaGastronomia $cfg,
        ?CuentaGastronomia $cuenta = null,
    ): array {
        $cfg->loadMissing('salidaFactura');
        $salida = $cfg->salidaFactura;
        if (! $salida instanceof Salida) {
            return ['ok' => false, 'mensaje' => 'No hay salida de facturas configurada en el punto de venta gastronomía.'];
        }

        $comando = trim((string) $salida->comando);
        if ($comando === '' || ! str_contains($comando, '%s')) {
            return ['ok' => false, 'mensaje' => 'El comando de salida de facturas debe incluir %s (ruta del ticket).'];
        }

        try {
            $venta = $this->cargarVenta($ventaId);
            if ($cuenta !== null) {
                $cuenta->loadMissing('mozo');
            } else {
                $cuenta = CuentaGastronomia::query()
                    ->where('venta_id', $ventaId)
                    ->with('mozo')
                    ->first();
            }

            $bytes = $this->generarBytesTicket($venta, $cuenta);
            $this->guardarVistaPreviaTexto($ventaId);
            $ruta = $this->guardarArchivoTemporal($ventaId, $bytes);
            try {
                $this->ejecutarComandoSalida($comando, $ruta);
            } finally {
                @unlink($ruta);
            }

            return ['ok' => true];
        } catch (Throwable $e) {
            Log::warning('gastronomia.ticket_factura.fallo', [
                'venta_id' => $ventaId,
                'salida_id' => $salida->id,
                'msg' => $e->getMessage(),
            ]);

            return ['ok' => false, 'mensaje' => 'No se pudo imprimir el ticket: '.$e->getMessage()];
        }
    }

    private function resolverConfiguracionDesdeEmision(int $ventaId): ?ConfiguracionPuntoventaGastronomia
    {
        $emision = VentaGastronomiaEmision::query()
            ->where('venta_id', $ventaId)
            ->with('configuracionPuntoventa.salidaFactura')
            ->first();

        return $emision?->configuracionPuntoventa;
    }

    public function generarBytesTicket(Venta $venta, ?CuentaGastronomia $cuenta = null): string
    {
        $ancho = max(32, (int) config('gastronomia.ticket_ancho_caracteres', 42));
        $codificacion = (string) config('gastronomia.ticket_codificacion', 'ISO-8859-1');

        $w = (new EscPosTicketWriter($ancho, $codificacion))->iniciar();

        $empresa = $venta->puntoventas->empresas;
        $pv = $venta->puntoventas;
        $nombreEmpresa = trim((string) ($empresa->nombre ?? config('app.empresa', 'Empresa')));
        $letra = $this->letraComprobante($venta);
        $codigoAfip = $this->codigoComprobanteAfip($venta, $letra);

        // —— Cabecera emisor (como ticket de referencia) ——
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

        $etiquetaFactura = 'FACTURA'.($letra !== '' ? ' '.$letra : '');
        $w->alinearCentro();
        $w->negrita(true);
        $w->dobleTamano(true);
        $w->linea($etiquetaFactura);
        $w->dobleTamano(false);
        $w->negrita(false);
        $w->linea('ORIGINAL (Cod.'.str_pad((string) $codigoAfip, 3, '0', STR_PAD_LEFT).')');
        $w->alinearIzquierda();

        $w->separador();

        $subtotal = 0.;
        $n = 0;
        foreach ($venta->venta_emisiones as $item) {
            $n++;
            $cant = (float) $item->cantidad;
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

            $desc = trim((string) ($item->articulos->descripcion ?? $item->detalle ?? 'Item'));
            $cantTxt = abs($cant - round($cant)) < 0.0001
                ? (string) (int) round($cant)
                : number_format($cant, 2, '.', '');
            $w->lineaConImporte($cantTxt.' '.$desc, number_format($importeLinea, 2, '.', ''));
        }

        $w->lineaConImporte('SUBTOT. SIN DESCUENTOS', number_format($subtotal, 2, '.', ''));
        $w->separadorDoble();

        $w->alinearCentro();
        $w->negrita(true);
        $w->dobleTamano(true);
        $w->linea('TOTAL');
        $w->linea('$ '.number_format((float) $venta->total, 2, '.', ''));
        $w->dobleTamano(false);
        $w->negrita(false);
        $w->alinearIzquierda();
        $w->separadorDoble();

        if ($letra === 'B') {
            $ivaContenido = $this->ivaContenido($venta);
            $w->linea('REGIMEN DE TRANSPARENCIA FISCAL');
            $w->linea('AL CONSUMIDOR');
            $w->lineaConImporte('IVA Contenido', number_format($ivaContenido, 2, '.', ''));
            $w->linea('LOS IMPUESTOS INFORMADOS SON SOLO');
            $w->linea('LOS QUE CORRESPONDEN A NIVEL NACIONAL');
            $w->separadorDoble();
        }

        $w->linea('VENTA A CLIENTE CONTADO');
        $w->linea($this->referenciaComprobanteCompacta($venta));
        $w->linea('Cliente: '.GastronomiaVentaDisplaySupport::nombreReceptorFactura($venta));

        $mozoLinea = $this->lineaMozo($cuenta);
        if ($mozoLinea !== '') {
            $w->linea($mozoLinea);
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
            $w->qr($qrUrl, (int) config('gastronomia.ticket_qr_size', 6));
            $w->alinearIzquierda();
        } catch (Throwable $e) {
            Log::warning('gastronomia.ticket_factura.qr', ['venta_id' => $venta->id, 'msg' => $e->getMessage()]);
        }

        $w->feed(3);
        $w->cortar();

        $this->ultimaVistaPreviaTexto = $w->textoPlanoVistaPrevia();

        return $w->bytes();
    }

    private function guardarVistaPreviaTexto(int $ventaId): void
    {
        if (! config('gastronomia.ticket_guardar_preview', false)) {
            return;
        }

        $texto = trim((string) $this->ultimaVistaPreviaTexto);
        if ($texto === '') {
            return;
        }

        $dir = storage_path('app/gastronomia/tickets/preview');
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            Log::warning('gastronomia.ticket_factura.preview_dir', ['venta_id' => $ventaId]);

            return;
        }

        $rutaVenta = $dir.'/ticket-'.$ventaId.'.txt';
        $rutaUltimo = $dir.'/ultimo-ticket.txt';
        if (file_put_contents($rutaVenta, $texto."\n") === false) {
            Log::warning('gastronomia.ticket_factura.preview_write', ['venta_id' => $ventaId, 'ruta' => $rutaVenta]);

            return;
        }

        @copy($rutaVenta, $rutaUltimo);
        Log::info('gastronomia.ticket_factura.preview', [
            'venta_id' => $ventaId,
            'ruta' => $rutaVenta,
            'ultimo' => $rutaUltimo,
        ]);
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
                'venta_emisiones.articulos',
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
        $dir = storage_path('app/gastronomia/tickets');
        if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
            throw new RuntimeException('No se pudo crear el directorio de tickets temporales.');
        }

        $ruta = $dir.'/ticket-'.$ventaId.'-'.time().'.bin';
        if (file_put_contents($ruta, $bytes) === false) {
            throw new RuntimeException('No se pudo escribir el archivo de ticket.');
        }

        return $ruta;
    }

    private function ejecutarComandoSalida(string $comandoPlantilla, string $rutaArchivo): void
    {
        if (! str_contains($comandoPlantilla, '%s')) {
            throw new InvalidArgumentException('El comando de salida debe incluir el marcador %s.');
        }

        // Mismo patrón que ArticuloController / PedidoService: plantilla con "%s" y sprintf.
        // No usar escapeshellarg aquí: con "%s" en la plantilla duplica comillas y el script no encuentra el archivo.
        $comando = sprintf($comandoPlantilla, $rutaArchivo);
        if (trim($comando) === '') {
            throw new InvalidArgumentException('Comando de salida vacío.');
        }

        $process = Process::fromShellCommandline($comando);
        $process->setTimeout((int) config('gastronomia.ticket_comando_timeout_segundos', 30));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
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
        $nombre = strtoupper(GastronomiaVentaDisplaySupport::nombreReceptorFactura($venta));
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
                $iva += (float) $imp->importe;
            }
        }

        return round($iva, 2);
    }

    private function lineaMozo(?CuentaGastronomia $cuenta): string
    {
        if (! $cuenta) {
            return '';
        }

        $mozo = $cuenta->mozo;
        if (! $mozo instanceof MozoGastronomia) {
            return '';
        }

        $codigo = trim((string) ($mozo->codigo ?? ''));
        $nombre = trim((string) ($mozo->nombre ?? ''));

        if ($codigo === '' && $nombre === '') {
            return '';
        }

        $id = str_pad($codigo !== '' ? $codigo : (string) $mozo->id, 6, '0', STR_PAD_LEFT);

        return 'Atendio: (0) '.$id.' '.$nombre;
    }
}
