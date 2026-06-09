<?php

namespace App\Services\Caja\Estacionamiento;

use App\Models\Caja\Estacionamiento\ConfiguracionPuntoventaEstacionamiento;
use App\Support\Caja\Estacionamiento\EstacionamientoIdentificadorPc;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class EstacionamientoPvService
{
    /**
     * Configuración PV de esta terminal (empresa, listas, puntos de venta).
     */
    public function resolverConfiguracionPv(?Request $request = null, ?int $empresaId = null): ?ConfiguracionPuntoventaEstacionamiento
    {
        $pc = EstacionamientoIdentificadorPc::resolver($request);

        $query = ConfiguracionPuntoventaEstacionamiento::query()
            ->where('identificador_pc', $pc)
            ->with([
                'puntoventaCae',
                'puntoventaCaea',
                'salidaFactura',
                'tipotransaccion',
                'tipotransaccionNotaCredito',
                'tipotransaccionCaja',
                'empresa',
            ]);

        if ($empresaId !== null && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        $configs = $query->get();

        if ($configs->isEmpty()) {
            return null;
        }

        if ($configs->count() > 1) {
            $empresas = $configs->pluck('empresa_id')->unique()->implode(', ');

            throw new InvalidArgumentException(
                'Hay más de una configuración PV estacionamiento para identificador_pc '.$pc
                .' (empresas: '.$empresas.'). Debe existir una sola fila por terminal'
                .($empresaId !== null && $empresaId > 0 ? ' y empresa.' : '. Indique empresa si opera en varias.')
            );
        }

        return $configs->first();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Configuracion\Empresa>  $empresasAsignadas
     * @return \Illuminate\Support\Collection<int, \App\Models\Configuracion\Empresa>
     */
    public function empresasConPvEnTerminal(string $pc, $empresasAsignadas)
    {
        if ($empresasAsignadas->isEmpty()) {
            return collect();
        }

        $idsConPv = ConfiguracionPuntoventaEstacionamiento::query()
            ->where('identificador_pc', $pc)
            ->whereIn('empresa_id', $empresasAsignadas->pluck('id'))
            ->pluck('empresa_id')
            ->unique()
            ->values();

        return $empresasAsignadas
            ->whereIn('id', $idsConPv)
            ->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Configuracion\Empresa>  $empresasAsignadas
     * @return \Illuminate\Support\Collection<int, \App\Models\Configuracion\Empresa>
     */
    public function empresasSinPvEnTerminal(string $pc, $empresasAsignadas)
    {
        $operables = $this->empresasConPvEnTerminal($pc, $empresasAsignadas);

        return $empresasAsignadas
            ->whereNotIn('id', $operables->pluck('id'))
            ->values();
    }

    /**
     * Configuraciones PV de las empresas del usuario (cualquier terminal), para mensajes de ayuda.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Configuracion\Empresa>  $empresasAsignadas
     * @return \Illuminate\Support\Collection<int, ConfiguracionPuntoventaEstacionamiento>
     */
    public function configuracionesPvParaEmpresasAsignadas($empresasAsignadas)
    {
        if ($empresasAsignadas->isEmpty()) {
            return collect();
        }

        return ConfiguracionPuntoventaEstacionamiento::query()
            ->whereIn('empresa_id', $empresasAsignadas->pluck('id'))
            ->with('empresa')
            ->orderBy('empresa_id')
            ->orderBy('identificador_pc')
            ->get();
    }
}
