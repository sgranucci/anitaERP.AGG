<?php

namespace Tests\Unit\Support\Wigos;

use App\Support\Wigos\WigosTrackdataNormalizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class WigosTrackdataNormalizerTest extends TestCase
{
    public function test_quita_sentinelas_de_lector_magnetic(): void
    {
        $this->assertSame(
            '1234567890',
            WigosTrackdataNormalizer::normalizar(';1234567890?')
        );
    }

    public function test_quita_asterisco_inicial_como_cupon(): void
    {
        $this->assertSame(
            'ABC123',
            WigosTrackdataNormalizer::normalizar('*ABC123')
        );
    }

    public function test_quita_caracteres_de_control(): void
    {
        $this->assertSame(
            'TRACK',
            WigosTrackdataNormalizer::normalizar("TRACK\r\n")
        );
    }

    public function test_vacio_lanza_excepcion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        WigosTrackdataNormalizer::normalizar('   ');
    }
}
