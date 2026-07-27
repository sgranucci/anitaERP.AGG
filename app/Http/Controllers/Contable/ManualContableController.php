<?php

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Services\Contable\ManualContableService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ManualContableController extends Controller
{
    public function __construct(private readonly ManualContableService $manual)
    {
    }

    public function index(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $meta = $this->manual->meta();

        return view('contable.manual.index', compact('meta'));
    }

    public function descargarPdf(): BinaryFileResponse
    {
        $path = base_path('docs/manual-contable/Manual_Usuario_AnitaERP_Modulo_Contable.pdf');
        if (! is_file($path)) {
            abort(404, 'Ejecute: php docs/manual-contable/generar.php');
        }

        return response()->download($path, basename($path));
    }

    public function descargarWord(): BinaryFileResponse
    {
        $path = base_path('docs/manual-contable/Manual_Usuario_AnitaERP_Modulo_Contable.docx');
        if (! is_file($path)) {
            abort(404, 'Ejecute: php docs/manual-contable/generar.php');
        }

        return response()->download($path, basename($path));
    }
}
