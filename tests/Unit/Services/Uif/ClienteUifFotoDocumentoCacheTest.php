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
}
