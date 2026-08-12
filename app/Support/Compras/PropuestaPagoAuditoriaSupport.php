<?php

namespace App\Support\Compras;

use App\Models\Compras\LoteBancario;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\PropuestaPago;
use App\Models\Compras\PropuestaPagoEstado;
use App\Services\Compras\PropuestaPagoArbolIntegracionService;
use Illuminate\Support\Collection;

/**
 * Pack auditoría / compliance de propuesta de pagos.
 */
class PropuestaPagoAuditoriaSupport
{
    /**
     * @return array{
     *   propuesta: PropuestaPago,
     *   estados: Collection,
     *   firmas_arbol: Collection,
     *   ops: Collection,
     *   lotes: Collection,
     *   resumen: array<string, mixed>
     * }
     */
    public static function armar(int $propuestaPagoId): array
    {
        $propuesta = PropuestaPago::query()
            ->with([
                'empresas',
                'usuarios',
                'lineas.proveedores',
                'pagoproveedores.proveedores',
                'estados.usuarios',
            ])
            ->findOrFail($propuestaPagoId);

        $estados = PropuestaPagoEstado::query()
            ->with('usuarios')
            ->where('propuesta_pago_id', $propuestaPagoId)
            ->orderBy('id')
            ->get();

        $firmas = app(PropuestaPagoArbolIntegracionService::class)
            ->findPorPropuestaPago($propuestaPagoId)
            ->map(function ($m) {
                $dest = $m->destinatariousuarios;
                $env = $m->enviousuarios;

                return (object) [
                    'id' => $m->id,
                    'fecha_envio' => $m->fechaenvio,
                    'fecha_proceso' => $m->fechaproceso,
                    'nivel' => $m->nivel ?? null,
                    'estado' => $m->estado ?? null,
                    'destinatario' => $dest->nombre ?? ($dest->usuario ?? ''),
                    'enviado_por' => $env->nombre ?? ($env->usuario ?? ''),
                    'observacion' => $m->observacion ?? '',
                    'hash' => $m->hashaprobacion ?? '',
                ];
            });

        $ops = Pagoproveedor::query()
            ->with(['proveedores'])
            ->where('propuesta_pago_id', $propuestaPagoId)
            ->orderBy('id')
            ->get()
            ->map(function ($op) {
                $op->bloqueada_banco = PropuestaPagoExcepcionSupport::opBloqueadaBanco($op);

                return $op;
            });

        $lotes = LoteBancario::query()
            ->with('lineas')
            ->where('propuesta_pago_id', $propuestaPagoId)
            ->orderByDesc('id')
            ->get();

        $incluidas = $propuesta->lineas->where('incluido', true);
        $ejecutadas = $incluidas->whereNotNull('pagoproveedor_id');
        $pendientes = $incluidas->filter(fn ($l) => empty($l->pagoproveedor_id) && (float) $l->monto_propuesto > 0);
        $excluidas = $propuesta->lineas->where('incluido', false);

        return [
            'propuesta' => $propuesta,
            'estados' => $estados,
            'firmas_arbol' => $firmas,
            'ops' => $ops,
            'lotes' => $lotes,
            'resumen' => [
                'lineas_incluidas' => $incluidas->count(),
                'lineas_ejecutadas' => $ejecutadas->count(),
                'lineas_pendientes' => $pendientes->count(),
                'lineas_excluidas' => $excluidas->count(),
                'monto_total' => (float) $propuesta->monto_total,
                'monto_autorizado' => (float) ($propuesta->monto_autorizado ?: 0),
                'ops_bloqueadas' => $ops->where('bloqueada_banco', true)->count(),
                'lote_enviado' => (bool) PropuestaPagoExcepcionSupport::loteEnviado($propuestaPagoId),
            ],
        ];
    }
}
