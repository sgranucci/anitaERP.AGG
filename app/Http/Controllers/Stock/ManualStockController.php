<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Services\Stock\ManualStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ManualStockController extends Controller
{
    public function __construct(private readonly ManualStockService $manual)
    {
    }

    public function index(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $meta = $this->manual->meta();

        return view('stock.manual.index', compact('meta'));
    }

    public function descargarPdf(): BinaryFileResponse
    {
        $path = base_path('docs/manual-stock/Manual_Usuario_AnitaERP_Recuento_Inventario.pdf');
        if (! is_file($path)) {
            abort(404, 'Ejecute: php docs/manual-stock/generar.php');
        }

        return response()->download($path, basename($path));
    }

    public function descargarWord(): BinaryFileResponse
    {
        $path = base_path('docs/manual-stock/Manual_Usuario_AnitaERP_Recuento_Inventario.docx');
        if (! is_file($path)) {
            abort(404, 'Ejecute: php docs/manual-stock/generar.php');
        }

        return response()->download($path, basename($path));
    }
}
