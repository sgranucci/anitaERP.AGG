<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Comprobante;
use App\Models\Compras\Requisicion;
use App\Support\Compras\AnitaImport\PagoproveedorAnitaImpresionAuxpagMapper;

/**
 * Cadena inversa OP → facturas aplicadas → OC / requisición / COM + URLs de consulta y PDF.
 */
final class PagoproveedorDocumentosRelacionadosSupport
{
    /**
     * @return array{
     *   pagoproveedor_id: int,
     *   etiqueta_op: string,
     *   filas: list<array<string, mixed>>
     * }
     */
    public static function armar(Pagoproveedor $pago): array
    {
        $aplicaciones = Pagoproveedor_Comprobante::query()
            ->where('pagoproveedor_id', (int) $pago->id)
            ->with([
                'monedas:id,abreviatura',
                'proveedor_cuentacorrientes.monedas:id,abreviatura',
                'proveedor_cuentacorrientes.pagoproveedores',
                'proveedor_cuentacorrientes.comprobante_proveedor_cuotas',
                'proveedor_cuentacorrientes.comprobante_proveedores.tipotransaccion_compras',
                'proveedor_cuentacorrientes.comprobante_proveedores.comprobante_proveedor_cuotas',
                'proveedor_cuentacorrientes.comprobante_proveedores.ordencompras.requisiciones',
                'proveedor_cuentacorrientes.comprobante_proveedores.recepcion_proveedores',
            ])
            ->orderBy('id')
            ->get();

        $filas = [];
        $ya = [];
        foreach ($aplicaciones as $apl) {
            $fila = self::filaDesdeAplicacion($apl);
            if ($fila !== null) {
                $filas[] = $fila;
                $facturaId = (int) ($fila['factura']['id'] ?? 0);
                if ($facturaId > 0) {
                    $ya[$facturaId] = true;
                }
            }
        }

        foreach (self::filasDesdeSnapshotAnita($pago, $ya) as $fila) {
            $filas[] = $fila;
        }

        return [
            'pagoproveedor_id' => (int) $pago->id,
            'etiqueta_op' => $pago->etiquetaComprobante(),
            'filas' => $filas,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function filaDesdeAplicacion(Pagoproveedor_Comprobante $apl): ?array
    {
        $cc = $apl->proveedor_cuentacorrientes;
        if ($cc === null) {
            return null;
        }

        $comp = $cc->comprobante_proveedores;
        $monto = abs((float) $apl->montoaplicado);
        $moneda = (string) ($apl->monedas?->abreviatura ?? $cc->monedas?->abreviatura ?? '');

        $fila = [
            'pagoproveedor_comprobante_id' => (int) $apl->id,
            'proveedor_cuentacorriente_id' => (int) $cc->id,
            'etiqueta' => ProveedorCuentacorrienteGrillaSupport::etiquetaComprobanteAbreviado($cc),
            'fecha' => optional($cc->fecha)->format('d/m/Y') ?: '',
            'monto_aplicado' => $monto,
            'monto_aplicado_fmt' => number_format($monto, 2, ',', '.').($moneda !== '' ? ' '.$moneda : ''),
            'es_opa' => PagoproveedorAplicacionLadoSupport::esOpa($cc),
            'factura' => null,
            'ordencompra' => null,
            'requisicion' => null,
            'coms' => [],
        ];

        if ($comp instanceof Comprobante_Proveedor) {
            $fila['factura'] = CircuitoComprasDocumentosRelacionadosSupport::bloqueFactura($comp);
            $oc = $comp->ordencompras;
            if ($oc instanceof Ordencompra) {
                $fila['ordencompra'] = CircuitoComprasDocumentosRelacionadosSupport::bloqueOrdencompra($oc);
                $req = $oc->requisiciones;
                if ($req instanceof Requisicion) {
                    $fila['requisicion'] = CircuitoComprasDocumentosRelacionadosSupport::bloqueRequisicion($req);
                }
            }
            $ocId = $oc instanceof Ordencompra ? (int) $oc->id : 0;
            if ($ocId <= 0) {
                $ocId = (int) $comp->recepcion_proveedores
                    ->map(static fn ($rec) => (int) ($rec->ordencompra_id ?? 0))
                    ->first(static fn (int $id) => $id > 0);
            }
            $fila['coms'] = $ocId > 0
                ? CircuitoComprasDocumentosRelacionadosSupport::bloquesComDeOrdencompra($ocId)
                : CircuitoComprasDocumentosRelacionadosSupport::bloquesComDesdeColeccion($comp->recepcion_proveedores);
        }

        return $fila;
    }

    /**
     * Comprobantes del snapshot Anita cuando la OP no escribió cuenta corriente.
     *
     * @param  array<int, true>  $ya
     * @return list<array<string, mixed>>
     */
    private static function filasDesdeSnapshotAnita(Pagoproveedor $pago, array $ya): array
    {
        $snap = $pago->anita_impresion_json;
        $aplicaciones = is_array($snap) ? ($snap['aplicaciones'] ?? null) : null;
        if (! is_array($aplicaciones) || $aplicaciones === []) {
            return [];
        }

        [$comprobantesSnap] = PagoproveedorAnitaImpresionAuxpagMapper::separarSnapshotAplicaciones($aplicaciones);
        $porNro = [];
        foreach ($comprobantesSnap as $apl) {
            $nro = (int) ($apl['nro_int'] ?? 0);
            if ($nro > 0) {
                $porNro[$nro] = $apl;
            }
        }
        if ($porNro === []) {
            return [];
        }

        $comps = Comprobante_Proveedor::query()
            ->where('empresa_id', (int) $pago->empresa_id)
            ->where('proveedor_id', (int) $pago->proveedor_id)
            ->whereIn('anita_nro_interno', array_keys($porNro))
            ->with([
                'tipotransaccion_compras',
                'monedas:id,abreviatura',
                'ordencompras.requisiciones',
                'recepcion_proveedores',
            ])
            ->get()
            ->keyBy(static fn (Comprobante_Proveedor $comp) => (int) $comp->anita_nro_interno);

        $out = [];
        foreach ($porNro as $nro => $apl) {
            $comp = $comps->get($nro);
            if (! $comp instanceof Comprobante_Proveedor || isset($ya[(int) $comp->id])) {
                continue;
            }
            $out[] = self::filaDesdeComprobanteSnapshot($comp, $apl);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $apl
     * @return array<string, mixed>
     */
    private static function filaDesdeComprobanteSnapshot(Comprobante_Proveedor $comp, array $apl): array
    {
        $monto = abs((float) ($apl['monto_aplicado'] ?? $apl['monto'] ?? 0));
        $moneda = (string) ($comp->monedas?->abreviatura ?? ($apl['moneda'] ?? ''));
        $fila = [
            'pagoproveedor_comprobante_id' => 0,
            'proveedor_cuentacorriente_id' => 0,
            'etiqueta' => (string) ($apl['numero'] ?? ''),
            'fecha' => (string) ($apl['fecha'] ?? ''),
            'monto_aplicado' => $monto,
            'monto_aplicado_fmt' => number_format($monto, 2, ',', '.').($moneda !== '' ? ' '.$moneda : ''),
            'es_opa' => false,
            'factura' => CircuitoComprasDocumentosRelacionadosSupport::bloqueFactura($comp),
            'ordencompra' => null,
            'requisicion' => null,
            'coms' => [],
        ];

        $oc = $comp->ordencompras;
        if ($oc instanceof Ordencompra) {
            $fila['ordencompra'] = CircuitoComprasDocumentosRelacionadosSupport::bloqueOrdencompra($oc);
            $req = $oc->requisiciones;
            if ($req instanceof Requisicion) {
                $fila['requisicion'] = CircuitoComprasDocumentosRelacionadosSupport::bloqueRequisicion($req);
            }
        }
        $ocId = $oc instanceof Ordencompra ? (int) $oc->id : 0;
        if ($ocId <= 0) {
            $ocId = (int) $comp->recepcion_proveedores
                ->map(static fn ($rec) => (int) ($rec->ordencompra_id ?? 0))
                ->first(static fn (int $id) => $id > 0);
        }
        $fila['coms'] = $ocId > 0
            ? CircuitoComprasDocumentosRelacionadosSupport::bloquesComDeOrdencompra($ocId)
            : CircuitoComprasDocumentosRelacionadosSupport::bloquesComDesdeColeccion($comp->recepcion_proveedores);

        return $fila;
    }
}
