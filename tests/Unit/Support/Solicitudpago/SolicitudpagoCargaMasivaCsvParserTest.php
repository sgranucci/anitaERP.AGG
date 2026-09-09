<?php

namespace Tests\Unit\Support\Solicitudpago;

use App\Support\Solicitudpago\SolicitudpagoCargaMasivaCsvParser;
use PHPUnit\Framework\TestCase;

class SolicitudpagoCargaMasivaCsvParserTest extends TestCase
{
    public function test_parsea_suss_con_un_haber_y_varios_debe_desde_encabezado(): void
    {
        $csv = <<<'CSV'
Empresa,Proveedor,Concepto,Sector,Forma de pago,Beneficiario ,Moneda,Detalle ,FECHA DE VENCIMIENTO,total del pago,Nro. Cuenta Haber,Importe haber,Nro. Cuenta Debe,Importe debe,Nro. Cuenta Debe,Importe debe,Nro. Cuenta Debe,Importe debe,Nro. Cuenta Debe,Importe debe
1,1299,58,12,4,,1,SUSS 08-2026,09/09/2026,117598194.2,111050-009,117598194.2,213010-003,19401796.79,213010-002,90333173.73,213010-005,7791887.54,213010-007,71336.16
CSV;

        $parser = new SolicitudpagoCargaMasivaCsvParser;
        $filas = $parser->parsear($csv);

        $this->assertCount(1, $filas);
        $fila = $filas[0];
        $this->assertSame(1, $fila['empresa_codigo']);
        $this->assertSame(58, $fila['concepto_codigo']);
        $this->assertSame(117598194.2, $fila['monto']);
        $this->assertSame('2026-09-09', $fila['fecha_vencimiento']);

        $cuentas = $fila['cuentas'];
        $this->assertCount(5, $cuentas);
        $this->assertSame(['H', 'D', 'D', 'D', 'D'], array_column($cuentas, 'debe_haber'));
        $this->assertSame(
            ['111050009', '213010003', '213010002', '213010005', '213010007'],
            array_column($cuentas, 'cuenta_codigo')
        );
        $this->assertEqualsWithDelta(117598194.2, $cuentas[0]['monto'], 0.001);
        $this->assertEqualsWithDelta(71336.16, $cuentas[4]['monto'], 0.001);
    }

    public function test_sin_encabezado_no_inventa_dh_en_pares(): void
    {
        // Formato Anita clásico sin fila de títulos: D/H queda null (lo resuelve el servicio).
        $csv = '1,1299,58,12,4,,1,Detalle,09/09/2026,100.00,111050-009,100.00,213010-003,100.00';

        $parser = new SolicitudpagoCargaMasivaCsvParser;
        $filas = $parser->parsear($csv);

        $this->assertCount(1, $filas);
        $this->assertSame([null, null], array_column($filas[0]['cuentas'], 'debe_haber'));
        $this->assertSame(['111050009', '213010003'], array_column($filas[0]['cuentas'], 'cuenta_codigo'));
    }

    public function test_omite_fila_encabezado_sin_datos(): void
    {
        $csv = "Empresa,Proveedor,Concepto\n";

        $parser = new SolicitudpagoCargaMasivaCsvParser;
        $this->assertSame([], $parser->parsear($csv));
    }
}
