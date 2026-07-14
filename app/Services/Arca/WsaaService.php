<?php

namespace App\Services\Arca;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Str;
use SoapClient;

class WsaaService
{
    /**
     * Obtiene Token y Sign para un servicio WSAA.
     *
     * @param  array|null  $context  Si no es null, firma y cachea TA con rutas propias
     *                                 (p. ej. WSFE: storage/app/arca/wsfe). Claves:
     *                                 cert_path, private_key_path, ?private_key_passphrase,
     *                                 ta_storage_dir, cache_key (sufijo único por certificado/CUIT),
     *                                 ?tmp_dir (firma PKCS7)
     */
    public function getTokenSign(string $serviceId, ?array $context = null): array
    {
        $cached = $this->readCachedTa($serviceId, $context);
        if ($cached !== null) {
            return $cached;
        }

        $taXml = $this->requestNewTa($serviceId, $context);
        $parsed = $this->parseTa($taXml);

        $this->writeCachedTa($serviceId, $taXml, $context);

        return [
            'token' => $parsed['token'],
            'sign' => $parsed['sign'],
            'expirationTime' => $parsed['expirationTime'],
        ];
    }

    private function requestNewTa(string $serviceId, ?array $context = null): string
    {
        $traXml = $this->createTraXml($serviceId);
        $cms = $this->signTra($traXml, $context);

        $wsaa = $this->wsaaConfig();

        $client = new SoapClient($wsaa['wsdl'], [
            'soap_version' => SOAP_1_2,
            'location' => $wsaa['url'],
            'trace' => 1,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,
        ]);

        $resp = $client->loginCms(['in0' => $cms]);

        $xml = $resp->loginCmsReturn ?? null;
        if (! is_string($xml) || trim($xml) === '') {
            throw new Exception('WSAA: respuesta vacía en loginCmsReturn');
        }

        return $xml;
    }

    private function createTraXml(string $serviceId): string
    {
        $now = Carbon::now('America/Argentina/Buenos_Aires');

        // WSAA exige generationTime en el pasado y expirationTime en el futuro respecto a *su* reloj.
        // Ventana corta (+10 min) falla si el servidor local va desfasado (xml.expirationTime.expired).
        $gen = $now->copy()->subMinutes(10);
        $exp = $now->copy()->addHours(12);

        $xml = new \SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>'.
            '<loginTicketRequest version="1.0"></loginTicketRequest>'
        );

        $xml->addChild('header');
        $xml->header->addChild('uniqueId', (string) $now->timestamp);
        $xml->header->addChild('generationTime', $gen->format('c'));
        $xml->header->addChild('expirationTime', $exp->format('c'));
        $xml->addChild('service', $serviceId);

        $out = $xml->asXML();
        if ($out === false) {
            throw new Exception('WSAA: no se pudo generar TRA XML');
        }

        return $out;
    }

    private function signTra(string $traXml, ?array $context = null): string
    {
        if ($context !== null) {
            $certPath = $context['cert_path'] ?? null;
            $keyPath = $context['private_key_path'] ?? null;
            $pass = (string) ($context['private_key_passphrase'] ?? '');
            $tmpDir = $context['tmp_dir'] ?? storage_path('app/arca/wsfe/tmp');
        } else {
            $certPath = config('arca.cert_path');
            $keyPath = config('arca.private_key_path');
            $pass = config('arca.private_key_passphrase', '');
            $tmpDir = config('arca.tmp_dir');
            if (! is_string($tmpDir) || $tmpDir === '') {
                $tmpDir = rtrim((string) config('arca.padron_base_storage', ''), '/').'/tmp';
            }
        }

        if (! is_string($certPath) || ! file_exists($certPath)) {
            throw new Exception("WSAA: certificado no encontrado en {$certPath}");
        }
        if (! is_string($keyPath) || ! file_exists($keyPath)) {
            throw new Exception("WSAA: clave privada no encontrada en {$keyPath}");
        }
        try {
            $this->ensureDir($tmpDir, 'directorio temporal');
        } catch (Exception $e) {
            // Fallback: para la firma PKCS7, alcanza con un tmp del sistema.
            $tmpDir = rtrim(sys_get_temp_dir(), '/').'/anitaERP/arca/sr_padron/tmp';
            $this->ensureDir($tmpDir, 'directorio temporal (fallback /tmp)');
        }

        $base = $tmpDir.'/'.Str::uuid()->toString();
        $traFile = $base.'.xml';
        $tmpSigned = $base.'.p7s';

        file_put_contents($traFile, $traXml);

        $ok = openssl_pkcs7_sign(
            $traFile,
            $tmpSigned,
            'file://'.$certPath,
            ['file://'.$keyPath, (string) $pass],
            [],
            ! PKCS7_DETACHED
        );

        if (! $ok) {
            @unlink($traFile);
            @unlink($tmpSigned);
            throw new Exception('WSAA: error generando firma PKCS7 (openssl_pkcs7_sign)');
        }

        $content = file_get_contents($tmpSigned);
        @unlink($traFile);
        @unlink($tmpSigned);

        if (! is_string($content) || $content === '') {
            throw new Exception('WSAA: firma PKCS7 vacía');
        }

        // Eliminar encabezado MIME (primeras 4 líneas) como en el ejemplo oficial.
        $lines = preg_split("/\r\n|\n|\r/", $content);
        $cms = implode("\n", array_slice($lines, 4));
        $cms = trim($cms)."\n";

        return $cms;
    }

    private function parseTa(string $taXml): array
    {
        $xml = @simplexml_load_string($taXml);
        if ($xml === false) {
            throw new Exception('WSAA: no se pudo parsear TA XML');
        }

        $token = (string) ($xml->credentials->token ?? '');
        $sign = (string) ($xml->credentials->sign ?? '');
        $exp = (string) ($xml->header->expirationTime ?? '');

        if ($token === '' || $sign === '' || $exp === '') {
            throw new Exception('WSAA: TA sin token/sign/expirationTime');
        }

        return [
            'token' => $token,
            'sign' => $sign,
            'expirationTime' => $exp,
        ];
    }

    private function readCachedTa(string $serviceId, ?array $context = null): ?array
    {
        $file = $this->taFile($serviceId, $context);
        if (! file_exists($file)) {
            return null;
        }

        $xml = file_get_contents($file);
        if (! is_string($xml) || trim($xml) === '') {
            return null;
        }

        try {
            $parsed = $this->parseTa($xml);
        } catch (Exception $e) {
            return null;
        }

        try {
            $exp = Carbon::parse($parsed['expirationTime'], 'America/Argentina/Buenos_Aires');
        } catch (Exception $e) {
            return null;
        }

        // Margen: si vence en menos de 2 minutos, pedimos uno nuevo.
        if ($exp->lessThanOrEqualTo(Carbon::now('America/Argentina/Buenos_Aires')->addMinutes(2))) {
            return null;
        }

        return $parsed;
    }

    private function writeCachedTa(string $serviceId, string $taXml, ?array $context = null): void
    {
        $dir = $context !== null
            ? ($context['ta_storage_dir'] ?? null)
            : config('arca.ta_storage_dir');
        if (! is_string($dir) || $dir === '') {
            throw new Exception('WSAA: ta_storage_dir inválido');
        }
        $this->ensureDir($dir, 'cache TA');

        $file = $this->taFile($serviceId, $context);
        file_put_contents($file, $taXml);
        // CLI (umask 022) deja 0644 y php-fpm (www-data) no puede renovar el TA.
        @chmod($file, 0664);
    }

    private function taFile(string $serviceId, ?array $context = null): string
    {
        $env = (string) config('arca.env', 'homo');
        $safe = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $serviceId);
        if ($context !== null) {
            $dir = (string) ($context['ta_storage_dir'] ?? '');
            $suffix = isset($context['cache_key']) ? '__'.preg_replace('/[^a-zA-Z0-9_.-]/', '_', (string) $context['cache_key']) : '';
        } else {
            $dir = (string) config('arca.ta_storage_dir');
            $suffix = '';
        }

        return rtrim($dir, '/')."/ta_{$env}_{$safe}{$suffix}.xml";
    }

    private function wsaaConfig(): array
    {
        $env = (string) config('arca.env', 'homo');
        $cfg = config("arca.wsaa.{$env}");

        if (! is_array($cfg) || empty($cfg['wsdl']) || empty($cfg['url'])) {
            throw new Exception("WSAA: configuración inválida para env={$env}");
        }

        return $cfg;
    }

    private function ensureDir(string $dir, string $purpose): void
    {
        if (is_dir($dir)) {
            if (! is_writable($dir)) {
                @chmod($dir, 0775);
            }
            if (! is_writable($dir)) {
                throw new Exception(
                    "WSAA: {$purpose} existe pero no es escribible: '{$dir}'. ".
                    'Ej.: chmod -R g+rwX sobre storage/app/arca/sr_padron (y wsfe) para el grupo del servidor web.'
                );
            }

            return;
        }

        // 0775: típico para aplicaciones web (owner+group write).
        // El umask del proceso puede recortar permisos.
        if (! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $err = error_get_last();
            $msg = $err['message'] ?? 'sin detalle';
            throw new Exception("WSAA: no se pudo crear {$purpose} en '{$dir}' ({$msg})");
        }

        if (! is_writable($dir)) {
            throw new Exception("WSAA: {$purpose} creado pero no es escribible: '{$dir}'");
        }
    }
}
