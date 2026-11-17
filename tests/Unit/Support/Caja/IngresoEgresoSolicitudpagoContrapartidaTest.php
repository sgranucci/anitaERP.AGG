<?php

namespace Tests\Unit\Support\Caja;

use App\Models\Contable\Cuentacontable;
use App\Models\Solicitudpago\Solicitudpago;
use App\Models\Solicitudpago\Solicitudpago_Cuenta;
use App\Support\Caja\IngresoEgresoSolicitudpagoSupport;
use App\Support\Solicitudpago\SolicitudpagoTratamientos;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class IngresoEgresoSolicitudpagoContrapartidaTest extends TestCase
{
    public function test_opp_desembolsa_neto_menos_haber_no_banco(): void
    {
        $sp = $this->solicitudConAsiento(
            5500000.00,
            SolicitudpagoTratamientos::NORMAL,
            [
                ['dh' => 'D', 'codigo' => '521010001', 'monto' => 5500000.00],
                ['dh' => 'H', 'codigo' => '111050016', 'monto' => 5200000.00],
                ['dh' => 'H', 'codigo' => '213010023', 'monto' => 300000.00],
            ]
        );

        $this->assertSame(300000.00, IngresoEgresoSolicitudpagoSupport::contrapartidaHaberNoBanco($sp));
        $this->assertSame(5200000.00, IngresoEgresoSolicitudpagoSupport::montoADesembolsar($sp));
        $this->assertFalse(IngresoEgresoSolicitudpagoSupport::esPagoOpa($sp));
        $this->assertSame('OPP', IngresoEgresoSolicitudpagoSupport::abreviaturaTipoPago($sp));
    }

    public function test_opp_sin_contrapartida_desembolsa_monto_completo(): void
    {
        $sp = $this->solicitudConAsiento(
            1000.00,
            SolicitudpagoTratamientos::NORMAL,
            [
                ['dh' => 'D', 'codigo' => '521010001', 'monto' => 1000.00],
                ['dh' => 'H', 'codigo' => '111050016', 'monto' => 1000.00],
            ]
        );

        $this->assertSame(0.0, IngresoEgresoSolicitudpagoSupport::contrapartidaHaberNoBanco($sp));
        $this->assertSame(1000.00, IngresoEgresoSolicitudpagoSupport::montoADesembolsar($sp));
    }

    public function test_opa_no_usa_contrapartida_haber_no_banco(): void
    {
        $sp = $this->solicitudConAsiento(
            5500000.00,
            SolicitudpagoTratamientos::ANTICIPADA,
            [
                ['dh' => 'D', 'codigo' => '521010001', 'monto' => 5500000.00],
                ['dh' => 'H', 'codigo' => '111050016', 'monto' => 5200000.00],
                ['dh' => 'H', 'codigo' => '213010023', 'monto' => 300000.00],
            ]
        );

        // OPA no mezcla Haber no-banco de la SP: el neto OPA sigue por retenciones.
        $this->assertTrue(IngresoEgresoSolicitudpagoSupport::esPagoOpa($sp));
        $this->assertSame(0.0, IngresoEgresoSolicitudpagoSupport::contrapartidaHaberNoBanco($sp));
    }

    /**
     * @param  list<array{dh: string, codigo: string, monto: float}>  $lineas
     */
    private function solicitudConAsiento(float $monto, string $tratamiento, array $lineas): Solicitudpago
    {
        $sp = new Solicitudpago([
            'monto' => $monto,
            'tratamiento' => $tratamiento,
        ]);

        $cuentas = new Collection();
        foreach ($lineas as $i => $linea) {
            $cta = new Solicitudpago_Cuenta([
                'debe_haber' => $linea['dh'],
                'monto' => $linea['monto'],
                'cuentacontable_id' => $i + 1,
            ]);
            $cta->setRelation('cuentacontables', new Cuentacontable([
                'codigo' => $linea['codigo'],
                'nombre' => 'Cuenta '.$linea['codigo'],
            ]));
            $cuentas->push($cta);
        }
        $sp->setRelation('cuentas', $cuentas);

        return $sp;
    }
}
