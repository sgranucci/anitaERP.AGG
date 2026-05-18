<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Configuracion\Tipodocumento;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\DescuentoGastronomia;
use App\Models\Ventas\Cliente;
use InvalidArgumentException;

/**
 * Resuelve receptor de factura gastronomía según tope RG4444 (manual ARCA WSFE).
 *
 * Bajo tope: DocTipo 99, DocNro 0, nombre CONSUMIDOR FINAL (sin maestro 000000).
 * Sobre tope: cliente del maestro (cliente_id) o datos manuales nombre + documento (+ domicilio opcional).
 */
final class GastronomiaReceptorFacturacionService
{
    public const MODO_CONSUMIDOR_FINAL = 'consumidor_final';

    public const MODO_MAESTRO = 'maestro';

    public const MODO_MANUAL = 'manual';

    public function estimarSubtotalFactura(CuentaGastronomia $cuenta): float
    {
        $cuenta->loadMissing(['lineas', 'descuentoGastronomia']);

        $articuloIds = [];
        $cantidades = [];
        $precios = [];
        foreach ($cuenta->lineas as $linea) {
            $pct = (float) $linea->descuento_linea_pct;
            $articuloIds[] = (int) $linea->articulo_id;
            $cantidades[] = (float) $linea->cantidad;
            $precios[] = (float) $linea->precio_unitario * (1 - $pct / 100);
        }

        $contexto = app(GastronomiaFacturacionService::class)->evaluarDescuentoCabecera(
            $cuenta,
            $articuloIds,
            $cantidades,
            $precios,
        );

        if ($contexto['factura_cortesia_total']) {
            return GastronomiaFacturacionService::IMPORTE_MINIMO_FACTURA;
        }

        $sub = $contexto['subtotal_lineas'];
        $d = $cuenta->descuentoGastronomia;
        if ($d instanceof DescuentoGastronomia) {
            if ($d->tipovalor === DescuentoGastronomia::TIPO_PORCENTAJE) {
                $sub *= (1 - (float) $d->valor / 100);
            } elseif ($d->tipovalor === DescuentoGastronomia::TIPO_IMPORTE) {
                $sub -= (float) $d->valor;
            }
        }

        return max(0., round($sub, 2));
    }

    /**
     * @return array{
     *   cliente_id:int,
     *   modo:string,
     *   arca_receptor?:array{tipodoc:int|string,numerodocumento:string,nombre:string,domicilio?:string},
     *   venta_receptor?:array{nombre:string,numerodocumento:string,domicilio?:string}
     * }
     */
    /**
     * Cliente del maestro elegido para facturar (no el de descuento interno ni el cliente contable CF).
     */
    public function clienteIdMaestroExplicito(CuentaGastronomia $cuenta): ?int
    {
        $id = (int) ($cuenta->cliente_id ?? 0);
        if ($id <= 0) {
            return null;
        }

        try {
            if ($id === $this->resolverClienteContableInternoId()) {
                return null;
            }
        } catch (InvalidArgumentException) {
            return $id;
        }

        return $id;
    }

    public function facturaComoConsumidorFinal(CuentaGastronomia $cuenta): bool
    {
        return $this->clienteIdMaestroExplicito($cuenta) === null;
    }

    public function nombreConsumidorFinalFactura(): string
    {
        $nombre = trim((string) config('arca_wsfe.receptor.consumidor_final_razon_social', 'CONSUMIDOR FINAL'));
        $nombre = trim($nombre, "'\"");

        return $nombre !== '' ? $nombre : 'CONSUMIDOR FINAL';
    }

    /**
     * Datos de receptor para grabar en venta.nombre / venta.numerodocumento (sin cliente maestro en la cuenta).
     *
     * @return array{nombre:string,numerodocumento:string,domicilio:string}
     */
    public function datosVentaReceptorConsumidorFinal(): array
    {
        return [
            'nombre' => $this->nombreConsumidorFinalFactura(),
            'numerodocumento' => (string) config('arca_wsfe.receptor.consumidor_final_numero_documento', '0'),
            'domicilio' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $receptor
     */
    public function aplicarReceptorAlPayloadFacturacion(array &$payload, array $receptor): void
    {
        if (! empty($receptor['arca_receptor']) && is_array($receptor['arca_receptor'])) {
            $payload['arca_receptor'] = $receptor['arca_receptor'];
        }

        if (! empty($receptor['venta_receptor']) && is_array($receptor['venta_receptor'])) {
            $payload['venta_receptor'] = $receptor['venta_receptor'];

            return;
        }

        $modo = (string) ($receptor['modo'] ?? '');
        if ($modo === self::MODO_CONSUMIDOR_FINAL) {
            $payload['venta_receptor'] = $this->datosVentaReceptorConsumidorFinal();
        } elseif ($modo === self::MODO_MANUAL && ! empty($receptor['arca_receptor'])) {
            $ar = $receptor['arca_receptor'];
            $payload['venta_receptor'] = [
                'nombre' => (string) ($ar['nombre'] ?? ''),
                'numerodocumento' => (string) ($ar['numerodocumento'] ?? ''),
                'domicilio' => (string) ($ar['domicilio'] ?? ''),
            ];
        }
    }

    public function resolverParaFacturar(CuentaGastronomia $cuenta, ?float $subtotal = null): array
    {
        $cuenta->loadMissing(['cliente.tipodocumentos', 'lineas', 'descuentoGastronomia']);
        $total = $subtotal ?? $this->estimarSubtotalFactura($cuenta);
        $tope = (float) config('arca_wsfe.receptor.consumidor_final_umbral_monto', 0);

        $clienteMaestroId = $this->clienteIdMaestroExplicito($cuenta);
        if ($clienteMaestroId) {
            return $this->resolverModoMaestro($cuenta, $clienteMaestroId, $total, $tope);
        }

        if ($tope > 0 && $total >= $tope) {
            return $this->resolverModoManualObligatorio($cuenta, $total, $tope);
        }

        return $this->resolverModoConsumidorFinal($total, $tope);
    }

    /**
     * @return array{cliente_id:int,modo:string,arca_receptor:array,venta_receptor:array}
     */
    private function resolverModoConsumidorFinal(float $total, float $tope): array
    {
        $ventaReceptor = $this->datosVentaReceptorConsumidorFinal();
        $tipodoc = (int) config('arca_wsfe.receptor.consumidor_final_tipo_documento', 99);

        $receptor = [
            'tipodoc' => $tipodoc,
            'numerodocumento' => $ventaReceptor['numerodocumento'],
            'nombre' => $ventaReceptor['nombre'],
            'domicilio' => $ventaReceptor['domicilio'],
        ];

        return [
            'cliente_id' => $this->resolverClienteContableInternoId(),
            'modo' => self::MODO_CONSUMIDOR_FINAL,
            'arca_receptor' => $receptor,
            'venta_receptor' => $ventaReceptor,
            'subtotal_estimado' => $total,
            'tope_consumidor_final' => $tope,
        ];
    }

    /**
     * @return array{cliente_id:int,modo:string}
     */
    private function resolverModoMaestro(CuentaGastronomia $cuenta, int $clienteMaestroId, float $total, float $tope): array
    {
        $cliente = Cliente::query()->with('tipodocumentos')->find($clienteMaestroId);
        if (! $cliente) {
            throw new InvalidArgumentException('El cliente asignado a la cuenta no existe en el maestro.');
        }

        $doc = trim((string) ($cliente->numerodocumento ?? ''));
        $tipodocExt = (int) ($cliente->tipodocumentos->codigoexterno ?? 0);

        if ($tope > 0 && $total >= $tope) {
            if ($doc === '' || $doc === '0') {
                throw new InvalidArgumentException(
                    'El total supera $'.number_format($tope, 2, ',', '.')
                    .': el cliente del maestro debe tener documento válido (ARCA: DocTipo distinto de 99 y DocNro > 0).'
                );
            }
            if ($tipodocExt === 99) {
                throw new InvalidArgumentException(
                    'El total supera $'.number_format($tope, 2, ',', '.')
                    .': no puede facturarse a Consumidor Final (tipo doc. 99). Indique otro cliente o datos de receptor.'
                );
            }
        }

        return [
            'cliente_id' => (int) $cliente->id,
            'modo' => self::MODO_MAESTRO,
            'subtotal_estimado' => $total,
            'tope_consumidor_final' => $tope,
        ];
    }

    /**
     * @return array{cliente_id:int,modo:string,arca_receptor:array,venta_receptor:array}
     */
    private function resolverModoManualObligatorio(CuentaGastronomia $cuenta, float $total, float $tope): array
    {
        $nombre = trim((string) ($cuenta->factura_receptor_nombre ?? ''));
        $documento = preg_replace('/\D/', '', (string) ($cuenta->factura_receptor_documento ?? ''));
        $domicilio = trim((string) ($cuenta->factura_receptor_domicilio ?? ''));

        if ($nombre === '' || $documento === '' || (int) $documento <= 0) {
            throw new InvalidArgumentException(
                'El total ($'.number_format($total, 2, ',', '.').') supera el tope de Consumidor Final ($'
                .number_format($tope, 2, ',', '.').'): indique un cliente del maestro o complete nombre y documento para facturar.'
            );
        }

        $tipodoc = $this->resolverTipodocArcaManual($cuenta, $documento);

        if ($tipodoc === 99) {
            throw new InvalidArgumentException(
                'Para montos sobre el tope no puede usarse tipo de documento 99 (Consumidor Final). Use DNI, CUIT u otro tipo válido.'
            );
        }

        $receptor = [
            'tipodoc' => $tipodoc,
            'numerodocumento' => $documento,
            'nombre' => $nombre,
            'domicilio' => $domicilio,
        ];

        return [
            'cliente_id' => $this->resolverClienteContableInternoId(),
            'modo' => self::MODO_MANUAL,
            'arca_receptor' => $receptor,
            'venta_receptor' => [
                'nombre' => $nombre,
                'numerodocumento' => $documento,
                'domicilio' => $domicilio,
            ],
            'subtotal_estimado' => $total,
            'tope_consumidor_final' => $tope,
        ];
    }

    private function resolverTipodocArcaManual(CuentaGastronomia $cuenta, string $documentoSoloDigitos): int
    {
        if ($cuenta->factura_receptor_tipodocumento_id) {
            $td = Tipodocumento::query()->find((int) $cuenta->factura_receptor_tipodocumento_id);
            if ($td && $td->codigoexterno !== null && $td->codigoexterno !== '') {
                return (int) $td->codigoexterno;
            }
        }

        $defecto = (int) config('arca_wsfe.receptor.identificado_tipo_documento_default', 96);
        if (strlen($documentoSoloDigitos) === 11) {
            return 80;
        }

        return $defecto;
    }

    public function resolverClienteContableInternoId(): int
    {
        $id = (int) config('arca_wsfe.receptor.cliente_erp_interno_id', 0);
        if ($id > 0) {
            if (! Cliente::query()->where('id', $id)->exists()) {
                throw new InvalidArgumentException(
                    'ARCA_WSFE_RECEPTOR_CLIENTE_ERP_INTERNO_ID='.$id.' no existe en el maestro de clientes.'
                );
            }

            return $id;
        }

        $condicionId = (int) config('arca_wsfe.receptor.consumidor_final_condicion_iva_id', 3);
        $cliente = Cliente::query()
            ->where('condicioniva_id', $condicionId)
            ->orderBy('id')
            ->first();

        if (! $cliente) {
            throw new InvalidArgumentException(
                'Configure ARCA_WSFE_RECEPTOR_CLIENTE_ERP_INTERNO_ID con un cliente interno para contabilidad/IVA (WSFE).'
            );
        }

        return (int) $cliente->id;
    }
}
