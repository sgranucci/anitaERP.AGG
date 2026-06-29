<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\Articulo_Saldo_Deposito;
use App\Models\Stock\Depmae;
use App\Support\Ventas\GastronomiaVentaDetalleSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class RecuentoMovimientosArticuloSupport
{
    public static function esModoTodosDepositos(int $depositoId): bool
    {
        return $depositoId <= 0;
    }

    public static function resolverDepositoIdDesdeRequest(mixed $valor): int
    {
        if ($valor === null || $valor === '' || $valor === 'todos') {
            return 0;
        }

        return max(0, (int) $valor);
    }

    /**
     * @return array{
     *     articulo: array{id:int, sku:string, descripcion:string, unidad_medida:string, unidad_medida_abreviatura:string, unidad_medida_nombre:string},
     *     deposito: array{id:int, codigo:string, nombre:string, empresa_nombre?:string},
     *     modo_todos_depositos: bool,
     *     saldo: float,
     *     saldo_fmt: string
     * }
     */
    public static function validarContexto(int $articuloId, int $depositoId, ?int $empresaId = null): array
    {
        if ($articuloId <= 0) {
            throw new \InvalidArgumentException('Artículo requerido.');
        }

        $empresaId = (int) ($empresaId ?? 0);
        $empresaId = $empresaId > 0 ? $empresaId : null;

        $articulo = Articulo::query()
            ->select('id', 'sku', 'descripcion', 'unidadmedida_id')
            ->with('unidadesdemedidas:id,nombre,abreviatura')
            ->find($articuloId);
        if (! $articulo) {
            throw new \RuntimeException('Artículo no encontrado.');
        }

        $articuloResumen = MovimientosArticuloDepositoSupport::articuloResumen($articulo);

        $modoTodos = self::esModoTodosDepositos($depositoId);

        if ($modoTodos) {
            $nombreDeposito = 'Todos los depósitos';
            if ($empresaId !== null) {
                $empresaNombre = DB::table('empresa')->where('id', $empresaId)->value('nombre');
                if ($empresaNombre) {
                    $nombreDeposito .= ' — '.$empresaNombre;
                }
            }

            return [
                'articulo' => $articuloResumen,
                'deposito' => [
                    'id' => 0,
                    'codigo' => '',
                    'nombre' => $nombreDeposito,
                ],
                'modo_todos_depositos' => true,
                'empresa_id' => $empresaId,
                'saldo' => ($saldoTodos = self::saldoTodosDepositos($articuloId, $empresaId)),
                'saldo_fmt' => self::formatearNumero($saldoTodos),
            ];
        }

        $deposito = Depmae::query()
            ->select('id', 'codigo', 'nombre', 'empresa_id')
            ->with('empresas:id,nombre')
            ->find($depositoId);
        if (! $deposito) {
            throw new \RuntimeException('Depósito no encontrado.');
        }
        if (! MovimientosArticuloDepositoSupport::depositoConsultable((int) $deposito->id, $empresaId)) {
            throw new \RuntimeException('Depósito no autorizado para su usuario o empresa.');
        }

        $saldoRepo = app(\App\Repositories\Stock\Articulo_Saldo_DepositoRepositoryInterface::class);
        $saldo = $saldoRepo->saldo($articuloId, $depositoId);

        return [
            'articulo' => $articuloResumen,
            'deposito' => [
                'id' => (int) $deposito->id,
                'codigo' => (string) ($deposito->codigo ?? ''),
                'nombre' => (string) ($deposito->nombre ?? ''),
                'empresa_nombre' => (string) (optional($deposito->empresas)->nombre ?? ''),
            ],
            'modo_todos_depositos' => false,
            'empresa_id' => $empresaId,
            'saldo' => $saldo,
            'saldo_fmt' => self::formatearNumero($saldo),
        ];
    }

    public static function query(int $articuloId, int $depositoId, ?int $empresaId = null): Builder
    {
        $empresaId = (int) ($empresaId ?? 0);
        $empresaId = $empresaId > 0 ? $empresaId : null;

        $query = self::queryBase($articuloId)
            ->where('am.articulo_id', $articuloId)
            ->whereNull('am.deleted_at');

        if (self::esModoTodosDepositos($depositoId)) {
            self::aplicarFiltroDepositosAutorizados($query, $empresaId);

            return $query
                ->orderByDesc('am.fecha')
                ->orderByDesc('am.id');
        }

        return $query
            ->where('am.deposito_id', $depositoId)
            ->orderByDesc('am.fecha')
            ->orderByDesc('am.id');
    }

    /**
     * @param  object|\ArrayAccess<string, mixed>  $row
     */
    public static function enriquecerFila(object $row, bool $incluirDeposito = false): object
    {
        $cantidad = (float) ($row->cantidad ?? 0);

        $row->entrada = $cantidad > 0 ? $cantidad : null;
        $row->salida = $cantidad < 0 ? abs($cantidad) : null;
        $row->entrada_fmt = $row->entrada !== null ? self::formatearNumero((float) $row->entrada) : '';
        $row->salida_fmt = $row->salida !== null ? self::formatearNumero((float) $row->salida) : '';

        $tipoVenta = trim((string) ($row->tipo_venta_abreviatura ?? $row->tipo_venta_nombre ?? ''));
        if (! empty($row->venta_id) && $tipoVenta !== '') {
            $row->tipo = $tipoVenta;
        } else {
            $row->tipo = trim((string) ($row->tipo_abreviatura ?: $row->tipo_nombre ?: '—'));
        }

        $row->concepto_display = self::resolverConceptoDisplay($row);
        $row->nombreempresa = trim((string) ($row->empresa_nombre ?? ''));

        if ($incluirDeposito) {
            $row->deposito_etiqueta = self::etiquetaDepositoConEmpresa([
                'id' => (int) ($row->deposito_id ?? 0),
                'codigo' => (string) ($row->deposito_codigo ?? ''),
                'nombre' => (string) ($row->deposito_nombre ?? ''),
            ], (string) ($row->empresa_nombre ?? ''));
        }

        $row = ArticuloMovimientoPrecioHistoricoSupport::enriquecerPrecioDisplay($row);

        return KardexMovimientoComprobanteSupport::enriquecerFila($row);
    }

    /**
     * @param  object|\ArrayAccess<string, mixed>  $row
     */
    public static function resolverConceptoDisplay(object $row): string
    {
        $concepto = trim((string) ($row->concepto ?? ''));
        $ventaCodigo = trim((string) ($row->venta_codigo ?? ''));

        if ($ventaCodigo !== '') {
            if (GastronomiaVentaDetalleSupport::conceptoEsMovimientoInsumo($concepto)) {
                return $ventaCodigo.GastronomiaVentaDetalleSupport::SUFIJO_CONCEPTO_INSUMO;
            }

            return $ventaCodigo;
        }

        if ($concepto !== '') {
            return $concepto;
        }

        return '—';
    }

    public static function formatearNumero(float $n): string
    {
        if (abs($n - round($n)) < 1e-9) {
            return (string) (int) round($n);
        }

        return rtrim(rtrim(number_format($n, 6, '.', ''), '0'), '.');
    }

    public static function etiquetaDeposito(array $deposito): string
    {
        return Depmae::etiquetaDesdePartes(
            (string) ($deposito['codigo'] ?? ''),
            (string) ($deposito['nombre'] ?? ''),
            (int) ($deposito['id'] ?? 0)
        );
    }

    public static function etiquetaDepositoConEmpresa(array $deposito, ?string $empresaNombre = null): string
    {
        $etiqueta = self::etiquetaDeposito($deposito);
        if (! MovimientosArticuloDepositoSupport::mostrarEmpresaEnListados()) {
            return $etiqueta;
        }

        $empresa = trim((string) ($empresaNombre ?? $deposito['empresa_nombre'] ?? ''));
        if ($empresa === '') {
            return $etiqueta;
        }

        return $etiqueta.' ('.$empresa.')';
    }

    private static function queryBase(int $articuloId): Builder
    {
        return Articulo_Movimiento::query()
            ->from('articulo_movimiento as am')
            ->select([
                'am.id',
                'am.fecha',
                'am.cantidad',
                'am.precio',
                'am.costo',
                'am.concepto',
                'am.venta_id',
                'am.deposito_id',
                'am.movimientostock_id',
                'dep.codigo AS deposito_codigo',
                'dep.nombre AS deposito_nombre',
                'emp.nombre AS empresa_nombre',
                'v.codigo AS venta_codigo',
                'tt.abreviatura AS tipo_venta_abreviatura',
                'tt.nombre AS tipo_venta_nombre',
                DB::raw('COALESCE(ts.nombre, tt.nombre) AS tipo_nombre'),
                DB::raw('COALESCE(ts.abreviatura, tt.abreviatura) AS tipo_abreviatura'),
                'ms.codigo AS movimiento_codigo',
                'ms.leyenda AS movimiento_leyenda',
            ])
            ->leftJoin('depmae as dep', 'dep.id', '=', 'am.deposito_id')
            ->leftJoin('empresa as emp', 'emp.id', '=', 'dep.empresa_id')
            ->leftJoin('venta as v', 'v.id', '=', 'am.venta_id')
            ->leftJoin('tipotransaccion_stock as ts', 'ts.id', '=', 'am.tipotransaccion_stock_id')
            ->leftJoin('tipotransaccion as tt', 'tt.id', '=', 'am.tipotransaccion_id')
            ->leftJoin('movimientostock as ms', 'ms.id', '=', 'am.movimientostock_id')
            ->where('am.articulo_id', $articuloId);
    }

    private static function aplicarFiltroDepositosAutorizados(Builder $query, ?int $empresaId = null): void
    {
        $ids = MovimientosArticuloDepositoSupport::idsDepositosConsultablesFiltrados($empresaId);
        if ($ids === null) {
            if ($empresaId !== null && $empresaId > 0) {
                $query->where('dep.empresa_id', $empresaId);
            }

            return;
        }

        if ($ids === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('am.deposito_id', $ids);
    }

    private static function saldoTodosDepositos(int $articuloId, ?int $empresaId = null): float
    {
        $saldoRepo = app(\App\Repositories\Stock\Articulo_Saldo_DepositoRepositoryInterface::class);
        $idsConsultables = MovimientosArticuloDepositoSupport::idsDepositosConsultablesFiltrados($empresaId);

        if ($idsConsultables === null) {
            return (float) Articulo_Saldo_Deposito::query()
                ->where('articulo_id', $articuloId)
                ->sum('cantidad');
        }

        if ($idsConsultables === []) {
            return 0.0;
        }

        $saldos = $saldoRepo->saldosArticuloPorDeposito($articuloId, $idsConsultables);

        return (float) array_sum($saldos);
    }
}
