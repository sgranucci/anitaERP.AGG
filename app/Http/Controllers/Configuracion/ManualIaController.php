<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Services\Configuracion\ManualIaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ManualIaController extends Controller
{
    public function __construct(private readonly ManualIaService $manual) {}

    public function index(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $meta = $this->manual->meta();

        return view('configuracion.manual.index', compact('meta'));
    }

    public function descargarPdf(): BinaryFileResponse
    {
        $path = base_path('docs/manual-ia/Manual_Plataforma_IA_AnitaERP.pdf');
        if (! is_file($path)) {
            abort(404, 'Ejecute: php docs/manual-ia/generar.php');
        }

        return response()->download($path, basename($path));
    }

    public function descargarWord(): BinaryFileResponse
    {
        $path = base_path('docs/manual-ia/Manual_Plataforma_IA_AnitaERP.docx');
        if (! is_file($path)) {
            abort(404, 'Ejecute: php docs/manual-ia/generar.php');
        }

        return response()->download($path, basename($path));
    }
}
