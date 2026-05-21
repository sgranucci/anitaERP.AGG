<?php

namespace App\Support\Caja;

use App\Models\Caja\InterbankingTransferencia;
use App\Repositories\Caja\BancoRepositoryInterface;
use Carbon\Carbon;

/**
 * Datos para el PDF «Comprobante de transferencia electrónica» (formato Interbanking).
 */
final class InterbankingTransferenciaComprobanteSupport
{
    /** @var array<string, string> */
    private const ETIQUETAS_CUENTA = [
        'bank_name' => 'Banco',
        'bankName' => 'Banco',
        'bank_number' => 'Código banco',
        'bankNumber' => 'Código banco',
        'account_label' => 'Denominación',
        'accountLabel' => 'Denominación',
        'denomination' => 'Denominación',
        'name' => 'Denominación',
        'taxpayer_cuit' => 'CUIT',
        'taxpayerCuit' => 'CUIT',
        'customer_cuit' => 'CUIT',
        'customerCuit' => 'CUIT',
        'cuit' => 'CUIT',
        'account_cbu' => 'CBU',
        'accountCbu' => 'CBU',
        'cbu' => 'CBU',
        'account_number' => 'Nº cuenta',
        'accountNumber' => 'Nº cuenta',
        'number' => 'Nº cuenta',
        'account_type' => 'Tipo cuenta',
        'accountType' => 'Tipo cuenta',
    ];

    /** @var array<string, string> */
    private const ETIQUETAS_AFIP = [
        'tax_description' => 'Impuesto',
        'concept_description' => 'Concepto',
        'pago_desc' => 'Descripción pago',
        'vep_number' => 'Nº VEP',
        'control_code' => 'Código control',
        'fiscal_period' => 'Período fiscal',
        'provider_name' => 'Proveedor',
        'provider_code' => 'Código proveedor',
        'nro_formulario' => 'Nº formulario',
        'tax_code' => 'Código impuesto',
        'concept_code' => 'Código concepto',
        'fee_number' => 'Nº cuota',
    ];

    public function __construct(
        private BancoRepositoryInterface $bancoRepository
    ) {}

    /**
     * @return array{banco: string, denominacion: string, cuit: string, cbu: string}
     */
    public function cuentaResumen(mixed $jsonColumn, ?string $legacyEtiqueta, ?string $bankNumberFiltro = null): array
    {
        return $this->resolverCuenta($jsonColumn, $legacyEtiqueta, $bankNumberFiltro);
    }

    public function cbuCuenta(mixed $jsonColumn, ?string $legacyEtiqueta, ?string $bankNumberFiltro = null): string
    {
        return $this->cuentaResumen($jsonColumn, $legacyEtiqueta, $bankNumberFiltro)['cbu'];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function seccionesDetalleModal(InterbankingTransferencia $transferencia): array
    {
        $transferencia->loadMissing('empresa:id,nombre');

        $secciones = [];

        $secciones['Transferencia'] = array_filter([
            'Empresa' => $transferencia->empresa->nombre ?? '',
            'Fecha transferencia' => $transferencia->request_date
                ? $transferencia->request_date->format('d/m/Y H:i')
                : '',
            'Tipo' => (string) ($transferencia->transfer_type_description ?? $transferencia->transfer_type_code ?? ''),
            'Importe' => number_format((float) $transferencia->amount, 2, ',', '.').' '.($transferencia->currency ?? ''),
            'Nº transferencia' => $transferencia->transfer_id !== null ? (string) $transferencia->transfer_id : '',
            'Nº red' => $transferencia->network_number !== null ? (string) $transferencia->network_number : '',
            'Código validación' => (string) ($transferencia->validation_code ?? ''),
        ], fn ($v) => $v !== null && $v !== '');

        $debitoRaw = $this->decodificarCuenta($transferencia->debit_account_json, $transferencia->debit_account);
        if ($debitoRaw !== null) {
            $secciones['Cuenta débito'] = $this->camposLegibles($debitoRaw, self::ETIQUETAS_CUENTA);
        }

        $creditoRaw = $this->decodificarCuenta($transferencia->credit_account_json, $transferencia->credit_account);
        if ($creditoRaw !== null) {
            $secciones['Cuenta crédito'] = $this->camposLegibles($creditoRaw, self::ETIQUETAS_CUENTA);
        }

        if (is_array($transferencia->afip_json) && $transferencia->afip_json !== []) {
            $secciones['Datos AFIP'] = $this->camposLegibles($transferencia->afip_json, self::ETIQUETAS_AFIP);
        }

        return $secciones;
    }

    /**
     * Detalle desde fila JSON de la API (modal saldos en vivo).
     *
     * @param  array<string, mixed>  $filaApi
     * @return array<string, array<string, string>>
     */
    public function seccionesDetalleDesdeApi(array $filaApi): array
    {
        $secciones = [];

        $monto = $filaApi['amount'] ?? 0;
        if (is_array($monto)) {
            $monto = $monto['value'] ?? $monto['amount'] ?? 0;
        }

        $secciones['Transferencia'] = array_filter([
            'Fecha transferencia' => ! empty($filaApi['request_date'])
                ? Carbon::parse($filaApi['request_date'])->format('d/m/Y H:i')
                : '',
            'Tipo' => (string) ($filaApi['transfer_type_description'] ?? $filaApi['transfer_type_code'] ?? ''),
            'Importe' => number_format((float) $monto, 2, ',', '.').' '.($filaApi['currency'] ?? ''),
            'Nº transferencia' => isset($filaApi['transfer_id']) ? (string) $filaApi['transfer_id'] : '',
            'Nº red' => isset($filaApi['network_number']) ? (string) $filaApi['network_number'] : '',
            'Código validación' => (string) ($filaApi['validation_code'] ?? ''),
        ], fn ($v) => $v !== '');

        $debitoRaw = $this->normalizarCuentaApi($filaApi['debit_account'] ?? null);
        if ($debitoRaw !== null) {
            $secciones['Cuenta débito'] = $this->camposLegibles($debitoRaw, self::ETIQUETAS_CUENTA);
        }

        $creditoRaw = $this->normalizarCuentaApi($filaApi['credit_account'] ?? null);
        if ($creditoRaw !== null) {
            $secciones['Cuenta crédito'] = $this->camposLegibles($creditoRaw, self::ETIQUETAS_CUENTA);
        }

        $afip = $filaApi['afip'] ?? null;
        if (is_array($afip) && $afip !== []) {
            $secciones['Datos AFIP'] = $this->camposLegibles($afip, self::ETIQUETAS_AFIP);
        }

        return $secciones;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $etiquetas
     * @return array<string, string>
     */
    private function camposLegibles(array $data, array $etiquetas): array
    {
        $out = [];
        foreach ($data as $clave => $valor) {
            if (! is_scalar($valor) || (string) $valor === '') {
                continue;
            }
            $etiqueta = $etiquetas[$clave] ?? ucfirst(str_replace('_', ' ', (string) $clave));
            $out[$etiqueta] = trim((string) $valor);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizarCuentaApi(mixed $cuenta): ?array
    {
        if ($cuenta === null || $cuenta === '') {
            return null;
        }
        if (is_array($cuenta)) {
            return $cuenta;
        }
        if (is_scalar($cuenta)) {
            return ['account_label' => (string) $cuenta];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function datosDesdeModelo(InterbankingTransferencia $transferencia): array
    {
        $transferencia->loadMissing('empresa:id,nombre');

        $debito = $this->resolverCuenta(
            $transferencia->debit_account_json,
            $transferencia->debit_account,
            $transferencia->debit_bank_number
        );
        $credito = $this->resolverCuenta(
            $transferencia->credit_account_json,
            $transferencia->credit_account,
            null
        );

        [$concepto, $motivo] = $this->resolverConceptoMotivo($transferencia->afip_json);

        $moneda = strtoupper((string) ($transferencia->currency ?: 'ARS'));
        $simbolo = $moneda === 'USD' ? 'U$S' : '$';

        return [
            'titulo' => 'Comprobante de transferencia electrónica',
            'fecha' => $transferencia->request_date
                ? $transferencia->request_date->format('d/m/Y H:i').' hs.'
                : '—',
            'tipo_transferencia' => (string) ($transferencia->transfer_type_description
                ?? $transferencia->transfer_type_code
                ?? '—'),
            'concepto' => $concepto,
            'motivo' => $motivo,
            'nro_transferencia' => $transferencia->transfer_id !== null
                ? (string) $transferencia->transfer_id
                : '—',
            'nro_red' => $transferencia->network_number !== null
                ? (string) $transferencia->network_number
                : '—',
            'importe' => $simbolo.' '.number_format((float) $transferencia->amount, 2, '.', ''),
            'codigo_validacion' => (string) ($transferencia->validation_code ?? ''),
            'credito' => $credito,
            'debito' => $debito,
        ];
    }

    /**
     * @return array{banco: string, denominacion: string, cuit: string, cbu: string}
     */
    private function resolverCuenta(mixed $jsonColumn, ?string $legacyEtiqueta, ?string $bankNumberFiltro): array
    {
        $data = $this->decodificarCuenta($jsonColumn, $legacyEtiqueta);

        $banco = $this->texto($data, ['bank_name', 'bankName']);
        if ($banco === '' && $bankNumberFiltro !== null && $bankNumberFiltro !== '') {
            $banco = $this->resolverNombreBanco($bankNumberFiltro);
        }
        if ($banco === '' && isset($data['bank_number'])) {
            $banco = $this->resolverNombreBanco((string) $data['bank_number']);
        }

        $cbu = $this->texto($data, ['account_cbu', 'accountCbu', 'cbu']);
        if ($banco === '' && strlen($cbu) >= 3) {
            $banco = $this->resolverNombreBanco(substr($cbu, 0, 3));
        }

        return [
            'banco' => $banco !== '' ? $banco : '—',
            'denominacion' => $this->texto($data, ['account_label', 'accountLabel', 'denomination', 'name']) ?: '—',
            'cuit' => $this->texto($data, ['taxpayer_cuit', 'taxpayerCuit', 'customer_cuit', 'customerCuit', 'cuit']) ?: '—',
            'cbu' => $cbu !== '' ? $cbu : '—',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @param  list<string>  $keys
     */
    private function texto(?array $data, array $keys): string
    {
        if ($data === null) {
            return '';
        }
        foreach ($keys as $key) {
            if (! empty($data[$key]) && is_scalar($data[$key])) {
                return trim((string) $data[$key]);
            }
        }

        return '';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodificarCuenta(mixed $jsonColumn, ?string $legacyEtiqueta): ?array
    {
        if (is_array($jsonColumn) && $jsonColumn !== []) {
            return $jsonColumn;
        }

        if (is_string($jsonColumn) && $jsonColumn !== '') {
            $decoded = json_decode($jsonColumn, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $legacy = trim((string) ($legacyEtiqueta ?? ''));
        if ($legacy !== '' && str_starts_with($legacy, '{')) {
            $decoded = json_decode($legacy, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            $parcial = $this->extraerCamposJsonParcial($legacy);
            if ($parcial !== null) {
                return $parcial;
            }
        }

        if ($legacy !== '' && ! str_starts_with($legacy, '{')) {
            return ['account_label' => $legacy];
        }

        return null;
    }

    /**
     * Registros antiguos guardaban JSON truncado en varchar(64); extrae campos conocidos.
     *
     * @return array<string, mixed>|null
     */
    private function extraerCamposJsonParcial(string $texto): ?array
    {
        $out = [];
        $patrones = [
            'account_cbu' => '/"account_cbu"\s*:\s*"([^"]+)"/',
            'account_label' => '/"account_label"\s*:\s*"([^"]+)/',
            'bank_name' => '/"bank_name"\s*:\s*"([^"]+)"/',
            'taxpayer_cuit' => '/"taxpayer_cuit"\s*:\s*"([^"]+)/',
            'customer_cuit' => '/"customer_cuit"\s*:\s*"([^"]+)/',
            'bank_number' => '/"bank_number"\s*:\s*"([^"]+)"/',
        ];

        foreach ($patrones as $clave => $patron) {
            if (preg_match($patron, $texto, $coincidencia) === 1) {
                $out[$clave] = $coincidencia[1];
            }
        }

        return $out === [] ? null : $out;
    }

    /**
     * @param  array<string, mixed>|null  $afip
     * @return array{0: string, 1: string}
     */
    private function resolverConceptoMotivo(?array $afip): array
    {
        if ($afip === null || $afip === []) {
            return ['', ''];
        }

        $concepto = trim((string) ($afip['concept_description'] ?? $afip['pago_desc'] ?? ''));
        $motivo = trim((string) ($afip['tax_description'] ?? $afip['vep_number'] ?? ''));

        return [$concepto, $motivo];
    }

    private function resolverNombreBanco(?string $codigo): string
    {
        if ($codigo === null || $codigo === '') {
            return '';
        }

        $str = (string) $codigo;
        $sinCerosIzq = ltrim($str, '0');
        $sinCerosIzq = $sinCerosIzq === '' ? '0' : $sinCerosIzq;

        $candidatos = array_unique(array_filter([
            $str,
            str_pad($sinCerosIzq, 3, '0', STR_PAD_LEFT),
            str_pad($sinCerosIzq, 4, '0', STR_PAD_LEFT),
        ]));

        foreach ($candidatos as $c) {
            $banco = $this->bancoRepository->findPorCodigo($c);
            if ($banco) {
                return $banco->nombre;
            }
        }

        return '';
    }

    public function nombreArchivoPdf(InterbankingTransferencia $transferencia): string
    {
        $id = $transferencia->transfer_id ?? $transferencia->id;
        $fecha = $transferencia->request_date instanceof Carbon
            ? $transferencia->request_date->format('Ymd')
            : now()->format('Ymd');

        return 'comprobante_transferencia_'.$fecha.'_'.$id.'.pdf';
    }
}
