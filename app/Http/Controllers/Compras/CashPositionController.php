<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Compras\CashPositionSupport;
use Illuminate\Http\Request;

class CashPositionController extends Controller
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        if (! can('listar-propuesta-pago', false) && ! can('listar-pagoproveedor', false)) {
            can('listar-propuesta-pago');
        }

        $empresaId = (int) $request->query('empresa_id', 0);
        $resumen = CashPositionSupport::resumir($empresaId > 0 ? $empresaId : null);
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('compras.cash_position.index', array_merge($resumen, [
            'empresa_query' => $empresa_query,
            'empresa_id' => $empresaId,
        ]));
    }
}
