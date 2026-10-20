<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\ControlInventarioItemsClasificacionSupport as Clasif;
use PHPUnit\Framework\TestCase;

class ControlInventarioItemsClasificacionSupportTest extends TestCase
{
    public function test_clasifica_recepcion_devolucion_ajuste_consumo_y_otros(): void
    {
        $this->assertSame(Clasif::BUCKET_RECEPCIONES, Clasif::bucketDeAbreviatura('RCING'));
        $this->assertSame(Clasif::BUCKET_DEVOLUCIONES, Clasif::bucketDeAbreviatura('RCDEV'));
        $this->assertSame(Clasif::BUCKET_AJUSTES, Clasif::bucketDeAbreviatura('RCAJP'));
        $this->assertSame(Clasif::BUCKET_AJUSTES, Clasif::bucketDeAbreviatura('ENT'));
        $this->assertSame(Clasif::BUCKET_CONSUMOS, Clasif::bucketDeAbreviatura('AJCON'));
        $this->assertSame(Clasif::BUCKET_CONSUMOS, Clasif::bucketDeAbreviatura('SVIAN'));
        $this->assertSame(Clasif::BUCKET_OTROS, Clasif::bucketDeAbreviatura('TRA'));
        $this->assertSame(Clasif::BUCKET_OTROS, Clasif::bucketDeAbreviatura('PRSAL'));
        $this->assertSame(Clasif::BUCKET_OTROS, Clasif::bucketDeAbreviatura(''));
    }

    public function test_lista_abreviaturas_en_otros(): void
    {
        $otros = Clasif::abreviaturasEnOtros(['RCING', 'TRA', 'TRCONT', 'AJCON', 'FOO']);
        $this->assertSame(['FOO', 'TRA', 'TRCONT'], $otros);
    }
}
