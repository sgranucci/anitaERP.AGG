<?php

namespace Tests\Unit\Support\Contable\Efe;

use App\Models\Caja\Cuentacaja;
use App\Support\Contable\Efe\EfePosicionFinancieraFuenteErpSupport;
use ReflectionMethod;
use Tests\TestCase;

class EfePosicionFinancieraFuenteErpValormaeTest extends TestCase
{
    public function test_deposito_no_se_confunde_con_etiqueta_corta_qr_en_kandiko(): void
    {
        $valormae = [
            68 => ['desc' => 'QR', 'tipo' => '5'],
            73 => ['desc' => 'TotalCoin QR Maq.', 'tipo' => '9'],
            74 => ['desc' => 'TotalCoin QR Caja', 'tipo' => '9'],
            76 => ['desc' => 'DEPOSITO EFECTIVO PAGO QR', 'tipo' => '7'],
        ];

        $deposito = new Cuentacaja([
            'codigo' => '25',
            'nombre' => 'Deposito efectivo pago QR',
            'descripcion_operaciones' => 'DEPOSITO EFECTIVO PAGO QR',
        ]);
        $qr = new Cuentacaja([
            'codigo' => 'M0QR',
            'nombre' => 'QR Maquinas',
            'descripcion_operaciones' => 'QR',
        ]);

        $this->assertSame(76, $this->resolver(0, $deposito, $valormae));
        $this->assertSame(68, $this->resolver(0, $qr, $valormae));
    }

    public function test_deposito_biyemas_sigue_en_codigo_25(): void
    {
        $valormae = [
            25 => ['desc' => 'DEPOSITO EFECTIVO PAGO QR', 'tipo' => '7'],
            4 => ['desc' => 'QR', 'tipo' => '5'],
            21 => ['desc' => 'Totalcoin QR Maq.', 'tipo' => '9'],
            22 => ['desc' => 'Totalcoin QR Caja', 'tipo' => '9'],
        ];

        $deposito = new Cuentacaja([
            'codigo' => '25',
            'nombre' => 'Deposito efectivo pago QR',
            'descripcion_operaciones' => 'DEPOSITO EFECTIVO PAGO QR',
        ]);
        $qr = new Cuentacaja([
            'codigo' => 'M0QR',
            'nombre' => 'QR Maquinas',
            'descripcion_operaciones' => 'QR',
        ]);

        $this->assertSame(25, $this->resolver(0, $deposito, $valormae));
        $this->assertSame(4, $this->resolver(0, $qr, $valormae));
    }

    /**
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     */
    private function resolver(int $codigo, Cuentacaja $cuenta, array $valormae): ?int
    {
        $method = new ReflectionMethod(EfePosicionFinancieraFuenteErpSupport::class, 'resolverCodigoValormaeMaquina');
        $method->setAccessible(true);

        return $method->invoke(new EfePosicionFinancieraFuenteErpSupport(), $codigo, $cuenta, $valormae);
    }
}
