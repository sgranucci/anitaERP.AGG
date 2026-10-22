<?php

namespace App\Support\Solicitudpago;

use App\Models\Compras\Pagoproveedor_Retencion;
use App\Models\Compras\Proveedor;
use App\Models\Contable\Cuentacontable;
use App\Models\Solicitudpago\Solicitudpago;
use App\Models\Solicitudpago\Solicitudpago_Retencion;
use App\Services\Compras\RetencionesPagoCalculator;
use App\Services\Compras\RetencionesPagoContextoBuilder;
use App\Support\Compras\PagoproveedorAsientoArmadoSupport;
use App\Support\Compras\Retencion\RetencionesPagoResultado;
use App\Support\Contable\AsientoCentrocostoObligatorioSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use InvalidArgumentException;

/**
 * Retenciones opcionales de una solicitud anticipada.
 *
 * El neto gravado se informa por monto o se despeja del monto de la SP con la tasa de IVA.
 * El resultado queda guardado en la SP. La OPA de ingresos y egresos lo toma tal cual:
 * caja = monto de la solicitud − retenciones; el anticipo al Debe es el monto completo.
 */
final class SolicitudpagoRetencionCalculoSupport
{
    public const MODO_MONTO = 'monto';

    public const MODO_TASA_IVA = 'tasa_iva';

    /**
     * @param  array<string, mixed>  $data
     */
    public static function sincronizar(Solicitudpago $sp, array $data): void
    {
        $activa = ! empty($data['retencion_activa'])
            && SolicitudpagoTratamientos::esAnticipada($sp->tratamiento ?? null);

        if (! $activa) {
            self::limpiar($sp);

            return;
        }

        $presentacion = self::calcularDesdePedido(
            (int) ($sp->proveedor_id ?? 0),
            (int) ($sp->empresa_id ?? 0),
            $sp->fecha?->format('Y-m-d'),
            (float) ($sp->monto ?? 0),
            (string) ($data['retencion_modo'] ?? ''),
            isset($data['retencion_neto_gravado']) ? (float) $data['retencion_neto_gravado'] : null,
            isset($data['retencion_tasa_iva']) ? (float) $data['retencion_tasa_iva'] : null,
            self::codigosPedidos($data),
        );

        self::guardarPresentacion($sp, $presentacion);
    }

    /**
     * @return array{
     *     modo: string,
     *     bases: array{neto_gravado: float, importe_iva: float, tasa_iva: ?float},
     *     filas: list<array<string, mixed>>,
     *     lineas: list<array<string, mixed>>,
     *     total: float,
     *     bruto: float,
     *     a_transferir: float
     * }
     */
    public static function calcularDesdePedido(
        int $proveedorId,
        int $empresaId,
        ?string $fecha,
        float $montoSp,
        string $modo,
        ?float $neto,
        ?float $tasa,
        array $codigos = [],
    ): array {
        $montoSp = round($montoSp, 2);
        if ($montoSp < 0.01) {
            throw new InvalidArgumentException('La solicitud no tiene monto para calcular retenciones.');
        }
        if ($proveedorId <= 0) {
            throw new InvalidArgumentException(
                'Para calcular retenciones la solicitud anticipada tiene que tener proveedor.'
            );
        }

        $bases = self::bases($montoSp, $modo, $neto, $tasa);
        $proveedor = Proveedor::query()->with(['condicionivas', 'condicionIIBBs'])->find($proveedorId);
        if ($proveedor === null) {
            throw new InvalidArgumentException('No se encontró el proveedor de la solicitud.');
        }

        $ctx = app(RetencionesPagoContextoBuilder::class)->armarInput(
            proveedor: $proveedor,
            aplicaciones: [],
            fecha: $fecha,
            empresaId: $empresaId > 0 ? $empresaId : null,
            importeNetoFallback: $bases['neto_gravado'],
            importeIvaFallback: $bases['importe_iva'],
            iibbSobreNetoInformado: true,
            overrides: self::overridesCodigos($codigos),
        );
        $resultado = app(RetencionesPagoCalculator::class)->calcular($ctx['input']);
        $filas = self::filasDesdeResultado($resultado);
        $total = round(array_sum(array_map(static fn (array $fila) => (float) $fila['importe'], $filas)), 2);
        if ($montoSp - $total < 0.01 && $total > 0) {
            throw new InvalidArgumentException(
                'Las retenciones (' . number_format($total, 2, ',', '.')
                . ') cubren el monto de la solicitud. Revise el neto gravado.'
            );
        }

        return [
            'modo' => $modo === self::MODO_TASA_IVA ? self::MODO_TASA_IVA : self::MODO_MONTO,
            'bases' => $bases,
            'filas' => $filas,
            'lineas' => self::lineasUi($resultado),
            'total' => $total,
            'bruto' => $montoSp,
            'a_transferir' => round($montoSp - $total, 2),
        ];
    }

    /**
     * Códigos confirmados en la solicitud. Vacío significa el del proveedor.
     *
     * @param  array<string, mixed>  $data
     * @return array{retencionganancia_id: ?int, retencioniva_id: ?int, retencionsuss_id: ?int}
     */
    public static function codigosPedidos(array $data): array
    {
        $codigos = [];
        foreach (['retencionganancia_id', 'retencioniva_id', 'retencionsuss_id'] as $campo) {
            $id = (int) ($data[$campo] ?? 0);
            $codigos[$campo] = $id > 0 ? $id : null;
        }

        return $codigos;
    }

    /**
     * @param  array<string, ?int>  $codigos
     * @return array<string, int>
     */
    private static function overridesCodigos(array $codigos): array
    {
        $overrides = [];
        foreach (['retencionganancia_id', 'retencioniva_id', 'retencionsuss_id'] as $campo) {
            $id = (int) ($codigos[$campo] ?? 0);
            if ($id > 0) {
                $overrides[$campo] = $id;
            }
        }

        return $overrides;
    }

    /**
     * Neto gravado e IVA según el modo elegido en la solicitud.
     *
     * Por monto: el neto es el importe informado. Si además hay tasa, el IVA es neto × tasa.
     * Por tasa de IVA: el monto de la solicitud incluye IVA. Neto = monto / (1 + tasa/100).
     *
     * @return array{neto_gravado: float, importe_iva: float, tasa_iva: ?float}
     */
    public static function bases(float $montoSp, string $modo, ?float $neto, ?float $tasa): array
    {
        $montoSp = round($montoSp, 2);
        $tasa = $tasa !== null ? round($tasa, 4) : null;
        if ($tasa !== null && $tasa < 0) {
            throw new InvalidArgumentException('La tasa de IVA no puede ser negativa.');
        }

        if ($modo === self::MODO_TASA_IVA) {
            if ($tasa === null) {
                throw new InvalidArgumentException('Elija la tasa de IVA.');
            }
            if ($montoSp < 0.01) {
                throw new InvalidArgumentException('La solicitud no tiene monto para calcular el neto gravado.');
            }
            $netoCalc = round($montoSp / (1 + ($tasa / 100)), 2);
            $iva = round($montoSp - $netoCalc, 2);

            return [
                'neto_gravado' => $netoCalc,
                'importe_iva' => $iva,
                'tasa_iva' => $tasa,
            ];
        }

        $neto = round((float) $neto, 2);
        if ($neto < 0.01) {
            throw new InvalidArgumentException('Indique el neto gravado de las retenciones.');
        }
        $iva = 0.0;
        if ($tasa !== null && $tasa > 0) {
            $iva = round($neto * $tasa / 100, 2);
        }

        return [
            'neto_gravado' => $neto,
            'importe_iva' => $iva,
            'tasa_iva' => $tasa,
        ];
    }

    public static function totalImportes(?Solicitudpago $sp): float
    {
        if ($sp === null || ! $sp->retencion_activa) {
            return 0.0;
        }
        if (! SolicitudpagoTratamientos::esAnticipada($sp->tratamiento ?? null)) {
            return 0.0;
        }
        $sp->loadMissing('retenciones');

        return round((float) $sp->retenciones->sum('importe'), 2);
    }

    /**
     * Haber de cada retención precalculada, con la cuenta automática del pago.
     *
     * @return list<array<string, mixed>>
     */
    public static function lineasHaberAsiento(
        Solicitudpago $sp,
        int $empresaId,
        int $monedaId,
        float|int|string $cotizacion,
    ): array {
        if (self::totalImportes($sp) < 0.01 || $empresaId <= 0) {
            return [];
        }

        $sp->loadMissing('retenciones');
        $lineas = [];
        foreach ($sp->retenciones as $ret) {
            $importe = round((float) ($ret->importe ?? 0), 2);
            if ($importe < 0.01) {
                continue;
            }
            $tipo = (string) $ret->tiporetencion;
            $cuentaId = PagoproveedorAsientoArmadoSupport::cuentaIdRetencion(
                $tipo,
                $empresaId,
                (int) ($ret->provincia_id ?? 0) ?: null,
            );
            if ($cuentaId <= 0) {
                throw new InvalidArgumentException(
                    'No hay cuenta contable para la retención ' . $ret->etiquetaTipo()
                    . '. Configure Contable → Cuentas automáticas (pago.retencion_ganancias / iva / suss / iibb).'
                );
            }
            $cuenta = Cuentacontable::query()->find($cuentaId, ['id', 'codigo', 'nombre', 'manejaccosto']);
            if ($cuenta === null) {
                throw new InvalidArgumentException(
                    'No se encontró la cuenta contable de la retención ' . $ret->etiquetaTipo() . '.'
                );
            }
            $cc = 0;
            if (AsientoCentrocostoObligatorioSupport::maneja($cuenta->manejaccosto ?? null)) {
                $cc = (int) ($sp->centrocosto_id ?? 0);
            }
            $lineas[] = [
                'cuentacontable_id' => (int) $cuenta->id,
                'codigo' => $cuenta->codigo,
                'nombre' => $cuenta->nombre,
                'moneda_id' => $monedaId > 0 ? $monedaId : (int) ($sp->moneda_id ?? 1),
                'cotizacion' => $cotizacion,
                'centrocosto_id' => $cc,
                'debe' => '',
                'haber' => $importe,
                'observacion' => 'Retención ' . $ret->etiquetaTipo(),
                'carga_cuentacontable_manual' => 'N',
            ];
        }

        return $lineas;
    }

    public static function motivoTexto(string $motivo): string
    {
        return match ($motivo) {
            'ok', 'ok_manual' => 'Corresponde',
            'no_retiene' => 'No retiene',
            'sin_regimen' => 'Sin régimen',
            'excluido' => 'Excluido',
            'bajo_minimo_no_sujeto' => 'Bajo el mínimo no sujeto',
            'bajo_minimo_retencion' => 'Bajo el mínimo de retención',
            'bajo_minimo_imponible' => 'Bajo el mínimo imponible',
            'bajo_base_periodo' => 'Bajo la base del período',
            'no_agente' => 'La empresa no es agente',
            'sin_tasa' => 'Sin tasa',
            'sin_base_factura' => 'Sin base',
            'no_sujeto_pasible' => 'No es sujeto pasible',
            'manual_requerido' => 'Requiere importe manual',
            default => $motivo !== '' ? $motivo : '—',
        };
    }

    /**
     * @param  array<string, mixed>  $presentacion
     */
    private static function guardarPresentacion(Solicitudpago $sp, array $presentacion): void
    {
        $bases = $presentacion['bases'];
        $sp->update([
            'retencion_activa' => true,
            'retencion_modo' => $presentacion['modo'],
            'retencion_neto_gravado' => $bases['neto_gravado'],
            'retencion_tasa_iva' => $bases['tasa_iva'],
            'retencion_importe_iva' => $bases['importe_iva'],
            'retencion_calculada_en' => now(),
        ]);

        EloquentAuditDeleteSupport::each(
            Solicitudpago_Retencion::query()->where('solicitudpago_id', $sp->id)
        );
        foreach ($presentacion['filas'] as $fila) {
            Solicitudpago_Retencion::query()->create(array_merge($fila, [
                'solicitudpago_id' => $sp->id,
            ]));
        }
        $sp->unsetRelation('retenciones');
    }

    private static function limpiar(Solicitudpago $sp): void
    {
        if ($sp->retencion_activa
            || $sp->retencion_neto_gravado !== null
            || $sp->retencion_modo !== null
        ) {
            $sp->update([
                'retencion_activa' => false,
                'retencion_modo' => null,
                'retencion_neto_gravado' => null,
                'retencion_tasa_iva' => null,
                'retencion_importe_iva' => null,
                'retencion_calculada_en' => null,
            ]);
        }
        if (Solicitudpago_Retencion::query()->where('solicitudpago_id', $sp->id)->exists()) {
            EloquentAuditDeleteSupport::each(
                Solicitudpago_Retencion::query()->where('solicitudpago_id', $sp->id)
            );
        }
        $sp->unsetRelation('retenciones');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function filasDesdeResultado(RetencionesPagoResultado $resultado): array
    {
        $filas = [];
        $ganancias = $resultado->ganancias;
        if ($ganancias->aplica && $ganancias->importeRetencion > 0) {
            $integrantes = $ganancias->detalle['integrantes'] ?? null;
            if (is_array($integrantes) && $integrantes !== []) {
                foreach ($integrantes as $linea) {
                    if (! is_array($linea)) {
                        continue;
                    }
                    $importe = round((float) ($linea['importe'] ?? 0), 2);
                    if ($importe < 0.01) {
                        continue;
                    }
                    $filas[] = self::fila(
                        Pagoproveedor_Retencion::TIPO_GANANCIAS,
                        $importe,
                        (float) ($linea['base_retenible'] ?? $linea['base_calculo'] ?? 0),
                        (float) ($linea['alicuota'] ?? $ganancias->alicuotaAplicada),
                        (string) ($linea['motivo'] ?? $ganancias->motivo),
                        $linea,
                        [
                            'retencionganancia_id' => self::idONull($linea['regimen_id'] ?? ($ganancias->detalle['regimen_id'] ?? null)),
                            'codigo_regimen' => (string) ($linea['regimen'] ?? ($ganancias->detalle['regimen'] ?? '')),
                            'codigo_retencion' => (string) ($linea['codigo'] ?? ($ganancias->detalle['codigo'] ?? '')),
                        ],
                    );
                }
            } else {
                $filas[] = self::fila(
                    Pagoproveedor_Retencion::TIPO_GANANCIAS,
                    $ganancias->importeRetencion,
                    $ganancias->baseRetenible > 0 ? $ganancias->baseRetenible : $ganancias->baseCalculo,
                    $ganancias->alicuotaAplicada,
                    $ganancias->motivo,
                    $ganancias->detalle,
                    [
                        'retencionganancia_id' => self::idONull($ganancias->detalle['regimen_id'] ?? null),
                        'codigo_regimen' => (string) ($ganancias->detalle['regimen'] ?? ''),
                        'codigo_retencion' => (string) ($ganancias->detalle['codigo'] ?? ''),
                    ],
                );
            }
        }

        $iva = $resultado->iva;
        if ($iva->aplica && $iva->importeRetencion > 0) {
            $filas[] = self::fila(
                Pagoproveedor_Retencion::TIPO_IVA,
                $iva->importeRetencion,
                $iva->baseCalculo,
                $iva->alicuotaAplicada,
                $iva->motivo,
                $iva->detalle,
                [
                    'retencioniva_id' => self::idONull($iva->detalle['regimen_id'] ?? null),
                    'codigo_regimen' => (string) ($iva->detalle['regimen'] ?? ''),
                    'codigo_retencion' => (string) ($iva->detalle['codigo'] ?? ''),
                ],
            );
        }

        $suss = $resultado->suss;
        if ($suss->aplica && $suss->importeRetencion > 0) {
            $filas[] = self::fila(
                Pagoproveedor_Retencion::TIPO_SUSS,
                $suss->importeRetencion,
                $suss->baseCalculo,
                $suss->alicuotaAplicada,
                $suss->motivo,
                $suss->detalle,
                [
                    'retencionsuss_id' => self::idONull($suss->detalle['regimen_id'] ?? null),
                    'codigo_regimen' => (string) ($suss->detalle['regimen'] ?? ''),
                    'codigo_retencion' => (string) ($suss->detalle['codigo'] ?? ''),
                ],
            );
        }

        $iibb = $resultado->iibb;
        if ($iibb->aplica && $iibb->importeRetencion > 0) {
            $porProvincia = $iibb->detalle['por_provincia'] ?? null;
            if (is_array($porProvincia) && $porProvincia !== []) {
                foreach ($porProvincia as $linea) {
                    if (! is_array($linea)) {
                        continue;
                    }
                    $importe = round((float) ($linea['importe'] ?? $linea['retencion'] ?? 0), 2);
                    if ($importe < 0.01) {
                        continue;
                    }
                    $filas[] = self::fila(
                        Pagoproveedor_Retencion::TIPO_IIBB,
                        $importe,
                        (float) ($linea['base'] ?? $linea['base_calculo'] ?? 0),
                        (float) ($linea['alicuota'] ?? $iibb->alicuotaAplicada),
                        (string) ($linea['motivo'] ?? $iibb->motivo),
                        $linea,
                        [
                            'provincia_id' => self::idONull($linea['provincia_id'] ?? null),
                            'codigo_regimen' => (string) ($linea['jurisdiccion'] ?? ''),
                        ],
                    );
                }
            } else {
                $filas[] = self::fila(
                    Pagoproveedor_Retencion::TIPO_IIBB,
                    $iibb->importeRetencion,
                    $iibb->baseCalculo,
                    $iibb->alicuotaAplicada,
                    $iibb->motivo,
                    $iibb->detalle,
                    [
                        'provincia_id' => self::idONull($iibb->detalle['provincia_id'] ?? null),
                        'codigo_regimen' => (string) ($iibb->detalle['jurisdiccion'] ?? ''),
                    ],
                );
            }
        }

        return $filas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function lineasUi(RetencionesPagoResultado $resultado): array
    {
        $mapa = [
            [Pagoproveedor_Retencion::TIPO_GANANCIAS, 'Ganancias', $resultado->ganancias->aplica, $resultado->ganancias->importeRetencion, $resultado->ganancias->alicuotaAplicada, $resultado->ganancias->baseCalculo, $resultado->ganancias->motivo],
            [Pagoproveedor_Retencion::TIPO_IVA, 'IVA', $resultado->iva->aplica, $resultado->iva->importeRetencion, $resultado->iva->alicuotaAplicada, $resultado->iva->baseCalculo, $resultado->iva->motivo],
            [Pagoproveedor_Retencion::TIPO_SUSS, 'SUSS', $resultado->suss->aplica, $resultado->suss->importeRetencion, $resultado->suss->alicuotaAplicada, $resultado->suss->baseCalculo, $resultado->suss->motivo],
            [Pagoproveedor_Retencion::TIPO_IIBB, 'IIBB', $resultado->iibb->aplica, $resultado->iibb->importeRetencion, $resultado->iibb->alicuotaAplicada, $resultado->iibb->baseCalculo, $resultado->iibb->motivo],
        ];
        $lineas = [];
        foreach ($mapa as [$tipo, $etiqueta, $aplica, $importe, $alicuota, $base, $motivo]) {
            $importe = $aplica ? round((float) $importe, 2) : 0.0;
            $lineas[] = [
                'tipo' => $tipo,
                'etiqueta' => $etiqueta,
                'aplica' => $importe >= 0.01,
                'importe' => $importe,
                'alicuota' => round((float) $alicuota, 4),
                'base' => round((float) $base, 2),
                'motivo' => self::motivoTexto((string) $motivo),
            ];
        }

        return $lineas;
    }

    /**
     * @param  array<string, mixed>  $detalle
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function fila(
        string $tipo,
        float $importe,
        float $base,
        float $alicuota,
        string $motivo,
        array $detalle,
        array $extra,
    ): array {
        $fila = array_merge([
            'tiporetencion' => $tipo,
            'retencionganancia_id' => null,
            'retencioniva_id' => null,
            'retencionsuss_id' => null,
            'provincia_id' => null,
            'codigo_regimen' => null,
            'codigo_retencion' => null,
            'base_calculo' => round($base, 2),
            'alicuota' => round($alicuota, 4),
            'importe' => round($importe, 2),
            'detalle_calculo' => $detalle,
            'motivo' => $motivo !== '' ? $motivo : null,
        ], $extra);
        foreach (['codigo_regimen', 'codigo_retencion'] as $campo) {
            if ($fila[$campo] !== null && $fila[$campo] !== '') {
                $fila[$campo] = mb_substr((string) $fila[$campo], 0, 20);
            }
        }
        if ($fila['motivo'] !== null && $fila['motivo'] !== '') {
            $fila['motivo'] = mb_substr((string) $fila['motivo'], 0, 80);
        }

        return $fila;
    }

    private static function idONull(mixed $valor): ?int
    {
        $id = (int) $valor;

        return $id > 0 ? $id : null;
    }
}
