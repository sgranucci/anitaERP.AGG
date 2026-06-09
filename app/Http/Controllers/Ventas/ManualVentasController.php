<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Services\Ventas\ManualVentasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ManualVentasController extends Controller
{
    public function __construct(private readonly ManualVentasService $manual)
    {
    }

    public function index(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $meta = $this->manual->meta();

        return view('ventas.manual.pedidos.index', compact('meta'));
    }

    public function descargarPdf(): BinaryFileResponse
    {
        $path = base_path('docs/manual-ventas/Manual_Usuario_AnitaERP_Pedidos_Facturacion.pdf');
        if (! is_file($path)) {
            abort(404, 'Ejecute: php docs/manual-ventas/generar.php');
        }

        return response()->download($path, basename($path));
    }

    public function descargarWord(): BinaryFileResponse
    {
        $path = base_path('docs/manual-ventas/Manual_Usuario_AnitaERP_Pedidos_Facturacion.docx');
        if (! is_file($path)) {
            abort(404, 'Ejecute: php docs/manual-ventas/generar.php');
        }

        return response()->download($path, basename($path));
    }
}
