<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Support\Caja\IngresoEgresoConfiguracionSupport;
use Illuminate\Http\Request;
use RuntimeException;

class IngresoEgresoConfiguracionController extends Controller
{
    public function editar()
    {
        $this->exigirEntorno();
        can('editar-configuracion-ingresoegreso');

        $muestraCobranzaTarjeta = IngresoEgresoConfiguracionSupport::valorFormulario();

        return view('caja.ingresoegreso.configuracion', compact('muestraCobranzaTarjeta'));
    }

    public function actualizar(Request $request)
    {
        $this->exigirEntorno();
        can('actualizar-configuracion-ingresoegreso');

        $data = $request->validate([
            'muestra_cobranza_tarjeta' => 'required|in:0,1',
        ]);

        try {
            IngresoEgresoConfiguracionSupport::guardarMuestraCobranzaTarjeta(
                $data['muestra_cobranza_tarjeta'] === '1'
            );
        } catch (RuntimeException $e) {
            return redirect()
                ->route('editar_configuracion_ingresoegreso')
                ->withErrors(['muestra_cobranza_tarjeta' => $e->getMessage()]);
        }

        return redirect()
            ->route('editar_configuracion_ingresoegreso')
            ->with('mensaje', 'Configuración de ingresos y egresos actualizada.');
    }

    private function exigirEntorno(): void
    {
        if (! IngresoEgresoConfiguracionSupport::disponibleEnEsteEntorno()) {
            abort(404);
        }
    }
}
