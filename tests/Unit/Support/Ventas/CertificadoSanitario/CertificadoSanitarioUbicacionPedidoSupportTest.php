<?php

namespace Tests\Unit\Support\Ventas\CertificadoSanitario;

use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Provincia;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Cliente_Entrega;
use App\Models\Ventas\Pedido;
use App\Models\Ventas\Zonavta;
use App\Support\Ventas\CertificadoSanitario\CertificadoSanitarioUbicacionPedidoSupport;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD): prioridad de cliente_entrega sobre cliente/pedido.
 */
class CertificadoSanitarioUbicacionPedidoSupportTest extends TestCase
{
    public function test_sin_entrega_usa_zona_pedido_y_localidad_cliente(): void
    {
        $zonaPed = new Zonavta(['id' => 1, 'codigo' => '217', 'nombre' => 'CAPITAL FEDERAL']);
        $locCli = new Localidad(['id' => 10, 'nombre' => 'MURGUIONDO', 'codigosenasa' => '32978']);
        $prov = new Provincia(['id' => 2, 'nombre' => 'BUENOS AIRES']);
        $locCli->setRelation('provincias', $prov);

        $cliente = new Cliente(['id' => 100]);
        $cliente->setRelation('localidades', $locCli);

        $pedido = new Pedido(['id' => 1, 'cliente_entrega_id' => null, 'zonavta_id' => 1]);
        $pedido->setRelation('zonavtas', $zonaPed);
        $pedido->setRelation('cliente_entregas', null);

        $u = CertificadoSanitarioUbicacionPedidoSupport::resolver($pedido, $cliente);

        self::assertFalse($u['desde_entrega']);
        self::assertSame(1, $u['zona']?->id);
        self::assertSame('MURGUIONDO', $u['localidad']?->nombre);
        self::assertSame(32978, (int) $u['localidad']?->codigosenasa);
    }

    public function test_con_entrega_de_archivo_prioriza_zona_y_localidad_entrega(): void
    {
        $zonaPed = new Zonavta(['id' => 1, 'codigo' => '217', 'nombre' => 'CAPITAL FEDERAL']);
        $zonaEnt = new Zonavta(['id' => 50, 'codigo' => '291', 'nombre' => 'MONTE GRANDE']);
        $locCli = new Localidad(['id' => 10, 'nombre' => 'MURGUIONDO', 'codigosenasa' => '32978']);
        $locEnt = new Localidad(['id' => 20, 'nombre' => 'ISIDRO CASANOVA', 'codigosenasa' => '974']);
        $provEnt = new Provincia(['id' => 2, 'nombre' => 'BUENOS AIRES']);
        $locEnt->setRelation('provincias', $provEnt);

        $cliente = new Cliente(['id' => 100]);
        $cliente->setRelation('localidades', $locCli);

        $entrega = new Cliente_Entrega([
            'id' => 724,
            'cliente_id' => 100,
            'nombre' => 'ISIDRO CASANOVA',
            'localidad_id' => 20,
            'zonavta_id' => 50,
        ]);
        $entrega->setRelation('zonavtas', $zonaEnt);
        $entrega->setRelation('localidades', $locEnt);

        $pedido = new Pedido(['id' => 1, 'cliente_entrega_id' => 724, 'zonavta_id' => 1]);
        $pedido->setRelation('zonavtas', $zonaPed);
        $pedido->setRelation('cliente_entregas', $entrega);

        $u = CertificadoSanitarioUbicacionPedidoSupport::resolver($pedido, $cliente);

        self::assertTrue($u['desde_entrega']);
        self::assertSame(50, $u['zona']?->id);
        self::assertSame('291', (string) $u['zona']?->codigo);
        self::assertSame('ISIDRO CASANOVA', $u['localidad']?->nombre);
        self::assertSame(974, (int) $u['localidad']?->codigosenasa);
        self::assertSame('BUENOS AIRES', $u['provincia']?->nombre);
    }

    public function test_texto_libre_sin_id_no_cuenta_como_entrega(): void
    {
        $pedido = new Pedido([
            'id' => 1,
            'cliente_entrega_id' => null,
            'lugarentrega' => 'ISIDRO CASANOVA',
            'zonavta_id' => 1,
        ]);
        $pedido->setRelation('zonavtas', new Zonavta(['id' => 1, 'codigo' => '217']));
        $pedido->setRelation('cliente_entregas', null);

        self::assertNull(CertificadoSanitarioUbicacionPedidoSupport::entregaDeArchivo($pedido));
    }
}
