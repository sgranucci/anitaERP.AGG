<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ProyeccionPagosColumnasSupport;
use App\Support\Compras\ProyeccionPagosReporteFiltros;
use PHPUnit\Framework\TestCase;

class ProyeccionPagosColumnasSupportTest extends TestCase
{
    public function test_catalogo_incluye_autorizante_de_requisicion(): void
    {
        $claves = array_column(ProyeccionPagosColumnasSupport::catalogo(), 'clave');

        $this->assertContains('usuario_requisicion', $claves);
        $this->assertContains('autorizante_requisicion', $claves);
        $this->assertContains('aprobacion_requisicion', $claves);
        $this->assertSame(
            'autorizante_requisicion',
            $claves[array_search('usuario_requisicion', $claves, true) + 1]
        );
    }

    public function test_si_ya_muestra_confecciona_agrega_autorizante_al_lado(): void
    {
        $visibles = ProyeccionPagosColumnasSupport::resolverVisibles(
            ProyeccionPagosColumnasSupport::catalogo(),
            'proveedor_codigo,proveedor_nombre,requisicion,usuario_requisicion,aprobacion_requisicion,total_adeudado',
            ProyeccionPagosReporteFiltros::SALIDA_DETALLE,
        );
        $claves = array_column($visibles, 'clave');

        $this->assertContains('autorizante_requisicion', $claves);
        $this->assertSame(
            ['requisicion', 'usuario_requisicion', 'autorizante_requisicion', 'aprobacion_requisicion'],
            array_values(array_intersect($claves, [
                'requisicion', 'usuario_requisicion', 'autorizante_requisicion', 'aprobacion_requisicion',
            ]))
        );
    }

    public function test_sin_columnas_de_requisicion_no_fuerza_autorizante(): void
    {
        $visibles = ProyeccionPagosColumnasSupport::resolverVisibles(
            ProyeccionPagosColumnasSupport::catalogo(),
            'proveedor_codigo,proveedor_nombre,total_adeudado',
            ProyeccionPagosReporteFiltros::SALIDA_DETALLE,
        );
        $claves = array_column($visibles, 'clave');

        $this->assertNotContains('autorizante_requisicion', $claves);
    }
}
