<?php

namespace App\Services\Compras;

use App\Mail\Compras\ComprobanteProveedorAuditorAnticipoBsUsoCapexMail;
use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Ordencompra;
use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Compras\ComprobanteProveedorAuditorAnticipoBsUsoCapexSupport;
use App\Support\Compras\ComprobanteProveedorFacturaAnticipadaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Auditor ERP-only: imputaciones a anticipo BS.USO (123010007) sin Capex en la OC.
 * Cubre facturas, COM y asientos con OC directa. Omite aperturas e importaciones Anita.
 */
final class ComprobanteProveedorAuditorAnticipoBsUsoCapexService
{
    /**
     * @return array<string, mixed>
     */
    public function ejecutar(
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        bool $enviarMail = true,
    ): array {
        $config = config('comprobante_proveedor_auditoria_erp.anticipo_bsuso_sin_capex', []);
        $codigo = (string) ($config['codigo_cuenta'] ?? '123010007');
        $maxFilas = max(10, (int) ($config['max_filas_mail'] ?? 80));

        [$desde, $hasta] = $this->resolverPeriodoMes($fechaDesde, $fechaHasta);

        $informe = [
            'fecha_calendario' => $desde.' → '.$hasta,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'campo_fecha' => 'asiento.fecha',
            'codigo_cuenta' => $codigo,
            'total' => 0,
            'ok' => 0,
            'omitidos' => 0,
            'desvios' => [],
            'desvios_oc_sin_capex' => 0,
            'desvios_sin_oc' => 0,
            'errores' => [],
            'requiere_alerta' => false,
            'mail_enviado' => false,
            'mail_destino' => null,
            'mail_error' => null,
        ];

        $rows = DB::select('
            SELECT a.id AS asiento_id,
                   a.fecha,
                   a.comprobante_proveedor_id,
                   a.recepcionproveedor_id,
                   a.ordencompra_id,
                   a.observacion,
                   a.anita_origen,
                   a.anita_tipo,
                   a.anita_nro,
                   SUM(am.monto) AS monto_puente
            FROM asiento a
            INNER JOIN asiento_movimiento am ON am.asiento_id = a.id
            INNER JOIN cuentacontable cc ON cc.id = am.cuentacontable_id
            WHERE DATE(a.fecha) >= ?
              AND DATE(a.fecha) <= ?
              AND cc.codigo = ?
            GROUP BY a.id
            HAVING ABS(SUM(am.monto)) >= 0.01
            ORDER BY a.fecha, a.id
        ', [$desde, $hasta, $codigo]);

        $informe['total'] = count($rows);

        foreach ($rows as $row) {
            $asientoId = (int) $row->asiento_id;
            try {
                [$oc, $origenEtiqueta, $extra] = $this->resolverOcYEtiqueta($row);
                $ocId = $oc ? (int) $oc->id : null;
                $cpId = (int) ($row->comprobante_proveedor_id ?? 0) ?: null;
                $comId = (int) ($row->recepcionproveedor_id ?? 0) ?: null;
                // Si el asiento no trae OC pero el CP/COM sí, usar ese id para apertura/clasificar.
                $ocIdParaFiltro = $ocId ?: ((int) ($row->ordencompra_id ?? 0) ?: null);

                $esApertura = ComprobanteProveedorAuditorAnticipoBsUsoCapexSupport::esApertura(
                    $row->observacion ?? null,
                    $row->anita_tipo ?? null,
                    $cpId,
                    $comId,
                    $ocIdParaFiltro,
                );
                $tieneDocumentoErp = $cpId !== null || $comId !== null;
                $fueraAlcance = ComprobanteProveedorAuditorAnticipoBsUsoCapexSupport::esFueraAlcanceErp(
                    $extra['origen_entrada'] !== '' ? $extra['origen_entrada'] : null,
                    $row->anita_origen ?? null,
                    $row->observacion ?? null,
                    $row->anita_tipo ?? null,
                    isset($row->anita_nro) ? (string) $row->anita_nro : null,
                    $tieneDocumentoErp,
                );

                $tieneCapex = ComprobanteProveedorFacturaAnticipadaSupport::ocTieneCapex($oc);
                $eval = ComprobanteProveedorAuditorAnticipoBsUsoCapexSupport::clasificar(
                    (float) $row->monto_puente,
                    $tieneCapex,
                    $ocId,
                    $esApertura,
                    $fueraAlcance,
                );

                $base = [
                    'id' => $asientoId,
                    'etiqueta' => $origenEtiqueta,
                    'empresa' => $extra['empresa'] ?? '',
                    'proveedor' => $extra['proveedor'] ?? '',
                    'oc' => $oc ? (string) ($oc->numeroordencompra ?? '') : '',
                    'modo_carga' => $extra['modo_carga'] ?? '',
                    'monto_puente' => round((float) $row->monto_puente, 2),
                    'tiene_capex' => $tieneCapex,
                    'fechaiva' => substr((string) $row->fecha, 0, 10),
                    'fechacomprobante' => substr((string) $row->fecha, 0, 10),
                    'asiento_id' => $asientoId,
                    'tipo' => $eval['tipo'],
                ];

                if ($eval['ok']) {
                    $informe['ok']++;

                    continue;
                }

                if ($eval['omitido']) {
                    $informe['omitidos']++;

                    continue;
                }

                if ($eval['tipo'] === ComprobanteProveedorAuditorAnticipoBsUsoCapexSupport::TIPO_SIN_OC) {
                    $informe['desvios_sin_oc']++;
                } else {
                    $informe['desvios_oc_sin_capex']++;
                }

                $informe['desvios'][] = $base + [
                    'estado' => 'desvio',
                    'motivo' => $eval['motivo'],
                ];
            } catch (Throwable $e) {
                $informe['errores'][] = [
                    'id' => $asientoId,
                    'etiqueta' => 'Asiento #'.$asientoId,
                    'mensaje' => $e->getMessage(),
                ];
                Log::warning('auditor_bsuso_capex.error', [
                    'asiento_id' => $asientoId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $informe['requiere_alerta'] = $informe['desvios'] !== [] || $informe['errores'] !== [];

        if ($enviarMail) {
            $this->enviarMail($informe, $config, $maxFilas);
        }

        return $informe;
    }

    /**
     * @return array{0: ?Ordencompra, 1: string, 2: array<string, string>}
     */
    private function resolverOcYEtiqueta(object $row): array
    {
        $extra = [
            'empresa' => '',
            'proveedor' => '',
            'modo_carga' => '',
            'origen_entrada' => '',
        ];

        $cpId = (int) ($row->comprobante_proveedor_id ?? 0);
        if ($cpId > 0) {
            $cp = Comprobante_Proveedor::query()
                ->with(['proveedores', 'tipotransaccion_compras', 'ordencompras.ordencompra_articulos', 'empresas'])
                ->find($cpId);
            if ($cp) {
                $extra['empresa'] = (string) ($cp->empresas->nombre ?? '');
                $extra['proveedor'] = (string) ($cp->proveedores->nombre ?? '');
                $extra['modo_carga'] = (string) ($cp->modo_carga ?? '');
                $extra['origen_entrada'] = (string) ($cp->origen_entrada ?? '');
                $abrev = (string) ($cp->tipotransaccion_compras->abreviatura ?? 'FC');
                $etiqueta = sprintf(
                    'FC %s %s %04d-%d (#%d) asiento %d',
                    $abrev,
                    (string) ($cp->letra ?? ''),
                    (int) ($cp->sucursal ?? 0),
                    (int) ($cp->numerocomprobante ?? 0),
                    $cpId,
                    (int) $row->asiento_id,
                );

                return [$cp->ordencompras, $etiqueta, $extra];
            }
        }

        $comId = (int) ($row->recepcionproveedor_id ?? 0);
        if ($comId > 0) {
            $com = Recepcion_Proveedor::query()
                ->with(['ordencompras.ordencompra_articulos', 'proveedores', 'empresas'])
                ->find($comId);
            if ($com) {
                $extra['empresa'] = (string) ($com->empresas->nombre ?? '');
                $extra['proveedor'] = (string) ($com->proveedores->nombre ?? '');
                $extra['modo_carga'] = 'COM';
                $oc = $com->ordencompras;
                if (! $oc && (int) ($com->ordencompra_id ?? 0) > 0) {
                    $oc = Ordencompra::query()
                        ->with('ordencompra_articulos')
                        ->find((int) $com->ordencompra_id);
                }
                $etiqueta = sprintf(
                    'COM #%s (recepción %d) asiento %d',
                    (string) ($com->numerorecepcion ?? $com->anita_nro ?? $comId),
                    $comId,
                    (int) $row->asiento_id,
                );

                return [$oc, $etiqueta, $extra];
            }
        }

        $ocId = (int) ($row->ordencompra_id ?? 0);
        if ($ocId > 0) {
            $oc = Ordencompra::query()->with('ordencompra_articulos')->find($ocId);
            $etiqueta = sprintf(
                'Asiento %d OC %s · %s',
                (int) $row->asiento_id,
                $oc ? (string) $oc->numeroordencompra : (string) $ocId,
                substr((string) ($row->observacion ?? ''), 0, 60),
            );

            return [$oc, $etiqueta, $extra];
        }

        $tipo = trim((string) ($row->anita_tipo ?? ''));
        $nro = (string) ($row->anita_nro ?? '');
        $etiqueta = sprintf(
            'Asiento %d %s %s · %s',
            (int) $row->asiento_id,
            $tipo !== '' ? $tipo : '—',
            $nro !== '' ? $nro : '',
            substr((string) ($row->observacion ?? ''), 0, 60),
        );

        return [null, $etiqueta, $extra];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolverPeriodoMes(?string $desde, ?string $hasta): array
    {
        if ($desde && $hasta) {
            return [$desde, $hasta];
        }

        return [
            $desde ?: Carbon::today()->startOfMonth()->toDateString(),
            $hasta ?: Carbon::today()->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $informe
     * @param  array<string, mixed>  $config
     */
    private function enviarMail(array &$informe, array $config, int $maxFilas): void
    {
        $destino = trim((string) (
            $config['email']
            ?? config('comprobante_proveedor_auditoria_erp.email')
            ?? ''
        ));
        if ($destino === '') {
            return;
        }

        $mailSiempre = filter_var($config['mail_siempre'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (! $mailSiempre && ! ($informe['requiere_alerta'] ?? false)) {
            return;
        }

        $informeMail = $informe;
        $informeMail['desvios'] = array_slice($informe['desvios'], 0, $maxFilas);
        $informeMail['desvios_truncados'] = count($informe['desvios']) > $maxFilas;

        try {
            Mail::to($destino)->send(new ComprobanteProveedorAuditorAnticipoBsUsoCapexMail($informeMail));
            $informe['mail_enviado'] = true;
            $informe['mail_destino'] = $destino;
        } catch (Throwable $e) {
            $informe['mail_error'] = $e->getMessage();
            Log::error('auditor_bsuso_capex.mail', ['error' => $e->getMessage()]);
        }
    }
}
