<?php

declare(strict_types=1);

namespace App\Support\Ventas\GastronomiaCrown;

use App\Models\Configuracion\Sala;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\Venta;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownSalas;

/**
 * Bridge Informix de escritura gastronomía Crown por sala.
 *
 * Solo gastronomía. Cipolletti (CIP) → cncadmin. Otras salas: mapa de transferencia
 * (AC → acncadmin, etc.). Sin sala conocida no pisa el IFX_SERVER global del .env.
 */
final class GastronomiaCrownAnitaBridgeSupport
{
    /**
     * @return array{ifx_server: string, path_sistema: string, sala_codigo: string}|null
     */
    public static function parametrosEscritura(?string $salaCodigo = null): ?array
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return null;
        }

        $sala = strtoupper(trim((string) $salaCodigo));
        if ($sala === '') {
            $sala = self::salaCodigoJornadaAbierta() ?? '';
        }
        if ($sala === '') {
            return null;
        }

        $ifx = self::ifxServerParaSala($sala);
        if ($ifx === null || $ifx === '') {
            return null;
        }

        $path = trim((string) config('gastronomia.crown.numerador_anita_path_sistema', '/usr2/casino'));
        if ($path === '') {
            $path = '/usr2/casino';
        }

        return [
            'ifx_server' => $ifx,
            'path_sistema' => $path,
            'sala_codigo' => $sala,
        ];
    }

    /**
     * @return array{ifx_server: string, path_sistema: string, sala_codigo: string}|null
     */
    public static function parametrosEscrituraDesdeVenta(Venta $venta): ?array
    {
        return self::parametrosEscritura(self::salaCodigoDesdeVenta($venta));
    }

    public static function salaCodigoDesdeVenta(Venta $venta): ?string
    {
        $venta->loadMissing([
            'gastronomiaEmision.configuracionPuntoventa.sala:id,codigo',
        ]);
        $codigo = strtoupper(trim((string) (
            $venta->gastronomiaEmision?->configuracionPuntoventa?->sala?->codigo ?? ''
        )));
        if ($codigo !== '') {
            return $codigo;
        }

        return self::salaCodigoJornadaAbierta();
    }

    /**
     * CIP → cncadmin; resto según ventas_transferencia_crown / AC → acncadmin.
     */
    public static function ifxServerParaSala(string $salaCodigo): ?string
    {
        $sala = strtoupper(trim($salaCodigo));
        if ($sala === '') {
            return null;
        }

        // Gastro Cipolletti: cncadmin (no el default acncadmin del .env).
        if ($sala === 'CIP') {
            $ifx = trim((string) config('gastronomia.crown.numerador_anita_ifx_server', 'cncadmin'));

            return $ifx !== '' ? $ifx : 'cncadmin';
        }

        $mapa = TransferenciaCrownSalas::gastronomiaPorCodigo($sala);
        if ($mapa !== null) {
            $ifx = trim((string) ($mapa['ifx_server'] ?? ''));
            if ($ifx !== '') {
                return $ifx;
            }
        }

        if ($sala === 'AC') {
            return 'acncadmin';
        }

        return null;
    }

    public static function salaCodigoJornadaAbierta(): ?string
    {
        if (! EntornoEmpresaSupport::esCrown() || ! class_exists(JornadaGastronomia::class)) {
            return null;
        }

        $jornada = JornadaGastronomia::query()
            ->with(['sala:id,codigo'])
            ->where('estado', JornadaGastronomia::ESTADO_ABIERTA)
            ->orderByDesc('id')
            ->first();
        $codigo = strtoupper(trim((string) ($jornada?->sala?->codigo ?? '')));
        if ($codigo !== '') {
            return $codigo;
        }

        $salaId = (int) ($jornada?->sala_id ?? 0);
        if ($salaId <= 0) {
            return null;
        }

        $codigo = strtoupper(trim((string) (Sala::query()->whereKey($salaId)->value('codigo') ?? '')));

        return $codigo !== '' ? $codigo : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function mergePayload(array $payload, ?string $salaCodigo = null): array
    {
        $params = self::parametrosEscritura($salaCodigo);
        if ($params === null) {
            return $payload;
        }
        if (! isset($payload['ifx_server']) || trim((string) $payload['ifx_server']) === '') {
            $payload['ifx_server'] = $params['ifx_server'];
        }
        if (! isset($payload['path_sistema']) || trim((string) $payload['path_sistema']) === '') {
            $payload['path_sistema'] = $params['path_sistema'];
        }

        return $payload;
    }
}
