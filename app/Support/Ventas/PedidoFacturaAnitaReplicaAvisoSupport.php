<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaAnitaReplica;
use App\Services\Configuracion\AnitaNotificacionService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Aviso in-app (campanita) cuando falla la réplica Anita diferida de una factura.
 * El HTTP de facturación ya respondió OK: el operador solo se entera por acá / listado.
 */
final class PedidoFacturaAnitaReplicaAvisoSupport
{
    private const CACHE_PREFIX = 'pedido_anita_replica_aviso:';

    /** Anti-spam: una campanita por venta cada N horas mientras siga en error. */
    private const CACHE_HORAS = 6;

    public static function notificarError(int $ventaId, string $mensaje): void
    {
        if ($ventaId <= 0 || ! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $cacheKey = self::CACHE_PREFIX.$ventaId;
        if (! Cache::add($cacheKey, 1, now()->addHours(self::CACHE_HORAS))) {
            return;
        }

        $venta = Venta::query()->find($ventaId);
        $usuarioId = (int) ($venta->usuario_id ?? 0);
        if ($usuarioId <= 0) {
            Log::info('pedido.anita.replica.aviso_sin_usuario', ['venta_id' => $ventaId]);

            return;
        }

        $codigo = trim((string) ($venta->codigo ?? ''));
        if ($codigo === '') {
            $codigo = 'venta #'.$ventaId;
        }
        $detalle = mb_substr(trim($mensaje), 0, 280);
        $titulo = 'Anita pendiente: '.$codigo;
        $cuerpo = 'La factura quedó en ERP/ARCA pero falló la réplica a Anita.'
            .($detalle !== '' ? ' '.$detalle : '')
            .' El cron de regrabado reintentará; revisá el listado de comprobantes.';
        $url = route('editar_factura', ['id' => $ventaId], false);

        try {
            app(AnitaNotificacionService::class)->avisarSistema(
                $usuarioId,
                $titulo,
                $cuerpo,
                $url,
                [
                    'venta_anita_replica' => true,
                    'venta_id' => $ventaId,
                    'estado' => VentaAnitaReplica::ESTADO_ERROR,
                ]
            );
        } catch (Throwable $e) {
            Log::warning('pedido.anita.replica.aviso_fallo', [
                'venta_id' => $ventaId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  list<int>  $ventaIds
     * @return array<int, string> venta_id => estado
     */
    public static function estadosPorVentaIds(array $ventaIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ventaIds))));
        if ($ids === [] || ! class_exists(VentaAnitaReplica::class)) {
            return [];
        }

        $map = [];
        foreach (
            VentaAnitaReplica::query()
                ->whereIn('venta_id', $ids)
                ->get(['venta_id', 'estado']) as $fila
        ) {
            $map[(int) $fila->venta_id] = (string) $fila->estado;
        }

        return $map;
    }
}
