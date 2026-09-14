<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Support\Ventas\CotConfiguracionSupport;
use Illuminate\Http\Request;

class CotConfiguracionController extends Controller
{
    public function index()
    {
        can('editar-cot-configuracion');

        return view('ventas.cot_configuracion.index', [
            'modo' => CotConfiguracionSupport::modo(),
            'opciones' => CotConfiguracionSupport::opcionesModo(),
            'ambiente' => (string) config('arba_cot.ambiente', 'test'),
        ]);
    }

    public function actualizar(Request $request)
    {
        can('actualizar-cot-configuracion');

        $modo = (string) $request->input('modo', '');
        if (! CotConfiguracionSupport::esModoValido($modo)) {
            return redirect()
                ->route('cot_configuracion')
                ->withErrors(['modo' => 'Seleccione un modo válido.']);
        }

        CotConfiguracionSupport::guardarModo($modo);

        return redirect()
            ->route('cot_configuracion')
            ->with('mensaje', 'Configuración COT actualizada: '.CotConfiguracionSupport::etiquetaModo($modo));
    }
}
