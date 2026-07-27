<?php

namespace App\Services\Compras\Ai;

use App\Models\Ai\AiDecision;
use App\Services\Ai\AiDecisionLogger;
use App\Services\Ai\AiPolicy;
use App\Services\Ai\Skills\AiSkillContext;
use App\Services\Ai\Skills\AiSkillInterface;
use App\Services\Ai\Skills\AiSkillResult;
use App\Services\Compras\ComprobanteProveedorPdfIaClient;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorExtraccionScoreSupport;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Primera skill de negocio: extracción de factura de proveedor desde PDF.
 *
 * Envuelve el pipeline existente (OCR + heurísticas + Ollama) agregando la capa de
 * gobernanza: score observable, advertencias y registro en ai_decision.
 * NO graba nada: devuelve la sugerencia que la pantalla muestra en preview.
 */
final class ExtraerFacturaProveedorSkill implements AiSkillInterface
{
    public const NOMBRE = 'extraer_factura_proveedor';

    public const ENTIDAD = 'precarga_comprobante_proveedor';

    public function __construct(
        private ComprobanteProveedorPdfIaClient $iaClient,
        private AiPolicy $policy,
        private AiDecisionLogger $logger,
    ) {}

    public function nombre(): string
    {
        return self::NOMBRE;
    }

    public function ejecutar(AiSkillContext $contexto): AiSkillResult
    {
        $pdf = $contexto->entrada('pdf');
        if (! $pdf instanceof UploadedFile) {
            return AiSkillResult::fallo('La skill requiere el PDF de la factura.');
        }

        $inicio = microtime(true);

        try {
            $extraccion = $this->iaClient->extraer($pdf);
        } catch (Throwable $e) {
            $this->logger->registrar([
                'skill' => self::NOMBRE,
                'accion' => AiDecision::ACCION_ERROR,
                'driver' => $this->driverConfigurado(),
                'model' => $this->modeloConfigurado(),
                'empresa_id' => $contexto->empresaId,
                'usuario_id' => $contexto->usuarioId(),
                'entidad_tipo' => self::ENTIDAD,
                'latencia_ms' => $this->latenciaMs($inicio),
                'input_hash' => $this->hashPdf($pdf),
                'payload' => ['error' => $e->getMessage()],
            ]);

            return AiSkillResult::fallo($e->getMessage());
        }

        $latencia = $this->latenciaMs($inicio);
        $score = FacturaProveedorExtraccionScoreSupport::calcular($extraccion);
        $advertencias = FacturaProveedorExtraccionScoreSupport::advertencias($extraccion);

        $decision = $this->logger->registrar([
            'skill' => self::NOMBRE,
            'accion' => AiDecision::ACCION_SUGERIDA,
            'driver' => $this->driverConfigurado(),
            'model' => $this->modeloConfigurado(),
            'empresa_id' => $contexto->empresaId,
            'usuario_id' => $contexto->usuarioId(),
            'entidad_tipo' => self::ENTIDAD,
            'score' => $score,
            'latencia_ms' => $latencia,
            'input_hash' => $this->hashPdf($pdf),
            'payload' => $this->payloadAuditoria($extraccion, $pdf),
        ]);

        return AiSkillResult::sugerencia(
            $extraccion,
            $score,
            $advertencias,
            $this->policy->puedeAutoAplicar(self::NOMBRE, $score),
            $decision?->id,
        );
    }

    /**
     * Resumen auditable: datos de cabecera y conteo de líneas, sin el OCR completo.
     *
     * @param  array<string, mixed>  $extraccion
     * @return array<string, mixed>
     */
    private function payloadAuditoria(array $extraccion, UploadedFile $pdf): array
    {
        $lineas = is_array($extraccion['lineas'] ?? null) ? $extraccion['lineas'] : [];

        return [
            'archivo' => $pdf->getClientOriginalName(),
            'cuit_proveedor' => $extraccion['cuit_proveedor'] ?? null,
            'cuit_destinatario' => $extraccion['cuit_destinatario'] ?? null,
            'numero_oc' => $extraccion['numero_oc'] ?? null,
            'letra' => $extraccion['letra'] ?? null,
            'sucursal' => $extraccion['sucursal'] ?? null,
            'numero_factura' => $extraccion['numero_factura'] ?? null,
            'fecha_factura' => $extraccion['fecha_factura'] ?? null,
            'total' => $extraccion['total'] ?? null,
            'moneda' => $extraccion['moneda'] ?? null,
            'lineas_detectadas' => count($lineas),
            'fuentes' => $extraccion['_meta']['fuentes'] ?? [],
        ];
    }

    private function hashPdf(UploadedFile $pdf): ?string
    {
        $ruta = $pdf->getRealPath();
        if (! is_string($ruta) || $ruta === '' || ! is_readable($ruta)) {
            return null;
        }

        $hash = @hash_file('sha256', $ruta);

        return is_string($hash) ? $hash : null;
    }

    private function latenciaMs(float $inicio): int
    {
        return (int) round((microtime(true) - $inicio) * 1000);
    }

    private function driverConfigurado(): string
    {
        return (string) config('comprobante_proveedor_pdf_ia.driver', 'interno');
    }

    private function modeloConfigurado(): string
    {
        return (string) config('comprobante_proveedor_pdf_ia.ollama.model', '');
    }
}
