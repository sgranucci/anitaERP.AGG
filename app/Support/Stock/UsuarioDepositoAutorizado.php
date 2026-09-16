<?php

namespace App\Support\Stock;

use App\Models\Seguridad\Usuario;
use App\Models\Stock\Depmae;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Session;

final class UsuarioDepositoAutorizado
{
    /**
     * IDs de depósitos autorizados para el usuario logueado, o null si no hay restricción.
     *
     * @return array<int>|null
     */
    public static function idsRestringidos(): ?array
    {
        if (! Session::has('usuario_depositos_ids')) {
            return null;
        }

        $ids = Session::get('usuario_depositos_ids');
        if (! is_array($ids) || count($ids) === 0) {
            return null;
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Códigos de depósito derivados de los depósitos asignados al usuario (multiempresa).
     *
     * @return list<string>|null null = sin restricción
     */
    public static function codigosAutorizados(): ?array
    {
        $ids = self::idsRestringidos();
        if ($ids === null) {
            return null;
        }

        return Depmae::query()
            ->whereIn('id', $ids)
            ->pluck('codigo')
            ->map(fn ($codigo) => trim((string) $codigo))
            ->filter(fn (string $codigo) => $codigo !== '')
            ->unique()
            ->values()
            ->all();
    }

    public static function tieneRestriccion(): bool
    {
        $ids = self::idsRestringidos();

        return is_array($ids) && count($ids) > 0;
    }

    public static function depositoAutorizado(int $depositoId): bool
    {
        if ($depositoId <= 0) {
            return false;
        }

        $ids = self::idsRestringidos();
        if ($ids === null) {
            return true;
        }

        if (in_array($depositoId, $ids, true)) {
            return true;
        }

        $codigos = self::codigosAutorizados();
        if ($codigos === []) {
            return false;
        }

        $deposito = Depmae::query()->select('id', 'codigo', 'empresa_id')->find($depositoId);
        if (! $deposito) {
            return false;
        }

        $codigo = trim((string) ($deposito->codigo ?? ''));
        if ($codigo === '' || ! in_array($codigo, $codigos, true)) {
            return false;
        }

        $empresaId = (int) ($deposito->empresa_id ?? 0);
        $empresasAsignadas = collect(Session::get('usuario_empresas', []))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($empresasAsignadas !== []) {
            return in_array($empresaId, $empresasAsignadas, true);
        }

        return true;
    }

    /**
     * @param  Builder<Depmae>  $query
     * @return Builder<Depmae>
     */
    public static function aplicarFiltroQuery(Builder $query, ?int $empresaIdFija = null): Builder
    {
        $codigos = self::codigosAutorizados();
        if ($codigos === null) {
            return $query;
        }

        $table = $query->getModel()->getTable();

        if ($empresaIdFija > 0) {
            return $query
                ->where($table.'.empresa_id', $empresaIdFija)
                ->whereIn($table.'.codigo', $codigos);
        }

        return $query->whereIn($table.'.codigo', $codigos);
    }

    public static function cargarEnSession(Usuario $usuario): void
    {
        $ids = $usuario->depositosAutorizados()->pluck('depmae.id')->all();

        if (count($ids) > 0) {
            Session::put('usuario_depositos_ids', array_values(array_map('intval', $ids)));
        } else {
            Session::forget('usuario_depositos_ids');
        }
    }

    /**
     * @param  array<int|string>  $depositoIds
     * @param  array<int|string>  $empresaIds
     * @return array<int>
     */
    public static function idsValidosParaEmpresas(array $depositoIds, array $empresaIds): array
    {
        $depositoIds = array_values(array_unique(array_filter(array_map('intval', $depositoIds))));
        $empresaIds = array_values(array_unique(array_filter(array_map('intval', $empresaIds))));

        if ($depositoIds === [] || $empresaIds === []) {
            return [];
        }

        $codigos = Depmae::query()
            ->whereIn('id', $depositoIds)
            ->pluck('codigo')
            ->map(fn ($codigo) => trim((string) $codigo))
            ->filter(fn (string $codigo) => $codigo !== '')
            ->unique()
            ->values()
            ->all();

        if ($codigos === []) {
            return [];
        }

        return Depmae::query()
            ->whereIn('empresa_id', $empresaIds)
            ->whereIn('codigo', $codigos)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * IDs de depmae autorizados para filtrar artículos por depositoentrega_id.
     * Expande por código (multiempresa). null = sin restricción de usuario.
     *
     * @return list<int>|null
     */
    public static function idsParaFiltroArticulo(?int $depositoIdFijo = null): ?array
    {
        $ids = self::idsRestringidos();
        if ($ids === null) {
            return null;
        }

        $codigos = self::codigosAutorizados() ?? [];
        if ($codigos === []) {
            return [];
        }

        if ($depositoIdFijo !== null && $depositoIdFijo > 0) {
            if (! self::depositoAutorizado($depositoIdFijo)) {
                return [];
            }

            $codigoFijo = trim((string) (Depmae::query()->whereKey($depositoIdFijo)->value('codigo') ?? ''));
            if ($codigoFijo === '' || ! in_array($codigoFijo, $codigos, true)) {
                return [];
            }

            $codigos = [$codigoFijo];
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
     * Acota artículos al depósito de entrega de los depósitos autorizados del usuario.
     * Sin filas en usuario_deposito = no restringe.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function aplicarFiltroArticuloPorDepositoEntrega(Builder $query, ?int $depositoIdFijo = null): Builder
    {
        $ids = self::idsParaFiltroArticulo($depositoIdFijo);
        if ($ids === null) {
            return $query;
        }

        if ($ids === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereIn('articulo.depositoentrega_id', $ids);
    }

    public static function articuloAutorizadoPorDepositoEntrega(?int $depositoEntregaId): bool
    {
        $ids = self::idsParaFiltroArticulo();
        if ($ids === null) {
            return true;
        }

        if ($depositoEntregaId === null || $depositoEntregaId <= 0) {
            return false;
        }

        return in_array($depositoEntregaId, $ids, true);
    }
}
