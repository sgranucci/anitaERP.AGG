<?php

namespace App\Http\Controllers\Ventas\Mercadolibre;

use App\Http\Controllers\Controller;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\Mercadolibre\MercadolibreDefaultsSupport;
use App\Support\Ventas\Mercadolibre\MercadolibreTokenSupport;

class MercadolibreConfiguracionController extends Controller
{
    public function editar()
    {
        $this->assertFerli();
        can('editar-configuracion-mercadolibre');

        $defaults = MercadolibreDefaultsSupport::resolver(null);
        $defaultsFull = MercadolibreDefaultsSupport::resolver(['logistic_type' => 'fulfillment']);

        return view('ventas.mercadolibre_configuracion.editar', [
            'oauth' => MercadolibreTokenSupport::estadoParaVista(),
            'appOk' => MercadolibreTokenSupport::appConfigurada(),
            'clientId' => (string) config('mercadolibre.client_id'),
            'redirectUri' => (string) config('mercadolibre.redirect_uri'),
            'notificationsUri' => (string) config('mercadolibre.notifications_uri'),
            'defaults' => $defaults,
            'defaultsFull' => $defaultsFull,
            'puedeAuth' => can('autorizar-mercadolibre', false),
        ]);
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }
}
