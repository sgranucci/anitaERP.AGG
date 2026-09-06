<?php

namespace App\Services\Compras;

use App\Mail\Compras\OrdencompraDevueltaAComprasMail;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Historia;
use App\Support\Compras\OrdencompraEnvioCuentasAPagarGateSupport;
use App\Support\Seguridad\UsuarioOperativoSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Devuelve el legajo (OC) al sector COMPRAS y notifica por mail a usuarios operativos del sector.
 */
class OrdencompraDevolverAComprasNotificacionService
{
    /**
     * @return array{ok: bool, mensaje: string, emails: list<string>}
     */
    public function devolver(int $ordencompraId, string $motivo, string $detalle = '', ?int $usuarioHistoriaId = null): array
    {
        $oc = Ordencompra::query()
            ->with(['proveedores', 'empresas'])
            ->find($ordencompraId);

        if (! $oc) {
            throw new RuntimeException('Orden de compra inexistente.');
        }

        $sectorComprasId = OrdencompraEnvioCuentasAPagarGateSupport::sectorIdPorNombre(
            OrdencompraEnvioCuentasAPagarGateSupport::SECTOR_COMPRAS
        );
        if ($sectorComprasId <= 0) {
            throw new RuntimeException('No está configurado el sector COMPRAS.');
        }

        $sectorActual = (int) ($oc->sector_legajocompra_id ?? 0);
        $usuarioId = $usuarioHistoriaId && $usuarioHistoriaId > 0
            ? $usuarioHistoriaId
            : (int) (Auth::id() ?: ($oc->creousuario_id ?: 1));
        if ($sectorActual !== $sectorComprasId) {
            DB::transaction(function () use ($oc, $sectorComprasId, $motivo, $detalle, $usuarioId) {
                $oc->update(['sector_legajocompra_id' => $sectorComprasId]);
                Ordencompra_Historia::create([
                    'ordencompra_id' => $oc->id,
                    'sector_legajocompra_id' => $sectorComprasId,
                    'fecha' => Carbon::now(),
                    'observacion' => mb_substr($motivo, 0, 255),
                    'leyenda' => mb_substr($detalle !== '' ? $detalle : $motivo, 0, 2000),
                    'creousuario_id' => $usuarioId,
                ]);
            });
        }

        $usuariosCompras = UsuarioOperativoSupport::listadoParaSelector(
            empresaId: ((int) $oc->empresa_id) > 0 ? (int) $oc->empresa_id : null,
            soloConEmail: true,
            sectorLegajocompraId: $sectorComprasId,
        );
        $emails = $usuariosCompras
            ->pluck('email')
            ->map(fn ($e) => trim((string) $e))
            ->filter(fn ($e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
        $datos = [
            'numero_oc' => $oc->numeroordencompra,
            'ordencompra_id' => (int) $oc->id,
            'proveedor' => (string) ($oc->proveedores?->nombre ?? ''),
            'empresa' => (string) ($oc->empresas?->nombre ?? ''),
            'motivo' => $motivo,
            'detalle' => $detalle,
            'url' => url('compras/ordencompra/'.$oc->id.'/editar'),
        ];

        if ($emails !== []) {
            try {
                Mail::to($emails)->send(new OrdencompraDevueltaAComprasMail($datos));
            } catch (\Throwable $e) {
                Log::warning('No se pudo enviar mail de devolución legajo a COMPRAS: '.$e->getMessage(), [
                    'ordencompra_id' => $oc->id,
                ]);
            }
        }

        try {
            app(\App\Services\Configuracion\AnitaNotificacionService::class)->avisarSistemaAUsuarios(
                $usuariosCompras->pluck('id')->all(),
                'Legajo OC '.$oc->numeroordencompra.' devuelto a COMPRAS',
                mb_substr($motivo !== '' ? $motivo : 'Revisá el legajo en Compras.', 0, 500),
                $datos['url'],
                ['origen' => 'oc_devolver_compras', 'ordencompra_id' => (int) $oc->id]
            );
        } catch (\Throwable) {
        }

        return [
            'ok' => true,
            'mensaje' => 'Legajo OC '.$oc->numeroordencompra.' devuelto a COMPRAS.',
            'emails' => $emails,
        ];
    }
}
