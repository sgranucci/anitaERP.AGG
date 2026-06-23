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
     * @return array{
     *     articulo: array{id: int, sku: string, descripcion: string},
     *     filas: list<array{deposito_id: int, codigo: string, nombre: string, saldo: float, saldo_fmt: string}>,
     *     total: float,
     *     total_fmt: string
     * }
     */
    public static function listadoPorArticulo(int $articuloId): array
    {
        $articulo = Articulo::query()
            ->select('id', 'sku', 'codigoarticulo', 'descripcion')
            ->findOrFail($articuloId);

        $depQuery = Depmae::query()
            ->select('id', 'codigo', 'nombre')
            ->orderBy('codigo');
        UsuarioDepositoAutorizado::aplicarFiltroQuery($depQuery);
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

            if (abs($saldo) < 0.0000001) {
                continue;
            }

            $filas[] = [
                'deposito_id' => $depId,
                'codigo' => (string) ($dep->codigo ?? ''),
                'nombre' => (string) ($dep->nombre ?? ''),
                'saldo' => $saldo,
                'saldo_fmt' => self::formatSaldo($saldo),
            ];
        }

        usort($filas, static function (array $a, array $b): int {
            $cmp = abs($b['saldo']) <=> abs($a['saldo']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp($a['codigo'], $b['codigo']);
        });

        $sku = trim((string) ($articulo->codigoarticulo ?? $articulo->sku ?? ''));

        return [
            'articulo' => [
                'id' => (int) $articulo->id,
                'sku' => $sku,
                'descripcion' => (string) ($articulo->descripcion ?? ''),
            ],
            'filas' => $filas,
            'total' => $total,
            'total_fmt' => self::formatSaldo($total),
        ];
    }

    public static function formatSaldo(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 4, ',', '.'), '0'), ',');
    }
}
