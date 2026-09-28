<?php

namespace App\Support\Contable;

use App\Models\Contable\Cuentacontable;

/**
 * En la carga manual de asientos, si la cuenta maneja centro de costo,
 * la línea con importe debe traer un centro de costo.
 */
final class AsientoCentrocostoObligatorioSupport
{
    public static function maneja(mixed $flag): bool
    {
        return $flag === 1 || $flag === true || in_array((string) $flag, ['S', 's', '1'], true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, array{codigo?: string, nombre?: string, manejaccosto?: mixed}>  $cuentasPorId
     * @return list<string>
     */
    public static function etiquetasSinCentrocosto(array $payload, array $cuentasPorId): array
    {
        $cuentas = is_array($payload['cuentacontable_ids'] ?? null) ? $payload['cuentacontable_ids'] : [];
        $centros = is_array($payload['centrocosto_ids'] ?? null) ? $payload['centrocosto_ids'] : [];
        $centrosPrevios = is_array($payload['centrocosto_id_previo'] ?? null) ? $payload['centrocosto_id_previo'] : [];
        $debes = is_array($payload['debes'] ?? null) ? $payload['debes'] : [];
        $haberes = is_array($payload['haberes'] ?? null) ? $payload['haberes'] : [];

        $cantidad = max(count($cuentas), count($debes), count($haberes));
        $etiquetas = [];

        for ($i = 0; $i < $cantidad; $i++) {
            $cuentaId = (int) ($cuentas[$i] ?? 0);
            if ($cuentaId <= 0) {
                continue;
            }

            $debe = AsientoBalanceSupport::parseMonto($debes[$i] ?? null);
            $haber = AsientoBalanceSupport::parseMonto($haberes[$i] ?? null);
            if ($debe <= 0 && $haber <= 0) {
                continue;
            }

            $centroId = (int) ($centros[$i] ?? $centrosPrevios[$i] ?? 0);
            if ($centroId > 0) {
                continue;
            }

            $cuenta = $cuentasPorId[$cuentaId] ?? null;
            if ($cuenta === null || ! self::maneja($cuenta['manejaccosto'] ?? null)) {
                continue;
            }

            $codigo = trim((string) ($cuenta['codigo'] ?? ''));
            $nombre = trim((string) ($cuenta['nombre'] ?? ''));
            $etiqueta = $codigo !== '' ? $codigo : (string) $cuentaId;
            if ($nombre !== '') {
                $etiqueta .= ' — '.$nombre;
            }

            $etiquetas[] = 'Línea '.($i + 1).': '.$etiqueta;
        }

        return $etiquetas;
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws \InvalidArgumentException
     */
    public static function assertDesdePayload(array $payload): void
    {
        $cuentas = is_array($payload['cuentacontable_ids'] ?? null) ? $payload['cuentacontable_ids'] : [];
        $ids = [];
        foreach ($cuentas as $cuentaId) {
            $id = (int) $cuentaId;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        $cuentasPorId = [];
        if ($ids !== []) {
            $filas = Cuentacontable::query()
                ->whereIn('id', array_values($ids))
                ->get(['id', 'codigo', 'nombre', 'manejaccosto']);

            foreach ($filas as $fila) {
                $cuentasPorId[(int) $fila->id] = [
                    'codigo' => (string) ($fila->codigo ?? ''),
                    'nombre' => (string) ($fila->nombre ?? ''),
                    'manejaccosto' => $fila->manejaccosto,
                ];
            }
        }

        $etiquetas = self::etiquetasSinCentrocosto($payload, $cuentasPorId);
        if ($etiquetas === []) {
            return;
        }

        throw new \InvalidArgumentException(
            'Indique el centro de costo en las cuentas que lo manejan: '.implode('; ', $etiquetas).'.'
        );
    }
}
