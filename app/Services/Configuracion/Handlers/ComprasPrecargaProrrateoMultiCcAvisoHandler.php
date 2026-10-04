<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoHandlerInterface;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Services\Compras\OrdencompraService;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorAbreviaturaTipoSupport;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorProrrateoMultiCcSupport;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorTipoItemSupport;
use App\Support\Navegacion\ModoConsultaUrlSupport;
use Throwable;

/**
 * Aviso de prueba/ops: precarga API grabada como tipo prorrateado multi-CC (FPB/…).
 * entityId = precarga_comprobante_proveedor.id
 */
class ComprasPrecargaProrrateoMultiCcAvisoHandler implements ModuloAvisoHandlerInterface
{
    public function contextoFiltro(int $entityId): array
    {
        $precarga = $this->precarga($entityId);

        return [
            'empresa_id' => (int) ($precarga->empresa_id ?? 0) ?: null,
            'centrocosto_id' => null,
        ];
    }

    public function placeholders(int $entityId): array
    {
        $precarga = $this->precarga($entityId);
        $tipo = (string) ($precarga?->tipotransaccion_compras?->abreviatura ?? '???');
        $letra = strtoupper(substr((string) ($precarga->letra ?? '?'), 0, 1));
        $comprobante = sprintf(
            '%s %s-%s-%s',
            $tipo,
            $letra,
            (int) ($precarga->sucursal ?? 0),
            (int) ($precarga->numerocomprobante ?? 0)
        );

        $conceptos = $precarga->precarga_comprobante_proveedor_conceptos ?? collect();
        $detalleConceptos = $conceptos->map(function ($linea) {
            $cod = (string) (optional($linea->concepto_ivacompras)->codigo ?? $linea->concepto_ivacompra_id);
            $nom = (string) (optional($linea->concepto_ivacompras)->nombre ?? '');
            $tipoConc = strtoupper(trim((string) (optional($linea->concepto_ivacompras)->tipoconcepto ?? '')));
            $monto = number_format((float) ($linea->monto ?? 0), 2, ',', '.');
            $prefijo = $tipoConc !== '' ? '['.$tipoConc.'] ' : '';

            return trim($prefijo.$cod.' '.$nom).': '.$monto;
        })->filter()->take(30)->implode("\n");

        $meta = $this->metaProrrateoDesdeOc($precarga);
        $pesosTxt = '—';
        if ($meta['pesos_por_fino'] !== []) {
            $partes = [];
            foreach ($meta['pesos_por_fino'] as $fino => $peso) {
                $partes[] = $fino.' '.number_format((float) $peso, 2, ',', '.');
            }
            $pesosTxt = implode(' · ', $partes);
        }

        $tieneIva = $conceptos->contains(function ($linea) {
            return strtoupper(trim((string) (optional($linea->concepto_ivacompras)->tipoconcepto ?? ''))) === 'I';
        });
        $sumaConceptos = round((float) $conceptos->sum(fn ($linea) => (float) ($linea->monto ?? 0)), 2);
        $totalPrecarga = round((float) ($precarga->total ?? 0), 2);
        $cuadra = $totalPrecarga > 0 && abs($sumaConceptos - $totalPrecarga) <= 0.90;

        $partesAlerta = [];
        if ((bool) ($precarga->pararevisar ?? false)) {
            $partesAlerta[] = 'Marcada para revisar.';
        }
        if (! $cuadra) {
            $partesAlerta[] = 'Los conceptos suman '.number_format($sumaConceptos, 2, ',', '.')
                .' y el total es '.number_format($totalPrecarga, 2, ',', '.').'.';
        }
        if (! $tieneIva && $cuadra) {
            $partesAlerta[] = 'Sin IVA discriminado (factura exenta o no gravada): no hay IVA para prorratear.';
        }
        $alerta = implode(' ', $partesAlerta);

        return [
            'empresa' => (string) (optional($precarga?->empresas)->nombre ?? '—'),
            'proveedor' => (string) (optional($precarga?->proveedores)->nombre ?? '—'),
            'comprobante' => $comprobante,
            'tipo' => $tipo,
            'fecha' => $precarga?->fechafactura ? $precarga->fechafactura->format('d/m/Y') : '—',
            'total' => number_format((float) ($precarga->total ?? 0), 2, ',', '.'),
            'subtotal' => number_format((float) ($precarga->subtotal ?? 0), 2, ',', '.'),
            'oc' => (string) ($precarga->numeroordencompra ?? '—'),
            'centros' => $meta['centros'] !== [] ? implode('/', $meta['centros']) : '—',
            'tipos_origen' => $meta['tipos_origen'] !== [] ? implode('+', $meta['tipos_origen']) : '—',
            'pesos' => $pesosTxt,
            'alerta' => $alerta !== '' ? $alerta : '—',
            'conceptos' => $detalleConceptos !== '' ? $detalleConceptos : '—',
            'cuadro_oc' => $this->cuadroOcHtml($precarga, $tieneIva),
        ];
    }

    public function linkConsulta(int $entityId): ?string
    {
        if ($entityId <= 0) {
            return null;
        }

        return ModoConsultaUrlSupport::urlAbsolutaConConsulta(
            'compras/precarga_comprobante_proveedor/'.$entityId.'/editar'
        );
    }

    public function generarPdf(int $entityId): ?array
    {
        return null;
    }

    /**
     * Cuadro HTML de la OC: cada línea es el peso con el que se partiría el IVA.
     * Va entre marcas para que el mail lo pinte como tabla y el resto del texto siga escapado.
     */
    private function cuadroOcHtml(Precarga_Comprobante_Proveedor $precarga, bool $tieneIva): string
    {
        $numeroOc = trim((string) ($precarga->numeroordencompra ?? ''));
        if ($numeroOc === '') {
            return '—';
        }

        $oc = Ordencompra::query()
            ->where('numeroordencompra', $numeroOc)
            ->orWhere('numeroordencompra', ltrim($numeroOc, '0'))
            ->first();
        if (! $oc) {
            return '—';
        }

        $oc->load([
            'ordencompra_articulos.centrocostos_destino:id,codigo,nombre,tipoiva',
            'ordencompra_articulos.articulos:id,sku,descripcion',
        ]);

        $tipo = strtoupper(trim((string) ($precarga->tipotransaccion_compras->abreviatura ?? 'FPB')));
        $familia = PrecargaProveedorProrrateoMultiCcSupport::familiaDesdeAbreviatura($tipo);
        $tipoItem = 'B';
        try {
            $tipoItem = PrecargaProveedorAbreviaturaTipoSupport::tipoItemDesdeOrdencompra($oc);
        } catch (Throwable) {
            $tipoItem = 'B';
        }

        $filas = [];
        $pesos = [];
        foreach ($oc->ordencompra_articulos as $linea) {
            $cc = $linea->centrocostos_destino;
            $codigo = trim((string) ($cc->codigo ?? ''));
            if ($codigo === '') {
                continue;
            }
            $importe = round(max(0.0, (float) $linea->cantidad * (float) $linea->precio), 2);
            $sigla = PrecargaProveedorAbreviaturaTipoSupport::abreviatura(
                $familia,
                $codigo,
                (string) ($cc->tipoiva ?? ''),
                $tipoItem,
            );
            if ($sigla === '') {
                $sigla = '—';
            }
            $filas[] = [
                'articulo' => trim((string) (optional($linea->articulos)->descripcion ?: $linea->detalle ?: '—')),
                'centro' => $codigo.' '.trim((string) ($cc->nombre ?? '')),
                'tratamiento' => trim((string) ($cc->tipoiva ?? '')) ?: '—',
                'sigla' => $sigla,
                'importe' => $importe,
            ];
            $pesos[$sigla] = round(($pesos[$sigla] ?? 0) + $importe, 2);
        }

        if ($filas === []) {
            return '—';
        }

        ksort($pesos);
        $totalOc = round(array_sum($pesos), 2);
        $e = static fn (string $valor): string => htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $monto = static fn (float $valor): string => number_format($valor, 2, ',', '.');
        $pct = static function (float $parte, float $total): string {
            if ($total <= 0) {
                return '—';
            }

            return number_format($parte / $total * 100, 2, ',', '.').'%';
        };

        $letra = strtoupper(substr($tipo, 0, 1));
        $queEs = match ($letra) {
            'C' => 'nota de crédito',
            'D' => 'nota de débito',
            default => 'factura',
        };

        $html = '<p style="margin:16px 0 8px 0;">Orden de compra <strong>'.$e($numeroOc)
            .'</strong>. Estos importes son el peso del prorrateo. '
            .'La sigla es el tipo que le correspondería a ese centro si el comprobante fuera solo de ese centro. '
            .'Este comprobante es <strong>'.$e($tipo).'</strong> ('.$e($queEs).'), por eso la sigla empieza con '
            .$e($letra).'. La segunda letra es el tratamiento del centro (N no computable, I indirecto, D directo). '
            .'La tercera es el ítem (B bienes).</p>';
        $html .= '<table cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:13px;margin:0 0 12px 0;">';
        $html .= '<thead><tr style="background:#85C1E9;color:#17202A;">'
            .'<th align="left" style="border:1px solid #cccccc;">Artículo</th>'
            .'<th align="left" style="border:1px solid #cccccc;">Centro</th>'
            .'<th align="left" style="border:1px solid #cccccc;">Tratamiento de IVA</th>'
            .'<th align="left" style="border:1px solid #cccccc;">Sigla</th>'
            .'<th align="right" style="border:1px solid #cccccc;">Importe en la OC</th>'
            .'</tr></thead><tbody>';
        foreach ($filas as $fila) {
            $html .= '<tr>'
                .'<td style="border:1px solid #cccccc;">'.$e($fila['articulo']).'</td>'
                .'<td style="border:1px solid #cccccc;">'.$e($fila['centro']).'</td>'
                .'<td style="border:1px solid #cccccc;">'.$e($fila['tratamiento']).'</td>'
                .'<td style="border:1px solid #cccccc;">'.$e($fila['sigla']).'</td>'
                .'<td align="right" style="border:1px solid #cccccc;">'.$e($monto($fila['importe'])).'</td>'
                .'</tr>';
        }
        foreach ($pesos as $sigla => $peso) {
            $html .= '<tr style="background:#EAF2F8;font-weight:bold;">'
                .'<td colspan="4" style="border:1px solid #cccccc;">Peso '.$e($sigla)
                .' — '.$e($pct($peso, $totalOc)).' del IVA, si el comprobante trae IVA</td>'
                .'<td align="right" style="border:1px solid #cccccc;">'.$e($monto($peso)).'</td>'
                .'</tr>';
        }
        $html .= '<tr style="background:#D6EAF8;font-weight:bold;">'
            .'<td colspan="4" style="border:1px solid #cccccc;">Total de la orden (suma de los pesos)</td>'
            .'<td align="right" style="border:1px solid #cccccc;">'.$e($monto($totalOc)).'</td>'
            .'</tr>';
        $html .= '</tbody></table>';

        $totalPrecarga = number_format((float) ($precarga->total ?? 0), 2, ',', '.');
        $html .= '<p style="margin:0 0 12px 0;">El total de esta precarga es <strong>'.$e($totalPrecarga)
            .'</strong>. El neto gravado queda en una sola línea y no se parte entre centros. ';
        if ($tieneIva) {
            $html .= 'El IVA sí se parte con los porcentajes de arriba.</p>';
        } else {
            $html .= 'Esta precarga no trae IVA, así que esos pesos no se aplicaron a ningún importe.</p>';
        }

        return "[[CUADRO_OC_HTML]]\n".$html."\n[[/CUADRO_OC_HTML]]";
    }

    /**
     * @return array{
     *   centros: list<string>,
     *   tipos_origen: list<string>,
     *   pesos_por_fino: array<string, float>
     * }
     */
    private function metaProrrateoDesdeOc(Precarga_Comprobante_Proveedor $precarga): array
    {
        $vacio = ['centros' => [], 'tipos_origen' => [], 'pesos_por_fino' => []];
        $numeroOc = trim((string) ($precarga->numeroordencompra ?? ''));
        if ($numeroOc === '') {
            return $vacio;
        }

        try {
            $ordencompra = app(OrdencompraService::class)->leeOrdenCompra($numeroOc);
            if ($ordencompra === 'OC inexistente') {
                return $vacio;
            }
            $itemsOc = $ordencompra['item'] ?? [];
            $tipo = (string) ($precarga->tipotransaccion_compras->abreviatura ?? 'FPB');
            $familia = PrecargaProveedorProrrateoMultiCcSupport::familiaDesdeAbreviatura($tipo);
            $tipoItem = PrecargaProveedorTipoItemSupport::resolver($itemsOc, '');
            $centrosConPeso = PrecargaProveedorProrrateoMultiCcSupport::centrosConPesoParaOc($numeroOc, $itemsOc);
            $prorrateo = app(PrecargaProveedorProrrateoMultiCcSupport::class)
                ->resolverDesdeCentros($familia, $tipoItem, $centrosConPeso);

            return [
                'centros' => array_map('strval', $prorrateo['centros'] ?? []),
                'tipos_origen' => array_map('strval', $prorrateo['tipos_origen'] ?? []),
                'pesos_por_fino' => $prorrateo['pesos_por_fino'] ?? [],
            ];
        } catch (Throwable) {
            return $vacio;
        }
    }

    private function precarga(int $entityId): Precarga_Comprobante_Proveedor
    {
        return Precarga_Comprobante_Proveedor::query()
            ->with([
                'empresas',
                'proveedores',
                'tipotransaccion_compras',
                'precarga_comprobante_proveedor_conceptos.concepto_ivacompras',
            ])
            ->findOrNew($entityId);
    }
}
