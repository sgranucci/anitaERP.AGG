<?php

declare(strict_types=1);

namespace App\Services\Configuracion;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Descarga el padrón RG ARBA (DFE) equivalente a /home/sergio/padronarba/bajapadron.sh.
 */
class PadronIibbArbaDescargaService
{
    public const URL = 'https://dfe.arba.gov.ar/DomicilioElectronico/SeguridadCliente/dfeServicioDescargaPadron.do';

    /**
     * @param  'actual'|'siguiente'  $periodo
     * @return array{zip:string,mes:string,anio:string,fecha_desde:string,fecha_hasta:string,bytes:int}
     */
    public function descargar(string $periodo = 'siguiente', ?string $directorio = null): array
    {
        $user = (string) config('padrones_iibb.arba.user', '');
        $password = (string) config('padrones_iibb.arba.password', '');
        if ($user === '' || $password === '') {
            throw new RuntimeException(
                'Faltan ARBA_DFE_USER / ARBA_DFE_PASSWORD en .env (credenciales DFE ARBA).'
            );
        }

        $dir = $directorio ?: (string) config('padrones_iibb.arba.directorio');
        if ($dir === '') {
            $dir = storage_path('app/padrones/arba');
        }
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException("No se pudo crear directorio: {$dir}");
        }
        if (! is_writable($dir)) {
            throw new RuntimeException("Directorio no escribible: {$dir}");
        }

        $ref = $periodo === 'actual'
            ? Carbon::now()
            : Carbon::now()->addMonthNoOverflow();

        $fechaDesde = $ref->copy()->startOfMonth()->format('Ymd');
        $fechaHasta = $ref->copy()->endOfMonth()->format('Ymd');
        $mes = $ref->format('m');
        $anio = $ref->format('Y');
        $destZip = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'PadronRGS' . $mes . $anio . '.zip';

        $xmlPath = $this->escribirXmlPedido($dir, $fechaDesde, $fechaHasta);
        $curlOut = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dfeServicioDescargaPadron.do';

        Log::info('padron_iibb_arba:descarga:inicio', [
            'periodo' => $periodo,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'dest' => $destZip,
        ]);

        try {
            $this->ejecutarCurl($user, $password, $xmlPath, $curlOut);
        } finally {
            @unlink($xmlPath);
        }

        if (! is_file($curlOut) || filesize($curlOut) < 100) {
            $preview = is_file($curlOut) ? substr((string) file_get_contents($curlOut), 0, 400) : '';
            @unlink($curlOut);
            throw new RuntimeException(
                'Descarga ARBA vacía o inválida. Respuesta: ' . $preview
            );
        }

        // Si ARBA devolvió error HTML/JSON en lugar de zip
        $fh = fopen($curlOut, 'rb');
        $magic = $fh ? (string) fread($fh, 4) : '';
        if ($fh) {
            fclose($fh);
        }
        if ($magic !== "PK\x03\x04" && $magic !== "PK\x05\x06" && $magic !== "PK\x07\x08") {
            $preview = substr((string) file_get_contents($curlOut), 0, 500);
            @unlink($curlOut);
            throw new RuntimeException(
                'ARBA no devolvió un ZIP. Respuesta: ' . $preview
            );
        }

        if (is_file($destZip)) {
            @unlink($destZip);
        }
        if (! rename($curlOut, $destZip)) {
            if (! copy($curlOut, $destZip)) {
                throw new RuntimeException("No se pudo guardar {$destZip}");
            }
            @unlink($curlOut);
        }

        $bytes = (int) filesize($destZip);
        Log::info('padron_iibb_arba:descarga:ok', [
            'zip' => $destZip,
            'bytes' => $bytes,
        ]);

        return [
            'zip' => $destZip,
            'mes' => $mes,
            'anio' => $anio,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'bytes' => $bytes,
        ];
    }

    private function escribirXmlPedido(string $dir, string $fechaDesde, string $fechaHasta): string
    {
        $xml = "<?xml version='1.0' encoding='ISO-8859-1'?>\n"
            . "<DESCARGA-PADRON>\n"
            . "    <fechaDesde>{$fechaDesde}</fechaDesde>\n"
            . "    <fechaHasta>{$fechaHasta}</fechaHasta>\n"
            . "</DESCARGA-PADRON>\n";

        $base = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'DFEServicioDescargaPadron';
        $tmp = $base . '.xml';
        if (file_put_contents($tmp, $xml) === false) {
            throw new RuntimeException('No se pudo escribir XML de pedido ARBA.');
        }
        $md5 = md5_file($tmp);
        if ($md5 === false) {
            @unlink($tmp);
            throw new RuntimeException('No se pudo calcular MD5 del XML ARBA.');
        }
        $hashed = $base . '_' . $md5 . '.xml';
        if (! rename($tmp, $hashed)) {
            @unlink($tmp);
            throw new RuntimeException('No se pudo renombrar XML hasheado ARBA.');
        }

        return $hashed;
    }

    private function ejecutarCurl(string $user, string $password, string $xmlPath, string $outputPath): void
    {
        @unlink($outputPath);

        $process = new Process([
            'curl',
            '-sS',
            '-k',
            '-H', 'Accept: application/json',
            '-o', $outputPath,
            '-F', 'user=' . $user,
            '-F', 'password=' . $password,
            '-F', 'file=@' . $xmlPath,
            self::URL,
        ]);
        $process->setTimeout(max(120, (int) config('padrones_iibb.arba.curl_timeout', 600)));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                'curl ARBA falló: ' . trim($process->getErrorOutput() . ' ' . $process->getOutput())
            );
        }
    }
}
