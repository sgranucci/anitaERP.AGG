<?php

namespace App\Http\Controllers\Solicitudpago;

use App\Http\Controllers\Controller;
use App\Services\Solicitudpago\ManualSolicitudpagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ManualSolicitudpagoController extends Controller
{
    public function __construct(private readonly ManualSolicitudpagoService $manual)
    {
    }

    public function index(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $meta = $this->manual->meta();

        return view('solicitudpago.manual.index', compact('meta'));
    }

    public function descargarPdf(): BinaryFileResponse
    {
        $path = base_path('docs/manual-solicitudpago/Manual_Usuario_AnitaERP_Solicitudes_Pago.pdf');
        if (! is_file($path)) {
            abort(404, 'Ejecute: php docs/manual-solicitudpago/generar.php');
        }

        return response()->download($path, basename($path));
    }

    public function descargarWord(): BinaryFileResponse
    {
        $path = base_path('docs/manual-solicitudpago/Manual_Usuario_AnitaERP_Solicitudes_Pago.docx');
        if (! is_file($path)) {
            abort(404, 'Ejecute: php docs/manual-solicitudpago/generar.php');
        }

        return response()->download($path, basename($path));
    }
}
