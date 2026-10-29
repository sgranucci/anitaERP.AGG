<?php

namespace App\Support\Stock;

use App\Models\Stock\Depmae;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Stock por OT: todos los depósitos, los de canal Local, o el resto (canal Fábrica).
 * Un depósito puntual del formulario reemplaza el grupo.
 */
final class ReporteStockOtDepositoAmbitoSupport
{
    public const TODOS = 'TODOS';

    public const LOCALES = 'LOCALES';

    public const FABRICA = 'FABRICA';

    /**
     * @return array<string, string>
     */
    public static function opciones(): array
    {
        return [
            self::TODOS => 'Todos',
            self::LOCALES => 'Locales',
            self::FABRICA => 'Fábrica',
        ];
    }

    public static function normalizar(?string $ambito): string
    {
        $ambito = strtoupper(trim((string) $ambito));

        return array_key_exists($ambito, self::opciones()) ? $ambito : self::TODOS;
    }

    public static function etiqueta(?string $ambito): string
    {
        $ambito = self::normalizar($ambito);

        return self::opciones()[$ambito];
    }

    /**
     * Nombres de los depósitos de locales, para el texto de ayuda del formulario.
     */
    public static function etiquetaDepositosLocales(): string
    {
        $ids = MovimientoStockFerliSupport::idsDepositosDeLocalVenta();
        if ($ids === []) {
            return '';
        }

        return Depmae::query()
            ->whereIn('id', $ids)
            ->orderBy('codigo')
            ->get(['codigo', 'nombre'])
            ->map(static fn (Depmae $dep) => trim($dep->codigo.' '.$dep->nombre))
            ->implode(', ');
    }

    /**
     * OT todavía en planta (sin movimiento de stock) entran en Todos y en Fábrica.
     * En Locales no: todavía no están en una tienda.
     */
    public static function incluyeOtEnProduccion(?string $ambito, int $depositoId, ?string $estadoOt): bool
    {
        return $depositoId <= 0
            && (string) $estadoOt !== 'ENTREGA'
            && self::normalizar($ambito) !== self::LOCALES;
    }

    /**
     * @param  EloquentBuilder|QueryBuilder  $query
     * @return EloquentBuilder|QueryBuilder
     */
    public static function aplicar($query, ?string $ambito, int $depositoId, string $columnaDeposito = 'articulo_movimiento.deposito_id')
    {
        if ($depositoId > 0) {
            return $query->where($columnaDeposito, $depositoId);
        }

        $ambito = self::normalizar($ambito);
        if ($ambito === self::TODOS) {
            return $query;
        }

        $idsLocales = MovimientoStockFerliSupport::idsDepositosDeLocalVenta();
        if ($ambito === self::LOCALES) {
            return $idsLocales === []
                ? $query->whereIn($columnaDeposito, [0])
                : $query->whereIn($columnaDeposito, $idsLocales);
        }

        if ($idsLocales !== []) {
            $query->where(function ($q) use ($columnaDeposito, $idsLocales) {
                $q->whereNotIn($columnaDeposito, $idsLocales)
                    ->orWhereNull($columnaDeposito);
            });
        }

        return self::excluirCopiasAnitaLocal($query, $columnaDeposito);
    }

    /**
     * El modal y el Enter del código usan el maestro depmae, con el mismo corte que el reporte.
     *
     * @param  EloquentBuilder|QueryBuilder  $query
     * @return EloquentBuilder|QueryBuilder
     */
    public static function aplicarSobreMaestro($query, ?string $ambito, string $columnaId = 'id')
    {
        $ambito = self::normalizar($ambito);
        if ($ambito === self::TODOS) {
            return $query;
        }

        $idsLocales = MovimientoStockFerliSupport::idsDepositosDeLocalVenta();
        if ($ambito === self::LOCALES) {
            return $idsLocales === []
                ? $query->whereIn($columnaId, [0])
                : $query->whereIn($columnaId, $idsLocales);
        }

        if ($idsLocales !== []) {
            $query->whereNotIn($columnaId, $idsLocales);
        }

        return self::excluirCopiasAnitaLocal($query, $columnaId);
    }

    /**
     * Copias Anita del mismo local (610, 620…): no son planta ni la tienda operativa.
     *
     * @param  EloquentBuilder|QueryBuilder  $query
     * @return EloquentBuilder|QueryBuilder
     */
    private static function excluirCopiasAnitaLocal($query, string $columnaId)
    {
        if ($columnaId === 'id') {
            return $query->where(function ($q) {
                $q->where('nombre', 'not like', '%(local %')
                    ->orWhereNull('nombre');
            });
        }

        return $query->whereNotExists(function ($sub) use ($columnaId) {
            $sub->select(DB::raw('1'))
                ->from('depmae as dep_ambito_local')
                ->whereColumn('dep_ambito_local.id', $columnaId)
                ->where('dep_ambito_local.nombre', 'like', '%(local %');
        });
    }
}
