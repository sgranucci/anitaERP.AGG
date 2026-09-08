<?php

namespace App\Support\Compras;

use App\Models\Compras\Suscripcion_Comprobante;
use App\Services\Compras\SuscripcionComprobanteService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Digest de comprobantes de portal pendientes (dueño / escalamiento).
 */
final class SuscripcionComprobantePendienteSupport
{
    /**
     * @return array{cantidad: int, fecha: string, filas: list<array<string, string>>, filas_mail: list<string>}
     */
    public static function recopilarParaOwner(int $ownerUsuarioId): array
    {
        $filas = Suscripcion_Comprobante::query()
            ->with(['ordencompras', 'suscripcion_cargos'])
            ->where('estado', Suscripcion_Comprobante::ESTADO_PENDIENTE)
            ->whereHas('ordencompras', fn ($q) => $q
                ->where('es_suscripcion', true)
                ->where('suscripcion_owner_usuario_id', $ownerUsuarioId))
            ->orderBy('periodo')
            ->get();

        return self::armarDigest($filas);
    }

    /**
     * Pendientes que ya alcanzaron el umbral de escalamiento (config dia_escalamiento).
     *
     * @return array{cantidad: int, fecha: string, filas: list<array<string, string>>, filas_mail: list<string>}
     */
    public static function recopilarEscalamientos(?Carbon $hoy = null): array
    {
        $hoy = $hoy?->copy()->startOfDay() ?? Carbon::today();
        $servicio = app(SuscripcionComprobanteService::class);

        $filas = Suscripcion_Comprobante::query()
            ->with(['ordencompras.empresas', 'ordencompras.suscripcion_owners', 'suscripcion_cargos'])
            ->where('estado', Suscripcion_Comprobante::ESTADO_PENDIENTE)
            ->whereHas('ordencompras', fn ($q) => $q->where('es_suscripcion', true))
            ->orderBy('periodo')
            ->get()
            ->filter(fn (Suscripcion_Comprobante $c) => $servicio->debeEscalar($c, $hoy))
            ->values();

        return self::armarDigest($filas);
    }

    public static function hayPendientes(array $digest): bool
    {
        return (int) ($digest['cantidad'] ?? 0) > 0;
    }

    /**
     * @param  Collection<int, Suscripcion_Comprobante>  $filas
     * @return array{cantidad: int, fecha: string, filas: list<array<string, string>>, filas_mail: list<string>}
     */
    private static function armarDigest(Collection $filas): array
    {
        $lista = [];
        $mail = [];
        foreach ($filas as $c) {
            $oc = $c->ordencompras;
            $cargo = $c->suscripcion_cargos;
            $linea = [
                'periodo' => (string) $c->periodo,
                'suscripcion' => (string) ($oc->suscripcion_nombre ?: $oc->detalle ?: '—'),
                'area' => (string) ($oc->suscripcion_area ?: '—'),
                'dueno' => (string) (optional($oc->suscripcion_owners)->nombre ?: '—'),
                'comercio' => (string) (optional($cargo)->comercio ?: '—'),
                'monto' => $cargo
                    ? number_format((float) $cargo->monto, 2, ',', '.')
                    : '—',
            ];
            $lista[] = $linea;
            $mail[] = sprintf(
                '%s · %s · %s · %s · $%s',
                $linea['periodo'],
                $linea['suscripcion'],
                $linea['area'],
                $linea['comercio'],
                $linea['monto']
            );
        }

        return [
            'cantidad' => count($lista),
            'fecha' => now()->format('d/m/Y'),
            'filas' => $lista,
            'filas_mail' => $mail,
            'ids' => $filas->pluck('id')->map(fn ($id) => (int) $id)->all(),
        ];
    }

    /**
     * @param  list<string>  $lineas
     */
    public static function formatearLista(array $lineas, int $cantidad): string
    {
        if ($lineas === []) {
            return '(sin ítems)';
        }
        $max = 30;
        $slice = array_slice($lineas, 0, $max);
        $texto = '- '.implode("\n- ", $slice);
        if ($cantidad > $max) {
            $texto .= "\n… y ".($cantidad - $max).' más';
        }

        return $texto;
    }
}
