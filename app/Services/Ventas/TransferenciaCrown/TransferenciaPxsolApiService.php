<?php

declare(strict_types=1);

namespace App\Services\Ventas\TransferenciaCrown;

use App\Models\Ventas\Cliente;
use App\Models\Ventas\Puntoventa;
use App\Support\Ventas\TransferenciaCrown\PxsolApiClient;
use App\Support\Ventas\TransferenciaCrown\PxsolHotelesSupport;
use App\Support\Ventas\TransferenciaCrown\PxsolVoucherNormalizer;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownArcaTipos;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownAsientoBuilder;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownComprobante;
use Carbon\Carbon;
use RuntimeException;

/**
 * Importa facturas de hotel desde la API PXSOL (sin archivo ARCA).
 * Idempotente: si el comprobante ya está, no lo pisa.
 */
final class TransferenciaPxsolApiService
{
    public function __construct(
        private readonly PxsolApiClient $api,
        private readonly TransferenciaCrownAsientoBuilder $asientos,
        private readonly TransferenciaCrownFacturaWriter $writer,
        private readonly TransferenciaCrownAnitaReplicaDesdeErpSupport $replicaAnita,
    ) {
    }

    /**
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   resumen: array<string, mixed>,
     *   advertencias: list<string>,
     *   hotel: string,
     *   hotel_nombre: string,
     *   puntoventa: string,
     *   desde: string,
     *   hasta: string
     * }
     */
    public function previsualizar(
        string $hotelCodigo,
        string $desdeYmd,
        string $hastaYmd,
        string $puntoventaCodigo = '',
        int $usuarioId = 0,
    ): array {
        $this->validarRango($desdeYmd, $hastaYmd);
        $hotel = PxsolHotelesSupport::resolver($hotelCodigo);
        $pvFiltro = $this->resolverPvFiltro($hotel, $puntoventaCodigo);
        $vouchers = $this->api->listarVouchers(
            $hotel['token'],
            $hotel['hotel_id'],
            $desdeYmd,
            $hastaYmd,
            $pvFiltro['fiscal'],
        );

        $mapeo = TransferenciaCrownArcaTipos::resolver($usuarioId);
        $filas = [];
        foreach ($vouchers as $voucher) {
            $filas[] = $this->normalizar(PxsolVoucherNormalizer::aFila($voucher), $mapeo);
        }
        $filas = $this->writer->marcarExistentes($filas);
        // Reimportación: lo que ya está (con o sin asiento) no se toca.
        foreach ($filas as &$fila) {
            if (($fila['estado'] ?? '') === 'enganchar') {
                $fila['estado'] = 'existe';
                $fila['mensaje'] = 'Ya está en el ERP. La reimportación no lo pisa.';
            }
        }
        unset($fila);

        return [
            'filas' => $filas,
            'resumen' => $this->resumen($filas),
            'advertencias' => [
                'Origen: API PXSOL voucher/list (no se sube Excel ni ARCA).',
                'Cuenta corriente: asiento a la cuenta del cliente (cliente.cuentacontable_id, en Crown casi siempre 130110000) y alta en la CC del cliente por CUIT. El resto → efectivo del PV.',
                'Si el comprobante ya está en el ERP, se omite: la reimportación no pisa facturas ni asientos.',
            ],
            'hotel' => $hotel['codigo'],
            'hotel_nombre' => $hotel['nombre'],
            'puntoventa' => $pvFiltro['codigo'] !== '' ? $pvFiltro['codigo'] : 'todos',
            'desde' => $desdeYmd,
            'hasta' => $hastaYmd,
            'mostrar_pago' => true,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{
     *   grabados: int,
     *   enganchados: int,
     *   asientos: int,
     *   replicadas_anita: int,
     *   total_grabado: float,
     *   total_enganchado: float,
     *   errores: list<string>
     * }
     */
    public function confirmar(array $filas, int $usuarioId): array
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '900');

        // Defensa: no reprocesar existencias aunque el preview viejo diga enganchar.
        $filas = $this->writer->marcarExistentes($filas);

        $grabados = 0;
        $totalGrabado = 0.0;
        $replicadasAnita = 0;
        $errores = [];
        foreach ($filas as $fila) {
            if (($fila['estado'] ?? '') !== 'nuevo') {
                continue;
            }
            $etiqueta = TransferenciaCrownComprobante::codigoVisible($fila);
            $signo = TransferenciaCrownComprobante::esCredito($fila) ? -1 : 1;
            $importe = round($signo * abs((float) ($fila['total'] ?? 0)), 2);
            try {
                $fila = $this->asegurarVtoCae($fila);
                $leyenda = 'Importación PXSOL API '.$etiqueta;
                if (! empty($fila['pxsol_voucher_id'])) {
                    $leyenda .= ' #'.(int) $fila['pxsol_voucher_id'];
                }
                if (! empty($fila['payment_type'])) {
                    $leyenda .= ' '.$fila['payment_type'];
                }
                $grabado = $this->writer->grabar($fila, $usuarioId, $leyenda);
                $grabados++;
                $totalGrabado = round($totalGrabado + $importe, 2);
                $ventaId = (int) ($grabado['venta_id'] ?? 0);
                if ($ventaId > 0) {
                    $rep = $this->replicaAnita->replicarVentaId($ventaId);
                    if ($rep['ok']) {
                        $replicadasAnita++;
                    } else {
                        $errores[] = $etiqueta.': ERP OK; Anita: '.$rep['mensaje'];
                    }
                }
            } catch (\Throwable $e) {
                $errores[] = $etiqueta.': '.$e->getMessage();
            }
        }

        return [
            'grabados' => $grabados,
            'enganchados' => 0,
            'asientos' => $grabados,
            'replicadas_anita' => $replicadasAnita,
            'total_grabado' => $totalGrabado,
            'total_enganchado' => 0.0,
            'errores' => $errores,
        ];
    }

    /**
     * @param  array{
     *   codigo: string,
     *   nombre: string,
     *   hotel_id: int,
     *   token: string,
     *   puntoventas: list<array{codigo: string, nombre: string, fiscal: int}>
     * }  $hotel
     * @return array{codigo: string, fiscal: int|null}
     */
    private function resolverPvFiltro(array $hotel, string $puntoventaCodigo): array
    {
        $puntoventaCodigo = preg_replace('/\D+/', '', $puntoventaCodigo) ?? '';
        if ($puntoventaCodigo === '' || $puntoventaCodigo === '0') {
            return ['codigo' => '', 'fiscal' => null];
        }
        $codigo = str_pad($puntoventaCodigo, 5, '0', STR_PAD_LEFT);
        $fiscal = (int) $puntoventaCodigo;
        foreach ($hotel['puntoventas'] as $pv) {
            if ($pv['codigo'] === $codigo) {
                return ['codigo' => $codigo, 'fiscal' => $pv['fiscal']];
            }
        }

        return ['codigo' => $codigo, 'fiscal' => $fiscal];
    }

    private function validarRango(string $desdeYmd, string $hastaYmd): void
    {
        try {
            $desde = Carbon::createFromFormat('Y-m-d', $desdeYmd)->startOfDay();
            $hasta = Carbon::createFromFormat('Y-m-d', $hastaYmd)->startOfDay();
        } catch (\Throwable) {
            throw new RuntimeException('Las fechas desde/hasta no son válidas.');
        }
        if ($hasta->lt($desde)) {
            throw new RuntimeException('La fecha hasta no puede ser anterior a desde.');
        }
        $maxDias = max(1, (int) config('ventas_transferencia_crown.max_dias', 31));
        if ($desde->diffInDays($hasta) + 1 > $maxDias) {
            throw new RuntimeException('El rango no puede superar '.$maxDias.' días.');
        }
    }

    /**
     * @param  array<string, mixed>  $cruda
     * @param  array<int, string>  $mapeo
     * @return array<string, mixed>
     */
    private function normalizar(array $cruda, array $mapeo): array
    {
        $tipoAfip = (int) ($cruda['codigo_afip'] ?? $cruda['tipo_afip'] ?? 0);
        $tipo = $mapeo[$tipoAfip] ?? TransferenciaCrownArcaTipos::defecto($tipoAfip);
        if (! isset(TransferenciaCrownArcaTipos::OPCIONES[$tipo])) {
            $tipo = TransferenciaCrownComprobante::abreviaturaErp((string) $cruda['tipo']);
        }
        $letra = strtoupper((string) ($cruda['letra'] ?? 'B'));
        $fila = $cruda;
        $fila['tipo'] = $tipo;
        $fila['tipo_anita'] = strtoupper((string) ($cruda['tipo_anita'] ?? $cruda['tipo'] ?? $tipo));
        $fila['letra'] = $letra;
        $fila['abreviatura_erp'] = $tipo;
        $fila['codigo_afip'] = $tipoAfip > 0
            ? $tipoAfip
            : TransferenciaCrownComprobante::codigoAfip($tipo, $letra);
        $fila['estado'] = 'nuevo';
        $fila['mensaje'] = '';
        $fila['asiento_origen'] = 'generado';
        $fila['asiento_usa_jornada'] = false;
        $fila['nro_operacion'] = 0;
        $fila['lineas_asiento'] = [];
        $fila['es_cuenta_corriente'] = (bool) ($cruda['es_cuenta_corriente'] ?? false);

        $pagoTxt = trim((string) ($fila['payment_type'] ?? ''));
        if ($pagoTxt !== '') {
            $fila['mensaje'] = $pagoTxt.($fila['es_cuenta_corriente'] ? ' → cuenta corriente' : ' → efectivo');
        }

        if ((int) $fila['sucursal'] <= 0 || (int) $fila['numero'] <= 0) {
            $fila['estado'] = 'error';
            $fila['mensaje'] = 'Falta punto de venta fiscal o número.';

            return $fila;
        }
        if (trim((string) ($fila['fecha'] ?? '')) === '') {
            $fila['estado'] = 'error';
            $fila['mensaje'] = 'Falta fecha del comprobante.';

            return $fila;
        }
        if ((float) $fila['total'] <= 0 && (float) $fila['gravado21'] <= 0) {
            $fila['total'] = 0.01;
            $fila['gravado21'] = 0.01;
        }

        $pv = Puntoventa::query()
            ->where('codigo', str_pad((string) (int) $fila['sucursal'], 5, '0', STR_PAD_LEFT))
            ->orderBy('id')
            ->first();
        if ($pv === null) {
            $fila['estado'] = 'error';
            $fila['mensaje'] = 'No existe el punto de venta '.$fila['sucursal'].'.';

            return $fila;
        }

        if ($fila['es_cuenta_corriente']) {
            $cliente = $this->buscarClientePorDocumento((string) ($fila['cuit'] ?? ''));
            if ($cliente === null) {
                $fila['estado'] = 'error';
                $fila['mensaje'] = 'Cuenta corriente sin cliente Anita para CUIT/DNI '.($fila['cuit'] ?: '(vacío)').'.';

                return $fila;
            }
            $fila['cliente_id'] = (int) $cliente->id;
            if (trim((string) ($fila['nombre'] ?? '')) === '') {
                $fila['nombre'] = (string) $cliente->nombre;
            }
        }

        try {
            $asiento = $this->asientos->generar((int) $pv->empresa_id, $fila, 'pxsol');
            $fila['lineas_asiento'] = $asiento['lineas'];
            $desbalance = TransferenciaCrownComprobante::desbalance($asiento['lineas']);
            if (abs($desbalance) > 0.05) {
                $fila['estado'] = 'error';
                $fila['mensaje'] = 'El asiento no balancea ('.$desbalance.').';
            }
        } catch (\Throwable $e) {
            $fila['estado'] = 'error';
            $fila['mensaje'] = $e->getMessage();
        }

        return $fila;
    }

    private function buscarClientePorDocumento(string $documento): ?Cliente
    {
        $documento = preg_replace('/\D+/', '', $documento) ?? '';
        if ($documento === '') {
            return null;
        }

        // El CUIT se guarda con guiones (30-61206726-7); la API lo trae solo con dígitos.
        return Cliente::query()
            ->whereRaw("REPLACE(REPLACE(REPLACE(numerodocumento, '-', ''), '.', ''), ' ', '') = ?", [$documento])
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private function asegurarVtoCae(array $fila): array
    {
        if (trim((string) ($fila['fechavencimientocae'] ?? '')) !== '') {
            return $fila;
        }
        if (trim((string) ($fila['cae'] ?? '')) === '') {
            return $fila;
        }
        $fecha = trim((string) ($fila['fecha'] ?? ''));
        if ($fecha === '') {
            return $fila;
        }
        try {
            $fila['fechavencimientocae'] = Carbon::parse($fecha)->addDays(10)->format('Y-m-d');
        } catch (\Throwable) {
        }

        return $fila;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, float|int>
     */
    private function resumen(array $filas): array
    {
        $resumen = [
            'leidos' => count($filas),
            'nuevos' => 0,
            'enganchar' => 0,
            'existen' => 0,
            'errores' => 0,
            'total_nuevos' => 0.0,
            'cuenta_corriente' => 0,
            'efectivo' => 0,
        ];
        foreach ($filas as $fila) {
            if (! empty($fila['es_cuenta_corriente'])) {
                $resumen['cuenta_corriente']++;
            } else {
                $resumen['efectivo']++;
            }
            if ($fila['estado'] === 'nuevo') {
                $resumen['nuevos']++;
                $signo = TransferenciaCrownComprobante::esCredito($fila) ? -1 : 1;
                $resumen['total_nuevos'] += $signo * (float) $fila['total'];
            } elseif ($fila['estado'] === 'enganchar') {
                $resumen['enganchar']++;
            } elseif ($fila['estado'] === 'existe') {
                $resumen['existen']++;
            } else {
                $resumen['errores']++;
            }
        }
        $resumen['total_nuevos'] = round($resumen['total_nuevos'], 2);

        return $resumen;
    }
}
