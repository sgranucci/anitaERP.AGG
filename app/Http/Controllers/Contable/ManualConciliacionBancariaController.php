<?php

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Services\Contable\ManualConciliacionBancariaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ManualConciliacionBancariaController extends Controller
{
    public function __construct(private readonly ManualConciliacionBancariaService $manual)
    {
    }

    public function index(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        if (! can('listar-conciliacion-bancaria', false)
            && ! can('listar-conciliacion-bancaria-tablero', false)
            && ! can('listar-conciliacion-bancaria-aging', false)
            && ! can('editar-configuracion-conciliacion-bancaria', false)) {
            can('listar-conciliacion-bancaria');
        }

        $meta = $this->manual->meta();

        return view('contable.conciliacion_bancaria.manual_usuario.index', compact('meta'));
    }

    public function descargarPdf(): BinaryFileResponse
    {
        $path = base_path('docs/manual-conciliacion-bancaria/Manual_Usuario_AnitaERP_Conciliacion_Bancaria.pdf');
        if (! is_file($path)) {
            abort(404, 'Ejecute: php docs/manual-conciliacion-bancaria/generar.php');
        }

        return response()->download($path, basename($path));
    }

    public function descargarWord(): BinaryFileResponse
    {
        $path = base_path('docs/manual-conciliacion-bancaria/Manual_Usuario_AnitaERP_Conciliacion_Bancaria.docx');
        if (! is_file($path)) {
            abort(404, 'Ejecute: php docs/manual-conciliacion-bancaria/generar.php');
        }

        return response()->download($path, basename($path));
    }
}
