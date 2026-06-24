<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\CotRemitoEnvio;
use App\Models\Ventas\CotSesionEnvio;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CotSesionEnvioRepository
{
    /**
     * @param  array{fecha_desde?:string,fecha_hasta?:string,ambiente?:string,ok?:string}  $filtros
     */
    public function leeSesiones(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        $query = $this->querySesiones($filtros)
            ->with(['usuarios'])
            ->orderByDesc('fecha_envio')
            ->orderByDesc('id');

        if ($paginar) {
            return $query->paginate(20);
        }

        return $query->get();
    }

    public function leeSesion(int $id): ?CotSesionEnvio
    {
        return CotSesionEnvio::query()
            ->with(['usuarios', 'remitos.clientes', 'remitos.transportes'])
            ->find($id);
    }

    /**
     * @param  array{fecha_desde?:string,fecha_hasta?:string,ambiente?:string,ok?:string,session_id?:int}  $filtros
     */
    public function leeRemitosDetalle(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        $query = CotRemitoEnvio::query()
            ->with(['cotSesionEnvio.usuarios', 'clientes'])
            ->when(! empty($filtros['session_id']), fn (Builder $q) => $q->where('cot_sesion_envio_id', (int) $filtros['session_id']))
            ->when(! empty($filtros['fecha_desde']), fn (Builder $q) => $q->whereHas(
                'cotSesionEnvio',
                fn (Builder $sq) => $sq->whereDate('fecha_envio', '>=', $filtros['fecha_desde'])
            ))
            ->when(! empty($filtros['fecha_hasta']), fn (Builder $q) => $q->whereHas(
                'cotSesionEnvio',
                fn (Builder $sq) => $sq->whereDate('fecha_envio', '<=', $filtros['fecha_hasta'])
            ))
            ->when(! empty($filtros['ambiente']), fn (Builder $q) => $q->whereHas(
                'cotSesionEnvio',
                fn (Builder $sq) => $sq->where('ambiente', $filtros['ambiente'])
            ))
            ->orderByDesc('id');

        if ($paginar) {
            return $query->paginate(50);
        }

        return $query->get();
    }

    /** @param  array{fecha_desde?:string,fecha_hasta?:string,ambiente?:string,ok?:string}  $filtros */
    private function querySesiones(array $filtros): Builder
    {
        $fechaDesde = trim((string) ($filtros['fecha_desde'] ?? ''));
        $fechaHasta = trim((string) ($filtros['fecha_hasta'] ?? ''));
        $ambiente = trim((string) ($filtros['ambiente'] ?? ''));
        $ok = trim((string) ($filtros['ok'] ?? ''));

        return CotSesionEnvio::query()
            ->when($fechaDesde !== '', fn (Builder $q) => $q->whereDate('fecha_envio', '>=', $fechaDesde))
            ->when($fechaHasta !== '', fn (Builder $q) => $q->whereDate('fecha_envio', '<=', $fechaHasta))
            ->when($ambiente !== '', fn (Builder $q) => $q->where('ambiente', $ambiente))
            ->when($ok === '1', fn (Builder $q) => $q->where('ok', true))
            ->when($ok === '0', fn (Builder $q) => $q->where('ok', false));
    }

    /** @return array{fecha_desde:string,fecha_hasta:string,ambiente:string,ok:string} */
    public function filtrosDesdeRequest(?string $fechaDesde, ?string $fechaHasta, ?string $ambiente, ?string $ok): array
    {
        $desde = $fechaDesde ?: Carbon::now()->subDays(30)->toDateString();
        $hasta = $fechaHasta ?: Carbon::now()->toDateString();

        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'ambiente' => trim((string) ($ambiente ?? '')),
            'ok' => trim((string) ($ok ?? '')),
        ];
    }

    /** @param  array{fecha_desde?:string,fecha_hasta?:string,ambiente?:string,ok?:string}  $filtros */
    public function paraQueryString(array $filtros): array
    {
        return array_filter([
            'fecha_desde' => $filtros['fecha_desde'] ?? null,
            'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
            'ambiente' => ($filtros['ambiente'] ?? '') !== '' ? $filtros['ambiente'] : null,
            'ok' => ($filtros['ok'] ?? '') !== '' ? $filtros['ok'] : null,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
