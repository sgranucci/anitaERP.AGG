<?php

namespace App\Services\Stock;

use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Stock\Recepcion_Proveedor_Archivo;
use App\Repositories\Stock\Recepcion_ProveedorRepositoryInterface;
use App\Services\Ai\AiPolicy;
use App\Services\Ai\Skills\AiSkillContext;
use App\Services\Ai\Skills\AiSkillRegistry;
use App\Services\Stock\Ai\ExtraerRemitoRecepcionSkill;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RecepcionProveedorOcrService
{
    public function __construct(
        private readonly Recepcion_ProveedorRepositoryInterface $recepcionRepository,
        private readonly RecepcionProveedorOcrCoreService $core,
        private readonly AiSkillRegistry $skillRegistry,
        private readonly AiPolicy $aiPolicy,
    ) {}

    /**
     * OCR en pantalla de alta (sin recepción guardada): completa OC e ítems en el formulario.
     *
     * @return array<string, mixed>
     */
    public function procesarArchivoPreview(
        UploadedFile $archivo,
        ?int $ordencompraId = null,
        ?int $numeroOcForm = null,
    ): array {
        $this->assertOcrHabilitado();

        $rutaRelativa = $archivo->store('recepcion_proveedor/ocr/preview/'.date('Y/m/d'), 'local');
        $rutaAbsoluta = Storage::disk('local')->path($rutaRelativa);

        try {
            $resultado = $this->analizarConGobernanza(
                $rutaAbsoluta,
                (string) $archivo->getMimeType(),
                $ordencompraId,
                $numeroOcForm,
                null,
                $archivo->getClientOriginalName(),
            );

            return $this->sinMetadatosInternos($resultado);
        } finally {
            Storage::disk('local')->delete($rutaRelativa);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function procesarArchivo(int $recepcionId, UploadedFile $archivo): array
    {
        $this->assertOcrHabilitado();

        $recepcion = $this->recepcionRepository->find($recepcionId);
        if ($recepcion->estado !== Recepcion_Proveedor::ESTADO_BORRADOR) {
            throw new \RuntimeException('Solo se puede cargar OCR en recepciones en BORRADOR.');
        }

        $nombre = $archivo->getClientOriginalName();
        $ruta = $archivo->store('recepcion_proveedor/ocr/'.date('Y/m'), 'local');

        $registro = Recepcion_Proveedor_Archivo::create([
            'recepcion_proveedor_id' => $recepcionId,
            'nombre' => $nombre,
            'ruta' => $ruta,
            'tipo_archivo' => Recepcion_Proveedor_Archivo::TIPO_OCR,
            'mime' => $archivo->getMimeType(),
            'ocr_estado' => Recepcion_Proveedor_Archivo::OCR_PENDIENTE,
        ]);

        $driver = (string) config('recepcion_proveedor.ocr.driver', 'tesseract');
        if ($driver === 'stub') {
            return [
                'archivo_id' => $registro->id,
                'ocr_estado' => Recepcion_Proveedor_Archivo::OCR_PENDIENTE,
                'lineas' => [],
                'resumen' => 'Driver stub: archivo guardado sin procesar.',
                'ocr_lineas_detectadas' => 0,
                'numero_oc_detectado' => null,
                'numero_oc_origen' => null,
                'ordencompra_id' => null,
                'numeroordencompra' => null,
                'proveedor_nombre' => null,
                'empresa_id' => null,
                'ocr_texto_puro' => null,
            ];
        }

        try {
            $resultado = $this->analizarConGobernanza(
                $this->rutaAbsoluta($registro),
                (string) $registro->mime,
                (int) $recepcion->ordencompra_id ?: null,
                (int) (optional($recepcion->ordencompras)->numeroordencompra ?? 0) ?: null,
                $recepcion,
                $nombre,
            );

            $registro->update([
                'ocr_estado' => Recepcion_Proveedor_Archivo::OCR_PROCESADO,
                'ocr_texto' => mb_substr((string) ($resultado['_ocr_texto'] ?? ''), 0, 65000),
                'ocr_datos' => [
                    'lineas_ocr' => $resultado['_lineas_ocr'] ?? [],
                    'numero_oc' => $resultado['_numero_oc_info'] ?? [],
                    'resumen' => $resultado['_resumen_arr'] ?? [],
                    'ai_decision_id' => $resultado['ai_decision_id'] ?? null,
                    'ai_score' => $resultado['ai_score'] ?? null,
                ],
            ]);

            $recepcion->update(['origen_carga' => 'OCR']);

            $resultado = $this->sinMetadatosInternos($resultado);
            $resultado['archivo_id'] = $registro->id;

            return $resultado;
        } catch (\Throwable $e) {
            $registro->update([
                'ocr_estado' => Recepcion_Proveedor_Archivo::OCR_ERROR,
                'ocr_texto' => mb_substr($e->getMessage(), 0, 65000),
            ]);
            Log::warning('RecepcionProveedorOCR error', [
                'recepcion_id' => $recepcionId,
                'archivo_id' => $registro->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function analizarConGobernanza(
        string $rutaAbsoluta,
        string $mime,
        ?int $ordencompraId,
        ?int $numeroOcForm,
        ?Recepcion_Proveedor $recepcion,
        ?string $archivoNombre,
    ): array {
        $skill = ExtraerRemitoRecepcionSkill::NOMBRE;
        $habilitada = $this->skillRegistry->tiene($skill) && $this->aiPolicy->puedeEjecutar($skill);

        if (! $habilitada) {
            return $this->core->analizar($rutaAbsoluta, $mime, $ordencompraId, $numeroOcForm, $recepcion);
        }

        $resultado = $this->skillRegistry->ejecutar($skill, new AiSkillContext(
            entradas: [
                'ruta_absoluta' => $rutaAbsoluta,
                'mime' => $mime,
                'ordencompra_id' => $ordencompraId,
                'numero_oc' => $numeroOcForm,
                'recepcion' => $recepcion,
                'archivo_nombre' => $archivoNombre,
            ],
            empresaId: $recepcion?->empresa_id ? (int) $recepcion->empresa_id : null,
            entidadTipo: ExtraerRemitoRecepcionSkill::ENTIDAD,
            entidadId: $recepcion?->id ? (int) $recepcion->id : null,
        ));

        if (! $resultado->ok) {
            throw new \RuntimeException($resultado->error ?? 'No se pudo interpretar el remito con OCR.');
        }

        $datos = $resultado->datos;
        $datos['ai_decision_id'] = $resultado->decisionId;
        $datos['ai_score'] = $resultado->score;
        $datos['ai_auto_aplicable'] = $resultado->autoAplicable;

        return $datos;
    }

    private function assertOcrHabilitado(): void
    {
        if (! config('recepcion_proveedor.ocr.habilitado')) {
            throw new \RuntimeException('OCR de recepción no habilitado. Active RECEPCION_PROVEEDOR_OCR_HABILITADO.');
        }

        if ((string) config('recepcion_proveedor.ocr.driver', 'tesseract') === 'stub') {
            throw new \RuntimeException('OCR en modo stub: no procesa documentos. Configure RECEPCION_PROVEEDOR_OCR_DRIVER=tesseract.');
        }
    }

    private function sinMetadatosInternos(array $resultado): array
    {
        if (isset($resultado['_ocr_texto'])) {
            $resultado['ocr_texto_puro'] = (string) $resultado['_ocr_texto'];
        }
        if (isset($resultado['_lineas_ocr'])) {
            $resultado['ocr_lineas_parseadas'] = $resultado['_lineas_ocr'];
        }

        unset($resultado['_ocr_texto'], $resultado['_lineas_ocr'], $resultado['_numero_oc_info'], $resultado['_resumen_arr']);

        return $resultado;
    }

    public function rutaAbsoluta(Recepcion_Proveedor_Archivo $archivo): string
    {
        return Storage::disk('local')->path($archivo->ruta);
    }
}
