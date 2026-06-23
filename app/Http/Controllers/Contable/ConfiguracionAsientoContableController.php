<?php

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionConfiguracionAsientoContable;
use App\Models\Contable\Configuracion_AsientoContable;

class ConfiguracionAsientoContableController extends Controller
{
    public function index()
    {
        can('editar-configuracion-asiento-contable');

        $config = Configuracion_AsientoContable::vigente();

        return view('contable.configuracion_asiento.editar', compact('config'));
    }

    public function actualizar(ValidacionConfiguracionAsientoContable $request)
    {
        can('actualizar-configuracion-asiento-contable');

        $config = Configuracion_AsientoContable::vigente();
        $data = $request->validated();
        $data['enviar_mail_aprobacion'] = (bool) ($request->input('enviar_mail_aprobacion', false));
        $config->fill($data)->save();

        return redirect('contable/configuracion-asiento')
            ->with('mensaje', 'Configuración actualizada.');
    }
}
