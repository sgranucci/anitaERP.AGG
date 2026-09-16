<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoHandlerInterface;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Services\Compras\OrdencompraService;
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
