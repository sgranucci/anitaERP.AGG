<?php

namespace App\Support\Compras\PrecargaProveedor\FacturaPdfIa;

/**
 * Fusiona extracción heurística + Ollama priorizando valores no vacíos y líneas de conceptos más completas.
 */
final class FacturaProveedorExtraccionFusionSupport
{
    /**
     * @param  array<string, mixed>  $heuristica
     * @param  ?array<string, mixed>  $ollama
     * @return array<string, mixed>
     */
    public function fusionar(array $heuristica, ?array $ollama): array
    {
        if ($ollama === null) {
            return $this->normalizarSalida($heuristica, ['heuristica']);
        }

        $campos = [
            'cuit_destinatario', 'cuit_proveedor', 'numero_oc', 'tipo_comprobante', 'letra',
            'sucursal', 'numero_factura', 'fecha_factura', 'numerocae', 'fecha_vto_cai_cae',
            'subtotal', 'total', 'moneda', 'cotizacion',
        ];

        $fusion = [];
        foreach ($campos as $campo) {
            $fusion[$campo] = $this->elegir($ollama[$campo] ?? null, $heuristica[$campo] ?? null);
        }

        $fusion['lineas'] = $this->fusionarLineas(
            $heuristica['lineas'] ?? [],
            $ollama['lineas'] ?? []
        );

        return $this->normalizarSalida($fusion, $ollama !== null ? ['heuristica', 'ollama'] : ['heuristica']);
    }

    private function elegir(mixed $ollama, mixed $heuristica): mixed
    {
        if ($this->tieneValor($ollama)) {
            return $ollama;
        }

        return $heuristica;
    }

    private function tieneValor(mixed $v): bool
    {
        if ($v === null || $v === '') {
            return false;
        }
        if (is_numeric($v) && (float) $v === 0.0) {
            return false;
        }

        return true;
    }

    /**
     * @param  mixed  $lineasHeur
     * @param  mixed  $lineasOllama
     * @return list<array<string, mixed>>
     */
    private function fusionarLineas(mixed $lineasHeur, mixed $lineasOllama): array
    {
        $h = is_array($lineasHeur) ? $lineasHeur : [];
        $o = is_array($lineasOllama) ? $lineasOllama : [];

        if (count($o) >= count($h)) {
            return $this->sanearLineas($o !== [] ? $o : $h);
        }

        $merged = $h;
        foreach ($o as $lineaO) {
            if (! is_array($lineaO)) {
                continue;
            }
            $duplicada = false;
            foreach ($merged as $lineaH) {
                if (! is_array($lineaH)) {
                    continue;
                }
                if (($lineaH['tipo'] ?? '') === ($lineaO['tipo'] ?? '')
                    && ($lineaH['alicuota_iva'] ?? null) == ($lineaO['alicuota_iva'] ?? null)) {
                    $duplicada = true;
                    break;
                }
            }
            if (! $duplicada) {
                $merged[] = $lineaO;
            }
        }

        return $this->sanearLineas($merged);
    }

    /**
     * @param  list<mixed>  $lineas
     * @return list<array<string, mixed>>
     */
    private function sanearLineas(array $lineas): array
    {
        $salida = [];
        foreach ($lineas as $linea) {
            if (! is_array($linea)) {
                continue;
            }
            $importe = round(abs((float) ($linea['importe'] ?? 0)), 2);
            if ($importe <= 0) {
                continue;
            }
            $salida[] = [
                'descripcion' => (string) ($linea['descripcion'] ?? $linea['tipo'] ?? 'Concepto'),
                'importe' => $importe,
                'alicuota_iva' => isset($linea['alicuota_iva']) ? (float) $linea['alicuota_iva'] : null,
                'tipo' => (string) ($linea['tipo'] ?? 'neto'),
            ];
        }

        return $salida;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $fuentes
     * @return array<string, mixed>
     */
    private function normalizarSalida(array $data, array $fuentes): array
    {
        unset($data['_meta']);
        $data['lineas'] = $this->sanearLineas($data['lineas'] ?? []);
        $data['_meta'] = [
            'fuentes' => $fuentes,
            'lineas_detectadas' => count($data['lineas']),
        ];

        return $data;
    }
}
