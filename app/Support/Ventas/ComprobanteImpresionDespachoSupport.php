<?php

namespace App\Support\Ventas;

use App\Models\Configuracion\Salida;
use App\Support\Configuracion\SalidaImpresionFallbackSupport;
use Symfony\Component\Process\Process;

final class ComprobanteImpresionDespachoSupport
{
    /**
     * @return array{ok: bool, mensaje: string, medio: string}
     */
    public static function despachar(string $rutaPdf, ?Salida $salida, ?string $destinoNas = null): array
    {
        if (! is_file($rutaPdf)) {
            return ['ok' => false, 'mensaje' => 'PDF inexistente: '.$rutaPdf, 'medio' => 'IMPRESORA'];
        }

        if ($salida === null) {
            return ['ok' => false, 'mensaje' => 'Sin salida configurada para esta copia', 'medio' => 'IMPRESORA'];
        }

        $comandoPlantilla = trim((string) $salida->comando);
        $esArchivo = ComprobanteImpresionNasPathSupport::esSalidaArchivo($comandoPlantilla);
        $medio = $esArchivo ? 'ARCHIVO' : 'IMPRESORA';

        if ($esArchivo) {
            if (! ComprobanteImpresionNasPathSupport::nasMontado()) {
                return ['ok' => false, 'mensaje' => 'NAS no montado ('.$destinoNas.')', 'medio' => $medio];
            }
            if ($destinoNas === null || $destinoNas === '') {
                return ['ok' => false, 'mensaje' => 'Destino NAS vacío', 'medio' => $medio];
            }
        }

        if (! SalidaImpresionFallbackSupport::comandoPdfCompatible($salida)) {
            $marcadores = SalidaImpresionFallbackSupport::cantidadMarcadoresSprintf($comandoPlantilla);

            return [
                'ok' => false,
                'mensaje' => $marcadores === 0
                    ? 'El comando de la salida debe incluir un %s (ruta del PDF)'
                    : 'La salida «'.$salida->nombre.'» no sirve para facturas/PDF: su comando tiene '
                        .$marcadores.' marcadores %s (emisión OT). Configurá «Mi impresora» con una salida de un solo %s.',
                'medio' => $medio,
            ];
        }

        try {
            $comando = sprintf($comandoPlantilla, $rutaPdf);
        } catch (\ArgumentCountError|\ValueError $e) {
            return [
                'ok' => false,
                'mensaje' => 'Comando de salida incompatible con PDF: '.$e->getMessage(),
                'medio' => $medio,
            ];
        }

        if ($destinoNas !== null && $destinoNas !== '') {
            $comando = 'IMPRESION_NAS_DESTINO='.escapeshellarg($destinoNas).' '.$comando;
        }
        $process = Process::fromShellCommandline($comando);
        $process->setTimeout(60);
        $process->run();

        if ($process->isSuccessful()) {
            return ['ok' => true, 'mensaje' => $esArchivo ? 'Archivado en NAS' : 'Enviado a '.$salida->nombre, 'medio' => $medio];
        }

        $detalle = trim($process->getErrorOutput());
        if ($detalle === '') {
            $detalle = trim($process->getOutput());
        }

        return [
            'ok' => false,
            'mensaje' => $detalle !== '' ? $detalle : 'Falló el comando de '.$salida->nombre,
            'medio' => $medio,
        ];
    }
}
