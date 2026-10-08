<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Cuentacontable;

/**
 * Cuenta de compras o gastos del artículo para una empresa.
 *
 * Prioridad de la grilla del artículo: COMPRAS de esa empresa y, si no hay, GASTOS.
 * Si la grilla no tiene fila, se usa la cuenta de compras del maestro solo cuando
 * pertenece a la misma empresa. Es la cuenta que contaduría imputa al facturar.
 */
final class ArticuloCuentaCompraGastoSupport
{
    /**
     * @return array{cuentacontable_id: int, codigo: string, nombre: string, tipo: string}|null
     */
    public static function resolver(?Articulo $articulo, int $empresaId): ?array
    {
        if (! $articulo || $empresaId <= 0) {
            return null;
        }

        $mapa = self::etiquetasPorArticulos([(int) $articulo->id], $empresaId);

        return $mapa[(int) $articulo->id] ?? null;
    }

    /**
     * @param  list<int>  $articuloIds
     * @return array<int, array{cuentacontable_id: int, codigo: string, nombre: string, tipo: string}>
     */
    public static function etiquetasPorArticulos(array $articuloIds, int $empresaId): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $articuloIds),
            static fn (int $id) => $id > 0
        )));
        if ($ids === [] || $empresaId <= 0) {
            return [];
        }

        $filas = Articulo_Cuentacontable::query()
            ->with(['cuentacontables:id,codigo,nombre'])
            ->where('empresa_id', $empresaId)
            ->whereIn('articulo_id', $ids)
            ->orderBy('id')
            ->get();

        $porTipo = [];
        foreach ($filas as $fila) {
            $tipo = strtoupper(trim((string) $fila->tipoimputacion));
            if (! in_array($tipo, ['COMPRAS', 'GASTOS'], true) || (int) $fila->cuentacontable_id <= 0) {
                continue;
            }
            $artId = (int) $fila->articulo_id;
            if (! isset($porTipo[$artId][$tipo])) {
                $porTipo[$artId][$tipo] = $fila;
            }
        }

        $out = [];
        foreach ($ids as $artId) {
            $fila = $porTipo[$artId]['COMPRAS'] ?? $porTipo[$artId]['GASTOS'] ?? null;
            if (! $fila instanceof Articulo_Cuentacontable) {
                continue;
            }
            $cta = $fila->cuentacontables;
            $out[$artId] = [
                'cuentacontable_id' => (int) $fila->cuentacontable_id,
                'codigo' => trim((string) ($cta->codigo ?? '')),
                'nombre' => trim((string) ($cta->nombre ?? '')),
                'tipo' => strtoupper(trim((string) $fila->tipoimputacion)) === 'GASTOS' ? 'GASTOS' : 'COMPRAS',
            ];
        }

        $faltan = array_values(array_diff($ids, array_keys($out)));
        if ($faltan !== []) {
            $arts = Articulo::query()
                ->with(['cuentascontablescompras:id,codigo,nombre,empresa_id'])
                ->whereIn('id', $faltan)
                ->where('cuentacontablecompra_id', '>', 0)
                ->get(['id', 'cuentacontablecompra_id']);
            foreach ($arts as $art) {
                $cta = $art->cuentascontablescompras;
                if ($cta === null) {
                    continue;
                }
                $empCuenta = (int) ($cta->empresa_id ?? 0);
                if ($empCuenta > 0 && $empCuenta !== $empresaId) {
                    continue;
                }
                $out[(int) $art->id] = [
                    'cuentacontable_id' => (int) $cta->id,
                    'codigo' => trim((string) ($cta->codigo ?? '')),
                    'nombre' => trim((string) ($cta->nombre ?? '')),
                    'tipo' => 'COMPRAS',
                ];
            }
        }

        return $out;
    }

    /**
     * @param  array{codigo?: string, nombre?: string, tipo?: string}|null  $cuenta
     */
    public static function textoVisible(?array $cuenta): string
    {
        if ($cuenta === null) {
            return '';
        }
        $codigo = trim((string) ($cuenta['codigo'] ?? ''));
        $nombre = trim((string) ($cuenta['nombre'] ?? ''));
        if ($codigo !== '' && $nombre !== '') {
            return $codigo.' · '.$nombre;
        }

        return $codigo !== '' ? $codigo : $nombre;
    }

    /**
     * @param  array{codigo?: string, nombre?: string, tipo?: string}|null  $cuenta
     */
    public static function titulo(?array $cuenta): string
    {
        $texto = self::textoVisible($cuenta);
        if ($texto === '') {
            return '';
        }
        $tipo = strtoupper(trim((string) ($cuenta['tipo'] ?? '')));
        $rotulo = $tipo === 'GASTOS' ? 'Cuenta de gastos' : 'Cuenta de compras';

        return $rotulo.' de la empresa: '.$texto;
    }
}
