<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Models\Compras\Proveedor_Formapago;
use App\Models\Ventas\Formapago;
use Illuminate\Support\Facades\DB;

/**
 * CBU de pago al proveedor (proveedor_formapago / Anita propago).
 * Si hay más de uno válido, la UI abre modal de elección.
 */
final class ProveedorCbuPagoSupport
{
    /**
     * Cuentas con CBU BCRA válido (22 dígitos), una fila por CBU.
     * Prefiere fila con banco asignado y nombre (titular).
     *
     * @return list<array{
     *   id:int,
     *   cbu:string,
     *   alias_cbu:?string,
     *   titular:string,
     *   banco:string,
     *   formapago:string,
     *   numerocuenta:string,
     *   etiqueta:string
     * }>
     */
    public static function listarCbusValidos(int $proveedorId): array
    {
        if ($proveedorId <= 0) {
            return [];
        }

        $idsTransf = Formapago::idsTransferencia();

        $query = DB::table('proveedor_formapago as pf')
            ->leftJoin('banco as b', 'b.id', '=', 'pf.banco_id')
            ->leftJoin('formapago as f', 'f.id', '=', 'pf.formapago_id')
            ->where('pf.proveedor_id', $proveedorId)
            ->whereNotNull('pf.cbu')
            ->where('pf.cbu', '!=', '')
            ->orderByRaw('CASE WHEN pf.banco_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('pf.id')
            ->select([
                'pf.id',
                'pf.cbu',
                'pf.alias_cbu',
                'pf.nombre',
                'pf.numerocuenta',
                'b.nombre as banco_nombre',
                'f.nombre as formapago_nombre',
                'f.abreviatura as formapago_abrev',
            ]);

        if ($idsTransf !== []) {
            $query->where(function ($q) use ($idsTransf) {
                $q->whereIn('pf.formapago_id', $idsTransf)
                    ->orWhereNull('pf.formapago_id');
            });
        }

        $porCbu = [];
        foreach ($query->get() as $fp) {
            $val = CbuSupport::validarConMensaje((string) $fp->cbu);
            if (! $val['ok']) {
                continue;
            }
            $cbu = $val['cbu'];
            if (isset($porCbu[$cbu])) {
                continue;
            }
            $banco = trim((string) ($fp->banco_nombre ?? ''));
            if ($banco === '') {
                $banco = 'Sin banco';
            }
            $titular = trim((string) ($fp->nombre ?? ''));
            $forma = trim((string) ($fp->formapago_nombre ?? $fp->formapago_abrev ?? 'Transfer.'));
            $cuenta = trim((string) ($fp->numerocuenta ?? ''));
            $porCbu[$cbu] = [
                'id' => (int) $fp->id,
                'cbu' => $cbu,
                'alias_cbu' => ($a = trim((string) ($fp->alias_cbu ?? ''))) !== '' ? $a : null,
                'titular' => $titular,
                'banco' => $banco,
                'formapago' => $forma !== '' ? $forma : 'Transfer.',
                'numerocuenta' => $cuenta,
                'etiqueta' => self::etiquetaFila($cbu, $titular, $banco),
            ];
        }

        return array_values($porCbu);
    }

    public static function cantidadCbusValidos(int $proveedorId): int
    {
        return count(self::listarCbusValidos($proveedorId));
    }

    /**
     * @return array{proveedor_formapago_id:?int,cbu_pago:?string}|null
     */
    public static function resolverDesdeRequest(int $proveedorId, mixed $formapagoId, mixed $cbuPago): ?array
    {
        $cbu = CbuSupport::normalizar((string) $cbuPago);
        $fpId = (int) $formapagoId;

        if ($fpId > 0) {
            $fp = Proveedor_Formapago::query()
                ->whereKey($fpId)
                ->where('proveedor_id', $proveedorId)
                ->first();
            if ($fp) {
                $val = CbuSupport::validarConMensaje((string) ($cbu !== '' ? $cbu : $fp->cbu));
                if ($val['ok']) {
                    return [
                        'proveedor_formapago_id' => (int) $fp->id,
                        'cbu_pago' => $val['cbu'],
                    ];
                }
            }
        }

        if ($cbu !== '') {
            $val = CbuSupport::validarConMensaje($cbu);
            if (! $val['ok']) {
                return null;
            }
            $fp = Proveedor_Formapago::query()
                ->where('proveedor_id', $proveedorId)
                ->where('cbu', $val['cbu'])
                ->orderBy('id')
                ->first();

            return [
                'proveedor_formapago_id' => $fp ? (int) $fp->id : null,
                'cbu_pago' => $val['cbu'],
            ];
        }

        $lista = self::listarCbusValidos($proveedorId);
        if (count($lista) === 1) {
            return [
                'proveedor_formapago_id' => $lista[0]['id'],
                'cbu_pago' => $lista[0]['cbu'],
            ];
        }

        return null;
    }

    public static function cbuDesdeDocumento(?int $formapagoId, ?string $cbuPago, int $proveedorId, ?string $detalle = null): string
    {
        $val = CbuSupport::validarConMensaje((string) $cbuPago);
        if ($val['ok']) {
            return $val['cbu'];
        }

        if ($formapagoId && $formapagoId > 0) {
            $fp = Proveedor_Formapago::query()->find($formapagoId);
            if ($fp) {
                $v = CbuSupport::validarConMensaje((string) $fp->cbu);
                if ($v['ok']) {
                    return $v['cbu'];
                }
            }
        }

        if ($detalle) {
            $extraido = PropuestaPagoBridgeBancarioSupport::extraerCbuDesdeDetalle($detalle);
            $v = CbuSupport::validarConMensaje($extraido);
            if ($v['ok']) {
                return $v['cbu'];
            }
        }

        $fp = PropuestaPagoInstrumentoSupport::resolverFormapagoProveedor($proveedorId, null);
        $v = CbuSupport::validarConMensaje((string) ($fp->cbu ?? ''));

        return $v['ok'] ? $v['cbu'] : '';
    }

    private static function etiquetaFila(string $cbu, string $titular, string $banco): string
    {
        $partes = array_filter([$titular, $banco, $cbu], static fn ($p) => trim((string) $p) !== '');

        return implode(' — ', $partes);
    }
}
