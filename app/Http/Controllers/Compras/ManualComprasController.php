<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Services\Compras\ManualComprasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ManualComprasController extends Controller
{
    public function __construct(private readonly ManualComprasService $manual)
    {
    }

    public function index(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $meta = $this->manual->meta();

        return view('compras.manual.index', compact('meta'));
    }

    public function descargarPdf(): BinaryFileResponse
    {
        $path = base_path('docs/manual-compras/Manual_Usuario_AnitaERP_Modulo_Compras.pdf');
        if (! is_file($path)) {
            abort(404, 'Ejecute: php docs/manual-compras/generar.php');
        }

        return response()->download($path, basename($path));
    }

    public function descargarWord(): BinaryFileResponse
    {
        $path = base_path('docs/manual-compras/Manual_Usuario_AnitaERP_Modulo_Compras.docx');
        if (! is_file($path)) {
            abort(404, 'Ejecute: php docs/manual-compras/generar.php');
        }

        return response()->download($path, basename($path));
    }
}
