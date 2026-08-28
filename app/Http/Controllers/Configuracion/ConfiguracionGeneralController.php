<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionConfiguracionGeneral;
use App\Support\Configuracion\ParametroSistemaSupport;

class ConfiguracionGeneralController extends Controller
{
    public function index()
    {
        can('editar-configuracion-general');

        $grupos = ParametroSistemaSupport::listarParaFormulario();

        return view('configuracion.general.editar', compact('grupos'));
    }

    public function actualizar(ValidacionConfiguracionGeneral $request)
    {
        can('actualizar-configuracion-general');

        ParametroSistemaSupport::guardar($request->validated()['parametros'] ?? []);

        return redirect()
            ->route('configuracion_general')
            ->with('mensaje', 'Configuración general actualizada.');
    }
}
