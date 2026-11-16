<?php

namespace App\Support\Caja;

use App\Services\Arca\ConstanciaInscripcionService;
use App\Support\Ventas\ArcaPadronImpuestosClienteValidacion;
use Throwable;

/**
 * Completa razón social / condición IVA eventual desde el padrón ARCA
 * (constancia de inscripción) cuando la IA no los trajo.
 *
 * No crea ni matchea proveedor del maestro: solo datos de referencia para I/E.
 */
final class ProveedorEventualDesdePadronArcaSupport
{
    public function __construct(
        private ConstanciaInscripcionService $constanciaInscripcion,
    ) {}

    /**
     * @return array{
     *     nombre: string,
     *     condicioniva_id: int|null,
     *     consultado: bool,
     *     advertencias: list<string>
     * }
     */
    public function completar(string $cuitDigitos, string $nombreActual, ?int $condicionivaIdActual = null): array
    {
        $nombre = trim($nombreActual);
        $condicionivaId = ($condicionivaIdActual !== null && $condicionivaIdActual > 0)
            ? $condicionivaIdActual
            : null;
        $advertencias = [];

        $cuit = preg_replace('/\D+/', '', $cuitDigitos) ?? '';
        if (strlen($cuit) !== 11) {
            return [
                'nombre' => $nombre,
                'condicioniva_id' => $condicionivaId,
                'consultado' => false,
                'advertencias' => $advertencias,
            ];
        }

        $faltaNombre = $nombre === '';
        $faltaCondicion = $condicionivaId === null;
        if (! $faltaNombre && ! $faltaCondicion) {
            return [
                'nombre' => $nombre,
                'condicioniva_id' => $condicionivaId,
                'consultado' => false,
                'advertencias' => $advertencias,
            ];
        }

        try {
            $padron = $this->constanciaInscripcion->getPersonaV2($cuit);
        } catch (Throwable $e) {
            $advertencias[] = 'No se pudo consultar el padrón ARCA para el CUIT '.$this->formatearCuit($cuit)
                .': '.$e->getMessage();

            return [
                'nombre' => $nombre,
                'condicioniva_id' => $condicionivaId,
                'consultado' => false,
                'advertencias' => $advertencias,
            ];
        }

        if (! empty($padron['error'])) {
            $advertencias[] = 'Padrón ARCA: '.(string) $padron['error'];

            return [
                'nombre' => $nombre,
                'condicioniva_id' => $condicionivaId,
                'consultado' => true,
                'advertencias' => $advertencias,
            ];
        }

        if ($faltaNombre) {
            $desdePadron = trim((string) (
                $padron['nombre']
                ?? $padron['razonSocial']
                ?? ''
            ));
            if ($desdePadron === '' && ! empty($padron['apellido'])) {
                $desdePadron = trim(implode(' ', array_filter([
                    (string) ($padron['apellido'] ?? ''),
                    (string) ($padron['nombrePersona'] ?? ''),
                ])));
            }
            if ($desdePadron !== '') {
                $nombre = $desdePadron;
                $advertencias[] = 'Razón social completada desde padrón ARCA.';
            }
        }

        if ($faltaCondicion) {
            $inferida = $this->inferirCondicionivaId($padron);
            if ($inferida !== null) {
                $condicionivaId = $inferida;
                $advertencias[] = 'Condición IVA sugerida desde padrón ARCA.';
            }
        }

        return [
            'nombre' => $nombre,
            'condicioniva_id' => $condicionivaId,
            'consultado' => true,
            'advertencias' => $advertencias,
        ];
    }

    /**
     * @param  array<string, mixed>  $padron
     */
    private function inferirCondicionivaId(array $padron): ?int
    {
        $impuestos = is_array($padron['impuestos'] ?? null) ? $padron['impuestos'] : [];
        $datosMono = is_array($padron['datosMonotributo'] ?? null) ? $padron['datosMonotributo'] : null;

        $ivaId = (int) config('arca.padron_validacion_proveedor.impuesto_iva_id', 30);
        $monoImpId = (int) config('arca.padron_validacion_proveedor.impuesto_monotributo_id', 20);
        $riId = (int) config('arca.padron_validacion_proveedor.condicioniva_responsable_inscripto_id', 1);
        $monoId = (int) config('arca.padron_validacion_proveedor.condicioniva_monotributo_id', 4);

        $ivaActivo = ArcaPadronImpuestosClienteValidacion::tieneImpuestoActivo($impuestos, $ivaId);
        if ($ivaActivo) {
            return $riId > 0 ? $riId : null;
        }

        $monoActivo = ArcaPadronImpuestosClienteValidacion::tieneImpuestoActivo($impuestos, $monoImpId, 'monotributo')
            || ArcaPadronImpuestosClienteValidacion::tieneImpuestoActivo($impuestos, $monoImpId);
        $tieneCategoria = is_array($datosMono) && ! empty($datosMono['categoriaMonotributo'] ?? $datosMono['categoria'] ?? null);

        if ($monoActivo || $tieneCategoria) {
            return $monoId > 0 ? $monoId : null;
        }

        return null;
    }

    private function formatearCuit(string $cuit11): string
    {
        if (strlen($cuit11) !== 11) {
            return $cuit11;
        }

        return substr($cuit11, 0, 2).'-'.substr($cuit11, 2, 8).'-'.substr($cuit11, 10, 1);
    }
}
