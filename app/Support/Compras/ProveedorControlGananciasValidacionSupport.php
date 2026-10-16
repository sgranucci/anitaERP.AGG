<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Models\Compras\Proveedor;
use App\Models\Configuracion\Condicioniva;
use App\Support\Configuracion\CondicionivaLetraComprasSupport;
use App\Support\Ventas\ArcaPadronImpuestosClienteValidacion;

/**
 * Arma filas del reporte de control de ganancias (solo lectura / propuesta).
 */
final class ProveedorControlGananciasValidacionSupport
{
    public const SEGMENTO_RI = 'ri';

    public const SEGMENTO_MONO = 'mono';

    public const SEGMENTO_OTRO = 'otro';

    /**
     * @param  array<string, mixed>|null  $padronData
     * @return array<string, mixed>
     */
    public static function armarFila(Proveedor $proveedor, ?array $padronData, ?string $errorArca = null): array
    {
        $condicion = $proveedor->condicionivas;
        $segmento = self::segmento($condicion);
        $retActual = $proveedor->retencionganancias;

        $cuit = preg_replace('/\D+/', '', (string) ($proveedor->nroinscripcion ?? '')) ?? '';
        $actividad = is_array($padronData['actividadPrincipal'] ?? null)
            ? $padronData['actividadPrincipal']
            : null;
        $idActividad = isset($actividad['idActividad']) ? (int) $actividad['idActividad'] : null;
        $descActividad = isset($actividad['descripcionActividad'])
            ? (string) $actividad['descripcionActividad']
            : null;

        if ($segmento === self::SEGMENTO_MONO) {
            $propuesta = ProveedorActividadRetencionGananciasSupport::proponerSinRetencion();
        } elseif ($padronData === null && ($errorArca === null || $errorArca === '')) {
            // Sin consulta ARCA: no inventar propuesta ni marcar “sin actividad”.
            $propuesta = [
                'codigo' => null,
                'nombre' => null,
                'retencionganancia_id' => null,
                'origen' => 'sin_consulta_arca',
                'mensaje' => null,
            ];
        } else {
            $propuesta = ProveedorActividadRetencionGananciasSupport::proponerDesdeActividad(
                $idActividad > 0 ? $idActividad : null
            );
        }

        $padronOk = null;
        $padronMensaje = null;
        if ($errorArca !== null && $errorArca !== '') {
            $padronOk = false;
            $padronMensaje = $errorArca;
        } elseif ($padronData !== null) {
            $condicionParaPadron = self::condicionivaIdParaValidacionPadron($segmento, $condicion);
            $validacion = ArcaPadronImpuestosClienteValidacion::validar($condicionParaPadron, $padronData);
            if ($validacion['aplica'] ?? false) {
                $padronOk = (bool) ($validacion['ok'] ?? false);
                $padronMensaje = $validacion['mensaje'] ?? null;
            } else {
                $padronOk = true;
                $padronMensaje = null;
            }
            if (! empty($padronData['error'])) {
                $padronOk = false;
                $padronMensaje = (string) $padronData['error'];
            }
        }

        $obs = self::observaciones(
            $segmento,
            $condicion,
            $retActual,
            $propuesta,
            $padronOk,
            $padronMensaje,
            $errorArca
        );

        $codigoActual = $retActual ? trim((string) $retActual->codigo) : '';
        $codigoPropuesto = $propuesta['codigo'] !== null ? trim((string) $propuesta['codigo']) : '';
        $discrepanciaRetencion = $codigoPropuesto !== '' && $codigoActual !== $codigoPropuesto;
        $discrepanciaMonoCondicion = $segmento === self::SEGMENTO_MONO
            && ! self::condicionMonoEsperada($condicion);
        $discrepancia = $discrepanciaRetencion
            || $discrepanciaMonoCondicion
            || ($padronOk === false)
            || ($obs !== []);

        return [
            'proveedor_id' => (int) $proveedor->id,
            'codigo' => (string) ($proveedor->codigo ?? ''),
            'cuit' => strlen($cuit) === 11 ? $cuit : (string) ($proveedor->nroinscripcion ?? ''),
            'razon_social' => (string) ($proveedor->nombre ?? ''),
            'estado' => (string) ($proveedor->estado ?? ''),
            'segmento' => $segmento,
            'segmento_etiqueta' => match ($segmento) {
                self::SEGMENTO_RI => 'Responsable Inscripto',
                self::SEGMENTO_MONO => 'Monotributista',
                default => 'Otro',
            },
            'condicioniva_id' => $condicion ? (int) $condicion->id : null,
            'condicioniva_nombre' => $condicion ? (string) $condicion->nombre : '',
            'condicioniva_ok_mono' => $segmento === self::SEGMENTO_MONO
                ? self::condicionMonoEsperada($condicion)
                : null,
            'retieneganancia' => (string) ($proveedor->retieneganancia ?? ''),
            'condicionganancia' => (string) ($proveedor->condicionganancia ?? ''),
            'retencion_actual_id' => $retActual ? (int) $retActual->id : null,
            'retencion_actual_codigo' => $codigoActual,
            'retencion_actual_nombre' => $retActual ? (string) $retActual->nombre : '',
            'actividad_id' => $idActividad > 0 ? $idActividad : null,
            'actividad_descripcion' => $descActividad ?? '',
            'actividad_fuente' => isset($actividad['fuente']) ? (string) $actividad['fuente'] : '',
            'retencion_propuesta_codigo' => $codigoPropuesto,
            'retencion_propuesta_nombre' => $propuesta['nombre'] ?? '',
            'retencion_propuesta_origen' => $propuesta['origen'] ?? '',
            'padron_ok' => $padronOk,
            'padron_mensaje' => $padronMensaje,
            'discrepancia' => $discrepancia,
            'observaciones' => implode(' | ', $obs),
        ];
    }

    public static function segmento(?Condicioniva $condicion): string
    {
        if ($condicion === null) {
            return self::SEGMENTO_OTRO;
        }

        $riId = (int) config('arca.padron_validacion_proveedor.condicioniva_responsable_inscripto_id', 1);
        if ((int) $condicion->id === $riId) {
            return self::SEGMENTO_RI;
        }

        if (CondicionivaLetraComprasSupport::esMonotributo($condicion)) {
            return self::SEGMENTO_MONO;
        }

        $nombre = self::normalizarTexto((string) ($condicion->nombre ?? ''));
        if (str_contains($nombre, 'responsable inscripto') || $nombre === 'ri') {
            return self::SEGMENTO_RI;
        }

        return self::SEGMENTO_OTRO;
    }

    public static function condicionMonoEsperada(?Condicioniva $condicion): bool
    {
        if ($condicion === null) {
            return false;
        }

        $esperado = self::normalizarTexto(
            (string) config(
                'compras_control_ganancias.condicioniva_monotributo_nombre_esperado',
                'Monotributo C Proveedores'
            )
        );
        $actual = self::normalizarTexto((string) ($condicion->nombre ?? ''));

        if ($esperado === '' || $actual === '') {
            return false;
        }

        return $actual === $esperado
            || str_contains($actual, $esperado)
            || str_contains($esperado, $actual);
    }

    /**
     * @param  array<string, mixed>  $propuesta
     * @return list<string>
     */
    private static function observaciones(
        string $segmento,
        ?Condicioniva $condicion,
        mixed $retActual,
        array $propuesta,
        ?bool $padronOk,
        ?string $padronMensaje,
        ?string $errorArca
    ): array {
        $obs = [];

        if ($errorArca) {
            $obs[] = 'ARCA: '.$errorArca;
        } elseif ($padronOk === false && $padronMensaje) {
            $obs[] = $padronMensaje;
        }

        if ($segmento === self::SEGMENTO_MONO) {
            if (! self::condicionMonoEsperada($condicion)) {
                $esperado = (string) config(
                    'compras_control_ganancias.condicioniva_monotributo_nombre_esperado',
                    'Monotributo C Proveedores'
                );
                $obs[] = 'Condición IVA ABM distinta de "'.$esperado.'".';
            }
            $codigoActual = $retActual ? trim((string) $retActual->codigo) : '';
            $codigoProp = $propuesta['codigo'] !== null ? trim((string) $propuesta['codigo']) : '';
            if ($codigoProp !== '' && $codigoActual !== $codigoProp) {
                $obs[] = 'Debería tener retención "No aplica" (código '.$codigoProp.').';
            }
        }

        if ($segmento === self::SEGMENTO_RI) {
            if (($propuesta['mensaje'] ?? null) !== null) {
                $obs[] = (string) $propuesta['mensaje'];
            }
            $codigoActual = $retActual ? trim((string) $retActual->codigo) : '';
            $codigoProp = $propuesta['codigo'] !== null ? trim((string) $propuesta['codigo']) : '';
            if ($codigoProp !== '' && $codigoActual !== $codigoProp) {
                $obs[] = 'Código actual ('.$codigoActual.') ≠ propuesto ('.$codigoProp.').';
            }
        }

        if ($segmento === self::SEGMENTO_OTRO) {
            $obs[] = 'Condición IVA fuera de RI/Monotributo; solo informativo.';
        }

        return $obs;
    }

    private static function condicionivaIdParaValidacionPadron(string $segmento, ?Condicioniva $condicion): ?int
    {
        if ($segmento === self::SEGMENTO_RI) {
            return (int) config('arca.padron_validacion_cliente.condicioniva_responsable_inscripto_id', 1);
        }
        if ($segmento === self::SEGMENTO_MONO) {
            return (int) config('arca.padron_validacion_cliente.condicioniva_monotributo_id', 4);
        }

        return $condicion ? (int) $condicion->id : null;
    }

    private static function normalizarTexto(string $v): string
    {
        $v = mb_strtolower(trim($v));
        $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);
        if (is_string($trans) && $trans !== '') {
            $v = $trans;
        }
        $v = preg_replace('/[^a-z0-9 ]+/', ' ', $v) ?? $v;

        return trim(preg_replace('/\s+/', ' ', $v) ?? $v);
    }
}
