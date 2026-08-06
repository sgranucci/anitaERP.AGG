<?php

namespace App\Support\Stock;

use App\Models\Stock\CertificadoSenasaSurmar;
use App\Models\Stock\CertificadoSenasaSurmarArticulo;
use App\Models\Ventas\Camion;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Transporte;
use RuntimeException;

/**
 * Arma el payload SOAP generarRemito a partir del certificado Surmar (port remito_electronico.fc / a-certsan.c).
 */
final class CertificadoSenasaSurmarRemitoPayloadBuilder
{
    /**
     * @return array{id_req: int, cuit_representada: int, remito: array<string, mixed>}
     */
    public function build(CertificadoSenasaSurmar $cert): array
    {
        $defaults = config('arca_wsremcarne.defaults', []);
        $cuitTitular = $this->soloDigitos(
            $cert->cuit_titular ?: (string) config('arca_wsremcarne.cuit_titular_default', '30505150372')
        );
        if (strlen($cuitTitular) !== 11) {
            throw new RuntimeException('CUIT titular inválido para remito cárnico.');
        }

        $cliente = $cert->cliente_id
            ? Cliente::query()->find($cert->cliente_id)
            : null;
        $cuitReceptor = $this->soloDigitos(
            $cert->cuit_receptor ?: (string) ($cliente->numerodocumento ?? $cuitTitular)
        );
        if (strlen($cuitReceptor) !== 11) {
            $cuitReceptor = $cuitTitular;
        }

        $camion = $cert->camion_id ? Camion::query()->find($cert->camion_id) : null;
        $transporte = $cert->transporte_id ? Transporte::query()->find($cert->transporte_id) : null;

        $cuitExpreso = $this->soloDigitos(
            $cert->cuit_transportista
                ?: (string) ($transporte->nroinscripcion ?? '')
        );
        $cuitConductor = $this->soloDigitos(
            $cert->cuit_conductor
                ?: (string) ($camion->cuit_chofer ?? $transporte->cuit_chofer ?? '')
        );
        if (strlen($cuitExpreso) !== 11) {
            throw new RuntimeException('CUIT transportista/expreso inválido o vacío.');
        }
        if (strlen($cuitConductor) !== 11) {
            throw new RuntimeException('CUIT conductor inválido o vacío (camión/transporte).');
        }

        $dominio = trim((string) ($cert->dominio_vehiculo ?: ($camion->dominio ?? '')));
        if ($dominio === '') {
            throw new RuntimeException('Dominio del vehículo vacío.');
        }
        $acoplado = trim((string) ($cert->dominio_acoplado ?: ($camion->dominio_acoplado ?? '')));

        $puntoEmision = (int) ($cert->punto_emision
            ?: config('arca_wsremcarne.defaults.punto_emision', 1));
        $idReq = (int) ($cert->id_req ?: 0);
        if ($idReq <= 0) {
            throw new RuntimeException('id_req de remito no calculado.');
        }

        $codDomDestino = (int) ($cert->cod_dom_destino ?? 0);
        if ($codDomDestino === 0 && $cliente) {
            $codigoCli = preg_replace('/\D+/', '', (string) ($cliente->codigo ?? '')) ?? '';
            $especial = preg_replace(
                '/\D+/',
                '',
                (string) ($defaults['cliente_domicilio_especial'] ?? '000004')
            ) ?? '';
            if ($codigoCli !== '' && $especial !== '' && (int) $codigoCli === (int) $especial) {
                $codDomDestino = 1;
            }
        }

        $fechaViaje = optional($cert->fecha)->format('Y-m-d') ?: now()->toDateString();

        $mercaderias = [];
        $orden = 0;
        /** @var CertificadoSenasaSurmarArticulo $linea */
        foreach ($cert->articulos as $linea) {
            $kilos = (float) $linea->kilos;
            if ($kilos <= 0) {
                continue;
            }
            $grupo = (int) ($linea->grupocarne ?? 0);
            $tipo = (int) ($linea->tipocarne ?? 0);
            if ($grupo <= 0 && $tipo <= 0) {
                throw new RuntimeException(
                    'Artículo '.$linea->sku.' sin grupocarne/tipocarne para AFIP (codTipoProd).'
                );
            }
            $orden++;
            $item = [
                'orden' => $orden,
                'codTipoProd' => $grupo.'.'.$tipo,
                'kilos' => round($kilos, 2),
                'unidadMedida' => 'kilo',
            ];
            if ((int) ($linea->tropa ?? 0) > 0) {
                $item['tropa'] = (string) (int) $linea->tropa;
            }
            $mercaderias[] = $item;
        }

        if ($mercaderias === []) {
            throw new RuntimeException('No hay mercadería con kilos > 0 para el remito.');
        }

        $arrayMercaderias = count($mercaderias) === 1
            ? ['mercaderia' => $mercaderias[0]]
            : ['mercaderia' => $mercaderias];

        $remito = [
            'tipoMovimiento' => (string) ($cert->tipo_movimiento
                ?: ($defaults['tipo_movimiento'] ?? 'ENV')),
            'tipoComprobante' => (int) ($defaults['tipo_comprobante'] ?? 995),
            'categoriaEmisor' => (int) ($cert->categoria_emisor
                ?: ($defaults['categoria_emisor'] ?? 3)),
            'puntoEmision' => $puntoEmision,
            'cuitTitularMercaderia' => (float) $cuitTitular,
            'tipoReceptor' => (string) ($cert->tipo_receptor
                ?: ($defaults['tipo_receptor'] ?? 'MI')),
            'categoriaReceptor' => (int) ($cert->categoria_receptor
                ?: ($defaults['categoria_receptor'] ?? 2)),
            'cuitReceptor' => (float) $cuitReceptor,
            'codDomDestino' => $codDomDestino,
            'viaje' => [
                'cuitTransportista' => (float) $cuitExpreso,
                'cuitConductor' => (float) $cuitConductor,
                'fechaInicioViaje' => $fechaViaje,
                'distanciaKm' => (float) ($cert->distancia_km
                    ?: ($defaults['distancia_km'] ?? 1)),
                'vehiculo' => array_filter([
                    'dominioVehiculo' => $dominio,
                    'dominioAcoplado' => $acoplado !== '' ? $acoplado : null,
                ], fn ($v) => $v !== null && $v !== ''),
            ],
            'arrayMercaderias' => $arrayMercaderias,
        ];

        $depositario = $this->soloDigitos((string) ($cert->cuit_depositario ?? ''));
        if (strlen($depositario) === 11) {
            $remito['cuitDepositario'] = (float) $depositario;
        }
        $codDomOrigen = (int) ($cert->cod_dom_origen ?? 0);
        if ($codDomOrigen > 0) {
            $remito['codDomOrigen'] = $codDomOrigen;
        }

        return [
            'id_req' => $idReq,
            'cuit_representada' => (int) $cuitTitular,
            'remito' => $remito,
        ];
    }

    private function soloDigitos(string $valor): string
    {
        return preg_replace('/\D+/', '', $valor) ?? '';
    }
}
