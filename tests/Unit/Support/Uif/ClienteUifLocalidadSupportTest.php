<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Uif;

use App\Support\Uif\ClienteUifLocalidadSupport;
use PHPUnit\Framework\TestCase;

final class ClienteUifLocalidadSupportTest extends TestCase
{
    public function test_id_vacio_cero_o_blanco_queda_null(): void
    {
        $this->assertNull(ClienteUifLocalidadSupport::idEnteroONull(null));
        $this->assertNull(ClienteUifLocalidadSupport::idEnteroONull(''));
        $this->assertNull(ClienteUifLocalidadSupport::idEnteroONull('  '));
        $this->assertNull(ClienteUifLocalidadSupport::idEnteroONull(0));
        $this->assertNull(ClienteUifLocalidadSupport::idEnteroONull('0'));
        $this->assertSame(112, ClienteUifLocalidadSupport::idEnteroONull('112'));
        $this->assertSame(274, ClienteUifLocalidadSupport::idEnteroONull(274));
    }

    public function test_si_el_combo_llega_vacio_recupera_la_localidad_previa(): void
    {
        $this->assertSame(112, ClienteUifLocalidadSupport::idConFallback('', '112'));
        $this->assertSame(274, ClienteUifLocalidadSupport::idConFallback(null, 274));
        $this->assertSame(99, ClienteUifLocalidadSupport::idConFallback('99', '112'));
        $this->assertNull(ClienteUifLocalidadSupport::idConFallback('', ''));
        $this->assertNull(ClienteUifLocalidadSupport::idConFallback(0, 0));
    }

    public function test_aplicar_no_borra_localidades_si_el_post_viene_vacio_con_previa(): void
    {
        $data = ClienteUifLocalidadSupport::aplicar([
            'localidad_uif_id' => '',
            'localidad_uif_id_previa' => '274',
            'provincia_uif_id' => '1',
            'localidadnacimiento_id' => '',
            'localidadnacimiento_id_previa' => '112',
            'provincianacimiento_id' => '2',
        ], fn () => $this->fail('no debe consultar provincia si ya está informada'));

        $this->assertSame(274, $data['localidad_uif_id']);
        $this->assertSame(1, $data['provincia_uif_id']);
        $this->assertSame(112, $data['localidadnacimiento_id']);
        $this->assertSame(2, $data['provincianacimiento_id']);
    }

    public function test_completa_provincia_de_nacimiento_desde_la_localidad(): void
    {
        $data = ClienteUifLocalidadSupport::aplicar([
            'localidadnacimiento_id' => '112',
            'provincianacimiento_id' => '',
            'localidad_uif_id' => '274',
            'provincia_uif_id' => '1',
        ], function (int $localidadId): ?int {
            return $localidadId === 112 ? 2 : 1;
        });

        $this->assertSame(2, $data['provincianacimiento_id']);
        $this->assertSame(1, $data['provincia_uif_id']);
    }

    public function test_no_pisa_provincia_especial_si_ya_viene_cargada(): void
    {
        $data = ClienteUifLocalidadSupport::completarProvinciaSiVacia(
            [
                'localidad_uif_id' => 274,
                'provincia_uif_id' => 26,
            ],
            'localidad_uif_id',
            'provincia_uif_id',
            fn () => $this->fail('no debe alinear si la provincia ya está')
        );

        $this->assertSame(26, $data['provincia_uif_id']);
    }
}
