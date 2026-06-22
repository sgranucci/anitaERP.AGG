<?php

namespace App\Repositories\Compras;

use Illuminate\Http\Request;

interface Comprobante_Proveedor_ArchivoRepositoryInterface
{
    public function sincronizarDesdeRequest(Request $request, int $comprobanteId): void;
}
