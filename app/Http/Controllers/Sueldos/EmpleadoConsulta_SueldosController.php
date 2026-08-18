<?php

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Controller;
use App\Repositories\Sueldos\Empleado_SueldosRepositoryInterface;
use Illuminate\Http\Request;

class EmpleadoConsulta_SueldosController extends Controller
{
    public function __construct(
        private readonly Empleado_SueldosRepositoryInterface $repository,
    ) {}

    public function consultar(Request $request)
    {
        $this->autorizarConsulta();

        $filas = $this->repository->consultaOperativa(
            (string) $request->input('consulta', ''),
            $this->empresaId($request),
        );

        return response()->json([
            'data' => $filas->map(fn ($empleado) => $this->serializar($empleado))->values(),
        ]);
    }

    public function resolver(Request $request)
    {
        $this->autorizarConsulta();

        $empleado = $this->repository->findOperativoPorLegajo(
            (int) $request->input('legajo', 0),
            $this->empresaId($request),
        );

        if (! $empleado) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'No se encontró un empleado activo con ese legajo.',
            ], 404);
        }

        return response()->json(array_merge(['ok' => true], $this->serializar($empleado)));
    }

    private function empresaId(Request $request): ?int
    {
        $empresaId = (int) $request->input('empresa_id', 0);

        return $empresaId > 0 ? $empresaId : null;
    }

    private function serializar($empleado): array
    {
        $puedeConsultarAbm = can('editar-empleado-sueldos', false)
            || can('listar-empleado-sueldos', false);

        return [
            'id' => (int) $empleado->id,
            'empresa_id' => (int) $empleado->empresa_id,
            'legajo' => (int) $empleado->legajo,
            'nombre' => (string) $empleado->nombre,
            'documento' => (string) ($empleado->documento ?? ''),
            'cuil' => (string) ($empleado->cuil ?? ''),
            'consultar_url' => $puedeConsultarAbm
                ? route('editar_empleado_sueldos', [
                    'id' => (int) $empleado->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ])
                : null,
        ];
    }

    private function autorizarConsulta(): void
    {
        $permitido = can('listar-empleado-sueldos', false)
            || can('editar-empleado-sueldos', false)
            || can('listar-descuento-fallo-sueldos', false)
            || can('crear-descuento-fallo-sueldos', false)
            || can('listar-fallo-reporte-sueldos', false)
            || can('listar-perdida-personal-reporte', false);

        abort_unless($permitido, 403);
    }
}
