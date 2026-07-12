<?php

namespace App\Services\Ventas\Vianda;

use App\Models\Configuracion\Salida;
use App\Models\Ventas\ConfiguracionTerminalVianda;
use App\Models\Ventas\ViandaConsumo;
use App\Support\Ventas\EscPosTicketWriter;
use App\Support\Ventas\NcjetdirectSalidaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Voucher interno de retiro de vianda (ESC/POS). No es factura ni muestra precios:
 * ítems, cantidades, usuario, centro de costo y código de retiro.
 */
final class ViandaVoucherService
{
    /**
     * @return array{ok:bool,omitida?:bool,mensaje?:string,texto_preview:string}
     */
    public function emitir(ViandaConsumo $consumo, ConfiguracionTerminalVianda $cfg): array
    {
        $consumo->loadMissing(['lineas', 'centrocosto', 'viandaUsuario', 'empresa']);

        $bytes = $this->generarBytes($consumo, $cfg);
        $textoPreview = $this->ultimaVistaPrevia;

        $cfg->loadMissing('salidaVoucher');
        $salida = $cfg->salidaVoucher;
        if (! $salida instanceof Salida) {
            return [
                'ok' => false,
                'mensaje' => 'La terminal de viandas no tiene impresora/salida de voucher configurada.',
                'texto_preview' => $textoPreview,
            ];
        }

        $comando = trim((string) $salida->comando);
        if ($comando === '' || ! str_contains($comando, '%s')) {
            return [
                'ok' => false,
                'mensaje' => 'El comando de la salida del voucher debe incluir %s (ruta del archivo ESC/POS).',
                'texto_preview' => $textoPreview,
            ];
        }

        try {
            $ruta = $this->guardarArchivoTemporal((int) $consumo->id, $bytes);
            $resultado = null;
            try {
                $resultado = NcjetdirectSalidaSupport::ejecutar($comando, $ruta);
            } finally {
                @unlink($ruta);
            }

            if ($resultado !== null && empty($resultado['ok'])) {
                Log::warning('vianda.voucher.fallo', [
                    'consumo_id' => (int) $consumo->id,
                    'salida_id' => (int) $salida->id,
                    'msg' => $resultado['mensaje'] ?? '',
                ]);

                return [
                    'ok' => false,
                    'mensaje' => 'No se pudo imprimir el voucher: '.($resultado['mensaje'] ?? 'error desconocido'),
                    'texto_preview' => $textoPreview,
                ];
            }

            return ['ok' => true, 'texto_preview' => $textoPreview];
        } catch (Throwable $e) {
            Log::warning('vianda.voucher.excepcion', [
                'consumo_id' => (int) $consumo->id,
                'msg' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'mensaje' => 'No se pudo imprimir el voucher: '.$e->getMessage(),
                'texto_preview' => $textoPreview,
            ];
        }
    }

    private string $ultimaVistaPrevia = '';

    public function generarBytes(ViandaConsumo $consumo, ConfiguracionTerminalVianda $cfg): string
    {
        $ancho = max(32, (int) config('gastronomia.ticket_ancho_caracteres', 42));
        $codificacion = (string) config('gastronomia.ticket_codificacion', 'ISO-8859-1');

        $w = (new EscPosTicketWriter($ancho, $codificacion))->iniciar();

        $nombreEmpresa = trim((string) ($consumo->empresa->nombre ?? config('app.empresa', 'Empresa')));
        $w->titulo($nombreEmpresa);
        $w->textoCentradoNegrita('VOUCHER DE VIANDA');
        $w->textoCentrado('Comprobante interno de retiro');
        $w->textoCentrado('No valido como factura');

        $descTerminal = trim((string) ($cfg->descripcion ?? ''));
        $ubicacion = trim((string) ($cfg->ubicacion?->nombre ?? ''));
        if ($descTerminal !== '' || $ubicacion !== '') {
            $w->textoCentrado(trim($descTerminal.($descTerminal !== '' && $ubicacion !== '' ? ' - ' : '').$ubicacion));
        }

        $w->separador();

        $fechaHora = $consumo->created_at
            ? Carbon::parse($consumo->created_at)->format('d/m/Y H:i')
            : Carbon::parse($consumo->fecha)->format('d/m/Y').' '.(string) $consumo->hora;
        $w->linea('Fecha/hora: '.$fechaHora);
        if ($consumo->fecha_jornada !== null) {
            $w->linea('Jornada:    '.Carbon::parse($consumo->fecha_jornada)->format('d/m/Y'));
        }
        $w->linea('Comprob.:   '.$consumo->codigo_retiro);

        $w->separador();

        $login = trim((string) $consumo->login_usuario);
        $nombreUsuario = trim((string) ($consumo->nombre_usuario ?: ($consumo->viandaUsuario->nombre ?? '')));
        $w->negrita(true);
        $w->linea('Empleado:');
        $w->negrita(false);
        $w->linea('  '.trim(($login !== '' ? $login.' - ' : '').$nombreUsuario));
        $centrocosto = trim((string) ($consumo->centrocosto->nombre ?? ''));
        if ($centrocosto !== '') {
            $w->linea('Centro costo: '.$centrocosto);
        }

        $w->separador();
        $w->negrita(true);
        $w->linea('DETALLE A RETIRAR');
        $w->negrita(false);

        foreach ($consumo->lineas as $linea) {
            $cant = (float) $linea->cantidad;
            $cantTxt = abs($cant - round($cant)) < 0.0001
                ? (string) (int) round($cant)
                : number_format($cant, 2, '.', '');
            $desc = trim((string) ($linea->descripcion ?: ('Art. '.$linea->articulo_id)));
            $w->linea($cantTxt.' x '.$desc);

            $comentario = trim((string) $linea->comentario);
            if ($comentario !== '') {
                $w->linea('   > '.$comentario);
            }
        }

        $observacion = trim((string) $consumo->observacion);
        if ($observacion !== '') {
            $w->separador();
            $w->negrita(true);
            $w->linea('OBSERVACION COMANDA');
            $w->negrita(false);
            $w->linea($observacion);
        }

        // Código de retiro destacado (equivalente al papelito del monitor).
        $w->bloquePapelitoMonitor($consumo->codigo_retiro);
        $w->textoCentrado('Presente este voucher para retirar su vianda');

        try {
            $w->alinearCentro();
            $w->qr($consumo->codigo_retiro, (int) config('gastronomia.ticket_qr_size', 6));
            $w->alinearIzquierda();
        } catch (Throwable $e) {
            Log::warning('vianda.voucher.qr', ['consumo_id' => (int) $consumo->id, 'msg' => $e->getMessage()]);
        }

        $w->feed(3);
        $w->cortar();

        $this->ultimaVistaPrevia = $w->textoPlanoVistaPrevia();

        return $w->bytes();
    }

    private function guardarArchivoTemporal(int $consumoId, string $bytes): string
    {
        $dir = storage_path('app/vianda/vouchers');
        if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
            throw new RuntimeException('No se pudo crear el directorio de vouchers temporales.');
        }

        $ruta = $dir.'/voucher-'.$consumoId.'-'.time().'.bin';
        if (file_put_contents($ruta, $bytes) === false) {
            throw new RuntimeException('No se pudo escribir el archivo del voucher.');
        }

        return $ruta;
    }
}
