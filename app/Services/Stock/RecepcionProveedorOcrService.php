<?php

namespace App\Services\Stock;

use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Stock\Recepcion_Proveedor_Archivo;
use App\Repositories\Stock\Recepcion_ProveedorRepositoryInterface;
use App\Support\Stock\RecepcionProveedorOcr\RecepcionProveedorOcrLineasParser;
use App\Support\Stock\RecepcionProveedorOcr\RecepcionProveedorOcrMatcher;
use App\Support\Stock\RecepcionProveedorOcr\RecepcionProveedorOcrNumeroOcExtractor;
use App\Support\Stock\RecepcionProveedorOcr\RecepcionProveedorOcrTextoExtractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RecepcionProveedorOcrService
{
    public function __construct(
        private readonly Recepcion_ProveedorRepositoryInterface $recepcionRepository,
        private readonly RecepcionProveedorOrdencompraResolverService $ocResolver,
        private readonly RecepcionProveedorOcrTextoExtractor $textoExtractor,
        private readonly RecepcionProveedorOcrLineasParser $lineasParser,
        private readonly RecepcionProveedorOcrMatcher $matcher,
        private readonly RecepcionProveedorOcrNumeroOcExtractor $numeroOcExtractor,
    ) {
    }

    /**
     * OCR en pantalla de alta (sin recepción guardada): completa OC e ítems en el formulario.
     *
     * @return array{
     *   archivo_id: ?int,
     *   ocr_estado: string,
     *   lineas: list<array<string, mixed>>,
     *   resumen: ?string,
     *   ocr_lineas_detectadas: int,
     *   numero_oc_detectado: ?int,
     *   numero_oc_origen: ?string,
     *   ordencompra_id: ?int,
     *   numeroordencompra: ?int,
     *   proveedor_nombre: ?string,
     *   empresa_id: ?int,
     *   ocr_texto_puro: ?string
     * }
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
            $resultado = $this->analizarArchivo(
                $rutaAbsoluta,
                (string) $archivo->getMimeType(),
                $ordencompraId,
                $numeroOcForm,
                null
            );

            return $this->sinMetadatosInternos($resultado);
        } finally {
            Storage::disk('local')->delete($rutaRelativa);
        }
    }

    /**
     * @return array{
     *   archivo_id: int,
     *   ocr_estado: string,
     *   lineas: list<array<string, mixed>>,
     *   resumen: ?string,
     *   ocr_lineas_detectadas: int,
     *   numero_oc_detectado: ?int,
     *   numero_oc_origen: ?string,
     *   ordencompra_id: ?int,
     *   numeroordencompra: ?int,
     *   proveedor_nombre: ?string,
     *   empresa_id: ?int,
     *   ocr_texto_puro: ?string
     * }
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
            $resultado = $this->analizarArchivo(
                $this->rutaAbsoluta($registro),
                (string) $registro->mime,
                (int) $recepcion->ordencompra_id ?: null,
                (int) (optional($recepcion->ordencompras)->numeroordencompra ?? 0) ?: null,
                $recepcion
            );

            $registro->update([
                'ocr_estado' => Recepcion_Proveedor_Archivo::OCR_PROCESADO,
                'ocr_texto' => mb_substr((string) ($resultado['_ocr_texto'] ?? ''), 0, 65000),
                'ocr_datos' => [
                    'lineas_ocr' => $resultado['_lineas_ocr'] ?? [],
                    'numero_oc' => $resultado['_numero_oc_info'] ?? [],
                    'resumen' => $resultado['_resumen_arr'] ?? [],
                ],
            ]);

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
    private function analizarArchivo(
        string $rutaAbsoluta,
        string $mime,
        ?int $ordencompraId,
        ?int $numeroOcForm,
        ?Recepcion_Proveedor $recepcion,
    ): array {
        $texto = $this->textoExtractor->extraer($rutaAbsoluta, $mime);
        if (trim($texto) === '') {
            throw new \RuntimeException('No se extrajo texto del archivo. Verifique calidad de imagen o instalación de Tesseract.');
        }

        $numeroOcInfo = $this->numeroOcExtractor->extraer($texto);
        $lineasOcr = $this->lineasParser->parsear($texto);
        if ($lineasOcr === []) {
            throw new \RuntimeException(
                'Se leyó el documento pero no se detectaron líneas con cantidad. '
                .'Revise el formato o complete manualmente.'
            );
        }

        $ocData = $this->resolverDatosOc($ordencompraId, $numeroOcForm, $numeroOcInfo);
        $oc = $ocData['cabecera'];
        $numeroOcDetectado = $numeroOcInfo['numero'];
        $numeroOcCargada = $numeroOcForm ?: (int) (optional($recepcion?->ordencompras)->numeroordencompra ?? 0);

        $resultado = $this->matcher->aplicar($ocData['lineas'], $lineasOcr);
        $resumenArr = $resultado['resumen'];
        $resumenTexto = sprintf(
            'OCR: %d línea(s) emparejada(s), %d sin match en OC, %d ítem(s) OC sin dato OCR.',
            $resumenArr['emparejadas'],
            $resumenArr['sin_match'],
            $resumenArr['sin_ocr']
        );

        if ($numeroOcDetectado !== null) {
            $resumenTexto .= "\nOC detectada en documento: {$numeroOcDetectado}";
            if ($numeroOcInfo['origen'] !== null) {
                $resumenTexto .= ' ('.$numeroOcInfo['origen'].')';
            }
        }

        if ($numeroOcDetectado !== null && $numeroOcCargada > 0 && $numeroOcDetectado !== $numeroOcCargada) {
            $resumenTexto .= "\nAtención: OC del documento ({$numeroOcDetectado}) distinta a la cargada ({$numeroOcCargada}).";
        }

        if ($recepcion !== null) {
            $recepcion->update(['origen_carga' => 'OCR']);
        }

        return [
            'archivo_id' => null,
            'ocr_estado' => Recepcion_Proveedor_Archivo::OCR_PROCESADO,
            'lineas' => $resultado['lineas'],
            'resumen' => $resumenTexto,
            'ocr_lineas_detectadas' => count($lineasOcr),
            'numero_oc_detectado' => $numeroOcDetectado,
            'numero_oc_origen' => $numeroOcInfo['origen'],
            'ordencompra_id' => $oc->id,
            'numeroordencompra' => (int) $oc->numeroordencompra,
            'proveedor_id' => (int) $oc->proveedor_id,
            'proveedor_nombre' => optional($oc->proveedores)->nombre,
            'empresa_id' => $oc->empresa_id,
            '_ocr_texto' => $texto,
            '_lineas_ocr' => $lineasOcr,
            '_numero_oc_info' => $numeroOcInfo,
            '_resumen_arr' => $resumenArr,
        ];
    }

    /**
     * @param  array{numero: ?int, origen: ?string, candidatos: list<int>}  $numeroOcInfo
     * @return array{cabecera: \App\Models\Compras\Ordencompra, lineas: list<array<string, mixed>>}
     */
    private function resolverDatosOc(?int $ordencompraId, ?int $numeroOcForm, array $numeroOcInfo): array
    {
        if ($ordencompraId !== null && $ordencompraId > 0) {
            return $this->ocResolver->resolverPorId($ordencompraId);
        }

        if ($numeroOcForm !== null && $numeroOcForm > 0) {
            return $this->ocResolver->resolverPorNumeroOc($numeroOcForm, (int) Auth::id());
        }

        $numeroOc = (int) ($numeroOcInfo['numero'] ?? 0);
        if ($numeroOc <= 0) {
            throw new \RuntimeException(
                'No hay OC cargada y no se detectó número de orden de compra en el documento. '
                .'Cargue la OC manualmente o use un remito que indique el número (OC, orden de compra o 6 dígitos).'
            );
        }

        return $this->ocResolver->resolverPorNumeroOc($numeroOc, (int) Auth::id());
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
