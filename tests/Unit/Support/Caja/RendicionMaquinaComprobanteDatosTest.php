<?php

namespace Tests\Unit\Support\Caja;

use App\Exports\Caja\RendicionMaquinaComprobanteExport;
use App\Models\Caja\AperturaGasto;
use App\Models\Caja\Cuentacaja;
use App\Models\Caja\RendicionMaquina;
use App\Models\Caja\RendicionMaquinaGasto;
use App\Models\Caja\RendicionMaquinaValor;
use App\Models\Configuracion\Empresa;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaComprobanteDatos;
use App\Support\Export\ExcelFormatoNumero;
use Tests\TestCase;

class RendicionMaquinaComprobanteDatosTest extends TestCase
{
    public function test_arma_los_mismos_totales_que_el_comprobante(): void
    {
        $datos = RendicionMaquinaComprobanteDatos::armar($this->rendicion());

        $this->assertSame('RM-10', $datos['codigo']);
        $this->assertSame('Central', $datos['empresa']);
        $this->assertSame(350.0, $this->valor($datos, 'Drop rodillo bruto'));
        $this->assertSame(300.0, $this->valor($datos, 'Drop rodillo neto'));
        $this->assertSame(423.45, $this->valor($datos, 'WIN'));
        $this->assertArrayNotHasKey('Dif. caja', $this->mapa($datos['totales']));

        $etiquetas = array_column($datos['principales'], 'etiqueta');
        $this->assertContains('Venta fichas (slots)', $etiquetas);
        $this->assertNotContains('Sobrantes', $etiquetas);
        $this->assertSame('Efectivo', $datos['valores'][0]['cuenta']);
        $this->assertSame(10.5, $datos['total_valores']);
        $this->assertCount(1, $datos['gastos']);
        $this->assertSame(4.0, $datos['total_gastos']);
        $this->assertSame('Nota de turno', $datos['observacion']);
    }

    public function test_turno_cierre_incluye_diferencia_de_caja(): void
    {
        $rendicion = $this->rendicion();
        $rendicion->turno = 'C';
        $rendicion->dif_caja = 8.5;

        $datos = RendicionMaquinaComprobanteDatos::armar($rendicion);

        $this->assertSame(8.5, $this->valor($datos, 'Dif. caja'));
    }

    public function test_la_vista_excel_incluye_win_y_codigo(): void
    {
        $html = (new RendicionMaquinaComprobanteExport($this->rendicion()))->view()->render();

        $formato = ExcelFormatoNumero::preferenciaGlobal();
        $win = ExcelFormatoNumero::esAuto($formato)
            ? '423.45'
            : ExcelFormatoNumero::formatearTexto(423.45, $formato, 2);

        $this->assertStringContainsString('Rendición de máquinas', $html);
        $this->assertStringContainsString('RM-10', $html);
        $this->assertStringContainsString('WIN', $html);
        $this->assertStringContainsString($win, $html);
    }

    private function rendicion(): RendicionMaquina
    {
        $empresa = new Empresa();
        $empresa->nombre = 'Central';

        $cuenta = new Cuentacaja();
        $cuenta->codigo = '1';
        $cuenta->descripcion_operaciones = 'Efectivo';

        $valor = new RendicionMaquinaValor();
        $valor->monto = 10.5;
        $valor->setRelation('cuentacaja', $cuenta);

        $apertura = new AperturaGasto();
        $apertura->codigo = 12;
        $apertura->nombre = 'Viáticos';

        $gasto = new RendicionMaquinaGasto();
        $gasto->monto = 4;
        $gasto->setRelation('aperturaGasto', $apertura);

        $gastoCero = new RendicionMaquinaGasto();
        $gastoCero->monto = 0;
        $gastoCero->setRelation('aperturaGasto', $apertura);

        $rendicion = new RendicionMaquina();
        $rendicion->forceFill([
            'codigo' => 'RM-10',
            'turno' => 'M',
            'estado' => RendicionMaquina::ESTADO_CONFIRMADA,
            'fondo_inicial' => 100,
            'total_ingreso' => 200,
            'total_salida' => 50,
            'resultado_turno' => 150,
            'fondo_cierre' => 80,
            'transferencia' => 20,
            'observacion' => 'Nota de turno',
            'inputs_json' => [
                'drop_billete' => 300,
                'impuesto_drop' => 50,
                'venta_ficha' => 20,
                'dropqr_rodillo' => 5,
                'dropqr_ruleta' => 1.45,
                'sobrantes' => 0,
                'pago_manual' => 1,
                'tito' => 2,
            ],
            'calc_json' => [
                'variables' => [
                    'comprobante' => 10,
                    'drop_bill_rodillo' => 300,
                    'drop_bill_ruleta' => 100,
                ],
            ],
        ]);
        $rendicion->setRelation('empresa', $empresa);
        $rendicion->setRelation('valores', collect([$valor]));
        $rendicion->setRelation('gastos', collect([$gasto, $gastoCero]));
        $rendicion->setRelation('supervisorUsuario', null);
        $rendicion->setRelation('cajeroUsuario', null);
        $rendicion->setRelation('auxiliarUsuario', null);
        $rendicion->setRelation('creoUsuario', null);

        return $rendicion;
    }

    /**
     * @param  array{totales: list<array{etiqueta: string, valor: float}>}  $datos
     */
    private function valor(array $datos, string $etiqueta): float
    {
        return $this->mapa($datos['totales'])[$etiqueta];
    }

    /**
     * @param  list<array{etiqueta: string, valor: float}>  $filas
     * @return array<string, float>
     */
    private function mapa(array $filas): array
    {
        $mapa = [];
        foreach ($filas as $fila) {
            $mapa[$fila['etiqueta']] = (float) $fila['valor'];
        }

        return $mapa;
    }
}
