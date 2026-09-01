<?php

namespace App\Support\Stock;

use App\Models\Stock\Configuracion_Prestamo;
use App\Models\Stock\Prestamo_Token;
use App\Repositories\Stock\PrestamoRepositoryInterface;
use App\Support\Configuracion\OperacionPublicaTokenSupport;

final class PrestamoEnlacePublicoSupport
{
    public static function urlConsultaMail(int $prestamoId, ?int $usuarioDestinoId = null): string
    {
        if ($usuarioDestinoId === null) {
            $prestamo = app(PrestamoRepositoryInterface::class)->findConRelaciones($prestamoId);
            $usuarioDestinoId = (int) optional($prestamo->solicitante)->id ?: null;
        }

        $config = Configuracion_Prestamo::vigente();
        $horas = max(1, (int) ($config->horas_validez_token ?? config('modulo_aviso.publico_horas_validez_token', 168)));

        $token = OperacionPublicaTokenSupport::renovarVisualizar(
            Prestamo_Token::class,
            'prestamo_id',
            $prestamoId,
            $usuarioDestinoId,
            $horas,
        );

        return urlAppAbsoluta('stock/salida-bienes/publico/'.$token.'/ver');
    }
}
