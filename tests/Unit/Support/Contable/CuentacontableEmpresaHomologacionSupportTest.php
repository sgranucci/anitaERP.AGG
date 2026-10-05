<?php

namespace Tests\Unit\Support\Contable;

use App\Models\Contable\Cuentacontable;
use App\Support\Contable\CuentacontableEmpresaHomologacionSupport;
use Tests\TestCase;

final class CuentacontableEmpresaHomologacionSupportTest extends TestCase
{
    public function test_caja_pesos_de_biyemas_se_homologa_al_plan_de_rebisco(): void
    {
        $origen = Cuentacontable::query()->find(5, ['id', 'empresa_id', 'codigo', 'nombre']);
        $destino = Cuentacontable::query()
            ->where('empresa_id', 3)
            ->where('codigo', '111010001')
            ->where('tipocuenta', 1)
            ->first(['id', 'nombre']);

        if ($origen === null || (int) $origen->empresa_id !== 1 || $destino === null) {
            $this->markTestSkipped('Sin Caja Pesos de Biyemas y Rebisco en este entorno.');
        }

        $this->assertSame('CAJA PESOS', trim((string) $origen->nombre));
        $this->assertSame((int) $destino->id, CuentacontableEmpresaHomologacionSupport::idParaEmpresa(5, 3));
        $this->assertSame(5, CuentacontableEmpresaHomologacionSupport::idParaEmpresa(5, 1));
    }
}
