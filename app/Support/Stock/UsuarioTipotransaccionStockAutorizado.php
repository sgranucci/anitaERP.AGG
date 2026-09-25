<?php

namespace App\Support\Stock;

use App\Models\Seguridad\Usuario;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Session;

final class UsuarioTipotransaccionStockAutorizado
{
    /**
     * IDs de tipos de transacción autorizados para el usuario logueado, o null si no hay restricción.
     *
     * @return array<int>|null
     */
    public static function idsRestringidos(): ?array
    {
        if (! Session::has('usuario_tipotransaccion_stock_ids')) {
            return null;
        }

        $ids = Session::get('usuario_tipotransaccion_stock_ids');
        if (! is_array($ids) || count($ids) === 0) {
            return null;
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    public static function tieneRestriccion(): bool
    {
        $ids = self::idsRestringidos();

        return is_array($ids) && count($ids) > 0;
    }

    public static function tipotransaccionAutorizada(int $tipotransaccionStockId): bool
    {
        if ($tipotransaccionStockId <= 0) {
            return false;
        }

        $ids = self::idsRestringidos();
        if ($ids === null) {
            return true;
        }

        return in_array($tipotransaccionStockId, $ids, true);
    }

    /**
     * @param  Builder<Tipotransaccion_Stock>  $query
     * @return Builder<Tipotransaccion_Stock>
     */
    public static function aplicarFiltroQuery(Builder $query): Builder
    {
        $ids = self::idsRestringidos();
        if ($ids === null) {
            return $query;
        }

        return $query->whereIn($query->getModel()->getTable().'.id', $ids);
    }

    public static function cargarEnSession(Usuario $usuario): void
    {
        $ids = $usuario->tipotransaccionesStockAutorizadas()->pluck('tipotransaccion_stock.id')->all();

        if (count($ids) > 0) {
            Session::put('usuario_tipotransaccion_stock_ids', array_values(array_map('intval', $ids)));
        } else {
            Session::forget('usuario_tipotransaccion_stock_ids');
        }
    }

    /**
     * @param  array<int|string>  $tipoIds
     * @return array<int>
     */
    public static function idsValidos(array $tipoIds): array
    {
        $tipoIds = array_values(array_unique(array_filter(array_map('intval', $tipoIds))));

        if ($tipoIds === []) {
            return [];
        }

        return Tipotransaccion_Stock::query()
            ->whereIn('id', $tipoIds)
            ->where('estado', 'A')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Resuelve tipos autorizados desde abreviaturas (y opcionalmente IDs) del ABM usuario.
     * Si el renglón tiene abreviatura, manda la abreviatura (evita carrera blur/AJAX vs submit).
     *
     * @param  array<int|string|null>  $tipoIds
     * @param  array<int|string|null>  $abreviaturas
     * @return array<int>
     */
    public static function idsDesdeFormulario(array $tipoIds, array $abreviaturas): array
    {
        $abrevResueltas = [];
        $idsSinAbrev = [];
        $max = max(count($tipoIds), count($abreviaturas));

        for ($i = 0; $i < $max; $i++) {
            $abrev = strtolower(trim((string) ($abreviaturas[$i] ?? '')));
            $id = (int) ($tipoIds[$i] ?? 0);

            if ($abrev !== '') {
                $abrevResueltas[] = $abrev;
                continue;
            }

            if ($id > 0) {
                $idsSinAbrev[] = $id;
            }
        }

        $validIds = [];
        if ($abrevResueltas !== []) {
            $validIds = array_merge($validIds, self::idsDesdeAbreviaturas($abrevResueltas));
        }
        if ($idsSinAbrev !== []) {
            $validIds = array_merge($validIds, self::idsValidos($idsSinAbrev));
        }

        return array_values(array_unique(array_map('intval', $validIds)));
    }

    /**
     * @param  array<int|string>  $abreviaturas
     * @return array<int>
     */
    public static function idsDesdeAbreviaturas(array $abreviaturas): array
    {
        $abreviaturas = array_values(array_unique(array_filter(array_map(
            static fn ($abrev) => strtolower(trim((string) $abrev)),
            $abreviaturas
        ), static fn (string $abrev) => $abrev !== '')));

        if ($abreviaturas === []) {
            return [];
        }

        return Tipotransaccion_Stock::query()
            ->where('estado', 'A')
            ->where(function ($q) use ($abreviaturas) {
                foreach ($abreviaturas as $abrev) {
                    $q->orWhereRaw(SqlDialectSupport::lower('abreviatura').' = ?', [$abrev]);
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
