<?php

namespace App\Services\Sueldos;

use App\Models\Stock\Depmae;
use App\Models\Sueldos\Configuracion_Indumentaria_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Entrega_Prenda_Articulo_Sueldos;
use App\Models\Sueldos\Entrega_Prenda_Sueldos;
use App\Models\Sueldos\Prenda_Agrupamiento_Sueldos;
use App\Models\Sueldos\Prenda_Articulo_Sueldos;
use App\Services\Stock\MovimientoStockService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Entrega de indumentaria a empleados. Descuenta stock y genera el asiento contable
 * reutilizando el circuito de "movimientos de stock" (MovimientoStockService), mediante
 * un tipo de transacción de stock propio configurado para indumentaria.
 */
class EntregaPrendaService
{
    public function __construct(
        private MovimientoStockService $movimientoStockService,
    ) {}

    public function configuracion(): Configuracion_Indumentaria_Sueldos
    {
        return Configuracion_Indumentaria_Sueldos::actual();
    }

    /**
     * Dotación del empleado (según agrupamiento y sexo) con lo entregado y el saldo del año.
     *
     * @return array{
     *   sin_agrupamiento: bool,
     *   sexo: string,
     *   anio: int,
     *   prendas: array<int, array{prenda_id:int, codigo:int, descripcion:string, color:?string, limite:float, entregado:float, saldo:float}>
     * }
     */
    public function resumenEmpleado(Empleado_Sueldos $empleado, ?int $anio = null): array
    {
        $anio = $anio ?: (int) date('Y');
        $sexo = Prenda_Agrupamiento_Sueldos::sexoDesdeEmpleado($empleado->sexo);
        $agrupamientoId = (int) ($empleado->agrupamiento_id ?? 0);

        $prendas = [];
        if ($agrupamientoId > 0) {
            $dotacion = Prenda_Agrupamiento_Sueldos::query()
                ->with(['prenda:id,codigo,descripcion,vida_util_meses,es_seguridad,requiere_certificacion,norma', 'color:id,nombre'])
                ->where('agrupamiento_id', $agrupamientoId)
                ->where('sexo', $sexo)
                ->orderBy('orden')->orderBy('id')
                ->get();

            $agrupadas = [];
            foreach ($dotacion as $fila) {
                $pid = (int) $fila->prenda_id;
                if (! isset($agrupadas[$pid])) {
                    $agrupadas[$pid] = [
                        'prenda_id' => $pid,
                        'codigo' => (int) ($fila->prenda->codigo ?? 0),
                        'descripcion' => (string) ($fila->prenda->descripcion ?? ''),
                        'color' => $fila->color->nombre ?? null,
                        'vida_util_meses' => (int) ($fila->prenda->vida_util_meses ?? 0),
                        'es_seguridad' => (bool) ($fila->prenda->es_seguridad ?? false),
                        'norma' => $fila->prenda->norma ?? null,
                        'modo' => ((int) ($fila->prenda->vida_util_meses ?? 0)) > 0 ? 'vencimiento' : 'anual',
                        'limite' => 0.0,
                        'entregado' => 0.0,
                        'saldo' => 0.0,
                        'proximo_vencimiento' => null,
                    ];
                }
                $agrupadas[$pid]['limite'] += (float) $fila->limite_anual;
            }

            foreach ($agrupadas as $pid => &$row) {
                if ($row['modo'] === 'vencimiento') {
                    $row['entregado'] = $this->entregadoVigente((int) $empleado->id, $pid);
                    $row['proximo_vencimiento'] = $this->proximoVencimiento((int) $empleado->id, $pid);
                } else {
                    $row['entregado'] = $this->contarEntregado((int) $empleado->id, $pid, $anio);
                }
                $row['saldo'] = round($row['limite'] - $row['entregado'], 3);
            }
            unset($row);

            $prendas = array_values($agrupadas);
        }

        return [
            'sin_agrupamiento' => $agrupamientoId === 0,
            'sexo' => $sexo,
            'anio' => $anio,
            'prendas' => $prendas,
        ];
    }

    public function contarEntregado(int $empleadoId, int $prendaId, int $anio): float
    {
        return (float) Entrega_Prenda_Articulo_Sueldos::query()
            ->join('entrega_prenda_sueldos as e', 'e.id', '=', 'entrega_prenda_articulo_sueldos.entrega_id')
            ->where('e.empleado_id', $empleadoId)
            ->where('e.anio', $anio)
            ->where('entrega_prenda_articulo_sueldos.prenda_id', $prendaId)
            ->sum('entrega_prenda_articulo_sueldos.cantidad');
    }

    /**
     * Cantidad de prendas todavía vigentes (no vencidas) que tiene el empleado.
     * Se usa para prendas EPP con vida útil: se repone cuando la anterior vence.
     */
    public function entregadoVigente(int $empleadoId, int $prendaId, ?string $fechaRef = null): float
    {
        $ref = $fechaRef ? Carbon::parse($fechaRef)->toDateString() : Carbon::today()->toDateString();

        return (float) Entrega_Prenda_Articulo_Sueldos::query()
            ->join('entrega_prenda_sueldos as e', 'e.id', '=', 'entrega_prenda_articulo_sueldos.entrega_id')
            ->where('e.empleado_id', $empleadoId)
            ->where('entrega_prenda_articulo_sueldos.prenda_id', $prendaId)
            ->where(function ($q) use ($ref) {
                $q->whereNull('entrega_prenda_articulo_sueldos.vence_el')
                    ->orWhere('entrega_prenda_articulo_sueldos.vence_el', '>=', $ref);
            })
            ->sum('entrega_prenda_articulo_sueldos.cantidad');
    }

    public function proximoVencimiento(int $empleadoId, int $prendaId): ?string
    {
        $fecha = Entrega_Prenda_Articulo_Sueldos::query()
            ->join('entrega_prenda_sueldos as e', 'e.id', '=', 'entrega_prenda_articulo_sueldos.entrega_id')
            ->where('e.empleado_id', $empleadoId)
            ->where('entrega_prenda_articulo_sueldos.prenda_id', $prendaId)
            ->whereNotNull('entrega_prenda_articulo_sueldos.vence_el')
            ->where('entrega_prenda_articulo_sueldos.vence_el', '>=', Carbon::today()->toDateString())
            ->min('entrega_prenda_articulo_sueldos.vence_el');

        return $fecha ? substr((string) $fecha, 0, 10) : null;
    }

    /**
     * Registra una entrega: descuenta stock + asiento (vía movimientos de stock) y graba el ledger.
     *
     * @param  array<int, array{prenda_articulo_id:int, cantidad:float}>  $lineas
     * @return Entrega_Prenda_Sueldos
     */
    public function registrar(
        Empleado_Sueldos $empleado,
        array $lineas,
        ?string $fecha,
        ?string $observacion,
        ?int $usuarioId,
        bool $omitirCupo = false,
    ): Entrega_Prenda_Sueldos {
        $config = $this->configuracion();
        if (! $config->estaCompleta()) {
            throw new \RuntimeException('Configure el depósito de origen y el tipo de transacción de indumentaria antes de entregar prendas.');
        }

        $deposito = Depmae::query()->find($config->deposito_id);
        if ($deposito === null) {
            throw new \RuntimeException('El depósito de origen configurado no existe.');
        }

        $fechaCarbon = $fecha ? Carbon::parse($fecha) : Carbon::today();
        $anio = (int) $fechaCarbon->year;
        $sexo = Prenda_Agrupamiento_Sueldos::sexoDesdeEmpleado($empleado->sexo);

        // Resolver líneas -> artículos (SKU) del maestro de stock.
        $detalle = [];
        $cantidadPorPrenda = [];
        foreach ($lineas as $l) {
            $varianteId = (int) ($l['prenda_articulo_id'] ?? 0);
            $cantidad = round((float) ($l['cantidad'] ?? 0), 3);
            if ($varianteId <= 0 || $cantidad <= 0) {
                continue;
            }
            $variante = Prenda_Articulo_Sueldos::query()->with('prenda:id,vida_util_meses')->find($varianteId);
            if ($variante === null) {
                throw new \RuntimeException('Variante de prenda inexistente.');
            }
            if ((int) $variante->articulo_id <= 0) {
                throw new \RuntimeException('La variante seleccionada no tiene un artículo (SKU) asociado; cárguelo en la prenda.');
            }
            $vidaUtil = (int) ($variante->prenda->vida_util_meses ?? 0);
            $venceEl = $vidaUtil > 0 ? $fechaCarbon->copy()->addMonthsNoOverflow($vidaUtil)->toDateString() : null;
            $detalle[] = [
                'variante' => $variante,
                'cantidad' => $cantidad,
                'vence_el' => $venceEl,
            ];
            $cantidadPorPrenda[(int) $variante->prenda_id] = ($cantidadPorPrenda[(int) $variante->prenda_id] ?? 0) + $cantidad;
        }

        if ($detalle === []) {
            throw new \RuntimeException('Debe indicar al menos una prenda con cantidad mayor a cero.');
        }

        if (! $omitirCupo) {
            $this->validarCupo($empleado, (int) ($empleado->agrupamiento_id ?? 0), $sexo, $anio, $cantidadPorPrenda);
        }

        $empresaId = (int) ($deposito->empresa_id ?? $empleado->empresa_id ?? 0);

        $articulosId = [];
        $cantidades = [];
        foreach ($detalle as $d) {
            $articulosId[] = (int) $d['variante']->articulo_id;
            $cantidades[] = $d['cantidad'];
        }

        return DB::transaction(function () use (
            $empleado, $config, $deposito, $empresaId, $fechaCarbon, $anio,
            $articulosId, $cantidades, $detalle, $observacion, $usuarioId
        ) {
            // Descuento de stock + asiento contable reutilizando movimientos de stock.
            $payload = [
                'tipotransaccion_stock_id' => (int) $config->tipotransaccion_stock_id,
                'fecha' => $fechaCarbon->toDateString(),
                'deposito_id' => (int) $config->deposito_id,
                'empresa_id' => $empresaId,
                'centrocosto_destino_id' => (int) ($config->centrocosto_id ?: $empleado->centrocosto_id ?: 0) ?: null,
                'lote' => ' ',
                'loteimportacion_id' => null,
                'leyenda' => 'Entrega indumentaria - Legajo '.$empleado->legajo.' '.$empleado->nombre,
                'articulos_id' => $articulosId,
                'cantidades' => $cantidades,
            ];

            $resultado = $this->movimientoStockService->guardaMovimientoStock($payload, 'create');
            $movimientostockId = is_array($resultado) ? (int) ($resultado['id'] ?? 0) : 0;

            $entrega = Entrega_Prenda_Sueldos::create([
                'empleado_id' => (int) $empleado->id,
                'fecha' => $fechaCarbon->toDateString(),
                'anio' => $anio,
                'deposito_id' => (int) $config->deposito_id,
                'tipotransaccion_stock_id' => (int) $config->tipotransaccion_stock_id,
                'movimientostock_id' => $movimientostockId ?: null,
                'observacion' => $observacion ? mb_substr(trim($observacion), 0, 255) : null,
                'usuario_id' => $usuarioId,
            ]);

            foreach ($detalle as $d) {
                $v = $d['variante'];
                Entrega_Prenda_Articulo_Sueldos::create([
                    'entrega_id' => $entrega->id,
                    'prenda_id' => (int) $v->prenda_id,
                    'prenda_articulo_id' => (int) $v->id,
                    'color_id' => $v->color_id,
                    'talle_id' => $v->talle_id,
                    'articulo_id' => $v->articulo_id,
                    'sku' => $v->sku,
                    'cantidad' => $d['cantidad'],
                    'vence_el' => $d['vence_el'] ?? null,
                ]);
            }

            return $entrega->fresh(['articulos.prenda', 'articulos.color', 'articulos.talle']);
        });
    }

    public function anular(Entrega_Prenda_Sueldos $entrega): void
    {
        DB::transaction(function () use ($entrega) {
            $movId = (int) ($entrega->movimientostock_id ?? 0);
            if ($movId > 0) {
                // Revierte stock y asiento (borra el movimiento de stock asociado).
                $this->movimientoStockService->borraMovimientoStock($movId);
            }
            $entrega->articulos()->delete();
            $entrega->delete();
        });
    }

    /**
     * @param  array<int, float>  $cantidadPorPrenda
     */
    private function validarCupo(Empleado_Sueldos $empleado, int $agrupamientoId, string $sexo, int $anio, array $cantidadPorPrenda): void
    {
        if ($agrupamientoId <= 0) {
            return;
        }

        $limites = Prenda_Agrupamiento_Sueldos::query()
            ->selectRaw('prenda_id, SUM(limite_anual) as limite')
            ->where('agrupamiento_id', $agrupamientoId)
            ->where('sexo', $sexo)
            ->groupBy('prenda_id')
            ->pluck('limite', 'prenda_id')
            ->all();

        foreach ($cantidadPorPrenda as $prendaId => $cantidad) {
            if (! array_key_exists($prendaId, $limites)) {
                // Sin dotación definida para esta prenda/sexo: no hay tope configurado.
                continue;
            }
            $limite = (float) $limites[$prendaId];
            if ($limite <= 0) {
                continue;
            }
            $prenda = \App\Models\Sueldos\Prenda_Sueldos::query()->find($prendaId);
            $vidaUtil = (int) ($prenda->vida_util_meses ?? 0);

            if ($vidaUtil > 0) {
                $entregado = $this->entregadoVigente((int) $empleado->id, (int) $prendaId);
                $ventana = 'vigentes';
            } else {
                $entregado = $this->contarEntregado((int) $empleado->id, (int) $prendaId, $anio);
                $ventana = 'año '.$anio;
            }

            if ($entregado + $cantidad > $limite + 1e-6) {
                $nombre = $prenda ? ($prenda->codigo.' - '.$prenda->descripcion) : ('prenda ID '.$prendaId);
                throw new \RuntimeException(
                    'Supera el cupo de '.$nombre.' ('.$ventana.'). Tope: '.$this->fmt($limite)
                    .', ya vigentes: '.$this->fmt($entregado).', solicitado: '.$this->fmt($cantidad).'.'
                );
            }
        }
    }

    private function fmt(float $v): string
    {
        $t = number_format($v, 3, ',', '.');

        return rtrim(rtrim($t, '0'), ',');
    }
}
