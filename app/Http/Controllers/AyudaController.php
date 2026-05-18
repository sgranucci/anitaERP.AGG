<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class AyudaController extends Controller
{
    /**
     * Portal genérico de manuales (extensible a otros módulos).
     */
    public function index()
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $manuales = [
            [
                'modulo' => 'Compras',
                'descripcion' => 'Proveedores, tablas, requisiciones, listas de precio, presupuestos y órdenes de compra.',
                'url' => route('manual_compras'),
                'icono' => 'fa-shopping-cart',
                'disponible' => true,
            ],
        ];

        return view('ayuda.index', compact('manuales'));
    }
}
