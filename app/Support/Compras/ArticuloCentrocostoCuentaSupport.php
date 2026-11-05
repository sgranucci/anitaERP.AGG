<?php

namespace App\Support\Compras;

use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use App\Models\Contable\Cuentacontable_Centrocosto;
use App\Models\Stock\Articulo;
use App\Support\Contable\AsientoCentrocostoObligatorioSupport;
use App\Support\Stock\ArticuloCuentaCompraGastoSupport;

/**
 * En requisiciones y órdenes de compra el centro de costo se controla solo
 * contra la cuenta de compras del artículo. La de gastos no entra en este circuito.
 * Si la cuenta de compras maneja centro de costo y tiene centros habilitados,
 * la línea solo puede usar uno de esos centros. Sin lista cargada, cualquiera vale.
 */
final class ArticuloCentrocostoCuentaSupport
{
    /**
     * @param  array{cuentacontable_id?: int, codigo?: string, nombre?: string, tipo?: string}|null  $cuenta
     * @return array{restringe_centrocosto: bool, centrocosto_ids: list<int>}
     */
    public static function datosRespuesta(?array $cuenta): array
    {
        $vacio = [
            'restringe_centrocosto' => false,
            'centrocosto_ids' => [],
        ];
        if ($cuenta === null || ! self::esCuentaCompras($cuenta)) {
            return $vacio;
        }

        $cuentaId = (int) ($cuenta['cuentacontable_id'] ?? 0);
        $mapa = self::mapaPorCuentas([$cuentaId]);
        $fila = $mapa[$cuentaId] ?? null;
        if ($fila === null) {
            return $vacio;
        }

        return [
            'restringe_centrocosto' => $fila['restringe'],
            'centrocosto_ids' => $fila['ids'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload  articulo_ids[], cantidades[], centrocostodestino_ids[], empresa_id, centrocosto_id
     *
     * @throws \InvalidArgumentException
     */
    public static function validarPayload(array $payload): void
    {
        $articuloIds = $payload['articulo_ids'] ?? [];
        if (! is_array($articuloIds) || $articuloIds === []) {
            return;
        }

        $empresaId = (int) ($payload['empresa_id'] ?? 0);
        $ccCabecera = (int) ($payload['centrocosto_id'] ?? 0);
        $centrosLinea = is_array($payload['centrocostodestino_ids'] ?? null) ? $payload['centrocostodestino_ids'] : [];
        $cantidades = is_array($payload['cantidades'] ?? null) ? $payload['cantidades'] : [];

        $ids = [];
        $n = count($articuloIds);
        for ($i = 0; $i < $n; $i++) {
            $articuloId = (int) ($articuloIds[$i] ?? 0);
            if ($articuloId <= 0 || (float) ($cantidades[$i] ?? 0) <= 0) {
                continue;
            }
            $ids[] = $articuloId;
        }
        if ($ids === [] || $empresaId <= 0) {
            return;
        }

        $cuentas = ArticuloCuentaCompraGastoSupport::etiquetasPorArticulos($ids, $empresaId);
        $cuentaIds = [];
        foreach ($cuentas as $cuenta) {
            if (! self::esCuentaCompras($cuenta)) {
                continue;
            }
            $cuentaId = (int) ($cuenta['cuentacontable_id'] ?? 0);
            if ($cuentaId > 0) {
                $cuentaIds[] = $cuentaId;
            }
        }
        $mapa = self::mapaPorCuentas($cuentaIds);

        $articulos = Articulo::query()
            ->whereIn('id', array_values(array_unique($ids)))
            ->get(['id', 'sku', 'descripcion'])
            ->keyBy('id');

        $rechazos = [];
        $ccIdsMensaje = [];
        for ($i = 0; $i < $n; $i++) {
            $articuloId = (int) ($articuloIds[$i] ?? 0);
            if ($articuloId <= 0 || (float) ($cantidades[$i] ?? 0) <= 0) {
                continue;
            }
            $cuenta = $cuentas[$articuloId] ?? null;
            if ($cuenta === null || ! self::esCuentaCompras($cuenta)) {
                continue;
            }
            $cuentaId = (int) ($cuenta['cuentacontable_id'] ?? 0);
            $restriccion = $mapa[$cuentaId] ?? null;
            if ($restriccion === null || ! $restriccion['restringe']) {
                continue;
            }
            $ccRaw = $centrosLinea[$i] ?? null;
            $ccId = ($ccRaw === null || $ccRaw === '') ? $ccCabecera : (int) $ccRaw;
            if ($ccId > 0 && in_array($ccId, $restriccion['ids'], true)) {
                continue;
            }
            $ccIdsMensaje[] = $ccId;
            $rechazos[] = [
                'linea' => $i + 1,
                'articulo_id' => $articuloId,
                'centrocosto_id' => $ccId,
                'cuenta' => $cuenta,
            ];
        }

        if ($rechazos === []) {
            return;
        }

        $nombresCc = self::etiquetasCentrocosto($ccIdsMensaje);
        $partes = [];
        foreach ($rechazos as $rechazo) {
            $art = $articulos->get($rechazo['articulo_id']);
            $sku = trim((string) ($art->sku ?? ''));
            $desc = trim((string) ($art->descripcion ?? ''));
            if (mb_strlen($desc) > 60) {
                $desc = mb_substr($desc, 0, 57).'…';
            }
            $etiquetaArt = $sku !== '' ? $sku : ('artículo '.$rechazo['articulo_id']);
            if ($desc !== '') {
                $etiquetaArt .= ' '.$desc;
            }
            $ccId = (int) $rechazo['centrocosto_id'];
            $etiquetaCc = $nombresCc[$ccId] ?? ($ccId > 0 ? (string) $ccId : 'sin centro de costo');
            $codigoCuenta = trim((string) ($rechazo['cuenta']['codigo'] ?? ''));
            $nombreCuenta = trim((string) ($rechazo['cuenta']['nombre'] ?? ''));
            $etiquetaCuenta = trim($codigoCuenta.' '.$nombreCuenta);
            $partes[] = 'línea '.$rechazo['linea'].' ('.$etiquetaArt.', centro '.$etiquetaCc.', cuenta '.$etiquetaCuenta.')';
        }

        throw new \InvalidArgumentException(
            'Hay artículos con un centro de costo que la cuenta contable no tiene habilitado: '
            .implode('; ', $partes)
            .'. Habilite el centro en la cuenta o elija otro artículo.'
        );
    }

    /**
     * @param  array{tipo?: string}  $cuenta
     */
    private static function esCuentaCompras(array $cuenta): bool
    {
        return strtoupper(trim((string) ($cuenta['tipo'] ?? ''))) === 'COMPRAS';
    }

    /**
     * @param  list<int>  $cuentaIds
     * @return array<int, array{restringe: bool, ids: list<int>}>
     */
    private static function mapaPorCuentas(array $cuentaIds): array
    {
        $ids = [];
        foreach ($cuentaIds as $id) {
            $id = (int) $id;
            if ($id > 0 && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }
        if ($ids === []) {
            return [];
        }

        $flags = Cuentacontable::query()
            ->whereIn('id', $ids)
            ->pluck('manejaccosto', 'id');

        $porCuenta = [];
        $filas = Cuentacontable_Centrocosto::query()
            ->whereIn('cuentacontable_id', $ids)
            ->orderBy('centrocosto_id')
            ->get(['cuentacontable_id', 'centrocosto_id']);
        foreach ($filas as $fila) {
            $cuentaId = (int) $fila->cuentacontable_id;
            $ccId = (int) $fila->centrocosto_id;
            if ($ccId <= 0) {
                continue;
            }
            if (! isset($porCuenta[$cuentaId])) {
                $porCuenta[$cuentaId] = [];
            }
            if (! in_array($ccId, $porCuenta[$cuentaId], true)) {
                $porCuenta[$cuentaId][] = $ccId;
            }
        }

        $out = [];
        foreach ($ids as $cuentaId) {
            $lista = $porCuenta[$cuentaId] ?? [];
            $maneja = AsientoCentrocostoObligatorioSupport::maneja($flags[$cuentaId] ?? null);
            $out[$cuentaId] = [
                'restringe' => $maneja && $lista !== [],
                'ids' => $lista,
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private static function etiquetasCentrocosto(array $ids): array
    {
        $limpios = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0 && ! in_array($id, $limpios, true)) {
                $limpios[] = $id;
            }
        }
        if ($limpios === []) {
            return [];
        }

        $out = [];
        $filas = Centrocosto::query()->whereIn('id', $limpios)->get(['id', 'codigo', 'nombre']);
        foreach ($filas as $fila) {
            $codigo = trim((string) ($fila->codigo ?? ''));
            $nombre = trim((string) ($fila->nombre ?? ''));
            $texto = $codigo !== '' && $nombre !== '' ? $codigo.' '.$nombre : ($codigo !== '' ? $codigo : $nombre);
            $out[(int) $fila->id] = $texto !== '' ? $texto : (string) $fila->id;
        }

        return $out;
    }
}
