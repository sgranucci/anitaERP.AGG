<?php

namespace App\Support\Compras;

use App\ApiAnita;
use App\Support\Compras\AnitaImport\ProveedorCuentacorrienteAnitaImportFormatoSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;

/**
 * Suma promov Anita (cabecera de OP) por tipo + número + empresa.
 */
final class PagoproveedorImputacionApPromovSupport
{
    public function __construct(
        private readonly ApiAnita $api = new ApiAnita(),
    ) {}

    /**
     * @param  list<array{empresa_anita:int, tipo:string, numero:int, sucursal:int, fecha:?string, signo:int, moneda_id?:int, cotizacion?:mixed}>  $claves
     * @return array<string, array{ars: float, monto: float, lineas: int, encontrado: bool}>
     */
    public function sumarPorOp(array $claves): array
    {
        $out = [];
        $porEmpresaTipo = [];
        foreach ($claves as $clave) {
            $empresa = (int) ($clave['empresa_anita'] ?? 0);
            $tipo = PagoproveedorImputacionApSupport::tipoDesdeComprobante((string) ($clave['tipo'] ?? ''));
            $nro = (int) ($clave['numero'] ?? 0);
            if ($empresa <= 0 || $nro <= 0) {
                continue;
            }
            $key = self::clave($empresa, $tipo, $nro);
            $out[$key] = ['ars' => 0.0, 'monto' => 0.0, 'lineas' => 0, 'encontrado' => false];
            $porEmpresaTipo[$empresa.'|'.$tipo][$nro] = [
                'fecha' => (string) ($clave['fecha'] ?? ''),
                'signo' => (int) ($clave['signo'] ?? PagoproveedorImputacionApSupport::signoHaberNeto($tipo)),
                'sucursal' => (int) ($clave['sucursal'] ?? 0),
                'moneda_id' => (int) ($clave['moneda_id'] ?? 1),
                'cotizacion' => $clave['cotizacion'] ?? 1,
            ];
        }

        if ($porEmpresaTipo === []) {
            return $out;
        }

        $perfil = ProveedorCuentacorrienteAnitaImportFormatoSupport::perfil();
        $tieneEmpresa = ! empty($perfil['tiene_empresa']);

        foreach ($porEmpresaTipo as $grupo => $ops) {
            [$empresaRaw, $tipo] = explode('|', $grupo, 2);
            $empresa = (int) $empresaRaw;
            $nros = array_map('intval', array_keys($ops));
            foreach (array_chunk($nros, 40) as $lote) {
                foreach ($this->listarLote($empresa, $tipo, $lote, $perfil, $tieneEmpresa) as $linea) {
                    $nro = (int) ($linea->prov_nro ?? 0);
                    $key = self::clave($empresa, $tipo, $nro);
                    if (! isset($out[$key], $ops[$nro])) {
                        continue;
                    }

                    $monto = abs((float) ($linea->prov_monto ?? 0));
                    $fecha = $ops[$nro]['fecha'] ?? '';
                    $signo = (int) ($ops[$nro]['signo'] ?? -1);
                    $monedaId = (int) ($ops[$nro]['moneda_id'] ?? 1);
                    $cotizacion = $ops[$nro]['cotizacion'] ?? 1;
                    if ($monedaId <= 1) {
                        $cotizacion = 1;
                    }
                    $ars = ComprobanteProveedorImputacionApSupport::aPesosTolerante(
                        $signo * $monto,
                        $monedaId,
                        $cotizacion,
                        $fecha !== '' ? $fecha : null,
                        'promov '.$tipo.' '.$nro
                    );

                    $out[$key]['monto'] = round($out[$key]['monto'] + ($signo * $monto), 2);
                    $out[$key]['ars'] = round($out[$key]['ars'] + $ars, 2);
                    $out[$key]['lineas']++;
                    $out[$key]['encontrado'] = true;
                }
            }
        }

        return $out;
    }

    public static function clave(int $empresaAnita, string $tipo, int $numero): string
    {
        return $empresaAnita.':'.PagoproveedorImputacionApSupport::tipoDesdeComprobante($tipo).':'.$numero;
    }

    public static function empresaAnitaDePago(int $empresaId): int
    {
        $codigo = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);

        return $codigo > 0 ? $codigo : $empresaId;
    }

    /**
     * @param  list<int>  $nros
     * @param  array<string, mixed>  $perfil
     * @return list<object>
     */
    private function listarLote(int $empresaAnita, string $tipo, array $nros, array $perfil, bool $tieneEmpresa): array
    {
        $nros = array_values(array_filter(array_map('intval', $nros), static fn (int $n) => $n > 0));
        $tipo = PagoproveedorImputacionApSupport::tipoDesdeComprobante($tipo);
        if ($empresaAnita <= 0 || $nros === [] || $tipo === '') {
            return [];
        }

        $where = ' WHERE prov_tipo = \''.$this->esc($tipo).'\''
            .' AND prov_nro IN ('.implode(',', $nros).')';
        if ($tieneEmpresa) {
            $where .= ' AND prov_empresa = '.$empresaAnita;
        }

        $payload = [
            'acc' => 'list',
            'sistema' => (string) ($perfil['sistema'] ?? 'compras'),
            'tabla' => (string) ($perfil['tabla_promov'] ?? 'promov'),
            'campos' => 'prov_tipo,prov_letra,prov_sucursal,prov_nro,prov_monto,prov_cotizacion,prov_cod_mon,prov_nro_cuota'
                .($tieneEmpresa ? ',prov_empresa' : ''),
            'whereArmado' => $where,
        ];

        $intentos = max(1, (int) config('pagoproveedor.imputacion_ap_diaria.anita_reintentos_bridge', 3));
        for ($i = 1; $i <= $intentos; $i++) {
            $raw = $this->api->apiCall($payload);
            if (ApiAnita::extraerMensajeError($raw) === null) {
                $filas = ApiAnita::decodificarListaFilas($raw);
                if ($filas !== [] || $i === $intentos) {
                    return $filas;
                }
            } elseif ($i === $intentos) {
                return [];
            }
            usleep(200000);
        }

        return [];
    }

    private function esc(string $valor): string
    {
        return str_replace("'", "''", $valor);
    }
}
