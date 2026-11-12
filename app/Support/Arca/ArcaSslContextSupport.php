<?php

namespace App\Support\Arca;

/**
 * Contexto SSL para SoapClient / file_get_contents hacia hosts ARCA.
 *
 * Desde oct/2026 aws.arca.gob.ar presenta el leaf firmado por
 * "Sectigo Public Server Authentication CA OV R36" sin enviar el intermedio.
 * Sin ese CA en el trust store, OpenSSL responde "unable to get local issuer
 * certificate" y PHP SoapClient lo traduce a "Could not connect to host".
 */
final class ArcaSslContextSupport
{
    private const INTERMEDIO_REL = 'arca/ca/sectigo_public_server_authentication_ca_ov_r36.crt';

    private const BUNDLE_REL = 'arca/ca/cacert_bundle.pem';

    /**
     * @return array{verify_peer: bool, verify_peer_name: bool, ciphers: string, cafile?: string}
     */
    public static function sslOptions(): array
    {
        $options = [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'ciphers' => 'DEFAULT@SECLEVEL=1',
        ];

        $cafile = self::cafile();
        if ($cafile !== null) {
            $options['cafile'] = $cafile;
        }

        return $options;
    }

    /**
     * Bundle = CAs del sistema + intermedio Sectigo OV R36 (versionado en resources/).
     */
    public static function cafile(): ?string
    {
        $intermedio = resource_path(self::INTERMEDIO_REL);
        if (! is_readable($intermedio)) {
            return null;
        }

        $bundle = storage_path('app/'.self::BUNDLE_REL);
        $dir = dirname($bundle);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return is_readable($intermedio) ? $intermedio : null;
        }

        $sistema = self::sistemaCaBundle();
        $mtimeIntermedio = (int) @filemtime($intermedio);
        $mtimeSistema = $sistema !== null ? (int) @filemtime($sistema) : 0;
        $mtimeBundle = is_readable($bundle) ? (int) @filemtime($bundle) : 0;

        if ($mtimeBundle > 0 && $mtimeBundle >= $mtimeIntermedio && $mtimeBundle >= $mtimeSistema) {
            return $bundle;
        }

        $partes = [];
        if ($sistema !== null && is_readable($sistema)) {
            $partes[] = (string) file_get_contents($sistema);
        }
        $partes[] = trim((string) file_get_contents($intermedio));

        $contenido = implode("\n", array_filter($partes, static fn (string $p): bool => $p !== ''))."\n";
        if (@file_put_contents($bundle, $contenido) === false) {
            return $intermedio;
        }

        @chmod($bundle, 0664);

        return $bundle;
    }

    private static function sistemaCaBundle(): ?string
    {
        $candidatos = [
            (string) ini_get('openssl.cafile'),
            (string) ini_get('curl.cainfo'),
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
            '/etc/ssl/cert.pem',
        ];

        foreach ($candidatos as $ruta) {
            $ruta = trim($ruta);
            if ($ruta !== '' && is_readable($ruta)) {
                return $ruta;
            }
        }

        return null;
    }
}
