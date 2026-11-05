<?php

declare(strict_types=1);

namespace App\Support\Contable;

use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;

/**
 * Si la cuenta maneja centro de costo y tiene centros habilitados, la línea
 * solo puede usar uno de esos. Sin lista cargada no hay restricción.
 */
final class CuentaCentrocostoHabilitadoSupport
{
    /**
     * @param  array<string, mixed>  $payload  cuentacontable_ids[], centrocosto_ids[], debes[], haberes[]
     */
    public static function assertPayload(array $payload, string $proceso): void
    {
        self::assertPares(self::paresDesdePayload($payload), $proceso);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    public static function mensajesPayload(array $payload): array
    {
        return self::mensajes(self::paresDesdePayload($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{cuentacontable_id: int, centrocosto_id: int, etiqueta: string}>
     */
    private static function paresDesdePayload(array $payload): array
    {
        $cuentas = is_array($payload['cuentacontable_ids'] ?? null) ? $payload['cuentacontable_ids'] : [];
        $centros = is_array($payload['centrocosto_ids'] ?? null) ? $payload['centrocosto_ids'] : [];
        $debes = is_array($payload['debes'] ?? null) ? $payload['debes'] : [];
        $haberes = is_array($payload['haberes'] ?? null) ? $payload['haberes'] : [];
        $n = max(count($cuentas), count($centros), count($debes), count($haberes));
        $pares = [];

        for ($i = 0; $i < $n; $i++) {
            $cuentaId = (int) ($cuentas[$i] ?? 0);
            if ($cuentaId <= 0) {
                continue;
            }
            if ($debes !== [] || $haberes !== []) {
                $debe = AsientoBalanceSupport::parseMonto($debes[$i] ?? null);
                $haber = AsientoBalanceSupport::parseMonto($haberes[$i] ?? null);
                if ($debe <= 0 && $haber <= 0) {
                    continue;
                }
            }
            $pares[] = [
                'cuentacontable_id' => $cuentaId,
                'centrocosto_id' => (int) ($centros[$i] ?? 0),
                'etiqueta' => 'Línea '.($i + 1),
            ];
        }

        return $pares;
    }

    /**
     * @param  list<array{cuentacontable_id: int, centrocosto_id: int, etiqueta?: string}>  $pares
     */
    public static function assertPares(array $pares, string $proceso): void
    {
        $mensajes = self::mensajes($pares);
        if ($mensajes === []) {
            return;
        }

        throw new \InvalidArgumentException($proceso.' '.implode(' ', $mensajes));
    }

    public static function mensaje(int $cuentaId, int $centrocostoId): ?string
    {
        $mensajes = self::mensajes([[
            'cuentacontable_id' => $cuentaId,
            'centrocosto_id' => $centrocostoId,
            'etiqueta' => '',
        ]]);

        return $mensajes[0] ?? null;
    }

    /**
     * @param  list<array{cuentacontable_id: int, centrocosto_id: int, etiqueta?: string}>  $pares
     * @return list<string>
     */
    public static function mensajes(array $pares): array
    {
        $rechazos = [];
        $cuentaIds = [];
        $ccIds = [];
        foreach ($pares as $par) {
            $cuentaId = (int) ($par['cuentacontable_id'] ?? 0);
            $ccId = (int) ($par['centrocosto_id'] ?? 0);
            if ($cuentaId <= 0) {
                continue;
            }
            $restriccion = CuentaCentrocostoAsignadosSupport::restriccion($cuentaId);
            if (! $restriccion['restringe'] || ($ccId > 0 && in_array($ccId, $restriccion['ids'], true))) {
                continue;
            }
            $cuentaIds[] = $cuentaId;
            if ($ccId > 0) {
                $ccIds[] = $ccId;
            }
            $rechazos[] = [
                'cuentacontable_id' => $cuentaId,
                'centrocosto_id' => $ccId,
                'etiqueta' => trim((string) ($par['etiqueta'] ?? '')),
            ];
        }

        if ($rechazos === []) {
            return [];
        }

        $cuentas = Cuentacontable::query()
            ->whereIn('id', array_values(array_unique($cuentaIds)))
            ->get(['id', 'codigo', 'nombre'])
            ->keyBy('id');
        $centros = $ccIds === []
            ? collect()
            : Centrocosto::query()
                ->whereIn('id', array_values(array_unique($ccIds)))
                ->get(['id', 'codigo', 'nombre'])
                ->keyBy('id');

        $out = [];
        foreach ($rechazos as $rechazo) {
            $cuenta = $cuentas->get($rechazo['cuentacontable_id']);
            $codigo = trim((string) ($cuenta->codigo ?? ''));
            $nombre = trim((string) ($cuenta->nombre ?? ''));
            $etiquetaCuenta = trim($codigo.' '.$nombre);
            if ($etiquetaCuenta === '') {
                $etiquetaCuenta = 'cuenta '.$rechazo['cuentacontable_id'];
            }
            $prefijo = $rechazo['etiqueta'] !== '' ? $rechazo['etiqueta'].': ' : '';
            $cc = $centros->get($rechazo['centrocosto_id']);
            if ($cc === null) {
                $out[] = $prefijo.'la cuenta '.$etiquetaCuenta
                    .' tiene centros de costo habilitados y la línea no usa uno de ellos.';

                continue;
            }
            $codigoCc = trim((string) ($cc->codigo ?? ''));
            $nombreCc = trim((string) ($cc->nombre ?? ''));
            $etiquetaCc = trim($codigoCc.' '.$nombreCc);
            $out[] = $prefijo.'el centro de costo '.$etiquetaCc.' no está habilitado en la cuenta '
                .$etiquetaCuenta.'.';
        }

        return $out;
    }
}
