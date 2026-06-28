<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Services\Ventas\ManualVendingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ManualVendingController extends Controller
{
    public function __construct(private readonly ManualVendingService $manual)
    {
    }

    public function index(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $meta = $this->manual->meta();

        return view('ventas.vending.manual.index', compact('meta'));
    }

    public function descargarPdf(): BinaryFileResponse
    {
        $path = base_path('docs/manual-vending/Manual_Usuario_AnitaERP_Vending.pdf');
        if (! is_file($path)) {
            abort(404, 'Ejecute: php docs/manual-vending/generar.php');
        }

        return response()->download($path, basename($path));
    }

    public function descargarWord(): BinaryFileResponse
    {
        $path = base_path('docs/manual-vending/Manual_Usuario_AnitaERP_Vending.docx');
        if (! is_file($path)) {
            abort(404, 'Ejecute: php docs/manual-vending/generar.php');
        }

        return response()->download($path, basename($path));
    }
}
