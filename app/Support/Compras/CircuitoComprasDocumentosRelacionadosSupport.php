<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Requisicion;
use App\Models\Stock\Recepcion_Proveedor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Circuito procure-to-pay: RQ → OC → COM → Factura → OP.
 *
 * Cada origen arma filas “hacia atrás y adelante” con bloques reutilizables
 * (consulta / PDF) para el modal de documentos relacionados.
 */
final class CircuitoComprasDocumentosRelacionadosSupport
{
    /**
     * @return array{origen: string, origen_id: int, etiqueta_origen: string, filas: list<array<string, mixed>>}
     */
    public static function armarDesdeRequisicion(Requisicion $req): array
    {
        $ocs = Ordencompra::query()
            ->where('requisicion_id', (int) $req->id)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        $filas = [];
        foreach ($ocs as $oc) {
            foreach (self::filasDesdeOc($oc, $req) as $fila) {
                $filas[] = $fila;
            }
        }

        return self::respuesta('requisicion', (int) $req->id, 'Requisición '.$req->numerorequisicion, $filas);
    }

    /**
     * @return array{origen: string, origen_id: int, etiqueta_origen: string, filas: list<array<string, mixed>>}
     */
    public static function armarDesdeOrdencompra(Ordencompra $oc): array
    {
        $oc->loadMissing('requisiciones');
        $req = $oc->requisiciones instanceof Requisicion ? $oc->requisiciones : null;
        $filas = self::filasDesdeOc($oc, $req);

        return self::respuesta(
            'ordencompra',
            (int) $oc->id,
            'OC '.((string) ($oc->numeroordencompra ?? $oc->id)),
            $filas
        );
    }

    /**
     * @return array{origen: string, origen_id: int, etiqueta_origen: string, filas: list<array<string, mixed>>}
     */
    public static function armarDesdeRecepcion(Recepcion_Proveedor $rec): array
    {
        $rec->loadMissing('ordencompras.requisiciones');
        $oc = $rec->ordencompras instanceof Ordencompra ? $rec->ordencompras : null;
        $req = $oc?->requisiciones instanceof Requisicion ? $oc->requisiciones : null;

        $facturas = self::facturasVinculadasARecepcion((int) $rec->id);

        $bloqueReq = $req ? self::bloqueRequisicion($req) : null;
        $bloqueOc = $oc ? self::bloqueOrdencompra($oc) : null;
        $bloqueCom = self::bloqueCom($rec);

        if ($facturas->isEmpty()) {
            $filas = [[
                'requisicion' => $bloqueReq,
                'ordencompra' => $bloqueOc,
                'coms' => [$bloqueCom],
                'factura' => null,
                'ops' => [],
            ]];
        } else {
            $filas = [];
            foreach ($facturas as $fact) {
                $filas[] = [
                    'requisicion' => $bloqueReq,
                    'ordencompra' => $bloqueOc ?? ($fact->ordencompras instanceof Ordencompra
                        ? self::bloqueOrdencompra($fact->ordencompras)
                        : null),
                    'coms' => [$bloqueCom],
                    'factura' => self::bloqueFactura($fact),
                    'ops' => self::bloquesOpParaComprobante((int) $fact->id),
                ];
            }
        }

        return self::respuesta(
            'recepcion',
            (int) $rec->id,
            'COM '.((string) ($rec->numerorecepcion ?? $rec->id)),
            $filas
        );
    }

    /**
     * @return array{origen: string, origen_id: int, etiqueta_origen: string, filas: list<array<string, mixed>>}
     */
    public static function armarDesdeComprobanteProveedor(Comprobante_Proveedor $comp): array
    {
        $comp->loadMissing([
            'tipotransaccion_compras:id,abreviatura',
            'ordencompras.requisiciones',
            'recepcion_proveedores',
        ]);

        $oc = $comp->ordencompras instanceof Ordencompra ? $comp->ordencompras : null;
        $req = $oc?->requisiciones instanceof Requisicion ? $oc->requisiciones : null;

        $fila = [
            'requisicion' => $req ? self::bloqueRequisicion($req) : null,
            'ordencompra' => $oc ? self::bloqueOrdencompra($oc) : null,
            'coms' => self::bloquesComDesdeColeccion($comp->recepcion_proveedores),
            'factura' => self::bloqueFactura($comp),
            'ops' => self::bloquesOpParaComprobante((int) $comp->id),
        ];

        $etiqueta = sprintf(
            '%s %s-%04d-%s',
            $comp->tipotransaccion_compras?->abreviatura ?? 'FAC',
            $comp->letra,
            (int) $comp->sucursal,
            $comp->numerocomprobante
        );

        return self::respuesta('factura', (int) $comp->id, $etiqueta, [$fila]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function filasDesdeOc(Ordencompra $oc, ?Requisicion $req): array
    {
        $bloqueReq = $req ? self::bloqueRequisicion($req) : null;
        $bloqueOc = self::bloqueOrdencompra($oc);

        $comsOc = Recepcion_Proveedor::query()
            ->where('ordencompra_id', (int) $oc->id)
            ->orderByDesc('id')
            ->get();

        $facturas = Comprobante_Proveedor::query()
            ->where('ordencompra_id', (int) $oc->id)
            ->with(['tipotransaccion_compras:id,abreviatura', 'recepcion_proveedores'])
            ->orderByDesc('id')
            ->get();

        if ($facturas->isEmpty()) {
            return [[
                'requisicion' => $bloqueReq,
                'ordencompra' => $bloqueOc,
                'coms' => self::bloquesComDesdeColeccion($comsOc),
                'factura' => null,
                'ops' => [],
            ]];
        }

        $filas = [];
        foreach ($facturas as $fact) {
            $comsFact = $fact->recepcion_proveedores;
            $comsMostrar = $comsFact->isNotEmpty() ? $comsFact : $comsOc;
            $filas[] = [
                'requisicion' => $bloqueReq,
                'ordencompra' => $bloqueOc,
                'coms' => self::bloquesComDesdeColeccion($comsMostrar),
                'factura' => self::bloqueFactura($fact),
                'ops' => self::bloquesOpParaComprobante((int) $fact->id),
            ];
        }

        return $filas;
    }

    /**
     * @return Collection<int, Comprobante_Proveedor>
     */
    private static function facturasVinculadasARecepcion(int $recepcionId): Collection
    {
        if ($recepcionId <= 0) {
            return collect();
        }

        return Comprobante_Proveedor::query()
            ->whereHas('recepcion_proveedores', static function ($q) use ($recepcionId) {
                $q->where('recepcion_proveedor.id', $recepcionId);
            })
            ->with(['tipotransaccion_compras:id,abreviatura', 'ordencompras.requisiciones'])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{origen: string, origen_id: int, etiqueta_origen: string, filas: list<array<string, mixed>>}
     */
    private static function respuesta(string $origen, int $origenId, string $etiqueta, array $filas): array
    {
        return [
            'origen' => $origen,
            'origen_id' => $origenId,
            'etiqueta_origen' => $etiqueta,
            'filas' => $filas,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function bloqueFactura(Comprobante_Proveedor $comp): array
    {
        $id = (int) $comp->id;
        $puedeVer = can('editar-comprobante-proveedor', false)
            || can('listar-comprobante-proveedor', false);

        $bloque = [
            'id' => $id,
            'etiqueta' => sprintf(
                '%s %s-%04d-%s',
                $comp->tipotransaccion_compras?->abreviatura ?? 'FAC',
                $comp->letra,
                (int) $comp->sucursal,
                $comp->numerocomprobante
            ),
        ];

        if ($puedeVer) {
            $bloque['url_ver'] = route('editar_comprobante_proveedor', [
                'id' => $id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ]);
            $bloque['url_pdf'] = route('comprobante_proveedor_factura_pdf', [
                'id' => $id,
                'inline' => 1,
            ]);
        }

        return $bloque;
    }

    /**
     * @return array<string, mixed>
     */
    public static function bloqueOrdencompra(Ordencompra $oc): array
    {
        $id = (int) $oc->id;
        $puedeVer = can('listar-ordencompra', false) || can('editar-ordencompra', false);

        $bloque = [
            'id' => $id,
            'numero' => (string) ($oc->numeroordencompra ?? ''),
            'etiqueta' => 'OC '.((string) ($oc->numeroordencompra ?? $id)),
        ];

        if ($puedeVer) {
            $bloque['url_ver'] = route('solo_consulta_ordencompra', ['id' => $id]);
            $bloque['url_pdf'] = route('imprimir_pdf_ordencompra', ['id' => $id]);
            $bloque['url_pdf_apaisado'] = route('imprimir_pdf_ordencompra', [
                'id' => $id,
                'formato' => 'apaisado',
            ]);
        }

        return $bloque;
    }

    /**
     * @return array<string, mixed>
     */
    public static function bloqueRequisicion(Requisicion $req): array
    {
        $id = (int) $req->id;
        $puedeVer = can('listar-requisicion', false) || can('editar-requisicion', false);

        $bloque = [
            'id' => $id,
            'numero' => (string) ($req->numerorequisicion ?? ''),
            'etiqueta' => 'REQ '.((string) ($req->numerorequisicion ?? $id)),
        ];

        if ($puedeVer) {
            $bloque['url_ver'] = route('solo_consulta_requisicion', ['id' => $id]);
            $bloque['url_pdf'] = route('imprimir_pdf_requisicion', ['id' => $id]);
        }

        return $bloque;
    }

    /**
     * @return array<string, mixed>
     */
    public static function bloqueCom(Recepcion_Proveedor $rec): array
    {
        $id = (int) $rec->id;
        $puedeVer = can('editar-recepcion-proveedor', false)
            || can('listar-recepcion-proveedor', false);

        $bloque = [
            'id' => $id,
            'numero' => (string) ($rec->numerorecepcion ?? $id),
            'etiqueta' => 'COM '.((string) ($rec->numerorecepcion ?? $id)),
        ];

        if ($puedeVer) {
            $bloque['url_ver'] = route('editar_recepcion_proveedor', [
                'id' => $id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ]);
            $bloque['url_pdf'] = route('recepcion_proveedor_com_pdf', [
                'id' => $id,
                'inline' => 1,
            ]);
        }

        return $bloque;
    }

    /**
     * @return array<string, mixed>
     */
    public static function bloqueOp(Pagoproveedor $pago): array
    {
        $id = (int) $pago->id;
        $puedeVer = can('listar-pagoproveedor', false) || can('editar-pagoproveedor', false);
        $etiqueta = $pago->etiquetaComprobante();

        $bloque = [
            'id' => $id,
            'numero' => $etiqueta,
            'etiqueta' => $etiqueta,
        ];

        if ($puedeVer) {
            $bloque['url_ver'] = route('editar_pagoproveedor', [
                'id' => $id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ]);
            $bloque['url_pdf'] = route('imprimir_pagoproveedor', ['id' => $id]);
        }

        return $bloque;
    }

    /**
     * @param  Collection<int, Recepcion_Proveedor>|iterable<int, Recepcion_Proveedor>  $coms
     * @return list<array<string, mixed>>
     */
    public static function bloquesComDesdeColeccion($coms): array
    {
        $out = [];
        foreach ($coms as $rec) {
            if ($rec instanceof Recepcion_Proveedor) {
                $out[] = self::bloqueCom($rec);
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function bloquesOpParaComprobante(int $comprobanteId): array
    {
        $opIds = self::pagoproveedorIdsPorComprobante($comprobanteId);
        if ($opIds === []) {
            return [];
        }

        $pagos = Pagoproveedor::query()
            ->whereIn('id', $opIds)
            ->orderByDesc('id')
            ->get();

        $out = [];
        foreach ($pagos as $pago) {
            $out[] = self::bloqueOp($pago);
        }

        return $out;
    }

    /**
     * @return list<int>
     */
    public static function pagoproveedorIdsPorComprobante(int $comprobanteId): array
    {
        if ($comprobanteId <= 0) {
            return [];
        }

        $ids = DB::table('proveedor_cuentacorriente')
            ->where('comprobante_proveedor_id', $comprobanteId)
            ->whereNotNull('pagoproveedor_id')
            ->where('pagoproveedor_id', '>', 0)
            ->pluck('pagoproveedor_id')
            ->all();

        $ccIds = ComprobanteProveedorPagoSupport::idsCuentacorrienteDeuda($comprobanteId);
        if ($ccIds !== []) {
            $ids = array_merge(
                $ids,
                DB::table('pagoproveedor_comprobante')
                    ->whereIn('proveedor_cuentacorriente_id', $ccIds)
                    ->pluck('pagoproveedor_id')
                    ->all(),
                DB::table('proveedor_cuentacorriente_aplicacion')
                    ->whereIn('proveedor_cuentacorriente_id', $ccIds)
                    ->whereNotNull('pagoproveedor_id')
                    ->where('pagoproveedor_id', '>', 0)
                    ->pluck('pagoproveedor_id')
                    ->all()
            );
        }

        $ids = array_merge(
            $ids,
            DB::table('proveedor_cuentacorriente_aplicacion')
                ->where('comprobante_proveedor_aplicado_id', $comprobanteId)
                ->whereNotNull('pagoproveedor_id')
                ->where('pagoproveedor_id', '>', 0)
                ->pluck('pagoproveedor_id')
                ->all()
        );

        return collect($ids)
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
