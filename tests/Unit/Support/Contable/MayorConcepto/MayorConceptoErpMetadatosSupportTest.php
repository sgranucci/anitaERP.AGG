<?php

namespace Tests\Unit\Support\Contable\MayorConcepto;

use App\Support\Contable\MayorConcepto\MayorConceptoErpMetadatosSupport;
use PHPUnit\Framework\TestCase;

class MayorConceptoErpMetadatosSupportTest extends TestCase
{
    public function test_detecta_descripcion_generica_pago_sp(): void
    {
        $this->assertTrue(MayorConceptoErpMetadatosSupport::esDescripcionGenericaPagoSp('Pago SP 11317'));
        $this->assertTrue(MayorConceptoErpMetadatosSupport::esDescripcionGenericaPagoSp('pago sp 11255'));
        $this->assertFalse(MayorConceptoErpMetadatosSupport::esDescripcionGenericaPagoSp('IVSA S.A. Ch: 77073587'));
        $this->assertFalse(MayorConceptoErpMetadatosSupport::esDescripcionGenericaPagoSp('Pago SP 11317 extra'));
    }

    public function test_enriquece_descripcion_con_detalle_sp_sin_tocar_si_ya_es_rica(): void
    {
        $this->assertSame(
            '40% ABL LUDOPATIA AGOSTO 2026',
            MayorConceptoErpMetadatosSupport::enriquecerDescripcion(
                'Pago SP 11317',
                '40% ABL LUDOPATIA AGOSTO 2026',
                'IVSA S.A.',
                '',
                '11317',
            ),
        );

        $this->assertSame(
            'IVSA S.A. Ch: 77073587',
            MayorConceptoErpMetadatosSupport::enriquecerDescripcion(
                'IVSA S.A. Ch: 77073587',
                'otro detalle',
                'IVSA S.A.',
                '999',
                '11317',
            ),
        );
    }

    public function test_enriquece_descripcion_agrega_cheque_si_falta(): void
    {
        $this->assertSame(
            'IVSA S.A. SP 11317 Ch: 77073587',
            MayorConceptoErpMetadatosSupport::enriquecerDescripcion(
                'Pago SP 11317',
                '',
                'IVSA S.A.',
                '77073587',
                '11317',
            ),
        );
    }

    public function test_cotizacion_vista_prefiere_caja_sin_pisar_cero_invalido(): void
    {
        $this->assertSame(1515.0, MayorConceptoErpMetadatosSupport::cotizacionVista(1.0, 1515.0));
        $this->assertSame(1.0, MayorConceptoErpMetadatosSupport::cotizacionVista(1.0, 0.0));
    }

    public function test_resolver_emisor_cuit_desde_promae_cuando_meta_trae_codigo(): void
    {
        $prom = (object) [
            'prom_nombre' => 'IVSA S.A.',
            'prom_cuit' => '30-64202387-6',
        ];

        $out = MayorConceptoErpMetadatosSupport::resolverEmisorCuitVista(
            '1921',
            '',
            '1921',
            $prom,
        );

        $this->assertSame('IVSA S.A.', $out['emisor']);
        $this->assertSame('30-64202387-6', $out['cuit']);
    }

    public function test_resolver_emisor_no_pisa_nombre_ya_resuelto(): void
    {
        $prom = (object) [
            'prom_nombre' => 'OTRO',
            'prom_cuit' => '30-11111111-1',
        ];

        $out = MayorConceptoErpMetadatosSupport::resolverEmisorCuitVista(
            'IVSA S.A.',
            '30-64202387-6',
            '1921',
            $prom,
        );

        $this->assertSame('IVSA S.A.', $out['emisor']);
        $this->assertSame('30-64202387-6', $out['cuit']);
    }

    public function test_numero_asiento_operativo_usa_numero_contable_nunca_pk(): void
    {
        $this->assertSame(5270726, MayorConceptoErpMetadatosSupport::numeroAsientoOperativo(null, 5270726));
        $this->assertSame(5270726, MayorConceptoErpMetadatosSupport::numeroAsientoOperativo(0, 5270726));
        $this->assertSame(900001, MayorConceptoErpMetadatosSupport::numeroAsientoOperativo(900001, 5270726));
        $this->assertSame(0, MayorConceptoErpMetadatosSupport::numeroAsientoOperativo(null, null));
    }
}
