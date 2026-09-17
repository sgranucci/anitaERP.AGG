<?php

namespace App\Support\Caja;

use App\ApiAnita;
use App\Models\Caja\Cheque;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Illuminate\Support\Facades\Log;

/**
 * Insert de CHT en Anita (ctermae) + numeración interna.
 * Patrón alineado a CobranzaService (acc=insert che_ban).
 */
final class ChequeTerceroCtermaeInsertAnitaSupport
{
    /**
     * Obtiene el próximo cter_nro_interno (max+1).
     * Requiere sistema che_ban: sin eso el bridge no descarga el CSV y se usaba 1 por error.
     */
    public static function siguienteNroInterno(): ?int
    {
        try {
            $api = new ApiAnita();
            $raw = $api->apiCall([
                'acc' => 'list',
                'sistema' => 'che_ban',
                'tabla' => 'ctermae',
                'campos' => 'max(cter_nro_interno) as numerointerno',
            ]);
            $err = ApiAnita::extraerMensajeError(is_string($raw) ? $raw : null);
            if ($err !== null) {
                Log::warning('Cheque CHT: error al leer max cter_nro_interno', [
                    'error' => $err,
                    'ferli' => EntornoEmpresaSupport::esFerli(),
                ]);

                return null;
            }
            $dataAnita = json_decode((string) $raw);
            if (! is_array($dataAnita) || $dataAnita === []) {
                return 1;
            }
            $max = (int) ($dataAnita[0]->numerointerno ?? 0);

            return $max > 0 ? $max + 1 : 1;
        } catch (\Throwable $e) {
            Log::warning('Cheque CHT: no se pudo leer max cter_nro_interno', [
                'error' => $e->getMessage(),
                'ferli' => EntornoEmpresaSupport::esFerli(),
            ]);

            return null;
        }
    }

    /**
     * Inserta el cheque en ctermae y devuelve el nro interno, o null si falló.
     */
    public static function insertar(Cheque $cheque): ?int
    {
        if ((string) ($cheque->origen ?? '') !== 'R') {
            return null;
        }
        if ((int) ($cheque->nro_interno_anita ?? 0) > 0) {
            return (int) $cheque->nro_interno_anita;
        }

        $nroInterno = self::siguienteNroInterno();
        if ($nroInterno === null || $nroInterno <= 0) {
            return null;
        }

        $cheque->loadMissing(['clientes:id,codigo,nombre,cuit', 'bancos:id,codigo,nombre', 'empresas:id,codigo']);

        $fechaPago = ChequePropioCpromaeAnitaMapper::ymd((string) ($cheque->fechapago ?? ''));
        $fechaIngreso = ChequePropioCpromaeAnitaMapper::ymd((string) ($cheque->fechaemision ?? $cheque->fechapago ?? date('Y-m-d')));
        if ($fechaPago === '0') {
            $fechaPago = date('Ymd');
        }
        if ($fechaIngreso === '0') {
            $fechaIngreso = $fechaPago;
        }

        $camara = ((string) ($cheque->fechapago ?? '') > (string) ($cheque->fechaemision ?? date('Y-m-d'))) ? '2' : '1';

        $codigoCliente = '000000';
        if ($cheque->clientes) {
            $codigoCliente = str_pad(
                ltrim(preg_replace('/\D/', '', (string) ($cheque->clientes->codigo ?? '0')) ?: '0', '0') ?: '0',
                6,
                '0',
                STR_PAD_LEFT
            );
        }

        $codigoBanco = trim((string) ($cheque->bancos->codigo ?? '0'));
        if ($codigoBanco === '') {
            $codigoBanco = '0';
        }
        $nombreBanco = self::esc(mb_substr((string) ($cheque->bancos->nombre ?? ''), 0, 40));

        $empresa = (string) SicoreEmpresaAnitaSupport::codigoEmpresaAnita((int) ($cheque->empresa_id ?? 0));
        $cuit = preg_replace('/\D/', '', (string) ($cheque->clientes->cuit ?? '')) ?: '0';
        $entregadoPor = self::esc(mb_substr((string) ($cheque->entregado ?? $cheque->clientes->nombre ?? ''), 0, 40));
        $entregadoA = self::esc(mb_substr((string) ($cheque->anombrede ?? ''), 0, 40));
        $sucursal = self::esc(mb_substr((string) ($cheque->sucursalpago ?? '0'), 0, 20));
        $ctaLib = self::esc(mb_substr((string) ($cheque->cuentalibradora ?? '0'), 0, 20));
        $nroCheque = self::esc((string) ($cheque->numerocheque ?? ''));
        $importe = number_format((float) $cheque->monto, 2, '.', '');
        $moneda = (int) ($cheque->moneda_id ?? 1) ?: 1;
        $cotizacion = (float) ($cheque->cotizacion ?? 1);
        if ($cotizacion <= 0) {
            $cotizacion = 1;
        }
        $nroCaucion = trim((string) ($cheque->nro_caucion ?? '0'));
        if ($nroCaucion === '') {
            $nroCaucion = '0';
        }

        $campos = '
                cter_nro_interno,
                cter_fecha_cheque,
                cter_fecha_ingreso,
                cter_fecha_dep,
                cter_fecha_acreed,
                cter_fecha_baja,
                cter_nro_cheque,
                cter_importe,
                cter_cliente,
                cter_proveedor,
                cter_entregado_a,
                cter_nro_recibo,
                cter_nro_op,
                cter_banco_emision,
                cter_cuenta,
                cter_nro_boleta,
                cter_clearing,
                cter_entregado_por,
                cter_interior,
                cter_nro_caucion,
                cter_cod_mon,
                cter_cotizacion,
                cter_estado,
                cter_cedio_a,
                cter_nro_cesion,
                cter_sucursal_bco,
                cter_cod_pos_bco,
                cter_cta_libradora,
                cter_cod_banco,
                cter_cuit_emisor';

        $valores = "
            '".$nroInterno."',
            '".$fechaPago."',
            '".$fechaIngreso."',
            '0',
            '0',
            '0',
            '".$nroCheque."',
            '".$importe."',
            '".$codigoCliente."',
            '',
            '".$entregadoA."',
            '0',
            '0',
            '".$nombreBanco."',
            '',
            '0',
            '0',
            '".$entregadoPor."',
            '".$camara."',
            '".self::esc($nroCaucion)."',
            '".$moneda."',
            '".$cotizacion."',
            ' ',
            '0',
            '0',
            '".$sucursal."',
            '0',
            '".$ctaLib."',
            '".self::esc($codigoBanco)."',
            '".self::esc($cuit)."'
        ";

        if (! EntornoEmpresaSupport::esFerli()) {
            $campos .= ',
                cter_empresa';
            $valores = rtrim($valores)." ,\n            '".$empresa."'\n        ";
        }

        $data = [
            'tabla' => 'ctermae',
            'acc' => 'insert',
            'sistema' => 'che_ban',
            'campos' => $campos,
            'valores' => $valores,
        ];

        try {
            $api = new ApiAnita();
            $api->apiCallEscritura($data);

            return $nroInterno;
        } catch (\Throwable $e) {
            Log::warning('Cheque CHT insert Anita falló', [
                'cheque_id' => $cheque->id,
                'nro_interno' => $nroInterno,
                'ferli' => EntornoEmpresaSupport::esFerli(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private static function esc(string $valor): string
    {
        return addslashes(trim($valor));
    }
}
