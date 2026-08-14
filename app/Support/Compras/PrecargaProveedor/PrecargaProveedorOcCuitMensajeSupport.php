<?php

namespace App\Support\Compras\PrecargaProveedor;

use App\ApiAnita;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mensaje accionable cuando la OC informada no pertenece al CUIT del comprobante.
 *
 * El agente externo suele mandar un número de 6 dígitos leído del PDF (remito, pedido,
 * código interno) en lugar de la OC del asunto/nombre de archivo. Con el mensaje genérico
 * («OC no corresponde con el CUIT indicado») el aviso termina pidiéndole al proveedor que
 * corrija una OC que estaba bien. Acá se informa a quién pertenece esa OC y se sugieren
 * las últimas OC del CUIT recibido.
 */
final class PrecargaProveedorOcCuitMensajeSupport
{
    /** OC sugeridas en el mensaje. */
    private const MAX_SUGERENCIAS = 5;

    /** Ventana de OC candidatas (el bridge HTTP ignora FIRST: se acota por fecha). */
    private const DIAS_VENTANA = 180;

    public function __construct(
        private PrecargaProveedorResolucionSupport $resolucionSupport,
    ) {}

    /**
     * @param  string  $numeroOc  OC informada por el agente
     * @param  string  $cuitOrdenCompra  CUIT dueño de esa OC (solo dígitos)
     * @param  string  $cuitInformado  CUIT del comprobante (solo dígitos)
     */
    public function mensaje(string $numeroOc, string $cuitOrdenCompra, string $cuitInformado): string
    {
        $mensaje = 'La OC '.$numeroOc.' pertenece al CUIT '.self::formatearCuit($cuitOrdenCompra)
            .', no al CUIT '.self::formatearCuit($cuitInformado).' del comprobante.';

        $sugeridas = $this->ultimasOcDelCuit($cuitInformado);
        if ($sugeridas !== []) {
            $mensaje .= ' Últimas OC de ese CUIT: '.implode(', ', $sugeridas).'.';
        }

        return $mensaje.' Verifique el número de orden de compra del comprobante'
            .' (puede haberse tomado un remito o pedido en lugar de la OC).';
    }

    /**
     * Best-effort: si el proveedor o el bridge Anita no responden, se devuelve vacío.
     *
     * @return list<string>
     */
    private function ultimasOcDelCuit(string $cuitInformado): array
    {
        try {
            $proveedor = $this->resolucionSupport->resolverProveedorPorCuit($cuitInformado);
            $codigo = str_pad((string) $proveedor['codigo'], 6, '0', STR_PAD_LEFT);

            $desde = (int) now()->subDays(self::DIAS_VENTANA)->format('Ymd');

            $filas = json_decode((new ApiAnita())->apiCall([
                'acc' => 'list',
                'sistema' => 'compras',
                'tabla' => 'pendmaep',
                'campos' => 'penmp_nro, penmp_fecha',
                'whereArmado' => " WHERE penmp_proveedor = '".$codigo."'"
                    .' AND penmp_fecha >= '.$desde.' ',
                'orderBy' => 'penmp_nro DESC',
            ]));

            if (! is_array($filas)) {
                return [];
            }

            $sugeridas = [];
            foreach ($filas as $fila) {
                $nro = trim((string) ($fila->penmp_nro ?? ''));
                if ($nro === '') {
                    continue;
                }
                $fecha = self::formatearFechaAnita((string) ($fila->penmp_fecha ?? ''));
                $sugeridas[] = $fecha !== '' ? $nro.' ('.$fecha.')' : $nro;
                if (count($sugeridas) >= self::MAX_SUGERENCIAS) {
                    break;
                }
            }

            return $sugeridas;
        } catch (Throwable $e) {
            Log::warning('precarga_proveedor_api.sugerencia_oc_cuit_fallo', [
                'cuit' => $cuitInformado,
                'mensaje' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private static function formatearCuit(string $digitos): string
    {
        $digitos = preg_replace('/\D/', '', $digitos) ?? '';
        if (strlen($digitos) !== 11) {
            return $digitos;
        }

        return substr($digitos, 0, 2).'-'.substr($digitos, 2, 8).'-'.substr($digitos, 10, 1);
    }

    /** Anita graba la fecha como YYYYMMDD. */
    private static function formatearFechaAnita(string $valor): string
    {
        $valor = preg_replace('/\D/', '', $valor) ?? '';
        if (strlen($valor) !== 8) {
            return '';
        }

        return substr($valor, 6, 2).'/'.substr($valor, 4, 2).'/'.substr($valor, 0, 4);
    }
}
