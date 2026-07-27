<?php

namespace App\Services\Stock;

use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Compras\OrdencompraDescuentoSupport;
use App\Support\Stock\RecepcionProveedorOcr\RecepcionProveedorOcrLineasParser;
use App\Support\Stock\RecepcionProveedorOcr\RecepcionProveedorOcrMatcher;
use App\Support\Stock\RecepcionProveedorOcr\RecepcionProveedorOcrNumeroOcExtractor;
use App\Support\Stock\RecepcionProveedorOcr\RecepcionProveedorOcrTextoExtractor;
use Illuminate\Support\Facades\Auth;

/**
 * Núcleo OCR + match remito/factura vs OC (sin gobernanza).
 */
final class RecepcionProveedorOcrCoreService
{
    public function __construct(
        private readonly RecepcionProveedorOrdencompraResolverService $ocResolver,
        private readonly RecepcionProveedorOcrTextoExtractor $textoExtractor,
        private readonly RecepcionProveedorOcrLineasParser $lineasParser,
        private readonly RecepcionProveedorOcrMatcher $matcher,
        private readonly RecepcionProveedorOcrNumeroOcExtractor $numeroOcExtractor,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function analizar(
        string $rutaAbsoluta,
        string $mime,
        ?int $ordencompraId,
        ?int $numeroOcForm,
        ?Recepcion_Proveedor $recepcion = null,
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

        return [
            'archivo_id' => null,
            'ocr_estado' => 'PROCESADO',
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
            'descuento_ordencompra' => OrdencompraDescuentoSupport::porcentajeEfectivoDesdeOrdencompra($oc),
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
}
