<?php

declare(strict_types=1);

namespace App\Services\Contable\PercepcionSufrida;

use App\ApiAnita;
use App\Support\Contable\Anita\AnitaSubdiarioMayorSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaEmisorSupport;
use App\Support\Contable\PercepcionSufrida\PercepcionSufridaLineaSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;

/**
 * Mayor de la cuenta de percepción sufrida desde Anita (ctamov + subdiario).
 */
final class PercepcionSufridaMayorAnitaReader
{
    private const CTAMOV_CAMPOS = 'ctav_fecha,ctav_tipo,ctav_letra,ctav_sucursal,ctav_nro,ctav_cuenta,ctav_d_h,ctav_importe,ctav_cotizacion,ctav_cod_mon,ctav_sistema,ctav_desc_mov';

    private const SUBDIARIO_CAMPOS = 'subd_fecha,subd_tipo,subd_letra,subd_sucursal,subd_nro,subd_emisor,subd_tipo_mov,'
        .'subd_cuenta,subd_contrapartida,subd_importe,subd_sistema,subd_ref_tipo,subd_ref_letra,subd_ref_sucursal,subd_ref_nro,subd_cod_mon,subd_cotizacion,subd_desc_mov';

    public function __construct(
        private readonly ApiAnita $api = new ApiAnita(),
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function leer(int $empresaId, string $fechaDesde, string $fechaHasta, int $cuenta): array
    {
        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        $desde = (int) str_replace('-', '', $fechaDesde);
        $hasta = (int) str_replace('-', '', $fechaHasta);
        if ($empresaAnita <= 0 || $cuenta <= 0 || $desde <= 0 || $hasta < $desde) {
            return [];
        }

        $ctamov = $this->listar(
            'ctamov',
            self::CTAMOV_CAMPOS,
            ' WHERE ctav_empresa='.$empresaAnita
            .' AND ctav_cuenta='.$cuenta
            .' AND ctav_fecha BETWEEN '.$desde.' AND '.$hasta,
            'ctav_fecha, ctav_nro',
        );
        $subdiario = $this->listar(
            'subdiario',
            self::SUBDIARIO_CAMPOS,
            ' WHERE subd_empresa='.$empresaAnita
            .' AND subd_fecha BETWEEN '.$desde.' AND '.$hasta
            .' AND (subd_cuenta='.$cuenta.' OR subd_contrapartida='.$cuenta.')',
            'subd_fecha, subd_nro',
        );

        $out = [];
        foreach ($ctamov as $fila) {
            $imp = AnitaSubdiarioMayorSupport::imputacionLineaCtamov($fila);
            if ($imp === null || (int) $imp['cuenta'] !== $cuenta) {
                continue;
            }
            $dh = AnitaSubdiarioMayorSupport::debeHaberDesdeDh((string) $imp['dh'], (float) $imp['importe']);
            $importe = round((float) ($dh['debe'] ?? 0) - (float) ($dh['haber'] ?? 0), 2);
            if (abs($importe) < 0.009) {
                continue;
            }
            $tipo = strtoupper(trim((string) ($fila->ctav_tipo ?? '')));
            $sistema = strtoupper(trim((string) ($fila->ctav_sistema ?? '')));
            $desc = trim((string) ($fila->ctav_desc_mov ?? ''));
            $emisor = MayorPlanoCuentaEmisorSupport::resolver($sistema, $tipo, '', $desc);
            $out[] = PercepcionSufridaLineaSupport::armar([
                'fecha' => $this->fecha((int) ($fila->ctav_fecha ?? 0)),
                'tipo' => $tipo,
                'letra' => (string) ($fila->ctav_letra ?? ''),
                'sucursal' => (int) ($fila->ctav_sucursal ?? 0),
                'nro' => (int) ($fila->ctav_nro ?? 0),
                'emisor' => $emisor['entidad'] === MayorPlanoCuentaEmisorSupport::ENTIDAD_PROVEEDOR ? $emisor['codigo'] : '',
                'descripcion' => $desc,
                'importe' => $importe,
                'origen' => 'anita_ctamov',
                'cod_mon' => trim((string) ($fila->ctav_cod_mon ?? '')),
                'cotizacion' => (float) ($fila->ctav_cotizacion ?? 0),
            ]);
        }

        foreach ($subdiario as $fila) {
            foreach (AnitaSubdiarioMayorSupport::imputacionesLineaSubdiario($fila) as $imp) {
                if ((int) $imp['cuenta'] !== $cuenta) {
                    continue;
                }
                $dh = AnitaSubdiarioMayorSupport::debeHaberDesdeDh((string) $imp['dh'], (float) $imp['importe']);
                $importe = round((float) ($dh['debe'] ?? 0) - (float) ($dh['haber'] ?? 0), 2);
                if (abs($importe) < 0.009) {
                    continue;
                }
                $subdTipo = trim((string) ($fila->subd_tipo ?? ''));
                $tipo = strtoupper(substr($subdTipo, 0, 3)) === 'AOP'
                    ? $subdTipo
                    : trim((string) ($fila->subd_ref_tipo ?? $subdTipo));
                $tipo = strtoupper(substr($tipo, 0, 3));
                $sistema = strtoupper(trim((string) ($fila->subd_sistema ?? '')));
                $desc = trim((string) ($fila->subd_desc_mov ?? ''));
                $emisor = MayorPlanoCuentaEmisorSupport::resolver(
                    $sistema,
                    $tipo,
                    (string) ($fila->subd_emisor ?? ''),
                    $desc,
                );
                $out[] = PercepcionSufridaLineaSupport::armar([
                    'fecha' => $this->fecha((int) ($fila->subd_fecha ?? 0)),
                    'tipo' => $tipo,
                    'letra' => (string) ($fila->subd_ref_letra ?? $fila->subd_letra ?? ''),
                    'sucursal' => (int) ($fila->subd_ref_sucursal ?? $fila->subd_sucursal ?? 0),
                    'nro' => (int) ($fila->subd_ref_nro ?? $fila->subd_nro ?? 0),
                    'emisor' => $emisor['entidad'] === MayorPlanoCuentaEmisorSupport::ENTIDAD_PROVEEDOR ? $emisor['codigo'] : '',
                    'descripcion' => $desc,
                    'importe' => $importe,
                    'origen' => 'anita_subdiario',
                    'cod_mon' => trim((string) ($fila->subd_cod_mon ?? '')),
                    'cotizacion' => (float) ($fila->subd_cotizacion ?? 0),
                ]);
            }
        }

        return $this->sinDuplicar($out);
    }

    /**
     * La misma imputación puede venir en ctamov y en subdiario. Se queda la que trae emisor.
     *
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function sinDuplicar(array $lineas): array
    {
        $porClave = [];
        foreach ($lineas as $linea) {
            $clave = (string) ($linea['clave_importe'] ?? '');
            if ($clave === '' || $clave === '|0.00') {
                continue;
            }
            $prev = $porClave[$clave] ?? null;
            if ($prev === null || ($prev['emisor'] === '' && $linea['emisor'] !== '')) {
                $porClave[$clave] = $linea;
            }
        }

        $out = array_values($porClave);
        usort($out, static function (array $a, array $b): int {
            return [$a['fecha'], $a['comprobante'], $a['importe']]
                <=> [$b['fecha'], $b['comprobante'], $b['importe']];
        });

        return $out;
    }

    /**
     * @return list<object>
     */
    private function listar(string $tabla, string $campos, string $where, string $orderBy): array
    {
        $raw = $this->api->apiCall([
            'acc' => 'list',
            'sistema' => 'contab',
            'tabla' => $tabla,
            'campos' => $campos,
            'whereArmado' => $where,
            'orderBy' => $orderBy,
        ]);
        if (ApiAnita::extraerMensajeError($raw) !== null) {
            return [];
        }

        return ApiAnita::decodificarListaFilas($raw);
    }

    private function fecha(int $ymd): string
    {
        if ($ymd <= 0) {
            return '';
        }
        $s = str_pad((string) $ymd, 8, '0', STR_PAD_LEFT);

        return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
    }
}
