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
    ) {}

    public function index()
    {
        can('editar-configuracion-general');

        $grupos = ParametroSistemaSupport::listarParaFormulario();
        $empresasIibb = $this->empresaRepository->allFiltrado();
        $matrizIibb = EmpresaJurisdiccionIibbSupport::matrizParaFormulario($empresasIibb);
        $matrizIibbUsaEnv = EmpresaJurisdiccionIibbSupport::matrizUsaFallbackEnv();
        $matrizIibbJursEnv = EmpresaJurisdiccionIibbSupport::desdeEnv('agente_percepcion_iibb');

        return view('configuracion.general.editar', compact(
            'grupos',
            'empresasIibb',
            'matrizIibb',
            'matrizIibbUsaEnv',
            'matrizIibbJursEnv',
        ));
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

        $empresasPermitidas = $this->empresaRepository->allFiltrado()
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->values()
            ->all();
        $agentes = $request->input('agentes', []);
        if (! is_array($agentes)) {
            $agentes = [];
        }
        $agentes = array_filter(
            $agentes,
            static fn ($_, $empresaId) => in_array((int) $empresaId, $empresasPermitidas, true),
            ARRAY_FILTER_USE_BOTH
        );

        try {
            EmpresaJurisdiccionIibbSupport::guardarMatriz($agentes);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('configuracion_general')
                ->withErrors(['agentes' => $e->getMessage()]);
        }

        return redirect()
            ->route('configuracion_general')
            ->with('mensaje', 'Agentes IIBB por empresa actualizados.');
    }
}
