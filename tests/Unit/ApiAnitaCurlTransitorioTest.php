<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\ApiAnita;
use PHPUnit\Framework\TestCase;

final class ApiAnitaCurlTransitorioTest extends TestCase
{
    public function test_empty_reply_es_transitorio(): void
    {
        $this->assertTrue(ApiAnita::esErrorCurlTransitorio('Empty reply from server'));
    }

    public function test_error_informix_no_es_transitorio(): void
    {
        $this->assertFalse(ApiAnita::esErrorCurlTransitorio('239: Unique constraint'));
        $this->assertFalse(ApiAnita::esErrorCurlTransitorio(null));
        $this->assertFalse(ApiAnita::esErrorCurlTransitorio(''));
    }
}
