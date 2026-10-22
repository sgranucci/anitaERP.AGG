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
 * Abrir cualquier documento resuelve su orden de compra y lista todas las
 * operaciones que tienen ese id (recepciones, devoluciones, facturas y sus OP).
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
        $etiqueta = 'COM '.((string) ($rec->numerorecepcion ?? $rec->id));
        if ($oc instanceof Ordencompra) {
            $req = $oc->requisiciones instanceof Requisicion ? $oc->requisiciones : null;

            return self::respuesta('recepcion', (int) $rec->id, $etiqueta, self::filasDesdeOc($oc, $req));
        }

        return self::respuesta('recepcion', (int) $rec->id, $etiqueta, [[
            'requisicion' => null,
            'ordencompra' => null,
            'coms' => [self::bloqueCom($rec)],
            'factura' => null,
            'ops' => [],
        ]]);
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
        if (! $oc instanceof Ordencompra) {
            $ocIdDesdeCom = (int) $comp->recepcion_proveedores
                ->map(static fn (Recepcion_Proveedor $rec) => (int) ($rec->ordencompra_id ?? 0))
                ->first(static fn (int $id) => $id > 0);
            if ($ocIdDesdeCom > 0) {
                $oc = Ordencompra::query()->with('requisiciones')->find($ocIdDesdeCom);
            }
        }

        $etiqueta = sprintf(
            '%s %s-%04d-%s',
            $comp->tipotransaccion_compras?->abreviatura ?? 'FAC',
            $comp->letra,
            (int) $comp->sucursal,
            $comp->numerocomprobante
        );

        if ($oc instanceof Ordencompra) {
            $req = $oc->requisiciones instanceof Requisicion ? $oc->requisiciones : null;

            return self::respuesta('factura', (int) $comp->id, $etiqueta, self::filasDesdeOc($oc, $req));
        }

        return self::respuesta('factura', (int) $comp->id, $etiqueta, [[
            'requisicion' => null,
            'ordencompra' => null,
            'coms' => self::bloquesComDesdeColeccion($comp->recepcion_proveedores),
            'factura' => self::bloqueFactura($comp),
            'ops' => self::bloquesOpParaComprobante((int) $comp->id),
        ]]);
    }

    /**
     * Recepciones, devoluciones, facturas y órdenes de pago de esta OC.
     *
     * @return list<array<string, mixed>>
     */
    private static function filasDesdeOc(Ordencompra $oc, ?Requisicion $req): array
    {
        $bloqueReq = $req ? self::bloqueRequisicion($req) : null;
        $bloqueOc = self::bloqueOrdencompra($oc);
        $coms = self::bloquesComDeOrdencompra((int) $oc->id);

        $facturas = Comprobante_Proveedor::query()
            ->where('ordencompra_id', (int) $oc->id)
            ->with(['tipotransaccion_compras:id,abreviatura'])
            ->orderByDesc('id')
            ->get();

        $bloquesFactura = [];
        $ops = [];
        $opsYa = [];
        foreach ($facturas as $fact) {
            $bloquesFactura[] = self::bloqueFactura($fact);
            foreach (self::bloquesOpParaComprobante((int) $fact->id) as $op) {
                $opId = (int) ($op['id'] ?? 0);
                if ($opId > 0 && isset($opsYa[$opId])) {
                    continue;
                }
                if ($opId > 0) {
                    $opsYa[$opId] = true;
                }
                $ops[] = $op;
            }
        }

        return [[
            'requisicion' => $bloqueReq,
            'ordencompra' => $bloqueOc,
            'coms' => $coms,
            'facturas' => $bloquesFactura,
            'factura' => $bloquesFactura[0] ?? null,
            'ops' => $ops,
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function bloquesComDeOrdencompra(int $ordencompraId): array
    {
        if ($ordencompraId <= 0) {
            return [];
        }

        return self::bloquesComDesdeColeccion(
            Recepcion_Proveedor::query()
                ->where('ordencompra_id', $ordencompraId)
                ->get()
        );
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

        $numero = (string) ($rec->numerorecepcion ?? $id);
        $esDevolucion = $rec->tipo === Recepcion_Proveedor::TIPO_DEVOLUCION;
        $bloque = [
            'id' => $id,
            'numero' => $numero,
            'etiqueta' => $esDevolucion ? 'Devolución COM '.$numero : 'COM '.$numero,
        ];
        if ($esDevolucion) {
            $bloque['usar_etiqueta'] = true;
        }

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
        $lista = collect();
        foreach ($coms as $rec) {
            if ($rec instanceof Recepcion_Proveedor) {
                $lista->push($rec);
            }
        }

        $lista = self::ordenarRecepcionesYDevoluciones($lista);

        $out = [];
        foreach ($lista as $rec) {
            $out[] = self::bloqueCom($rec);
        }

        return $out;
    }

    /**
     * @param  Collection<int, Recepcion_Proveedor>  $lista
     * @return Collection<int, Recepcion_Proveedor>
     */
    private static function ordenarRecepcionesYDevoluciones(Collection $lista): Collection
    {
        return $lista
            ->sortBy(static function (Recepcion_Proveedor $rec): string {
                $esDevolucion = $rec->tipo === Recepcion_Proveedor::TIPO_DEVOLUCION;
                $ancla = $esDevolucion
                    ? (int) ($rec->recepcion_referencia_id ?? $rec->id)
                    : (int) $rec->id;

                return sprintf('%010d-%d-%010d', $ancla, $esDevolucion ? 1 : 0, (int) $rec->id);
            })
            ->values();
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
                    ->all(),
                // Import Anita: la aplicación no guarda pagoproveedor_id; la OP
                // está en la CC crédito apuntada por proveedor_cuentacorriente_aplicado_id.
                DB::table('proveedor_cuentacorriente_aplicacion as app')
                    ->join('proveedor_cuentacorriente as credito', 'credito.id', '=', 'app.proveedor_cuentacorriente_aplicado_id')
                    ->whereIn('app.proveedor_cuentacorriente_id', $ccIds)
                    ->where('credito.pagoproveedor_id', '>', 0)
                    ->pluck('credito.pagoproveedor_id')
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
                ->all(),
            DB::table('proveedor_cuentacorriente_aplicacion as app')
                ->join('proveedor_cuentacorriente as credito', 'credito.id', '=', 'app.proveedor_cuentacorriente_id')
                ->where('app.comprobante_proveedor_aplicado_id', $comprobanteId)
                ->where('credito.pagoproveedor_id', '>', 0)
                ->pluck('credito.pagoproveedor_id')
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
