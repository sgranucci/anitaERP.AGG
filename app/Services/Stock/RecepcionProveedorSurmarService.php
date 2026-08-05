<?php

namespace App\Services\Stock;

use App\Models\Compras\Proveedor;
use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\Depmae;
use App\Models\Stock\MovimientoStock;
use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Stock\Recepcion_Proveedor_Estado;
use App\Models\Stock\RecepcionProveedorArticuloSurmar;
use App\Models\Stock\Stock_Etiqueta;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Repositories\Stock\Recepcion_ProveedorRepositoryInterface;
use App\Support\Stock\ArticuloMovimientoCantidadSignoSupport;
use App\Support\Stock\RecepcionProveedorSurmarListadoFiltros;
use App\Support\Stock\SurmarEtiquetaZplSupport;
use App\Support\Stock\SurmarSupport;
use Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Recepción Surmar: cabecera en BORRADOR + grabado provisorio por ítem (como Anita a-stock.c).
 * Cada línea se persiste al cerrar el piqueo y emite stock_etiqueta (ID físico).
 */
class RecepcionProveedorSurmarService
{
    public const ORIGEN_CARGA = 'SURMAR';

    public function __construct(
        private readonly Recepcion_ProveedorRepositoryInterface $repository,
    ) {
    }

    /** @param array<string, mixed> $filtros */
    public function listar(array $filtros = [], bool $paginar = true)
    {
        $query = Recepcion_Proveedor::query()
            ->select([
                'recepcion_proveedor.*',
                'empresa.nombre as nombreempresa',
                'proveedor.nombre as nombreproveedor',
            ])
            ->withCount('recepcion_proveedor_articulos')
            ->join('empresa', 'empresa.id', '=', 'recepcion_proveedor.empresa_id')
            ->join('proveedor', 'proveedor.id', '=', 'recepcion_proveedor.proveedor_id')
            ->where('recepcion_proveedor.empresa_id', SurmarSupport::EMPRESA_ID)
            ->where('recepcion_proveedor.origen_carga', self::ORIGEN_CARGA)
            ->orderByDesc('recepcion_proveedor.id');

        if (RecepcionProveedorSurmarListadoFiltros::tieneCriteriosAplicados($filtros)) {
            RecepcionProveedorSurmarListadoFiltros::aplicar($query, $filtros);
        }

        return $paginar ? $query->paginate(10) : $query->get();
    }

    public function buscar(int $id): Recepcion_Proveedor
    {
        $recepcion = Recepcion_Proveedor::query()
            ->with([
                'proveedores',
                'depositos',
                'empresas',
                'recepcion_proveedor_articulos' => fn ($q) => $q->orderBy('orden')->orderBy('id'),
                'recepcion_proveedor_articulos.articulos',
                'recepcion_proveedor_articulos.unidadesmedida',
            ])
            ->whereKey($id)
            ->where('empresa_id', SurmarSupport::EMPRESA_ID)
            ->where('origen_carga', self::ORIGEN_CARGA)
            ->firstOrFail();

        return $recepcion;
    }

    /**
     * Alta de cabecera provisoria (pantalla queda abierta para picar ítems).
     *
     * @param  array<string, mixed>  $data
     */
    public function iniciar(array $data): Recepcion_Proveedor
    {
        $empresaId = SurmarSupport::EMPRESA_ID;
        $proveedorId = (int) ($data['proveedor_id'] ?? 0);
        $depositoId = (int) ($data['deposito_id'] ?? 0);
        $fecha = (string) ($data['fecha'] ?? now()->toDateString());

        if ($proveedorId <= 0 || ! Proveedor::query()->whereKey($proveedorId)->exists()) {
            throw ValidationException::withMessages(['proveedor_id' => 'Proveedor inválido.']);
        }
        if ($depositoId <= 0 || ! Depmae::query()->whereKey($depositoId)->exists()) {
            throw ValidationException::withMessages(['deposito_id' => 'Depósito inválido.']);
        }

        return DB::transaction(function () use ($data, $empresaId, $proveedorId, $depositoId, $fecha) {
            $recepcion = $this->repository->create([
                'ordencompra_id' => null,
                'tipo' => Recepcion_Proveedor::TIPO_RECEPCION,
                'empresa_id' => $empresaId,
                'proveedor_id' => $proveedorId,
                'deposito_id' => $depositoId,
                'fecha' => $fecha,
                'moneda_id' => (int) ($data['moneda_id'] ?? 1),
                'cotizacion' => (float) ($data['cotizacion'] ?? 1),
                'estado' => Recepcion_Proveedor::ESTADO_BORRADOR,
                'observacion' => (string) ($data['observacion'] ?? ''),
                'origen_carga' => self::ORIGEN_CARGA,
                'creousuario_id' => Auth::id(),
                'anita_tipo' => 'COM',
            ]);

            Recepcion_Proveedor_Estado::create([
                'recepcion_proveedor_id' => $recepcion->id,
                'estado' => Recepcion_Proveedor::ESTADO_BORRADOR,
                'fecha' => now(),
                'usuario_id' => Auth::id(),
                'observacion' => 'Inicio recepción Surmar (provisorio)',
            ]);

            return $recepcion->fresh();
        });
    }

    /**
     * Graba un ítem terminado de picar: línea + etiqueta (como graba_comp / recepaper en Anita).
     *
     * @param  array<string, mixed>  $data
     * @return array{linea: RecepcionProveedorArticuloSurmar, etiqueta: Stock_Etiqueta, zpl: string}
     */
    public function guardarLineaProvisoria(int $recepcionId, array $data): array
    {
        $recepcion = $this->buscar($recepcionId);
        $this->assertBorrador($recepcion);

        $articuloId = (int) ($data['articulo_id'] ?? 0);
        $articulo = Articulo::query()->whereKey($articuloId)->first();
        if (! $articulo) {
            throw ValidationException::withMessages(['articulo_id' => 'Artículo inválido.']);
        }

        $pesoNeto = round((float) ($data['peso_neto'] ?? 0), 4);
        $pesoBruto = round((float) ($data['peso_bruto'] ?? $pesoNeto), 4);
        $cantPieza = round((float) ($data['cant_pieza'] ?? 1), 4);
        $lote = trim((string) ($data['lote_proveedor'] ?? $data['certificado'] ?? ''));
        if ($lote === '') {
            throw ValidationException::withMessages(['lote_proveedor' => 'Debe ingresar el lote.']);
        }
        if ($pesoNeto <= 0) {
            throw ValidationException::withMessages(['peso_neto' => 'Peso neto debe ser mayor a 0.']);
        }

        $cantidad = $pesoNeto; // Surmar: cantidad de stock = kilos netos (como Anita)
        $ahora = now();
        $horaPiqueo = $ahora->format('H:i');

        return DB::transaction(function () use (
            $recepcion, $articulo, $data, $pesoNeto, $pesoBruto, $cantPieza, $lote, $cantidad, $ahora, $horaPiqueo
        ) {
            $orden = (int) (RecepcionProveedorArticuloSurmar::query()
                ->where('recepcion_proveedor_id', $recepcion->id)
                ->max('orden') ?? 0) + 1;

            $umdId = (int) ($data['unidadmedida_id'] ?? $articulo->unidadmedida_id ?? 0);
            if ($umdId <= 0) {
                $umdId = null;
            }

            $linea = RecepcionProveedorArticuloSurmar::create([
                'recepcion_proveedor_id' => $recepcion->id,
                'orden' => $orden,
                'articulo_id' => $articulo->id,
                'cantidad' => $cantidad,
                'cantidad_stock' => $cantidad,
                'cantidad_oc' => 0,
                'unidadmedida_id' => $umdId,
                'coeficienteconversion' => 1,
                'precio' => (float) ($data['precio'] ?? 0),
                'precio_stock' => (float) ($data['precio'] ?? 0),
                'moneda_id' => $recepcion->moneda_id,
                'cotizacion' => $recepcion->cotizacion,
                'descuento' => 0,
                'deposito_id' => $recepcion->deposito_id,
                'detalle' => (string) ($data['detalle'] ?? ''),
                'estado' => 'ACTIVA',
                'lote_proveedor' => $lote,
                'certificado' => trim((string) ($data['certificado'] ?? $lote)),
                'fecha_vto' => $data['fecha_vto'] ?? null,
                'peso_bruto' => $pesoBruto,
                'peso_neto' => $pesoNeto,
                'cant_pieza' => $cantPieza,
                'hora_piqueo' => $horaPiqueo,
                'piqueado_at' => $ahora,
            ]);

            $etiqueta = Stock_Etiqueta::create([
                'empresa_id' => SurmarSupport::EMPRESA_ID,
                'articulo_id' => $articulo->id,
                'deposito_id' => $recepcion->deposito_id,
                'unidadmedida_id' => $umdId,
                'estado' => SurmarSupport::ESTADO_DISPONIBLE,
                'origen_tipo' => SurmarSupport::ORIGEN_COM,
                'origen_id' => $recepcion->id,
                'origen_linea_id' => $linea->id,
                'lote_proveedor' => $lote,
                'fecha_vto' => $data['fecha_vto'] ?? null,
                'fecha_emision' => $recepcion->fecha?->format('Y-m-d') ?? $ahora->toDateString(),
                'hora_emision' => $horaPiqueo,
                'cant_pieza' => $cantPieza,
                'peso_bruto' => $pesoBruto,
                'peso_neto' => $pesoNeto,
                'descripcion_snapshot' => mb_substr((string) $articulo->descripcion, 0, 60),
                'anita_tipo' => 'COM',
                'anita_orden' => $orden,
                'usuario_id' => Auth::id(),
            ]);

            $linea->update(['stock_etiqueta_id' => $etiqueta->id]);

            // Toca cabecera para updated_at (sesión viva, igual que graba_comp en Anita)
            $recepcion->touch();

            $zpl = $this->zplDesdeEtiqueta($etiqueta->fresh(['articulos', 'unidadesmedida']), $recepcion);

            return [
                'linea' => $linea->fresh(['articulos', 'unidadesmedida', 'stock_etiqueta']),
                'etiqueta' => $etiqueta,
                'zpl' => $zpl,
            ];
        });
    }

    public function eliminarLineaProvisoria(int $recepcionId, int $lineaId): void
    {
        $recepcion = $this->buscar($recepcionId);
        $this->assertBorrador($recepcion);

        DB::transaction(function () use ($recepcion, $lineaId) {
            $linea = RecepcionProveedorArticuloSurmar::query()
                ->where('recepcion_proveedor_id', $recepcion->id)
                ->whereKey($lineaId)
                ->firstOrFail();

            $etiquetaId = (int) ($linea->stock_etiqueta_id ?? 0);
            $linea->delete();

            if ($etiquetaId > 0) {
                Stock_Etiqueta::query()
                    ->whereKey($etiquetaId)
                    ->where('origen_tipo', SurmarSupport::ORIGEN_COM)
                    ->where('origen_id', $recepcion->id)
                    ->update(['estado' => SurmarSupport::ESTADO_ANULADA]);
            }

            $recepcion->touch();
        });
    }

    public function confirmar(int $id): Recepcion_Proveedor
    {
        $recepcion = $this->buscar($id);
        $this->assertBorrador($recepcion);

        $lineas = RecepcionProveedorArticuloSurmar::query()
            ->where('recepcion_proveedor_id', $recepcion->id)
            ->get();

        if ($lineas->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Debe picar al menos un ítem antes de confirmar.']);
        }

        return DB::transaction(function () use ($recepcion, $lineas) {
            $movId = $this->generarMovimientoStock($recepcion, $lineas);

            foreach ($lineas as $linea) {
                if ((int) ($linea->stock_etiqueta_id ?? 0) <= 0) {
                    continue;
                }
                Stock_Etiqueta::query()->whereKey($linea->stock_etiqueta_id)->update([
                    'articulo_movimiento_id' => $linea->articulo_movimiento_id,
                    'deposito_id' => $linea->deposito_id ?: $recepcion->deposito_id,
                ]);
            }

            $recepcion->update([
                'estado' => Recepcion_Proveedor::ESTADO_CONFIRMADA,
                'movimientostock_id' => $movId,
            ]);

            Recepcion_Proveedor_Estado::create([
                'recepcion_proveedor_id' => $recepcion->id,
                'estado' => Recepcion_Proveedor::ESTADO_CONFIRMADA,
                'fecha' => now(),
                'usuario_id' => Auth::id(),
                'observacion' => 'Confirmación Surmar — stock generado',
            ]);

            return $recepcion->fresh();
        });
    }

    public function anular(int $id): Recepcion_Proveedor
    {
        $recepcion = $this->buscar($id);
        if ($recepcion->estado === Recepcion_Proveedor::ESTADO_ANULADA) {
            return $recepcion;
        }
        if ($recepcion->estado === Recepcion_Proveedor::ESTADO_CONFIRMADA) {
            throw ValidationException::withMessages(['estado' => 'Recepción confirmada: anulación Surmar pendiente de reverso de stock.']);
        }

        return DB::transaction(function () use ($recepcion) {
            Stock_Etiqueta::query()
                ->where('origen_tipo', SurmarSupport::ORIGEN_COM)
                ->where('origen_id', $recepcion->id)
                ->update(['estado' => SurmarSupport::ESTADO_ANULADA]);

            $recepcion->update(['estado' => Recepcion_Proveedor::ESTADO_ANULADA]);
            Recepcion_Proveedor_Estado::create([
                'recepcion_proveedor_id' => $recepcion->id,
                'estado' => Recepcion_Proveedor::ESTADO_ANULADA,
                'fecha' => now(),
                'usuario_id' => Auth::id(),
                'observacion' => 'Anulación borrador Surmar',
            ]);

            return $recepcion->fresh();
        });
    }

    public function eliminarBorrador(int $id): void
    {
        $recepcion = $this->buscar($id);
        $this->assertBorrador($recepcion);

        DB::transaction(function () use ($recepcion) {
            $ids = RecepcionProveedorArticuloSurmar::query()
                ->where('recepcion_proveedor_id', $recepcion->id)
                ->pluck('stock_etiqueta_id')
                ->filter()
                ->all();

            RecepcionProveedorArticuloSurmar::query()
                ->where('recepcion_proveedor_id', $recepcion->id)
                ->delete();

            if ($ids !== []) {
                Stock_Etiqueta::query()->whereIn('id', $ids)->delete();
            }

            Recepcion_Proveedor_Estado::query()
                ->where('recepcion_proveedor_id', $recepcion->id)
                ->delete();

            $recepcion->delete();
        });
    }

    public function zplEtiqueta(int $etiquetaId): string
    {
        $etiqueta = Stock_Etiqueta::query()
            ->with(['articulos', 'unidadesmedida'])
            ->whereKey($etiquetaId)
            ->where('empresa_id', SurmarSupport::EMPRESA_ID)
            ->firstOrFail();

        $recepcion = null;
        if ($etiqueta->origen_tipo === SurmarSupport::ORIGEN_COM && $etiqueta->origen_id) {
            $recepcion = Recepcion_Proveedor::query()->find($etiqueta->origen_id);
        }

        return $this->zplDesdeEtiqueta($etiqueta, $recepcion);
    }

    /** @return array<string, mixed> */
    public function lineaPayload(RecepcionProveedorArticuloSurmar $linea): array
    {
        $art = $linea->articulos;

        return [
            'id' => $linea->id,
            'orden' => $linea->orden,
            'articulo_id' => $linea->articulo_id,
            'codigo' => $art->sku ?? '',
            'descripcion' => $art->descripcion ?? $linea->detalle,
            'lote_proveedor' => $linea->lote_proveedor,
            'certificado' => $linea->certificado,
            'fecha_vto' => optional($linea->fecha_vto)->format('Y-m-d'),
            'peso_bruto' => (float) $linea->peso_bruto,
            'peso_neto' => (float) $linea->peso_neto,
            'cant_pieza' => (float) $linea->cant_pieza,
            'hora_piqueo' => $linea->hora_piqueo,
            'piqueado_at' => optional($linea->piqueado_at)->format('d/m/Y H:i:s'),
            'stock_etiqueta_id' => $linea->stock_etiqueta_id,
        ];
    }

    private function assertBorrador(Recepcion_Proveedor $recepcion): void
    {
        if ($recepcion->estado !== Recepcion_Proveedor::ESTADO_BORRADOR) {
            throw ValidationException::withMessages(['estado' => 'La recepción ya no está en estado provisorio.']);
        }
    }

    /** @param \Illuminate\Support\Collection<int, RecepcionProveedorArticuloSurmar> $lineas */
    private function generarMovimientoStock(Recepcion_Proveedor $recepcion, $lineas): int
    {
        $tipoStock = Tipotransaccion_Stock::where('abreviatura', 'RCING')->first();
        if (! $tipoStock) {
            throw new \RuntimeException('Tipo transacción stock RCING no configurado.');
        }

        $signoDb = (int) $tipoStock->getRawOriginal('signo');
        $concepto = 'Recepción Surmar '.$recepcion->numerorecepcion;

        $mov = MovimientoStock::create([
            'fecha' => $recepcion->fecha->format('Y-m-d'),
            'fechajornada' => $recepcion->fecha->format('Y-m-d'),
            'tipotransaccion_stock_id' => $tipoStock->id,
            'codigo' => substr((string) $recepcion->numerorecepcion, 0, 10),
            'leyenda' => $concepto,
            'estado' => 'A',
            'usuario_id' => Auth::id(),
        ]);

        foreach ($lineas as $linea) {
            $cantidad = (float) $linea->cantidad;
            if ($cantidad <= 0.000001) {
                continue;
            }

            $cantidadFirmada = ArticuloMovimientoCantidadSignoSupport::cantidadFirmadaSignoStock(
                $cantidad,
                $signoDb
            );

            $am = Articulo_Movimiento::create([
                'fecha' => $recepcion->fecha->format('Y-m-d'),
                'fechajornada' => $recepcion->fecha->format('Y-m-d'),
                'tipotransaccion_stock_id' => $tipoStock->id,
                'movimientostock_id' => $mov->id,
                'articulo_id' => $linea->articulo_id,
                'concepto' => $concepto,
                'cantidad' => $cantidadFirmada,
                'precio' => (float) ($linea->precio_stock ?: $linea->precio),
                'costo' => (float) ($linea->precio_stock ?: $linea->precio),
                'descuento' => 0,
                'moneda_id' => $linea->moneda_id,
                'incluyeimpuesto' => 'N',
                'deposito_id' => $linea->deposito_id ?: $recepcion->deposito_id,
                'lote' => 0,
            ]);

            $linea->update(['articulo_movimiento_id' => $am->id]);
        }

        return (int) $mov->id;
    }

    private function zplDesdeEtiqueta(Stock_Etiqueta $etiqueta, ?Recepcion_Proveedor $recepcion): string
    {
        $art = $etiqueta->articulos;
        $umd = $etiqueta->unidadesmedida;
        $proveedorNombre = '';
        if ($recepcion) {
            $recepcion->loadMissing('proveedores');
            $proveedorNombre = (string) ($recepcion->proveedores->nombre ?? '');
        }

        return SurmarEtiquetaZplSupport::generar([
            'id' => (int) $etiqueta->id,
            'codigo_articulo' => (string) ($art->sku ?? ''),
            'descripcion' => (string) ($etiqueta->descripcion_snapshot ?: ($art->descripcion ?? '')),
            'proveedor' => $proveedorNombre,
            'peso_bruto' => (float) $etiqueta->peso_bruto,
            'peso_neto' => (float) $etiqueta->peso_neto,
            'cant_pieza' => (float) $etiqueta->cant_pieza,
            'umd' => (string) ($umd->abreviatura ?? $umd->nombre ?? 'KG'),
            'cantidad' => (int) round((float) $etiqueta->peso_neto),
            'lote' => (string) $etiqueta->lote_proveedor,
            'fecha' => optional($etiqueta->fecha_emision)->format('d/m/Y'),
            'fecha_vto' => optional($etiqueta->fecha_vto)->format('d/m/Y'),
        ]);
    }
}
