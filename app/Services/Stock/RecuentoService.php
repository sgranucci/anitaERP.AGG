<?php

namespace App\Services\Stock;

use App\Models\Stock\Articulo;
use App\Support\Stock\ArticuloSeleccionOperativaSupport;
use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\Depmae;
use App\Models\Stock\MovimientoStock;
use App\Models\Stock\Recuento;
use App\Models\Stock\Recuento_Estado;
use App\Models\Stock\Recuento_Item;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Repositories\Stock\Articulo_Saldo_DepositoRepositoryInterface;
use App\Repositories\Stock\Recuento_ArchivoRepositoryInterface;
use App\Repositories\Stock\Recuento_ItemRepositoryInterface;
use App\Repositories\Stock\RecuentoRepositoryInterface;
use App\Models\Stock\Color;
use App\Models\Stock\Talle;
use App\Support\Database\SqlDialectSupport;
use App\Support\Stock\ArticuloEmpresaAsignacionSupport;
use App\Support\Stock\ArticuloMovimientoCantidadSignoSupport;
use App\Support\Stock\ArticuloPrecioUltimaCompraSupport;
use App\Support\Stock\ArticuloStockColorTalleSupport;
use App\Support\Stock\MovimientoStockColorTalleExclusividadSupport;
use App\Support\Stock\RecuentoModoCierreSupport;
use App\Support\Stock\UsuarioDepositoAutorizado;
use InvalidArgumentException;
use Auth;
use Illuminate\Support\Facades\DB;

class RecuentoService
{
    public function __construct(
        private readonly RecuentoRepositoryInterface $recuentoRepository,
        private readonly Recuento_ItemRepositoryInterface $itemRepository,
        private readonly Recuento_ArchivoRepositoryInterface $archivoRepository,
        private readonly Articulo_Saldo_DepositoRepositoryInterface $saldoRepository,
    ) {}

    public function listar()
    {
        return $this->recuentoRepository->all();
    }

    public function buscar(int $id): Recuento
    {
        return $this->recuentoRepository->findConRelaciones($id);
    }

    public function guardar(array $data, $request = null): Recuento
    {
        $this->validarDatosBasicos($data);

        return DB::transaction(function () use ($data, $request) {
            $deposito = $this->resolverDeposito((int) $data['deposito_id']);

            $recuento = $this->recuentoRepository->create([
                'fecha' => $data['fecha'],
                'deposito_id' => $deposito->id,
                'empresa_id' => (int) $deposito->empresa_id,
                'usuario_id' => Auth::id(),
                'estado' => Recuento::ESTADO_PENDIENTE,
                'tipo' => $data['tipo'] ?? Recuento::TIPO_MANUAL,
                'cantidad_aleatoria' => $data['cantidad_aleatoria'] ?? null,
                'comentario' => $data['comentario'] ?? null,
            ]);

            $recuento->codigo = 'RC-'.str_pad((string) $recuento->id, 6, '0', STR_PAD_LEFT);
            $recuento->save();

            $this->itemRepository->syncFromRequest($data, $recuento->id, $deposito->id);
            $this->validarTieneItems($recuento->id);

            if ($request) {
                $this->archivoRepository->create($request, $recuento->id);
            }

            $this->logEstado($recuento, null, Recuento::ESTADO_PENDIENTE, 'Recuento creado');

            return $recuento->fresh(['items']);
        });
    }

    public function actualizar(int $id, array $data, $request = null): Recuento
    {
        $recuento = $this->recuentoRepository->find($id);
        $this->assertEditable($recuento);

        $this->validarDatosBasicos($data, true);

        return DB::transaction(function () use ($recuento, $data, $request) {
            $deposito = $this->resolverDeposito((int) $data['deposito_id']);

            $recuento->fill([
                'fecha' => $data['fecha'],
                'deposito_id' => $deposito->id,
                'empresa_id' => (int) $deposito->empresa_id,
                'comentario' => $data['comentario'] ?? null,
            ])->save();

            $this->itemRepository->syncFromRequest($data, $recuento->id, $deposito->id);
            $this->validarTieneItems($recuento->id);

            if ($request) {
                $this->archivoRepository->update($request, $recuento->id);
            }

            return $recuento->fresh(['items']);
        });
    }

    public function eliminar(int $id): void
    {
        $recuento = $this->recuentoRepository->find($id);
        if ($recuento->estado !== Recuento::ESTADO_PENDIENTE) {
            throw new \RuntimeException('Solo se puede eliminar un recuento en estado PENDIENTE.');
        }
        $this->recuentoRepository->delete($id);
    }

    public function suspender(int $id, ?string $obs = null): Recuento
    {
        $recuento = $this->recuentoRepository->find($id);
        if ($recuento->estado !== Recuento::ESTADO_PENDIENTE) {
            throw new \RuntimeException('Solo se puede suspender un recuento en estado PENDIENTE.');
        }

        return $this->cambiarEstado($recuento, Recuento::ESTADO_SUSPENDIDO, $obs ?? 'Recuento suspendido');
    }

    public function reactivar(int $id, ?string $obs = null): Recuento
    {
        $recuento = $this->recuentoRepository->find($id);
        if ($recuento->estado !== Recuento::ESTADO_SUSPENDIDO) {
            throw new \RuntimeException('Solo se puede reactivar un recuento suspendido.');
        }

        return $this->cambiarEstado($recuento, Recuento::ESTADO_PENDIENTE, $obs ?? 'Recuento reactivado');
    }

    public function anular(int $id, ?string $obs = null): Recuento
    {
        $recuento = $this->recuentoRepository->find($id);
        if (! in_array($recuento->estado, [Recuento::ESTADO_PENDIENTE, Recuento::ESTADO_SUSPENDIDO], true)) {
            throw new \RuntimeException('Solo se puede anular un recuento pendiente o suspendido.');
        }

        return $this->cambiarEstado($recuento, Recuento::ESTADO_ANULADO, $obs ?? 'Recuento anulado');
    }

    public function cerrarParcial(int $id, ?string $obs = null, ?string $modoCierre = null): Recuento
    {
        $recuento = $this->recuentoRepository->findConRelaciones($id);
        if (! in_array($recuento->estado, [Recuento::ESTADO_PENDIENTE, Recuento::ESTADO_SUSPENDIDO], true)) {
            throw new \RuntimeException('Solo se puede cerrar un recuento pendiente o suspendido.');
        }

        $items = $recuento->items;
        if ($items->isEmpty()) {
            throw new \RuntimeException('El recuento no tiene líneas cargadas.');
        }

        $modo = RecuentoModoCierreSupport::resolverModo($modoCierre);

        return DB::transaction(function () use ($recuento, $items, $obs, $modo) {
            $ajustes = [];
            foreach ($items as $item) {
                $colorId = (int) ($item->color_id ?? 0);
                $talleId = (int) ($item->talle_id ?? 0);
                $saldoRef = RecuentoModoCierreSupport::saldoReferencia(
                    $this->saldoRepository,
                    (int) $item->articulo_id,
                    (int) $recuento->deposito_id,
                    $modo,
                    $recuento->fecha,
                    $colorId > 0 ? $colorId : null,
                    $talleId > 0 ? $talleId : null
                );
                $contado = (float) $item->cantidad_contada;
                $delta = $contado - $saldoRef;
                if (abs($delta) < 1e-9) {
                    continue;
                }
                $ajustes[] = [
                    'articulo_id' => (int) $item->articulo_id,
                    'color_id' => $colorId,
                    'talle_id' => $talleId,
                    'delta' => $delta,
                    'concepto' => "Recuento {$recuento->codigo} - cierre parcial",
                ];
            }

            $fechaMov = RecuentoModoCierreSupport::fechaMovimientoCierre($recuento, $modo);
            $movId = null;
            if ($ajustes !== []) {
                $movId = $this->generarMovimientoAjuste($recuento, $ajustes, 'Cierre parcial de recuento', null, $fechaMov);
            }
            $estadoAnterior = $recuento->estado;
            $recuento->movimientostock_cierre_id = $movId;
            $recuento->modo_cierre = $modo;
            $recuento->estado = Recuento::ESTADO_CERRADO_PARCIAL;
            $recuento->save();

            $this->logEstado(
                $recuento,
                $estadoAnterior,
                Recuento::ESTADO_CERRADO_PARCIAL,
                $this->observacionCierre($obs, $modo, 'Cierre parcial procesado en stock')
            );

            return $recuento->fresh();
        });
    }

    public function cerrarTotal(int $id, ?string $obs = null, ?string $modoCierre = null): Recuento
    {
        $recuento = $this->recuentoRepository->findConRelaciones($id);
        if (! in_array($recuento->estado, [Recuento::ESTADO_PENDIENTE, Recuento::ESTADO_SUSPENDIDO], true)) {
            throw new \RuntimeException('Solo se puede cerrar un recuento pendiente o suspendido.');
        }

        $modo = RecuentoModoCierreSupport::resolverModo($modoCierre);

        return DB::transaction(function () use ($recuento, $obs, $modo) {
            $conteos = [];
            $modoCt = null;
            foreach ($recuento->items as $item) {
                $articuloId = (int) $item->articulo_id;
                $colorId = (int) ($item->color_id ?? 0);
                $talleId = (int) ($item->talle_id ?? 0);
                [$colorKey, $talleKey] = ArticuloStockColorTalleSupport::claveSaldo(
                    $colorId > 0 ? $colorId : null,
                    $talleId > 0 ? $talleId : null
                );
                $clave = $this->claveVariante($articuloId, $colorKey, $talleKey);
                $conteos[$clave] = (float) $item->cantidad_contada;
                $maneja = ArticuloStockColorTalleSupport::articuloManejaColorTalle($articuloId);
                if ($modoCt === null) {
                    $modoCt = $maneja;
                }
            }

            $variantes = $this->variantesConStockEnDeposito((int) $recuento->deposito_id, $modoCt);
            foreach (array_keys($conteos) as $clave) {
                if (! isset($variantes[$clave])) {
                    [$articuloId, $colorKey, $talleKey] = array_map('intval', explode('|', $clave));
                    $variantes[$clave] = [
                        'articulo_id' => $articuloId,
                        'color_id' => $colorKey,
                        'talle_id' => $talleKey,
                    ];
                }
            }

            $ajustes = [];
            foreach ($variantes as $clave => $variante) {
                $articuloId = (int) $variante['articulo_id'];
                $colorId = (int) $variante['color_id'];
                $talleId = (int) $variante['talle_id'];
                $saldoRef = RecuentoModoCierreSupport::saldoReferencia(
                    $this->saldoRepository,
                    $articuloId,
                    (int) $recuento->deposito_id,
                    $modo,
                    $recuento->fecha,
                    $colorId > 0 ? $colorId : null,
                    $talleId > 0 ? $talleId : null
                );
                $contado = (float) ($conteos[$clave] ?? 0);
                $delta = $contado - $saldoRef;
                if (abs($delta) < 1e-9) {
                    continue;
                }
                $ajustes[] = [
                    'articulo_id' => $articuloId,
                    'color_id' => $colorId,
                    'talle_id' => $talleId,
                    'delta' => $delta,
                    'concepto' => "Recuento {$recuento->codigo} - cierre total",
                ];
            }

            $fechaMov = RecuentoModoCierreSupport::fechaMovimientoCierre($recuento, $modo);
            $movId = null;
            if ($ajustes !== []) {
                $movId = $this->generarMovimientoAjuste($recuento, $ajustes, 'Cierre total de recuento', null, $fechaMov);
            }
            $estadoAnterior = $recuento->estado;
            $recuento->movimientostock_cierre_id = $movId;
            $recuento->modo_cierre = $modo;
            $recuento->estado = Recuento::ESTADO_CERRADO_TOTAL;
            $recuento->save();

            $this->logEstado(
                $recuento,
                $estadoAnterior,
                Recuento::ESTADO_CERRADO_TOTAL,
                $this->observacionCierre($obs, $modo, 'Cierre total procesado en stock')
            );

            return $recuento->fresh();
        });
    }

    public function anularCierre(int $id, ?string $obs = null): Recuento
    {
        $recuento = $this->recuentoRepository->findConRelaciones($id);
        if (! $recuento->estaCerrado()) {
            throw new \RuntimeException('Solo se puede anular el cierre de un recuento cerrado.');
        }
        if (! $recuento->movimientostock_cierre_id) {
            throw new \RuntimeException('El recuento no tiene movimiento de cierre asociado.');
        }

        return DB::transaction(function () use ($recuento, $obs) {
            $movCierre = MovimientoStock::with(['articulos_movimiento'])->find($recuento->movimientostock_cierre_id);
            if (! $movCierre) {
                throw new \RuntimeException('No se encontró el movimiento de cierre.');
            }

            $ajustesReverso = [];
            foreach ($movCierre->articulos_movimiento as $mov) {
                $colorId = (int) ($mov->color_id ?? 0);
                $talleId = (int) ($mov->talle_id ?? 0);
                $ajustesReverso[] = [
                    'articulo_id' => (int) $mov->articulo_id,
                    'color_id' => $colorId,
                    'talle_id' => $talleId,
                    'delta' => -1 * (float) $mov->cantidad,
                    'concepto' => "Anulación cierre recuento {$recuento->codigo}",
                ];
            }

            $movAnulacionId = $this->generarMovimientoAjuste($recuento, $ajustesReverso, 'Anulación de cierre de recuento', 'RCAJR');
            $estadoAnterior = $recuento->estado;
            $recuento->movimientostock_anulacion_id = $movAnulacionId;
            $recuento->modo_cierre = null;
            $recuento->estado = Recuento::ESTADO_PENDIENTE;
            $recuento->save();

            $this->logEstado($recuento, $estadoAnterior, Recuento::ESTADO_PENDIENTE, $obs ?? 'Cierre anulado; recuento reabierto');

            return $recuento->fresh();
        });
    }

    /**
     * Sortea N líneas (artículo o variante color/talle) para el recuento.
     * Prioriza variantes con saldo ≠ 0; si no hay, artículos con depósito de entrega.
     * Exclusividad: un solo modo (CT o no) por sorteo.
     *
     * @return array<int, array<string, mixed>>
     */
    public function generarLineasAleatorias(int $depositoId, int $cantidad): array
    {
        $deposito = $this->resolverDeposito($depositoId);
        $candidatos = $this->candidatosAleatorio((int) $deposito->id);

        if ($candidatos === []) {
            throw new \RuntimeException(
                'No hay artículos/variantes con saldo ni asignados al depósito para sortear.'
            );
        }

        $modoCt = null;
        foreach ($candidatos as $c) {
            if ($modoCt === null) {
                $modoCt = (bool) $c['maneja_ct'];
            }
        }
        $candidatos = array_values(array_filter(
            $candidatos,
            static fn (array $c): bool => (bool) $c['maneja_ct'] === $modoCt
        ));

        shuffle($candidatos);
        $seleccionados = array_slice($candidatos, 0, min($cantidad, count($candidatos)));
        $lineas = [];

        foreach ($seleccionados as $cand) {
            $articulo = Articulo::query()
                ->with('unidadesdemedidas:id,abreviatura,nombre')
                ->find((int) $cand['articulo_id']);
            if (! $articulo || ! ArticuloSeleccionOperativaSupport::esSeleccionable($articulo)) {
                continue;
            }
            $colorId = (int) ($cand['color_id'] ?? 0);
            $talleId = (int) ($cand['talle_id'] ?? 0);
            $saldo = $this->saldoRepository->saldoVariante(
                (int) $articulo->id,
                (int) $deposito->id,
                $colorId > 0 ? $colorId : null,
                $talleId > 0 ? $talleId : null
            );
            $lineas[] = [
                'articulo_id' => $articulo->id,
                'sku' => $articulo->sku,
                'descripcion' => $articulo->descripcion,
                'detalle' => $articulo->descripcion,
                'unidadmedida_id' => $articulo->unidadmedida_id,
                'unidadmedida' => optional($articulo->unidadesdemedidas)->abreviatura,
                'color_id' => $colorId,
                'talle_id' => $talleId,
                'maneja_stock_color_talle' => (bool) ($articulo->maneja_stock_color_talle ?? false),
                'saldo_sistema' => $saldo,
                'cantidad_contada' => 0,
            ];
        }

        if ($lineas === []) {
            throw new \RuntimeException('No se pudieron armar líneas para el recuento aleatorio.');
        }

        return $lineas;
    }

    /**
     * @return list<array{articulo_id:int, color_id:int, talle_id:int, maneja_ct:bool}>
     */
    private function candidatosAleatorio(int $depositoId): array
    {
        $variantes = $this->variantesConStockEnDeposito($depositoId, null);
        $candidatos = [];

        if ($variantes !== []) {
            $flags = Articulo::query()
                ->whereIn('id', array_values(array_unique(array_column($variantes, 'articulo_id'))))
                ->pluck('maneja_stock_color_talle', 'id');
            foreach ($variantes as $v) {
                $candidatos[] = [
                    'articulo_id' => (int) $v['articulo_id'],
                    'color_id' => (int) $v['color_id'],
                    'talle_id' => (int) $v['talle_id'],
                    'maneja_ct' => (bool) ($flags[$v['articulo_id']] ?? false),
                ];
            }

            return $candidatos;
        }

        foreach ($this->articuloIdsParaRecuentoAleatorio($depositoId) as $articuloId) {
            $maneja = ArticuloStockColorTalleSupport::articuloManejaColorTalle((int) $articuloId);
            $candidatos[] = [
                'articulo_id' => (int) $articuloId,
                'color_id' => 0,
                'talle_id' => 0,
                'maneja_ct' => $maneja,
            ];
        }

        return $candidatos;
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function articuloIdsParaRecuentoAleatorio(int $depositoId)
    {
        $porDepositoEntrega = ArticuloSeleccionOperativaSupport::aplicarSoloActivosTablaArticulo(
            Articulo::query()->where('depositoentrega_id', $depositoId)
        )
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($porDepositoEntrega->isNotEmpty()) {
            return $porDepositoEntrega->unique()->values();
        }

        $porSaldo = DB::table('articulo_saldo_deposito')
            ->where('deposito_id', $depositoId)
            ->pluck('articulo_id')
            ->map(fn ($id) => (int) $id);

        $porMovimiento = DB::table('articulo_movimiento')
            ->where('deposito_id', $depositoId)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('articulo_id')
            ->map(fn ($id) => (int) $id);

        return ArticuloSeleccionOperativaSupport::aplicarSoloActivosTablaArticulo(
            Articulo::query()->whereIn('id', $porSaldo->merge($porMovimiento)->filter(fn ($id) => $id > 0)->unique()->values())
        )
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    /**
     * @param  array<int, array{sku:string, cantidad_contada:float, detalle?:string, color?:string|null, talle?:string|null}>  $filas
     */
    public function lineasDesdeImportacion(int $depositoId, array $filas): array
    {
        $this->resolverDeposito($depositoId);
        $lineas = [];
        $clavesVistas = [];
        $articulosId = [];
        $coloresId = [];
        $tallesId = [];

        foreach ($filas as $fila) {
            $sku = trim((string) ($fila['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $articulo = ArticuloSeleccionOperativaSupport::aplicarSoloActivosTablaArticulo(
                Articulo::query()
                    ->with('unidadesdemedidas:id,abreviatura,nombre')
                    ->where('sku', $sku)
            )->first();
            if (! $articulo) {
                throw new \RuntimeException("Artículo no encontrado o inactivo con SKU: {$sku}");
            }

            $colorId = $this->resolverColorImport($fila['color'] ?? null);
            $talleId = $this->resolverTalleImport($fila['talle'] ?? null);
            $maneja = (bool) ($articulo->maneja_stock_color_talle ?? false);

            if ($maneja && ($colorId <= 0 || $talleId <= 0)) {
                throw new \RuntimeException(
                    "El artículo «{$sku}» maneja color/talle: indique ambas columnas en el archivo."
                );
            }
            if (! $maneja && ($colorId > 0 || $talleId > 0)) {
                throw new \RuntimeException(
                    "El artículo «{$sku}» no maneja color/talle: no informe esas columnas."
                );
            }

            [$colorKey, $talleKey] = ArticuloStockColorTalleSupport::claveSaldo(
                $colorId > 0 ? $colorId : null,
                $talleId > 0 ? $talleId : null
            );
            $clave = $this->claveVariante((int) $articulo->id, $colorKey, $talleKey);
            if (isset($clavesVistas[$clave])) {
                throw new \RuntimeException(
                    "La variante de «{$sku}» está repetida en el archivo importado. "
                    .'Cada combinación artículo/color/talle debe figurar una sola vez.'
                );
            }
            $clavesVistas[$clave] = true;

            $articulosId[] = (int) $articulo->id;
            $coloresId[] = $colorKey;
            $tallesId[] = $talleKey;

            $lineas[] = [
                'articulo_id' => $articulo->id,
                'sku' => $articulo->sku,
                'descripcion' => $articulo->descripcion,
                'detalle' => $fila['detalle'] ?? $articulo->descripcion,
                'unidadmedida_id' => $articulo->unidadmedida_id,
                'unidadmedida' => optional($articulo->unidadesdemedidas)->abreviatura,
                'color_id' => $colorKey,
                'talle_id' => $talleKey,
                'maneja_stock_color_talle' => $maneja,
                'saldo_sistema' => $this->saldoRepository->saldoVariante(
                    (int) $articulo->id,
                    $depositoId,
                    $colorKey > 0 ? $colorKey : null,
                    $talleKey > 0 ? $talleKey : null
                ),
                'cantidad_contada' => (float) ($fila['cantidad_contada'] ?? 0),
            ];
        }

        if ($lineas === []) {
            throw new \RuntimeException('No se importó ninguna línea válida.');
        }

        try {
            MovimientoStockColorTalleExclusividadSupport::validarLineas($articulosId, $coloresId, $tallesId);
        } catch (InvalidArgumentException $e) {
            throw new \RuntimeException($e->getMessage(), 0, $e);
        }

        return $lineas;
    }

    private function resolverColorImport(?string $valor): int
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return 0;
        }
        if (ctype_digit($valor)) {
            return (int) $valor;
        }

        $id = Color::query()
            ->whereRaw(SqlDialectSupport::lower('nombre').' = ?', [mb_strtolower($valor)])
            ->value('id');

        if (! $id) {
            throw new \RuntimeException("Color no encontrado: {$valor}");
        }

        return (int) $id;
    }

    private function resolverTalleImport(?string $valor): int
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return 0;
        }
        if (ctype_digit($valor)) {
            return (int) $valor;
        }

        $id = Talle::query()
            ->whereRaw(SqlDialectSupport::lower('nombre').' = ?', [mb_strtolower($valor)])
            ->value('id');

        if (! $id) {
            throw new \RuntimeException("Talle no encontrado: {$valor}");
        }

        return (int) $id;
    }

    public function importarLineas(int $id, array $filas): Recuento
    {
        $recuento = $this->recuentoRepository->find($id);
        $this->assertEditable($recuento);
        $lineas = $this->lineasDesdeImportacion((int) $recuento->deposito_id, $filas);

        $payload = [
            'articulo_ids' => [],
            'recuento_item_ids' => [],
            'colores_id' => [],
            'talles_id' => [],
            'detalle_articulos' => [],
            'cantidades_contadas' => [],
            'saldos_sistema' => [],
            'unidadmedida_ids' => [],
        ];
        foreach ($lineas as $ln) {
            $payload['articulo_ids'][] = $ln['articulo_id'];
            $payload['recuento_item_ids'][] = '';
            $payload['colores_id'][] = (int) ($ln['color_id'] ?? 0);
            $payload['talles_id'][] = (int) ($ln['talle_id'] ?? 0);
            $payload['detalle_articulos'][] = $ln['detalle'] ?? '';
            $payload['cantidades_contadas'][] = $ln['cantidad_contada'];
            $payload['saldos_sistema'][] = $ln['saldo_sistema'];
            $payload['unidadmedida_ids'][] = $ln['unidadmedida_id'] ?? '';
        }

        return DB::transaction(function () use ($recuento, $payload) {
            $this->itemRepository->syncFromRequest($payload, $recuento->id, (int) $recuento->deposito_id);
            $recuento->tipo = Recuento::TIPO_IMPORTADO;
            $recuento->save();

            return $recuento->fresh(['items']);
        });
    }

    public function saldoArticulo(
        int $articuloId,
        int $depositoId,
        ?int $colorId = null,
        ?int $talleId = null,
    ): float {
        $this->resolverDeposito($depositoId);

        $color = ($colorId !== null && $colorId > 0) ? $colorId : 0;
        $talle = ($talleId !== null && $talleId > 0) ? $talleId : 0;

        if ($color > 0 || $talle > 0) {
            return $this->saldoRepository->saldoVariante(
                $articuloId,
                $depositoId,
                $color > 0 ? $color : null,
                $talle > 0 ? $talle : null
            );
        }

        // Artículo con flag y sin variante elegida → saldo 0/0 (no el total sumado).
        if (ArticuloStockColorTalleSupport::articuloManejaColorTalle($articuloId)) {
            return $this->saldoRepository->saldoVariante($articuloId, $depositoId, null, null);
        }

        return $this->saldoRepository->saldo($articuloId, $depositoId);
    }

    private function claveVariante(int $articuloId, int $colorKey, int $talleKey): string
    {
        return $articuloId.'|'.$colorKey.'|'.$talleKey;
    }

    /**
     * Variantes con saldo ≠ 0 en el depósito, filtradas por modo CT del comprobante.
     * $modoCt true = solo artículos con flag; false = solo sin flag (variante 0/0);
     * null = sin filtro (recuento vacío: todas las variantes con stock).
     *
     * @return array<string, array{articulo_id:int, color_id:int, talle_id:int}>
     */
    private function variantesConStockEnDeposito(int $depositoId, ?bool $modoCt): array
    {
        $filas = DB::table('articulo_saldo_deposito')
            ->where('deposito_id', $depositoId)
            ->whereRaw('ABS(cantidad) > ?', [1e-9])
            ->get(['articulo_id', 'color_id', 'talle_id']);

        $desdeMov = DB::table('articulo_movimiento')
            ->select('articulo_id', 'color_id', 'talle_id')
            ->selectRaw('SUM(cantidad) as total')
            ->where('deposito_id', $depositoId)
            ->whereNull('deleted_at')
            ->groupBy('articulo_id', 'color_id', 'talle_id')
            ->havingRaw('ABS(SUM(cantidad)) > ?', [1e-9])
            ->get();

        $mapa = [];
        foreach ($filas->merge($desdeMov) as $row) {
            $articuloId = (int) $row->articulo_id;
            if ($articuloId <= 0) {
                continue;
            }
            [$colorKey, $talleKey] = ArticuloStockColorTalleSupport::claveSaldo(
                isset($row->color_id) ? (int) $row->color_id : null,
                isset($row->talle_id) ? (int) $row->talle_id : null
            );
            $clave = $this->claveVariante($articuloId, $colorKey, $talleKey);
            $mapa[$clave] = [
                'articulo_id' => $articuloId,
                'color_id' => $colorKey,
                'talle_id' => $talleKey,
            ];
        }

        if ($modoCt === null || $mapa === []) {
            return $mapa;
        }

        $articuloIds = array_values(array_unique(array_column($mapa, 'articulo_id')));
        $flags = Articulo::query()
            ->whereIn('id', $articuloIds)
            ->pluck('maneja_stock_color_talle', 'id');

        $filtrado = [];
        foreach ($mapa as $clave => $variante) {
            $maneja = (bool) ($flags[$variante['articulo_id']] ?? false);
            if ($modoCt && ! $maneja) {
                continue;
            }
            if (! $modoCt && $maneja) {
                continue;
            }
            if (! $modoCt && ($variante['color_id'] !== 0 || $variante['talle_id'] !== 0)) {
                continue;
            }
            $filtrado[$clave] = $variante;
        }

        return $filtrado;
    }

    private function validarDatosBasicos(array $data, bool $esActualizacion = false): void
    {
        foreach (['fecha', 'deposito_id'] as $campo) {
            if (empty($data[$campo])) {
                throw new \RuntimeException("Campo requerido: {$campo}");
            }
        }
    }

    private function resolverDeposito(int $depositoId): Depmae
    {
        $deposito = Depmae::query()->find($depositoId);
        if (! $deposito) {
            throw new \RuntimeException('Depósito no encontrado.');
        }
        if (! Depmae::autorizadoParaUsuarioYEmpresa((int) $deposito->id, (int) $deposito->empresa_id)) {
            throw new \RuntimeException('Depósito no autorizado para su usuario o empresa.');
        }
        if (! UsuarioDepositoAutorizado::depositoAutorizado((int) $deposito->id)) {
            throw new \RuntimeException('No tiene permiso para operar sobre este depósito.');
        }

        return $deposito;
    }

    private function assertEditable(Recuento $recuento): void
    {
        if (! $recuento->esEditable()) {
            throw new \RuntimeException(
                'El recuento no puede editarse en estado '.$recuento->estado.'.'
            );
        }
    }

    private function validarTieneItems(int $recuentoId): void
    {
        if (Recuento_Item::where('recuento_id', $recuentoId)->count() === 0) {
            throw new \RuntimeException('Debe cargar al menos un artículo en el recuento.');
        }
    }

    private function cambiarEstado(Recuento $recuento, string $nuevo, ?string $obs): Recuento
    {
        return DB::transaction(function () use ($recuento, $nuevo, $obs) {
            $anterior = $recuento->estado;
            $recuento->estado = $nuevo;
            $recuento->save();
            $this->logEstado($recuento, $anterior, $nuevo, $obs);

            return $recuento->fresh();
        });
    }

    private function logEstado(Recuento $recuento, ?string $anterior, string $nuevo, ?string $obs): void
    {
        Recuento_Estado::create([
            'recuento_id' => $recuento->id,
            'estado_anterior' => $anterior,
            'estado_nuevo' => $nuevo,
            'usuario_id' => Auth::id(),
            'observaciones' => $obs,
            'ocurrio_el' => now(),
        ]);
    }

    /**
     * @param  list<array{articulo_id:int, delta:float, concepto:string}>  $ajustes
     */
    private function generarMovimientoAjuste(
        Recuento $recuento,
        array $ajustes,
        string $leyenda,
        ?string $abrevReverso = null,
        ?string $fechaMovimiento = null,
    ): int {
        if ($ajustes === []) {
            throw new \RuntimeException('No hay diferencias de stock para procesar.');
        }

        $fecha = $fechaMovimiento ?: now()->toDateString();

        $codigo = 'RC-'.str_pad((string) $recuento->id, 6, '0', STR_PAD_LEFT).'-'.now()->format('YmdHis');

        $tipoPositivo = Tipotransaccion_Stock::where('abreviatura', 'RCAJP')->first();
        $tipoNegativo = Tipotransaccion_Stock::where('abreviatura', 'RCAJN')->first();
        $tipoReverso = $abrevReverso
            ? Tipotransaccion_Stock::where('abreviatura', $abrevReverso)->first()
            : null;

        if (! $tipoPositivo || ! $tipoNegativo) {
            throw new \RuntimeException('Faltan tipos de transacción RCAJP/RCAJN. Ejecute las migraciones de recuento.');
        }

        $tipoCabecera = $tipoPositivo;

        $articuloIdsAjuste = array_values(array_unique(array_map(
            static fn (array $a) => (int) $a['articulo_id'],
            $ajustes
        )));
        $preciosUltimaCompra = ArticuloPrecioUltimaCompraSupport::resolverPorArticulos($articuloIdsAjuste);

        $movimiento = MovimientoStock::create([
            'fecha' => $fecha,
            'fechajornada' => $fecha,
            'tipotransaccion_stock_id' => $tipoCabecera->id,
            'codigo' => $codigo,
            'leyenda' => $leyenda,
            'estado' => 'A',
            'usuario_id' => Auth::id(),
        ]);

        foreach ($ajustes as $ajuste) {
            $delta = (float) $ajuste['delta'];
            if (abs($delta) < 1e-9) {
                continue;
            }

            if ($tipoReverso) {
                $tipo = $tipoReverso;
                $cantidad = $delta;
            } else {
                $tipo = $delta > 0 ? $tipoPositivo : $tipoNegativo;
                $signoDb = (int) ($tipo->getAttributes()['signo'] ?? 0);
                $cantidad = ArticuloMovimientoCantidadSignoSupport::cantidadFirmadaSignoStock(abs($delta), $signoDb);
            }

            $articuloId = (int) $ajuste['articulo_id'];
            $precioUnitario = (float) ($preciosUltimaCompra[$articuloId]['precio'] ?? 0);
            [$colorMov, $talleMov] = ArticuloStockColorTalleSupport::valoresMovimiento(
                isset($ajuste['color_id']) ? (int) $ajuste['color_id'] : null,
                isset($ajuste['talle_id']) ? (int) $ajuste['talle_id'] : null
            );

            Articulo_Movimiento::create([
                'fecha' => $fecha,
                'fechajornada' => $fecha,
                'tipotransaccion_stock_id' => $tipo->id,
                'movimientostock_id' => $movimiento->id,
                'lote' => 0,
                'articulo_id' => $articuloId,
                'color_id' => $colorMov,
                'talle_id' => $talleMov,
                'concepto' => $ajuste['concepto'] ?? $tipo->nombre,
                'cantidad' => $cantidad,
                'precio' => $precioUnitario,
                'costo' => $precioUnitario,
                'deposito_id' => (int) $recuento->deposito_id,
            ]);

            ArticuloEmpresaAsignacionSupport::asignarSiVacia(
                $articuloId,
                (int) $recuento->empresa_id,
            );
        }

        return (int) $movimiento->id;
    }

    private function observacionCierre(?string $obs, string $modoCierre, string $mensajeBase): string
    {
        $texto = $mensajeBase.' (modo: '.RecuentoModoCierreSupport::etiqueta($modoCierre).')';
        if ($obs !== null && trim($obs) !== '') {
            $texto .= ' — '.trim($obs);
        }

        return $texto;
    }

}
