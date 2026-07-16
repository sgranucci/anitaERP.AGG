<?php

namespace App\Services\Ventas\CotElectronico;

use App\ApiAnita;
use App\Models\Configuracion\Empresa;
use App\Models\Stock\Articulo;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\CotRemitoEnvio;
use App\Models\Ventas\Remito;
use App\Support\Ventas\RemitoEstadosSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Listado COT alineado a p-cot.c:
 * 1) Anita comprob (comp_remito > 0 + comp_transporte = reparto), salvo si ya hay REM físico.
 * 2) Anita pendmae tipo REM del día por penm_expreso (reparto), excluye penm_ref_tipo = 'Z  '.
 * 3) Remitos anitaERP que no estén ya cubiertos por Anita (misma clave REM|letra|sucursal|numero).
 */
class CotRemitoConsultaService
{
    /**
     * @param  list<array{transporte_id:int,codigo:string,nombre:string,patente:?string,cuit_chofer:?string}>  $repartos
     * @return list<array<string, mixed>>
     */
    public function listarRemitosDelDia(Carbon $fecha, array $repartos): array
    {
        $transporteIds = collect($repartos)
            ->pluck('transporte_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($transporteIds === []) {
            return [];
        }

        $repartosPorId = collect($repartos)->keyBy('transporte_id');
        $codigosReparto = collect($repartos)
            ->map(fn ($r) => (int) preg_replace('/\D+/', '', (string) ($r['codigo'] ?? '')))
            ->filter(fn ($c) => $c > 0)
            ->unique()
            ->values()
            ->all();
        $repartosPorCodigo = collect($repartos)->keyBy(
            fn ($r) => (int) preg_replace('/\D+/', '', (string) ($r['codigo'] ?? ''))
        );

        $filas = [];
        $claves = [];

        // 1 y 2: Anita bridge (mientras haya datos allí), como p-cot.c
        foreach ($this->listarDesdeAnita($fecha, $codigosReparto, $repartosPorCodigo) as $fila) {
            $clave = (string) ($fila['clave'] ?? '');
            if ($clave === '' || isset($claves[$clave])) {
                continue;
            }
            $claves[$clave] = true;
            $filas[] = $fila;
        }

        // 3: remitos anitaERP que no están en Anita
        foreach ($this->listarDesdeRemitoErp($fecha, $transporteIds, $repartosPorId) as $fila) {
            $clave = (string) ($fila['clave'] ?? '');
            if ($clave === '' || isset($claves[$clave])) {
                continue;
            }
            $claves[$clave] = true;
            $filas[] = $fila;
        }

        usort($filas, fn ($a, $b) => ($a['numero_remito'] <=> $b['numero_remito']));

        return $filas;
    }

    /**
     * @param  list<int>  $codigosReparto
     * @param  Collection<int|string, array<string, mixed>>  $repartosPorCodigo
     * @return list<array<string, mixed>>
     */
    private function listarDesdeAnita(Carbon $fecha, array $codigosReparto, Collection $repartosPorCodigo): array
    {
        if ($codigosReparto === []) {
            return [];
        }

        $fechaAnita = (int) $fecha->format('Ymd');
        $remitosPendmae = $this->cargarPendmaeRemDelDia($fechaAnita, $codigosReparto);
        $clavesPendmae = [];
        foreach ($remitosPendmae as $penm) {
            $clavesPendmae[$this->claveRemito(
                'REM',
                trim((string) ($penm->penm_letra ?? 'R')) ?: 'R',
                (int) ($penm->penm_sucursal ?? 1),
                (int) ($penm->penm_nro ?? 0),
            )] = true;
        }

        $filas = [];

        // p-cot: primero facturas con remito en comprob
        foreach ($this->cargarComprobConRemito($fechaAnita, $codigosReparto) as $comp) {
            $numeroRemito = (int) ($comp->comp_remito ?? 0);
            if ($numeroRemito <= 0) {
                continue;
            }

            // p-cot un_comprobante: si existe REM R suc=1 nro=comp_remito en pendmae, lo saltea
            // (el físico se lista después). Misma clave para deduplicar.
            $claveFisica = $this->claveRemito('REM', 'R', 1, $numeroRemito);
            if (isset($clavesPendmae[$claveFisica])) {
                continue;
            }

            $fila = $this->mapearFilaDesdeAnitaComprob($comp, $repartosPorCodigo, $fecha);
            if ($fila !== null) {
                $filas[] = $fila;
            }
        }

        // p-cot: luego remitos físicos pendmae REM
        foreach ($remitosPendmae as $penm) {
            $fila = $this->mapearFilaDesdeAnitaPendmae($penm, $repartosPorCodigo, $fecha);
            if ($fila !== null) {
                $filas[] = $fila;
            }
        }

        return $filas;
    }

    /**
     * @param  list<int>  $codigosReparto
     * @return list<object>
     */
    private function cargarComprobConRemito(int $fechaAnita, array $codigosReparto): array
    {
        $codigosSql = implode(',', array_map('intval', $codigosReparto));
        $api = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'comprob',
            'campos' => '
                comp_cliente, comp_tipo, comp_letra, comp_sucursal, comp_nro_fact,
                comp_remito, comp_fecha, comp_transporte, comp_o_compra, comp_total,
                comp_iva, comp_exento, comp_gravado, comp_leyenda
            ',
            'whereArmado' => ' WHERE comp_fecha = '.$fechaAnita
                .' AND comp_remito > 0'
                .' AND comp_transporte IN ('.$codigosSql.') ',
        ];

        $parseado = ApiAnita::parsearRespuestaLista($api->apiCall($data));
        if ($parseado['error_lectura'] !== null) {
            Log::warning('cot_electronico.anita_comprob', ['mensaje' => $parseado['error_lectura']]);

            return [];
        }

        return $parseado['filas'];
    }

    /**
     * @param  list<int>  $codigosReparto
     * @return list<object>
     */
    private function cargarPendmaeRemDelDia(int $fechaAnita, array $codigosReparto): array
    {
        $codigosSql = implode(',', array_map('intval', $codigosReparto));
        $api = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'pendmae',
            'campos' => '
                penm_tipo, penm_letra, penm_sucursal, penm_nro, penm_cliente,
                penm_fecha, penm_expreso, penm_ref_tipo, penm_neto, penm_tot_seguro
            ',
            'whereArmado' => " WHERE penm_tipo = 'REM' AND penm_fecha = ".$fechaAnita
                .' AND penm_expreso IN ('.$codigosSql.') '
                ." AND penm_ref_tipo <> 'Z  ' ",
        ];

        $parseado = ApiAnita::parsearRespuestaLista($api->apiCall($data));
        if ($parseado['error_lectura'] !== null) {
            Log::warning('cot_electronico.anita_pendmae', ['mensaje' => $parseado['error_lectura']]);

            return [];
        }

        return $parseado['filas'];
    }

    /**
     * @param  list<int>  $transporteIds
     * @param  Collection<int|string, array<string, mixed>>  $repartosPorId
     * @return list<array<string, mixed>>
     */
    private function listarDesdeRemitoErp(Carbon $fecha, array $transporteIds, Collection $repartosPorId): array
    {
        $fechaSql = $fecha->toDateString();

        $remitos = Remito::query()
            ->whereDate('fecha', $fechaSql)
            ->whereIn('transporte_id', $transporteIds)
            ->where('numero', '>', 0)
            ->where(function ($q) {
                $q->whereNull('estadoremito')
                    ->orWhere('estadoremito', '!=', RemitoEstadosSupport::ESTADOREMITO_ANULADO);
            })
            ->with([
                'clientes.localidades',
                'clientes.provincias',
                'clientes.condicionivas',
                'clientes.tipodocumentos',
                'transportes',
                'puntoventas',
                'ventas.venta_impuestos',
                'remito_articulos.articulos.unidadesdemedidas',
            ])
            ->orderBy('numero')
            ->get();

        $filas = [];
        foreach ($remitos as $remito) {
            $filas[] = $this->mapearFilaDesdeRemito($remito, $repartosPorId, $fecha);
        }

        return $filas;
    }

    /**
     * @param  Collection<int|string, array<string, mixed>>  $repartosPorCodigo
     * @return array<string, mixed>|null
     */
    private function mapearFilaDesdeAnitaComprob(object $row, Collection $repartosPorCodigo, Carbon $fecha): ?array
    {
        $numeroRemito = (int) ($row->comp_remito ?? 0);
        if ($numeroRemito <= 0) {
            return null;
        }

        $codigoTransporte = (int) ($row->comp_transporte ?? 0);
        $reparto = $repartosPorCodigo->get($codigoTransporte, []);
        if ($reparto === []) {
            return null;
        }

        // p-cot: sucursal remito de factura siempre 1
        $sucursal = 1;
        $fechaRemito = $this->fechaDesdeAnita((int) ($row->comp_fecha ?? 0), $fecha);
        $envioPrevio = $this->buscarEnvioExitosoPrevio('REM', 'R', $sucursal, $numeroRemito, $fechaRemito);

        $codigoClienteAnita = trim((string) ($row->comp_cliente ?? ''));
        $cliente = $this->resolverClientePorCodigoAnita($codigoClienteAnita);
        $kilos = $this->kilosDesdeCompa(
            trim((string) ($row->comp_tipo ?? '')),
            trim((string) ($row->comp_letra ?? '')),
            (int) ($row->comp_sucursal ?? 0),
            (int) ($row->comp_nro_fact ?? 0),
        );

        // p-cot genera_cot factura: ven_gravado + ven_gravado_ot + ven_exento ≈ gravado+exento de comprob
        $importe = abs((float) ($row->comp_gravado ?? 0)) + abs((float) ($row->comp_exento ?? 0));
        if ($importe <= 0) {
            $importe = abs((float) ($row->comp_total ?? 0)) - abs((float) ($row->comp_iva ?? 0));
        }

        return [
            'clave' => $this->claveRemito('REM', 'R', $sucursal, $numeroRemito),
            'origen' => 'anita_comprob',
            'remito_id' => null,
            'venta_id' => null,
            'anita' => [
                'fuente' => 'comprob',
                'tipo' => trim((string) ($row->comp_tipo ?? '')),
                'letra' => trim((string) ($row->comp_letra ?? '')),
                'sucursal_factura' => (int) ($row->comp_sucursal ?? 0),
                'nro_fact' => (int) ($row->comp_nro_fact ?? 0),
                'cliente_codigo' => $codigoClienteAnita,
            ],
            'cliente_id' => (int) ($cliente->id ?? 0) ?: null,
            'tipo' => 'REM',
            'letra' => 'R',
            'sucursal' => $sucursal,
            'numero_remito' => $numeroRemito,
            'fecha_remito' => $fechaRemito->format('Y-m-d'),
            'fecha_factura' => $fechaRemito->format('d/m/Y'),
            'desde_factura' => true,
            'factura_codigo' => trim((string) ($row->comp_tipo ?? ''))
                .trim((string) ($row->comp_letra ?? ''))
                .'-'
                .str_pad((string) (int) ($row->comp_sucursal ?? 0), 5, '0', STR_PAD_LEFT)
                .'-'
                .str_pad((string) (int) ($row->comp_nro_fact ?? 0), 8, '0', STR_PAD_LEFT),
            'cliente_codigo' => (string) ($cliente->codigo ?? ltrim($codigoClienteAnita, '0')),
            'cliente_nombre' => trim((string) ($cliente->nombre ?? $codigoClienteAnita)),
            'transporte_id' => (int) ($reparto['transporte_id'] ?? 0) ?: null,
            'transporte_codigo' => (string) ($reparto['codigo'] ?? $codigoTransporte),
            'transporte_nombre' => (string) ($reparto['nombre'] ?? ''),
            'patente' => (string) ($reparto['patente'] ?? ''),
            'cuit_chofer' => (string) ($reparto['cuit_chofer'] ?? ''),
            'kilos' => round($kilos, 2),
            'importe' => round($importe, 2),
            'ya_enviado' => $envioPrevio !== null,
            'cot_previo' => $envioPrevio?->cot,
            'nro_unico_previo' => $envioPrevio?->nro_unico,
            'error_previo' => $envioPrevio?->error,
            'seleccionado' => $envioPrevio === null,
            'destinatario' => $this->destinatarioDesdeCliente($cliente, $codigoClienteAnita),
        ];
    }

    /**
     * @param  Collection<int|string, array<string, mixed>>  $repartosPorCodigo
     * @return array<string, mixed>|null
     */
    private function mapearFilaDesdeAnitaPendmae(object $row, Collection $repartosPorCodigo, Carbon $fecha): ?array
    {
        $numeroRemito = (int) ($row->penm_nro ?? 0);
        if ($numeroRemito <= 0) {
            return null;
        }

        $codigoTransporte = (int) ($row->penm_expreso ?? 0);
        $reparto = $repartosPorCodigo->get($codigoTransporte, []);
        if ($reparto === []) {
            return null;
        }

        $letra = trim((string) ($row->penm_letra ?? 'R')) ?: 'R';
        $sucursal = (int) ($row->penm_sucursal ?? 1);
        if ($sucursal <= 0) {
            $sucursal = 1;
        }
        $fechaRemito = $this->fechaDesdeAnita((int) ($row->penm_fecha ?? 0), $fecha);
        $envioPrevio = $this->buscarEnvioExitosoPrevio('REM', $letra, $sucursal, $numeroRemito, $fechaRemito);

        $codigoClienteAnita = trim((string) ($row->penm_cliente ?? ''));
        $cliente = $this->resolverClientePorCodigoAnita($codigoClienteAnita);
        $kilos = $this->kilosDesdePendmov(
            trim((string) ($row->penm_tipo ?? 'REM')),
            $letra,
            $sucursal,
            $numeroRemito,
        );

        // p-cot un_remito: penm_tot_seguro == 0 ? penm_neto : penm_tot_seguro
        $totSeguro = (float) ($row->penm_tot_seguro ?? 0);
        $importe = $totSeguro == 0.0 ? (float) ($row->penm_neto ?? 0) : $totSeguro;

        return [
            'clave' => $this->claveRemito('REM', $letra, $sucursal, $numeroRemito),
            'origen' => 'anita_pendmae',
            'remito_id' => null,
            'venta_id' => null,
            'anita' => [
                'fuente' => 'pendmae',
                'tipo' => trim((string) ($row->penm_tipo ?? 'REM')),
                'letra' => $letra,
                'sucursal_factura' => $sucursal,
                'nro_fact' => $numeroRemito,
                'cliente_codigo' => $codigoClienteAnita,
            ],
            'cliente_id' => (int) ($cliente->id ?? 0) ?: null,
            'tipo' => 'REM',
            'letra' => $letra,
            'sucursal' => $sucursal,
            'numero_remito' => $numeroRemito,
            'fecha_remito' => $fechaRemito->format('Y-m-d'),
            'fecha_factura' => $fechaRemito->format('d/m/Y'),
            'desde_factura' => false,
            'factura_codigo' => '',
            'cliente_codigo' => (string) ($cliente->codigo ?? ltrim($codigoClienteAnita, '0')),
            'cliente_nombre' => trim((string) ($cliente->nombre ?? $codigoClienteAnita)),
            'transporte_id' => (int) ($reparto['transporte_id'] ?? 0) ?: null,
            'transporte_codigo' => (string) ($reparto['codigo'] ?? $codigoTransporte),
            'transporte_nombre' => (string) ($reparto['nombre'] ?? ''),
            'patente' => (string) ($reparto['patente'] ?? ''),
            'cuit_chofer' => (string) ($reparto['cuit_chofer'] ?? ''),
            'kilos' => round($kilos, 2),
            'importe' => round(abs($importe), 2),
            'ya_enviado' => $envioPrevio !== null,
            'cot_previo' => $envioPrevio?->cot,
            'nro_unico_previo' => $envioPrevio?->nro_unico,
            'error_previo' => $envioPrevio?->error,
            'seleccionado' => $envioPrevio === null,
            'destinatario' => $this->destinatarioDesdeCliente($cliente, $codigoClienteAnita),
        ];
    }

    /**
     * @param  Collection<int|string, array<string, mixed>>  $repartosPorId
     * @return array<string, mixed>
     */
    private function mapearFilaDesdeRemito(Remito $remito, Collection $repartosPorId, Carbon $fecha): array
    {
        $numeroRemito = (int) $remito->numero;
        $sucursal = $this->sucursalDesdeRemito($remito);
        $fechaRemito = Carbon::parse($remito->fecha)->startOfDay();
        $transporteId = (int) ($remito->transporte_id ?? 0);
        $reparto = $repartosPorId->get($transporteId, []);
        $envioPrevio = $this->buscarEnvioExitosoPrevio('REM', 'R', $sucursal, $numeroRemito, $fechaRemito);

        $cliente = $remito->clientes;
        $kilos = $this->calcularKilosRemito($remito);
        $importe = $this->calcularImporteRemito($remito);

        return [
            'clave' => $this->claveRemito('REM', 'R', $sucursal, $numeroRemito),
            'origen' => 'erp',
            'remito_id' => (int) $remito->id,
            'venta_id' => (int) ($remito->venta_id ?? 0) ?: null,
            'anita' => null,
            'cliente_id' => (int) ($cliente->id ?? 0) ?: null,
            'tipo' => 'REM',
            'letra' => 'R',
            'sucursal' => $sucursal,
            'numero_remito' => $numeroRemito,
            'fecha_remito' => $fechaRemito->format('Y-m-d'),
            'fecha_factura' => $fechaRemito->format('d/m/Y'),
            'desde_factura' => (int) ($remito->venta_id ?? 0) > 0,
            'factura_codigo' => '',
            'cliente_codigo' => (string) ($cliente->codigo ?? ''),
            'cliente_nombre' => trim((string) ($cliente->nombre ?? '')),
            'transporte_id' => $transporteId,
            'transporte_codigo' => (string) ($reparto['codigo'] ?? $remito->transportes->codigo ?? ''),
            'transporte_nombre' => (string) ($reparto['nombre'] ?? $remito->transportes->nombre ?? ''),
            'patente' => (string) ($reparto['patente'] ?? $remito->transportes->patentevehiculo ?? ''),
            'cuit_chofer' => (string) ($reparto['cuit_chofer'] ?? ''),
            'kilos' => round($kilos, 2),
            'importe' => round($importe, 2),
            'ya_enviado' => $envioPrevio !== null,
            'cot_previo' => $envioPrevio?->cot,
            'nro_unico_previo' => $envioPrevio?->nro_unico,
            'error_previo' => $envioPrevio?->error,
            'seleccionado' => $envioPrevio === null,
            'destinatario' => $this->destinatarioDesdeCliente($cliente),
        ];
    }

    private function kilosDesdeCompa(string $tipo, string $letra, int $sucursal, int $nroFact): float
    {
        if ($tipo === '' || $nroFact <= 0) {
            return 0.0;
        }

        $api = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'compaux',
            'campos' => 'compa_articulo, compa_cantidad, compa_pieza',
            'whereArmado' => " WHERE compa_tipo = '".$this->esc($tipo)
                ."' AND compa_letra = '".$this->esc($letra)
                ."' AND compa_sucursal = ".$sucursal
                .' AND compa_nro_fact = '.$nroFact.' ',
        ];
        $parseado = ApiAnita::parsearRespuestaLista($api->apiCall($data));
        if ($parseado['error_lectura'] !== null) {
            return 0.0;
        }

        return $this->sumarKilosArticulosAnita(
            $parseado['filas'],
            'compa_articulo',
            'compa_cantidad',
        );
    }

    private function kilosDesdePendmov(string $tipo, string $letra, int $sucursal, int $nro): float
    {
        $api = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'pendmov',
            'campos' => 'penv_articulo, penv_cantidad, penv_pieza',
            'whereArmado' => " WHERE penv_tipo = '".$this->esc($tipo)
                ."' AND penv_letra = '".$this->esc($letra)
                ."' AND penv_sucursal = ".$sucursal
                .' AND penv_nro = '.$nro.' ',
        ];
        $parseado = ApiAnita::parsearRespuestaLista($api->apiCall($data));
        if ($parseado['error_lectura'] !== null) {
            return 0.0;
        }

        return $this->sumarKilosArticulosAnita(
            $parseado['filas'],
            'penv_articulo',
            'penv_cantidad',
        );
    }

    /**
     * @param  list<object>  $filas
     */
    private function sumarKilosArticulosAnita(array $filas, string $campoSku, string $campoCantidad): float
    {
        $total = 0.0;
        foreach ($filas as $linea) {
            $sku = trim((string) ($linea->{$campoSku} ?? ''));
            if ($this->esLineaExcluida($sku)) {
                continue;
            }

            $cantidad = (float) ($linea->{$campoCantidad} ?? 0);
            if ($cantidad <= 0) {
                continue;
            }

            $articulo = $this->resolverArticuloPorSku($sku);
            $um = strtoupper((string) optional($articulo?->unidadesdemedidas)->abreviatura);
            $peso = (float) ($articulo->peso ?? $articulo->coeficienteconversion ?? 0);

            if (str_starts_with($um, 'UN') && $peso > 0) {
                $total += $cantidad * $peso;
            } else {
                $total += $cantidad;
            }
        }

        return $total;
    }

    private function resolverArticuloPorSku(string $sku): ?Articulo
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }
        $skuAlt = ltrim($sku, '0');

        return Articulo::query()
            ->with('unidadesdemedidas')
            ->where(function ($q) use ($sku, $skuAlt) {
                $q->where('sku', $sku);
                if ($skuAlt !== '' && $skuAlt !== $sku) {
                    $q->orWhere('sku', $skuAlt);
                }
            })
            ->first();
    }

    private function sucursalDesdeRemito(Remito $remito): int
    {
        if ($remito->puntoventas) {
            return (int) $remito->puntoventas->codigo;
        }

        return (int) config('facturacion.PUNTOVENTA_REMITO', 1) ?: 1;
    }

    private function fechaDesdeAnita(int $fechaAnita, Carbon $fallback): Carbon
    {
        if ($fechaAnita >= 19000101) {
            try {
                return Carbon::createFromFormat('Ymd', (string) $fechaAnita)->startOfDay();
            } catch (\Throwable) {
            }
        }

        return $fallback->copy()->startOfDay();
    }

    private function resolverClientePorCodigoAnita(string $codigoClienteAnita): ?Cliente
    {
        $codigoClienteAnita = trim($codigoClienteAnita);
        if ($codigoClienteAnita === '') {
            return null;
        }

        $codigoSinCeros = ltrim($codigoClienteAnita, '0');

        return Cliente::query()
            ->with(['localidades', 'provincias', 'condicionivas', 'tipodocumentos'])
            ->where(function ($q) use ($codigoClienteAnita, $codigoSinCeros) {
                $q->where('codigo', $codigoClienteAnita);
                if ($codigoSinCeros !== '' && $codigoSinCeros !== $codigoClienteAnita) {
                    $q->orWhere('codigo', $codigoSinCeros);
                }
            })
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function destinatarioDesdeCliente(?Cliente $cliente, string $fallbackNombre = ''): array
    {
        if (! $cliente) {
            return [
                'es_cf' => false,
                'cuit' => '',
                'documento' => '',
                'razon_social' => $fallbackNombre,
                'calle' => 'S/N',
                'numero' => '',
                'localidad' => '',
                'provincia' => 'B',
                'codigo_postal' => '',
            ];
        }

        $condicion = strtoupper(trim((string) ($cliente->condicionivas->nombre ?? '')));
        $esCf = str_contains($condicion, 'CONSUMIDOR');
        $domicilio = trim((string) ($cliente->domicilio ?? ''));
        $parte = $this->partirCalleNumero($domicilio);

        return [
            'es_cf' => $esCf,
            'cuit' => preg_replace('/\D+/', '', (string) ($cliente->numerodocumento ?? '')) ?: '',
            'documento' => preg_replace('/\D+/', '', (string) ($cliente->numerodocumento ?? '')) ?: '',
            'razon_social' => trim((string) ($cliente->nombre ?? $fallbackNombre)),
            'calle' => $parte['calle'],
            'numero' => $parte['numero'],
            'localidad' => (string) (optional($cliente->localidades)->nombre ?? ''),
            'provincia' => strtoupper(trim((string) (optional($cliente->provincias)->abreviatura ?? 'B'))) ?: 'B',
            'codigo_postal' => preg_replace('/\D+/', '', (string) ($cliente->codigopostal ?? '')) ?: '',
        ];
    }

    /** @return array{calle:string,numero:string} */
    private function partirCalleNumero(string $domicilio): array
    {
        $domicilio = trim($domicilio);
        if ($domicilio === '') {
            return ['calle' => 'S/N', 'numero' => ''];
        }

        if (preg_match('/^(.+?)\s+(\d+[A-Za-z]?)$/', $domicilio, $m)) {
            return ['calle' => trim($m[1]), 'numero' => trim($m[2])];
        }

        return ['calle' => $domicilio, 'numero' => ''];
    }

    private function claveRemito(string $tipo, string $letra, int $sucursal, int $numero): string
    {
        return implode('|', [$tipo, $letra, $sucursal, $numero]);
    }

    private function buscarEnvioExitosoPrevio(
        string $tipo,
        string $letra,
        int $sucursal,
        int $numeroRemito,
        Carbon $fechaFactura,
    ): ?CotRemitoEnvio {
        return CotRemitoEnvio::query()
            ->where('tipo', $tipo)
            ->where('letra', $letra)
            ->where('sucursal', $sucursal)
            ->where('numero_remito', $numeroRemito)
            ->whereDate('fecha_remito', $fechaFactura->toDateString())
            ->where(function ($q) {
                $q->where('procesado', 'SI')
                    ->orWhere(function ($sq) {
                        $sq->whereNotNull('cot')->where('cot', '!=', '');
                    });
            })
            ->orderByDesc('id')
            ->first();
    }

    private function calcularKilosRemito(Remito $remito): float
    {
        $total = 0.0;

        foreach ($remito->remito_articulos as $item) {
            $articulo = $item->articulos;
            if (! $articulo) {
                continue;
            }
            if ($this->esLineaExcluida((string) ($articulo->sku ?? ''))) {
                continue;
            }

            $kilo = (float) ($item->kilo ?? 0);
            if ($kilo > 0) {
                $total += $kilo;
                continue;
            }

            $um = strtoupper((string) optional($articulo->unidadesdemedidas)->abreviatura);
            $cantidad = (float) ($item->pieza ?? 0);
            $peso = (float) ($articulo->peso ?? $articulo->coeficienteconversion ?? 0);
            if (str_starts_with($um, 'UN') && $peso > 0) {
                $total += $cantidad * $peso;
            } else {
                $total += $cantidad;
            }
        }

        return $total;
    }

    private function calcularImporteRemito(Remito $remito): float
    {
        if ($remito->ventas) {
            $venta = $remito->ventas;
            $desglose = \App\Support\Ventas\IvaVentas\IvaVentasDesgloseSupport::columnasDesdeVenta($venta);
            $total = (float) ($desglose['neto_gravado'] ?? 0)
                + (float) ($desglose['exento'] ?? 0)
                + (float) ($desglose['no_gravado'] ?? 0);
            if ($total > 0) {
                return $total;
            }
            if (abs((float) ($venta->total ?? 0)) > 0) {
                return abs((float) $venta->total);
            }
        }

        $total = 0.0;
        foreach ($remito->remito_articulos as $item) {
            $articulo = $item->articulos;
            if ($articulo && $this->esLineaExcluida((string) ($articulo->sku ?? ''))) {
                continue;
            }
            $total += (float) ($item->kilo ?? 0) * (float) ($item->precio ?? 0);
        }

        return $total;
    }

    private function esLineaExcluida(string $sku): bool
    {
        $sku = trim($sku);

        return $sku === ''
            || str_starts_with(strtolower($sku), 'texto')
            || $sku === '0000000000903';
    }

    private function esc(string $valor): string
    {
        return str_replace("'", "''", $valor);
    }

    public function resolverEmpresaEmisora(): ?Empresa
    {
        return Empresa::query()->orderBy('id')->first();
    }

    /**
     * Productos para archivo ARBA (compartic / pendmov / remito_articulo).
     *
     * @param  array<string, mixed>  $filaRemito
     * @return list<array<string, mixed>>
     */
    public function productosParaArchivo(array $filaRemito): array
    {
        $origen = (string) ($filaRemito['origen'] ?? '');

        if ($origen === 'erp') {
            return $this->productosDesdeRemitoErp((int) ($filaRemito['remito_id'] ?? 0));
        }

        $anita = $filaRemito['anita'] ?? null;
        if (! is_array($anita)) {
            return [];
        }

        if (($anita['fuente'] ?? '') === 'comprob') {
            return $this->productosDesdeCompa(
                (string) ($anita['tipo'] ?? ''),
                (string) ($anita['letra'] ?? ''),
                (int) ($anita['sucursal_factura'] ?? 0),
                (int) ($anita['nro_fact'] ?? 0),
            );
        }

        if (($anita['fuente'] ?? '') === 'pendmae') {
            return $this->productosDesdePendmov(
                (string) ($anita['tipo'] ?? 'REM'),
                (string) ($anita['letra'] ?? 'R'),
                (int) ($anita['sucursal_factura'] ?? 1),
                (int) ($anita['nro_fact'] ?? 0),
            );
        }

        return [];
    }

    /** @return list<array<string, mixed>> */
    private function productosDesdeRemitoErp(int $remitoId): array
    {
        if ($remitoId <= 0) {
            return [];
        }

        $remito = Remito::query()
            ->with(['remito_articulos.articulos.unidadesdemedidas'])
            ->find($remitoId);
        if (! $remito) {
            return [];
        }

        $productos = [];
        foreach ($remito->remito_articulos as $item) {
            $articulo = $item->articulos;
            if (! $articulo) {
                continue;
            }
            $sku = (string) ($articulo->sku ?? '');
            if ($this->esLineaExcluida($sku)) {
                continue;
            }

            $um = strtoupper((string) optional($articulo->unidadesdemedidas)->abreviatura);
            $cantidad = (float) ($item->kilo ?? 0);
            if ($cantidad <= 0) {
                $cantidad = (float) ($item->pieza ?? 0);
                $peso = (float) ($articulo->peso ?? $articulo->coeficienteconversion ?? 0);
                if (str_starts_with($um, 'UN') && $peso > 0) {
                    $cantidad *= $peso;
                }
            }
            if ($cantidad <= 0) {
                continue;
            }

            $this->acumularProducto($productos, $articulo, $sku, $cantidad);
        }

        return array_values($productos);
    }

    /** @return list<array<string, mixed>> */
    private function productosDesdeCompa(string $tipo, string $letra, int $sucursal, int $nroFact): array
    {
        if ($tipo === '' || $nroFact <= 0) {
            return [];
        }

        $api = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'compaux',
            'campos' => 'compa_articulo, compa_cantidad',
            'whereArmado' => " WHERE compa_tipo = '".$this->esc($tipo)
                ."' AND compa_letra = '".$this->esc($letra)
                ."' AND compa_sucursal = ".$sucursal
                .' AND compa_nro_fact = '.$nroFact.' ',
        ];
        $parseado = ApiAnita::parsearRespuestaLista($api->apiCall($data));
        if ($parseado['error_lectura'] !== null) {
            return [];
        }

        return $this->productosDesdeFilasAnita($parseado['filas'], 'compa_articulo', 'compa_cantidad');
    }

    /** @return list<array<string, mixed>> */
    private function productosDesdePendmov(string $tipo, string $letra, int $sucursal, int $nro): array
    {
        $api = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'pendmov',
            'campos' => 'penv_articulo, penv_cantidad',
            'whereArmado' => " WHERE penv_tipo = '".$this->esc($tipo)
                ."' AND penv_letra = '".$this->esc($letra)
                ."' AND penv_sucursal = ".$sucursal
                .' AND penv_nro = '.$nro.' ',
        ];
        $parseado = ApiAnita::parsearRespuestaLista($api->apiCall($data));
        if ($parseado['error_lectura'] !== null) {
            return [];
        }

        return $this->productosDesdeFilasAnita($parseado['filas'], 'penv_articulo', 'penv_cantidad');
    }

    /**
     * @param  list<object>  $filas
     * @return list<array<string, mixed>>
     */
    private function productosDesdeFilasAnita(array $filas, string $campoSku, string $campoCantidad): array
    {
        $productos = [];
        foreach ($filas as $linea) {
            $sku = trim((string) ($linea->{$campoSku} ?? ''));
            if ($this->esLineaExcluida($sku)) {
                continue;
            }
            $cantidad = (float) ($linea->{$campoCantidad} ?? 0);
            if ($cantidad <= 0) {
                continue;
            }

            $articulo = $this->resolverArticuloPorSku($sku);
            if (! $articulo) {
                continue;
            }

            $um = strtoupper((string) optional($articulo->unidadesdemedidas)->abreviatura);
            $peso = (float) ($articulo->peso ?? $articulo->coeficienteconversion ?? 0);
            if (str_starts_with($um, 'UN') && $peso > 0) {
                $cantidad *= $peso;
            }

            $this->acumularProducto($productos, $articulo, (string) $articulo->sku, $cantidad);
        }

        return array_values($productos);
    }

    /**
     * @param  array<string, array<string, mixed>>  $productos
     */
    private function acumularProducto(array &$productos, Articulo $articulo, string $sku, float $cantidad): void
    {
        $codigoNomenclador = trim((string) ($articulo->nomenclador ?? ''));
        if ($codigoNomenclador === '') {
            $codigoNomenclador = '1';
        }
        $codigoUmd = trim((string) ($articulo->unidadmedidanomenclador ?? ''));
        if ($codigoUmd === '') {
            $codigoUmd = '3';
        }

        $clave = $sku.'|'.$codigoNomenclador;
        if (! isset($productos[$clave])) {
            $productos[$clave] = [
                'sku' => $sku,
                'descripcion' => (string) ($articulo->descripcion ?? $sku),
                'codigo_nomenclador' => $codigoNomenclador,
                'codigo_umd' => $codigoUmd,
                'umd_descripcion' => (string) (optional($articulo->unidadesdemedidas)->nombre ?: 'KILO'),
                'cantidad' => 0.0,
            ];
        }
        $productos[$clave]['cantidad'] += $cantidad;
    }
}
