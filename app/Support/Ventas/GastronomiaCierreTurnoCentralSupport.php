<?php

namespace App\Support\Ventas;

use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use InvalidArgumentException;

/**
 * Resolución de turno operativo para cierre centralizado (desde oficina, no desde la PC del turno).
 */
final class GastronomiaCierreTurnoCentralSupport
{
    public function __construct(
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function resolverTurnoHabilitado(int $turnoOperativoId, int $empresaId): TurnoOperativoGastronomia
    {
        if ($turnoOperativoId <= 0) {
            throw new InvalidArgumentException('Turno operativo inválido.');
        }

        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Empresa inválida.');
        }

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            throw new InvalidArgumentException('Empresa no permitida para su usuario.');
        }

        $turno = TurnoOperativoGastronomia::query()
            ->with(['turno', 'jornada', 'usuarioHabilitado', 'configuracionPuntoventa.puntoventaCae', 'configuracionPuntoventa.puntoventaCaea'])
            ->find($turnoOperativoId);

        if ($turno === null) {
            throw new InvalidArgumentException('Turno operativo no encontrado.');
        }

        if ((int) $turno->empresa_id !== $empresaId) {
            throw new InvalidArgumentException('El turno no pertenece a la empresa indicada.');
        }

        if ($turno->estado !== TurnoOperativoGastronomia::ESTADO_HABILITADO) {
            throw new InvalidArgumentException('El turno ya no está habilitado (pudo cerrarse en la terminal).');
        }

        return $turno;
    }
}
