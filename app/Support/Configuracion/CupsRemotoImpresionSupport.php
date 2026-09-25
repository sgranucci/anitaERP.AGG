<?php

namespace App\Support\Configuracion;

use Illuminate\Support\Facades\Log;

/**
 * Envía archivos raw/texto a una cola CUPS remota vía IPP.
 *
 * En Ferli L12 no hay `lp` local: las térmicas "calidad" / "armado" viven
 * en el CUPS del L8 (socket://calidad:9100 resuelto en ese host).
 */
final class CupsRemotoImpresionSupport
{
    /**
     * @return array{ok: bool, mensaje: string}
     */
    public static function imprimirArchivo(string $rutaArchivo, ?string $cola = null, ?string $host = null): array
    {
        $cola = trim((string) ($cola ?? config('impresion_termica.cola_empaque', 'calidad')));
        $host = trim((string) ($host ?? config('impresion_termica.cups_host', '160.132.0.209')));
        $port = (int) config('impresion_termica.cups_port', 631);
        $timeout = (int) config('impresion_termica.timeout_segundos', 30);

        if ($cola === '' || $host === '') {
            return ['ok' => false, 'mensaje' => 'Falta cola o host CUPS para impresión térmica.'];
        }

        if (! is_file($rutaArchivo) || ! is_readable($rutaArchivo)) {
            return ['ok' => false, 'mensaje' => 'No se encuentra el archivo a imprimir.'];
        }

        $contenido = file_get_contents($rutaArchivo);
        if ($contenido === false) {
            return ['ok' => false, 'mensaje' => 'No se pudo leer el archivo a imprimir.'];
        }

        $printerUri = 'ipp://'.$host.'/printers/'.$cola;
        $path = '/printers/'.$cola;

        $body = self::construirPrintJob($printerUri, $contenido);

        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($host, $port, $errno, $errstr, min(10, $timeout));
        if ($fp === false) {
            return [
                'ok' => false,
                'mensaje' => 'No se pudo conectar a CUPS '.$host.':'.$port.' ('.$errstr.').',
            ];
        }

        stream_set_timeout($fp, $timeout);

        $headers = "POST {$path} HTTP/1.1\r\n"
            ."Host: {$host}:{$port}\r\n"
            ."Content-Type: application/ipp\r\n"
            .'Content-Length: '.strlen($body)."\r\n"
            ."Connection: close\r\n\r\n";

        fwrite($fp, $headers.$body);
        $respuesta = stream_get_contents($fp) ?: '';
        fclose($fp);

        if (! preg_match('/^HTTP\/\d\.\d\s+(\d+)/', $respuesta, $mHttp)) {
            return ['ok' => false, 'mensaje' => 'Respuesta inválida de CUPS al imprimir en «'.$cola.'».'];
        }

        $httpStatus = (int) $mHttp[1];
        $posBody = strpos($respuesta, "\r\n\r\n");
        $ippBody = $posBody === false ? '' : substr($respuesta, $posBody + 4);

        if ($httpStatus < 200 || $httpStatus >= 300 || strlen($ippBody) < 4) {
            Log::warning('CupsRemotoImpresionSupport HTTP error', [
                'host' => $host,
                'cola' => $cola,
                'http' => $httpStatus,
            ]);

            return [
                'ok' => false,
                'mensaje' => 'CUPS rechazó la impresión en «'.$cola.'» (HTTP '.$httpStatus.').',
            ];
        }

        $ippStatus = unpack('n', substr($ippBody, 2, 2))[1] ?? 0xFFFF;
        // 0x0000..0x00FF = successful-*; 0x0100.. = info/redirection/client/server error
        if ($ippStatus > 0x00FF) {
            Log::warning('CupsRemotoImpresionSupport IPP error', [
                'host' => $host,
                'cola' => $cola,
                'ipp' => sprintf('0x%04X', $ippStatus),
            ]);

            return [
                'ok' => false,
                'mensaje' => 'CUPS no pudo imprimir en «'.$cola.'» (IPP '.sprintf('0x%04X', $ippStatus).').',
            ];
        }

        return [
            'ok' => true,
            'mensaje' => 'Etiqueta enviada a la cola «'.$cola.'».',
        ];
    }

    private static function construirPrintJob(string $printerUri, string $documento): string
    {
        $body = pack('CCnN', 1, 1, 0x0002, 1); // IPP 1.1 Print-Job, request-id 1
        $body .= "\x01"; // operation-attributes-tag
        $body .= self::attr(0x47, 'attributes-charset', 'utf-8');
        $body .= self::attr(0x48, 'attributes-natural-language', 'es');
        $body .= self::attr(0x45, 'printer-uri', $printerUri);
        $body .= self::attr(0x42, 'requesting-user-name', 'anitaERP');
        $body .= self::attr(0x49, 'document-format', 'application/octet-stream');
        $body .= "\x03"; // end-of-attributes-tag
        $body .= $documento;

        return $body;
    }

    private static function attr(int $tag, string $name, string $value): string
    {
        return chr($tag)
            .pack('n', strlen($name)).$name
            .pack('n', strlen($value)).$value;
    }
}
