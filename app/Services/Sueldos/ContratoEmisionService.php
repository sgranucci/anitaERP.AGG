<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Contrato_Plantilla_Sueldos;
use App\Models\Sueldos\Empleado_Contrato_Emision_Sueldos;
use App\Models\Sueldos\Empleado_Contrato_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Support\Sueldos\ContratoDocxMergeSupport;
use App\Support\Sueldos\ContratoDocumentoTipos;
use App\Support\Sueldos\ContratoPlantillaResolver;
use App\Support\Sueldos\EmpleadoEstados;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class ContratoEmisionService
{
    public function __construct(
        private ContratoPlantillaResolver $resolver,
        private ContratoEmisionDatosService $datosService,
        private ContratoDocxMergeSupport $mergeSupport,
    ) {}

    /**
     * @return array{emision: Empleado_Contrato_Emision_Sueldos, path: string}
     */
    public function emitir(
        Empleado_Sueldos $empleado,
        string $tipo,
        ?Empleado_Contrato_Sueldos $contrato = null,
        ?string $fechaEmision = null,
        ?int $liquidacion = null,
        ?int $plantillaId = null,
    ): array {
        if (! ContratoDocumentoTipos::esValido($tipo)) {
            throw new RuntimeException('Tipo de documento inválido.');
        }

        if ($tipo === ContratoDocumentoTipos::CERT_BAJA && ! EmpleadoEstados::esBaja($empleado->estado)) {
            throw new RuntimeException('Solo se puede emitir certificado de baja si el empleado está dado de baja.');
        }

        if ($tipo === ContratoDocumentoTipos::CONTRATO && ! $contrato) {
            throw new RuntimeException('Debe indicar el contrato a emitir.');
        }

        $fecha = $fechaEmision
            ? Carbon::parse($fechaEmision)->startOfDay()
            : Carbon::today();

        $modalidad = $contrato?->modalidad ?? $empleado->modalidad_sijp;
        $plantilla = $this->resolver->resolver(
            $tipo,
            $modalidad,
            $empleado->sindicato_id ? (int) $empleado->sindicato_id : null,
            $empleado->empresa_id ? (int) $empleado->empresa_id : null,
            $plantillaId,
        );

        if (! $plantilla) {
            throw new RuntimeException(
                'No hay plantilla asignada para '.ContratoDocumentoTipos::label($tipo)
                .' (modalidad '.$modalidad.', sindicato/empresa del legajo). Configure la asignación en Plantillas de contrato.'
            );
        }

        if (! is_file($plantilla->rutaAbsoluta())) {
            throw new RuntimeException('El archivo de la plantilla «'.$plantilla->codigo.'» no está en el servidor.');
        }

        $tags = $this->datosService->armar($empleado, $contrato, $fecha, $modalidad);

        $nombreSalida = $this->nombreSalida($tipo, $empleado, $contrato);
        $relPath = 'sueldos/contratos/emisiones/'.$empleado->id.'/'.$nombreSalida;
        $absPath = storage_path('app/'.$relPath);

        $this->mergeSupport->generar($plantilla->rutaAbsoluta(), $tags, $absPath);

        $emision = Empleado_Contrato_Emision_Sueldos::create([
            'empleado_id' => $empleado->id,
            'empleado_contrato_id' => $contrato?->id,
            'tipo' => $tipo,
            'contrato_plantilla_id' => $plantilla->id,
            'fecha_emision' => $fecha->toDateString(),
            'liquidacion' => $liquidacion,
            'archivo_path' => $relPath,
            'archivo_nombre' => $nombreSalida,
            'usuario_id' => Auth::id(),
            'datos_merge' => $tags,
        ]);

        return ['emision' => $emision, 'path' => $absPath];
    }

    private function nombreSalida(
        string $tipo,
        Empleado_Sueldos $empleado,
        ?Empleado_Contrato_Sueldos $contrato,
    ): string {
        $legajo = (int) $empleado->legajo;
        $stamp = now()->format('Ymd_His');

        return match ($tipo) {
            ContratoDocumentoTipos::CONTRATO => sprintf(
                'CONT_Legajo_%d_%d_%s.docx',
                $legajo,
                (int) ($contrato?->nro_orden ?? 0),
                $stamp
            ),
            ContratoDocumentoTipos::CERT_LABORAL => sprintf('CERT_LABORAL_Legajo_%d_%s.docx', $legajo, $stamp),
            ContratoDocumentoTipos::CERT_BAJA => sprintf('CERT_BAJA_Legajo_%d_%s.docx', $legajo, $stamp),
            default => sprintf('DOC_Legajo_%d_%s.docx', $legajo, $stamp),
        };
    }
}
