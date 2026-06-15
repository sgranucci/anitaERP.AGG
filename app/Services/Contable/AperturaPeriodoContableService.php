<?php

namespace App\Services\Contable;

use App\Models\Contable\AperturaPeriodoContable;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Contable\AperturaPeriodoContablePermiso;
use App\Support\Contable\PeriodoContableCierreSupport;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class AperturaPeriodoContableService
{
    public function __construct(
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {
    }

    public function listar(?int $empresaId, ?string $estado, int $perPage = 15): LengthAwarePaginator
    {
        $query = AperturaPeriodoContable::query()
            ->with([
                'empresa:id,nombre',
                'solicitante:id,nombre,usuario',
                'habilitado:id,nombre,usuario,email',
                'aprobador:id,nombre,usuario',
            ])
            ->orderByDesc('id');

        if ($empresaId !== null && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        if ($estado !== null && $estado !== '') {
            $query->where('estado', $estado);
        }

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id');

        if (! AperturaPeriodoContablePermiso::puedeGestionarSolicitudes()) {
            $usuarioId = (int) (Auth::id() ?? 0);
            $query->where(function ($q) use ($usuarioId) {
                $q->where('usuario_solicitante_id', $usuarioId)
                    ->orWhere('usuario_habilitado_id', $usuarioId);
            });
        }

        return $query->paginate(max(5, min(50, $perPage)));
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function solicitar(array $datos): AperturaPeriodoContable
    {
        $empresaId = (int) ($datos['empresa_id'] ?? 0);
        $this->assertEmpresaPermitida($empresaId);

        $desde = Carbon::parse($datos['fecha_operacion_desde'])->startOfDay();
        $hasta = Carbon::parse($datos['fecha_operacion_hasta'])->startOfDay();

        if ($hasta->lt($desde)) {
            throw new InvalidArgumentException('La fecha hasta debe ser mayor o igual a la fecha desde.');
        }

        $alcance = (string) ($datos['alcance'] ?? '');
        if (! array_key_exists($alcance, PeriodoContableCierreSupport::alcancesDisponibles())) {
            throw new InvalidArgumentException('Alcance de apertura inválido.');
        }

        $duracionCantidad = max(1, (int) ($datos['duracion_cantidad'] ?? 1));
        $duracionUnidad = ($datos['duracion_unidad'] ?? 'horas') === 'dias' ? 'dias' : 'horas';

        $habilitadoId = (int) ($datos['usuario_habilitado_id'] ?? Auth::id());
        if ($habilitadoId <= 0) {
            throw new InvalidArgumentException('Debe indicar el usuario a habilitar.');
        }

        $cierre = PeriodoContableCierreSupport::fechaCierreVigente($empresaId);
        if ($cierre === null) {
            throw new InvalidArgumentException('No hay cierre contable vigente para esta empresa. No requiere apertura.');
        }

        if ($hasta->gt($cierre)) {
            throw new InvalidArgumentException(
                'El rango solicitado debe estar dentro del período cerrado (hasta '
                .$cierre->format('d/m/Y').').'
            );
        }

        $apertura = AperturaPeriodoContable::query()->create([
            'empresa_id' => $empresaId,
            'usuario_solicitante_id' => (int) Auth::id(),
            'usuario_habilitado_id' => $habilitadoId,
            'fecha_operacion_desde' => $desde->format('Y-m-d'),
            'fecha_operacion_hasta' => $hasta->format('Y-m-d'),
            'alcance' => $alcance,
            'duracion_cantidad' => $duracionCantidad,
            'duracion_unidad' => $duracionUnidad,
            'estado' => 'pendiente',
            'motivo' => trim((string) ($datos['motivo'] ?? '')),
        ]);

        $this->moduloAvisoService->enviar('contable', 'apertura_periodo_solicitud_pendiente', (int) $apertura->id);

        return $apertura;
    }

    public function aprobar(int $id, ?string $observacion = null): AperturaPeriodoContable
    {
        $apertura = $this->findConAcceso($id);

        if ($apertura->estado !== 'pendiente') {
            throw new InvalidArgumentException('Solo puede aprobar solicitudes pendientes.');
        }

        $inicio = now();
        $vence = PeriodoContableCierreSupport::calcularVencimiento(
            $inicio,
            (int) $apertura->duracion_cantidad,
            (string) $apertura->duracion_unidad
        );

        $apertura->update([
            'estado' => 'activa',
            'usuario_aprobador_id' => (int) Auth::id(),
            'observacion_aprobacion' => trim((string) $observacion) ?: null,
            'inicio_en' => $inicio,
            'vence_en' => $vence,
        ]);

        $apertura->refresh();
        $this->moduloAvisoService->enviar('contable', 'apertura_periodo_habilitada', $apertura->id);

        return $apertura;
    }

    public function rechazar(int $id, ?string $observacion = null): AperturaPeriodoContable
    {
        $apertura = $this->findConAcceso($id);

        if ($apertura->estado !== 'pendiente') {
            throw new InvalidArgumentException('Solo puede rechazar solicitudes pendientes.');
        }

        $apertura->update([
            'estado' => 'rechazada',
            'usuario_aprobador_id' => (int) Auth::id(),
            'observacion_aprobacion' => trim((string) $observacion) ?: null,
        ]);

        return $apertura->refresh();
    }

    public function revocar(int $id, ?string $observacion = null): AperturaPeriodoContable
    {
        $apertura = $this->findConAcceso($id);

        if (! in_array($apertura->estado, ['pendiente', 'activa'], true)) {
            throw new InvalidArgumentException('La apertura no puede revocarse en su estado actual.');
        }

        $apertura->update([
            'estado' => 'revocada',
            'usuario_aprobador_id' => (int) Auth::id(),
            'observacion_aprobacion' => trim((string) $observacion) ?: null,
        ]);

        if ($apertura->aviso_cierre_enviado_en === null) {
            $this->moduloAvisoService->enviar('contable', 'apertura_periodo_cerrada', $apertura->id);
            $apertura->update(['aviso_cierre_enviado_en' => now()]);
        }

        return $apertura->refresh();
    }

    public function findConAcceso(int $id): AperturaPeriodoContable
    {
        $apertura = AperturaPeriodoContable::query()->findOrFail($id);
        $this->assertEmpresaPermitida((int) $apertura->empresa_id);

        if (! AperturaPeriodoContablePermiso::puedeGestionarSolicitudes()) {
            $usuarioId = (int) Auth::id();
            if (! in_array($usuarioId, [(int) $apertura->usuario_solicitante_id, (int) $apertura->usuario_habilitado_id], true)) {
                throw new InvalidArgumentException('No tiene acceso a esta solicitud.');
            }
        }

        return $apertura;
    }

    private function assertEmpresaPermitida(int $empresaId): void
    {
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            throw new InvalidArgumentException('No tiene acceso a la empresa seleccionada.');
        }
    }
}
