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

    public function test_preferir_erp_si_cargado_no_pisa_valor_existente(): void
    {
        $this->assertSame(5, ClienteUifLocalidadSupport::preferirErpSiCargado(5, 123));
        $this->assertSame(123, ClienteUifLocalidadSupport::preferirErpSiCargado(null, 123));
        $this->assertSame(21, ClienteUifLocalidadSupport::preferirErpSiCargado('', 21));
        $this->assertNull(ClienteUifLocalidadSupport::preferirErpSiCargado(null, null));
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
        ], function (int $localidadId): ?int {
            return $localidadId === 112 ? 2 : 1;
        });

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

    public function test_alinea_provincia_si_esta_desfasada_respecto_de_la_localidad(): void
    {
        $data = ClienteUifLocalidadSupport::alinearProvinciaConLocalidad(
            [
                'localidad_uif_id' => 274,
                'provincia_uif_id' => 2,
            ],
            'localidad_uif_id',
            'provincia_uif_id',
            fn (int $localidadId): ?int => $localidadId === 274 ? 1 : null
        );

        $this->assertSame(1, $data['provincia_uif_id']);
    }

    public function test_conserva_provincia_si_la_localidad_no_tiene_provincia_en_maestro(): void
    {
        $data = ClienteUifLocalidadSupport::alinearProvinciaConLocalidad(
            [
                'localidadnacimiento_id' => 28,
                'provincianacimiento_id' => 26,
            ],
            'localidadnacimiento_id',
            'provincianacimiento_id',
            fn (): ?int => null
        );

        $this->assertSame(26, $data['provincianacimiento_id']);
    }

    public function test_no_pisa_provincia_real_con_no_residente_de_localidad(): void
    {
        $data = ClienteUifLocalidadSupport::alinearProvinciaConLocalidad(
            [
                'localidad_uif_id' => 337,
                'provincia_uif_id' => 2,
            ],
            'localidad_uif_id',
            'provincia_uif_id',
            fn (): ?int => 26
        );

        $this->assertSame(2, $data['provincia_uif_id']);
    }
}
