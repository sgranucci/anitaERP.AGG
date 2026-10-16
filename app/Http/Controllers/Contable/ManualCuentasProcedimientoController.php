<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Services\Contable\ManualCuentasProcedimientoService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\Auth;

class ManualCuentasProcedimientoController extends Controller
{
    public function __construct(private readonly ManualCuentasProcedimientoService $manual)
    {
    }

    public function index()
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        if (! EntornoEmpresaSupport::esAgg()) {
            abort(404);
        }

        return view('manual.documento', [
            'meta' => $this->manual->meta(),
            'configKey' => 'manual_cuentas_procedimiento',
            'imgPublicPrefix' => 'docs/manual-cuentas-procedimiento/img',
            'etiquetaModulo' => 'Cuentas BIYEMAS',
            'rutaPdf' => null,
            'rutaWord' => null,
            'atajos' => [
                ['label' => 'Plan de cuentas', 'route' => 'cuentacontable'],
                ['label' => 'Centros de costo', 'route' => 'centrocosto'],
                ['label' => 'Asientos', 'route' => 'asiento'],
            ],
        ]);
    }
}
