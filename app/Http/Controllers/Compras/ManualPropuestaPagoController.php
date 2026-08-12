<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Services\Compras\ManualPropuestaPagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ManualPropuestaPagoController extends Controller
{
    public function __construct(private readonly ManualPropuestaPagoService $manual)
    {
    }

    public function index(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        if (! can('listar-propuesta-pago', false)
            && ! can('listar-pagoproveedor', false)
            && ! can('listar-ingresos-egresos-caja', false)) {
            can('listar-propuesta-pago');
        }

        $meta = $this->manual->meta();

        return view('compras.propuesta_pago.manual.index', compact('meta'));
    }

    public function descargarPdf(): BinaryFileResponse
    {
        $path = base_path('docs/manual-propuesta-pago/Manual_Usuario_AnitaERP_Propuesta_Pagos.pdf');
        if (! is_file($path)) {
            abort(404, 'Ejecute: php docs/manual-propuesta-pago/generar.php');
        }

        return response()->download($path, basename($path));
    }

    public function descargarWord(): BinaryFileResponse
    {
        $path = base_path('docs/manual-propuesta-pago/Manual_Usuario_AnitaERP_Propuesta_Pagos.docx');
        if (! is_file($path)) {
            abort(404, 'Ejecute: php docs/manual-propuesta-pago/generar.php');
        }

        return response()->download($path, basename($path));
    }
}
