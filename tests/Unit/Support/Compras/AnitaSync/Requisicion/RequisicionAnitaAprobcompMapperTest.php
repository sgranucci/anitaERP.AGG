<?php

namespace Tests\Unit\Support\Compras\AnitaSync\Requisicion;

use App\Support\Compras\AnitaSync\Requisicion\RequisicionAnitaAprobcompMapper;
use PHPUnit\Framework\TestCase;

class RequisicionAnitaAprobcompMapperTest extends TestCase
{
    public function test_no_escribe_si_no_hubo_aprobada(): void
    {
        $this->assertFalse(RequisicionAnitaAprobcompMapper::correspondeSnapshot('GENERO ORDEN COMPRA', false));
        $this->assertFalse(RequisicionAnitaAprobcompMapper::correspondeSnapshot('APROBADA', false));
    }

    public function test_no_escribe_mientras_anita_puede_grabar_el_arbol(): void
    {
        $this->assertFalse(RequisicionAnitaAprobcompMapper::correspondeSnapshot('EN ARBOL APROBACION', true));
        $this->assertFalse(RequisicionAnitaAprobcompMapper::correspondeSnapshot('PENDIENTE', true));
        $this->assertFalse(RequisicionAnitaAprobcompMapper::correspondeSnapshot('PROVISORIO', true));
    }

    public function test_escribe_cuando_ya_cerro_el_circuito_en_erp(): void
    {
        $this->assertTrue(RequisicionAnitaAprobcompMapper::correspondeSnapshot('APROBADA', true));
        $this->assertTrue(RequisicionAnitaAprobcompMapper::correspondeSnapshot('GENERO ORDEN COMPRA', true));
        $this->assertTrue(RequisicionAnitaAprobcompMapper::correspondeSnapshot('PARCIAL', true));
        $this->assertTrue(RequisicionAnitaAprobcompMapper::correspondeSnapshot('CUMPLIDA', true));
        $this->assertTrue(RequisicionAnitaAprobcompMapper::correspondeSnapshot('EN COMPRAS', true));
    }

    public function test_autorizante_prioriza_historia_aprobada(): void
    {
        $this->assertSame(91, RequisicionAnitaAprobcompMapper::autorizanteErpId(
            ['usuario_id' => 91],
            ['destinatario_id' => 10, 'enviador_id' => 78],
        ));
    }

    public function test_autorizante_cae_a_firmante_del_arbol(): void
    {
        $this->assertSame(10, RequisicionAnitaAprobcompMapper::autorizanteErpId(
            null,
            ['destinatario_id' => 10, 'enviador_id' => 78],
        ));
        $this->assertSame(78, RequisicionAnitaAprobcompMapper::autorizanteErpId(
            ['usuario_id' => 0],
            ['destinatario_id' => 0, 'enviador_id' => 78],
        ));
    }

    public function test_where_req_usa_matches_para_no_chocar_el_char_anita(): void
    {
        $this->assertSame(
            " WHERE aprobc_tipo MATCHES 'R*' AND aprobc_nro = 231241",
            RequisicionAnitaAprobcompMapper::whereReq(231241)
        );
    }

    public function test_where_req_in_arma_lote(): void
    {
        $this->assertSame(
            " WHERE aprobc_tipo MATCHES 'R*' AND aprobc_nro IN (10,20,30)",
            RequisicionAnitaAprobcompMapper::whereReqIn([10, 20, 10, 30, 0])
        );
        $this->assertSame(' WHERE 1=0', RequisicionAnitaAprobcompMapper::whereReqIn([]));
    }

    public function test_valores_insert_incluye_nro_int_ap_fecha_y_nombre(): void
    {
        $sql = RequisicionAnitaAprobcompMapper::valoresInsert([
            'nro_int_ap' => 96414,
            'numerorequisicion' => 231241,
            'empresa' => 1,
            'proveedor' => '4033',
            'usuario_anita' => 1201,
            'usuario_nombre' => 'FLORENTIN ANGEL',
            'fecha_ymd' => 20260825,
            'hora_hm' => '11:41:09',
        ]);

        $this->assertStringContainsString('96414', $sql);
        $this->assertStringContainsString('20260825', $sql);
        $this->assertStringContainsString("'11:41'", $sql);
        $this->assertStringContainsString("'REQ'", $sql);
        $this->assertStringContainsString('231241', $sql);
        $this->assertStringContainsString(', 3,', $sql);
        $this->assertStringContainsString("'ERP'", $sql);
        $this->assertStringContainsString("'004033'", $sql);
        $this->assertStringContainsString("'FLORENTIN ANGEL'", $sql);
        $this->assertStringNotContainsString('aflore', $sql);
    }

    public function test_valores_update_incompleto_solo_toca_campos_faltantes(): void
    {
        $sql = RequisicionAnitaAprobcompMapper::valoresUpdateIncompleto([
            'nro_int_ap' => 96415,
            'usuario_nombre' => 'BLANCO ALEJANDRO',
            'fecha_ymd' => 20260825,
            'hora_hm' => '12:05',
        ]);

        $this->assertStringContainsString('aprobc_nro_int_ap = 96415', $sql);
        $this->assertStringContainsString('aprobc_fecha_envio = 20260825', $sql);
        $this->assertStringContainsString("aprobc_hora_envio = '12:05'", $sql);
        $this->assertStringContainsString("'BLANCO ALEJANDR'", $sql);
        $this->assertStringNotContainsString('aprobc_nro =', $sql);
        $this->assertStringNotContainsString('aprobc_estado', $sql);
    }

    public function test_where_snapshots_incompletos_solo_motivo_erp(): void
    {
        $where = RequisicionAnitaAprobcompMapper::whereSnapshotsErpIncompletos(231241);

        $this->assertStringContainsString("aprobc_motivo = 'ERP'", $where);
        $this->assertStringContainsString('aprobc_nro = 231241', $where);
        $this->assertStringContainsString('aprobc_nro_int_ap = 0', $where);
    }

    public function test_nombre_usuario_anita_corta_a_15(): void
    {
        $this->assertSame('BLANCO ALEJANDR', RequisicionAnitaAprobcompMapper::nombreUsuarioAnita('BLANCO ALEJANDRO'));
        $this->assertSame('00:00', RequisicionAnitaAprobcompMapper::horaHm(''));
        $this->assertTrue(RequisicionAnitaAprobcompMapper::snapshotIncompleto('0', '20260825'));
        $this->assertTrue(RequisicionAnitaAprobcompMapper::snapshotIncompleto('96376', '0'));
        $this->assertFalse(RequisicionAnitaAprobcompMapper::snapshotIncompleto('96376', '20260825'));
    }
}
