<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionConfiguracionGeneral;
use App\Http\Requests\ValidacionEmpresaJurisdiccionIibb;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Configuracion\EmpresaJurisdiccionIibbSupport;
use App\Support\Configuracion\ParametroSistemaSupport;

class ConfiguracionGeneralController extends Controller
{
    public function __construct(
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index()
    {
        can('editar-configuracion-general');

        $grupos = ParametroSistemaSupport::listarParaFormulario();
        $empresasIibb = $this->empresaRepository->all();
        $matrizIibb = EmpresaJurisdiccionIibbSupport::matrizParaFormulario($empresasIibb);

        return view('configuracion.general.editar', compact('grupos', 'empresasIibb', 'matrizIibb'));
    }

    public function actualizar(ValidacionConfiguracionGeneral $request)
    {
        can('actualizar-configuracion-general');

        ParametroSistemaSupport::guardar($request->validated()['parametros'] ?? []);

        return redirect()
            ->route('configuracion_general')
            ->with('mensaje', 'Configuración general actualizada.');
    }

    public function actualizarAgentesIibb(ValidacionEmpresaJurisdiccionIibb $request)
    {
        can('actualizar-configuracion-general');

        EmpresaJurisdiccionIibbSupport::guardarMatriz($request->input('agentes', []));

        return redirect()
            ->route('configuracion_general')
            ->with('mensaje', 'Agentes IIBB por empresa actualizados.');
    }
}
