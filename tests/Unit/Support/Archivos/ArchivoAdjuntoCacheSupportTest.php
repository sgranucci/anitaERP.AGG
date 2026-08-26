<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Archivos;

use App\Support\Archivos\ArchivoAdjuntoCacheSupport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class ArchivoAdjuntoCacheSupportTest extends TestCase
{
    public function test_con_version_agrega_query_y_cambia_al_reemplazar(): void
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'adjunto_cache_'.uniqid('', true).'.pdf';
        file_put_contents($path, 'viejo');
        touch($path, time() - 3600);
        clearstatcache(true, $path);

        $url = ArchivoAdjuntoCacheSupport::conVersion('https://erp.test/archivo', $path);
        $this->assertSame('https://erp.test/archivo?v='.filemtime($path), $url);

        $urlConQuery = ArchivoAdjuntoCacheSupport::conVersion('https://erp.test/archivo?inline=1', $path);
        $this->assertSame('https://erp.test/archivo?inline=1&v='.filemtime($path), $urlConQuery);

        file_put_contents($path, 'nuevo');
        touch($path, time());
        clearstatcache(true, $path);

        $urlNueva = ArchivoAdjuntoCacheSupport::conVersion('https://erp.test/archivo', $path);
        $this->assertNotSame($url, $urlNueva);
        $this->assertSame('https://erp.test/archivo?v='.filemtime($path), $urlNueva);

        @unlink($path);
    }

    public function test_con_version_no_toca_url_si_no_hay_archivo(): void
    {
        $url = 'https://erp.test/archivo?inline=1';
        $this->assertSame(
            $url,
            ArchivoAdjuntoCacheSupport::conVersion($url, '/tmp/no-existe-adjunto-'.uniqid('', true).'.pdf')
        );
    }

    public function test_anti_cache_quita_public(): void
    {
        $response = new Response();
        $response->setPublic();
        ArchivoAdjuntoCacheSupport::aplicarAntiCacheNavegador($response);

        $cacheControl = strtolower((string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringNotContainsString('public', $cacheControl);
    }
}
