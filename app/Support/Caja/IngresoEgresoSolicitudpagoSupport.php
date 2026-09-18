<?php

namespace App\Support\Caja;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Cuentacaja;
use App\Models\Caja\Tipotransaccion_Caja;
use App\Models\Contable\Cuentacontable;
use App\Models\Solicitudpago\Solicitudpago;
use App\Support\Compras\ProveedorAnticipoCuentaContableSupport;
use App\Support\Contable\CuentacajaCuentacontableResolverSupport;
use App\Support\Numerico\NumeroDecimalLocalSupport;
use App\Support\Solicitudpago\SolicitudpagoEstados;
use App\Support\Solicitudpago\SolicitudpagoTratamientos;
use InvalidArgumentException;

/**
 * Pago IE vinculado a solicitud de pago: monto fijo, tipo OPP (o OPA si ANTICIPADA)
 * y asiento desde cuentas SP o anticipo a proveedores.
 */
class IngresoEgresoSolicitudpagoSupport
{
    public static function montoPendiente(?Solicitudpago $sp): float
    {
        if ($sp === null) {
            return 0.0;
        }

        return round(abs((float) $sp->monto), 2);
    }

    public static function esPagoOpa(?Solicitudpago $sp): bool
    {
        return $sp !== null && SolicitudpagoTratamientos::esAnticipada($sp->tratamiento ?? null);
    }

    /**
     * Tipo de transacción para pagos desde SP (OPP, o OPA si la SP es ANTICIPADA).
     */
    public static function tipotransaccionCajaIdPorConfig(?Solicitudpago $sp = null): int
    {
        if (self::esPagoOpa($sp)) {
            $tipoId = (int) config('caja.ingresoegreso_sp_anticipo_tipotransaccion_id', 0);
            if ($tipoId > 0) {
                return $tipoId;
            }

            return self::tipotransaccionCajaIdPorAbreviatura(self::abreviaturaTipoPago($sp));
        }

        $tipoId = (int) config('caja.ingresoegreso_sp_tipotransaccion_id', 0);
        if ($tipoId > 0) {
            return $tipoId;
        }

        return self::tipotransaccionCajaIdPorAbreviatura(self::abreviaturaTipoPago($sp));
    }

    public static function abreviaturaTipoPago(?Solicitudpago $sp = null): string
    {
        if (self::esPagoOpa($sp)) {
            $abrev = strtoupper(trim((string) config(
                'caja.ingresoegreso_sp_anticipo_tipotransaccion_abreviatura',
                'OPA'
            )));

            return $abrev !== '' ? $abrev : 'OPA';
        }

        $abrev = strtoupper(trim((string) config(
            'caja.ingresoegreso_sp_tipotransaccion_abreviatura',
            'OPP'
        )));

        return $abrev !== '' ? $abrev : 'OPP';
    }

    /**
     * Descripción de movimiento al estilo Anita: detalle de la SP (no "Pago SP N").
     * Truncada a 255 (asiento_movimiento.observacion / caja_movimiento.detalle).
     */
    public static function descripcionMovimientoDesdeSp(?object $sp, int $maxLen = 255): string
    {
        if ($sp === null) {
            return '';
        }

        $detalle = trim((string) ($sp->detalle ?? ''));
        if ($detalle !== '') {
            return self::truncarDescripcion($detalle, $maxLen);
        }

        $codigo = trim((string) ($sp->codigo ?? ''));
        if ($codigo !== '') {
            return self::truncarDescripcion('Pago SP '.$codigo, $maxLen);
        }

        $id = (int) ($sp->id ?? 0);

        return $id > 0 ? self::truncarDescripcion('Pago SP '.$id, $maxLen) : '';
    }

    /**
     * True si la descripción es el placeholder genérico del circuito SP→IE.
     */
    public static function esDescripcionGenericaPagoSp(string $descripcion): bool
    {
        $txt = trim($descripcion);
        if ($txt === '') {
            return true;
        }

        // "Pago SP 11317" o "Pago SP 11317 — …" (prefijo genérico del ERP).
        return preg_match('/^Pago SP\s+\d+/i', $txt) === 1;
    }

    private static function truncarDescripcion(string $texto, int $maxLen): string
    {
        $texto = trim($texto);
        if ($maxLen <= 0 || mb_strlen($texto) <= $maxLen) {
            return $texto;
        }

        return mb_substr($texto, 0, $maxLen);
    }

    private static function tipotransaccionCajaIdPorAbreviatura(string $abrev): int
    {
        if ($abrev === '') {
            return 0;
        }

        $tipo = Tipotransaccion_Caja::query()
            ->whereRaw('UPPER(TRIM(abreviatura)) = ?', [$abrev])
            ->first();

        return $tipo ? (int) $tipo->id : 0;
    }

    /** Resuelve tipo OPP/OPA (u otra abreviatura) por código Anita. */
    public static function tipotransaccionCajaIdPorAbreviaturaPublica(string $abrev): int
    {
        return self::tipotransaccionCajaIdPorAbreviatura(strtoupper(trim($abrev)));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function solicitudpagoIdDesdeData(array $data): int
    {
        $raw = $data['solicitudpago_id'] ?? 0;
        $spId = (int) $raw;
        if ($spId <= 0 && is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw);
            $spId = (int) ($decoded ?? 0);
        }

        return $spId;
    }

    /**
     * Pago desde SP: el comprobante Anita (pago/tesmov/ctamov) cierra OPP,
     * o OPA si la solicitud es ANTICIPADA.
     *
     * @param  array<string, mixed>  $data
     */
    public static function aplicarPagoDesdeSolicitud(array &$data): void
    {
        $spId = self::solicitudpagoIdDesdeData($data);
        if ($spId <= 0) {
            return;
        }

        $data['solicitudpago_id'] = $spId;
        $sp = Solicitudpago::query()->find($spId);

        $tipoId = self::tipotransaccionCajaIdPorConfig($sp);
        $abrev = self::abreviaturaTipoPago($sp);
        if ($tipoId <= 0) {
            throw new InvalidArgumentException(
                'No hay tipo de transacción '.$abrev
                .' configurado para pagar solicitudes de pago.'
            );
        }
        $data['tipotransaccion_caja_id'] = $tipoId;

        $proveedorId = (int) ($data['proveedor_id'] ?? 0);
        if ($proveedorId <= 0 && $sp && (int) ($sp->proveedor_id ?? 0) > 0) {
            $proveedorId = (int) $sp->proveedor_id;
            $data['proveedor_id'] = $proveedorId;
        }

        if (self::esPagoOpa($sp) && $proveedorId <= 0) {
            throw new InvalidArgumentException(
                'La solicitud anticipada debe tener proveedor para generar la OPA y el crédito en cuenta corriente.'
            );
        }
    }

    /**
     * Asiento TES on-the-fly desde las imputaciones de la SP.
     * Si hay cuentas de caja del pago, la pierna caja/banco (111xxx) se toma
     * de la cuenta financiera, no de la plantilla del concepto/SP.
     *
     * $signoOperacion: mismo criterio que IE sin SP (+1 ingreso / −1 egreso-OPP).
     * Los montos de caja en pantalla suelen venir en valor absoluto; se firman acá.
     *
     * @param  list<object>  $datosCaja
     * @return list<array<string, mixed>>
     */
    public static function lineasAsientoDesdeSolicitud(
        Solicitudpago $sp,
        int $monedaId,
        float|int|string $cotizacion,
        array $datosCaja = [],
        int $empresaId = 0,
        int $signoOperacion = -1
    ): array {
        $sp->loadMissing(['cuentas.cuentacontables']);

        $lineas = [];
        foreach ($sp->cuentas as $cta) {
            $cuentaId = (int) ($cta->cuentacontable_id ?? 0);
            $monto = round(abs((float) ($cta->monto ?? 0)), 2);
            if ($cuentaId <= 0 || $monto < 0.01) {
                continue;
            }

            $cuenta = $cta->cuentacontables;
            if ($cuenta === null) {
                continue;
            }

            $dh = strtoupper((string) ($cta->debe_haber ?? 'D')) === 'H' ? 'H' : 'D';
            $lineas[] = [
                'cuentacontable_id' => $cuentaId,
                'codigo' => $cuenta->codigo,
                'nombre' => $cuenta->nombre,
                'moneda_id' => $monedaId > 0 ? $monedaId : (int) ($sp->moneda_id ?? 0),
                'cotizacion' => self::cotizacionParaMoneda($monedaId > 0 ? $monedaId : (int) ($sp->moneda_id ?? 0), $cotizacion),
                'centrocosto_id' => (int) ($cta->centrocosto_id ?? 0),
                'debe' => $dh === 'D' ? $monto : '',
                'haber' => $dh === 'H' ? $monto : '',
                'observacion' => '',
                'carga_cuentacontable_manual' => 'N',
            ];
        }

        $empresaAsiento = $empresaId > 0 ? $empresaId : (int) ($sp->empresa_id ?? 0);
        $lineasCaja = self::lineasDesdeCuentacaja(
            $datosCaja,
            $empresaAsiento,
            $monedaId,
            $cotizacion,
            $signoOperacion,
            self::centrocostoPiernaFinanciera($lineas)
        );

        if (self::esPagoOpa($sp)) {
            return self::lineasAsientoOpa($sp, $empresaAsiento, $monedaId, $cotizacion, $lineas, $lineasCaja);
        }

        return self::reemplazarPiernaFinanciera($lineas, $lineasCaja);
    }

    /**
     * La cuenta financiera del IE pisa el banco/caja del asiento de la SP.
     * Conserva gasto/retenciones (no 111xxx) y agrega la pierna de las cuentas de caja.
     *
     * @param  list<array<string, mixed>>  $lineasAsiento
     * @param  list<array<string, mixed>>  $lineasCaja
     * @return list<array<string, mixed>>
     */
    public static function reemplazarPiernaFinanciera(array $lineasAsiento, array $lineasCaja): array
    {
        if ($lineasCaja === []) {
            return $lineasAsiento;
        }

        $sinBanco = array_values(array_filter(
            $lineasAsiento,
            static fn (array $linea) => ! self::esCodigoCajaBanco((string) ($linea['codigo'] ?? ''))
        ));
        $sinBanco = self::ajustarLineasNoBancoAlCaja($sinBanco, $lineasCaja);

        return array_merge($sinBanco, $lineasCaja);
    }

    /**
     * Al grabar IE desde SP: reemplaza 111xxx del asiento POST por la cuenta
     * contable de las cuentacaja seleccionadas (OP 125043: 127 vs banco de la SP).
     *
     * @param  array<string, mixed>  $data
     */
    public static function pisarPiernaFinancieraEnDataAsiento(array &$data): void
    {
        $spId = self::solicitudpagoIdDesdeData($data);
        if ($spId <= 0) {
            return;
        }

        $cuentaIds = array_values((array) ($data['cuentacontable_ids'] ?? []));
        if ($cuentaIds === []) {
            return;
        }

        $datosCaja = self::datosCajaDesdeData($data);
        $empresaId = (int) ($data['empresa_id'] ?? 0);
        $signo = self::signoOperacionDesdeData($data);
        $monedaCotiz = self::monedaYCotizacionDesdeDatosCaja($datosCaja);
        $lineas = self::lineasAsientoDesdeData($data);
        $cc = self::centrocostoPiernaFinanciera($lineas);
        $lineasCaja = self::lineasDesdeCuentacaja(
            $datosCaja,
            $empresaId,
            $monedaCotiz['moneda_id'],
            $monedaCotiz['cotizacion'],
            $signo,
            $cc
        );
        if ($lineasCaja === []) {
            return;
        }

        self::escribirLineasAsientoEnData($data, self::reemplazarPiernaFinanciera($lineas, $lineasCaja));
    }

    /**
     * Preview: si el asiento ya venía armado (datoscontables), igual pisa 111xxx
     * con las cuentas de caja actuales.
     *
     * @param  list<array<string, mixed>>  $lineasAsiento
     * @param  list<object>  $datosCaja
     * @return list<array<string, mixed>>
     */
    public static function aplicarPiernaFinancieraALineas(
        array $lineasAsiento,
        array $datosCaja,
        int $empresaId,
        int $monedaId,
        float|int|string $cotizacion,
        int $signoOperacion = -1
    ): array {
        if ($lineasAsiento === [] || $datosCaja === []) {
            return $lineasAsiento;
        }

        $lineasCaja = self::lineasDesdeCuentacaja(
            $datosCaja,
            $empresaId,
            $monedaId,
            $cotizacion,
            $signoOperacion,
            self::centrocostoPiernaFinanciera($lineasAsiento)
        );
        if ($lineasCaja === []) {
            return $lineasAsiento;
        }

        return self::reemplazarPiernaFinanciera($lineasAsiento, $lineasCaja);
    }

    /**
     * OPA: Debe anticipo a proveedores + Haber caja (no usa el gasto de la SP).
     *
     * @param  list<array<string, mixed>>  $lineasSolicitud
     * @param  list<array<string, mixed>>  $lineasCaja
     * @return list<array<string, mixed>>
     */
    private static function lineasAsientoOpa(
        Solicitudpago $sp,
        int $empresaId,
        int $monedaId,
        float|int|string $cotizacion,
        array $lineasSolicitud,
        array $lineasCaja
    ): array {
        $cuentaId = ProveedorAnticipoCuentaContableSupport::cuentaAnticipoId($empresaId);
        if ($cuentaId === null || $cuentaId <= 0) {
            throw new InvalidArgumentException(
                'La solicitud anticipada requiere la cuenta automática de anticipos a proveedores '
                .'(pago.anticipo_proveedor) configurada para la empresa.'
            );
        }

        $cuenta = Cuentacontable::query()->find($cuentaId, ['id', 'codigo', 'nombre']);
        if ($cuenta === null) {
            throw new InvalidArgumentException(
                'No se encontró la cuenta contable de anticipos a proveedores configurada para la empresa.'
            );
        }

        $monto = 0.0;
        $monedaLinea = $monedaId > 0 ? $monedaId : (int) ($sp->moneda_id ?? 1);
        $cotizLinea = self::cotizacionParaMoneda($monedaLinea, $cotizacion);
        foreach ($lineasCaja as $linea) {
            $monto += (float) ($linea['haber'] ?: 0);
            $monto += (float) ($linea['debe'] ?: 0);
            if ((int) ($linea['moneda_id'] ?? 0) > 0) {
                $monedaLinea = (int) $linea['moneda_id'];
                $cotizLinea = $linea['cotizacion'] ?? $cotizLinea;
            }
        }
        if ($monto < 0.01) {
            $monto = self::montoPendiente($sp);
        }
        $monto = round($monto, 2);

        $anticipo = [
            'cuentacontable_id' => $cuentaId,
            'codigo' => $cuenta->codigo,
            'nombre' => $cuenta->nombre,
            'moneda_id' => $monedaLinea,
            'cotizacion' => $cotizLinea,
            'centrocosto_id' => self::centrocostoPiernaFinanciera($lineasSolicitud),
            'debe' => $monto,
            'haber' => '',
            'observacion' => 'Anticipo a proveedores',
            'carga_cuentacontable_manual' => 'N',
        ];

        if ($lineasCaja === []) {
            return [$anticipo];
        }

        return array_merge([$anticipo], $lineasCaja);
    }

    /**
     * La pierna de caja es el importe real del pago. Si el asiento de la SP
     * no coincidía con el monto (p. ej. importado de Anita), escala el Debe
     * no-banco para que cierre. Conserva retenciones (Haber no-banco).
     *
     * @param  list<array<string, mixed>>  $sinBanco
     * @param  list<array<string, mixed>>  $lineasCaja
     * @return list<array<string, mixed>>
     */
    public static function ajustarLineasNoBancoAlCaja(array $sinBanco, array $lineasCaja): array
    {
        if ($sinBanco === [] || $lineasCaja === []) {
            return $sinBanco;
        }

        $debeCaja = 0.0;
        $haberCaja = 0.0;
        foreach ($lineasCaja as $linea) {
            $debeCaja += (float) ($linea['debe'] ?: 0);
            $haberCaja += (float) ($linea['haber'] ?: 0);
        }

        $debeNoBanco = 0.0;
        $haberNoBanco = 0.0;
        foreach ($sinBanco as $linea) {
            $debeNoBanco += (float) ($linea['debe'] ?: 0);
            $haberNoBanco += (float) ($linea['haber'] ?: 0);
        }

        $debeTotal = round($debeCaja + $debeNoBanco, 2);
        $haberTotal = round($haberCaja + $haberNoBanco, 2);
        $dif = round($haberTotal - $debeTotal, 2);
        if (abs($dif) < 0.01) {
            return $sinBanco;
        }

        if ($dif > 0 && $debeNoBanco >= 0.01) {
            return self::escalarLadoAsiento($sinBanco, 'debe', $debeNoBanco + $dif);
        }
        if ($dif < 0 && $haberNoBanco >= 0.01) {
            return self::escalarLadoAsiento($sinBanco, 'haber', $haberNoBanco - $dif);
        }
        if ($dif < 0 && $debeNoBanco >= 0.01) {
            return self::escalarLadoAsiento($sinBanco, 'debe', $debeNoBanco + $dif);
        }

        return $sinBanco;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private static function escalarLadoAsiento(array $lineas, string $lado, float $destino): array
    {
        $actual = 0.0;
        $indices = [];
        foreach ($lineas as $i => $linea) {
            $valor = (float) ($linea[$lado] ?: 0);
            if ($valor >= 0.01) {
                $actual += $valor;
                $indices[] = $i;
            }
        }
        if ($actual < 0.01 || $indices === []) {
            return $lineas;
        }

        $destino = round($destino, 2);
        $acum = 0.0;
        $ultimo = $indices[count($indices) - 1];
        $otro = $lado === 'debe' ? 'haber' : 'debe';
        foreach ($indices as $i) {
            if ($i === $ultimo) {
                $nuevo = round($destino - $acum, 2);
            } else {
                $nuevo = round(((float) ($lineas[$i][$lado] ?: 0)) * $destino / $actual, 2);
                $acum += $nuevo;
            }
            $lineas[$i][$lado] = $nuevo;
            $lineas[$i][$otro] = $lineas[$i][$otro] ?: '';
        }

        return $lineas;
    }

    /**
     * @param  list<object>  $datosCaja
     * @return list<array<string, mixed>>
     */
    private static function lineasDesdeCuentacaja(
        array $datosCaja,
        int $empresaId,
        int $monedaId,
        float|int|string $cotizacion,
        int $signoOperacion = -1,
        int $centrocostoId = 0
    ): array {
        $signo = $signoOperacion < 0 ? -1 : 1;
        $lineas = [];
        foreach ($datosCaja as $movimiento) {
            $cajaId = (int) ($movimiento->cuentacaja_ids ?? $movimiento->cuentacaja_id ?? 0);
            $importeOriginal = (float) ($movimiento->montos ?? $movimiento->monto ?? 0);
            $importeAbs = abs($importeOriginal);
            if ($cajaId <= 0 || $importeAbs < 0.01) {
                continue;
            }

            $caja = Cuentacaja::query()->with('cuentacontables')->find($cajaId);
            if ($caja === null) {
                continue;
            }

            $cuentaId = (int) (CuentacajaCuentacontableResolverSupport::resolverIdParaEmpresa($caja, $empresaId) ?? 0);
            if ($cuentaId <= 0) {
                $cuentaId = (int) ($caja->cuentacontable_id ?? 0);
            }
            if ($cuentaId <= 0) {
                continue;
            }

            $cuenta = $caja->cuentacontables;
            if ($cuenta === null || (int) $cuenta->id !== $cuentaId) {
                $cuenta = Cuentacontable::query()->find($cuentaId, ['id', 'codigo', 'nombre']);
            }
            if ($cuenta === null) {
                continue;
            }

            $monedaMov = (int) ($movimiento->moneda_ids ?? $monedaId);
            $cotizMov = self::cotizacionParaMoneda(
                $monedaMov > 0 ? $monedaMov : $monedaId,
                $movimiento->cotizaciones ?? $cotizacion
            );
            // Tipo de egreso (OPP/EGR): la pantalla carga el monto en positivo,
            // la pierna financiera siempre va al Haber. TRA/ingresos respetan el signo.
            $importeFirmado = $signo < 0 ? -$importeAbs : $importeOriginal;
            $dh = $importeFirmado < 0 ? 'H' : 'D';
            $monto = round($importeAbs, 2);

            $lineas[] = [
                'cuentacontable_id' => $cuentaId,
                'codigo' => $cuenta->codigo,
                'nombre' => $cuenta->nombre,
                'moneda_id' => $monedaMov > 0 ? $monedaMov : $monedaId,
                'cotizacion' => $cotizMov,
                'centrocosto_id' => $centrocostoId,
                'debe' => $dh === 'D' ? $monto : '',
                'haber' => $dh === 'H' ? $monto : '',
                'observacion' => '',
                'carga_cuentacontable_manual' => 'N',
            ];
        }

        return $lineas;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function signoOperacionDesdeData(array $data): int
    {
        $tipoId = (int) ($data['tipotransaccion_caja_id'] ?? 0);
        if ($tipoId <= 0) {
            return -1;
        }

        $tipo = Tipotransaccion_Caja::query()->find($tipoId);
        if ($tipo && IngresoEgresoTransferenciaSupport::esTransferencia($tipo)) {
            return 1;
        }

        return ($tipo && ($tipo->signo ?? '') === 'I') ? 1 : -1;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<object>
     */
    private static function datosCajaDesdeData(array $data): array
    {
        $ids = array_values((array) ($data['cuentacaja_ids'] ?? []));
        $montos = array_values((array) ($data['montos'] ?? []));
        $monedas = array_values((array) ($data['moneda_ids'] ?? []));
        $cotizaciones = array_values((array) ($data['cotizaciones'] ?? []));
        $out = [];
        foreach ($ids as $i => $id) {
            $cajaId = (int) $id;
            if ($cajaId <= 0) {
                continue;
            }
            $out[] = (object) [
                'cuentacaja_ids' => $cajaId,
                'montos' => NumeroDecimalLocalSupport::aFloat($montos[$i] ?? 0),
                'moneda_ids' => (int) ($monedas[$i] ?? 0),
                'cotizaciones' => $cotizaciones[$i] ?? 1,
            ];
        }

        return $out;
    }

    /**
     * @param  list<object>  $datosCaja
     * @return array{moneda_id: int, cotizacion: float|int|string}
     */
    private static function monedaYCotizacionDesdeDatosCaja(array $datosCaja): array
    {
        foreach ($datosCaja as $movimiento) {
            $monedaMov = (int) ($movimiento->moneda_ids ?? 0);
            if ($monedaMov > 0) {
                return [
                    'moneda_id' => $monedaMov,
                    'cotizacion' => $movimiento->cotizaciones ?? 1,
                ];
            }
        }

        return ['moneda_id' => 1, 'cotizacion' => 1];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private static function lineasAsientoDesdeData(array $data): array
    {
        $cuentaIds = array_values((array) ($data['cuentacontable_ids'] ?? []));
        $codigos = array_values((array) ($data['codigoasientos'] ?? []));
        $nombres = array_values((array) ($data['nombrecuentacontables'] ?? []));
        $ccs = array_values((array) ($data['centrocostoasiento_ids'] ?? []));
        $monedas = array_values((array) ($data['monedaasiento_ids'] ?? []));
        $debes = array_values((array) ($data['debeasientos'] ?? []));
        $haberes = array_values((array) ($data['haberasientos'] ?? []));
        $cotizaciones = array_values((array) ($data['cotizacionasientos'] ?? []));
        $obs = array_values((array) ($data['observacionasientos'] ?? []));
        $manual = array_values((array) ($data['carga_cuentacontable_manuales'] ?? []));

        $lineas = [];
        foreach ($cuentaIds as $i => $cuentaId) {
            $cuentaId = (int) $cuentaId;
            if ($cuentaId <= 0) {
                continue;
            }
            $codigo = trim((string) ($codigos[$i] ?? ''));
            if ($codigo === '') {
                $codigo = (string) (Cuentacontable::query()->whereKey($cuentaId)->value('codigo') ?? '');
            }
            $debe = NumeroDecimalLocalSupport::aFloat($debes[$i] ?? '');
            $haber = NumeroDecimalLocalSupport::aFloat($haberes[$i] ?? '');
            $lineas[] = [
                'cuentacontable_id' => $cuentaId,
                'codigo' => $codigo,
                'nombre' => (string) ($nombres[$i] ?? ''),
                'moneda_id' => (int) ($monedas[$i] ?? 0),
                'cotizacion' => $cotizaciones[$i] ?? 1,
                'centrocosto_id' => (int) ($ccs[$i] ?? 0),
                'debe' => $debe >= 0.01 ? round($debe, 2) : '',
                'haber' => $haber >= 0.01 ? round($haber, 2) : '',
                'observacion' => (string) ($obs[$i] ?? ''),
                'carga_cuentacontable_manual' => (string) ($manual[$i] ?? 'N'),
            ];
        }

        return $lineas;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lineas
     */
    private static function escribirLineasAsientoEnData(array &$data, array $lineas): void
    {
        $data['cuentacontable_ids'] = [];
        $data['cuentacontable_id_previa'] = [];
        $data['codigoasientos'] = [];
        $data['codigo_previo_cuentacontables'] = [];
        $data['nombrecuentacontables'] = [];
        $data['centrocostoasiento_ids'] = [];
        $data['centrocostoasiento_id_previo'] = [];
        $data['monedaasiento_ids'] = [];
        $data['monedaasiento_id_previo'] = [];
        $data['debeasientos'] = [];
        $data['haberasientos'] = [];
        $data['cotizacionasientos'] = [];
        $data['observacionasientos'] = [];
        $data['carga_cuentacontable_manuales'] = [];
        $data['cuenta'] = [];

        foreach ($lineas as $linea) {
            $cuentaId = (int) ($linea['cuentacontable_id'] ?? 0);
            $codigo = (string) ($linea['codigo'] ?? '');
            $monedaId = (int) ($linea['moneda_id'] ?? 0);
            $cc = (int) ($linea['centrocosto_id'] ?? 0);
            $data['cuentacontable_ids'][] = $cuentaId;
            $data['cuentacontable_id_previa'][] = $cuentaId;
            $data['codigoasientos'][] = $codigo;
            $data['codigo_previo_cuentacontables'][] = $codigo;
            $data['nombrecuentacontables'][] = (string) ($linea['nombre'] ?? '');
            $data['centrocostoasiento_ids'][] = $cc;
            $data['centrocostoasiento_id_previo'][] = $cc;
            $data['monedaasiento_ids'][] = $monedaId;
            $data['monedaasiento_id_previo'][] = $monedaId;
            $data['debeasientos'][] = $linea['debe'] ?? '';
            $data['haberasientos'][] = $linea['haber'] ?? '';
            $data['cotizacionasientos'][] = $linea['cotizacion'] ?? 1;
            $data['observacionasientos'][] = $linea['observacion'] ?? '';
            $data['carga_cuentacontable_manuales'][] = $linea['carga_cuentacontable_manual'] ?? 'N';
            $data['cuenta'][] = 1;
        }
    }

    /**
     * Centro de costo a usar en la pierna financiera del pago: el que trae la SP
     * en su cuenta de caja/banco (o cualquiera de sus imputaciones).
     *
     * @param  list<array<string, mixed>>  $lineasSolicitud
     */
    private static function centrocostoPiernaFinanciera(array $lineasSolicitud): int
    {
        $fallback = 0;
        foreach ($lineasSolicitud as $linea) {
            $cc = (int) ($linea['centrocosto_id'] ?? 0);
            if ($cc <= 0) {
                continue;
            }
            if (self::esCodigoCajaBanco((string) ($linea['codigo'] ?? ''))) {
                return $cc;
            }
            if ($fallback <= 0) {
                $fallback = $cc;
            }
        }

        return $fallback;
    }

    /**
     * Moneda local: cotización 1. Evita que leercotizacion (que para id=1 devuelve USD)
     * deje 1515 en la pierna en pesos y rompa controles posteriores.
     */
    private static function cotizacionParaMoneda(int $monedaId, float|int|string|null $cotizacion): float|int|string
    {
        if ($monedaId <= 1) {
            return 1;
        }
        if ($cotizacion === '' || $cotizacion === null) {
            return 1;
        }

        return $cotizacion;
    }

    public static function esCodigoCajaBanco(string $codigo): bool
    {
        $n = (int) preg_replace('/\D/', '', $codigo);

        return $n >= 111000000 && $n < 112000000;
    }

    /**
     * Réplica simple del total de operación de la pantalla (sin conversión FX).
     *
     * @param  array<string, mixed>  $data
     */
    public static function totalOperacionDesdeRequest(array $data): float
    {
        $debe = 0.0;
        $haber = 0.0;

        foreach ($data['montos'] ?? [] as $monto) {
            $valor = (float) $monto;
            if ($valor >= 0) {
                $debe += $valor;
            } else {
                $haber += abs($valor);
            }
        }

        foreach ($data['montocheque_emitidos'] ?? [] as $monto) {
            $valor = abs((float) $monto);
            if ($valor > 0) {
                $haber += $valor;
            }
        }

        foreach ($data['montocheque_recibidos'] ?? [] as $monto) {
            $valor = abs((float) $monto);
            if ($valor > 0) {
                $debe += $valor;
            }
        }

        return round(max($debe, $haber), 2);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function assertMontoCoincideConSolicitud(array $data): void
    {
        $spId = (int) ($data['solicitudpago_id'] ?? 0);
        if ($spId <= 0) {
            return;
        }

        $sp = Solicitudpago::query()->find($spId);
        if ($sp === null) {
            throw new InvalidArgumentException('No se encontró la solicitud de pago vinculada.');
        }

        $esperado = self::montoPendiente($sp);
        if ($esperado < 0.01) {
            throw new InvalidArgumentException('La solicitud de pago no tiene monto pendiente a pagar.');
        }

        $actual = self::totalOperacionDesdeRequest($data);
        if (abs($actual - $esperado) > 0.02) {
            throw new InvalidArgumentException(
                'El total del pago ('.number_format($actual, 2, ',', '.').') debe ser exactamente '
                .'el monto pendiente de la solicitud ('.number_format($esperado, 2, ',', '.').').'
            );
        }
    }

    /**
     * Impide una segunda OP sobre una SP ya pagada o con IE vigente.
     *
     * @param  array<string, mixed>  $data
     */
    public static function assertSolicitudDisponibleParaPagar(array $data, bool $conLock = false): void
    {
        $spId = self::solicitudpagoIdDesdeData($data);
        if ($spId <= 0) {
            return;
        }

        $query = Solicitudpago::query();
        if ($conLock) {
            $query->lockForUpdate();
        }
        $sp = $query->find($spId);
        if ($sp === null) {
            throw new InvalidArgumentException('No se encontró la solicitud de pago vinculada.');
        }

        if (strtoupper(trim((string) $sp->estado)) === SolicitudpagoEstados::PAGADA) {
            throw new InvalidArgumentException(
                'La solicitud de pago #'.$sp->codigo.' ya está PAGADA. No se puede generar otra OP.'
            );
        }

        $existe = Caja_Movimiento::query()
            ->where('solicitudpago_id', $spId)
            ->whereNull('caja_movimiento_origen_id')
            ->whereNull('caja_movimiento_revertido_por_id')
            ->exists();
        if ($existe) {
            throw new InvalidArgumentException(
                'La solicitud de pago #'.$sp->codigo.' ya tiene una OP vigente. No se puede generar otra.'
            );
        }
    }
}
