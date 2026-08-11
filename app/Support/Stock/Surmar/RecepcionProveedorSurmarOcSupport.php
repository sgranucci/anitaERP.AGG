<?php

namespace App\Support\Stock\Surmar;

use App\Models\Compras\Ordencompra;
use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Compras\OrdencompraEstados;
use App\Support\Compras\OrdencompraLineaEstados;
use App\Support\Stock\RecepcionProveedorOcPendienteSupport;
use App\Support\Stock\RecepcionProveedorVisibilidadSupport;
use App\Support\Stock\SurmarSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * OC pendientes Surmar (empresa_id=3 El Bierzo). Solo lee ERP; no sync Anita AGG.
 */
final class RecepcionProveedorSurmarOcSupport
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function buscarPendientes(?string $consulta, int $limite = 80): array
    {
        $limite = max(1, min(200, $limite));
        $consulta = trim((string) $consulta);
        $empresaId = SurmarSupport::EMPRESA_ID;

        $recibidoSub = DB::table('recepcion_proveedor_articulo as rpa')
            ->join('recepcion_proveedor as rp', 'rp.id', '=', 'rpa.recepcion_proveedor_id')
            ->where('rp.estado', Recepcion_Proveedor::ESTADO_CONFIRMADA)
            ->whereIn('rp.tipo', [
                Recepcion_Proveedor::TIPO_RECEPCION,
                Recepcion_Proveedor::TIPO_DEVOLUCION,
            ])
            ->whereNotNull('rpa.ordencompra_articulo_id')
            ->groupBy('rpa.ordencompra_articulo_id')
            ->selectRaw(
                'rpa.ordencompra_articulo_id as linea_id, SUM('
                .'CASE rp.tipo '
                .'WHEN ? THEN rpa.cantidad + COALESCE(rpa.cantidad_rechazada, 0) '
                .'WHEN ? THEN -(rpa.cantidad + COALESCE(rpa.cantidad_rechazada, 0)) '
                .'ELSE 0 END) as cantidad_recibida',
                [
                    Recepcion_Proveedor::TIPO_RECEPCION,
                    Recepcion_Proveedor::TIPO_DEVOLUCION,
                ]
            );

        $query = DB::table('ordencompra as oc')
            ->join('proveedor as p', 'p.id', '=', 'oc.proveedor_id')
            ->join('empresa as e', 'e.id', '=', 'oc.empresa_id')
            ->join('ordencompra_articulo as oa', 'oa.ordencompra_id', '=', 'oc.id')
            ->where('oc.empresa_id', $empresaId)
            ->where(function ($q) {
                $q->whereNull('oa.estado_linea_oc')
                    ->orWhere('oa.estado_linea_oc', '!=', OrdencompraLineaEstados::CERRADA);
            })
            ->leftJoinSub($recibidoSub, 'rec', function ($join) {
                $join->on('rec.linea_id', '=', 'oa.id');
            })
            // El Bierzo/Surmar: incluye PENDIENTE (OC recién cargadas).
            // AGG sigue en RecepcionProveedorOcPendienteSupport → solo APROBADA/CUMPLIDA.
            ->whereIn('oc.estadoordencompra', [
                OrdencompraEstados::PENDIENTE,
                OrdencompraEstados::APROBADA,
                OrdencompraEstados::CUMPLIDA,
            ])
            ->when($consulta !== '', function ($q) use ($consulta) {
                $like = '%'.$consulta.'%';
                $q->where(function ($sub) use ($like, $consulta) {
                    $sub->where('p.nombre', 'like', $like)
                        ->orWhere('oc.detalle', 'like', $like)
                        ->orWhere('oc.comentario', 'like', $like);
                    if (ctype_digit($consulta)) {
                        $sub->orWhere('oc.numeroordencompra', (int) $consulta)
                            ->orWhere('oc.id', (int) $consulta);
                    }
                });
            })
            ->groupBy(
                'oc.id', 'oc.numeroordencompra', 'oc.fecha', 'oc.proveedor_id', 'oc.empresa_id',
                'oc.estadoordencompra', 'p.nombre', 'e.nombre'
            )
            ->havingRaw('SUM(oa.cantidad) > COALESCE(SUM(rec.cantidad_recibida), 0) + 0.000001')
            ->selectRaw('oc.id, oc.numeroordencompra, oc.fecha, oc.proveedor_id, oc.empresa_id, oc.estadoordencompra')
            ->selectRaw('p.nombre as proveedor_nombre, e.nombre as empresa_nombre')
            ->selectRaw('SUM(oa.cantidad) as cantidad_pedida')
            ->selectRaw('COALESCE(SUM(rec.cantidad_recibida), 0) as cantidad_recibida')
            ->orderByDesc('oc.numeroordencompra')
            ->orderByDesc('oc.fecha')
            ->limit($limite);

        RecepcionProveedorVisibilidadSupport::aplicarFiltroOrdencompra($query, 'oc');

        return self::mapearFilasBusqueda($query->get());
    }

    /**
     * @return array{cabecera: Ordencompra, lineas: list<array<string, mixed>>}
     */
    public static function resolver(int $ordencompraId, bool $validarNuevaRecepcion = true): array
    {
        $oc = self::cargarOc($ordencompraId);
        if ($validarNuevaRecepcion) {
            RecepcionProveedorOcPendienteSupport::assertPermiteNuevaRecepcion($oc);
        }

        return [
            'cabecera' => $oc,
            'lineas' => self::armarLineasPendientes($oc),
        ];
    }

    public static function resolverPorNumero(int $numeroOc, bool $validarNuevaRecepcion = true): array
    {
        $oc = Ordencompra::query()
            ->where('empresa_id', SurmarSupport::EMPRESA_ID)
            ->where('numeroordencompra', $numeroOc)
            ->first();

        if (! $oc) {
            throw new \RuntimeException("Orden de compra {$numeroOc} inexistente en Surmar (ERP).");
        }

        return self::resolver((int) $oc->id, $validarNuevaRecepcion);
    }

    public static function cargarOc(int $ordencompraId): Ordencompra
    {
        if ($ordencompraId <= 0) {
            throw new \RuntimeException('Orden de compra inválida.');
        }

        if (! RecepcionProveedorVisibilidadSupport::ordencompraAccesible($ordencompraId)) {
            throw new \RuntimeException('Orden de compra no encontrada o sin acceso.');
        }

        $oc = Ordencompra::query()
            ->with([
                'empresas',
                'proveedores',
                'ordencompra_articulos' => static fn ($q) => $q->orderBy('penvp_orden')->orderBy('id'),
                'ordencompra_articulos.articulos.unidadesdemedidas',
                'ordencompra_articulos.monedas',
                'ordencompra_articulos.entregas',
            ])
            ->whereKey($ordencompraId)
            ->where('empresa_id', SurmarSupport::EMPRESA_ID)
            ->first();

        if (! $oc) {
            throw new \RuntimeException('Orden de compra no pertenece a Surmar o no existe.');
        }

        return $oc;
    }

    /**
     * Líneas OC con saldo pendiente (para workbench / precarga).
     *
     * @return list<array<string, mixed>>
     */
    public static function armarLineasPendientes(Ordencompra $oc): array
    {
        $oc->loadMissing([
            'ordencompra_articulos.articulos.unidadesdemedidas',
            'ordencompra_articulos.monedas',
            'ordencompra_articulos.entregas',
        ]);
        $recibidos = RecepcionProveedorOcPendienteSupport::cantidadesRecibidasPorLineaOc((int) $oc->id);
        $lineas = [];
        $orden = 1;

        foreach ($oc->ordencompra_articulos->sortBy([['penvp_orden', 'asc'], ['id', 'asc']])->values() as $ocArt) {
            if ((string) ($ocArt->estado_linea_oc ?? OrdencompraLineaEstados::ACTIVA)
                === OrdencompraLineaEstados::CERRADA) {
                continue;
            }

            $pedida = (float) ($ocArt->cantidad ?? 0);
            $recibido = (float) ($recibidos[$ocArt->id] ?? 0);
            $pendiente = RecepcionProveedorOcPendienteSupport::saldoPendienteLineaEstricto($pedida, $recibido);
            if ($pendiente <= 0.000001) {
                continue;
            }

            $art = $ocArt->articulos;
            $pesoUnit = (float) ($ocArt->peso_unitario ?? 0);
            $pesoTotal = (float) ($ocArt->peso_total ?? 0);
            if ($pesoTotal <= 0 && $pesoUnit > 0 && $pedida > 0) {
                $pesoTotal = round($pesoUnit * $pedida, 6);
            }
            $monedaAbr = trim((string) ($ocArt->monedas->abreviatura ?? ''));
            $precio = (float) ($ocArt->precio ?? 0);
            $entregas = $ocArt->entregas->map(static function ($e) {
                return [
                    'fecha' => $e->fecha ? $e->fecha->format('Y-m-d') : null,
                    'cantidad' => (float) $e->cantidad,
                ];
            })->filter(static function ($e) {
                return ! empty($e['fecha']) && (float) $e['cantidad'] > 0;
            })->values()->all();

            $lineas[] = [
                'orden' => $orden++,
                'ordencompra_articulo_id' => (int) $ocArt->id,
                'penvp_orden' => (int) ($ocArt->penvp_orden ?? 0) ?: null,
                'penvp_nro_interno' => (int) ($ocArt->penvp_nro_interno ?? 0) ?: null,
                'articulo_id' => (int) $ocArt->articulo_id,
                'sku' => (string) ($art->sku ?? ''),
                'descripcion' => (string) ($art->descripcion ?? $ocArt->detalle ?? ''),
                'cantidad_oc' => $pedida,
                'cantidad_recibida' => $recibido,
                'cantidad_pendiente' => $pendiente,
                'peso_unitario' => $pesoUnit,
                'peso_total' => $pesoTotal,
                'precio' => $precio,
                'precio_ordencompra' => $precio,
                'moneda_id' => (int) ($ocArt->moneda_id ?? 0) ?: null,
                'moneda_abreviatura' => $monedaAbr,
                'unidadmedida_id' => (int) ($art->unidadmedida_id ?? 0) ?: null,
                'detalle' => (string) ($ocArt->detalle ?? ''),
                'vencimientoendia' => (int) ($art->vencimientoendia ?? 0),
                'entregas_semanales' => $entregas,
                'tiene_entregas_semanales' => $entregas !== [],
            ];
        }

        return $lineas;
    }

    /**
     * @param  Collection<int, object>  $filas
     * @return list<array<string, mixed>>
     */
    private static function mapearFilasBusqueda(Collection $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $pedida = (float) ($fila->cantidad_pedida ?? 0);
            $recibida = (float) ($fila->cantidad_recibida ?? 0);
            $pendiente = max(0, $pedida - $recibida);

            $out[] = [
                'id' => (int) $fila->id,
                'numeroordencompra' => (int) $fila->numeroordencompra,
                'fecha' => $fila->fecha ? (string) $fila->fecha : null,
                'proveedor_id' => (int) $fila->proveedor_id,
                'proveedor_nombre' => (string) ($fila->proveedor_nombre ?? ''),
                'empresa_id' => (int) $fila->empresa_id,
                'empresa_nombre' => (string) ($fila->empresa_nombre ?? ''),
                'estadoordencompra' => (string) ($fila->estadoordencompra ?? ''),
                'estado_com' => RecepcionProveedorOcPendienteSupport::etiquetaEstadoCom($recibida, $pedida),
                'cantidad_pedida' => $pedida,
                'cantidad_recibida' => $recibida,
                'cantidad_pendiente' => $pendiente,
                'url_consulta' => route('editar_ordencompra', [
                    'id' => (int) $fila->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]),
            ];
        }

        return $out;
    }
}
