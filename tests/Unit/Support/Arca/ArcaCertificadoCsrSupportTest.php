<?php

namespace Tests\Unit\Support\Arca;

use App\Support\Arca\ArcaCertificadoCsrSupport;
use PHPUnit\Framework\TestCase;

class ArcaCertificadoCsrSupportTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/anita-arca-csr-'.bin2hex(random_bytes(4));
        mkdir($this->dir, 0700, true);
    }

    protected function tearDown(): void
    {
        if ($this->dir !== '' && is_dir($this->dir)) {
            foreach (glob($this->dir.'/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->dir);
        }
        parent::tearDown();
    }

    public function test_dn_copia_alias_cuit_y_organizacion(): void
    {
        $dn = ArcaCertificadoCsrSupport::dnDesdeSubject([
            'CN' => 'serverbiyemas',
            'serialNumber' => 'CUIT 30682403671',
            'O' => 'biyemas sa',
            'C' => 'ar',
        ]);

        $this->assertSame('serverbiyemas', $dn['commonName']);
        $this->assertSame('CUIT 30682403671', $dn['serialNumber']);
        $this->assertSame('biyemas sa', $dn['organizationName']);
        $this->assertSame('ar', $dn['countryName']);
    }

    public function test_alias_override_reemplaza_solo_cn(): void
    {
        $dn = ArcaCertificadoCsrSupport::dnDesdeSubject([
            'CN' => 'remelec',
            'serialNumber' => 'CUIT 30505150372',
        ], 'remelec_nuevo');

        $this->assertSame('remelec_nuevo', $dn['commonName']);
        $this->assertSame('CUIT 30505150372', $dn['serialNumber']);
    }

    public function test_genera_csr_con_serial_cuit_del_certificado_actual(): void
    {
        $origen = $this->dir.'/origen';
        mkdir($origen, 0700, true);
        $this->emitirCertificadoAutofirmado(
            $origen,
            [
                'commonName' => 'factdetallada',
                'serialNumber' => 'CUIT 30505150372',
            ]
        );

        $leido = ArcaCertificadoCsrSupport::leerCertificado($origen.'/cert.crt');
        $this->assertSame('factdetallada', $leido['alias']);
        $this->assertSame('30505150372', $leido['cuit']);

        $renov = $this->dir.'/renovacion';
        $gen = ArcaCertificadoCsrSupport::generar($renov, $leido['dn']);

        $this->assertFileExists($gen['csr_path']);
        $this->assertFileExists($gen['key_path']);
        $this->assertStringContainsString('factdetallada', $gen['subject']);
        $this->assertStringContainsString('CUIT 30505150372', $gen['subject']);
        $this->assertFalse(
            ArcaCertificadoCsrSupport::certCoincideConClave($origen.'/cert.crt', $gen['key_path']),
            'La clave nueva no debe ser la de producción'
        );
    }

    /**
     * @param  array<string, string>  $dn
     */
    private function emitirCertificadoAutofirmado(string $dir, array $dn): void
    {
        $cnf = $dir.'/openssl.cnf';
        file_put_contents($cnf, ArcaCertificadoCsrSupport::construirOpensslCnf($dn));
        $config = [
            'config' => $cnf,
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'encrypt_key' => false,
        ];
        $key = openssl_pkey_new($config);
        $this->assertNotFalse($key);
        $csr = openssl_csr_new($dn, $key, $config);
        $this->assertNotFalse($csr);
        $cert = openssl_csr_sign($csr, null, $key, 365, $config, 1);
        $this->assertNotFalse($cert);
        openssl_x509_export($cert, $certOut);
        openssl_pkey_export($key, $keyOut);
        file_put_contents($dir.'/cert.crt', $certOut);
        file_put_contents($dir.'/privada.key', $keyOut);
    }
}
