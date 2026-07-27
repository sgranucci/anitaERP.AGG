<?php

namespace App\Support\Compras;

use App\Repositories\Compras\ProveedorRepositoryInterface;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Resolución del proveedor en el MVP interno del portal.
 * En la versión externa el ID vendrá solo de la sesión autenticada.
 */
final class PortalProveedorContexto
{
    public static function proveedorIdDesdeRequest(Request $request): int
    {
        return (int) $request->input('proveedor_id', 0);
    }

    /**
     * @return object|null Modelo proveedor o null si no hay ID
     */
    public static function resolverProveedor(
        Request $request,
        ProveedorRepositoryInterface $proveedorRepository
    ): ?object {
        $proveedorId = self::proveedorIdDesdeRequest($request);
        if ($proveedorId <= 0) {
            return null;
        }

        $proveedor = $proveedorRepository->find($proveedorId);
        if (! $proveedor) {
            abort(404, 'Proveedor inexistente.');
        }

        return $proveedor;
    }

    public static function assertProveedorExiste(
        int $proveedorId,
        ProveedorRepositoryInterface $proveedorRepository
    ): void {
        if ($proveedorId <= 0 || ! $proveedorRepository->find($proveedorId)) {
            throw new RuntimeException('Proveedor inexistente.');
        }
    }

    /**
     * Query string base para conservar el proveedor al navegar el portal.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function queryBase(int $proveedorId, array $extra = []): array
    {
        $base = $proveedorId > 0 ? ['proveedor_id' => $proveedorId] : [];

        return array_merge($base, $extra);
    }
}
