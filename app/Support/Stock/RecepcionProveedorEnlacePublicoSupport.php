<?php

namespace App\Support\Stock;

use App\Models\Stock\Recepcion_Proveedor_Token;
use App\Repositories\Stock\Recepcion_ProveedorRepositoryInterface;
use App\Support\Configuracion\OperacionPublicaTokenSupport;

final class RecepcionProveedorEnlacePublicoSupport
{
    public static function urlConsultaMail(int $recepcionId, ?int $usuarioDestinoId = null): string
    {
        if ($usuarioDestinoId === null) {
            $rec = app(Recepcion_ProveedorRepositoryInterface::class)->find($recepcionId);
            $usuarioDestinoId = (int) ($rec->creousuario_id ?? 0) ?: null;
        }

        $token = OperacionPublicaTokenSupport::renovarVisualizar(
            Recepcion_Proveedor_Token::class,
            'recepcion_proveedor_id',
            $recepcionId,
            $usuarioDestinoId,
        );

        return urlAppAbsoluta('stock/recepcion-proveedor/publico/'.$token.'/ver');
    }

    public static function urlComPdfPublico(string $tokenVisualizar): string
    {
        return urlAppAbsoluta('stock/recepcion-proveedor/publico/'.$tokenVisualizar.'/com-pdf');
    }
}
