<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Uif;

use App\Services\Uif\ClienteUifDniAnitaHttpSyncService;
use PHPUnit\Framework\TestCase;

final class ClienteUifDniAnitaHttpSyncServiceTest extends TestCase
{
    public function test_parsea_solo_archivos_nombrados_por_dni(): void
    {
        $html = <<<'HTML'
<html><body>
<a href="31503720.pdf">31503720.pdf</a>
<a href="30282121.PDF">30282121.PDF</a>
<a href="foto.jpg">foto.jpg</a>
<a href="884-DDJJPEP.pdf">884-DDJJPEP.pdf</a>
<a href="/dni_uif/">Parent</a>
</body></html>
HTML;

        $got = ClienteUifDniAnitaHttpSyncService::parsearListadoHtml($html);
        $this->assertContains('31503720.pdf', $got);
        $this->assertContains('30282121.PDF', $got);
        $this->assertCount(2, $got);
    }
}
