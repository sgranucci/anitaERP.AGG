<?php

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Provincia;
use App\Models\Ventas\Cliente;
use InvalidArgumentException;

/**
 * Receptor eventual del POS Local: datos solo en la venta (venta_receptor / arca_receptor).
 * No crea ni actualiza el maestro de clientes. Aislado de AGG.
 */
final class FacturacionLocalReceptorSupport
{
    public const MODO_MAESTRO = 'maestro';

    public const MODO_MANUAL_B = 'manual_b';

    public const MODO_MANUAL_A = 'manual_a';

    public const MODO_CF = 'cf';

    /**
     * @param  array<string,mixed>  $input
     * @return array{
     *   modo:string,
     *   cliente_id:int,
     *   letra:string,
     *   tiene_datos_cliente:bool,
     *   provincia_id:?int,
     *   localidad_id:?int,
     *   omitir_percepciones:bool,
     *   arca_receptor:array{tipodoc:int,numerodocumento:string,nombre:string,domicilio:string},
     *   venta_receptor:array<string,mixed>
     * }
     */
    public static function resolver(array $input): array
    {
        $manual = ! empty($input['receptor_manual']) && is_array($input['receptor_manual'])
            ? $input['receptor_manual']
            : [];
        $modoManual = strtolower(trim((string) ($manual['modo'] ?? '')));

        if ($modoManual === 'a' || $modoManual === self::MODO_MANUAL_A) {
            return self::manualFacturaA($manual);
        }
        if ($modoManual === 'b' || $modoManual === self::MODO_MANUAL_B) {
            return self::manualFacturaB($manual);
        }

        $clienteId = (int) ($input['cliente_id'] ?? 0);
        if ($clienteId > 0) {
            return self::desdeMaestro($clienteId, is_array($input['receptor'] ?? null) ? $input['receptor'] : []);
        }

        return self::consumidorFinal();
    }

    /**
     * @param  array<string,mixed>  $manual
     * @return array<string,mixed>
     */
    private static function manualFacturaB(array $manual): array
    {
        $nombre = trim((string) ($manual['nombre'] ?? ''));
        $documento = preg_replace('/\D/', '', (string) ($manual['documento'] ?? $manual['numerodocumento'] ?? '')) ?? '';
        $domicilio = trim((string) ($manual['domicilio'] ?? ''));

        if ($nombre === '') {
            throw new InvalidArgumentException('Indicá el nombre del receptor eventual.');
        }
        if ($documento === '' || (int) $documento <= 0) {
            throw new InvalidArgumentException('Indicá el DNI del receptor eventual.');
        }
        if (strlen($documento) === 11) {
            throw new InvalidArgumentException('Para CUIT usá Factura A (receptor eventual).');
        }

        $tipodoc = (int) config('arca_wsfe.receptor.identificado_tipo_documento_default', 96);
        $clienteId = self::clienteContadoId();

        return [
            'modo' => self::MODO_MANUAL_B,
            'cliente_id' => $clienteId,
            'letra' => 'B',
            'tiene_datos_cliente' => true,
            'provincia_id' => null,
            'localidad_id' => null,
            'omitir_percepciones' => true,
            'arca_receptor' => [
                'tipodoc' => $tipodoc,
                'numerodocumento' => $documento,
                'nombre' => $nombre,
                'domicilio' => $domicilio,
            ],
            'venta_receptor' => [
                'nombre' => $nombre,
                'numerodocumento' => $documento,
                'domicilio' => $domicilio,
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $manual
     * @return array<string,mixed>
     */
    private static function manualFacturaA(array $manual): array
    {
        $nombre = trim((string) ($manual['nombre'] ?? ''));
        $cuit = preg_replace('/\D/', '', (string) ($manual['cuit'] ?? $manual['documento'] ?? $manual['numerodocumento'] ?? '')) ?? '';
        $domicilio = trim((string) ($manual['domicilio'] ?? ''));
        $localidadId = (int) ($manual['localidad_id'] ?? 0);
        $provinciaId = (int) ($manual['provincia_id'] ?? 0);

        if ($nombre === '') {
            throw new InvalidArgumentException('Indicá la razón social del receptor (Factura A).');
        }
        if (strlen($cuit) !== 11 || (int) $cuit <= 0) {
            throw new InvalidArgumentException('Indicá un CUIT válido de 11 dígitos (Factura A).');
        }
        if ($domicilio === '') {
            throw new InvalidArgumentException('Indicá el domicilio del receptor (Factura A).');
        }
        if ($localidadId <= 0 || ! Localidad::query()->whereKey($localidadId)->exists()) {
            throw new InvalidArgumentException('Indicá la localidad del receptor (Factura A).');
        }
        if ($provinciaId <= 0 || ! Provincia::query()->whereKey($provinciaId)->exists()) {
            throw new InvalidArgumentException('Indicá la provincia del receptor (Factura A).');
        }

        $clienteId = self::clienteRiId();

        return [
            'modo' => self::MODO_MANUAL_A,
            'cliente_id' => $clienteId,
            'letra' => 'A',
            'tiene_datos_cliente' => true,
            'provincia_id' => $provinciaId,
            'localidad_id' => $localidadId,
            'omitir_percepciones' => false,
            'arca_receptor' => [
                'tipodoc' => 80,
                'numerodocumento' => $cuit,
                'nombre' => $nombre,
                'domicilio' => $domicilio,
            ],
            'venta_receptor' => [
                'nombre' => $nombre,
                'numerodocumento' => $cuit,
                'domicilio' => $domicilio,
                'localidad_id' => $localidadId,
                'provincia_id' => $provinciaId,
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $receptor
     * @return array<string,mixed>
     */
    private static function desdeMaestro(int $clienteId, array $receptor): array
    {
        $cliente = Cliente::query()->with(['tipodocumentos', 'condicionivas'])->find($clienteId);
        if (! $cliente) {
            throw new InvalidArgumentException('Cliente inexistente.');
        }

        $doc = preg_replace('/\D/', '', (string) ($cliente->numerodocumento ?? '')) ?? '';
        $tipodocExt = (int) ($cliente->tipodocumentos?->codigoexterno ?? 0);
        $documentoValido = $doc !== '' && (int) $doc > 0 && $tipodocExt > 0 && $tipodocExt !== 99;
        $letra = strtoupper(trim((string) ($cliente->condicionivas?->letra ?? 'B')));
        if ($letra === '') {
            $letra = 'B';
        }

        $nombre = trim((string) ($receptor['nombre'] ?? $cliente->nombre ?? ''));
        $domicilio = trim((string) ($receptor['domicilio'] ?? $cliente->domicilio ?? ''));
        if ($nombre === '') {
            $nombre = trim((string) ($cliente->nombre ?? ''));
        }

        if ($documentoValido) {
            return [
                'modo' => self::MODO_MAESTRO,
                'cliente_id' => $clienteId,
                'letra' => $letra,
                'tiene_datos_cliente' => true,
                'provincia_id' => (int) ($cliente->provincia_id ?: 0) ?: null,
                'localidad_id' => (int) ($cliente->localidad_id ?: 0) ?: null,
                'omitir_percepciones' => $letra === 'B',
                'arca_receptor' => [
                    'tipodoc' => $tipodocExt,
                    'numerodocumento' => $doc,
                    'nombre' => $nombre,
                    'domicilio' => $domicilio,
                ],
                'venta_receptor' => [
                    'nombre' => $nombre,
                    'numerodocumento' => $doc,
                    'domicilio' => $domicilio,
                    'localidad_id' => (int) ($cliente->localidad_id ?: 0) ?: null,
                    'provincia_id' => (int) ($cliente->provincia_id ?: 0) ?: null,
                ],
            ];
        }

        $cf = self::consumidorFinal();
        $cf['cliente_id'] = $clienteId;
        $cf['modo'] = self::MODO_MAESTRO;
        $cf['letra'] = $letra;
        $cf['arca_receptor']['nombre'] = $nombre !== '' ? $nombre : $cf['arca_receptor']['nombre'];
        $cf['venta_receptor']['nombre'] = $cf['arca_receptor']['nombre'];
        $cf['venta_receptor']['domicilio'] = $domicilio;

        return $cf;
    }

    /**
     * @return array<string,mixed>
     */
    private static function consumidorFinal(): array
    {
        $nombre = trim((string) config('arca_wsfe.receptor.consumidor_final_razon_social', 'CONSUMIDOR FINAL'), "'\"");
        if ($nombre === '') {
            $nombre = 'CONSUMIDOR FINAL';
        }
        $doc = (string) config('arca_wsfe.receptor.consumidor_final_numero_documento', '0');

        return [
            'modo' => self::MODO_CF,
            'cliente_id' => self::clienteContadoId(),
            'letra' => 'B',
            'tiene_datos_cliente' => false,
            'provincia_id' => null,
            'localidad_id' => null,
            'omitir_percepciones' => true,
            'arca_receptor' => [
                'tipodoc' => (int) config('arca_wsfe.receptor.consumidor_final_tipo_documento', 99),
                'numerodocumento' => $doc,
                'nombre' => $nombre,
                'domicilio' => '',
            ],
            'venta_receptor' => [
                'nombre' => $nombre,
                'numerodocumento' => $doc,
                'domicilio' => '',
            ],
        ];
    }

    public static function clienteContadoId(): int
    {
        $id = (int) config('facturacion_local.cliente_contado_id', 0);
        if ($id > 0 && Cliente::query()->whereKey($id)->exists()) {
            return $id;
        }

        $condicionId = (int) config('arca_wsfe.receptor.consumidor_final_condicion_iva_id', 3);
        $porNombre = Cliente::query()
            ->where('condicioniva_id', $condicionId)
            ->where('nombre', 'like', '%CONSUMIDOR FINAL%')
            ->orderBy('id')
            ->first();
        if ($porNombre) {
            return (int) $porNombre->id;
        }

        $cliente = Cliente::query()
            ->where('condicioniva_id', $condicionId)
            ->orderBy('id')
            ->first();
        if ($cliente) {
            return (int) $cliente->id;
        }

        throw new InvalidArgumentException(
            'Configure FACTURACION_LOCAL_CLIENTE_CONTADO_ID (cliente Consumidor Final del ERP).'
        );
    }

    public static function clienteRiId(): int
    {
        $id = (int) config('facturacion_local.cliente_ri_id', 0);
        if ($id > 0 && Cliente::query()->whereKey($id)->exists()) {
            return $id;
        }

        $cliente = Cliente::query()
            ->whereHas('condicionivas', static function ($q) {
                $q->where('letra', 'A');
            })
            ->orderBy('id')
            ->first();
        if ($cliente) {
            return (int) $cliente->id;
        }

        throw new InvalidArgumentException(
            'Configure FACTURACION_LOCAL_CLIENTE_RI_ID (cliente RI interno para Factura A eventual).'
        );
    }
}
