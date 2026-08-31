<?php

namespace Tests\Unit\Support\Contable;

use App\Support\Contable\CierreRendicionMaquinaAsientoSupport;
use Tests\TestCase;

class CierreRendicionMaquinaAsientoSupportTest extends TestCase
{
    public function test_asiento_biyemas_1_agosto_replica_ctamov_363027(): void
    {
        $config = [
            'cuenta_caja_pesos_id' => 5,
            'cuenta_tarjetas_id' => 104,
            'cuenta_mep_id' => 60,
            'cuenta_dolares_id' => 1,
            'cuenta_euros_id' => 1,
            'cuenta_cripto_id' => 1,
            'cuenta_totalcoin_id' => 114,
            'cuenta_impuesto_esp_id' => 738,
            'cuenta_gastos_id' => 1,
            'cuenta_ticket_gastro_id' => 339,
            'cuenta_pago24_id' => 1,
            'cuenta_ticket_prom_debe_id' => 1,
            'cuenta_ticket_prom_haber_id' => 1,
            'cuenta_caja_transitoria_id' => 8,
            'cuenta_ff_maquina_id' => 15,
            'cuenta_ventas_id' => 572,
            'cuenta_ventas_ruleta_id' => 575,
            'cuenta_poder_publico_id' => 1,
            'cuenta_diferencia_caja_id' => 847,
            'cuenta_partida_pendiente_id' => 337,
            'cuenta_canon_loteria_id' => 900,
            'cuenta_cont_canon_loteria_id' => 901,
            'cuenta_canon_hospital_id' => 902,
            'cuenta_cont_canon_hospital_id' => 903,
        ];

        $tot = [
            'efectivo' => 0.0,
            'tarjetas' => 0.0,
            'mep' => 0.0,
            'valores_cuenta' => [
                ['cuentacontable_id' => 5, 'concepto' => 'Caja pesos', 'monto' => 355330.0],
                ['cuentacontable_id' => 60, 'concepto' => 'MEP', 'monto' => 3020000.0],
                ['cuentacontable_id' => 48, 'concepto' => 'Transf. Check MS', 'monto' => 12107500.0],
                ['cuentacontable_id' => 114, 'concepto' => 'TotalCoin QR Maquina', 'monto' => 101399973.0],
                ['cuentacontable_id' => 115, 'concepto' => 'TotalCoin QR Caja', 'monto' => 8490000.0],
            ],
            'dolares_en_pesos' => 0.0,
            'euros_en_pesos' => 0.0,
            'cripto_en_pesos' => 0.0,
            'totalcoin' => 3860000.0,
            'impuesto_esp' => 7857972.48,
            'vales' => 0.0,
            'reintegros' => 0.0,
            'gastos_apertura' => [
                ['descripcion' => 'Perdidas de personal', 'cuentacontable_id' => 847, 'contrapartida_id' => 0, 'monto' => 20000.0],
                ['descripcion' => 'Reconocimiento de clientes', 'cuentacontable_id' => 845, 'contrapartida_id' => 0, 'monto' => 5000.0],
                ['descripcion' => 'Desperfecto de maquinas', 'cuentacontable_id' => 848, 'contrapartida_id' => 0, 'monto' => 2000.0],
                ['descripcion' => 'Reconocimiento impuesto', 'cuentacontable_id' => 739, 'contrapartida_id' => 0, 'centrocosto_id' => 13, 'monto' => 51893.75],
            ],
            'ticket_gastro' => 287500.0,
            'vta_ant_gastro' => 0.0,
            'ticket_prom' => 0.0,
            'variacion_ff' => 0.0,
            'tot_caja_trans' => -127877614.0,
            'maquinas_online' => 214072337.31,
            'ruletas_online' => 40841710.0,
            'maquinas_real' => 239692939.22,
            'ruletas_real' => 5547800.0,
            'pago_diferido' => 0.0,
        ];

        $asientos = CierreRendicionMaquinaAsientoSupport::armarAsientos($tot, $config);
        $this->assertSame('Venta maquinas', $asientos[0]['leyenda'] ?? '');

        $lineas = $asientos[0]['lineas'];
        $this->assertLinea($lineas, 5, 355330.0, 0.0);
        $this->assertLinea($lineas, 60, 3020000.0, 0.0);
        $this->assertLinea($lineas, 48, 12107500.0, 0.0);
        $this->assertLinea($lineas, 114, 101399973.0, 3860000.0);
        $this->assertLinea($lineas, 115, 8490000.0, 0.0);
        $this->assertLinea($lineas, 738, 0.0, 7857972.48);
        $this->assertLinea($lineas, 339, 0.0, 287500.0);
        $this->assertLinea($lineas, 8, 287500.0 + 127877614.0, 0.0);
        $this->assertLinea($lineas, 572, 0.0, 214072337.31);
        $this->assertLinea($lineas, 575, 0.0, 40841710.0);
        $this->assertLinea($lineas, 847, 20000.0, 230599.05);
        $this->assertLinea($lineas, 5, 355330.0 + 3860000.0, 0.0);
        $this->assertLinea($lineas, 337, 9673308.09, 0.0);
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private function assertLinea(array $lineas, int $cuentaId, float $debe, float $haber): void
    {
        $sumD = 0.0;
        $sumH = 0.0;
        foreach ($lineas as $ln) {
            if ((int) ($ln['cuenta_id'] ?? 0) !== $cuentaId) {
                continue;
            }
            $sumD += (float) ($ln['debe'] ?? 0);
            $sumH += (float) ($ln['haber'] ?? 0);
        }

        $this->assertEqualsWithDelta($debe, $sumD, 0.02, 'Debe cuenta '.$cuentaId);
        $this->assertEqualsWithDelta($haber, $sumH, 0.02, 'Haber cuenta '.$cuentaId);
    }
}
