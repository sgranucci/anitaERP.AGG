<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoHandlerInterface;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Support\Compras\ComprobanteProveedorCotizacionIngresoSupport;
use App\Support\Navegacion\ModoConsultaUrlSupport;

/**
 * Aviso cuando la precarga de factura llegó con cotización de ME ilegible o ajena al día.
 * entityId = precarga_comprobante_proveedor.id
 */
class ComprasPrecargaCotizacionInvalidaAvisoHandler implements ModuloAvisoHandlerInterface
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

        return [
            'empresa' => (string) (optional($precarga?->empresas)->nombre ?? '—'),
            'proveedor' => (string) (optional($precarga?->proveedores)->nombre ?? '—'),
            'comprobante' => $comprobante,
            'fecha' => $precarga?->fechafactura ? $precarga->fechafactura->format('d/m/Y') : '—',
            'moneda' => (string) ($precarga->moneda ?? optional($precarga?->monedas)->nombre ?? '—'),
            'cotizacion_grabada' => number_format((float) ($precarga->cotizacion ?? 0), 4, ',', '.'),
            'marca_error' => ComprobanteProveedorCotizacionIngresoSupport::etiquetaMarca(
                (string) ($precarga->marca_error ?? '')
            ) ?: (string) ($precarga->marca_error ?? '—'),
            'aviso' => (string) ($precarga->aviso_error ?? '—'),
            'total' => number_format((float) ($precarga->total ?? 0), 2, ',', '.'),
            'oc' => (string) ($precarga->numeroordencompra ?? '—'),
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

    private function precarga(int $entityId): Precarga_Comprobante_Proveedor
    {
        return Precarga_Comprobante_Proveedor::query()
            ->with(['empresas', 'proveedores', 'tipotransaccion_compras', 'monedas'])
            ->findOrNew($entityId);
    }
}
