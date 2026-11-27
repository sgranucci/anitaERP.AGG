<?php

namespace App\Support\Compras;

use App\Models\Compras\Proveedor_Servicio;
use Illuminate\Support\Collection;

/**
 * Medidor / nro. de cliente del proveedor (tabla proveedor_servicio) asignable
 * a precarga y comprobante (p. ej. factura Edesur).
 *
 * El maestro borra/recrea filas al guardar el proveedor: la FK puede quedar null.
 * Por eso también se persiste servicio_cliente (snapshot) y se reengancha por texto.
 */
final class ComprobanteProveedorServicioSupport
{
    /**
     * @return Collection<int, Proveedor_Servicio>
     */
    public static function listarPorProveedor(?int $proveedorId): Collection
    {
        if ($proveedorId === null || $proveedorId <= 0) {
            return collect();
        }

        return Proveedor_Servicio::query()
            ->where('proveedor_id', $proveedorId)
            ->orderBy('cliente')
            ->get(['id', 'proveedor_id', 'empresa_id', 'cliente', 'detalle']);
    }

    public static function etiqueta(object $servicio): string
    {
        $cliente = trim((string) ($servicio->cliente ?? ''));
        $detalle = trim((string) ($servicio->detalle ?? ''));

        if ($cliente === '') {
            return $detalle !== '' ? $detalle : ('#'.(int) ($servicio->id ?? 0));
        }

        return $detalle !== '' ? $cliente.' — '.$detalle : $cliente;
    }

    public static function leyendaSugerida(object $servicio): string
    {
        $etiqueta = self::etiqueta($servicio);

        return mb_substr('Cliente '.$etiqueta, 0, 255);
    }

    public static function leyendaDesdeCliente(?string $cliente): ?string
    {
        $cliente = trim((string) $cliente);
        if ($cliente === '') {
            return null;
        }

        return mb_substr('Cliente '.$cliente, 0, 255);
    }

    /**
     * Resuelve FK + snapshot. Reengancha por nro. de cliente si el id ya no existe
     * (borrado/recreación del maestro). Si hay un solo medidor y no hay dato, lo asigna.
     *
     * @return array{proveedor_servicio_id: ?int, servicio_cliente: ?string}
     */
    public static function resolverAsignacion(
        mixed $servicioId,
        ?int $proveedorId,
        mixed $servicioCliente = null,
    ): array {
        $vacio = ['proveedor_servicio_id' => null, 'servicio_cliente' => null];
        if ($proveedorId === null || $proveedorId <= 0) {
            return $vacio;
        }

        $clienteSnap = trim((string) ($servicioCliente ?? ''));
        $id = (int) $servicioId;

        if ($id > 0) {
            $serv = Proveedor_Servicio::query()
                ->whereKey($id)
                ->where('proveedor_id', $proveedorId)
                ->first(['id', 'cliente', 'detalle']);
            if ($serv) {
                return [
                    'proveedor_servicio_id' => (int) $serv->id,
                    'servicio_cliente' => mb_substr(trim((string) $serv->cliente), 0, 255) ?: null,
                ];
            }
        }

        if ($clienteSnap !== '') {
            $serv = Proveedor_Servicio::query()
                ->where('proveedor_id', $proveedorId)
                ->where('cliente', $clienteSnap)
                ->orderBy('id')
                ->first(['id', 'cliente']);
            if ($serv) {
                return [
                    'proveedor_servicio_id' => (int) $serv->id,
                    'servicio_cliente' => mb_substr(trim((string) $serv->cliente), 0, 255) ?: $clienteSnap,
                ];
            }

            // Snapshot conserva el nro. aunque el maestro ya no lo tenga.
            return [
                'proveedor_servicio_id' => null,
                'servicio_cliente' => mb_substr($clienteSnap, 0, 255),
            ];
        }

        $unicos = Proveedor_Servicio::query()
            ->where('proveedor_id', $proveedorId)
            ->orderBy('id')
            ->limit(2)
            ->get(['id', 'cliente']);

        if ($unicos->count() === 1) {
            $serv = $unicos->first();

            return [
                'proveedor_servicio_id' => (int) $serv->id,
                'servicio_cliente' => mb_substr(trim((string) $serv->cliente), 0, 255) ?: null,
            ];
        }

        return $vacio;
    }

    /**
     * @deprecated Preferir resolverAsignacion(); se mantiene para callers existentes.
     */
    public static function idValidoParaProveedor(mixed $servicioId, ?int $proveedorId): ?int
    {
        return self::resolverAsignacion($servicioId, $proveedorId)['proveedor_servicio_id'];
    }

    /**
     * Si el proveedor tiene medidores, hace falta id o snapshot de cliente.
     *
     * @return list<string>
     */
    public static function erroresAsignacion(
        mixed $servicioId,
        ?int $proveedorId,
        mixed $servicioCliente = null,
    ): array {
        if ($proveedorId === null || $proveedorId <= 0) {
            return [];
        }

        $cantidad = Proveedor_Servicio::query()->where('proveedor_id', $proveedorId)->count();
        if ($cantidad === 0) {
            return [];
        }

        $asig = self::resolverAsignacion($servicioId, $proveedorId, $servicioCliente);
        if ($asig['proveedor_servicio_id'] === null && $asig['servicio_cliente'] === null) {
            return ['Indicá el cliente / medidor del proveedor (solapa Servicios de la ficha).'];
        }

        return [];
    }

    /**
     * @param  iterable<int, object>  $servicios
     * @return list<array{id: int, cliente: string, detalle: ?string, etiqueta: string, leyenda: string}>
     */
    public static function opcionesParaJson(iterable $servicios): array
    {
        $out = [];
        foreach ($servicios as $servicio) {
            $id = (int) ($servicio->id ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'cliente' => (string) ($servicio->cliente ?? ''),
                'detalle' => $servicio->detalle !== null ? (string) $servicio->detalle : null,
                'etiqueta' => self::etiqueta($servicio),
                'leyenda' => self::leyendaSugerida($servicio),
            ];
        }

        return $out;
    }

    /**
     * Opciones de medidor para uno o más proveedores (select / modal de reportes).
     *
     * @param  list<int>  $proveedorIds
     * @return list<array{
     *     id: int,
     *     proveedor_id: int,
     *     proveedor_codigo: string,
     *     proveedor_nombre: string,
     *     cliente: string,
     *     detalle: ?string,
     *     etiqueta: string
     * }>
     */
    public static function opcionesParaProveedores(array $proveedorIds, ?int $empresaId = null, ?string $texto = null): array
    {
        $ids = [];
        foreach ($proveedorIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return [];
        }

        $query = Proveedor_Servicio::query()
            ->with(['proveedores:id,codigo,nombre'])
            ->whereIn('proveedor_id', array_values($ids));

        // Un medidor cargado para otra empresa no se ofrece; sin empresa en la ficha vale para todas.
        if ($empresaId !== null && $empresaId > 0) {
            $query->where(function ($q) use ($empresaId) {
                $q->where('empresa_id', $empresaId)->orWhereNull('empresa_id');
            });
        }

        $texto = trim((string) $texto);
        if ($texto !== '') {
            $like = '%'.$texto.'%';
            $query->where(function ($q) use ($like) {
                $q->where('cliente', 'like', $like)->orWhere('detalle', 'like', $like);
            });
        }

        $servicios = $query
            ->orderBy('proveedor_id')
            ->orderBy('cliente')
            ->limit(500)
            ->get(['id', 'proveedor_id', 'empresa_id', 'cliente', 'detalle']);

        $out = [];
        foreach ($servicios as $servicio) {
            $cliente = trim((string) ($servicio->cliente ?? ''));
            if ($cliente === '') {
                continue;
            }
            $codigo = (string) ($servicio->proveedores->codigo ?? '');
            $nombre = (string) ($servicio->proveedores->nombre ?? '');
            $etiq = self::etiqueta($servicio);
            $prefijo = $codigo !== '' ? $codigo.' · ' : '';
            $out[] = [
                'id' => (int) $servicio->id,
                'proveedor_id' => (int) $servicio->proveedor_id,
                'proveedor_codigo' => $codigo,
                'proveedor_nombre' => $nombre,
                'cliente' => $cliente,
                'detalle' => $servicio->detalle !== null ? (string) $servicio->detalle : null,
                'etiqueta' => $prefijo.$etiq,
            ];
        }

        return $out;
    }
}
