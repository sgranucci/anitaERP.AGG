<?php

namespace App\Support\Caja;

use App\Models\Configuracion\Sala;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TesoreriaCrownSalaSupport
{
    /** Código de depósitos centrales. No es una sala de juego. */
    private const CODIGO_ADMINISTRACION_CENTRAL = 'AC';

    public static function salas(): Collection
    {
        $empresas = collect(app(EmpresaRepositoryInterface::class)->allFiltrado());
        $ids = $empresas->pluck('id')->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->values()->all();
        $query = Sala::query()
            ->whereRaw('UPPER(codigo) <> ?', [self::CODIGO_ADMINISTRACION_CENTRAL])
            ->orderBy('codigo');
        if ($ids !== []) {
            $query->whereIn('empresa_id', $ids);
        }

        return $query->get(['id', 'codigo', 'nombre', 'empresa_id']);
    }

    public static function pedida(Request $request, ?Collection $salas = null): ?Sala
    {
        $salas = $salas ?? self::salas();
        $salaId = (int) $request->input('sala_id', 0);
        if ($salaId > 0) {
            $sala = $salas->firstWhere('id', $salaId);
            if (! $sala) {
                abort(404);
            }

            return $sala;
        }

        $codigo = strtoupper(trim((string) config('caja_anita.sala_codigo', '')));
        if ($codigo !== '') {
            $preferida = $salas->first(fn ($sala) => strtoupper((string) $sala->codigo) === $codigo);
            if ($preferida) {
                return $preferida;
            }
        }

        return $salas->first();
    }

    public static function exigida(int $salaId): Sala
    {
        $sala = self::salas()->firstWhere('id', $salaId);
        if (! $sala) {
            abort(404);
        }

        return $sala;
    }
}
