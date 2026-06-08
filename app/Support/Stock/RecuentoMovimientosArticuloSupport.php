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
     *     articulo: array{id:int, sku:string, descripcion:string},
     *     deposito: array{id:int, codigo:string, nombre:string},
     *     modo_todos_depositos: bool,
     *     saldo: float,
     *     saldo_fmt: string
     * }
     */
    public static function validarContexto(int $articuloId, int $depositoId): array
    {
        if ($articuloId <= 0) {
            throw new \InvalidArgumentException('Artículo requerido.');
        }

        $articulo = Articulo::query()->select('id', 'sku', 'descripcion')->find($articuloId);
        if (! $articulo) {
            throw new \RuntimeException('Artículo no encontrado.');
        }

        $modoTodos = self::esModoTodosDepositos($depositoId);

        if ($modoTodos) {
            return [
                'articulo' => [
                    'id' => (int) $articulo->id,
                    'sku' => (string) $articulo->sku,
                    'descripcion' => (string) $articulo->descripcion,
                ],
                'deposito' => [
                    'id' => 0,
                    'codigo' => '',
                    'nombre' => 'Todos los depósitos',
                ],
                'modo_todos_depositos' => true,
                'saldo' => ($saldoTodos = self::saldoTodosDepositos($articuloId)),
                'saldo_fmt' => self::formatearNumero($saldoTodos),
            ];
        }

        $deposito = Depmae::query()->select('id', 'codigo', 'nombre', 'empresa_id')->find($depositoId);
        if (! $deposito) {
            throw new \RuntimeException('Depósito no encontrado.');
        }
        if (! Depmae::autorizadoParaUsuarioYEmpresa((int) $deposito->id, (int) $deposito->empresa_id)) {
            throw new \RuntimeException('Depósito no autorizado para su usuario o empresa.');
        }
        if (! UsuarioDepositoAutorizado::depositoAutorizado((int) $deposito->id)) {
            throw new \RuntimeException('No tiene permiso para operar sobre este depósito.');
        }

        $saldoRepo = app(\App\Repositories\Stock\Articulo_Saldo_DepositoRepositoryInterface::class);
        $saldo = $saldoRepo->saldo($articuloId, $depositoId);

        return [
            'articulo' => [
                'id' => (int) $articulo->id,
                'sku' => (string) $articulo->sku,
                'descripcion' => (string) $articulo->descripcion,
            ],
            'deposito' => [
                'id' => (int) $deposito->id,
                'codigo' => (string) ($deposito->codigo ?? ''),
                'nombre' => (string) ($deposito->nombre ?? ''),
            ],
            'modo_todos_depositos' => false,
            'saldo' => $saldo,
            'saldo_fmt' => self::formatearNumero($saldo),
        ];
    }

    public static function query(int $articuloId, int $depositoId): Builder
    {
        $query = self::queryBase($articuloId)
            ->where('am.articulo_id', $articuloId)
            ->whereNull('am.deleted_at');

        if (self::esModoTodosDepositos($depositoId)) {
            self::aplicarFiltroDepositosAutorizados($query);

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
            $row->deposito_etiqueta = self::etiquetaDeposito([
                'id' => (int) ($row->deposito_id ?? 0),
                'codigo' => (string) ($row->deposito_codigo ?? ''),
                'nombre' => (string) ($row->deposito_nombre ?? ''),
            ]);
        }

        return $row;
    }

    /**
     * @param  object|\ArrayAccess<string, mixed>  $row
     */
    public static function resolverConceptoDisplay(object $row): string
    {
        $concepto = trim((string) ($row->concepto ?? ''));
        $ventaCodigo = trim((string) ($row->venta_codigo ?? ''));
        $sufijoInsumo = GastronomiaVentaDetalleSupport::SUFIJO_CONCEPTO_INSUMO;

        if ($ventaCodigo !== '') {
            if ($sufijoInsumo !== '' && str_contains($concepto, $sufijoInsumo)) {
                return $ventaCodigo.$sufijoInsumo;
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
        $partes = array_filter([
            $deposito['codigo'] ?? '',
            $deposito['nombre'] ?? '',
        ], fn ($v) => trim((string) $v) !== '');

        return $partes !== [] ? implode(' — ', $partes) : ('ID '.($deposito['id'] ?? ''));
    }

    private static function queryBase(int $articuloId): Builder
    {
        return Articulo_Movimiento::query()
            ->from('articulo_movimiento as am')
            ->select([
                'am.id',
                'am.fecha',
                'am.cantidad',
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

    private static function aplicarFiltroDepositosAutorizados(Builder $query): void
    {
        $ids = UsuarioDepositoAutorizado::idsRestringidos();
        if ($ids !== null) {
            $query->whereIn('am.deposito_id', $ids);
        }
    }

    private static function saldoTodosDepositos(int $articuloId): float
    {
        $saldoRepo = app(\App\Repositories\Stock\Articulo_Saldo_DepositoRepositoryInterface::class);
        $idsAutorizados = UsuarioDepositoAutorizado::idsRestringidos();

        if ($idsAutorizados === null) {
            return (float) Articulo_Saldo_Deposito::query()
                ->where('articulo_id', $articuloId)
                ->sum('cantidad');
        }

        $saldos = $saldoRepo->saldosArticuloPorDeposito($articuloId, $idsAutorizados);

        return (float) array_sum($saldos);
    }
}
