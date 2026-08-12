<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Compras\ConfiguracionPropuestaPago;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Compras\PropuestaPagoModoSupport;
use Illuminate\Http\Request;

class ConfiguracionPropuestaPagoController extends Controller
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('editar-configuracion-propuesta-pago');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $empresa_id = (int) ($request->input('empresa_id') ?: old('empresa_id') ?: ($empresa_query->first()->id ?? 0));
        $config = PropuestaPagoModoSupport::config($empresa_id);

        return view('compras.configuracion_propuesta_pago.editar', compact(
            'empresa_query',
            'empresa_id',
            'config'
        ));
    }

    public function actualizar(Request $request)
    {
        can('actualizar-configuracion-propuesta-pago');

        $request->validate([
            'empresa_id' => 'required|integer|exists:empresa,id',
            'modo' => 'required|in:premium,light',
        ]);

        $empresaId = (int) $request->input('empresa_id');
        $modo = (string) $request->input('modo');
        $premium = $modo === PropuestaPagoModoSupport::MODO_PREMIUM;

        ConfiguracionPropuestaPago::query()->updateOrCreate(
            ['empresa_id' => $empresaId],
            [
                'modo' => $modo,
                'exige_arbol_aprobacion' => $premium
                    || $request->input('exige_arbol_aprobacion') === '1',
                'ejecutar_confirmada' => $request->input('ejecutar_confirmada') === '1'
                    || $request->boolean('ejecutar_confirmada'),
                'permite_op_sin_propuesta' => $request->input('permite_op_sin_propuesta') === '1'
                    || $request->boolean('permite_op_sin_propuesta'),
            ]
        );

        return redirect()
            ->route('configuracion_propuesta_pago', ['empresa_id' => $empresaId])
            ->with('mensaje', 'Configuración de propuesta de pagos guardada.');
    }
}
