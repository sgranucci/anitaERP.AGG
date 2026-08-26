<?php

namespace App\Services\Compras;

use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Compras\ComprobanteProveedorCotizacionIngresoSupport;

/**
 * Aplica la cotización de ingreso a la precarga y avisa a compras / CxC si hay marca.
 */
class PrecargaComprobanteCotizacionIngresoService
{
    public const AVISO_MODULO = 'compras';

    public const AVISO_CODIGO = 'precarga_cotizacion_invalida';

    public function __construct(
        private ModuloAvisoService $moduloAvisoService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function aplicarAPayload(array $data): array
    {
        $fecha = (string) ($data['fechafactura'] ?? $data['fecha_factura'] ?? '');
        $resultado = ComprobanteProveedorCotizacionIngresoSupport::resolverParaFecha(
            (int) ($data['moneda_id'] ?? 1),
            $data['cotizacion'] ?? 0,
            $fecha !== '' ? $fecha : null,
        );

        $data['cotizacion'] = $resultado['cotizacion'];
        if ($resultado['marca_error'] !== null) {
            $data['pararevisar'] = 1;
            $data['marca_error'] = $resultado['marca_error'];
            $data['aviso_error'] = $resultado['aviso'];
        }

        return $data;
    }

    public function notificarSiMarca(?Precarga_Comprobante_Proveedor $precarga): void
    {
        if ($precarga === null || (int) $precarga->id <= 0) {
            return;
        }
        if (! filled($precarga->marca_error)) {
            return;
        }

        $this->moduloAvisoService->enviar(
            self::AVISO_MODULO,
            self::AVISO_CODIGO,
            (int) $precarga->id
        );
    }
}
