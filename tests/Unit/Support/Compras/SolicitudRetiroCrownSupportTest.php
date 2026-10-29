<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Compras;

use App\Support\Caja\InterbankingArchivoPagoFormatoSupport;
use App\Support\Compras\SolicitudRetiroCrownSupport;
use PHPUnit\Framework\TestCase;

/**
 * Layout de p-cargasolret: PROBABLE CVU va al Francés; el resto a Interbanking.
 */
class SolicitudRetiroCrownSupportTest extends TestCase
{
    public function test_parte_cvu_y_banco_y_arma_los_dos_archivos(): void
    {
        $csv = implode("\n", [
            '# de Operacion,Monto,Estado de Operación,Nombre,Apellido,DNI,CUIT / CUIL,Cuentas Bancarias,Banco',
            '1001,$100000.00,Autorizado,Rosa Isabel,Aguero,16880450,23-16880450-4,0000003100096247921633,PROBABLE CVU',
            '1002,$1875000.00,Autorizado,Victor David,Asenjo,39585114,20-39585114-5,1430001713025496980016,Brubank S.A.U.',
            '1003,$0.00,Autorizado,Sin,Monto,1,20-00000000-1,0000003100000000000000,PROBABLE CVU',
        ]);

        $cbuOrigen = '0070999020000045678901';
        $resultado = SolicitudRetiroCrownSupport::procesar($csv, $cbuOrigen, 20261029, 7);

        $this->assertSame(3, $resultado['cantidad_datos']);
        $this->assertSame(1, $resultado['cantidad_frances']);
        $this->assertSame(1, $resultado['cantidad_interbanking']);
        $this->assertSame(100000.0, $resultado['total_frances']);
        $this->assertSame(1875000.0, $resultado['total_interbanking']);
        $this->assertCount(1, $resultado['omitidas']);

        $this->assertSame(
            "Rosa Isabel Aguero,0000003100096247921633,0000100000.00,VAR,23168804504\r\n",
            $resultado['frances']
        );

        $lineas = explode("\n", $resultado['interbanking']);
        $cab = $lineas[0];
        $mov = $lineas[1];
        $this->assertSame(InterbankingArchivoPagoFormatoSupport::ANCHO_REGISTRO, strlen($cab));
        $this->assertSame('*U*', substr($cab, 0, 3));
        $this->assertSame($cbuOrigen, substr($cab, 3, 22));
        $this->assertSame('20261029', substr($cab, 26, 8));
        $this->assertSame(str_pad('Solicitudes de retiro', 61), substr($cab, 35, 61));
        $this->assertSame('10/29/26', substr($cab, 101, 8));
        $this->assertSame('00000007', substr($cab, 109, 8));
        $this->assertSame('*M*', substr($mov, 0, 3));
        $this->assertSame('1430001713025496980016', substr($mov, 3, 22));
        $this->assertSame('00000000187500000', substr($mov, 25, 17));
    }

    public function test_nombre_con_enie_queda_en_espacio_como_anita(): void
    {
        $this->assertSame('Boris Eduardo Iba ez', SolicitudRetiroCrownSupport::limpiarNombre('Boris Eduardo', 'Ibañez'));
    }

    public function test_acepta_punto_y_coma_y_decimal_con_coma(): void
    {
        $csv = implode("\n", [
            'Codigo;Monto;Estado;Nombre;Apellido;DNI;CUIT;CBU;Banco',
            '9;$1.500,50;Autorizado;Ana;Paz;1;27-11111111-1;0110012345678901234567;Banco Nacion',
        ]);

        $resultado = SolicitudRetiroCrownSupport::procesar($csv, '0070999020000045678901', 20261029, 1);

        $this->assertSame(1, $resultado['cantidad_interbanking']);
        $this->assertSame(1500.5, $resultado['total_interbanking']);
        $this->assertSame('27111111111', $resultado['filas'][0]['cuit']);
    }
}
