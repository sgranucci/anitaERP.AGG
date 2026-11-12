<?php

declare(strict_types=1);

namespace App\Services\Contable\PercepcionSufrida;

use App\ApiAnita;
use App\Support\Contable\PercepcionSufrida\PercepcionSufridaLineaSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Retenciones IIBB sufridas en cobranzas Anita (auxpag).
 *
 * Crown nativo: axp_tipo_ap = RIC.
 * Sync ERP → Anita: R01/R02… (R + últimos 2 dígitos de la jurisdicción 901/902).
 */
final class SifereRetencionAnitaReader
{
    public function __construct(
        private readonly ApiAnita $api = new ApiAnita,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function leer(string $desde, string $hasta): array
    {
        if ($desde === '' || $hasta === '' || $desde > $hasta) {
            return [];
        }

        $desdeYmd = (int) str_replace('-', '', $desde);
        $hastaYmd = (int) str_replace('-', '', $hasta);
        $tipos = $this->tiposAnita();
        if ($tipos === []) {
            return [];
        }

        $condiciones = [];
        foreach ($tipos as $tipo) {
            $t = addslashes($tipo);
            $condiciones[] = "axp_tipo_ap MATCHES '".$t."*'";
        }
        $sql = 'SELECT axp_pro p, axp_fecha f, axp_rec r, axp_tipo t, axp_nro n,'
            .' axp_tipo_ap ap, axp_monto_ap m, axp_sucursal s'
            .' FROM auxpag'
            .' WHERE axp_fecha BETWEEN '.$desdeYmd.' AND '.$hastaYmd
            .' AND ('.implode(' OR ', $condiciones).')';

        try {
            $raw = $this->api->apiCall([
                'acc' => 'customSql',
                'sistema' => 'che_ban',
                'campos' => 'p,f,r,t,n,ap,m,s',
                'customSql' => $sql,
            ]);
        } catch (\Throwable $e) {
            Log::warning('sifere.retencion.anita.exception', ['message' => $e->getMessage()]);

            return [];
        }

        $err = ApiAnita::extraerMensajeError(is_string($raw) ? $raw : null);
        if ($err !== null) {
            Log::warning('sifere.retencion.anita.error', ['error' => $err]);

            return [];
        }

        $rows = json_decode(is_string($raw) ? $raw : '[]');
        if (! is_array($rows)) {
            return [];
        }

        $clientes = $this->resolverClientes(array_map(
            static fn ($r) => trim((string) ($r->p ?? '')),
            $rows,
        ));

        $out = [];
        foreach ($rows as $fila) {
            $pro = trim((string) ($fila->p ?? ''));
            $cliente = $clientes[$this->codigoClave($pro)] ?? null;
            $cuit = PercepcionSufridaLineaSupport::cuit11((string) ($cliente['cuit'] ?? ''));
            if ($cuit === '') {
                continue;
            }
            $importe = round((float) ($fila->m ?? 0), 2);
            if (abs($importe) < 0.009) {
                continue;
            }
            $fechaYmd = (string) ($fila->f ?? '');
            $fecha = $this->ymdAIso($fechaYmd);
            if ($fecha === '') {
                continue;
            }
            $tipoAp = strtoupper(trim((string) ($fila->ap ?? '')));
            $jur = $this->jurisdiccionDesdeTipoAp($tipoAp);
            if ($jur <= 0) {
                $jur = (int) ($cliente['jurisdiccion'] ?? 0);
            }
            if ($jur <= 0) {
                $jur = 902;
            }

            $nroRet = (int) ($fila->n ?? 0);
            $nroRecibo = (int) ($fila->r ?? 0);
            if ($nroRet <= 0) {
                $nroRet = $nroRecibo;
            }

            $out[] = [
                'fecha' => $fecha,
                'cuit' => $cuit,
                'emisor' => ltrim($pro, '0') !== '' ? ltrim($pro, '0') : $pro,
                'emisor_nombre' => (string) ($cliente['nombre'] ?? ''),
                'descripcion' => 'Retención IIBB clientes ('.$tipoAp.')',
                'jurisdiccion' => $jur,
                'sucursal' => max(0, (int) ($fila->s ?? 0)),
                'nro_retencion' => $nroRet,
                'tipo_documento' => 'R',
                'tipo' => 'R',
                'letra' => ' ',
                'nro_comprobante' => $nroRecibo,
                'nro' => $nroRecibo,
                'importe' => $importe,
                'importe_nativo' => $importe,
                'importe_pesos' => $importe,
                'moneda_id' => 1,
                'cotizacion' => 1.0,
                'origen' => 'anita',
                'comprobante' => sprintf('R %08d', $nroRecibo),
                'tipo_ap' => $tipoAp,
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function tiposAnita(): array
    {
        $cfg = config('contable.percepcion_sufrida.tipos_retencion_anita', ['RIC']);
        if (! is_array($cfg) || $cfg === []) {
            return ['RIC'];
        }
        $out = [];
        foreach ($cfg as $tipo) {
            $t = strtoupper(substr(trim((string) $tipo), 0, 3));
            if ($t !== '') {
                $out[$t] = $t;
            }
        }

        return array_values($out);
    }

    /**
     * R01 → 901, R02 → 902. RIC / otros → 0 (se completa con provincia del cliente).
     */
    private function jurisdiccionDesdeTipoAp(string $tipoAp): int
    {
        if (preg_match('/^R(\d{2})$/', $tipoAp, $m) === 1) {
            $sufijo = (int) $m[1];
            if ($sufijo >= 1 && $sufijo <= 24) {
                return 900 + $sufijo;
            }
        }

        return match ($tipoAp) {
            'RIB' => 901,
            'RBB' => 902,
            'RCO' => 904,
            'RTF' => 923,
            default => 0,
        };
    }

    /**
     * @param  list<string>  $codigosAnita
     * @return array<string, array{cuit: string, nombre: string, jurisdiccion: int}>
     */
    private function resolverClientes(array $codigosAnita): array
    {
        $claves = [];
        foreach ($codigosAnita as $codigo) {
            $clave = $this->codigoClave($codigo);
            if ($clave !== '') {
                $claves[$clave] = $clave;
            }
        }
        if ($claves === []) {
            return [];
        }

        $out = [];
        $codigosBusqueda = array_values(array_unique(array_merge(
            array_values($claves),
            array_map(
                static fn (string $c): string => str_pad($c, 6, '0', STR_PAD_LEFT),
                array_values($claves),
            ),
        )));
        $erp = DB::table('cliente as c')
            ->leftJoin('provincia as p', 'p.id', '=', 'c.provincia_id')
            ->whereIn('c.codigo', $codigosBusqueda)
            ->get([
                'c.codigo',
                'c.nombre',
                'c.numerodocumento',
                'p.jurisdiccion',
            ]);

        foreach ($erp as $fila) {
            $clave = $this->codigoClave((string) $fila->codigo);
            if ($clave === '' || isset($out[$clave])) {
                continue;
            }
            $out[$clave] = [
                'cuit' => (string) ($fila->numerodocumento ?? ''),
                'nombre' => (string) ($fila->nombre ?? ''),
                'jurisdiccion' => (int) ($fila->jurisdiccion ?? 0),
            ];
        }

        $faltan = array_diff_key($claves, $out);
        foreach ($faltan as $clave) {
            $padded = str_pad($clave, 6, '0', STR_PAD_LEFT);
            $sql = "SELECT clim_cliente c, clim_nombre n, clim_cuit cu FROM climae"
                ." WHERE clim_cliente MATCHES '".$padded."*'";
            try {
                $raw = $this->api->apiCall([
                    'acc' => 'customSql',
                    'sistema' => 'ventas',
                    'campos' => 'c,n,cu',
                    'customSql' => $sql,
                ]);
            } catch (\Throwable) {
                continue;
            }
            $rows = json_decode(is_string($raw) ? $raw : '[]');
            if (! is_array($rows) || $rows === []) {
                continue;
            }
            $hit = $rows[0];
            $out[$clave] = [
                'cuit' => (string) ($hit->cu ?? ''),
                'nombre' => (string) ($hit->n ?? ''),
                'jurisdiccion' => 0,
            ];
        }

        return $out;
    }

    private function codigoClave(string $codigo): string
    {
        $trim = ltrim(trim($codigo), '0');

        return $trim === '' ? '' : $trim;
    }

    private function ymdAIso(string $ymd): string
    {
        if (preg_match('/^\d{8}$/', $ymd) !== 1) {
            return '';
        }

        return substr($ymd, 0, 4).'-'.substr($ymd, 4, 2).'-'.substr($ymd, 6, 2);
    }
}
