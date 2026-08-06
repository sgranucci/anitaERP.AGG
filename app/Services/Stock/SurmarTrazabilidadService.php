<?php

namespace App\Services\Stock;

use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Stock\RecepcionProveedorArticuloSurmar;
use App\Models\Stock\Stock_Etiqueta;
use App\Support\Stock\SurmarSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trazabilidad Surmar: por ID de etiqueta o artículo+lote.
 * Arma historial hacia atrás (hasta COM) y hacia adelante (consumos / hijas).
 */
class SurmarTrazabilidadService
{
    /**
     * @return Collection<int, Stock_Etiqueta>
     */
    public function buscarEtiquetas(?int $etiquetaId, ?int $articuloId, ?string $lote): Collection
    {
        $q = Stock_Etiqueta::query()
            ->with(['articulos', 'depositos', 'unidadesmedida'])
            ->where('empresa_id', SurmarSupport::EMPRESA_ID)
            ->orderByDesc('id');

        if ($etiquetaId && $etiquetaId > 0) {
            $q->whereKey($etiquetaId);
        } else {
            if ($articuloId && $articuloId > 0) {
                $q->where('articulo_id', $articuloId);
            }
            $lote = trim((string) $lote);
            if ($lote !== '') {
                $q->where(function ($w) use ($lote) {
                    $w->where('lote_proveedor', 'like', '%'.$lote.'%');
                });
            }
            if ((! $articuloId || $articuloId <= 0) && $lote === '') {
                return collect();
            }
        }

        return $q->limit(200)->get();
    }

    /**
     * Historial de una etiqueta: nacimiento, vínculos, consumos, hijas, cadena hasta COM.
     *
     * @return array{
     *   etiqueta: Stock_Etiqueta,
     *   eventos: list<array<string, mixed>>,
     *   cadena_origen: list<array<string, mixed>>,
     *   consumos_de_esta: list<array<string, mixed>>,
     *   etiquetas_hijas: list<array<string, mixed>>,
     *   recepcion: ?array<string, mixed>
     * }
     */
    public function historialEtiqueta(int $etiquetaId): array
    {
        $etiqueta = Stock_Etiqueta::query()
            ->with(['articulos', 'depositos', 'unidadesmedida', 'usuarios'])
            ->where('empresa_id', SurmarSupport::EMPRESA_ID)
            ->whereKey($etiquetaId)
            ->firstOrFail();

        $eventos = [];
        $recepcion = $this->datoRecepcionOrigen($etiqueta);

        $eventos[] = [
            'tipo' => 'NACIMIENTO',
            'fecha' => optional($etiqueta->fecha_emision)->format('d/m/Y') ?: optional($etiqueta->created_at)->format('d/m/Y'),
            'hora' => $etiqueta->hora_emision ?: optional($etiqueta->created_at)->format('H:i'),
            'titulo' => 'Alta etiqueta #'.$etiqueta->id,
            'detalle' => sprintf(
                'Origen %s · Lote %s · Neto %.2f · Estado %s',
                $etiqueta->origen_tipo,
                $etiqueta->lote_proveedor ?: '—',
                (float) $etiqueta->peso_neto,
                $etiqueta->estado
            ),
            'ref' => $this->refOrigen($etiqueta),
        ];

        if ($recepcion) {
            $eventos[] = [
                'tipo' => 'COM',
                'fecha' => $recepcion['fecha'] ?? null,
                'hora' => $recepcion['hora_piqueo'] ?? null,
                'titulo' => 'Recepción Surmar Nº '.$recepcion['numerorecepcion'],
                'detalle' => 'Proveedor: '.($recepcion['proveedor'] ?? '—').' · Depósito: '.($recepcion['deposito'] ?? '—'),
                'ref' => [
                    'url' => route('cargar_recepcion_proveedor_surmar', $recepcion['id']),
                    'label' => 'Abrir recepción',
                ],
            ];
        }

        foreach ($this->movimientosDeEtiqueta($etiquetaId) as $mov) {
            $eventos[] = $mov;
        }

        $certsSenasa = $this->certificadosSenasaDeEtiqueta($etiquetaId);
        foreach ($certsSenasa as $cs) {
            $eventos[] = [
                'tipo' => 'CERT_SENASA',
                'fecha' => $cs['fecha'] ?? null,
                'hora' => $cs['hora_piqueo'] ?? null,
                'titulo' => 'Certificado SENASA '.$cs['etiqueta'],
                'detalle' => sprintf(
                    'Estado %s · Remito AFIP %s · Neto asociado %.2f',
                    $cs['estado'] ?? '—',
                    $cs['cod_remito'] ?: '—',
                    (float) ($cs['peso_neto'] ?? 0)
                ),
                'ref' => [
                    'url' => route('cargar_certificado_senasa_surmar', $cs['id']),
                    'label' => 'Abrir certificado',
                ],
            ];
        }

        $consumosDeEsta = $this->consumosDondeSaleEsta($etiquetaId);
        foreach ($consumosDeEsta as $c) {
            $eventos[] = [
                'tipo' => 'CONSUMO',
                'fecha' => $c['fecha'] ?? null,
                'hora' => $c['hora'] ?? null,
                'titulo' => 'Consumida en movimiento #'.($c['movimientostock_id'] ?? '—'),
                'detalle' => sprintf(
                    'Producto línea AM #%s · Neto consumido %.2f · Lote %s',
                    $c['articulo_movimiento_id'] ?? '—',
                    (float) ($c['peso_neto'] ?? 0),
                    $c['lote_proveedor'] ?? '—'
                ),
                'ref' => null,
            ];
        }

        $hijas = $this->etiquetasHijas($etiquetaId);
        foreach ($hijas as $h) {
            $eventos[] = [
                'tipo' => 'HIJA',
                'fecha' => $h['fecha_emision'] ?? null,
                'hora' => $h['hora_emision'] ?? null,
                'titulo' => 'Generó etiqueta hija #'.$h['id'],
                'detalle' => ($h['sku'] ?? '').' · '.($h['descripcion'] ?? '').' · Lote '.($h['lote_proveedor'] ?? '—'),
                'ref' => [
                    'url' => route('trazabilidad_surmar', ['etiqueta_id' => $h['id'], 'consultar' => 1]),
                    'label' => 'Ver hija #'.$h['id'],
                ],
            ];
        }

        usort($eventos, static function ($a, $b) {
            $ka = ($a['fecha'] ?? '').' '.($a['hora'] ?? '');
            $kb = ($b['fecha'] ?? '').' '.($b['hora'] ?? '');

            return strcmp($ka, $kb);
        });

        return [
            'etiqueta' => $etiqueta,
            'eventos' => $eventos,
            'cadena_origen' => $this->cadenaHastaCom($etiqueta),
            'consumos_de_esta' => $consumosDeEsta,
            'etiquetas_hijas' => $hijas,
            'recepcion' => $recepcion,
            'certificados_senasa' => $certsSenasa,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function certificadosSenasaDeEtiqueta(int $etiquetaId): array
    {
        if (! Schema::hasTable('certificado_senasa_surmar_etiqueta')) {
            return [];
        }

        $rows = DB::table('certificado_senasa_surmar_etiqueta as cse')
            ->join('certificado_senasa_surmar as cs', 'cs.id', '=', 'cse.certificado_senasa_surmar_id')
            ->where('cse.etiqueta_id', $etiquetaId)
            ->where('cse.empresa_id', SurmarSupport::EMPRESA_ID)
            ->orderByDesc('cs.id')
            ->get([
                'cs.id',
                'cs.numero',
                'cs.serie',
                'cs.fecha',
                'cs.estado',
                'cs.cod_remito',
                'cse.peso_neto',
                'cse.hora_piqueo',
            ]);

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) $r->id,
                'etiqueta' => ($r->serie ?? 'A').'-'.str_pad((string) $r->numero, 6, '0', STR_PAD_LEFT),
                'fecha' => $r->fecha ? date('d/m/Y', strtotime((string) $r->fecha)) : null,
                'hora_piqueo' => $r->hora_piqueo,
                'estado' => $r->estado,
                'cod_remito' => $r->cod_remito,
                'peso_neto' => (float) $r->peso_neto,
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function cadenaHastaCom(Stock_Etiqueta $etiqueta): array
    {
        $cadena = [];
        $visitados = [];
        $actual = $etiqueta;
        $guard = 0;

        while ($actual && $guard++ < 50) {
            if (isset($visitados[$actual->id])) {
                break;
            }
            $visitados[$actual->id] = true;

            $cadena[] = [
                'etiqueta_id' => $actual->id,
                'origen_tipo' => $actual->origen_tipo,
                'lote_proveedor' => $actual->lote_proveedor,
                'peso_neto' => (float) $actual->peso_neto,
                'estado' => $actual->estado,
                'sku' => $actual->articulos->sku ?? '',
                'descripcion' => $actual->descripcion_snapshot ?: ($actual->articulos->descripcion ?? ''),
            ];

            if ($actual->origen_tipo === SurmarSupport::ORIGEN_COM) {
                break;
            }

            // Padres por consumo: etiquetas que alimentaron el AM de esta etiqueta
            $padrePorConsumo = $this->etiquetaPadrePorConsumo($actual);
            if ($padrePorConsumo) {
                $actual = $padrePorConsumo;
                continue;
            }

            if ($actual->etiqueta_origen_id) {
                $actual = Stock_Etiqueta::query()
                    ->with('articulos')
                    ->whereKey($actual->etiqueta_origen_id)
                    ->first();
                continue;
            }

            break;
        }

        return $cadena;
    }

    /** @return ?array<string, mixed> */
    private function datoRecepcionOrigen(Stock_Etiqueta $etiqueta): ?array
    {
        if ($etiqueta->origen_tipo !== SurmarSupport::ORIGEN_COM || ! $etiqueta->origen_id) {
            // Intentar por línea
            if ($etiqueta->origen_linea_id) {
                $linea = RecepcionProveedorArticuloSurmar::query()
                    ->with(['recepcion_proveedores.proveedores', 'recepcion_proveedores.depositos'])
                    ->whereKey($etiqueta->origen_linea_id)
                    ->first();
                if ($linea && $linea->recepcion_proveedores) {
                    $r = $linea->recepcion_proveedores;

                    return $this->mapRecepcion($r, $linea);
                }
            }

            return null;
        }

        $r = Recepcion_Proveedor::query()
            ->with(['proveedores', 'depositos'])
            ->whereKey($etiqueta->origen_id)
            ->where('empresa_id', SurmarSupport::EMPRESA_ID)
            ->first();

        if (! $r) {
            return null;
        }

        $linea = null;
        if ($etiqueta->origen_linea_id) {
            $linea = RecepcionProveedorArticuloSurmar::query()->whereKey($etiqueta->origen_linea_id)->first();
        }

        return $this->mapRecepcion($r, $linea);
    }

    /** @return array<string, mixed> */
    private function mapRecepcion(Recepcion_Proveedor $r, ?RecepcionProveedorArticuloSurmar $linea): array
    {
        return [
            'id' => $r->id,
            'numerorecepcion' => $r->numerorecepcion,
            'fecha' => optional($r->fecha)->format('d/m/Y'),
            'estado' => $r->estado,
            'proveedor' => $r->proveedores->nombre ?? '',
            'deposito' => $r->depositos->nombre ?? '',
            'hora_piqueo' => $linea->hora_piqueo ?? null,
            'linea_id' => $linea->id ?? null,
        ];
    }

    /** @return array{url:?string,label:string} */
    private function refOrigen(Stock_Etiqueta $etiqueta): array
    {
        if ($etiqueta->origen_tipo === SurmarSupport::ORIGEN_COM && $etiqueta->origen_id) {
            return [
                'url' => route('cargar_recepcion_proveedor_surmar', $etiqueta->origen_id),
                'label' => 'Recepción #'.$etiqueta->origen_id,
            ];
        }

        return ['url' => null, 'label' => $etiqueta->origen_tipo];
    }

    /** @return list<array<string, mixed>> */
    private function movimientosDeEtiqueta(int $etiquetaId): array
    {
        if (! Schema::hasTable('stock_etiqueta_movimiento')) {
            return [];
        }

        $rows = DB::table('stock_etiqueta_movimiento as sem')
            ->leftJoin('articulo_movimiento as am', 'am.id', '=', 'sem.articulo_movimiento_id')
            ->leftJoin('movimientostock as ms', 'ms.id', '=', 'am.movimientostock_id')
            ->leftJoin('depmae as d1', 'd1.id', '=', 'sem.deposito_origen_id')
            ->leftJoin('depmae as d2', 'd2.id', '=', 'sem.deposito_destino_id')
            ->where('sem.etiqueta_id', $etiquetaId)
            ->orderBy('sem.id')
            ->get([
                'sem.rol',
                'sem.created_at',
                'am.fecha as am_fecha',
                'ms.id as movimientostock_id',
                'ms.leyenda',
                'd1.nombre as dep_origen',
                'd2.nombre as dep_destino',
            ]);

        $out = [];
        foreach ($rows as $row) {
            $fecha = $row->am_fecha
                ? date('d/m/Y', strtotime((string) $row->am_fecha))
                : ($row->created_at ? date('d/m/Y', strtotime((string) $row->created_at)) : null);
            $hora = $row->created_at ? date('H:i', strtotime((string) $row->created_at)) : null;
            $out[] = [
                'tipo' => 'MOV_'.$row->rol,
                'fecha' => $fecha,
                'hora' => $hora,
                'titulo' => 'Movimiento '.$row->rol.' · MS #'.($row->movimientostock_id ?? '—'),
                'detalle' => trim(($row->leyenda ?? '').' · '.($row->dep_origen ?? '').' → '.($row->dep_destino ?? ''), ' ·'),
                'ref' => null,
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function consumosDondeSaleEsta(int $etiquetaId): array
    {
        if (! Schema::hasTable('stock_etiqueta_consumo')) {
            return [];
        }

        $rows = DB::table('stock_etiqueta_consumo as sec')
            ->leftJoin('articulo_movimiento as am', 'am.id', '=', 'sec.articulo_movimiento_id')
            ->where('sec.etiqueta_id', $etiquetaId)
            ->orderBy('sec.id')
            ->get([
                'sec.*',
                'am.fecha as am_fecha',
                'am.created_at as am_created',
            ]);

        $out = [];
        foreach ($rows as $row) {
            $ts = $row->am_created ?: $row->created_at;
            $out[] = [
                'movimientostock_id' => $row->movimientostock_id,
                'articulo_movimiento_id' => $row->articulo_movimiento_id,
                'peso_neto' => $row->peso_neto,
                'lote_proveedor' => $row->lote_proveedor,
                'fecha' => $row->am_fecha ? date('d/m/Y', strtotime((string) $row->am_fecha)) : ($ts ? date('d/m/Y', strtotime((string) $ts)) : null),
                'hora' => $ts ? date('H:i', strtotime((string) $ts)) : null,
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function etiquetasHijas(int $etiquetaId): array
    {
        return Stock_Etiqueta::query()
            ->with('articulos')
            ->where('empresa_id', SurmarSupport::EMPRESA_ID)
            ->where('etiqueta_origen_id', $etiquetaId)
            ->orderBy('id')
            ->get()
            ->map(fn (Stock_Etiqueta $e) => [
                'id' => $e->id,
                'lote_proveedor' => $e->lote_proveedor,
                'sku' => $e->articulos->sku ?? '',
                'descripcion' => $e->descripcion_snapshot ?: ($e->articulos->descripcion ?? ''),
                'fecha_emision' => optional($e->fecha_emision)->format('d/m/Y'),
                'hora_emision' => $e->hora_emision,
            ])
            ->all();
    }

    private function etiquetaPadrePorConsumo(Stock_Etiqueta $etiqueta): ?Stock_Etiqueta
    {
        if (! Schema::hasTable('stock_etiqueta_consumo') || ! $etiqueta->articulo_movimiento_id) {
            return null;
        }

        $padreId = DB::table('stock_etiqueta_consumo')
            ->where('articulo_movimiento_id', $etiqueta->articulo_movimiento_id)
            ->orderBy('id')
            ->value('etiqueta_id');

        if (! $padreId) {
            return null;
        }

        return Stock_Etiqueta::query()->with('articulos')->whereKey($padreId)->first();
    }
}
