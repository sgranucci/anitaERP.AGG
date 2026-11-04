<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Empleado_Sueldos;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Sueldos\EmpleadoEstados;
use App\Support\Sueldos\LibretaSanitariaAvisoSupport;
use Illuminate\Support\Carbon;

class LibretaSanitariaAvisoService
{
    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {
    }

    /**
     * Empleados vigentes que deben la libreta. Las bajas quedan en la ficha y no se avisan.
     *
     * @return list<array{empresa: string, legajo: string, nombre: string, vencimiento: string}>
     */
    public function pendientes(): array
    {
        $filas = [];
        $empleados = Empleado_Sueldos::query()
            ->with('empresa:id,nombre')
            ->where('debe_libreta_sanitaria', true)
            ->where('estado', '!=', EmpleadoEstados::BAJA)
            ->orderBy('empresa_id')
            ->orderBy('legajo')
            ->get(['id', 'empresa_id', 'legajo', 'nombre', 'estado', 'fecha_vencimiento_libreta_sanitaria']);

        foreach ($empleados as $empleado) {
            $vence = $empleado->fecha_vencimiento_libreta_sanitaria;
            $filas[] = [
                'empresa' => (string) (optional($empleado->empresa)->nombre ?? ''),
                'legajo' => (string) $empleado->legajo,
                'nombre' => (string) ($empleado->nombre ?? ''),
                'vencimiento' => $vence instanceof Carbon ? $vence->format('d/m/Y') : '',
            ];
        }

        return $filas;
    }

    /**
     * @param  list<array{empresa: string, legajo: string, nombre: string, vencimiento: string}>  $filas
     */
    public function encolar(array $filas): void
    {
        if ($filas === []) {
            return;
        }

        $this->moduloAvisoService->enviar('sueldos', 'libreta_sanitaria_pendiente', 0, [
            'cantidad' => (string) count($filas),
            'empleados' => LibretaSanitariaAvisoSupport::formatearLista($filas),
            'fecha' => now()->timezone('America/Argentina/Buenos_Aires')->format('d/m/Y'),
        ]);
    }
}
