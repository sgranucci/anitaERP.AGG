<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\AnitaSync\RendicionBingoCabeceraAnitaMapper;
use PHPUnit\Framework\TestCase;

class RendicionBingoCabeceraAnitaMapperTest extends TestCase
{
    public function test_texto_aplana_saltos_de_linea_y_acentos(): void
    {
        $obs = "se deposita demás \$ 347.11 por diferencia del día 26/08/2026 \nAdjunto resumen BUB Y 10%";

        $resultado = RendicionBingoCabeceraAnitaMapper::texto($obs, 200);

        $this->assertSame(
            'se deposita demas $ 347.11 por diferencia del dia 26/08/2026 Adjunto resumen BUB Y 10%',
            $resultado,
        );
        $this->assertStringNotContainsString("\n", $resultado);
    }

    public function test_valores_insert_no_parten_el_sql_por_observacion_multiline(): void
    {
        $obs = "se deposita demás \$ 347.11 por diferencia del día 26/08/2026 \nAdjunto resumen BUB Y 10%";

        $valores = RendicionBingoCabeceraAnitaMapper::valoresInsert([
            'nro_oper' => 766228,
            'tipo_oper' => 'F',
            'caja_id' => 100,
            'usuario_id' => 136,
            'fecha_entera' => 20260909,
            'hora' => '18:49:26',
            'usuario_habilitado_id' => 136,
            'sobrante_faltante' => 0,
            'vales' => 0,
            'redondeo' => 0,
            'deposito' => 2171350,
            'cant_cartones' => 1000,
            'total_cartones' => 2000000,
            'observacion' => $obs,
            'empresa_anita' => 1,
            'estado' => ' ',
            'fecha_alfa' => '09/09/26',
            'turno_letra' => 'T',
            'fecha_carga' => 20260909,
            'hora_carga' => '18:49:26',
        ]);

        $sql = 'INSERT INTO rendbingo ('.RendicionBingoCabeceraAnitaMapper::camposInsert().') VALUES ('.$valores.')';

        $this->assertStringNotContainsString("\n", $sql);
        $this->assertStringContainsString('se deposita demas', $sql);
    }

    public function test_texto_escapa_comillas_simples_tras_sanitizar(): void
    {
        $this->assertSame("O''Higgins", RendicionBingoCabeceraAnitaMapper::texto("O'Higgins", 50));
    }
}
