<?php

namespace App\Services\Stock;

use App\Models\Compras\Ordencompra;
use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\Depmae;
use App\Models\Stock\MovimientoStock;
use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Stock\Recepcion_Proveedor_Articulo;
use App\Models\Stock\Recepcion_Proveedor_Estado;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Repositories\Stock\Recepcion_ProveedorRepositoryInterface;
use App\Support\Compras\ArticuloProveedorPrecioListaSupport;
use App\Support\Stock\RecepcionProveedorDiferenciaSupport;
use App\Support\Stock\RecepcionProveedorDepositoSupport;
use App\Support\Stock\RecepcionProveedorArticuloProveedorSyncSupport;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Stock\RecepcionProveedorEstados;
use App\Services\Configuracion\ModuloAvisoService;
use Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RecepcionProveedorService
{
    public function __construct(
        private readonly Recepcion_ProveedorRepositoryInterface $repository,
        private readonly RecepcionProveedorOrdencompraResolverService $ocResolver,
        private readonly RecepcionProveedorAsientoService $asientoService,
        private readonly RecepcionProveedorAnitaBridgeService $anitaBridge,
        private readonly RecepcionProveedorParteUnicaService $parteUnicaService,
        private readonly ModuloAvisoService $moduloAvisoService,
        private readonly MovimientoStockService $movimientoStockService,
    ) {
    }

    public function listar(array $filtros = [])
    {
        return $this->repository->leeRecepciones($filtros, true);
    }

    public function buscar(int $id): Recepcion_Proveedor
    {
        return $this->repository->find($id);
    }

    /** @param array<string, mixed> $data */
    public function guardar(array $data): Recepcion_Proveedor
    {
        return DB::transaction(function () use ($data) {
            $ocData = $this->ocResolver->resolverPorId((int) $data['ordencompra_id']);
            $this->assertPeriodoContableRecepcion((int) $ocData['cabecera']->empresa_id, (string) ($data['fecha'] ?? ''));
            $oc = $ocData['cabecera'];
            $items = $data['items'] ?? [];
            $tipo = $data['tipo'] ?? Recepcion_Proveedor::TIPO_RECEPCION;

            $analisis = $this->procesarItems(
                $oc,
                $items,
                $tipo === Recepcion_Proveedor::TIPO_DEVOLUCION,
                $this->resolverDepositoCabecera($data['deposito_id'] ?? null)
            );
            $items = $analisis['items'];

            $primerItem = $items[0] ?? null;

            $recepcion = $this->repository->create([
                'ordencompra_id' => $oc->id,
                'tipo' => $tipo,
                'recepcion_referencia_id' => $data['recepcion_referencia_id'] ?? null,
                'empresa_id' => $oc->empresa_id,
                'proveedor_id' => $oc->proveedor_id,
                'deposito_id' => $this->resolverDepositoCabecera($data['deposito_id'] ?? null),
                'fecha' => $data['fecha'],
                'numerofactura' => $data['numerofactura'] ?? '',
                'moneda_id' => $primerItem['moneda_id'] ?? null,
                'cotizacion' => (float) ($primerItem['cotizacion'] ?? 1),
                'estado' => RecepcionProveedorEstados::BORRADOR,
                'fl_precio_diferencia' => $analisis['fl_precio_diferencia'],
                'fl_diferencia_cantidad' => $analisis['fl_diferencia_cantidad'],
                'fl_articulo_extra' => $analisis['fl_articulo_extra'],
                'fl_faltante_oc' => $analisis['fl_faltante_oc'],
                'fl_laboratorio' => $analisis['fl_laboratorio'],
                'comentario_precio' => $this->extraerComentariosPrecio($items),
                'resumen_diferencias' => $analisis['resumen_diferencias'] ?: null,
                'observacion' => $data['observacion'] ?? null,
                'origen_carga' => $data['origen_carga'] ?? 'MANUAL',
                'creousuario_id' => Auth::id(),
            ]);

            $this->reemplazarItems($recepcion, $items);
            $this->logEstado($recepcion, null, RecepcionProveedorEstados::BORRADOR, 'Alta de recepción');
            $recepcion = $recepcion->fresh(['recepcion_proveedor_articulos.articulos']);
            RecepcionProveedorArticuloProveedorSyncSupport::sincronizarDesdeRecepcion($recepcion, $items);

            return $recepcion;
        });
    }

    /** @param array<string, mixed> $data */
    public function actualizar(int $id, array $data): Recepcion_Proveedor
    {
        $recepcion = $this->repository->find($id);
        if ($recepcion->estado !== RecepcionProveedorEstados::BORRADOR) {
            throw new \RuntimeException('Solo se puede editar una recepción en BORRADOR.');
        }

        $data['ordencompra_id'] = $recepcion->ordencompra_id;

        return DB::transaction(function () use ($recepcion, $data) {
            $this->assertPeriodoContableRecepcion(
                (int) $recepcion->empresa_id,
                (string) ($data['fecha'] ?? $recepcion->fecha?->format('Y-m-d') ?? '')
            );

            $ocData = $this->ocResolver->resolverPorId((int) $data['ordencompra_id']);
            $oc = $ocData['cabecera'];
            $items = $data['items'] ?? [];
            $analisis = $this->procesarItems(
                $oc,
                $items,
                $recepcion->tipo === Recepcion_Proveedor::TIPO_DEVOLUCION,
                $this->resolverDepositoCabecera($data['deposito_id'] ?? null)
            );
            $items = $analisis['items'];
            $primerItem = $items[0] ?? null;

            $recepcion->update([
                'fecha' => $data['fecha'],
                'deposito_id' => $this->resolverDepositoCabecera($data['deposito_id'] ?? null),
                'numerofactura' => $data['numerofactura'] ?? '',
                'moneda_id' => $primerItem['moneda_id'] ?? $recepcion->moneda_id,
                'cotizacion' => (float) ($primerItem['cotizacion'] ?? 1),
                'fl_precio_diferencia' => $analisis['fl_precio_diferencia'],
                'fl_diferencia_cantidad' => $analisis['fl_diferencia_cantidad'],
                'fl_articulo_extra' => $analisis['fl_articulo_extra'],
                'fl_faltante_oc' => $analisis['fl_faltante_oc'],
                'fl_laboratorio' => $analisis['fl_laboratorio'],
                'comentario_precio' => $this->extraerComentariosPrecio($items),
                'resumen_diferencias' => $analisis['resumen_diferencias'] ?: null,
                'observacion' => $data['observacion'] ?? null,
            ]);

            $this->reemplazarItems($recepcion, $items);

            $recepcion = $recepcion->fresh(['recepcion_proveedor_articulos.articulos']);
            RecepcionProveedorArticuloProveedorSyncSupport::sincronizarDesdeRecepcion($recepcion, $items);

            return $recepcion;
        });
    }

    public function confirmar(int $id): Recepcion_Proveedor
    {
        $recepcion = $this->repository->find($id);
        if ($recepcion->estado !== RecepcionProveedorEstados::BORRADOR) {
            throw new \RuntimeException('Solo se puede confirmar una recepción en BORRADOR.');
        }

        if ($recepcion->recepcion_proveedor_articulos->isEmpty()) {
            throw new \RuntimeException('La recepción no tiene ítems.');
        }

        return DB::transaction(function () use ($recepcion) {
            $this->assertPeriodoContableRecepcion(
                (int) $recepcion->empresa_id,
                (string) ($recepcion->fecha?->format('Y-m-d') ?? '')
            );

            $movId = $this->generarMovimientoStock($recepcion);
            $asientoId = $this->asientoService->generarAsiento($recepcion);

            $estadoAnterior = $recepcion->estado;
            $recepcion->update([
                'estado' => RecepcionProveedorEstados::CONFIRMADA,
                'movimientostock_id' => $movId,
                'asiento_id' => $asientoId,
            ]);

            $this->logEstado($recepcion, $estadoAnterior, RecepcionProveedorEstados::CONFIRMADA, 'Confirmación de recepción');

            try {
                $recepcion = $recepcion->fresh();
                $this->anitaBridge->sincronizarRecepcion($recepcion);
                $this->parteUnicaService->generarYSincronizar($recepcion->fresh());
            } catch (\Throwable $e) {
                report($e);
            }

            if ($recepcion->fl_precio_diferencia) {
                $this->moduloAvisoService->enviar('stock', 'recepcion_proveedor_precio_diferencia', $recepcion->id);
            }
            if ($recepcion->fl_diferencia_cantidad) {
                $this->moduloAvisoService->enviar('stock', 'recepcion_proveedor_cantidad_diferencia', $recepcion->id);
            }
            if ($recepcion->fl_articulo_extra) {
                $this->moduloAvisoService->enviar('stock', 'recepcion_proveedor_articulo_extra', $recepcion->id);
            }
            if ($recepcion->fl_faltante_oc) {
                $this->moduloAvisoService->enviar('stock', 'recepcion_proveedor_faltante_oc', $recepcion->id);
            }
            if ($recepcion->fl_laboratorio) {
                $this->moduloAvisoService->enviar('stock', 'recepcion_proveedor_laboratorio', $recepcion->id);
            }

            RecepcionProveedorArticuloProveedorSyncSupport::sincronizarDesdeRecepcion(
                $recepcion->fresh(['recepcion_proveedor_articulos.articulos'])
            );

            return $recepcion->fresh();
        });
    }

    public function eliminarBorrador(int $id): void
    {
        $recepcion = $this->repository->find($id);

        if ($recepcion->estado !== RecepcionProveedorEstados::BORRADOR) {
            throw new \RuntimeException('Solo se puede eliminar una recepción en BORRADOR.');
        }

        if (DB::table('comprobante_proveedor_recepcion')->where('recepcion_proveedor_id', $id)->exists()) {
            throw new \RuntimeException('No se puede eliminar: la recepción está vinculada a un comprobante de proveedor.');
        }

        DB::transaction(function () use ($recepcion) {
            foreach ($recepcion->recepcion_proveedor_archivos as $archivo) {
                $ruta = (string) ($archivo->ruta ?? '');
                if ($ruta !== '' && Storage::disk('local')->exists($ruta)) {
                    Storage::disk('local')->delete($ruta);
                }
            }

            $recepcion->delete();
        });
    }

    public function anular(int $id, ?string $motivo = null): Recepcion_Proveedor
    {
        $recepcion = $this->repository->find($id);

        if ($recepcion->estado !== RecepcionProveedorEstados::CONFIRMADA) {
            throw new \RuntimeException('Solo se puede anular una recepción CONFIRMADA.');
        }

        $tieneDevoluciones = Recepcion_Proveedor::query()
            ->where('recepcion_referencia_id', $recepcion->id)
            ->where('estado', RecepcionProveedorEstados::CONFIRMADA)
            ->exists();

        if ($tieneDevoluciones) {
            throw new \RuntimeException('No se puede anular: existen devoluciones confirmadas contra esta recepción.');
        }

        return DB::transaction(function () use ($recepcion, $motivo) {
            $this->assertPeriodoContableRecepcion(
                (int) $recepcion->empresa_id,
                (string) ($recepcion->fecha?->format('Y-m-d') ?? '')
            );

            $estadoAnterior = $recepcion->estado;

            if ($recepcion->movimientostock_id) {
                $this->movimientoStockService->borraMovimientoStock((int) $recepcion->movimientostock_id);
            }

            $this->asientoService->anularAsiento($recepcion);

            try {
                $this->anitaBridge->anularRecepcion($recepcion->fresh([
                    'proveedores', 'empresas', 'ordencompras',
                    'recepcion_proveedor_articulos.articulos',
                    'recepcion_proveedor_partes_unicas.recepcion_proveedor_articulos.articulos',
                ]));
            } catch (\Throwable $e) {
                report($e);
            }

            $recepcion->recepcion_proveedor_partes_unicas()->delete();

            $recepcion->update([
                'estado' => RecepcionProveedorEstados::ANULADA,
                'movimientostock_id' => null,
                'asiento_id' => null,
            ]);

            $this->logEstado(
                $recepcion,
                $estadoAnterior,
                RecepcionProveedorEstados::ANULADA,
                $motivo ?: 'Anulación de recepción'
            );

            return $recepcion->fresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function crearDevolucion(int $recepcionOrigenId, array $data): Recepcion_Proveedor
    {
        $origen = $this->repository->find($recepcionOrigenId);
        if ($origen->estado !== RecepcionProveedorEstados::CONFIRMADA) {
            throw new \RuntimeException('Solo se puede devolver contra una recepción confirmada.');
        }

        $this->validarCantidadesDevolucion($origen, $data['items'] ?? []);

        $data['tipo'] = Recepcion_Proveedor::TIPO_DEVOLUCION;
        $data['recepcion_referencia_id'] = $origen->id;
        $data['ordencompra_id'] = $origen->ordencompra_id;
        $data['empresa_id'] = $origen->empresa_id;
        $data['proveedor_id'] = $origen->proveedor_id;
        $data['centrocosto_id'] = $origen->centrocosto_id;
        $data['moneda_id'] = $origen->moneda_id;
        if (! isset($data['deposito_id']) && $origen->deposito_id) {
            $data['deposito_id'] = $origen->deposito_id;
        }

        $devolucion = $this->guardar($data);

        return $this->confirmar($devolucion->id);
    }

    /** @param list<array<string, mixed>> $items */
    private function validarCantidadesDevolucion(Recepcion_Proveedor $origen, array $items): void
    {
        $recibidoPorLinea = [];
        foreach ($origen->recepcion_proveedor_articulos as $linea) {
            $key = (int) $linea->articulo_id.'-'.(int) ($linea->ordencompra_articulo_id ?? 0);
            $recibidoPorLinea[$key] = ($recibidoPorLinea[$key] ?? 0) + (float) $linea->cantidad;
        }

        foreach ($items as $idx => $item) {
            $key = (int) ($item['articulo_id'] ?? 0).'-'.(int) ($item['ordencompra_articulo_id'] ?? 0);
            $max = $recibidoPorLinea[$key] ?? 0;
            $cant = (float) ($item['cantidad'] ?? 0);
            if ($cant > $max + 0.000001) {
                throw new \RuntimeException(
                    'Línea '.($idx + 1).": cantidad a devolver ({$cant}) supera lo recepcionado ({$max})."
                );
            }
        }
    }

    public function precargaDesdeOc(int $numeroOc): array
    {
        return $this->ocResolver->resolverPorNumeroOc($numeroOc, (int) (Auth::id() ?? 0));
    }

    /** @param list<array<string, mixed>> $items */
    private function procesarItems(
        Ordencompra $oc,
        array $items,
        bool $omitirFaltantes = false,
        ?int $depositoCabeceraId = null
    ): array {
        if ($items === []) {
            throw new \RuntimeException('Debe cargar al menos un ítem.');
        }

        $empresaId = (int) $oc->empresa_id;
        $proveedorId = (int) $oc->proveedor_id;

        RecepcionProveedorDepositoSupport::reiniciarCache();

        if ($depositoCabeceraId !== null) {
            $this->validarDepositoAutorizado($depositoCabeceraId, $empresaId, 'Depósito general de entrada');
        }

        $articuloIds = array_values(array_unique(array_map(
            static fn (array $item): int => (int) ($item['articulo_id'] ?? 0),
            $items
        )));
        $articulos = Articulo::query()->whereIn('id', $articuloIds)->get()->keyBy('id');

        $depositoIds = [];
        if ($depositoCabeceraId !== null) {
            $depositoIds[] = $depositoCabeceraId;
        }
        foreach ($articulos as $articulo) {
            $depArt = (int) ($articulo->depositoentrega_id ?? 0);
            if ($depArt > 0) {
                $depositoIds[] = $depArt;
            }
        }
        $depositos = Depmae::query()->whereIn('id', array_unique($depositoIds))->get()->keyBy('id');

        foreach ($items as $idx => &$item) {
            if ((float) ($item['cantidad'] ?? 0) <= 0) {
                throw new \RuntimeException('Línea '.($idx + 1).': cantidad inválida.');
            }

            $articuloId = (int) ($item['articulo_id'] ?? 0);
            $articulo = $articulos->get($articuloId);
            if ($articulo === null) {
                throw new \RuntimeException('Línea '.($idx + 1).': artículo inexistente.');
            }

            $depositoLineaId = RecepcionProveedorDepositoSupport::resolverDepositoLinea($depositoCabeceraId, $articulo);
            $usaDepositoArticulo = $depositoCabeceraId === null;

            $this->validarDepositoAutorizado($depositoLineaId, $empresaId, 'Línea '.($idx + 1));

            $deposito = $depositos->get($depositoLineaId);
            if ($deposito === null) {
                $deposito = Depmae::query()->find($depositoLineaId);
                if ($deposito === null) {
                    throw new \RuntimeException('Línea '.($idx + 1).': depósito destino inexistente.');
                }
                $depositos->put($depositoLineaId, $deposito);
            }

            $coefProveedor = RecepcionProveedorDepositoSupport::coeficienteProveedor($articuloId, $proveedorId);
            $conversion = RecepcionProveedorDepositoSupport::calcularConversionStock(
                $articulo,
                $deposito,
                (float) $item['cantidad'],
                (float) $item['precio'],
                $coefProveedor,
                $usaDepositoArticulo,
                $empresaId
            );

            $item['deposito_id'] = $depositoLineaId;
            $item['coeficiente_proveedor'] = $coefProveedor;
            $item['coeficienteconversion'] = $conversion['coeficienteconversion'];
            $item['cantidad_stock'] = $conversion['cantidad_stock'];
            $item['precio_stock'] = $conversion['precio_stock'];
            $item['fl_conversion_formula'] = $conversion['fl_conversion_formula'];
            $item['articulo_stock_id'] = $conversion['articulo_stock_id'];
            $item['articulo_stock_sku'] = $conversion['articulo_stock_sku'];
            $item['deposito_nombre'] = $deposito->nombre ?? '';
            $item['unidadmedida_id'] = (int) ($item['unidadmedida_id'] ?? $articulo->unidadmedida_id ?? 1) ?: 1;
        }
        unset($item);

        $oc->loadMissing('ordencompra_articulos.articulos');

        return RecepcionProveedorDiferenciaSupport::analizar($oc, $items, $omitirFaltantes);
    }

    private function resolverDepositoCabecera(mixed $depositoId): ?int
    {
        $id = (int) $depositoId;

        return $id > 0 ? $id : null;
    }

    private function validarDepositoAutorizado(int $depositoId, int $empresaId, string $contexto): void
    {
        if (! Depmae::autorizadoParaUsuarioYEmpresa($depositoId, $empresaId)) {
            throw new \RuntimeException("{$contexto}: depósito {$depositoId} no autorizado para su usuario o empresa.");
        }
    }

    /** @param list<array<string, mixed>> $items */
    private function extraerComentariosPrecio(array $items): ?string
    {
        $comentarios = [];
        foreach ($items as $item) {
            if (! empty($item['fl_precio_diferencia']) && ! empty($item['comentario_precio'])) {
                $comentarios[] = $item['comentario_precio'];
            }
        }

        return $comentarios !== [] ? implode("\n", $comentarios) : null;
    }

    /** @param list<array<string, mixed>> $items */
    private function reemplazarItems(Recepcion_Proveedor $recepcion, array $items): void
    {
        Recepcion_Proveedor_Articulo::where('recepcion_proveedor_id', $recepcion->id)->delete();

        $orden = 1;
        foreach ($items as $item) {
            if ((float) ($item['cantidad'] ?? 0) <= 0) {
                continue;
            }

            Recepcion_Proveedor_Articulo::create([
                'recepcion_proveedor_id' => $recepcion->id,
                'ordencompra_articulo_id' => $item['ordencompra_articulo_id'] ?? null,
                'ordencompra_articulo_sustituido_id' => $item['ordencompra_articulo_sustituido_id'] ?? null,
                'tipo_linea' => $item['tipo_linea'] ?? RecepcionProveedorDiferenciaSupport::TIPO_OC,
                'orden' => $orden,
                'penvp_orden' => $item['penvp_orden'] ?? $orden,
                'articulo_id' => $item['articulo_id'],
                'articulo_stock_id' => $item['articulo_stock_id'] ?? null,
                'cantidad' => $item['cantidad'],
                'cantidad_oc' => $item['cantidad_oc'] ?? null,
                'cantidad_stock' => $item['cantidad_stock'] ?? $item['cantidad'],
                'unidadmedida_id' => $item['unidadmedida_id'] ?? 1,
                'coeficienteconversion' => $item['coeficienteconversion'] ?? 1,
                'precio' => $item['precio'],
                'precio_ordencompra' => $item['precio_ordencompra'] ?? $item['precio'],
                'precio_stock' => $item['precio_stock'] ?? $item['precio'],
                'fl_precio_diferencia' => ! empty($item['fl_precio_diferencia']),
                'fl_cantidad_diferencia' => ! empty($item['fl_cantidad_diferencia']),
                'fl_articulo_distinto' => ! empty($item['fl_articulo_distinto']),
                'comentario_precio' => $item['comentario_precio'] ?? null,
                'comentario_diferencia' => $item['comentario_diferencia'] ?? null,
                'precio_lista_proveedor' => $item['precio_lista_proveedor'] ?? null,
                'moneda_id' => $item['moneda_id'],
                'cotizacion' => $item['cotizacion'] ?? 1,
                'descuento' => $item['descuento'] ?? 0,
                'deposito_id' => $item['deposito_id'],
                'detalle' => $item['detalle'] ?? null,
                'estado' => 'ACTIVO',
                'impuesto_id' => $item['impuesto_id'] ?? null,
                'incluyeimpuesto' => 'N',
                'centrocosto_id' => $item['centrocosto_id'],
                'lote_id' => $item['lote_id'] ?? null,
            ]);
            $orden++;
        }
    }

    private function generarMovimientoStock(Recepcion_Proveedor $recepcion): int
    {
        $abrev = $recepcion->tipo === Recepcion_Proveedor::TIPO_DEVOLUCION ? 'RCDEV' : 'RCING';
        $tipoStock = Tipotransaccion_Stock::where('abreviatura', $abrev)->first();
        if (! $tipoStock) {
            throw new \RuntimeException("Tipo transacción stock {$abrev} no configurado.");
        }

        $signo = (int) $tipoStock->signo;
        $concepto = $recepcion->tipo === Recepcion_Proveedor::TIPO_DEVOLUCION
            ? 'Devolución a proveedor '.$recepcion->numerorecepcion
            : 'Recepción proveedor '.$recepcion->numerorecepcion;

        $mov = MovimientoStock::create([
            'fecha' => $recepcion->fecha->format('Y-m-d'),
            'fechajornada' => $recepcion->fecha->format('Y-m-d'),
            'tipotransaccion_stock_id' => $tipoStock->id,
            'codigo' => substr($recepcion->numerorecepcion, 0, 10),
            'leyenda' => $concepto,
            'estado' => 'A',
            'usuario_id' => Auth::id(),
        ]);

        foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
            $cantidadStock = (float) ($linea->cantidad_stock ?: $linea->cantidad);
            $cantidadFirmada = abs($cantidadStock) * $signo;

            $precioMovimiento = (float) ($linea->precio_stock ?? 0);
            if ($precioMovimiento <= 0) {
                $precioMovimiento = (float) $linea->precio;
            }

            $articuloMovimientoId = (int) ($linea->articulo_stock_id ?? 0) > 0
                ? (int) $linea->articulo_stock_id
                : (int) $linea->articulo_id;

            $am = Articulo_Movimiento::create([
                'fecha' => $recepcion->fecha->format('Y-m-d'),
                'fechajornada' => $recepcion->fecha->format('Y-m-d'),
                'tipotransaccion_stock_id' => $tipoStock->id,
                'movimientostock_id' => $mov->id,
                'articulo_id' => $articuloMovimientoId,
                'concepto' => $concepto,
                'cantidad' => $cantidadFirmada,
                'precio' => $precioMovimiento,
                'costo' => $precioMovimiento,
                'descuento' => $linea->descuento,
                'moneda_id' => $linea->moneda_id,
                'incluyeimpuesto' => 'N',
                'deposito_id' => $linea->deposito_id,
                'lote' => 0,
            ]);

            $linea->update(['articulo_movimiento_id' => $am->id]);
        }

        return (int) $mov->id;
    }

    private function assertPeriodoContableRecepcion(int $empresaId, string $fecha): void
    {
        if ($empresaId <= 0 || $fecha === '') {
            return;
        }

        PeriodoContableCierreSupport::assertOperacionPermitida(
            $empresaId,
            $fecha,
            PeriodoContableCierreSupport::ALCANCE_RECEPCION_PROVEEDOR
        );
    }

    private function logEstado(
        Recepcion_Proveedor $recepcion,
        ?string $estadoAnterior,
        string $estadoNuevo,
        string $observacion
    ): void {
        Recepcion_Proveedor_Estado::create([
            'recepcion_proveedor_id' => $recepcion->id,
            'estado' => $estadoNuevo,
            'fecha' => now(),
            'usuario_id' => Auth::id(),
            'observacion' => $observacion,
        ]);
    }

}
