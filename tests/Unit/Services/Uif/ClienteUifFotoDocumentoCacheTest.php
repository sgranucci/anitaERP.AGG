<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Uif;

use App\Services\Uif\ClienteUifFotoDocumento;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

final class ClienteUifFotoDocumentoCacheTest extends TestCase
{
    public function test_version_cache_cambia_al_reemplazar_el_archivo(): void
    {
        $dir = sys_get_temp_dir();
        $path = $dir.DIRECTORY_SEPARATOR.'uif_dni_cache_'.uniqid('', true).'.jpg';
        file_put_contents($path, 'foto-vieja');
        touch($path, time() - 3600);
        clearstatcache(true, $path);

        $vieja = ClienteUifFotoDocumento::versionCache($path);
        $this->assertSame((string) filemtime($path), $vieja);

        file_put_contents($path, 'foto-nueva');
        touch($path, time());
        clearstatcache(true, $path);

        $nueva = ClienteUifFotoDocumento::versionCache($path);
        $this->assertNotSame($vieja, $nueva);
        $this->assertSame(['v' => $nueva], ClienteUifFotoDocumento::queryVersion($path));

        @unlink($path);
    }

    public function test_version_cache_sin_archivo_no_queda_vacia(): void
    {
        $v = ClienteUifFotoDocumento::versionCache('/tmp/no-existe-uif-dni-'.uniqid('', true).'.jpg');
        $this->assertNotSame('', $v);
        $this->assertTrue(ctype_digit($v));
    }

    public function test_anti_cache_quita_public_y_pide_revalidar(): void
    {
        $response = new Response();
        $response->setPublic();

        ClienteUifFotoDocumento::aplicarAntiCacheNavegador($response);

        $cacheControl = strtolower((string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringNotContainsString('public', $cacheControl);
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
        $this->assertSame('0', $response->headers->get('Expires'));
    }

    public function test_anti_cache_corrige_file_response_publico_de_laravel(): void
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'uif_dni_headers_'.uniqid('', true).'.jpg';
        file_put_contents($path, 'x');

        $response = new BinaryFileResponse($path, 200, [], true);
        ClienteUifFotoDocumento::aplicarAntiCacheNavegador($response);

        $cacheControl = strtolower((string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringNotContainsString('public', $cacheControl);

        @unlink($path);
    }

    public function test_encuentra_dni_en_subcarpeta_dni_viejos(): void
    {
        $mount = sys_get_temp_dir().DIRECTORY_SEPARATOR.'uif_dni_viejos_'.uniqid('', true);
        $nested = $mount.DIRECTORY_SEPARATOR.'DNI VIEJOS';
        self::assertTrue(mkdir($nested, 0777, true));
        $pdf = $nested.DIRECTORY_SEPARATOR.'95275896.pdf';
        file_put_contents($pdf, '%PDF-1.4');

        $this->assertSame($pdf, ClienteUifFotoDocumento::findInDniMountTree($mount, '95275896'));
        $this->assertSame($pdf, ClienteUifFotoDocumento::findBasenameInDniMountTree($mount, '95275896.pdf'));

        $this->rmTree($mount);
    }

    public function test_encuentra_dni_en_subcarpeta_kandiko_rebisco(): void
    {
        $mount = sys_get_temp_dir().DIRECTORY_SEPARATOR.'uif_dni_mount_'.uniqid('', true);
        $nested = $mount.DIRECTORY_SEPARATOR.'Kandiko'.DIRECTORY_SEPARATOR.'rebisco';
        self::assertTrue(mkdir($nested, 0777, true));
        $pdf = $nested.DIRECTORY_SEPARATOR.'30282121.pdf';
        file_put_contents($pdf, '%PDF-1.4');

        $porStem = ClienteUifFotoDocumento::findInDniMountTree($mount, '30282121');
        $porNombre = ClienteUifFotoDocumento::findBasenameInDniMountTree($mount, '30282121.pdf');

        $this->assertSame($pdf, $porStem);
        $this->assertSame($pdf, $porNombre);
        $this->assertNull(ClienteUifFotoDocumento::findInDniMountTree($mount, '99999999'));

        $this->rmTree($mount);
    }

    public function test_promueve_dni_de_subcarpeta_sin_pisar_el_de_la_raiz(): void
    {
        $mount = sys_get_temp_dir().DIRECTORY_SEPARATOR.'uif_dni_promo_'.uniqid('', true);
        $nested = $mount.DIRECTORY_SEPARATOR.'Kandiko'.DIRECTORY_SEPARATOR.'rebisco';
        self::assertTrue(mkdir($nested, 0777, true));
        $src = $nested.DIRECTORY_SEPARATOR.'30282121.pdf';
        file_put_contents($src, 'scan-rebisco');

        $dest = ClienteUifFotoDocumento::promoverADniMountCanonico($src, '30282121', $mount);
        $this->assertSame($mount.DIRECTORY_SEPARATOR.'30282121.pdf', $dest);
        $this->assertSame('scan-rebisco', file_get_contents((string) $dest));

        file_put_contents($nested.DIRECTORY_SEPARATOR.'30282121.pdf', 'scan-nuevo');
        $otraVez = ClienteUifFotoDocumento::promoverADniMountCanonico(
            $nested.DIRECTORY_SEPARATOR.'30282121.pdf',
            '30282121',
            $mount
        );
        $this->assertSame('scan-rebisco', file_get_contents((string) $otraVez));

        $this->rmTree($mount);
    }

    public function test_no_trata_retrato_de_tesoreria_como_dni(): void
    {
        $teso = sys_get_temp_dir().DIRECTORY_SEPARATOR.'uif_teso_'.uniqid('', true);
        $mount = sys_get_temp_dir().DIRECTORY_SEPARATOR.'uif_dni_teso_'.uniqid('', true);
        self::assertTrue(mkdir($teso, 0777, true));
        self::assertTrue(mkdir($mount, 0777, true));
        $retrato = $teso.DIRECTORY_SEPARATOR.'31503720.jpg';
        file_put_contents($retrato, 'retrato-caja');
        $copia = $mount.DIRECTORY_SEPARATOR.'31503720.jpg';
        file_put_contents($copia, 'retrato-caja');

        $this->assertTrue(ClienteUifFotoDocumento::esRutaFotoTesoreria($retrato, [$teso]));
        $this->assertFalse(ClienteUifFotoDocumento::esRutaFotoTesoreria($copia, [$teso]));
        $this->assertTrue(ClienteUifFotoDocumento::esCopiaDeFotoTesoreria($copia, '31503720', [$teso]));
        $this->assertFalse(ClienteUifFotoDocumento::esCopiaDeFotoTesoreria($retrato, '31503720', [$teso]));

        $this->rmTree($teso);
        $this->rmTree($mount);
    }

    public function test_no_usa_declaracion_ni_nosis_como_foto_dni(): void
    {
        $this->assertTrue(ClienteUifFotoDocumento::esNombreAdjuntoNoDni('319-DDJJPEP_Maidanaj_260425.pdf'));
        $this->assertTrue(ClienteUifFotoDocumento::esNombreAdjuntoNoDni('DDJJ_24000014_MAIDANA.pdf'));
        $this->assertTrue(ClienteUifFotoDocumento::esNombreAdjuntoNoDni('319-MAIDANA_NOSIS_290425.pdf'));
        $this->assertFalse(ClienteUifFotoDocumento::esNombreAdjuntoNoDni('24000014.pdf'));
        $this->assertFalse(ClienteUifFotoDocumento::esNombreAdjuntoNoDni('dni_frente_24000014.jpg'));

        $this->assertNull(ClienteUifFotoDocumento::puntajeCandidatoFotoDni('319-DDJJPEP_Maidanaj_260425.pdf', '24000014'));
        $this->assertNull(ClienteUifFotoDocumento::puntajeCandidatoFotoDni('DDJJ_24000014_MAIDANA.pdf', '24000014'));
        $this->assertSame(0, ClienteUifFotoDocumento::puntajeCandidatoFotoDni('319-MAIDANAJUSTAVOALBERTO_290425.pdf', '24000014'));
        $this->assertSame(100, ClienteUifFotoDocumento::puntajeCandidatoFotoDni('24000014.pdf', '24000014'));
        $this->assertGreaterThan(0, ClienteUifFotoDocumento::puntajeCandidatoFotoDni('dni_frente_maidana.jpg', '24000014'));
    }

    public function test_promueve_solo_archivos_nombrados_por_dni(): void
    {
        $mount = sys_get_temp_dir().DIRECTORY_SEPARATOR.'uif_dni_batch_'.uniqid('', true);
        $nested = $mount.DIRECTORY_SEPARATOR.'Kandiko'.DIRECTORY_SEPARATOR.'rebisco';
        self::assertTrue(mkdir($nested, 0777, true));
        file_put_contents($nested.DIRECTORY_SEPARATOR.'11111111.pdf', 'a');
        file_put_contents($nested.DIRECTORY_SEPARATOR.'GEREZDANIELA_200723.pdf', 'no-copiar');
        file_put_contents($mount.DIRECTORY_SEPARATOR.'22222222.pdf', 'ya');
        file_put_contents($nested.DIRECTORY_SEPARATOR.'22222222.pdf', 'otro');

        $stats = ClienteUifFotoDocumento::promoverNumeradosDesdeExtraDirs($mount);
        $this->assertSame(1, $stats['copiados']);
        $this->assertSame(1, $stats['ya_estaban']);
        $this->assertGreaterThanOrEqual(1, $stats['omitidos']);
        $this->assertFileExists($mount.DIRECTORY_SEPARATOR.'11111111.pdf');
        $this->assertSame('ya', file_get_contents($mount.DIRECTORY_SEPARATOR.'22222222.pdf'));
        $this->assertFileDoesNotExist($mount.DIRECTORY_SEPARATOR.'GEREZDANIELA_200723.pdf');

        $this->rmTree($mount);
    }

    private function rmTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($dir);
    }
}
