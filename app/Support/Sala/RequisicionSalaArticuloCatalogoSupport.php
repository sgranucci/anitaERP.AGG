<?php

namespace App\Support\Sala;

use App\Models\Stock\Depmae;
use App\Support\Stock\UsuarioDepositoAutorizado;
use Illuminate\Database\Eloquent\Builder;

/**
 * Catálogo de artículos en requisición de sala.
 *
 * Los depósitos 403/404/405/406 del técnico son destino de la requisición.
 * Los artículos (p.ej. PILA) tienen depositoentrega en almacenes de laboratorio
 * (4001/4002/4003/…). Si el usuario tiene usuario_deposito, se acota a esos
 * orígenes más los depósitos propios; sin restricción no se filtra.
 */
final class RequisicionSalaArticuloCatalogoSupport
{
    /**
     * IDs depmae del catálogo permitido, o null si no hay restricción de usuario.
     *
     * @return list<int>|null
     */
    public static function idsDepositosCatalogo(): ?array
    {
        if (! UsuarioDepositoAutorizado::tieneRestriccion()) {
            return null;
        }

        $codigos = self::codigosCatalogo();
        $codigosUsuario = UsuarioDepositoAutorizado::codigosAutorizados() ?? [];
        $codigos = array_values(array_unique(array_merge($codigos, $codigosUsuario)));
        $codigos = array_values(array_filter($codigos, static fn (string $c) => $c !== ''));

        if ($codigos === []) {
            return [];
        }

        return Depmae::query()
            ->whereIn('codigo', $codigos)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function codigosCatalogo(): array
    {
        $codigos = config('sala.requisicion_articulos_depositos_codigos', []);
        if (! is_array($codigos)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($c) => trim((string) $c),
            $codigos
        ), static fn (string $c) => $c !== '')));
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function aplicarFiltroArticulo(Builder $query): Builder
    {
        $ids = self::idsDepositosCatalogo();
        if ($ids === null) {
            return $query;
        }

        if ($ids === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereIn('articulo.depositoentrega_id', $ids);
    }

    public static function articuloPermitido(?int $depositoEntregaId): bool
    {
        $ids = self::idsDepositosCatalogo();
        if ($ids === null) {
            return true;
        }

        if ($depositoEntregaId === null || $depositoEntregaId <= 0) {
            return false;
        }

        return in_array($depositoEntregaId, $ids, true);
    }
}
