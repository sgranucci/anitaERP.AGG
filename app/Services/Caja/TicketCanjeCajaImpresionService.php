<?php

namespace App\Services\Caja;

use App\Models\Caja\TicketCanjeCaja;
use App\Services\Caja\Estacionamiento\EstacionamientoPvService;
use App\Support\Ventas\NcjetdirectSalidaSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Impresión térmica no fiscal del vale canje (formato legacy ventas_pdf.php / ImprimeTicket).
 */
final class TicketCanjeCajaImpresionService
{
    public function __construct(
        private readonly EstacionamientoPvService $pvService,
    ) {
    }

    /**
     * @return array{ok:bool,omitida?:bool,mensaje:string}
     */
    public function imprimir(TicketCanjeCaja $ticket, ?Request $request = null, string $copia = 'Original'): array
    {
        $ticket->loadMissing('empresa');

        if ((bool) $ticket->es_vip || (float) $ticket->monto_ticket <= 0) {
            return [
                'ok' => true,
                'omitida' => true,
                'mensaje' => 'VIP / monto 0: no se imprime.',
            ];
        }

        $cfg = $this->pvService->resolverConfiguracionPv($request, (int) $ticket->empresa_id);
        if ($cfg === null) {
            return [
                'ok' => false,
                'mensaje' => 'No hay configuración PV estacionamiento para imprimir en esta terminal/empresa.',
            ];
        }

        $cfg->loadMissing('salidaFactura');
        $salida = $cfg->salidaFactura;
        if ($salida === null) {
            return [
                'ok' => false,
                'mensaje' => 'No hay salida de impresora configurada en el PV estacionamiento.',
            ];
        }

        $comando = trim((string) $salida->comando);
        if ($comando === '' || ! str_contains($comando, '%s')) {
            return [
                'ok' => false,
                'mensaje' => 'El comando de salida debe incluir %s (ruta del ticket).',
            ];
        }

        try {
            $bytes = $this->generarBytesTicket($ticket, $copia);
            $ruta = $this->guardarArchivoTemporal($ticket, $bytes);
            $resultado = null;
            try {
                $resultado = NcjetdirectSalidaSupport::ejecutar(
                    $comando,
                    $ruta,
                    (int) config('caja.ticket_canje_comando_timeout_segundos', 30),
                );
            } finally {
                @unlink($ruta);
            }

            if ($resultado !== null && ! $resultado['ok']) {
                Log::warning('caja.ticket_canje.impresion.fallo', array_merge([
                    'ticket_id' => $ticket->id,
                    'empresa_id' => $ticket->empresa_id,
                    'msg' => $resultado['mensaje'],
                ], NcjetdirectSalidaSupport::contextoLog($resultado)));

                return [
                    'ok' => false,
                    'mensaje' => 'No se pudo imprimir el ticket: '.$resultado['mensaje'],
                ];
            }

            return [
                'ok' => true,
                'mensaje' => 'Ticket '.$ticket->etiquetaVale().' enviado a impresora.',
            ];
        } catch (Throwable $e) {
            Log::warning('caja.ticket_canje.impresion.fallo', [
                'ticket_id' => $ticket->id,
                'msg' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'mensaje' => 'No se pudo imprimir el ticket: '.$e->getMessage(),
            ];
        }
    }

    public function generarBytesTicket(TicketCanjeCaja $ticket, string $copia = 'Original'): string
    {
        $empresa = $ticket->empresa;
        $nombreEmpresa = trim((string) ($empresa->nombre ?? 'EMPRESA'));
        $cuit = trim((string) ($empresa->nroinscripcion ?? ''));
        $iibb = trim((string) ($empresa->numeroiibb ?? ''));
        $domicilio = trim((string) ($empresa->domicilio ?? ''));

        $out = '';
        $out .= chr(0x20)."\n";
        $out .= chr(27).chr(33).chr(1);
        $out .= $nombreEmpresa."\n";
        if ($cuit !== '') {
            $out .= 'CUIT Nro: '.$cuit."\n";
        }
        if ($iibb !== '') {
            $out .= 'INGRESOS BRUTOS: '.$iibb."\n";
        }
        if ($domicilio !== '') {
            $out .= $domicilio."\n";
        }
        $out .= "       * * * TICKET NO FISCAL * * *\n\n";
        $out .= "Vale Gastronomia Nro.:\n";
        $out .= chr(27).chr(33).chr(32);
        $out .= $ticket->etiquetaVale()."\n\n";
        $out .= chr(27).chr(33).chr(1);
        $out .= "DOCUMENTO NO VALIDO COMO FACTURA\n";
        $out .= $copia."\n";
        $out .= 'Fecha de emision: '.date('d-m-Y').' Hora: '.date('G:i')."\n";

        $fechaVto = date('d-m-Y', strtotime('+'.max(1, (int) config('caja.ticket_canje_vencimiento_dias', 30)).' day'));
        $out .= "\n\n".chr(27).chr(33).chr(32);
        $out .= sprintf("Importe: \$ %.2f\n", (float) $ticket->monto_ticket);
        $out .= chr(27).chr(33).chr(1);

        if ($ticket->es_vip) {
            $out .= sprintf("\n\nCliente: VIP %s\n", $ticket->nro_documento);
        } else {
            $out .= sprintf("\n\nCliente: %s\n", $ticket->nro_documento);
        }

        $out .= "-----------------------------------------\n";
        $out .= 'Fecha de vto: '.$fechaVto."\n\n";

        $barcode = $ticket->codigoBarras();
        $out .= chr(0x1d).chr(0x48).chr(2)."\n";
        $out .= chr(0x1d).chr(0x66).chr(0)."\n";
        $out .= chr(0x1d).chr(0x68).chr(100)."\n";
        $out .= chr(0x1d).chr(0x77).chr(4)."\n";
        $out .= chr(0x1d).chr(0x6b).chr(2).$barcode.chr(0)."\n";
        $out .= "\n\n\n\n\n\n\n\n\n\n\n";
        $out .= chr(29).chr(86).chr(0);

        return $out;
    }

    private function guardarArchivoTemporal(TicketCanjeCaja $ticket, string $bytes): string
    {
        $dir = storage_path('app/caja/ticket_canje');
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException('No se pudo crear directorio temporal de tickets canje.');
        }

        $ruta = $dir.'/'.(int) $ticket->id.'_'.str_replace('.', '', (string) microtime(true)).'.bin';
        if (file_put_contents($ruta, $bytes) === false) {
            throw new \RuntimeException('No se pudo escribir el archivo temporal del ticket.');
        }

        return $ruta;
    }
}
