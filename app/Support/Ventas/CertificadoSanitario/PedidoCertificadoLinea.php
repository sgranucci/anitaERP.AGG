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
        public readonly string $articuloNombre,
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
        /** Amparo SENASA de terceros (certart.certa_cert_terc / se:certificadoDeOrigen). */
        public readonly string $certificadoOrigen = '',
    ) {
    }

    public function conCertificadoOrigen(string $certificadoOrigen): self
    {
        return new self(
            codigoPedido: $this->codigoPedido,
            origen: $this->origen,
            codigoCliente: $this->codigoCliente,
            clienteId: $this->clienteId,
            transporteId: $this->transporteId,
            codigoTransporte: $this->codigoTransporte,
            zonavtaId: $this->zonavtaId,
            codigoZona: $this->codigoZona,
            sku: $this->sku,
            articuloNombre: $this->articuloNombre,
            articuloId: $this->articuloId,
            kilos: $this->kilos,
            cajas: $this->cajas,
            piezas: $this->piezas,
            codigosenasaId: $this->codigosenasaId,
            llevafrio: $this->llevafrio,
            registroSenasa: $this->registroSenasa,
            prefijoSenasa: $this->prefijoSenasa,
            envasesenasaId: $this->envasesenasaId,
            envaseNombre: $this->envaseNombre,
            marca: $this->marca,
            vencimientoEnDias: $this->vencimientoEnDias,
            pesoAprox: $this->pesoAprox,
            localidadSenasaCodigo: $this->localidadSenasaCodigo,
            clienteNombre: $this->clienteNombre,
            clienteDireccion: $this->clienteDireccion,
            clienteCp: $this->clienteCp,
            clienteTelefono: $this->clienteTelefono,
            localidadNombre: $this->localidadNombre,
            provinciaNombre: $this->provinciaNombre,
            certificadoOrigen: trim($certificadoOrigen),
        );
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
