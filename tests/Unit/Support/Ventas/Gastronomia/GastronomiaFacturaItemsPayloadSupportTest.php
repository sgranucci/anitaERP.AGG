<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Emision;
use App\Support\Ventas\Gastronomia\GastronomiaFacturaItemsPayloadSupport;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class GastronomiaFacturaItemsPayloadSupportTest extends TestCase
{
    public function test_desde_venta_emisiones_reconstruye_opcionales_cero(): void
    {
        $venta = new Venta;
        $venta->setRelation('venta_emisiones', new Collection([
            $this->emision(1, 5029, 1., 6100., 3),
            $this->emision(2, 11545, 1., 0., 3),
        ]));

        $items = GastronomiaFacturaItemsPayloadSupport::desdeVentaEmisiones($venta);

        $this->assertSame([5029, 11545], $items['articulo_ids']);
        $this->assertSame([6100., 0.], $items['precios']);
        $this->assertSame([['1' => 11545]], array_values($items['opcionales_por_item']));
        $this->assertSame([false, true], $items['omitir_stkmov_anita_por_item']);
    }

    private function emision(int $numero, int $articuloId, float $cant, float $precio, int $impuestoId): Venta_Emision
    {
        $em = new Venta_Emision;
        $em->numeroitem = $numero;
        $em->articulo_id = $articuloId;
        $em->cantidad = $cant;
        $em->precio = $precio;
        $em->impuesto_id = $impuestoId;
        $em->incluyeimpuesto = '1';
        $em->detalle = 'test';

        return $em;
    }
}
