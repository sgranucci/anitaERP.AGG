<?php

namespace App\Support\Compras\PrecargaProveedor\FacturaPdfIa;

use App\Support\Compras\PrecargaProveedor\PrecargaProveedorTipoComprobanteSupport;

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
            'numero_oc', 'tipo_comprobante', 'letra',
            'sucursal', 'numero_factura', 'fecha_factura', 'numerocae', 'tipo_autorizacion', 'fecha_vto_cai_cae',
            'fecha_vencimiento',
            'subtotal', 'total', 'moneda', 'cotizacion',
        ];

        $fusion = [];
        foreach ($campos as $campo) {
            // tipo_autorizacion: priorizar heurística (detecta CAEA vs CAE en el texto).
            if ($campo === 'tipo_autorizacion') {
                $fusion[$campo] = $this->elegir($heuristica[$campo] ?? null, $ollama[$campo] ?? null);
                continue;
            }
            // moneda/cotización: heurística manda. Ollama confunde el "$" argentino con USD.
            if ($campo === 'moneda' || $campo === 'cotizacion') {
                $fusion[$campo] = $this->elegir($heuristica[$campo] ?? null, $ollama[$campo] ?? null);
                continue;
            }
            $fusion[$campo] = $this->elegir($ollama[$campo] ?? null, $heuristica[$campo] ?? null);
        }

        // CUITs: la heurística etiqueta emisor/receptor; Ollama suele invertarlos (NC AFIP).
        $cuits = $this->fusionarCuits($heuristica, $ollama);
        $fusion['cuit_proveedor'] = $cuits['proveedor'];
        $fusion['cuit_destinatario'] = $cuits['destinatario'];

        // Tipo genérico: si la heurística vio ND/NC y el LLM dejó FC, priorizar ND/NC.
        $fusion['tipo_comprobante'] = $this->fusionarTipoComprobante(
            $ollama['tipo_comprobante'] ?? null,
            $heuristica['tipo_comprobante'] ?? null
        );

        // Si Ollama tomó el SUBTOTAL como TOTAL, preferir el TOTAL heurístico mayor.
        $totalH = isset($heuristica['total']) ? (float) $heuristica['total'] : null;
        $totalO = isset($ollama['total']) ? (float) $ollama['total'] : null;
        $subH = isset($heuristica['subtotal']) ? (float) $heuristica['subtotal'] : null;
        if ($totalH !== null && $totalH > 0 && $totalO !== null && $totalO > 0) {
            $ollamaPareceSubtotal = ($subH !== null && abs($totalO - $subH) <= 0.05)
                || ($totalH > $totalO + 0.05);
            if ($ollamaPareceSubtotal && $totalH > $totalO) {
                $fusion['total'] = $totalH;
            }
        }

        $total = isset($fusion['total']) ? (float) $fusion['total'] : null;
        $fusion['lineas'] = $this->fusionarLineas(
            $heuristica['lineas'] ?? [],
            $ollama['lineas'] ?? [],
            $total
        );

        $fusion['articulos'] = $this->fusionarArticulos(
            $heuristica['articulos'] ?? [],
            $ollama['articulos'] ?? $ollama['lineas_articulo'] ?? $ollama['items'] ?? [],
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

    /**
     * Prioriza el par heurístico cuando el LLM solo intercambia emisor ↔ receptor.
     *
     * @param  array<string, mixed>  $heuristica
     * @param  array<string, mixed>  $ollama
     * @return array{proveedor: mixed, destinatario: mixed}
     */
    private function fusionarCuits(array $heuristica, array $ollama): array
    {
        $hProv = $heuristica['cuit_proveedor'] ?? null;
        $hDest = $heuristica['cuit_destinatario'] ?? null;
        $oProv = $ollama['cuit_proveedor'] ?? null;
        $oDest = $ollama['cuit_destinatario'] ?? null;

        $hProvD = $this->cuitSoloDigitos($hProv);
        $hDestD = $this->cuitSoloDigitos($hDest);
        $oProvD = $this->cuitSoloDigitos($oProv);
        $oDestD = $this->cuitSoloDigitos($oDest);

        // Swap clásico: mismos dos CUITs, roles invertidos → confiar en heurística.
        if ($hProvD !== '' && $hDestD !== '' && $oProvD !== '' && $oDestD !== ''
            && $hProvD === $oDestD && $hDestD === $oProvD) {
            return ['proveedor' => $hProv, 'destinatario' => $hDest];
        }

        // Si ambos lados tienen valor y discrepan, preferir heurística (etiquetado OCR).
        $proveedor = ($this->tieneValor($hProv) && $this->tieneValor($oProv) && $hProvD !== $oProvD)
            ? $hProv
            : $this->elegir($oProv, $hProv);
        $destinatario = ($this->tieneValor($hDest) && $this->tieneValor($oDest) && $hDestD !== $oDestD)
            ? $hDest
            : $this->elegir($oDest, $hDest);

        return ['proveedor' => $proveedor, 'destinatario' => $destinatario];
    }

    private function cuitSoloDigitos(mixed $cuit): string
    {
        if (! $this->tieneValor($cuit)) {
            return '';
        }

        return preg_replace('/\D/', '', (string) $cuit) ?? '';
    }

    private function fusionarTipoComprobante(mixed $ollama, mixed $heuristica): string
    {
        $tO = PrecargaProveedorTipoComprobanteSupport::normalizar(
            is_string($ollama) || is_numeric($ollama) ? (string) $ollama : null
        );
        $tH = PrecargaProveedorTipoComprobanteSupport::normalizar(
            is_string($heuristica) || is_numeric($heuristica) ? (string) $heuristica : null
        );

        if (in_array($tH, ['ND', 'NC'], true) && $tO === 'FC') {
            return $tH;
        }
        if (in_array($tO, ['ND', 'NC', 'REC', 'REM'], true)) {
            return $tO;
        }
        if (in_array($tH, ['ND', 'NC', 'REC', 'REM'], true)) {
            return $tH;
        }

        return 'FC';
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
    private function fusionarLineas(mixed $lineasHeur, mixed $lineasOllama, ?float $totalFactura = null): array
    {
        $h = $this->sanearLineas(is_array($lineasHeur) ? $lineasHeur : []);
        $o = $this->sanearLineas(is_array($lineasOllama) ? $lineasOllama : []);

        if ($h === [] && $o === []) {
            return [];
        }
        if ($h === []) {
            return $o;
        }
        if ($o === []) {
            return $h;
        }

        // Si conocemos el total, gana el set cuya suma cierra mejor (evita IVA truncado del LLM).
        if ($totalFactura !== null && $totalFactura > 0) {
            $errH = abs($this->sumaImportes($h) - $totalFactura);
            $errO = abs($this->sumaImportes($o) - $totalFactura);
            if ($errH + 0.05 < $errO) {
                return $h;
            }
            if ($errO + 0.05 < $errH) {
                return $o;
            }
        }

        if (count($o) >= count($h)) {
            return $o;
        }

        $merged = $h;
        foreach ($o as $lineaO) {
            $duplicada = false;
            foreach ($merged as $lineaH) {
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

    /** @param  list<array<string, mixed>>  $lineas */
    private function sumaImportes(array $lineas): float
    {
        $suma = 0.0;
        foreach ($lineas as $linea) {
            $suma += (float) ($linea['importe'] ?? 0);
        }

        return round($suma, 2);
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
        $data['articulos'] = $this->sanearArticulos($data['articulos'] ?? []);
        $data['_meta'] = [
            'fuentes' => $fuentes,
            'lineas_detectadas' => count($data['lineas']),
            'articulos_detectados' => count($data['articulos']),
        ];

        return $data;
    }

    /**
     * @param  mixed  $heur
     * @param  mixed  $ollama
     * @return list<array<string, mixed>>
     */
    private function fusionarArticulos(mixed $heur, mixed $ollama): array
    {
        $h = is_array($heur) ? $heur : [];
        $o = is_array($ollama) ? $ollama : [];
        // Preferir Ollama si trajo ítems; si no, heurística.
        if ($o !== []) {
            return $o;
        }

        return $h;
    }

    /**
     * @param  mixed  $articulos
     * @return list<array{sku: string, codigo_proveedor: string, descripcion: string, cantidad: float, precio_unitario: float}>
     */
    private function sanearArticulos(mixed $articulos): array
    {
        if (! is_array($articulos)) {
            return [];
        }

        $salida = [];
        foreach ($articulos as $fila) {
            if (! is_array($fila)) {
                continue;
            }
            $tipo = strtolower(trim((string) ($fila['tipo'] ?? '')));
            if ($tipo !== '' && in_array($tipo, ['neto', 'iva', 'exento', 'percepcion', 'otro'], true)) {
                continue;
            }
            $sku = trim((string) ($fila['sku'] ?? $fila['codigo'] ?? ''));
            $codProv = trim((string) ($fila['codigo_proveedor'] ?? $fila['codigo_articulo_proveedor'] ?? ''));
            $desc = trim((string) ($fila['descripcion'] ?? $fila['detalle'] ?? ''));
            $cant = (float) ($fila['cantidad'] ?? $fila['qty'] ?? 0);
            $precio = (float) ($fila['precio_unitario'] ?? $fila['precio'] ?? $fila['precio_unit'] ?? 0);
            if ($sku === '' && $codProv === '' && $desc === '') {
                continue;
            }
            if ($cant <= 0 && $precio <= 0) {
                continue;
            }
            $salida[] = [
                'sku' => $sku !== '' ? $sku : $codProv,
                'codigo_proveedor' => $codProv !== '' ? $codProv : $sku,
                'descripcion' => mb_substr($desc, 0, 255),
                'cantidad' => $cant > 0 ? $cant : 1.0,
                'precio_unitario' => $precio,
            ];
        }

        return $salida;
    }
}
