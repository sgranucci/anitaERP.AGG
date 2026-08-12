<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Compras\PropuestaPagoClearingBancarioSupport;
use Illuminate\Http\Request;

class ClearingBancarioController extends Controller
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        if (! can('ejecutar-propuesta-pago', false) && ! can('listar-propuesta-pago', false)) {
            can('listar-propuesta-pago');
        }

        $empresaId = (int) $request->query('empresa_id', 0);
        $dias = max(7, min(90, (int) $request->query('dias', 30)));
        $data = PropuestaPagoClearingBancarioSupport::workbench(
            $empresaId > 0 ? $empresaId : null,
            $dias
        );

        return view('compras.clearing_bancario.index', array_merge($data, [
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'empresa_id' => $empresaId,
            'dias' => $dias,
        ]));
    }

    public function procesar(Request $request)
    {
        can('ejecutar-propuesta-pago');
        $propuestaId = (int) $request->input('propuesta_pago_id', 0);
        if ($propuestaId <= 0) {
            return back()->withErrors(['error' => 'Indique propuesta_pago_id.']);
        }
        $r = PropuestaPagoClearingBancarioSupport::procesarPropuesta($propuestaId, true);

        return back()->with('mensaje', $r['mensaje']);
    }

    public function confirmar(int $id)
    {
        can('ejecutar-propuesta-pago');
        $r = PropuestaPagoClearingBancarioSupport::confirmarSugerencia($id);
        if (! $r['ok']) {
            return back()->withErrors(['error' => $r['mensaje']]);
        }

        return back()->with('mensaje', $r['mensaje']);
    }

    public function rechazar(Request $request, int $id)
    {
        can('ejecutar-propuesta-pago');
        $r = PropuestaPagoClearingBancarioSupport::rechazarSugerencia(
            $id,
            (string) $request->input('motivo', '')
        );
        if (! $r['ok']) {
            return back()->withErrors(['error' => $r['mensaje']]);
        }

        return back()->with('mensaje', $r['mensaje']);
    }

    public function forzar(Request $request)
    {
        can('ejecutar-propuesta-pago');
        $opId = (int) $request->input('pagoproveedor_id', 0);
        $transfId = (int) $request->input('interbanking_transferencia_id', 0) ?: null;
        $movId = (int) $request->input('interbanking_movimiento_id', 0) ?: null;
        $r = PropuestaPagoClearingBancarioSupport::forzarMatch($opId, $transfId, $movId);
        if (! $r['ok']) {
            return back()->withErrors(['error' => $r['mensaje']]);
        }

        return back()->with('mensaje', $r['mensaje']);
    }
}
