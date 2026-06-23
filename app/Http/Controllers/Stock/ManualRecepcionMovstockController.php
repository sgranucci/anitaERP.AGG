<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Services\Stock\ManualRecepcionMovstockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ManualRecepcionMovstockController extends Controller
{
    public function __construct(private readonly ManualRecepcionMovstockService $manual)
    {
    }

    public function index(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $meta = $this->manual->meta();

        return view('stock.manual_recepcion_movstock.index', compact('meta'));
    }

    public function descargarPdf(): BinaryFileResponse
    {
        $path = base_path('docs/manual-recepcion-movstock/Manual_Usuario_AnitaERP_Recepcion_Movimientos_Stock.pdf');
        if (! is_file($path)) {
            abort(404, 'Ejecute: php docs/manual-recepcion-movstock/generar.php');
        }

        return response()->download($path, basename($path));
    }

    public function descargarWord(): BinaryFileResponse
    {
        $path = base_path('docs/manual-recepcion-movstock/Manual_Usuario_AnitaERP_Recepcion_Movimientos_Stock.docx');
        if (! is_file($path)) {
            abort(404, 'Ejecute: php docs/manual-recepcion-movstock/generar.php');
        }

        return response()->download($path, basename($path));
    }
}
