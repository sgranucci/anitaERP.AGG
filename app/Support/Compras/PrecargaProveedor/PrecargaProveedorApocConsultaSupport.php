<?php

namespace App\Support\Compras\PrecargaProveedor;

use App\Models\Compras\Proveedor;
use App\Support\Compras\ProveedorFacturasApocrifasSupport;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Consulta WSAPOC en precarga PDF+IA cuando ya se resolvió el proveedor.
 */
final class PrecargaProveedorApocConsultaSupport
{
    public function __construct(
        private ProveedorFacturasApocrifasSupport $apocSupport,
    ) {}

    /**
     * @param  array<string, mixed>  $resuelto
     * @return array<string, mixed>
     */
    public function consultarYEnriquecer(array $resuelto): array
    {
        if (! $this->apocSupport->habilitadoParaPrecargaIa()) {
            $resuelto['consulta_apoc'] = ['ejecutada' => false, 'motivo' => 'deshabilitado'];

            return $resuelto;
        }

        $proveedorId = (int) ($resuelto['proveedor_id'] ?? 0);
        if ($proveedorId <= 0) {
            $resuelto['consulta_apoc'] = [
                'ejecutada' => false,
                'motivo' => 'sin_proveedor',
            ];

            return $resuelto;
        }

        $proveedor = Proveedor::query()->find($proveedorId);
        if (! $proveedor) {
            $resuelto['consulta_apoc'] = [
                'ejecutada' => false,
                'motivo' => 'proveedor_inexistente',
            ];

            return $resuelto;
        }

        try {
            $eval = $this->apocSupport->evaluarProveedor($proveedor, suspenderSiApocrifo: true);
        } catch (Throwable $e) {
            Log::channel(config('comprobante_proveedor_pdf_ia.log_channel', 'stack'))
                ->warning('WSAPOC precarga: fallo de consulta', [
                    'proveedor_id' => $proveedorId,
                    'error' => $e->getMessage(),
                ]);

            $resuelto['consulta_apoc'] = [
                'ejecutada' => true,
                'ok' => false,
                'error' => $e->getMessage(),
            ];
            $resuelto['advertencias'] = $this->agregarAdvertencia(
                $resuelto['advertencias'] ?? [],
                'No se pudo consultar facturas apócrifas en ARCA: '.$e->getMessage()
            );
            $resuelto['pararevisar'] = true;

            return $resuelto;
        }

        return $this->aplicarEvaluacion($resuelto, $eval);
    }

    public function tieneProblemasApoc(array $resuelto): bool
    {
        $apoc = $resuelto['consulta_apoc'] ?? null;
        if (! is_array($apoc)) {
            return false;
        }

        return ($apoc['es_apocrifo'] ?? false) === true
            || ($apoc['ok'] ?? true) === false;
    }

    /**
     * @param  array<string, mixed>  $resuelto
     * @param  array<string, mixed>  $eval
     * @return array<string, mixed>
     */
    private function aplicarEvaluacion(array $resuelto, array $eval): array
    {
        $advertencias = $resuelto['advertencias'] ?? [];

        $resuelto['consulta_apoc'] = [
            'ejecutada' => true,
            'ok' => $eval['ok'] ?? false,
            'es_apocrifo' => $eval['es_apocrifo'] ?? false,
            'suspendido' => $eval['suspendido'] ?? false,
            'mensaje' => $eval['mensaje'] ?? null,
            'detalles' => $eval['detalles'] ?? [],
        ];

        if ($eval['es_apocrifo'] ?? false) {
            $advertencias = $this->agregarAdvertencia(
                $advertencias,
                '[ARCA APOC] '.($eval['mensaje'] ?? 'Proveedor con facturas apócrifas.')
            );
            if ($eval['suspendido'] ?? false) {
                $advertencias = $this->agregarAdvertencia(
                    $advertencias,
                    'El proveedor fue suspendido automáticamente por figurar en la base APOC de ARCA.'
                );
            }
            $resuelto['pararevisar'] = true;
        } elseif (! ($eval['ok'] ?? true) && ($eval['mensaje'] ?? '') !== '') {
            $advertencias = $this->agregarAdvertencia($advertencias, '[ARCA APOC] '.$eval['mensaje']);
            $resuelto['pararevisar'] = true;
        }

        $resuelto['advertencias'] = $advertencias;

        return $resuelto;
    }

    /**
     * @param  list<string>  $advertencias
     * @return list<string>
     */
    private function agregarAdvertencia(array $advertencias, string $mensaje): array
    {
        $mensaje = trim($mensaje);
        if ($mensaje === '') {
            return $advertencias;
        }

        if (! in_array($mensaje, $advertencias, true)) {
            $advertencias[] = $mensaje;
        }

        return $advertencias;
    }
}
