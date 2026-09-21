<?php

namespace App\Services\Ventas\FacturacionLocal;

use App\Models\Stock\Articulo;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\RemitoInterno;
use App\Models\Ventas\RemitoInternoLinea;
use App\Services\Stock\MovimientoStockService;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Ventas\FacturacionLocal\RemitoInternoEstadosSupport;
use App\Support\Ventas\FacturacionLocal\RemitoInternoListadoFiltros;
use App\Support\Ventas\FacturacionLocal\RemitoInternoNumeracionSupport;
use App\Support\Ventas\FacturacionLocal\RemitoInternoStockSupport;
use App\Support\Ventas\RemitoPdfAgrupacionFerliSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use InvalidArgumentException;

/**
 * Remitos internos de locales (Facturación Local Ferli).
 * Confirmar genera un único movimiento de stock (salida RINT).
 */
class RemitoInternoService
{
    public function __construct(
        private readonly MovimientoStockService $movimientoStockService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator|Collection
     */
    public function leeListado(array $filtros, bool $paginar = true)
    {
        $query = RemitoInterno::query()
            ->select('remito_interno.*')
            ->leftJoin('local_venta', 'local_venta.id', '=', 'remito_interno.local_venta_id')
            ->with(['localVenta:id,codigo,nombre', 'deposito:id,codigo,nombre'])
            ->orderByDesc('remito_interno.numero');

        RemitoInternoListadoFiltros::aplicar($query, $filtros);

        return $paginar ? $query->paginate(10) : $query->get();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lineas
     */
    public function crear(array $data, array $lineas): RemitoInterno
    {
        return DB::transaction(function () use ($data, $lineas) {
            $local = LocalVenta::query()
                ->with(['puntoventa', 'puntoventas'])
                ->findOrFail((int) $data['local_venta_id']);
            $depositoId = (int) ($local->deposito_id ?: 0);
            if ($depositoId <= 0) {
                throw new InvalidArgumentException('El local no tiene depósito asignado.');
            }

            $remito = RemitoInterno::query()->create([
                'numero' => RemitoInternoNumeracionSupport::reservarSiguiente($local, true),
                'fecha' => $data['fecha'],
                'local_venta_id' => (int) $local->id,
                'empresa_id' => (int) ($local->empresa_id ?: 0) ?: null,
                'deposito_id' => $depositoId,
                'usuario_id' => (int) (Auth::id() ?: 0),
                'estado' => RemitoInternoEstadosSupport::BORRADOR,
                'destinatario' => $data['destinatario'] ?? null,
                'leyenda' => $data['leyenda'] ?? null,
                'observacion' => $data['observacion'] ?? null,
            ]);

            $this->sincronizarLineas($remito, $lineas);

            return $remito->fresh(['lineas.articulo', 'lineas.combinacion', 'lineas.talle', 'localVenta', 'deposito']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lineas
     */
    public function actualizar(RemitoInterno $remito, array $data, array $lineas): RemitoInterno
    {
        if (! RemitoInternoEstadosSupport::esEditable($remito->estado)) {
            throw new InvalidArgumentException('Solo se puede editar un remito en borrador.');
        }

        return DB::transaction(function () use ($remito, $data, $lineas) {
            $local = LocalVenta::query()->findOrFail((int) $data['local_venta_id']);
            $depositoId = (int) ($local->deposito_id ?: 0);
            if ($depositoId <= 0) {
                throw new InvalidArgumentException('El local no tiene depósito asignado.');
            }

            $remito->update([
                'fecha' => $data['fecha'],
                'local_venta_id' => (int) $local->id,
                'empresa_id' => (int) ($local->empresa_id ?: 0) ?: null,
                'deposito_id' => $depositoId,
                'destinatario' => $data['destinatario'] ?? null,
                'leyenda' => $data['leyenda'] ?? null,
                'observacion' => $data['observacion'] ?? null,
            ]);

            $this->sincronizarLineas($remito, $lineas);

            return $remito->fresh(['lineas.articulo', 'lineas.combinacion', 'lineas.talle', 'localVenta', 'deposito']);
        });
    }

    public function confirmar(RemitoInterno $remito): RemitoInterno
    {
        if ($remito->estado !== RemitoInternoEstadosSupport::BORRADOR) {
            throw new InvalidArgumentException('Solo se puede confirmar un remito en borrador.');
        }

        $remito->load(['lineas', 'localVenta']);
        if ($remito->lineas->isEmpty()) {
            throw new InvalidArgumentException('El remito no tiene líneas.');
        }

        return DB::transaction(function () use ($remito) {
            $tipoId = $this->tipoStockId(RemitoInternoStockSupport::ABREVIATURA);
            $payload = $this->armarPayloadMovimiento(
                $remito,
                $tipoId,
                'R',
                'Remito interno Nº '.$remito->numero
            );

            $resultado = $this->movimientoStockService->guardaMovimientoStock($payload, 'create');
            $movId = (int) ($resultado['id'] ?? 0);
            if ($movId <= 0) {
                throw new InvalidArgumentException('No se pudo generar el movimiento de stock.');
            }

            $remito->update([
                'estado' => RemitoInternoEstadosSupport::CONFIRMADO,
                'movimientostock_id' => $movId,
            ]);

            return $remito->fresh(['lineas.articulo', 'lineas.combinacion', 'lineas.talle', 'localVenta', 'deposito', 'movimientoStock']);
        });
    }

    public function anular(RemitoInterno $remito): RemitoInterno
    {
        if ($remito->estado !== RemitoInternoEstadosSupport::CONFIRMADO) {
            throw new InvalidArgumentException('Solo se puede anular un remito confirmado.');
        }

        $remito->load(['lineas', 'localVenta']);

        return DB::transaction(function () use ($remito) {
            if ((int) ($remito->movimientostock_id ?: 0) > 0) {
                $tipoId = $this->tipoStockId(RemitoInternoStockSupport::ABREVIATURA_REVERSO);
                $payload = $this->armarPayloadMovimiento(
                    $remito,
                    $tipoId,
                    'S',
                    'Reverso anulación remito interno Nº '.$remito->numero
                );
                $this->movimientoStockService->guardaMovimientoStock($payload, 'create');
            }

            $remito->update([
                'estado' => RemitoInternoEstadosSupport::ANULADO,
            ]);

            return $remito->fresh(['lineas.articulo', 'lineas.combinacion', 'lineas.talle', 'localVenta', 'deposito']);
        });
    }

    /**
     * Genera PDF del remito y devuelve path absoluto del archivo.
     */
    public function generarPdfArchivo(RemitoInterno $remito): string
    {
        $remito->load([
            'lineas.articulo',
            'lineas.combinacion',
            'lineas.talle',
            'lineas.color',
            'localVenta.empresa',
            'localVenta.puntoventa',
            'localVenta.puntoventas',
            'deposito',
            'empresa',
        ]);

        $items = [];
        foreach ($remito->lineas as $linea) {
            $articulo = $linea->articulo;
            $comb = $linea->combinacion;
            $talle = $linea->talle;
            $color = $linea->color;
            $detalle = trim((string) ($linea->descripcion ?: ($articulo->descripcion ?? '')));
            $colorTxt = trim((string) ($comb->nombre ?? $color->nombre ?? ''));
            $medida = trim((string) ($talle->nombre ?? $talle->codigo ?? ''));

            $items[] = [
                'articulo_id' => (int) $linea->articulo_id,
                'combinacion_id' => (int) ($linea->combinacion_id ?: 0),
                'talle_id' => (int) ($linea->talle_id ?: 0),
                'sku' => (string) ($articulo->sku ?? ''),
                'detalle' => $detalle,
                'color' => $colorTxt,
                'medida' => $medida,
                'talle_nombre' => $medida,
                'cantidad' => (float) $linea->cantidad,
                'pieza' => (float) $linea->cantidad,
                'caja' => 0,
            ];
        }

        $itemsAgrupados = RemitoPdfAgrupacionFerliSupport::agruparItems($items);
        $totalPares = array_sum(array_map(static fn ($i) => (float) ($i['cantidad'] ?? 0), $itemsAgrupados));

        $html = View::make('ventas.facturacion_local.remito_interno.pdf', [
            'remito' => $remito,
            'items' => $itemsAgrupados,
            'totalPares' => $totalPares,
        ])->render();

        $path = storage_path('pdf/remitos_internos');
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
        $nombre = 'remito_interno_'.$remito->numero.'.pdf';
        $full = $path.'/'.$nombre;

        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('A4', 'portrait');
        $pdf->loadHTML($html)->save($full);

        return $full;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private function sincronizarLineas(RemitoInterno $remito, array $lineas): void
    {
        $idsConservar = [];
        $orden = 0;
        foreach ($lineas as $raw) {
            $articuloId = (int) ($raw['articulo_id'] ?? 0);
            $cantidad = (float) ($raw['cantidad'] ?? 0);
            if ($articuloId <= 0 || $cantidad <= 0) {
                continue;
            }

            $orden++;
            $payload = [
                'orden' => $orden,
                'articulo_id' => $articuloId,
                'combinacion_id' => (($c = (int) ($raw['combinacion_id'] ?? 0)) > 0) ? $c : null,
                'talle_id' => (($t = (int) ($raw['talle_id'] ?? 0)) > 0) ? $t : null,
                'color_id' => (($col = (int) ($raw['color_id'] ?? 0)) > 0) ? $col : null,
                'modulo_id' => (($m = (int) ($raw['modulo_id'] ?? 0)) > 0) ? $m : null,
                'cantidad' => $cantidad,
                'descripcion' => $this->resolverDescripcionLinea($raw, $articuloId),
            ];

            $lineaId = (int) ($raw['id'] ?? 0);
            if ($lineaId > 0) {
                $linea = RemitoInternoLinea::query()
                    ->where('remito_interno_id', $remito->id)
                    ->whereKey($lineaId)
                    ->first();
                if ($linea) {
                    $linea->update($payload);
                    $idsConservar[] = (int) $linea->id;
                    continue;
                }
            }

            $nueva = $remito->lineas()->create($payload);
            $idsConservar[] = (int) $nueva->id;
        }

        if ($orden === 0) {
            throw new InvalidArgumentException('Debe cargar al menos una línea con artículo y cantidad.');
        }

        $borrar = RemitoInternoLinea::query()
            ->where('remito_interno_id', $remito->id);
        if ($idsConservar !== []) {
            $borrar->whereNotIn('id', $idsConservar);
        }
        EloquentAuditDeleteSupport::each($borrar);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function resolverDescripcionLinea(array $raw, int $articuloId): ?string
    {
        $desc = trim((string) ($raw['descripcion'] ?? ''));
        if ($desc !== '') {
            return mb_substr($desc, 0, 255);
        }
        $art = Articulo::query()->find($articuloId, ['id', 'descripcion']);

        return $art ? mb_substr((string) $art->descripcion, 0, 255) : null;
    }

    private function tipoStockId(string $abreviatura): int
    {
        $id = (int) (Tipotransaccion_Stock::query()
            ->where('abreviatura', $abreviatura)
            ->where('estado', 'A')
            ->value('id') ?? 0);

        if ($id <= 0) {
            throw new InvalidArgumentException(
                'No está configurado el tipo de stock '.$abreviatura.' (remito interno).'
            );
        }

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function armarPayloadMovimiento(
        RemitoInterno $remito,
        int $tipotransaccionId,
        string $signoCantidad,
        string $leyenda
    ): array {
        $lineas = $remito->lineas;
        $n = $lineas->count();
        $articulosId = [];
        $combinacionesId = [];
        $tallesId = [];
        $coloresId = [];
        $modulosId = [];
        $cantidades = [];
        $medidas = [];
        $precios = [];
        $cajas = [];
        $piezas = [];
        $listas = [];
        $incluye = [];
        $monedas = [];
        $descuentos = [];
        $loteids = [];
        $numeropartes = [];
        $skus = [];

        foreach ($lineas as $linea) {
            $articulosId[] = (int) $linea->articulo_id;
            $combinacionesId[] = $linea->combinacion_id ? (int) $linea->combinacion_id : null;
            $tallesId[] = $linea->talle_id ? (int) $linea->talle_id : null;
            $coloresId[] = $linea->color_id ? (int) $linea->color_id : null;
            $modulosId[] = $linea->modulo_id ? (int) $linea->modulo_id : null;
            $cant = abs((float) $linea->cantidad);
            $cantidades[] = $cant;
            $precios[] = 0;
            $cajas[] = 0;
            $piezas[] = $cant;
            $listas[] = null;
            $incluye[] = '0';
            $monedas[] = null;
            $descuentos[] = 0;
            $loteids[] = 0;
            $numeropartes[] = '';
            $skus[] = '';

            if ((int) ($linea->talle_id ?: 0) > 0) {
                $medidas[] = json_encode([[
                    'talle_id' => (int) $linea->talle_id,
                    'cantidad' => $cant,
                    'precio' => 0,
                    'medida' => '',
                ]], JSON_UNESCAPED_UNICODE);
            } else {
                $medidas[] = '';
            }
        }

        $fecha = $remito->fecha?->format('Y-m-d') ?: now()->toDateString();
        $sucursal = '';
        if ($remito->relationLoaded('localVenta') && $remito->localVenta) {
            $sucursal = RemitoInternoNumeracionSupport::sucursalDesdeLocal($remito->localVenta);
        } elseif ($remito->local_venta_id) {
            $local = LocalVenta::query()->with(['puntoventa', 'puntoventas'])->find($remito->local_venta_id);
            if ($local) {
                $sucursal = RemitoInternoNumeracionSupport::sucursalDesdeLocal($local);
            }
        }
        $codigo = RemitoInternoNumeracionSupport::TIPO_ANITA
            .'-'.RemitoInternoNumeracionSupport::LETRA_ANITA
            .'-'.($sucursal !== '' ? $sucursal : '0')
            .'-'.str_pad((string) $remito->numero, 8, '0', STR_PAD_LEFT);

        return [
            'tipotransaccion_stock_id' => $tipotransaccionId,
            'signo_cantidad' => $signoCantidad,
            'fecha' => $fecha,
            'fechajornada' => $fecha,
            'deposito_id' => (int) $remito->deposito_id,
            'empresa_id' => (int) ($remito->empresa_id ?: 0) ?: null,
            'lote' => 0,
            'codigo' => $codigo,
            'leyenda' => $leyenda.($remito->destinatario ? ' → '.$remito->destinatario : ''),
            'loteimportacion_id' => null,
            'omitir_asiento_contable' => true,
            'articulos_id' => $articulosId,
            'skus' => $skus,
            'combinaciones_id' => $combinacionesId,
            'modulos_id' => $modulosId,
            'items' => $n,
            'cantidades' => $cantidades,
            'cajas' => $cajas,
            'piezas' => $piezas,
            'precios' => $precios,
            'listasprecios_id' => $listas,
            'incluyeimpuestos' => $incluye,
            'monedas_id' => $monedas,
            'descuentos' => $descuentos,
            'loteids' => $loteids,
            'medidas' => $medidas,
            'numeropartes' => $numeropartes,
            'colores_id' => $coloresId,
            'talles_id' => $tallesId,
        ];
    }
}
