<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente;

/**
 * Sugerencias NL al tipear: catálogo por rol/permiso + favoritos + recientes.
 */
final class ConsultaInteligenteNlSugerenciasSupport
{
    /**
     * @return list<array{texto: string, grupo: string, entidad?: string}>
     */
    public static function paraUsuario(?string $prefijo = null, int $limite = 10, ?int $usuarioId = null): array
    {
        $prefijo = trim(mb_strtolower((string) $prefijo));
        $limite = max(1, min(20, $limite));
        $out = [];
        $vistos = [];

        $push = static function (string $texto, string $grupo, ?string $entidad = null) use (&$out, &$vistos, $prefijo): void {
            $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? $texto);
            if ($texto === '') {
                return;
            }
            $key = mb_strtolower($texto);
            if (isset($vistos[$key])) {
                return;
            }
            if ($prefijo !== '' && ! str_contains($key, $prefijo)) {
                return;
            }
            $vistos[$key] = true;
            $item = ['texto' => $texto, 'grupo' => $grupo];
            if ($entidad !== null) {
                $item['entidad'] = $entidad;
            }
            $out[] = $item;
        };

        foreach (ConsultaInteligenteNlFavoritoSupport::listar($usuarioId) as $f) {
            $push((string) $f['pregunta'], 'favorito');
        }
        foreach (ConsultaInteligenteNlFavoritoSupport::historialReciente(8, $usuarioId) as $h) {
            if (! empty($h['ok'])) {
                $push((string) $h['pregunta'], 'reciente', $h['entidad'] ?? null);
            }
        }

        $roles = self::rolesUsuario();
        foreach (self::catalogoBase() as $item) {
            $entidad = (string) ($item['entidad'] ?? '');
            if ($entidad !== '' && EntidadSemanticaCatalogo::existe($entidad)) {
                $ent = EntidadSemanticaCatalogo::porClave($entidad);
                if (! can($ent->permisoListar(), false)) {
                    continue;
                }
            }
            $tags = $item['roles'] ?? [];
            if ($tags !== [] && ! self::rolCoincide($roles, $tags)) {
                // Sin match de rol: igual ofrecer si tiene permiso (prioridad más baja vía orden).
                if ($prefijo === '') {
                    continue;
                }
            }
            $push((string) $item['texto'], 'sugerida', $entidad !== '' ? $entidad : null);
        }

        return array_slice($out, 0, $limite);
    }

    /**
     * Chips iniciales (sin tipear): prioriza rol.
     *
     * @return list<array{texto: string, grupo: string, entidad?: string}>
     */
    public static function chipsIniciales(int $limite = 6, ?int $usuarioId = null): array
    {
        return self::paraUsuario(null, $limite, $usuarioId);
    }

    /** @return list<string> */
    private static function rolesUsuario(): array
    {
        try {
            $u = auth()->user();
            if (! $u || ! method_exists($u, 'roles')) {
                return [];
            }
            return $u->roles()->pluck('nombre')->map(
                static fn ($n) => mb_strtolower((string) $n)
            )->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  list<string>  $rolesUsuario
     * @param  list<string>  $tags
     */
    private static function rolCoincide(array $rolesUsuario, array $tags): bool
    {
        if ($rolesUsuario === []) {
            return true;
        }
        foreach ($rolesUsuario as $r) {
            foreach ($tags as $tag) {
                if (str_contains($r, mb_strtolower($tag))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<array{texto: string, entidad: string, roles: list<string>}>
     */
    private static function catalogoBase(): array
    {
        return [
            ['texto' => 'cheques para depositar', 'entidad' => 'cheque', 'roles' => ['tesorer', 'caja', 'ger', 'admin', 'direccion']],
            ['texto' => 'cheques en cartera', 'entidad' => 'cheque', 'roles' => ['tesorer', 'caja', 'ger', 'admin']],
            ['texto' => 'cheques que vencen esta semana', 'entidad' => 'cheque', 'roles' => ['tesorer', 'caja', 'ger', 'admin']],
            ['texto' => 'aging de cheques para depositar', 'entidad' => 'cheque', 'roles' => ['tesorer', 'caja', 'ger', 'admin', 'direccion']],
            ['texto' => 'suma de cheques para depositar', 'entidad' => 'cheque', 'roles' => ['tesorer', 'caja', 'ger', 'admin', 'direccion']],
            ['texto' => 'aging de facturas de compra', 'entidad' => 'comprobante_proveedor', 'roles' => ['compra', 'contab', 'ger', 'admin', 'direccion']],
            ['texto' => 'aging de cobranzas este mes', 'entidad' => 'cobranza', 'roles' => ['tesorer', 'venta', 'caja', 'ger', 'admin']],
            ['texto' => 'facturas de compra este mes', 'entidad' => 'comprobante_proveedor', 'roles' => ['compra', 'contab', 'ger', 'admin', 'direccion']],
            ['texto' => 'facturas de venta este mes', 'entidad' => 'factura', 'roles' => ['venta', 'ger', 'admin', 'direccion']],
            ['texto' => 'facturas de venta sin remito', 'entidad' => 'factura', 'roles' => ['venta', 'stock', 'ger', 'admin']],
            ['texto' => 'pedidos pendientes este mes', 'entidad' => 'pedido', 'roles' => ['venta', 'ger', 'admin']],
            ['texto' => 'pagos a proveedor este mes', 'entidad' => 'pagoproveedor', 'roles' => ['tesorer', 'compra', 'contab', 'ger', 'admin']],
            ['texto' => 'órdenes de compra pendientes', 'entidad' => 'ordencompra', 'roles' => ['compra', 'ger', 'admin']],
            ['texto' => 'requisiciones pendientes', 'entidad' => 'requisicion', 'roles' => ['compra', 'ger', 'admin']],
            ['texto' => 'cobranzas este mes', 'entidad' => 'cobranza', 'roles' => ['tesorer', 'venta', 'caja', 'ger', 'admin']],
            ['texto' => 'remitos este mes', 'entidad' => 'remito', 'roles' => ['venta', 'stock', 'ger', 'admin']],
            ['texto' => 'transferencias de mercadería este mes', 'entidad' => 'transferencia_mercaderia', 'roles' => ['stock', 'ger', 'admin']],
            ['texto' => 'movimientos de stock este mes', 'entidad' => 'movimientostock', 'roles' => ['stock', 'ger', 'admin']],
            ['texto' => 'capex activos', 'entidad' => 'capex', 'roles' => ['presupuesto', 'ger', 'admin', 'direccion']],
            ['texto' => 'tickets abiertos', 'entidad' => 'ticket', 'roles' => ['ticket', 'sistemas', 'ger', 'admin']],
        ];
    }
}
