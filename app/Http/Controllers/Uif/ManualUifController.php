<?php

namespace App\Http\Controllers\Uif;

use App\Http\Controllers\Controller;
use App\Services\Uif\ManualUifService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ManualUifController extends Controller
{
    public function __construct(private readonly ManualUifService $manual)
    {
    }

    public function index(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $meta = $this->manual->meta();

        return view('uif.manual.index', compact('meta'));
    }

    public function descargarPdf(): BinaryFileResponse
    {
        $path = base_path('docs/manual-uif/Manual_Usuario_AnitaERP_Modulo_UIF.pdf');
        if (! is_file($path)) {
            abort(404, 'Ejecute: php docs/manual-uif/generar.php');
        }

        return response()->download($path, basename($path));
    }

    public function descargarWord(): BinaryFileResponse
    {
        $path = base_path('docs/manual-uif/Manual_Usuario_AnitaERP_Modulo_UIF.docx');
        if (! is_file($path)) {
            abort(404, 'Ejecute: php docs/manual-uif/generar.php');
        }

        return response()->download($path, basename($path));
    }
}
