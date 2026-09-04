<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ayuda;

use App\Http\Controllers\Controller;
use App\Support\Ayuda\GuiaPasoAPasoCatalogo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GuiaPasoAPasoController extends Controller
{
    public function mostrar(string $slug): BinaryFileResponse|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $path = GuiaPasoAPasoCatalogo::rutaArchivo($slug);
        if ($path === null) {
            abort(404, 'Guía no encontrada');
        }

        return response()->file($path, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
