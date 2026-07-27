<?php

namespace App\Support\Contable;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Ordencompra;
use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Ventas\Venta;
use App\Support\Stock\RecepcionProveedorAnitaClaveSupport;

/**
 * Normaliza FKs de referencia del asiento manual y proyecta claves Anita (ctav_*).
 */
class AsientoReferenciaAnitaSupport
{
    public const TIPO_NINGUNA = 'ninguna';

    public const TIPO_ORDENCOMPRA = 'ordencompra';

    public const TIPO_COMPROBANTE_PROVEEDOR = 'comprobante_proveedor';

    public const TIPO_VENTA = 'venta';

    public const TIPO_OC_Y_COMPROBANTE = 'ordencompra_y_comprobante';

    /** @return list<string> */
    public static function tiposValidos(): array
    {
        return [
            self::TIPO_NINGUNA,
            self::TIPO_ORDENCOMPRA,
            self::TIPO_COMPROBANTE_PROVEEDOR,
            self::TIPO_VENTA,
            self::TIPO_OC_Y_COMPROBANTE,
        ];
    }

    /**
     * Conserva FKs de origen proceso que el form de referencias del CRUD no edita
     * (recepción, movimiento stock, etc.) para no perderlas al actualizar.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function conservarFksOrigenProceso(array $data, ?object $asientoExistente): array
    {
        if (! $asientoExistente) {
            return $data;
        }

        foreach (['recepcionproveedor_id', 'movimientostock_id', 'compra_id', 'caja_movimiento_id', 'cobranza_id', 'pagoproveedor_id'] as $fk) {
            $actual = (int) ($data[$fk] ?? 0);
            $existente = (int) ($asientoExistente->{$fk} ?? 0);
            if ($actual <= 0 && $existente > 0) {
                $data[$fk] = $existente;
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function aplicarAPayload(array $data): array
    {
        $tipo = (string) ($data['referencia_tipo'] ?? '');
        if ($tipo === '' || ! in_array($tipo, self::tiposValidos(), true)) {
            $tipo = self::inferirTipoDesdeFks($data);
        }

        $data['referencia_tipo'] = $tipo;
        $data = self::limpiarFksSegunTipo($data, $tipo);

        $ocId = (int) ($data['ordencompra_id'] ?? 0);
        $cpId = (int) ($data['comprobante_proveedor_id'] ?? 0);
        $ventaId = (int) ($data['venta_id'] ?? 0);
        $recepcionId = (int) ($data['recepcionproveedor_id'] ?? 0);

        $data['ordencompra_id'] = $ocId > 0 ? $ocId : null;
        $data['comprobante_proveedor_id'] = $cpId > 0 ? $cpId : null;
        $data['venta_id'] = $ventaId > 0 ? $ventaId : null;
        // No blanquear recepción en mass-assignment si el form no la manda.
        if ($recepcionId > 0) {
            $data['recepcionproveedor_id'] = $recepcionId;
        } else {
            unset($data['recepcionproveedor_id']);
        }

        $anita = self::resolverClavesAnita($ocId, $cpId, $ventaId, $recepcionId);
        foreach ($anita as $k => $v) {
            $data[$k] = $v;
        }

        return $data;
    }

    public static function inferirTipoDesdeFks(array $data): string
    {
        $oc = (int) ($data['ordencompra_id'] ?? 0) > 0;
        $cp = (int) ($data['comprobante_proveedor_id'] ?? 0) > 0;
        $venta = (int) ($data['venta_id'] ?? 0) > 0;

        if ($venta) {
            return self::TIPO_VENTA;
        }
        if ($oc && $cp) {
            return self::TIPO_OC_Y_COMPROBANTE;
        }
        if ($cp) {
            return self::TIPO_COMPROBANTE_PROVEEDOR;
        }
        if ($oc) {
            return self::TIPO_ORDENCOMPRA;
        }

        return self::TIPO_NINGUNA;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function limpiarFksSegunTipo(array $data, string $tipo): array
    {
        switch ($tipo) {
            case self::TIPO_ORDENCOMPRA:
                $data['comprobante_proveedor_id'] = null;
                $data['venta_id'] = null;
                break;
            case self::TIPO_COMPROBANTE_PROVEEDOR:
                $data['ordencompra_id'] = null;
                $data['venta_id'] = null;
                break;
            case self::TIPO_VENTA:
                $data['ordencompra_id'] = null;
                $data['comprobante_proveedor_id'] = null;
                break;
            case self::TIPO_OC_Y_COMPROBANTE:
                $data['venta_id'] = null;
                break;
            default:
                $data['ordencompra_id'] = null;
                $data['comprobante_proveedor_id'] = null;
                $data['venta_id'] = null;
                break;
        }

        return $data;
    }

    /**
     * @return array{ctav_o_compra: int, tipo?: string, letra?: string, sucursal?: int|string, nro?: int|string, sistema_ctav?: string}
     */
    public static function resolverClavesAnita(
        int $ordencompraId,
        int $comprobanteProveedorId,
        int $ventaId,
        int $recepcionProveedorId = 0,
    ): array {
        $out = ['ctav_o_compra' => 0];

        // Recepción COM: prioridad sobre OC sola (el CRUD de asientos suele mostrar solo la OC).
        if ($recepcionProveedorId > 0) {
            $recepcion = Recepcion_Proveedor::query()->find($recepcionProveedorId);
            if ($recepcion) {
                $clave = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
                $out['tipo'] = (string) $clave['tipo'];
                $out['letra'] = (string) $clave['letra'];
                $out['sucursal'] = (int) $clave['sucursal'];
                $out['nro'] = (int) $clave['nro'];
                $out['sistema_ctav'] = 'C';

                $ocId = $ordencompraId > 0 ? $ordencompraId : (int) ($recepcion->ordencompra_id ?? 0);
                if ($ocId > 0) {
                    $nroOc = (int) Ordencompra::query()->whereKey($ocId)->value('numeroordencompra');
                    if ($nroOc > 0) {
                        $out['ctav_o_compra'] = $nroOc;
                    }
                }

                return $out;
            }
        }

        if ($comprobanteProveedorId > 0) {
            $cp = Comprobante_Proveedor::query()
                ->with(['ordencompras:id,numeroordencompra', 'tipotransaccion_compras:id,abreviatura'])
                ->find($comprobanteProveedorId);
            if ($cp) {
                $tipoAbrev = (string) ($cp->tipotransaccion_compras?->abreviatura ?? 'FAC');
                $out['tipo'] = substr($tipoAbrev, 0, 3);
                $out['letra'] = (string) ($cp->letra ?? ' ');
                $out['sucursal'] = (int) ($cp->sucursal ?? 0);
                $out['nro'] = (int) ($cp->numerocomprobante ?? 0);
                $out['sistema_ctav'] = 'C';
                $nroOc = (int) ($cp->ordencompras?->numeroordencompra ?? 0);
                if ($nroOc > 0) {
                    $out['ctav_o_compra'] = $nroOc;
                }
            }
        }

        if ($ordencompraId > 0 && ($out['ctav_o_compra'] ?? 0) === 0) {
            $nroOc = (int) Ordencompra::query()->whereKey($ordencompraId)->value('numeroordencompra');
            if ($nroOc > 0) {
                $out['ctav_o_compra'] = $nroOc;
            }
        }

        if ($ventaId > 0) {
            $venta = Venta::query()
                ->with([
                    'tipotransacciones:id,abreviatura',
                    'puntoventas:id,codigo',
                    'condicionivas:id,letra',
                    'clientes:id,nombre',
                ])
                ->find($ventaId);
            if ($venta) {
                $tipoAbrev = (string) ($venta->tipotransacciones?->abreviatura ?? 'FAC');
                $letra = (string) ($venta->condicionivas?->letra
                    ?? $venta->clientes?->condicionivas?->letra
                    ?? 'A');
                $out['tipo'] = substr($tipoAbrev, 0, 3);
                $out['letra'] = $letra !== '' ? $letra : 'A';
                $out['sucursal'] = (int) ($venta->puntoventas?->codigo ?? 0);
                $out['nro'] = (int) ($venta->numerocomprobante ?? 0);
                $out['sistema_ctav'] = 'V';
            }
        }

        return $out;
    }

    /**
     * Etiquetas para precargar el form de edición.
     *
     * @return array{
     *   referencia_tipo: string,
     *   ordencompra_id: int,
     *   ordencompra_codigo: string,
     *   ordencompra_descripcion: string,
     *   comprobante_proveedor_id: int,
     *   comprobante_proveedor_codigo: string,
     *   comprobante_proveedor_descripcion: string,
     *   venta_id: int,
     *   venta_codigo: string,
     *   venta_descripcion: string
     * }
     */
    public static function etiquetasDesdeAsiento(?object $asiento): array
    {
        $base = [
            'referencia_tipo' => self::TIPO_NINGUNA,
            'ordencompra_id' => 0,
            'ordencompra_codigo' => '',
            'ordencompra_descripcion' => '',
            'comprobante_proveedor_id' => 0,
            'comprobante_proveedor_codigo' => '',
            'comprobante_proveedor_descripcion' => '',
            'venta_id' => 0,
            'venta_codigo' => '',
            'venta_descripcion' => '',
        ];

        if (! $asiento) {
            return $base;
        }

        $fks = [
            'ordencompra_id' => (int) ($asiento->ordencompra_id ?? 0),
            'comprobante_proveedor_id' => (int) ($asiento->comprobante_proveedor_id ?? 0),
            'venta_id' => (int) ($asiento->venta_id ?? 0),
        ];
        $base['referencia_tipo'] = self::inferirTipoDesdeFks($fks);
        $base['ordencompra_id'] = $fks['ordencompra_id'];
        $base['comprobante_proveedor_id'] = $fks['comprobante_proveedor_id'];
        $base['venta_id'] = $fks['venta_id'];

        if ($fks['ordencompra_id'] > 0) {
            $oc = $asiento->ordencompras ?? Ordencompra::query()->with('proveedores:id,nombre')->find($fks['ordencompra_id']);
            if ($oc) {
                $base['ordencompra_codigo'] = (string) ($oc->numeroordencompra ?? '');
                $base['ordencompra_descripcion'] = trim('OC '.$base['ordencompra_codigo'].' · '.($oc->proveedores->nombre ?? ''));
            }
        }

        if ($fks['comprobante_proveedor_id'] > 0) {
            $cp = $asiento->comprobante_proveedores
                ?? Comprobante_Proveedor::query()
                    ->with(['proveedores:id,nombre', 'tipotransaccion_compras:id,abreviatura'])
                    ->find($fks['comprobante_proveedor_id']);
            if ($cp) {
                $abrev = (string) ($cp->tipotransaccion_compras?->abreviatura ?? '');
                $comp = trim($abrev.' '.$cp->letra.'-'.str_pad((string) $cp->sucursal, 4, '0', STR_PAD_LEFT).'-'.$cp->numerocomprobante);
                $base['comprobante_proveedor_codigo'] = $comp;
                $base['comprobante_proveedor_descripcion'] = trim($comp.' · '.($cp->proveedores->nombre ?? ''));
            }
        }

        if ($fks['venta_id'] > 0) {
            $venta = $asiento->ventas
                ?? Venta::query()
                    ->with(['clientes:id,nombre', 'tipotransacciones:id,abreviatura', 'puntoventas:id,codigo'])
                    ->find($fks['venta_id']);
            if ($venta) {
                $abrev = (string) ($venta->tipotransacciones?->abreviatura ?? '');
                $pv = (string) ($venta->puntoventas?->codigo ?? '');
                $comp = trim($abrev.' '.$pv.'-'.$venta->numerocomprobante);
                $base['venta_codigo'] = $comp;
                $base['venta_descripcion'] = trim($comp.' · '.($venta->clientes->nombre ?? $venta->nombre ?? ''));
            }
        }

        return $base;
    }
}
