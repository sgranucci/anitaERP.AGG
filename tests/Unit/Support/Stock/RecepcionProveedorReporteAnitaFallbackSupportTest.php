<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\RecepcionProveedorReporteAnitaFallbackSupport;
use App\Support\Stock\RecepcionProveedorReporteFiltros;
use Tests\TestCase;

class RecepcionProveedorReporteAnitaFallbackSupportTest extends TestCase
{
    public function test_una_sola_lectura_con_outer_a_req_y_aprobcomp(): void
    {
        $from = RecepcionProveedorReporteAnitaFallbackSupport::tablaFrom();

        $this->assertStringContainsString('recepmov', $from);
        $this->assertStringContainsString('recepmae', $from);
        $this->assertStringContainsString('OUTER (pendmaep, OUTER (reqmae, OUTER aprobcomp))', $from);
        $this->assertStringNotContainsString('usuario', $from);
        $this->assertSame(1, substr_count($from, 'recepmov'));
    }

    public function test_campos_sin_comas_en_expresiones_para_el_parser_del_bridge(): void
    {
        foreach (RecepcionProveedorReporteAnitaFallbackSupport::camposSelect() as $campo) {
            $this->assertStringContainsString(' as ', $campo);
            $expr = explode(' as ', $campo)[0];
            $this->assertStringNotContainsString(',', $expr, $campo);
        }
        $this->assertStringContainsString('reqm_usuario as reqm_usuario', RecepcionProveedorReporteAnitaFallbackSupport::camposCsv());
        $this->assertStringContainsString('aprobc_usuario as autorizante_anita', RecepcionProveedorReporteAnitaFallbackSupport::camposCsv());
    }

    public function test_where_une_com_oc_req_y_aprobado_de_aprobcomp(): void
    {
        $where = RecepcionProveedorReporteAnitaFallbackSupport::whereArmado(
            ['estado' => RecepcionProveedorReporteFiltros::ESTADO_CONFIRMADA],
            ['desde' => 20210101, 'hasta' => 20221231],
            [1, 3],
        );

        $this->assertStringContainsString('recm_tipo = recv_tipo', $where);
        $this->assertStringContainsString('penmp_nro = recm_nro_fac', $where);
        $this->assertStringNotContainsString('penmp_nro = recm_com_nro', $where);
        $this->assertStringContainsString('reqm_nro = penmp_requisicion', $where);
        $this->assertStringContainsString('aprobc_nro = reqm_nro', $where);
        $this->assertStringNotContainsString('aprobc_estado =', $where);
        $this->assertStringContainsString('recm_fecha >= 20210101', $where);
        $this->assertStringContainsString('recm_fecha <= 20221231', $where);
        $this->assertStringContainsString('recm_sucursal IN (1,3)', $where);
    }

    public function test_rango_anita_nulo_si_el_periodo_es_posterior_al_corte(): void
    {
        $this->assertNull(RecepcionProveedorReporteAnitaFallbackSupport::rangoAnita([
            'fecha_desde' => '2025-03-01',
            'fecha_hasta' => '2025-03-31',
        ], '2025-01-01'));
    }

    public function test_rango_anita_recorta_hasta_el_dia_previo_al_corte(): void
    {
        $rango = RecepcionProveedorReporteAnitaFallbackSupport::rangoAnita([
            'fecha_desde' => '2021-01-01',
            'fecha_hasta' => '2026-06-30',
        ], '2025-01-02');

        $this->assertSame(['desde' => 20210101, 'hasta' => 20250101], $rango);
    }

    public function test_no_fallback_si_solo_devoluciones_o_solo_facturadas(): void
    {
        $base = [
            'fecha_desde' => '2021-01-01',
            'fecha_hasta' => '2021-12-31',
        ];

        $this->assertFalse(RecepcionProveedorReporteAnitaFallbackSupport::correspondeFallback(array_merge($base, [
            'tipo' => RecepcionProveedorReporteFiltros::TIPO_DEVOLUCION,
        ]), '2025-01-01'));
        $this->assertFalse(RecepcionProveedorReporteAnitaFallbackSupport::correspondeFallback(array_merge($base, [
            'facturacion' => RecepcionProveedorReporteFiltros::FACTURACION_FACTURADAS,
        ]), '2025-01-01'));
        $this->assertFalse(RecepcionProveedorReporteAnitaFallbackSupport::correspondeFallback(array_merge($base, [
            'solo_diferencias' => true,
        ]), '2025-01-01'));
    }

    public function test_corresponde_fallback_en_2021(): void
    {
        $this->assertTrue(RecepcionProveedorReporteAnitaFallbackSupport::correspondeFallback([
            'fecha_desde' => '2021-01-01',
            'fecha_hasta' => '2021-12-31',
        ], '2025-01-01'));
    }

    public function test_mapear_fila_trae_quien_pide_y_quien_aprueba(): void
    {
        $fila = RecepcionProveedorReporteAnitaFallbackSupport::mapearFila((object) [
            'sku' => 'LIM0003',
            'descripcion' => 'ALCOHOL FINO',
            'cantidad' => 48,
            'precio' => 173.56,
            'descuento' => 0,
            'cant_rech' => 0,
            'um' => 'UN',
            'ccosto' => 93,
            'deposito' => 1,
            'linea_orden' => 1,
            'codigo_proveedor' => '000160',
            'recm_letra' => 'X',
            'recm_sucursal' => 3,
            'recm_nro' => 39079,
            'recm_fecha' => 20210122,
            'recm_estado' => '2',
            'recm_usuario' => 'genrique',
            'com_nro' => 0,
            'nro_oc' => 31461,
            'fecha_oc' => 20210112,
            'nro_req' => 31461,
            'reqm_nro' => 31461,
            'fecha_req' => 20210111,
            'cc_dest' => 93,
            'usuario_requisicion' => 'ENRIQUE GUSTAVO',
            'logname_requisicion' => 'genrique',
            'autorizante_anita' => 'BLANCO ALEJANDR',
            'aprobc_estado' => 3,
            'empresa_id' => 3,
            'nombreempresa' => 'REB',
            'proveedor_id' => 10,
            'nombreproveedor' => 'TERZAGHI',
        ]);

        $this->assertSame('anita', $fila['fuente']);
        $this->assertSame('ENRIQUE GUSTAVO', $fila['usuario_requisicion']);
        $this->assertSame('BLANCO ALEJANDR', $fila['autorizante_requisicion']);
        $this->assertSame('31461', $fila['numerorequisicion']);
        $this->assertSame('31461', $fila['numeroordencompra']);
        $this->assertSame('X0003-00039079', $fila['com_anita']);
        $this->assertSame('genrique', $fila['usuario']);
    }

    public function test_mapear_usa_pep_de_nro_fac_si_com_nro_es_cero(): void
    {
        $fila = RecepcionProveedorReporteAnitaFallbackSupport::mapearFila((object) [
            'sku' => 'X',
            'cantidad' => 1,
            'precio' => 1,
            'recm_letra' => 'X',
            'recm_sucursal' => 1,
            'recm_nro' => 113731,
            'recm_fecha' => 20210105,
            'recm_estado' => '2',
            'com_nro' => 0,
            'nro_oc' => 0,
            'tipo_fac' => 'PEP',
            'nro_fac' => 164137,
            'reqm_usuario' => 5,
            'autorizante_anita' => 'vfernandez',
        ]);

        $this->assertSame('164137', $fila['numeroordencompra']);
        $this->assertSame('5', $fila['usuario_requisicion']);
        $this->assertSame('vfernandez', $fila['autorizante_requisicion']);
    }

    public function test_dedupe_conserva_la_fila_aprobada(): void
    {
        $base = [
            'recm_sucursal' => 1,
            'recm_nro' => 10,
            'linea_orden' => 1,
        ];
        $filas = RecepcionProveedorReporteAnitaFallbackSupport::deduplicarAprobado([
            (object) array_merge($base, ['aprobc_estado' => 1, 'autorizante_anita' => 'PENDIENTE']),
            (object) array_merge($base, ['aprobc_estado' => 3, 'autorizante_anita' => 'APROBADO']),
        ]);

        $this->assertCount(1, $filas);
        $this->assertSame('APROBADO', $filas[0]->autorizante_anita);
    }
}
