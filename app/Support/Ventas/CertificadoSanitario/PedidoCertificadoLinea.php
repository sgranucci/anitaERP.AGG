<?php

namespace App\Support\Ventas\CertificadoSanitario;

/**
 * Línea normalizada (ERP o Anita) para armar el certificado WEB.
 */
final class PedidoCertificadoLinea
{
    public function __construct(
        public readonly string $codigoPedido,
        public readonly string $origen, // erp|anita
        public readonly string $codigoCliente,
        public readonly ?int $clienteId,
        public readonly ?int $transporteId,
        public readonly ?string $codigoTransporte,
        public readonly ?int $zonavtaId,
        public readonly ?int $codigoZona,
        public readonly string $sku,
        public readonly ?int $articuloId,
        public readonly float $kilos,
        public readonly float $cajas,
        public readonly float $piezas,
        public readonly ?int $codigosenasaId,
        public readonly string $llevafrio,
        public readonly string $registroSenasa,
        public readonly string $prefijoSenasa,
        public readonly ?int $envasesenasaId,
        public readonly string $envaseNombre,
        public readonly string $marca,
        public readonly int $vencimientoEnDias,
        public readonly float $pesoAprox,
        public readonly ?int $localidadSenasaCodigo,
        public readonly string $clienteNombre,
        public readonly string $clienteDireccion,
        public readonly string $clienteCp,
        public readonly string $clienteTelefono,
        public readonly string $localidadNombre,
        public readonly string $provinciaNombre,
    ) {
    }

    public function claveAgrupacion(bool $abrePorLocalidad): string
    {
        $transporte = (string) ($this->codigoTransporte ?? $this->transporteId ?? '0');
        if ($abrePorLocalidad) {
            return $transporte.'|'.(string) ($this->codigoZona ?? $this->zonavtaId ?? '0');
        }

        return $transporte;
    }
}
