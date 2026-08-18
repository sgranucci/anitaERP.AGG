<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Depmae;
use App\Repositories\Stock\Articulo_Saldo_DepositoRepositoryInterface;

final class ArticuloSaldosDepositoSupport
{
    public static function puedeConsultar(): bool
    {
        return MovimientosArticuloDepositoSupport::puedeConsultar();
    }

    /**
     * Lista depósitos autorizados donde el artículo tuvo movimientos (aunque el saldo sea 0).
     * No incluye depósitos sin movimiento.
     *
     * @return array{
     *     articulo: array{id: int, sku: string, descripcion: string, unidad_medida: string, unidad_medida_abreviatura: string, unidad_medida_nombre: string},
     *     filas: list<array{deposito_id: int, codigo: string, nombre: string, empresa_id: int, empresa_nombre: string, saldo: float, saldo_fmt: string}>,
     *     mostrar_empresa: bool,
     *     total: float,
     *     total_fmt: string
     * }
     */
    public static function listadoPorArticulo(int $articuloId, ?int $empresaId = null): array
    {
        $articulo = Articulo::query()
            ->select('id', 'sku', 'descripcion', 'unidadmedida_id')
            ->with('unidadesdemedidas:id,nombre,abreviatura')
            ->findOrFail($articuloId);

        $mostrarEmpresa = MovimientosArticuloDepositoSupport::mostrarEmpresaEnListados();

        $depQuery = Depmae::query()
            ->select('id', 'codigo', 'nombre', 'empresa_id')
            ->with('empresas:id,nombre')
            ->paraUsuarioAutorizado()
            ->whereIn('id', function ($q) use ($articuloId) {
                $q->select('deposito_id')
                    ->from('articulo_movimiento')
                    ->where('articulo_id', $articuloId)
                    ->whereNotNull('deposito_id')
                    ->groupBy('deposito_id');
            })
            ->orderBy('codigo');

        $empresaId = (int) ($empresaId ?? 0);
        if ($empresaId > 0) {
            $depQuery->paraEmpresa($empresaId);
        } else {
            MovimientosArticuloDepositoSupport::aplicarFiltroConsultaDeposito($depQuery);
        }

        $depositos = $depQuery->get();

        $depositoIds = $depositos->pluck('id')->map(fn ($id) => (int) $id)->all();
        $saldoRepo = app(Articulo_Saldo_DepositoRepositoryInterface::class);
        $saldosMap = $depositoIds !== []
            ? $saldoRepo->saldosArticuloPorDeposito($articuloId, $depositoIds)
            : [];

        $filas = [];
        $total = 0.0;

        foreach ($depositos as $dep) {
            $depId = (int) $dep->id;
            $saldo = (float) ($saldosMap[$depId] ?? 0.0);
            $total += $saldo;

            $filas[] = [
                'deposito_id' => $depId,
                'codigo' => (string) ($dep->codigo ?? ''),
                'nombre' => (string) ($dep->nombre ?? ''),
                'empresa_id' => (int) ($dep->empresa_id ?? 0),
                'empresa_nombre' => (string) (optional($dep->empresas)->nombre ?? ''),
                'saldo' => $saldo,
                'saldo_fmt' => self::formatSaldo($saldo),
            ];
        }

        usort($filas, static function (array $a, array $b): int {
            $cmp = abs($b['saldo']) <=> abs($a['saldo']);
            if ($cmp !== 0) {
                return $cmp;
            }

            $cmpEmp = strcmp($a['empresa_nombre'], $b['empresa_nombre']);
            if ($cmpEmp !== 0) {
                return $cmpEmp;
            }

            return strcmp($a['codigo'], $b['codigo']);
        });

        return [
            'articulo' => MovimientosArticuloDepositoSupport::articuloResumen($articulo),
            'filas' => $filas,
            'mostrar_empresa' => $mostrarEmpresa,
            'total' => $total,
            'total_fmt' => self::formatSaldo($total),
        ];
    }

    public static function formatSaldo(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 4, ',', '.'), '0'), ',');
    }
}
