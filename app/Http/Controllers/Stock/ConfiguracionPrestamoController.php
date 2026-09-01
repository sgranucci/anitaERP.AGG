<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionConfiguracionPrestamo;
use App\Models\Stock\Configuracion_Prestamo;

class ConfiguracionPrestamoController extends Controller
{
    public function index()
    {
        can('editar-configuracion-salida-bienes');
        $config = Configuracion_Prestamo::vigente();

        return view('stock.configuracion_prestamo.editar', compact('config'));
    }

    public function actualizar(ValidacionConfiguracionPrestamo $request)
    {
        can('actualizar-configuracion-salida-bienes');

        $config = Configuracion_Prestamo::vigente();
        $data = $request->validated();
        $data['enviar_aprobacion'] = (bool) ($request->input('enviar_aprobacion', false));
        $data['enviar_recordatorios'] = (bool) ($request->input('enviar_recordatorios', false));
        $config->fill($data)->save();

        return redirect()->route('configuracion_salida_bienes')
            ->with('mensaje', 'Configuración de salida de bienes actualizada.');
    }
}
