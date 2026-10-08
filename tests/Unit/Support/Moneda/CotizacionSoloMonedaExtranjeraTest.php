<?php

namespace Tests\Unit\Support\Moneda;

use App\Support\Caja\IngresoEgresoImputacionDiariaSupport;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaValoresCuentacajaSupport;
use App\Support\Compras\ComprobanteProveedorMonedaMotor;
use App\Support\Compras\PagoproveedorAsientoArmadoSupport;
use App\Support\Compras\PagoproveedorLiquidacionSupport;
use App\Support\Compras\ProveedorCuentacorrienteAplicacionLiquidacionSupport;
use Tests\TestCase;

/**
 * Un importe en moneda local no se multiplica por la cotización.
 *
 * En pesos la cotización que viaja en el documento es informativa: sirve para expresar el
 * movimiento en dólares, no para reescalarlo. Multiplicarla infla el importe del orden de la
 * cotización — una factura en pesos contra OC en dólares daba un Debe de 35.930.078.800 en
 * lugar de 23.331.220.
 *
 * Cubre los conversores de facturas de proveedor, pago a proveedores e ingresos y egresos,
 * más el helper `calculaCoeficienteMoneda` que usan los tres.
 */
class CotizacionSoloMonedaExtranjeraTest extends TestCase
{
    private const MONTO = 1000.0;

    private const COTIZACION = 1540.0;

    private const PESOS = 1;

    private const DOLAR = 2;

    public function test_factura_de_proveedor_en_pesos_no_se_reescala(): void
    {
        $this->assertSinReescalar(ComprobanteProveedorMonedaMotor::aMonedaLocal(
            self::MONTO, self::PESOS, self::COTIZACION, '2026-10-08', 'la factura'
        ));

        $this->assertSinReescalar(ComprobanteProveedorMonedaMotor::convertir(
            self::MONTO,
            self::PESOS,
            self::COTIZACION,
            '2026-10-08',
            self::PESOS,
            self::COTIZACION,
            '2026-10-08',
            'la recepción',
            'la factura',
        ));
    }

    public function test_factura_sin_moneda_cargada_se_trata_como_pesos(): void
    {
        $this->assertSinReescalar(ComprobanteProveedorMonedaMotor::aMonedaLocal(
            self::MONTO, 0, self::COTIZACION, '2026-10-08', 'la factura'
        ));
    }

    public function test_pago_a_proveedores_en_pesos_no_se_reescala(): void
    {
        $this->assertSinReescalar(PagoproveedorAsientoArmadoSupport::convertirImporteAMonedaPago(
            self::MONTO, self::PESOS, self::PESOS, self::COTIZACION
        ));

        $this->assertSinReescalar(PagoproveedorAsientoArmadoSupport::convertirMontoRetencionAMonedaPago(
            self::MONTO, self::PESOS, self::COTIZACION
        ));

        $this->assertSinReescalar(ProveedorCuentacorrienteAplicacionLiquidacionSupport::valorLocal(
            self::MONTO, self::COTIZACION, self::PESOS
        ));

        $liquidacion = PagoproveedorLiquidacionSupport::calcular(
            self::MONTO, self::PESOS, self::COTIZACION, self::PESOS, self::COTIZACION
        );
        $this->assertSinReescalar((float) $liquidacion['valor_local_deuda']);
        $this->assertSinReescalar((float) $liquidacion['equivalente_pago']);
    }

    public function test_ingresos_y_egresos_en_pesos_no_se_reescala(): void
    {
        $this->assertSinReescalar(RendicionMaquinaValoresCuentacajaSupport::montoEnPesos(
            self::PESOS, self::MONTO, self::COTIZACION
        ));

        $this->assertSinReescalar(IngresoEgresoImputacionDiariaSupport::reconciliarTesmovEnPesos(
            self::MONTO, 99999.0, [self::PESOS => self::COTIZACION]
        ));
    }

    /**
     * Moneda vacía, nula o 0 es moneda local. Antes devolvía la cotización y una línea sin
     * moneda cargada quedaba multiplicada.
     */
    public function test_coeficiente_a_pesos_desde_moneda_sin_dato_es_uno(): void
    {
        foreach ([self::PESOS, 0, null, '', '0'] as $monedaOrigen) {
            $this->assertSame(
                1.0,
                (float) calculaCoeficienteMoneda(self::PESOS, $monedaOrigen, self::COTIZACION),
                'Moneda origen '.var_export($monedaOrigen, true).': no debe reescalar el importe.'
            );
        }
    }

    public function test_moneda_extranjera_si_multiplica(): void
    {
        $esperado = self::MONTO * self::COTIZACION;

        $this->assertEqualsWithDelta($esperado, ComprobanteProveedorMonedaMotor::aMonedaLocal(
            self::MONTO, self::DOLAR, self::COTIZACION, '2026-10-08', 'la factura'
        ), 0.01);

        $this->assertEqualsWithDelta($esperado, PagoproveedorAsientoArmadoSupport::convertirImporteAMonedaPago(
            self::MONTO, self::DOLAR, self::PESOS, self::COTIZACION
        ), 0.01);

        $this->assertEqualsWithDelta($esperado, RendicionMaquinaValoresCuentacajaSupport::montoEnPesos(
            self::DOLAR, self::MONTO, self::COTIZACION
        ), 0.01);

        $this->assertEqualsWithDelta(self::COTIZACION, (float) calculaCoeficienteMoneda(
            self::PESOS, self::DOLAR, self::COTIZACION
        ), 0.01);
    }

    public function test_pesos_a_moneda_extranjera_divide(): void
    {
        $this->assertEqualsWithDelta(
            self::MONTO / self::COTIZACION,
            PagoproveedorAsientoArmadoSupport::convertirImporteAMonedaPago(
                self::MONTO, self::PESOS, self::DOLAR, self::COTIZACION
            ),
            0.0001,
        );
    }

    private function assertSinReescalar(float $obtenido): void
    {
        $this->assertEqualsWithDelta(
            self::MONTO,
            $obtenido,
            0.01,
            'Un importe en pesos no se multiplica por la cotización.'
        );
    }
}
