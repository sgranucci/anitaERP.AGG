<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownCriterioFecha;
use PHPUnit\Framework\TestCase;

final class TransferenciaCrownCriterioFechaTest extends TestCase
{
    public function test_default_es_jornada(): void
    {
        $this->assertSame(TransferenciaCrownCriterioFecha::JORNADA, TransferenciaCrownCriterioFecha::normalizar(null));
        $this->assertSame(TransferenciaCrownCriterioFecha::JORNADA, TransferenciaCrownCriterioFecha::normalizar(''));
        $this->assertSame(TransferenciaCrownCriterioFecha::JORNADA, TransferenciaCrownCriterioFecha::normalizar('otra'));
    }

    public function test_acepta_fisica(): void
    {
        $this->assertSame(TransferenciaCrownCriterioFecha::FISICA, TransferenciaCrownCriterioFecha::normalizar('fisica'));
        $this->assertSame(TransferenciaCrownCriterioFecha::FISICA, TransferenciaCrownCriterioFecha::normalizar('FISICA'));
    }

    public function test_campo_anita(): void
    {
        $this->assertSame('ven_fecha_vto', TransferenciaCrownCriterioFecha::campoAnita('jornada'));
        $this->assertSame('ven_fecha', TransferenciaCrownCriterioFecha::campoAnita('fisica'));
    }
}
