<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Services\Ventas\ManualGastronomiaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ManualGastronomiaController extends Controller
{
    public function __construct(private readonly ManualGastronomiaService $manual)
    {
    }

    public function index(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $meta = $this->manual->meta();

        return view('ventas.manual.index', compact('meta'));
    }

    public function descargarPdf(): BinaryFileResponse
    {
        $path = base_path('docs/manual-gastronomia/Manual_Usuario_AnitaERP_Modulo_Gastronomia.pdf');
        if (! is_file($path)) {
            abort(404, 'Ejecute: php docs/manual-gastronomia/generar.php');
        }

        return response()->download($path, basename($path));
    }

    public function descargarWord(): BinaryFileResponse
    {
        $path = base_path('docs/manual-gastronomia/Manual_Usuario_AnitaERP_Modulo_Gastronomia.docx');
        if (! is_file($path)) {
            abort(404, 'Ejecute: php docs/manual-gastronomia/generar.php');
        }

        return response()->download($path, basename($path));
    }
}
