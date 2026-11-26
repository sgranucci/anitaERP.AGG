<?php

declare(strict_types=1);

namespace App\Services\Contable\IngresosBrutos;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Retencion;
use App\Models\Compras\Proveedor;
use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Models\Contable\IibbNeteoDevolucion;
use App\Models\Contable\Iibb_Presentacion_Config;
use App\Support\Contable\IngresosBrutos\IngresosBrutosFormatoArbaSupport;
use App\Support\Contable\IngresosBrutos\IngresosBrutosNeteoCalculoSupport as Calc;
use App\Support\Contable\Sicore\SicoreMayorComparableSupport;
use Carbon\Carbon;

/**
 * Detección e identificación de devoluciones de retenciones IIBB a netear.
 *
 * Fuente: movimientos al Debe de las cuentas configuradas (214010-014) en la quincena.
 * Las anulaciones de OP de la misma quincena ya vienen neteadas en el listado de
 * retenciones (retibrmov), así que no se consideran.
 */
final class IngresosBrutosNeteoService
{
    /**
     * @param  list<array<string, mixed>>  $registros  listado completo de retenciones (positivos y negativos)
     * @return list<array<string, mixed>>
     */
    public function detectar(
        int $empresaId,
        Iibb_Presentacion_Config $config,
        string $desde,
        string $hasta,
        array $registros,
    ): array {
        $palabrasBanco = $config->palabrasBanco();

        $positivos = array_values(array_filter(
            $registros,
            static fn (array $r) => (float) ($r['importe'] ?? 0) >= -0.001,
        ));

        $cuentaIds = $config->cuentas
            ->where('empresa_id', $empresaId)
            ->pluck('cuentacontable_id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        if ($cuentaIds === []) {
            return [];
        }

        $movimientos = Asiento_Movimiento::query()
            ->whereIn('cuentacontable_id', $cuentaIds)
            ->where('monto', '>', 0)
            ->whereHas('asientos', function ($q) use ($empresaId, $desde, $hasta) {
                $q->where('empresa_id', $empresaId)
                    ->whereBetween('fecha', [$desde, $hasta]);
            })
            ->with(['asientos'])
            ->orderBy('id')
            ->get();

        if ($movimientos->isEmpty()) {
            return [];
        }

        $cajaIds = $movimientos
            ->map(static fn (Asiento_Movimiento $m) => (int) ($m->asientos?->caja_movimiento_id ?? 0))
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        $cajas = $cajaIds === []
            ? collect()
            : Caja_Movimiento::query()->whereIn('id', $cajaIds)->with('proveedores')->get()->keyBy('id');

        $out = [];
        $vistos = [];
        foreach ($movimientos as $mov) {
            $asiento = $mov->asientos;
            if (! $asiento instanceof Asiento) {
                continue;
            }

            // Mismo criterio que el listado: sin tipo Anita se trata como OPP.
            $tipoAnita = strtoupper(trim((string) ($asiento->anita_tipo ?? ''))) ?: 'OPP';
            if (! SicoreMayorComparableSupport::esTipoDevolucionRetencion($tipoAnita)) {
                continue;
            }

            $detalle = trim((string) ($mov->observacion ?? $asiento->observacion ?? ''));
            $caja = $cajas->get((int) ($asiento->caja_movimiento_id ?? 0));
            if ($caja instanceof Caja_Movimiento && trim((string) $caja->detalle) !== '') {
                $detalle = trim((string) $caja->detalle);
            }

            $txt = Calc::analizarDetalle($detalle);

            // Pago de la DJ: emisor banco y detalle de una solicitud de retenciones de la quincena.
            // Un Debe sin CUIT (o con devolu / anula / reintegr / mal ejecutad) es devolución,
            // aunque el texto mencione ARBA o IIBB. El banco no se toma como proveedor.
            if ($this->esPagoLiquidacionQuincena($detalle, $caja, $palabrasBanco)) {
                continue;
            }
            if (! $this->esDevolucion($detalle, $txt)) {
                continue;
            }

            if ($txt['anula_op'] > 0 && $this->anulacionYaCompensada($empresaId, $txt['anula_op'], $desde, $hasta, $registros)) {
                continue;
            }

            $importe = round(abs((float) $mov->monto), 2);
            if ($importe < 0.001 || isset($vistos[(int) $mov->id])) {
                continue;
            }
            $vistos[(int) $mov->id] = true;

            $fecha = $asiento->fecha
                ? (is_string($asiento->fecha) ? substr($asiento->fecha, 0, 10) : $asiento->fecha->format('Y-m-d'))
                : '';
            if ($fecha === '') {
                continue;
            }

            $ident = $this->identificarProveedor($empresaId, $caja, $txt, $palabrasBanco);
            $prov = $ident['proveedor'];
            $via = $ident['via'];

            $certRef = $txt['cert'] > 0 ? $txt['cert'] : null;
            if ($certRef === null && $txt['op'] > 0) {
                $certRef = $this->certificadoDeOp($empresaId, $txt['op']);
            }

            // Un solo certificado de la quincena con el mismo importe: se propone y el usuario confirma.
            if ($certRef === null) {
                $sug = $this->certificadoSugerido($importe, $positivos);
                if ($sug !== null) {
                    if ($prov === null) {
                        $prov = $this->proveedorPorCuit($sug['nro_documento']);
                    }
                    $certRef = $sug['nro_cert'];
                    $via = 'cert';
                }
            }

            $out[] = [
                'asiento_movimiento_id' => (int) $mov->id,
                'asiento_id' => (int) $asiento->id,
                'asiento_numero' => (int) ($asiento->numeroasiento ?? $asiento->anita_nro ?? 0),
                'fecha' => $fecha,
                'detalle' => $detalle,
                'importe' => $importe,
                'proveedor' => $prov,
                'identificacion' => $via,
                'cert_ref' => $certRef,
            ];
        }

        return $out;
    }

    /**
     * Devoluciones persistidas de quincenas anteriores que aún tienen saldo sin neteo.
     *
     * Saldo = importe − Σ aplicaciones de cualquier quincena distinta de la actual
     * (las de la actual se recalculan al generar).
     *
     * @param  list<int>  $asientoMovIdsActuales
     * @return list<array<string, mixed>>
     */
    public function pendientesAnteriores(
        int $empresaId,
        int $configId,
        string $periodo,
        int $quincena,
        string $desde,
        array $asientoMovIdsActuales,
    ): array {
        $devs = IibbNeteoDevolucion::query()
            ->where('empresa_id', $empresaId)
            ->where('iibb_presentacion_config_id', $configId)
            ->where('fecha', '<', $desde)
            ->with(['proveedor:id,codigo,nombre,nroinscripcion', 'aplicaciones'])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($devs as $dev) {
            if (in_array((int) $dev->asiento_movimiento_id, $asientoMovIdsActuales, true)) {
                continue;
            }

            $aplicado = 0.0;
            foreach ($dev->aplicaciones as $ap) {
                if ((string) $ap->periodo === $periodo && (int) $ap->quincena === $quincena) {
                    continue;
                }
                $aplicado += (float) $ap->importe_aplicado;
            }

            $saldo = round((float) $dev->importe - $aplicado, 2);
            if ($saldo < 0.01) {
                continue;
            }

            $prov = $dev->proveedor;
            $out[] = [
                'id' => 'db'.$dev->id,
                'db_id' => (int) $dev->id,
                'asiento_movimiento_id' => (int) $dev->asiento_movimiento_id,
                'asiento_numero' => (int) ($dev->asiento_numero ?? 0),
                'fecha' => (string) $dev->fecha,
                'detalle' => (string) ($dev->detalle ?? ''),
                'importe' => $saldo,
                'proveedor_id' => $prov !== null ? (int) $prov->id : null,
                'proveedor' => $prov !== null ? Calc::digitos((string) $prov->nroinscripcion) : '',
                'proveedor_label' => $prov !== null ? trim($prov->codigo.' '.$prov->nombre) : '',
                'cert_ref' => (int) ($dev->cert_referencia ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Una anulación de OP ya está compensada en el listado cuando:
     *  - hay un reverso negativo del mismo OP (Anita ya lo descontó), o
     *  - el certificado ya no está en el listado y el pago es de esta quincena (ya quedó fuera del TXT).
     *
     * Si el certificado sigue positivo en el listado, la anulación hay que netearla.
     *
     * @param  list<array<string, mixed>>  $registros
     */
    private function anulacionYaCompensada(int $empresaId, int $opNro, string $desde, string $hasta, array $registros): bool
    {
        $negativo = false;
        $positivo = false;
        foreach ($registros as $r) {
            if ((int) ($r['nro_comp'] ?? 0) !== $opNro) {
                continue;
            }
            if ((float) ($r['importe'] ?? 0) < -0.001) {
                $negativo = true;
            } else {
                $positivo = true;
            }
        }

        if ($negativo) {
            return true;
        }
        if ($positivo) {
            return false;
        }

        $fecha = Pagoproveedor::query()
            ->where('empresa_id', $empresaId)
            ->where('numerotransaccion', $opNro)
            ->value('fecha');
        if ($fecha === null) {
            return false;
        }

        $f = Carbon::parse($fecha)->format('Y-m-d');

        return $f >= $desde && $f <= $hasta;
    }

    /**
     * Pago de la DJ: detalle de una solicitud de retenciones de la quincena
     * («Retenciones ARBA 1Q 10.2026», «Retenciones Arba Bsa 10.26 2Q»).
     * Una devolución que además nombra la quincena se conserva.
     *
     * @param  list<string>  $palabrasBanco
     */
    private function esPagoLiquidacionQuincena(string $detalle, ?Caja_Movimiento $caja, array $palabrasBanco): bool
    {
        if (preg_match('/devolu|reintegr|mal\s+ejecutad/iu', $detalle) === 1) {
            return false;
        }

        $tieneQuincena = preg_match('/\b[12]\s*Q\b/iu', $detalle) === 1;
        $tieneRetencion = preg_match('/\bretenc/iu', $detalle) === 1;
        if ($tieneQuincena && $tieneRetencion) {
            return true;
        }

        $emisor = $caja?->proveedores;

        return $emisor instanceof Proveedor
            && $this->esBanco($emisor, $palabrasBanco)
            && $tieneQuincena;
    }

    /**
     * Debe de devolución: el detalle lo dice, o es un Debe sin CUIT (el pago de la DJ ya se descartó).
     *
     * @param  array{devolucion: bool, anula_op: int, op: int, cert: int, codigo: string}  $txt
     */
    private function esDevolucion(string $detalle, array $txt): bool
    {
        if ($txt['devolucion'] || $txt['anula_op'] > 0) {
            return true;
        }
        if (preg_match('/devolu|anula|reintegr|mal\s+ejecutad/iu', $detalle) === 1) {
            return true;
        }

        return preg_match('/\d{2}-?\d{8}-?\d/', $detalle) !== 1;
    }

    /**
     * @return array{proveedor: ?Proveedor, via: ?string}
     */
    private function identificarProveedor(int $empresaId, ?Caja_Movimiento $caja, array $txt, array $palabrasBanco): array
    {
        $p = $caja?->proveedores;
        if ($p instanceof Proveedor && ! $this->esBanco($p, $palabrasBanco)) {
            return ['proveedor' => $p, 'via' => 'proveedor'];
        }

        if ($txt['codigo'] !== '') {
            $p = Proveedor::query()->where('codigo', $txt['codigo'])->first();
            if ($p instanceof Proveedor && ! $this->esBanco($p, $palabrasBanco)) {
                return ['proveedor' => $p, 'via' => 'codigo'];
            }
        }

        if ($txt['op'] > 0) {
            $pago = Pagoproveedor::query()
                ->where('empresa_id', $empresaId)
                ->where('numerotransaccion', $txt['op'])
                ->with('proveedores')
                ->first();
            $p = $pago?->proveedores;
            if ($p instanceof Proveedor && ! $this->esBanco($p, $palabrasBanco)) {
                return ['proveedor' => $p, 'via' => 'op'];
            }
        }

        return ['proveedor' => null, 'via' => null];
    }

    /**
     * Palabra completa (sin distinguir mayúsculas) en el nombre del proveedor.
     * Sin palabras configuradas no se excluye ningún proveedor.
     *
     * @param  list<string>  $palabrasBanco
     */
    private function esBanco(Proveedor $p, array $palabrasBanco): bool
    {
        if ($palabrasBanco === []) {
            return false;
        }

        $alternativas = implode('|', array_map(static fn (string $w) => preg_quote($w, '/'), $palabrasBanco));

        return preg_match('/\b('.$alternativas.')\b/iu', (string) ($p->nombre ?? '')) === 1;
    }

    private function certificadoDeOp(int $empresaId, int $opNro): ?int
    {
        $nro = Pagoproveedor_Retencion::query()
            ->where('tiporetencion', Pagoproveedor_Retencion::TIPO_IIBB)
            ->whereHas('pagoproveedores', static function ($q) use ($empresaId, $opNro) {
                $q->where('empresa_id', $empresaId)->where('numerotransaccion', $opNro);
            })
            ->value('nro_certificado');

        $n = (int) preg_replace('/\D+/', '', (string) $nro);

        return $n > 0 ? $n : null;
    }

    /**
     * Certificado del listado con importe exacto; solo si hay uno.
     *
     * @param  list<array<string, mixed>>  $positivos
     * @return array{nro_cert: int, nro_documento: string}|null
     */
    private function certificadoSugerido(float $importe, array $positivos): ?array
    {
        $coinciden = [];
        foreach ($positivos as $r) {
            if (abs((float) ($r['importe'] ?? 0) - $importe) < 0.005) {
                $coinciden[] = $r;
            }
        }
        if (count($coinciden) !== 1) {
            return null;
        }

        return [
            'nro_cert' => (int) $coinciden[0]['nro_cert'],
            'nro_documento' => (string) ($coinciden[0]['nro_documento'] ?? ''),
        ];
    }

    private function proveedorPorCuit(string $cuit): ?Proveedor
    {
        $d = Calc::digitos($cuit);
        if (strlen($d) !== 11) {
            return null;
        }

        return Proveedor::query()
            ->whereIn('nroinscripcion', [$d, IngresosBrutosFormatoArbaSupport::cuitConGuiones($d)])
            ->first();
    }
}
