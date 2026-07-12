<?php

namespace App\Services\Caja\Bingo;

use App\Models\Caja\Bingo\ConfiguracionPuntoventaBingo;
use App\Support\Caja\Bingo\BingoIdentificadorPc;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class BingoPvService
{
    public function resolverConfiguracionPv(?Request $request = null, ?int $empresaId = null): ?ConfiguracionPuntoventaBingo
    {
        $pc = BingoIdentificadorPc::resolver($request);

        $query = ConfiguracionPuntoventaBingo::query()
            ->where('identificador_pc', $pc)
            ->with(['empresa', 'cuentacaja']);

        if ($empresaId !== null && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        $configs = $query->get();

        if ($configs->isEmpty()) {
            return null;
        }

        if ($configs->count() > 1) {
            throw new InvalidArgumentException(
                'Hay más de una configuración bingo para identificador_pc '.$pc.'. Debe existir una sola fila por terminal y empresa.'
            );
        }

        return $configs->first();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Configuracion\Empresa>  $empresasAsignadas
     */
    public function empresasConPvEnTerminal(string $pc, $empresasAsignadas)
    {
        if ($empresasAsignadas->isEmpty()) {
            return collect();
        }

        $idsConPv = ConfiguracionPuntoventaBingo::query()
            ->where('identificador_pc', $pc)
            ->whereIn('empresa_id', $empresasAsignadas->pluck('id'))
            ->pluck('empresa_id')
            ->unique()
            ->values();

        return $empresasAsignadas->whereIn('id', $idsConPv)->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Configuracion\Empresa>  $empresasAsignadas
     */
    public function empresasSinPvEnTerminal(string $pc, $empresasAsignadas)
    {
        return $empresasAsignadas
            ->whereNotIn('id', $this->empresasConPvEnTerminal($pc, $empresasAsignadas)->pluck('id'))
            ->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Configuracion\Empresa>  $empresasAsignadas
     */
    public function configuracionesPvParaEmpresasAsignadas($empresasAsignadas)
    {
        if ($empresasAsignadas->isEmpty()) {
            return collect();
        }

        return ConfiguracionPuntoventaBingo::query()
            ->whereIn('empresa_id', $empresasAsignadas->pluck('id'))
            ->with('empresa')
            ->orderBy('empresa_id')
            ->orderBy('identificador_pc')
            ->get();
    }
}
