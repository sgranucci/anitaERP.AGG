<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Models\Ventas\CuentaGastronomia;
use App\Support\Ventas\Gastronomia\GastronomiaAnularCuentaPendienteClaveSupport;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Tests\TestCase;

class GastronomiaAnularCuentaPendienteClaveSupportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.empresa' => 'AGG',
            'gastronomia.anular_cuenta_pendiente_exige_clave' => true,
            'gastronomia.anular_cuenta_pendiente_clave' => 'clave-test',
            'gastronomia.anular_cuenta_pendiente_empresa_ids' => [2],
        ]);
    }

    public function test_activo_solo_agg_kandiko_con_flag(): void
    {
        $this->assertTrue(GastronomiaAnularCuentaPendienteClaveSupport::activoParaEmpresa(2));
        $this->assertFalse(GastronomiaAnularCuentaPendienteClaveSupport::activoParaEmpresa(1));
        $this->assertFalse(GastronomiaAnularCuentaPendienteClaveSupport::activoParaEmpresa(3));

        config(['gastronomia.anular_cuenta_pendiente_exige_clave' => false]);
        $this->assertFalse(GastronomiaAnularCuentaPendienteClaveSupport::activoParaEmpresa(2));

        config([
            'gastronomia.anular_cuenta_pendiente_exige_clave' => true,
            'app.empresa' => 'INTERFORMING',
        ]);
        $this->assertFalse(GastronomiaAnularCuentaPendienteClaveSupport::activoParaEmpresa(2));
    }

    public function test_exige_clave_en_kandiko_con_o_sin_consumos(): void
    {
        $conLineas = $this->cuentaStub(2, true);
        $vacia = $this->cuentaStub(2, false);
        $biyemas = $this->cuentaStub(1, true);

        $this->assertTrue(GastronomiaAnularCuentaPendienteClaveSupport::exigeClave($conLineas));
        $this->assertTrue(GastronomiaAnularCuentaPendienteClaveSupport::exigeClave($vacia));
        $this->assertFalse(GastronomiaAnularCuentaPendienteClaveSupport::exigeClave($biyemas));
    }

    public function test_validar_acepta_clave_correcta(): void
    {
        $cuenta = $this->cuentaStub(2, true);
        GastronomiaAnularCuentaPendienteClaveSupport::validar($cuenta, 'clave-test');
        $this->assertTrue(true);
    }

    public function test_validar_rechaza_clave_incorrecta(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GastronomiaAnularCuentaPendienteClaveSupport::validar($this->cuentaStub(2, true), 'otra');
    }

    public function test_validar_no_pide_clave_fuera_de_kandiko(): void
    {
        GastronomiaAnularCuentaPendienteClaveSupport::validar($this->cuentaStub(1, true), null);
        $this->assertTrue(true);
    }

    private function cuentaStub(int $empresaId, bool $conLineas): CuentaGastronomia
    {
        $cuenta = new CuentaGastronomia;
        $cuenta->empresa_id = $empresaId;
        $cuenta->setRelation('lineas', new Collection($conLineas ? [(object) ['id' => 1]] : []));

        return $cuenta;
    }
}
