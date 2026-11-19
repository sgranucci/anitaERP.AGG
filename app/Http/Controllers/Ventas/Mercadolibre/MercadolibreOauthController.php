<?php

namespace App\Http\Controllers\Ventas\Mercadolibre;

use App\Http\Controllers\Controller;
use App\Services\Ventas\Mercadolibre\MercadolibreOauthService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class MercadolibreOauthController extends Controller
{
    public function __construct(
        private readonly MercadolibreOauthService $oauth,
    ) {
    }

    public function autorizar()
    {
        $this->assertFerli();
        can('autorizar-mercadolibre');

        $state = bin2hex(random_bytes(16));
        Session::put('mercadolibre_oauth_state', $state);

        return redirect()->away($this->oauth->urlAutorizacion($state));
    }

    public function callback(Request $request)
    {
        $this->assertFerli();
        // Puede volver sin sesión si ML redirige; igual pedimos auth ERP.
        if (! auth()->check()) {
            return redirect()->route('login')->with('error', 'Iniciá sesión para completar la autorización MeLi.');
        }
        can('autorizar-mercadolibre');

        $state = (string) $request->query('state', '');
        $esperado = (string) Session::pull('mercadolibre_oauth_state', '');
        if ($esperado !== '' && $state !== $esperado) {
            return redirect()->route('editar_configuracion_mercadolibre')
                ->with('error', 'State OAuth inválido. Reintentá autorizar.');
        }

        $code = trim((string) $request->query('code', ''));
        if ($code === '') {
            $err = $request->query('error_description') ?: $request->query('error') ?: 'Sin code';

            return redirect()->route('editar_configuracion_mercadolibre')
                ->with('error', 'Autorización cancelada: '.$err);
        }

        $r = $this->oauth->intercambiarCode($code);
        if (! ($r['ok'] ?? false)) {
            return redirect()->route('editar_configuracion_mercadolibre')
                ->with('error', $r['error'] ?? 'No se pudo obtener el token');
        }

        return redirect()->route('editar_configuracion_mercadolibre')
            ->with('mensaje', 'App autorizada. Seller ID: '.($r['seller_id'] ?? '—'));
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }
}
