<?php

namespace App\Support\Contable\ConciliacionBancaria;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Cuentacaja;
use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Contable\Cuentacontable;
use App\Support\Compras\ComprobanteProveedorTipoTesoreria;
use App\Support\Contable\CuentacontableEmpresaHomologacionSupport;
use Carbon\Carbon;

/**
 * Arma el egreso de gastos bancarios y el ICO a partir de la codificación del extracto.
 */
final class ConciliacionBancariaGastoDocumentoSupport
{
    /**
     * @param  list<array<string, mixed>>  $movimientosBanco
     * @param  array<string, int|string>  $overridesSaldo
     * @return array<string, mixed>
     */
    public static function armar(
        array $movimientosBanco,
        int $empresaId,
        Cuentacaja $cuentacaja,
        int $mes,
        int $anio,
        array $overridesSaldo = [],
    ): array {
        $fecha = Carbon::createFromDate($anio, $mes, 1)->endOfMonth()->toDateString();
        $detalle = self::detalle($cuentacaja, $mes, $anio);
        $grupos = [];

        foreach ($movimientosBanco as $mov) {
            $clasif = ConciliacionBancariaCodificacionSupport::clasificarMovimientoBanco($mov, $overridesSaldo);
            $codigo = $clasif['codigo'];
            if ($codigo === 'AC' || ! is_numeric($codigo)) {
                continue;
            }

            $codigo = (int) $codigo;
            if ($codigo === (int) config('conciliacion_bancaria.codigo_transferencia_default', 10) || $codigo === 9) {
                continue;
            }

            $importe = ConciliacionBancariaHashSupport::importeFirmadoBanco($mov);
            if ($importe >= -0.004) {
                continue;
            }

            if (! isset($grupos[$codigo])) {
                $grupos[$codigo] = [
                    'codigo' => $codigo,
                    'descripcion' => (string) ($clasif['descripcion'] ?? ''),
                    'importe' => 0.0,
                ];
            }
            $grupos[$codigo]['importe'] += $importe;
        }

        ksort($grupos);
        $lineas = [];
        $faltantes = [];
        $total = 0.0;

        foreach ($grupos as $codigo => $grupo) {
            $importe = round(abs((float) $grupo['importe']), 2);
            if ($importe < 0.01) {
                continue;
            }

            $resuelta = self::resolverLinea((int) $codigo, $empresaId, (string) $grupo['descripcion']);
            $linea = [
                'codigo' => (int) $codigo,
                'descripcion' => (string) $grupo['descripcion'],
                'importe' => $importe,
                'cuenta_codigo' => $resuelta['cuenta_codigo'],
                'cuenta_nombre' => $resuelta['cuenta_nombre'],
                'cuentacontable_id' => $resuelta['cuentacontable_id'],
                'concepto_ivacompra_id' => $resuelta['concepto_ivacompra_id'],
                'concepto_codigo' => $resuelta['concepto_codigo'],
                'en_ico' => $resuelta['concepto_ivacompra_id'] > 0,
            ];
            if ($resuelta['error'] !== null) {
                $faltantes[] = $resuelta['error'];
                $linea['error'] = $resuelta['error'];
            }
            $lineas[] = $linea;
            $total += $importe;
        }

        $total = round($total, 2);
        $cuentaBancoId = CuentacontableEmpresaHomologacionSupport::idParaEmpresa(
            (int) ($cuentacaja->cuentacontable_id ?? 0),
            $empresaId,
        );
        if ($total > 0 && $cuentaBancoId <= 0) {
            $faltantes[] = 'La cuenta de caja no tiene cuenta contable para el haber del egreso.';
        }

        $existente = Caja_Movimiento::query()
            ->where('empresa_id', $empresaId)
            ->where('detalle', $detalle)
            ->first(['id', 'numerotransaccion']);

        return [
            'fecha' => $fecha,
            'detalle' => $detalle,
            'cuentacaja_id' => (int) $cuentacaja->id,
            'cuentacaja_codigo' => (string) ($cuentacaja->codigo ?? ''),
            'cuentacaja_nombre' => (string) ($cuentacaja->nombre ?? ''),
            'cuenta_banco_id' => $cuentaBancoId,
            'tipo_caja' => (string) config('conciliacion_bancaria.documento_gastos.tipo_caja_abreviatura', 'EGR'),
            'tipo_compra' => (string) config('conciliacion_bancaria.documento_gastos.tipo_compra_abreviatura', 'ICO'),
            'tipo_tesoreria' => ComprobanteProveedorTipoTesoreria::GASTO_BANCO,
            'lineas' => $lineas,
            'total' => $total,
            'faltantes' => $faltantes,
            'listo' => $total > 0 && $faltantes === [] && $cuentaBancoId > 0,
            'ya_generado' => $existente ? [
                'id' => (int) $existente->id,
                'numero' => (string) $existente->numerotransaccion,
            ] : null,
        ];
    }

    public static function detalle(Cuentacaja $cuentacaja, int $mes, int $anio): string
    {
        return sprintf(
            'Gastos bancarios conciliación %s %02d/%04d',
            trim((string) ($cuentacaja->codigo ?? '')),
            $mes,
            $anio,
        );
    }

    /**
     * @return array{
     *   cuentacontable_id: int,
     *   cuenta_codigo: string,
     *   cuenta_nombre: string,
     *   concepto_ivacompra_id: int,
     *   concepto_codigo: string,
     *   error: ?string
     * }
     */
    private static function resolverLinea(int $codigo, int $empresaId, string $descripcion): array
    {
        $vacio = [
            'cuentacontable_id' => 0,
            'cuenta_codigo' => '',
            'cuenta_nombre' => '',
            'concepto_ivacompra_id' => 0,
            'concepto_codigo' => '',
            'error' => null,
        ];

        $def = config('conciliacion_bancaria.documento_gastos.lineas.'.$codigo);
        if (! is_array($def)) {
            $vacio['error'] = 'El código '.$codigo.' ('.$descripcion.') no tiene cuenta ni concepto en la configuración de gastos.';

            return $vacio;
        }

        $conceptoCodigo = trim((string) ($def['concepto_ivacompra_codigo'] ?? ''));
        if ($conceptoCodigo !== '') {
            $concepto = Concepto_Ivacompra::query()->where('codigo', $conceptoCodigo)->first();
            if ($concepto === null) {
                $vacio['error'] = 'No existe el concepto de IVA compras '.$conceptoCodigo.' para el código '.$codigo.'.';

                return $vacio;
            }

            $cuentaId = $concepto->cuentacontableDebeIdParaEmpresa($empresaId);
            $cuenta = $cuentaId > 0 ? Cuentacontable::query()->find($cuentaId) : null;
            if ($cuentaId <= 0 || $cuenta === null) {
                $vacio['concepto_ivacompra_id'] = (int) $concepto->id;
                $vacio['concepto_codigo'] = $conceptoCodigo;
                $vacio['error'] = 'El concepto '.$conceptoCodigo.' no tiene cuenta de debe para la empresa.';

                return $vacio;
            }

            return [
                'cuentacontable_id' => (int) $cuenta->id,
                'cuenta_codigo' => (string) ($cuenta->codigo ?? ''),
                'cuenta_nombre' => (string) ($cuenta->nombre ?? ''),
                'concepto_ivacompra_id' => (int) $concepto->id,
                'concepto_codigo' => $conceptoCodigo,
                'error' => null,
            ];
        }

        $cuentaCodigo = trim((string) ($def['cuenta_codigo'] ?? ''));
        if ($cuentaCodigo === '') {
            $vacio['error'] = 'El código '.$codigo.' ('.$descripcion.') no tiene cuenta contable configurada.';

            return $vacio;
        }

        $cuenta = Cuentacontable::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', $cuentaCodigo)
            ->first();
        if ($cuenta === null) {
            $vacio['error'] = 'No existe la cuenta '.$cuentaCodigo.' en la empresa para el código '.$codigo.'.';

            return $vacio;
        }

        return [
            'cuentacontable_id' => (int) $cuenta->id,
            'cuenta_codigo' => (string) ($cuenta->codigo ?? ''),
            'cuenta_nombre' => (string) ($cuenta->nombre ?? ''),
            'concepto_ivacompra_id' => 0,
            'concepto_codigo' => '',
            'error' => null,
        ];
    }
}
