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
     * @return array{html: string, titulo: string, plantilla: string, fuente_word: bool}
     */
    public function previsualizar(
        Empleado_Sueldos $empleado,
        string $tipo,
        ?Empleado_Contrato_Sueldos $contrato = null,
        ?string $fechaEmision = null,
        ?int $liquidacion = null,
        ?int $plantillaId = null,
    ): array {
        $ctx = $this->resolverContexto($empleado, $tipo, $contrato, $fechaEmision, $liquidacion, $plantillaId);
        $html = $this->mergeSupport->generarHtml($ctx['plantilla']->rutaAbsoluta(), $ctx['tags']);

        return [
            'html' => $html,
            'titulo' => ContratoDocumentoTipos::label($tipo).' — legajo '.$empleado->legajo,
            'plantilla' => (string) $ctx['plantilla']->codigo,
            'fuente_word' => $ctx['plantilla']->esFuenteWord(),
        ];
    }

    /**
     * @param  'pdf'|'docx'  $formato
     * @return array{emision: Empleado_Contrato_Emision_Sueldos, path: string, formato: string}
     */
    public function emitir(
        Empleado_Sueldos $empleado,
        string $tipo,
        ?Empleado_Contrato_Sueldos $contrato = null,
        ?string $fechaEmision = null,
        ?int $liquidacion = null,
        ?int $plantillaId = null,
        string $formato = 'pdf',
    ): array {
        $formato = strtolower(trim($formato));
        if (! in_array($formato, ['pdf', 'docx'], true)) {
            throw new RuntimeException('Formato de emisión inválido (use pdf o docx).');
        }

        $ctx = $this->resolverContexto($empleado, $tipo, $contrato, $fechaEmision, $liquidacion, $plantillaId);

        if ($formato === 'docx' && ! $ctx['plantilla']->esFuenteWord()) {
            throw new RuntimeException(
                'La plantilla «'.$ctx['plantilla']->codigo.'» no es un Word; solo se puede emitir PDF.'
            );
        }

        $nombreSalida = $this->nombreSalida($tipo, $empleado, $contrato, $formato);
        $relPath = 'sueldos/contratos/emisiones/'.$empleado->id.'/'.$nombreSalida;
        $absPath = storage_path('app/'.$relPath);

        if ($formato === 'docx') {
            $this->mergeSupport->generar($ctx['plantilla']->rutaAbsoluta(), $ctx['tags'], $absPath);
        } else {
            $this->mergeSupport->generarPdf($ctx['plantilla']->rutaAbsoluta(), $ctx['tags'], $absPath);
        }

        $emision = Empleado_Contrato_Emision_Sueldos::create([
            'empleado_id' => $empleado->id,
            'empleado_contrato_id' => $contrato?->id,
            'tipo' => $tipo,
            'contrato_plantilla_id' => $ctx['plantilla']->id,
            'fecha_emision' => $ctx['fecha']->toDateString(),
            'liquidacion' => $liquidacion,
            'archivo_path' => $relPath,
            'archivo_nombre' => $nombreSalida,
            'usuario_id' => Auth::id(),
            'datos_merge' => $ctx['tags'],
        ]);

        return ['emision' => $emision, 'path' => $absPath, 'formato' => $formato];
    }

    /**
     * @return array{
     *   plantilla: Contrato_Plantilla_Sueldos,
     *   tags: array<string, string|int|float|null>,
     *   fecha: Carbon,
     *   modalidad: string|null
     * }
     */
    private function resolverContexto(
        Empleado_Sueldos $empleado,
        string $tipo,
        ?Empleado_Contrato_Sueldos $contrato,
        ?string $fechaEmision,
        ?int $liquidacion,
        ?int $plantillaId,
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
                .' (modalidad '.$modalidad.', sindicato/empresa del legajo). '
                .'Elija una plantilla en el selector o configure la asignación en Plantillas de contrato.'
            );
        }

        if (! is_file($plantilla->rutaAbsoluta())) {
            throw new RuntimeException('El archivo de la plantilla «'.$plantilla->codigo.'» no está en el servidor.');
        }

        $tags = $this->datosService->armar($empleado, $contrato, $fecha, $modalidad);

        return [
            'plantilla' => $plantilla,
            'tags' => $tags,
            'fecha' => $fecha,
            'modalidad' => $modalidad,
        ];
    }

    private function nombreSalida(
        string $tipo,
        Empleado_Sueldos $empleado,
        ?Empleado_Contrato_Sueldos $contrato,
        string $formato = 'pdf',
    ): string {
        $legajo = (int) $empleado->legajo;
        $stamp = now()->format('Ymd_His');
        $ext = $formato === 'docx' ? 'docx' : 'pdf';

        return match ($tipo) {
            ContratoDocumentoTipos::CONTRATO => sprintf(
                'CONT_Legajo_%d_%d_%s.%s',
                $legajo,
                (int) ($contrato?->nro_orden ?? 0),
                $stamp,
                $ext
            ),
            ContratoDocumentoTipos::CERT_LABORAL => sprintf('CERT_LABORAL_Legajo_%d_%s.%s', $legajo, $stamp, $ext),
            ContratoDocumentoTipos::CERT_BAJA => sprintf('CERT_BAJA_Legajo_%d_%s.%s', $legajo, $stamp, $ext),
            default => sprintf('DOC_Legajo_%d_%s.%s', $legajo, $stamp, $ext),
        };
    }
}
