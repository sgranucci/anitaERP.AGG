<?php

namespace App\Support\Compras;

use App\Models\Compras\LoteBancario;
use App\Models\Compras\LoteBancarioLinea;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\PropuestaPago;
use Auth;
use DB;
use Illuminate\Support\Collection;

/**
 * Genera lote bancario (archivo de transferencias) desde OP de una propuesta.
 * No llama API Interbanking: produce snapshot exportable (CSV genérico o driver convenio).
 */
class PropuestaPagoLoteBancarioSupport
{
    /**
     * @return array{ok:bool,mensaje:string,lote_bancario_id?:int,errores_cbu?:list<string>}
     */
    public static function generarDesdePropuesta(int $propuestaPagoId, bool $soloTransferencias = true): array
    {
        $propuesta = PropuestaPago::query()->findOrFail($propuestaPagoId);
        if (! in_array((string) $propuesta->estado, ['EJECUTADA', 'EJECUTADA_PARCIAL', 'AUTORIZADA'], true)) {
            return ['ok' => false, 'mensaje' => 'La propuesta debe estar ejecutada (o autorizada con OP) para armar el lote bancario.'];
        }

        $ops = Pagoproveedor::query()
            ->with(['proveedores', 'pagoproveedor_retenciones'])
            ->where('propuesta_pago_id', $propuestaPagoId)
            ->whereNull('deleted_at')
            ->whereNotIn('estado', ['REVERTIDA', 'BAJA'])
            ->orderBy('id')
            ->get()
            ->filter(fn ($op) => ! PropuestaPagoExcepcionSupport::opBloqueadaBanco($op))
            ->values();

        if ($ops->isEmpty()) {
            return ['ok' => false, 'mensaje' => 'No hay OP del lote disponibles (todas bloqueadas/enviadas o inexistentes).'];
        }

        $filas = [];
        $erroresCbu = [];
        foreach ($ops as $op) {
            $fila = self::armarFila($op);
            $val = CbuSupport::validarConMensaje($fila['cbu']);
            if (! $val['ok']) {
                $erroresCbu[] = 'OP #'.$op->id.' '.$fila['proveedor_nombre'].': '.$val['mensaje'];
                $fila['observacion'] = trim(($fila['observacion'] ?? '').' '.$val['mensaje']);
            } else {
                $fila['cbu'] = $val['cbu'];
            }
            if ($soloTransferencias) {
                $esCheque = stripos((string) $fila['medio'], 'Cheque') !== false;
                if ($esCheque && ! $val['ok']) {
                    continue;
                }
                if (! $val['ok']) {
                    continue;
                }
            } elseif (! $val['ok']) {
                continue;
            }
            $filas[] = $fila;
        }

        if ($filas === []) {
            return [
                'ok' => false,
                'mensaje' => 'Ninguna OP tiene CBU válido (BCRA 22 dígitos). Complete proveedor_formapago.cbu / alias.',
                'errores_cbu' => $erroresCbu,
            ];
        }

        $lote = DB::transaction(function () use ($propuesta, $filas) {
            LoteBancario::query()
                ->where('propuesta_pago_id', $propuesta->id)
                ->whereIn('estado', ['BORRADOR', 'EXPORTADO'])
                ->update(['estado' => 'REEMPLAZADO']);

            $lote = LoteBancario::query()->create([
                'propuesta_pago_id' => $propuesta->id,
                'empresa_id' => (int) $propuesta->empresa_id,
                'cuentacaja_id' => $propuesta->cuentacaja_id,
                'estado' => 'BORRADOR',
                'cantidad_lineas' => count($filas),
                'monto_total' => round(array_sum(array_column($filas, 'monto_neto')), 4),
                'usuario_id' => Auth::id(),
                'convenio_driver' => PropuestaPagoConvenioBancarioSupport::driverActivo()->codigo(),
            ]);

            foreach ($filas as $f) {
                LoteBancarioLinea::query()->create(array_merge($f, [
                    'lote_bancario_id' => $lote->id,
                ]));
            }

            return $lote;
        });

        $msg = 'Lote bancario #'.$lote->id.' con '.$lote->cantidad_lineas.' transferencias (neto '.number_format((float) $lote->monto_total, 2, ',', '.').').';
        if ($erroresCbu !== []) {
            $msg .= ' Omitidas por CBU: '.count($erroresCbu);
        }

        return [
            'ok' => true,
            'mensaje' => $msg,
            'lote_bancario_id' => (int) $lote->id,
            'errores_cbu' => $erroresCbu,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function armarFila(Pagoproveedor $op): array
    {
        $op->loadMissing(['proveedores', 'pagoproveedor_retenciones']);
        $fp = PropuestaPagoInstrumentoSupport::resolverFormapagoProveedor((int) $op->proveedor_id, null);
        $cbu = PropuestaPagoBridgeBancarioSupport::extraerCbuDesdeDetalle((string) ($op->detalle ?? ''));
        if ($cbu === '') {
            $cbu = CbuSupport::normalizar((string) ($fp->cbu ?? ''));
        }
        $alias = trim((string) ($fp->alias_cbu ?? ''));

        $bruto = (float) $op->monto;
        $ret = (float) $op->pagoproveedor_retenciones->sum('monto');
        $neto = round(max(0, $bruto - $ret), 4);
        $prov = $op->proveedores;
        $cuit = preg_replace('/\D+/', '', (string) ($prov->nroinscripcion ?? '')) ?? '';

        $medio = 'Transf';
        if (stripos((string) $op->detalle, 'Cheque') !== false) {
            $medio = 'Cheque';
        }

        return [
            'pagoproveedor_id' => (int) $op->id,
            'proveedor_id' => (int) $op->proveedor_id,
            'proveedor_nombre' => (string) ($prov->nombre ?? ('#'.$op->proveedor_id)),
            'cuit' => $cuit,
            'cbu' => $cbu,
            'alias_cbu' => $alias !== '' ? $alias : null,
            'monto_bruto' => round($bruto, 4),
            'monto_retenciones' => round($ret, 4),
            'monto_neto' => $neto,
            'referencia_op' => $op->etiquetaComprobante(),
            'medio' => $medio,
            'observacion' => null,
        ];
    }

    public static function ultimoLote(int $propuestaPagoId): ?LoteBancario
    {
        return LoteBancario::query()
            ->with('lineas')
            ->where('propuesta_pago_id', $propuestaPagoId)
            ->whereIn('estado', ['BORRADOR', 'EXPORTADO', 'ENVIADO'])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * CSV genérico (separador ; ) apto para importar en home banking.
     */
    public static function contenidoCsv(LoteBancario $lote): string
    {
        $lote->loadMissing('lineas');
        $out = "cuit;cbu;alias_cbu;importe;referencia;proveedor;op_id\n";
        foreach ($lote->lineas as $l) {
            $out .= sprintf(
                "%s;%s;%s;%s;%s;%s;%s\n",
                self::csvCell((string) $l->cuit),
                self::csvCell((string) $l->cbu),
                self::csvCell((string) ($l->alias_cbu ?? '')),
                number_format((float) $l->monto_neto, 2, '.', ''),
                self::csvCell((string) $l->referencia_op),
                self::csvCell((string) $l->proveedor_nombre),
                (string) $l->pagoproveedor_id
            );
        }

        return $out;
    }

    public static function marcarExportado(LoteBancario $lote, string $nombreArchivo): void
    {
        $lote->estado = 'EXPORTADO';
        $lote->archivo_nombre = $nombreArchivo;
        $lote->exportado_at = now();
        $lote->convenio_driver = $lote->convenio_driver ?: PropuestaPagoConvenioBancarioSupport::driverActivo()->codigo();
        $lote->save();
    }

    /**
     * Marca lote como enviado al banco y bloquea las OP del lote (no re-exportar / no anular liviano).
     *
     * @return array{ok:bool,mensaje:string}
     */
    public static function marcarEnviadoBanco(int $loteId): array
    {
        $lote = LoteBancario::query()->with('lineas')->find($loteId);
        if (! $lote) {
            return ['ok' => false, 'mensaje' => 'Lote no encontrado.'];
        }
        if (! in_array((string) $lote->estado, ['BORRADOR', 'EXPORTADO', 'ENVIADO'], true)) {
            return ['ok' => false, 'mensaje' => 'El lote no está vigente para marcar enviado.'];
        }

        DB::transaction(function () use ($lote) {
            $lote->estado = 'ENVIADO';
            $lote->enviado_banco_at = now();
            if (! $lote->exportado_at) {
                $lote->exportado_at = now();
            }
            $lote->save();

            $opIds = $lote->lineas->pluck('pagoproveedor_id')->filter()->unique()->values()->all();
            if ($opIds !== []) {
                Pagoproveedor::query()
                    ->whereIn('id', $opIds)
                    ->update(['bloqueado_banco' => true]);
            }
        });

        return [
            'ok' => true,
            'mensaje' => 'Lote #'.$lote->id.' marcado ENVIADO; OP bloqueadas contra re-propuesta.',
        ];
    }

    private static function csvCell(string $v): string
    {
        $v = str_replace(['"', ';', "\n", "\r"], ' ', $v);

        return $v;
    }

    /**
     * @return Collection<int, LoteBancario>
     */
    public static function historial(int $propuestaPagoId): Collection
    {
        return LoteBancario::query()
            ->where('propuesta_pago_id', $propuestaPagoId)
            ->orderByDesc('id')
            ->limit(20)
            ->get();
    }
}
