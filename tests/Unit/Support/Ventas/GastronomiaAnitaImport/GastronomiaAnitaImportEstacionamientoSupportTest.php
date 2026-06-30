<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas\GastronomiaAnitaImport;

use App\Models\Ventas\Venta;
use App\Support\Ventas\GastronomiaAnitaImport\GastronomiaAnitaImportEstacionamientoSupport;
use PHPUnit\Framework\TestCase;
use stdClass;

final class GastronomiaAnitaImportEstacionamientoSupportTest extends TestCase
{
    public function test_es_host_estacionamiento(): void
    {
        $this->assertTrue(GastronomiaAnitaImportEstacionamientoSupport::esHostEstacionamiento('pc-estac4'));
        $this->assertFalse(GastronomiaAnitaImportEstacionamientoSupport::esHostEstacionamiento('tactilbarra'));
    }

    public function test_es_leyenda_estacionamiento(): void
    {
        $this->assertTrue(GastronomiaAnitaImportEstacionamientoSupport::esLeyendaEstacionamiento('Estacionamiento — Cat. Automovil'));
        $this->assertFalse(GastronomiaAnitaImportEstacionamientoSupport::esLeyendaEstacionamiento('Importación Anita FAC B-00020-00184228'));
    }

    public function test_debe_omitir_por_resvta_estacionamiento(): void
    {
        $venta = new Venta(['leyenda' => 'Consumidor final']);
        $resvta = (object) ['resv_host' => 'pc-estac4'];

        $this->assertTrue(GastronomiaAnitaImportEstacionamientoSupport::debeOmitirCircuitoGastronomia($venta, $resvta));
    }
}
