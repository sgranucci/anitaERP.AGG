<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Combinacion;
use App\Models\Stock\Depmae;
use App\Models\Stock\Talle;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\DB;

/**
 * Explorador kardex Ferli: combinaciones activas + stock por depósito.
 */
final class ArticuloKardexCombinacionSupport
{
    public static function uiActiva(): bool
    {
        return EntornoEmpresaSupport::esFerli();
    }

    public static function puedeConsultar(): bool
    {
        return self::uiActiva() && MovimientosArticuloDepositoSupport::puedeConsultar();
    }

    /**
     * @return array{
     *     articulo: array{id:int,sku:string,descripcion:string},
     *     combinaciones: list<array{id:int,codigo:string,nombre:string,estado:string}>,
     *     talles: list<array{id:int,codigo:string,nombre:string}>,
     *     depositos: list<array{
     *         deposito_id:int,codigo:string,nombre:string,empresa_nombre:string,
     *         saldo:float,saldo_fmt:string,combinacion_id:?int
     *     }>,
     *     saldos_por_combinacion: array<string, list<array{
     *         deposito_id:int,codigo:string,nombre:string,empresa_nombre:string,
     *         saldo:float,saldo_fmt:string
     *     }>>,
     *     saldo_sin_combinacion: list<array{
     *         deposito_id:int,codigo:string,nombre:string,empresa_nombre:string,
     *         saldo:float,saldo_fmt:string
     *     }>,
     *     mostrar_empresa: bool,
     *     total_fmt: string,
     *     urls_kardex_base: string
     * }
     */
    public static function explorador(int $articuloId, ?int $empresaId = null): array
    {
        $articulo = Articulo::query()
            ->select('id', 'sku', 'descripcion')
            ->findOrFail($articuloId);

        $combinaciones = Combinacion::query()
            ->select('id', 'codigo', 'nombre', 'estado')
            ->where('articulo_id', $articuloId)
            ->where('estado', 'A')
            ->orderBy('codigo')
            ->get()
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'codigo' => (string) ($c->codigo ?? ''),
                'nombre' => (string) ($c->nombre ?? ''),
                'estado' => (string) ($c->estado ?? 'A'),
            ])
            ->all();

        $talles = Talle::query()
            ->select('id', 'codigo', 'nombre')
            ->orderBy('id')
            ->get()
            ->map(fn ($t) => [
                'id' => (int) $t->id,
                'codigo' => (string) ($t->codigo ?? ''),
                'nombre' => (string) ($t->nombre ?? ''),
            ])
            ->all();

        $mostrarEmpresa = MovimientosArticuloDepositoSupport::mostrarEmpresaEnListados();
        $saldosPorCombinacion = [];
        $totalGeneral = 0.0;

        foreach ($combinaciones as $comb) {
            $filas = self::saldosPorDeposito($articuloId, (int) $comb['id'], $empresaId);
            $saldosPorCombinacion[(string) $comb['id']] = $filas;
            foreach ($filas as $f) {
                $totalGeneral += (float) $f['saldo'];
            }
        }

        $saldoSinCombinacion = self::saldosPorDeposito($articuloId, null, $empresaId, true);
        if ($combinaciones === []) {
            foreach ($saldoSinCombinacion as $f) {
                $totalGeneral += (float) $f['saldo'];
            }
        }

        return [
            'articulo' => [
                'id' => (int) $articulo->id,
                'sku' => (string) ($articulo->sku ?? ''),
                'descripcion' => (string) ($articulo->descripcion ?? ''),
            ],
            'combinaciones' => $combinaciones,
            'talles' => $talles,
            'depositos' => $saldoSinCombinacion,
            'saldos_por_combinacion' => $saldosPorCombinacion,
            'saldo_sin_combinacion' => $saldoSinCombinacion,
            'mostrar_empresa' => $mostrarEmpresa,
            'total_fmt' => number_format($totalGeneral, 2, ',', '.'),
            'urls_kardex_base' => route('recuento_movimientos_articulo', [
                'articulo_id' => $articuloId,
                'vista' => 'consulta',
            ]),
        ];
    }

    /**
     * @return list<array{
     *     deposito_id:int,codigo:string,nombre:string,empresa_nombre:string,
     *     saldo:float,saldo_fmt:string,combinacion_id:?int
     * }>
     */
    public static function saldosPorDeposito(
        int $articuloId,
        ?int $combinacionId,
        ?int $empresaId = null,
        bool $agregadoArticulo = false
    ): array {
        $depQuery = Depmae::query()
            ->select('id', 'codigo', 'nombre', 'empresa_id')
            ->with('empresas:id,nombre')
            ->paraUsuarioAutorizado()
            ->whereIn('id', function ($q) use ($articuloId, $combinacionId, $agregadoArticulo) {
                $q->select('deposito_id')
                    ->from('articulo_movimiento')
                    ->where('articulo_id', $articuloId)
                    ->whereNotNull('deposito_id');
                if (! $agregadoArticulo && $combinacionId !== null && $combinacionId > 0) {
                    $q->where('combinacion_id', $combinacionId);
                }
                $q->groupBy('deposito_id');
            })
            ->orderBy('codigo');

        $empresaId = (int) ($empresaId ?? 0);
        if ($empresaId > 0) {
            $depQuery->paraEmpresa($empresaId);
        } else {
            MovimientosArticuloDepositoSupport::aplicarFiltroConsultaDeposito($depQuery);
        }

        $depositos = $depQuery->get();
        if ($depositos->isEmpty() && ($combinacionId === null || $agregadoArticulo)) {
            // Fallback: saldos on-line aunque no haya movimiento con combinación.
            return self::saldosDesdeTablaSaldo($articuloId, $empresaId > 0 ? $empresaId : null);
        }

        $filas = [];
        foreach ($depositos as $dep) {
            $depId = (int) $dep->id;
            $saldoQ = DB::table('articulo_movimiento')
                ->where('articulo_id', $articuloId)
                ->where('deposito_id', $depId);
            if (! $agregadoArticulo && $combinacionId !== null && $combinacionId > 0) {
                $saldoQ->where('combinacion_id', $combinacionId);
            }
            $saldo = (float) $saldoQ->sum('cantidad');
            $filas[] = [
                'deposito_id' => $depId,
                'codigo' => (string) ($dep->codigo ?? ''),
                'nombre' => (string) ($dep->nombre ?? ''),
                'empresa_nombre' => (string) (optional($dep->empresas)->nombre ?? ''),
                'saldo' => $saldo,
                'saldo_fmt' => number_format($saldo, 2, ',', '.'),
                'combinacion_id' => $combinacionId,
            ];
        }

        usort($filas, static fn ($a, $b) => abs($b['saldo']) <=> abs($a['saldo']));

        return $filas;
    }

    /**
     * @return list<array{
     *     deposito_id:int,codigo:string,nombre:string,empresa_nombre:string,
     *     saldo:float,saldo_fmt:string,combinacion_id:?int
     * }>
     */
    private static function saldosDesdeTablaSaldo(int $articuloId, ?int $empresaId): array
    {
        $base = ArticuloSaldosDepositoSupport::listadoPorArticulo($articuloId, $empresaId);
        $out = [];
        foreach ($base['filas'] as $f) {
            $out[] = [
                'deposito_id' => (int) $f['deposito_id'],
                'codigo' => (string) $f['codigo'],
                'nombre' => (string) $f['nombre'],
                'empresa_nombre' => (string) ($f['empresa_nombre'] ?? ''),
                'saldo' => (float) $f['saldo'],
                'saldo_fmt' => (string) $f['saldo_fmt'],
                'combinacion_id' => null,
            ];
        }

        return $out;
    }

    public static function urlKardex(int $articuloId, int $depositoId = 0, ?int $combinacionId = null, ?string $volver = null): string
    {
        $params = MovimientosArticuloDepositoSupport::parametrosUrlKardex($articuloId, $depositoId, $volver);
        if ($combinacionId !== null && $combinacionId > 0) {
            $params['combinacion_id'] = $combinacionId;
        }

        return route('recuento_movimientos_articulo', $params);
    }
}
