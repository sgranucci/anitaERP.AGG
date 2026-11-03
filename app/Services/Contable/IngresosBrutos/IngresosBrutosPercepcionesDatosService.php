<?php

declare(strict_types=1);

namespace App\Services\Contable\IngresosBrutos;

use App\ApiAnita;
use App\Models\Contable\Iibb_Presentacion_Config;
use App\Models\Configuracion\Provincia;
use App\Models\Ventas\Venta;
use App\Support\Compras\Retencion\AnitaRetencionEsquemaSupport;
use App\Support\Contable\IngresosBrutos\IngresosBrutosFormatoAgipSupport;
use App\Support\Contable\IngresosBrutos\IngresosBrutosFormatoArbaSupport;
use App\Support\Contable\IngresosBrutos\IngresosBrutosProvinciaAnitaSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalMapeosSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use App\Support\Contable\Sicore\SicoreVentaImpuestoSupport;

/**
 * Percepciones IIBB — Anita venibr (opción 8) + ERP venta_impuesto (Perc. Buenos Aires…).
 */
final class IngresosBrutosPercepcionesDatosService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function generar(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        Iibb_Presentacion_Config $config,
        Provincia $provincia,
    ): array {
        $anita = $this->desdeVenibrAnita($empresaId, $fechaDesde, $fechaHasta, $config, $provincia);
        $erp = $this->desdeVentaImpuestoErp($empresaId, $fechaDesde, $fechaHasta, $config, $provincia);

        // Preferir Anita si hay datos; ERP complementa cuando no hay venibr (ventas solo ERP).
        // Las percepciones de gastos bancarios (ICO/IDO) no van en esta liquidación:
        // no están en la cuenta de percepciones a terceros del agente.
        $filas = $anita !== [] ? $anita : $erp;

        // IVA ventas muestra el CUIT de la factura. El archivo de AGIP tiene que
        // usar ese mismo, y la nota de crédito tiene que apuntar a la factura percibida.
        return $this->completarDesdeVentaErp($filas);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function desdeVenibrAnita(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        Iibb_Presentacion_Config $config,
        Provincia $provincia,
    ): array {
        $codigosProv = IngresosBrutosProvinciaAnitaSupport::codigosAnita($provincia);
        if ($codigosProv === []) {
            return [];
        }

        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        $desdeAnita = (int) str_replace('-', '', $fechaDesde);
        $hastaAnita = (int) str_replace('-', '', $fechaHasta);
        $provIn = implode(',', array_map('intval', $codigosProv));
        $filtraEmpresa = AnitaRetencionEsquemaSupport::ventaTieneColumnaEmpresa();

        $campos = [
            'ven_fecha', 'ven_tipo', 'ven_letra', 'ven_sucursal', 'ven_nro',
            'ven_cliente', 'ven_gravado', 'ven_gravado_ot',
            'ven_cod_mon', 'ven_cotizacion',
            'clim_nombre', 'clim_cuit',
            'veni_provincia', 'veni_porcentaje', 'veni_importe',
            'ven_cuit_cli', 'ven_nombre_cliente',
        ];
        if ($filtraEmpresa) {
            $campos[] = 'ven_empresa';
        }
        $where = ' WHERE ven_cliente=clim_cliente'
            .' AND veni_tipo=ven_tipo AND veni_letra=ven_letra'
            .' AND veni_sucursal=ven_sucursal AND veni_nro=ven_nro'
            .' AND ven_fecha >= '.$desdeAnita
            .' AND ven_fecha <= '.$hastaAnita
            .' AND veni_provincia IN ('.$provIn.')'
            .' AND veni_importe <> 0';
        if ($filtraEmpresa) {
            $where .= ' AND ven_empresa = '.$empresaAnita;
        }

        $api = new ApiAnita();
        $filas = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'venta, outer climae, venibr',
            'campos' => implode(', ', $campos),
            'whereArmado' => $where,
            'orderBy' => 'ven_fecha, ven_sucursal, ven_nro',
        ]));

        $out = [];
        $vistos = [];
        foreach ($filas as $fila) {
            $fila = (array) $fila;
            $clave = trim((string) ($fila['ven_tipo'] ?? '')).'|'
                .trim((string) ($fila['ven_letra'] ?? '')).'|'
                .(int) ($fila['ven_sucursal'] ?? 0).'|'
                .(int) ($fila['ven_nro'] ?? 0);
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;

            $importe = round((float) ($fila['veni_importe'] ?? 0), 2);
            if (abs($importe) < 0.001) {
                continue;
            }

            $tipo = strtoupper(trim((string) ($fila['ven_tipo'] ?? '')));
            $signo = (str_starts_with($tipo, 'NC') || str_starts_with($tipo, 'C')) ? -1.0 : 1.0;
            // p-ingbruto.c: calcula_coef('1', ven_cod_mon, ven_cotizacion).
            // En pesos la cotización es el dólar del día y no convierte el gravado ni la percepción.
            $coef = $this->coeficienteMonedaAnita(
                $fila['ven_cod_mon'] ?? 1,
                $fila['ven_cotizacion'] ?? 1,
            );
            $base = round(((float) ($fila['ven_gravado'] ?? 0) + (float) ($fila['ven_gravado_ot'] ?? 0)) * $signo * $coef, 2);
            $importe = round($importe * $signo * $coef, 2);
            $fechaIso = IngresosBrutosFormatoArbaSupport::fechaIsoDesdeAnita((int) ($fila['ven_fecha'] ?? 0));
            $tipoDoc = $this->tipoDocumentoArba($tipo);

            $out[] = [
                'origen' => 'venibr',
                'iibb_config_id' => (int) $config->id,
                'tipo' => 'percepciones',
                'fecha_retencion' => $fechaIso,
                'fecha_comp' => $fechaIso,
                'nro_comp' => (int) ($fila['ven_nro'] ?? 0),
                'nro_cert' => 0,
                'sucursal' => (int) ($fila['ven_sucursal'] ?? 0),
                'letra' => substr(trim((string) ($fila['ven_letra'] ?? ' ')).' ', 0, 1),
                'tipo_documento' => $tipoDoc,
                'base_calculo' => abs($base),
                'importe' => $importe,
                'alicuota' => round((float) ($fila['veni_porcentaje'] ?? 0), 2),
                'nro_documento' => $this->cuitInformado(
                    (string) ($fila['clim_cuit'] ?? ''),
                    (string) ($fila['ven_cuit_cli'] ?? ''),
                ),
                'codigo_proveedor' => trim((string) ($fila['ven_cliente'] ?? '')),
                'razon_social' => $this->razonSocialInformada(
                    (string) ($fila['clim_nombre'] ?? ''),
                    (string) ($fila['ven_nombre_cliente'] ?? ''),
                ),
                'referencia' => sprintf(
                    'Perc.IIBB Anita %s %s %s-%08d',
                    $tipo,
                    (string) ($fila['ven_letra'] ?? ''),
                    str_pad((string) ((int) ($fila['ven_sucursal'] ?? 0)), 4, '0', STR_PAD_LEFT),
                    (int) ($fila['ven_nro'] ?? 0),
                ),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function desdeVentaImpuestoErp(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        Iibb_Presentacion_Config $config,
        Provincia $provincia,
    ): array {
        $nombreProv = trim((string) $provincia->nombre);
        $fragmentos = array_values(array_filter([
            $nombreProv,
            IngresosBrutosProvinciaAnitaSupport::esBuenosAires($provincia) ? 'Buenos Aires' : null,
            IngresosBrutosProvinciaAnitaSupport::esBuenosAires($provincia) ? 'BAI' : null,
            IngresosBrutosProvinciaAnitaSupport::esBuenosAires($provincia) ? 'ARBA' : null,
            IngresosBrutosProvinciaAnitaSupport::esCaba($provincia) ? 'CABA' : null,
            IngresosBrutosProvinciaAnitaSupport::esCaba($provincia) ? 'Capital Federal' : null,
            IngresosBrutosProvinciaAnitaSupport::esCaba($provincia) ? 'AGIP' : null,
        ]));

        $ventas = Venta::query()
            ->select('venta.*')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->where('puntoventa.empresa_id', $empresaId)
            ->whereBetween('venta.fecha', [$fechaDesde, $fechaHasta])
            ->with(['venta_impuestos', 'clientes', 'tipotransacciones', 'puntoventas'])
            ->orderBy('venta.fecha')
            ->orderBy('venta.id')
            ->get();

        $out = [];
        foreach ($ventas as $venta) {
            $signo = SicoreVentaImpuestoSupport::signoVenta($venta);
            $coef = $this->coeficienteMonedaAnita($venta->moneda_id ?? 1, $venta->cotizacion ?? 1);
            foreach ($venta->venta_impuestos as $imp) {
                $concepto = trim((string) $imp->concepto);
                if (! $this->esPercepcionProvincia($concepto, $fragmentos)) {
                    continue;
                }
                $importe = round((float) $imp->importe * $signo * $coef, 2);
                if (abs($importe) < 0.001) {
                    continue;
                }
                $cliente = $venta->clientes;
                $tipoComp = (string) ($venta->tipotransacciones?->codigo ?? '01');
                $tipoDoc = match ($tipoComp) {
                    '02' => 'D',
                    '03' => 'C',
                    default => 'F',
                };
                $alicuota = 0.0;
                if (preg_match('/(\d+(?:[.,]\d+)?)\s*%/', $concepto, $m)) {
                    $alicuota = (float) str_replace(',', '.', $m[1]);
                }

                $out[] = [
                    'origen' => 'venta_impuesto',
                    'iibb_config_id' => (int) $config->id,
                    'tipo' => 'percepciones',
                    'fecha_retencion' => (string) $venta->fecha,
                    'fecha_comp' => (string) $venta->fecha,
                    'nro_comp' => (int) ($venta->numerocomprobante ?? 0),
                    'nro_cert' => 0,
                    'sucursal' => (int) ($venta->puntoventas?->codigo ?? 0),
                    'letra' => 'A',
                    'tipo_documento' => $tipoDoc,
                    'base_calculo' => round(abs((float) ($venta->total ?? 0)) * $coef, 2),
                    'importe' => $importe,
                    'alicuota' => $alicuota,
                    'nro_documento' => $this->cuitInformado(
                        (string) ($cliente?->numerodocumento ?? ''),
                        (string) ($venta->nroinscripcion ?? ''),
                    ),
                    'codigo_proveedor' => (string) ($cliente?->codigo ?? ''),
                    'razon_social' => substr(trim((string) ($cliente?->nombre ?? $venta->nombre ?? '')), 0, 30),
                    'referencia' => sprintf('Perc.IIBB ERP %s', $concepto),
                    'venta_id' => (int) $venta->id,
                ];
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $fragmentos
     */
    private function esPercepcionProvincia(string $concepto, array $fragmentos): bool
    {
        if (stripos($concepto, 'Perc') === false && stripos($concepto, 'Percep') === false) {
            return false;
        }
        if (stripos($concepto, 'IVA') !== false) {
            return false;
        }
        foreach ($fragmentos as $frag) {
            if ($frag !== '' && stripos($concepto, $frag) !== false) {
                return true;
            }
        }

        return stripos($concepto, 'IIBB') !== false || stripos($concepto, 'Ing. Bruto') !== false;
    }

    /**
     * El maestro (climae) manda cuando tiene un CUIT real.
     * El cáscara RECEPTOR EVENTUAL guarda la palabra EVENTUAL: no es CUIT.
     * En ese caso manda el documento de la factura (ven_cuit_cli o nroinscripcion).
     */
    private function cuitInformado(string $maestro, string $comprobante): string
    {
        if ($this->cuitUtil($maestro)) {
            return IngresosBrutosFormatoArbaSupport::normalizarCuit($maestro);
        }
        if ($this->cuitUtil($comprobante)) {
            return IngresosBrutosFormatoArbaSupport::normalizarCuit($comprobante);
        }

        return IngresosBrutosFormatoArbaSupport::normalizarCuit($maestro);
    }

    private function cuitUtil(string $cuit): bool
    {
        $digits = preg_replace('/\D/', '', $cuit) ?? '';

        return strlen($digits) === 11 && $digits !== '00000000000';
    }

    /**
     * El CUIT que muestra IVA ventas está en la venta, no en el cliente cáscara.
     * La nota de crédito de AGIP tiene que citar la factura que percibió.
     *
     * @param  list<array<string, mixed>>  $registros
     * @return list<array<string, mixed>>
     */
    private function completarDesdeVentaErp(array $registros): array
    {
        if ($registros === []) {
            return $registros;
        }

        $nros = [];
        $fechas = [];
        foreach ($registros as $reg) {
            $nro = (int) ($reg['nro_comp'] ?? 0);
            if ($nro > 0) {
                $nros[$nro] = $nro;
            }
            $fecha = substr((string) ($reg['fecha_comp'] ?? ''), 0, 10);
            if ($fecha !== '') {
                $fechas[] = $fecha;
            }
        }
        if ($nros === [] || $fechas === []) {
            return $registros;
        }

        $ventas = Venta::query()
            ->select([
                'venta.id',
                'venta.nombre',
                'venta.total',
                'venta.nroinscripcion',
                'venta.numerocomprobante',
                'venta.fecha',
                'venta.codigo',
                'venta.puntoventa_id',
                'venta.venta_origen_id',
                'puntoventa.codigo as pv_codigo',
            ])
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->whereBetween('venta.fecha', [min($fechas), max($fechas)])
            ->whereIn('venta.numerocomprobante', array_values($nros))
            ->get();

        $indice = [];
        foreach ($ventas as $venta) {
            $indice[$this->claveVenta($venta)] = $venta;
        }

        foreach ($registros as $i => $reg) {
            $clave = $this->claveRegistro($reg);
            $venta = $indice[$clave] ?? null;
            if (! $venta instanceof Venta) {
                continue;
            }

            $cuit = IngresosBrutosFormatoArbaSupport::normalizarCuit((string) ($venta->nroinscripcion ?? ''));
            if ($this->cuitUtil($cuit)) {
                $registros[$i]['nro_documento'] = $cuit;
            }

            if (! IngresosBrutosFormatoAgipSupport::esNotaCredito($reg)) {
                continue;
            }

            $origen = $this->facturaOrigen($venta);
            if ($origen === null) {
                continue;
            }

            $registros[$i]['origen_sucursal'] = (int) preg_replace('/\D/', '', (string) ($origen->pv_codigo ?? ''));
            $registros[$i]['origen_nro'] = (int) $origen->numerocomprobante;
            $registros[$i]['origen_fecha'] = substr((string) $origen->fecha, 0, 10);
        }

        return $registros;
    }

    private function claveVenta(Venta $venta): string
    {
        $sucursal = (int) preg_replace('/\D/', '', (string) ($venta->pv_codigo ?? ''));
        $letra = LibroIvaDigitalMapeosSupport::letraDesdeCodigoVenta((string) ($venta->codigo ?? ''));
        $tipo = $this->tipoDocumentoDesdeCodigoVenta((string) ($venta->codigo ?? ''));

        return $tipo.'|'.$letra.'|'.$sucursal.'|'.(int) $venta->numerocomprobante.'|'.substr((string) $venta->fecha, 0, 10);
    }

    /**
     * @param  array<string, mixed>  $reg
     */
    private function claveRegistro(array $reg): string
    {
        return strtoupper(substr((string) ($reg['tipo_documento'] ?? 'F'), 0, 1))
            .'|'.strtoupper(substr(trim((string) ($reg['letra'] ?? '')).' ', 0, 1))
            .'|'.(int) ($reg['sucursal'] ?? 0)
            .'|'.(int) ($reg['nro_comp'] ?? 0)
            .'|'.substr((string) ($reg['fecha_comp'] ?? ''), 0, 10);
    }

    /**
     * Primero el comprobante aplicado. Si la nota no lo tiene, la factura del
     * mismo punto de venta, letra e importe (la 155 de Blinken no quedó enlazada).
     */
    private function facturaOrigen(Venta $nc): ?Venta
    {
        $origenId = (int) ($nc->venta_origen_id ?? 0);
        if ($origenId > 0) {
            $origen = $this->ventaConPuntoVenta($origenId);
            if ($origen !== null) {
                return $origen;
            }
        }

        return $this->facturaOrigenPorImporte($nc);
    }

    private function ventaConPuntoVenta(int $ventaId): ?Venta
    {
        $venta = Venta::query()
            ->select([
                'venta.id',
                'venta.nombre',
                'venta.total',
                'venta.nroinscripcion',
                'venta.numerocomprobante',
                'venta.fecha',
                'venta.codigo',
                'venta.puntoventa_id',
                'puntoventa.codigo as pv_codigo',
            ])
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->where('venta.id', $ventaId)
            ->first();

        return $venta instanceof Venta ? $venta : null;
    }

    private function facturaOrigenPorImporte(Venta $nc): ?Venta
    {
        $total = round(abs((float) $nc->total), 2);
        $fecha = substr((string) $nc->fecha, 0, 10);
        $puntoventaId = (int) ($nc->puntoventa_id ?? 0);
        if ($total < 0.01 || $fecha === '' || $puntoventaId <= 0) {
            return null;
        }

        $tsDesde = strtotime($fecha.' -120 days');
        $tsHasta = strtotime($fecha.' +60 days');
        if ($tsDesde === false || $tsHasta === false) {
            return null;
        }
        $desde = date('Y-m-d', $tsDesde);
        $hasta = date('Y-m-d', $tsHasta);
        $letraNc = LibroIvaDigitalMapeosSupport::letraDesdeCodigoVenta((string) ($nc->codigo ?? ''));
        $nombreNc = $this->nombreClave((string) ($nc->nombre ?? ''));

        $candidatos = Venta::query()
            ->select([
                'venta.id',
                'venta.nombre',
                'venta.total',
                'venta.nroinscripcion',
                'venta.numerocomprobante',
                'venta.fecha',
                'venta.codigo',
                'venta.puntoventa_id',
                'puntoventa.codigo as pv_codigo',
            ])
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->where('venta.puntoventa_id', $puntoventaId)
            ->where('venta.id', '!=', (int) $nc->id)
            ->where('venta.total', '>', 0)
            ->whereBetween('venta.fecha', [$desde, $hasta])
            ->whereRaw('ABS(venta.total - ?) < 0.05', [$total])
            ->get();

        $mismaLetra = [];
        $mismoNombre = [];
        foreach ($candidatos as $candidato) {
            if (! $candidato instanceof Venta) {
                continue;
            }
            if ($this->tipoDocumentoDesdeCodigoVenta((string) $candidato->codigo) !== 'F') {
                continue;
            }
            if (LibroIvaDigitalMapeosSupport::letraDesdeCodigoVenta((string) $candidato->codigo) !== $letraNc) {
                continue;
            }
            $mismaLetra[] = $candidato;
            if ($nombreNc !== '' && $this->nombreClave((string) $candidato->nombre) === $nombreNc) {
                $mismoNombre[] = $candidato;
            }
        }

        $pool = $mismoNombre !== [] ? $mismoNombre : $mismaLetra;
        if ($pool === []) {
            return null;
        }
        if (count($pool) === 1) {
            return $pool[0];
        }

        usort($pool, function (Venta $a, Venta $b) use ($fecha): int {
            $da = abs((int) strtotime(substr((string) $a->fecha, 0, 10)) - (int) strtotime($fecha));
            $db = abs((int) strtotime(substr((string) $b->fecha, 0, 10)) - (int) strtotime($fecha));

            return $da <=> $db;
        });

        $fechaA = substr((string) $pool[0]->fecha, 0, 10);
        $fechaB = substr((string) $pool[1]->fecha, 0, 10);
        $distA = abs((int) strtotime($fechaA) - (int) strtotime($fecha));
        $distB = abs((int) strtotime($fechaB) - (int) strtotime($fecha));

        return $distA < $distB ? $pool[0] : null;
    }

    private function nombreClave(string $nombre): string
    {
        $nombre = strtoupper(trim($nombre));
        $nombre = preg_replace('/[^A-Z0-9 ]/', ' ', $nombre) ?? $nombre;
        $nombre = preg_replace('/\s+/', ' ', $nombre) ?? $nombre;

        return trim($nombre);
    }

    private function tipoDocumentoDesdeCodigoVenta(string $codigo): string
    {
        $codigo = strtoupper(trim($codigo));
        if (str_starts_with($codigo, 'NC')) {
            return 'C';
        }
        if (str_starts_with($codigo, 'ND')) {
            return 'D';
        }

        return 'F';
    }

    /**
     * Misma prioridad que el CUIT: ficha del cliente y, si no hay, el nombre de la factura.
     */
    private function razonSocialInformada(string $maestro, string $comprobante): string
    {
        $maestro = trim($maestro);
        $nombre = $maestro !== '' ? $maestro : trim($comprobante);

        return substr($nombre, 0, 30);
    }

    /**
     * Misma moneda que el peso: coeficiente 1. Moneda extranjera: la cotización del comprobante.
     */
    private function coeficienteMonedaAnita(mixed $codigoMoneda, mixed $cotizacion): float
    {
        $moneda = (int) $codigoMoneda;
        $cot = (float) $cotizacion;

        return calculaCoeficienteMoneda(1, $moneda > 0 ? $moneda : 1, $cot > 0 ? $cot : 1.0);
    }

    private function tipoDocumentoArba(string $tipoCompAnita): string
    {
        $t = strtoupper(trim($tipoCompAnita));
        if (str_starts_with($t, 'ND') || $t === '02') {
            return 'D';
        }
        if (str_starts_with($t, 'NC') || $t === '03') {
            return 'C';
        }

        return 'F';
    }
}
