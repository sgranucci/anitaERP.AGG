<?php

namespace App\Support\Caja;

use App\Models\Configuracion\Sala;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TesoreriaCrownSalaSupport
{
    /** Código de depósitos centrales. No es una sala de juego. */
    private const CODIGO_ADMINISTRACION_CENTRAL = 'AC';

    /**
     * Orden operativo de casinos en chips, jornadas y reportes Flash.
     * Pedido 12/11/2026 (sin El Faro, cerrado).
     *
     * @var list<string>
     */
    public const ORDEN_OPERATIVA = [
        'CIP', // Cipolletti
        'GRO', // Gral Roca
        'VIE', // Viedma
        'LGR', // Las Grutas
        'CAT', // Catriel
        'CHO', // Choele Choel
        'VRE', // Villa Regina
        'CSA', // Cinco Saltos
    ];

    /**
     * Salas cerradas: no salen en tesorería ni Flash (historial por ID sigue vía exigida).
     *
     * @var list<string>
     */
    public const CODIGOS_CERRADAS = [
        'EFA', // El Faro
    ];

    public static function salas(): Collection
    {
        $empresas = collect(app(EmpresaRepositoryInterface::class)->allFiltrado());
        $ids = $empresas->pluck('id')->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->values()->all();
        $query = Sala::query()
            ->whereRaw('UPPER(codigo) <> ?', [self::CODIGO_ADMINISTRACION_CENTRAL])
            ->whereNotIn('codigo', self::CODIGOS_CERRADAS)
            ->orderByRaw(SqlDialectSupport::ordenPorLista('codigo', self::ORDEN_OPERATIVA))
            ->orderBy('nombre');
        if ($ids !== []) {
            $query->whereIn('empresa_id', $ids);
        }

        return $query->get(['id', 'codigo', 'nombre', 'empresa_id']);
    }

    /**
     * @return list<int>
     */
    public static function idsOperativos(): array
    {
        return self::salas()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();
    }

    public static function indiceOrden(string $codigo): int
    {
        static $orden = null;
        $orden ??= array_flip(self::ORDEN_OPERATIVA);

        return $orden[strtoupper(trim($codigo))] ?? 1000;
    }

    /**
     * @param  Collection<int, mixed>  $filas  filas con relación sala y fecha (FlashCrown u objetos similares)
     * @return Collection<int, mixed>
     */
    public static function ordenarColeccionPorFechaYSala(Collection $filas): Collection
    {
        return $filas
            ->sortBy(function ($fila): array {
                $fecha = $fila->fecha ?? null;
                $fechaFmt = $fecha instanceof \Carbon\CarbonInterface
                    ? $fecha->format('Y-m-d')
                    : (string) $fecha;
                $codigo = (string) ($fila->sala->codigo ?? '');

                return [$fechaFmt, self::indiceOrden($codigo)];
            })
            ->values();
    }

    public static function pedida(Request $request, ?Collection $salas = null): ?Sala
    {
        $salas = $salas ?? self::salas();
        $salaId = (int) $request->input('sala_id', 0);
        if ($salaId > 0) {
            $sala = $salas->firstWhere('id', $salaId);
            if (! $sala) {
                abort(404);
            }

            return $sala;
        }

        $codigo = strtoupper(trim((string) config('caja_anita.sala_codigo', '')));
        if ($codigo !== '') {
            $preferida = $salas->first(fn ($sala) => strtoupper((string) $sala->codigo) === $codigo);
            if ($preferida) {
                return $preferida;
            }
        }

        return $salas->first();
    }

    /**
     * Valida acceso a una sala por ID (incluye cerradas para historial).
     */
    public static function exigida(int $salaId): Sala
    {
        $empresas = collect(app(EmpresaRepositoryInterface::class)->allFiltrado());
        $ids = $empresas->pluck('id')->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->values()->all();
        $query = Sala::query()
            ->whereKey($salaId)
            ->whereRaw('UPPER(codigo) <> ?', [self::CODIGO_ADMINISTRACION_CENTRAL]);
        if ($ids !== []) {
            $query->whereIn('empresa_id', $ids);
        }

        $sala = $query->first(['id', 'codigo', 'nombre', 'empresa_id']);
        if (! $sala) {
            abort(404);
        }

        return $sala;
    }
}
