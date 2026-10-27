<?php

namespace App\Support\Ventas;

use App\Models\Configuracion\Salida;
use App\Models\Configuracion\Seteosalida;
use App\Repositories\Configuracion\SeteosalidaRepositoryInterface;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Configuracion\SalidaParaProgramaSupport;
use App\Support\Configuracion\SeteoSalidaProgramaSupport;
use Illuminate\Support\Facades\Auth;

/**
 * El Bierzo: al entrar al index de pedidos, quien no tiene impresora de pedidos
 * queda asignado a la cola del entrepiso (config pedido.impresora_default).
 */
final class PedidoImpresoraDefaultSupport
{
    public static function asegurarParaUsuarioActual(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $usuarioId = (int) (Auth::id() ?: 0);
        if ($usuarioId <= 0) {
            return;
        }

        $nombre = trim((string) config('pedido.impresora_default'));
        if ($nombre === '') {
            return;
        }

        $programa = SeteoSalidaProgramaSupport::VENTAS_PEDIDO;
        $repo = app(SeteosalidaRepositoryInterface::class);
        $existente = $repo->buscaSeteo($usuarioId, $programa);
        if ($existente && $existente->salidas) {
            return;
        }

        $salida = Salida::query()->where('nombre', $nombre)->first();
        if (! $salida) {
            return;
        }

        if (! SalidaParaProgramaSupport::salidaPermitidaParaPrograma($programa, (int) $salida->id)) {
            return;
        }

        if ($existente) {
            $existente->salida_id = $salida->id;
            $existente->save();

            return;
        }

        Seteosalida::query()->firstOrCreate(
            [
                'usuario_id' => $usuarioId,
                'programa' => $programa,
            ],
            [
                'salida_id' => $salida->id,
            ]
        );
    }
}
