<?php

namespace App\Support\Ventas\CertificadoSanitario;

use App\ApiAnita;
use App\Models\Stock\Articulo;
use App\Models\Stock\Codigosenasa;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Pedido;
use App\Models\Ventas\Transporte;
use App\Models\Ventas\Zonavta;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Fuente de pedidos para certificado sanitario: ERP primero, Anita (pendmae/pendmov) como fallback.
 */
final class PedidoCertificadoSource
{
    /** @var Collection<int, CertificadoSanitarioArticuloSinSenasa> */
    private Collection $omitidosSinSenasa;

    public function __construct()
    {
        $this->omitidosSinSenasa = collect();
    }

    /**
     * @param  array{
     *   fecha: string,
     *   transporte_id?: int|null,
     *   transporte_desde?: int|null,
     *   transporte_hasta?: int|null,
     *   zonavta_id?: int|null,
     *   cliente_id?: int|null,
     *   fallback_anita?: bool
     * }  $filtros
     * @return Collection<int, PedidoCertificadoLinea>
     */
    public function listarLineas(array $filtros): Collection
    {
        return $this->listar($filtros)->lineas;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function listar(array $filtros): PedidoCertificadoListado
    {
        $this->omitidosSinSenasa = collect();
        $fecha = Carbon::parse($filtros['fecha'])->startOfDay();
        $lineasErp = $this->lineasDesdeErp($fecha, $filtros);
        $codigosErp = $lineasErp->pluck('codigoPedido')->unique()->all();

        $fallback = array_key_exists('fallback_anita', $filtros)
            ? (bool) $filtros['fallback_anita']
            : (bool) config('senasa.fallback_anita_pedido', true);

        if (! $fallback) {
            return new PedidoCertificadoListado(
                CertificadoSanitarioDestinoAnitaSupport::enriquecerLineas(
                    CertificadoSanitarioOrigenSupport::enriquecerLineas($lineasErp->values())
                ),
                $this->omitidosSinSenasa->values()
            );
        }

        $lineasAnita = $this->lineasDesdeAnita($fecha, $filtros, $codigosErp);

        return new PedidoCertificadoListado(
            CertificadoSanitarioDestinoAnitaSupport::enriquecerLineas(
                CertificadoSanitarioOrigenSupport::enriquecerLineas(
                    $lineasErp->concat($lineasAnita)->values()
                )
            ),
            $this->omitidosSinSenasa->values()
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, PedidoCertificadoLinea>
     */
    private function lineasDesdeErp(Carbon $fecha, array $filtros): Collection
    {
        $query = Pedido::query()
            ->with([
                'clientes.localidades.provincias',
                'clientes.coeficientes',
                'transportes',
                'zonavtas',
                'pedido_articulos.articulos.codigosenasas.envasesenasas',
                'pedido_articulos.articulos.lineas',
                'pedido_articulos.articulos.mventas',
            ])
            ->whereDate('fechaentrega', $fecha->toDateString())
            ->where(function ($q) {
                $q->whereNull('estadopedido')
                    ->orWhereNotIn('estadopedido', ['Suspendido', 'Anulado']);
            })
            ->where(function ($q) {
                $q->whereNull('estado')->orWhere('estado', '!=', 'A');
            });

        if (! empty($filtros['transporte_id'])) {
            $query->where('transporte_id', (int) $filtros['transporte_id']);
        }
        if (! empty($filtros['zonavta_id'])) {
            $query->where('zonavta_id', (int) $filtros['zonavta_id']);
        }
        if (! empty($filtros['cliente_id'])) {
            $query->where('cliente_id', (int) $filtros['cliente_id']);
        }

        $out = collect();
        foreach ($query->get() as $pedido) {
            $cliente = $pedido->clientes;
            $transporte = $pedido->transportes;
            $zona = $pedido->zonavtas;
            $loc = $cliente?->localidades;
            $prov = $loc?->provincias;

            foreach ($pedido->pedido_articulos as $item) {
                if (($item->estado ?? '') === 'A') {
                    continue;
                }
                $art = $item->articulos;
                if (! $art) {
                    continue;
                }
                $sku = trim((string) ($art->sku ?? $art->codigo ?? ''));
                if ($sku === '' || stripos($sku, 'texto') === 0) {
                    continue;
                }
                $cods = $art->codigosenasas;
                if (! $this->tieneCodigoSenasa($cods)) {
                    $this->registrarOmitidoSinSenasa(
                        $filtros,
                        sku: $sku,
                        articuloId: (int) $art->id,
                        articuloNombre: trim((string) ($art->descripcion ?? $art->nombre ?? $sku)),
                        codigoPedido: (string) ($pedido->codigo ?? $pedido->id),
                        origen: 'erp',
                        codigoCliente: (string) ($cliente->codigo ?? ''),
                        clienteNombre: trim((string) ($cliente->nombre ?? '')),
                        codigoTransporte: $transporte?->codigo !== null ? (string) $transporte->codigo : null,
                    );
                    continue;
                }

                $piezas = (float) ($item->pieza ?? 0);
                $cajas = $this->cajasDesdePiezas($piezas, (float) ($art->unidadesxenvase ?? 0));
                if ($cajas == 0.0) {
                    $cajas = (float) ($item->caja ?? 0);
                }
                $cantidades = CertificadoSanitarioCoeficienteSupport::cantidadesParaCertificado(
                    $cliente,
                    $transporte,
                    (float) ($item->kilo ?? 0),
                    $cajas,
                    $piezas
                );
                if ($cantidades === null) {
                    continue;
                }

                $out->push(new PedidoCertificadoLinea(
                    codigoPedido: (string) ($pedido->codigo ?? $pedido->id),
                    origen: 'erp',
                    codigoCliente: (string) ($cliente->codigo ?? ''),
                    clienteId: $cliente?->id,
                    transporteId: $transporte?->id,
                    codigoTransporte: $transporte?->codigo !== null ? (string) $transporte->codigo : null,
                    zonavtaId: $zona?->id,
                    codigoZona: $zona?->codigo !== null ? (int) $zona->codigo : ($zona?->id),
                    sku: $sku,
                    articuloNombre: trim((string) ($art->descripcion ?? $art->nombre ?? '')),
                    articuloId: (int) $art->id,
                    kilos: $cantidades['kilos'],
                    cajas: $cantidades['cajas'],
                    piezas: $cantidades['piezas'],
                    codigosenasaId: (int) $cods->id,
                    llevafrio: Codigosenasa::codigoFrio($cods->llevafrio ?? 'N'),
                    registroSenasa: trim((string) ($cods->registro ?? '')),
                    prefijoSenasa: trim((string) ($cods->prefijo ?? '')),
                    envasesenasaId: $cods->envasesenasa_id ? (int) $cods->envasesenasa_id : null,
                    envaseNombre: trim((string) ($cods->envasesenasas->nombre ?? '')),
                    marca: trim((string) ($art->mventas->nombre ?? $art->lineas->nombre ?? $art->nombre ?? '')),
                    vencimientoEnDias: (int) ($art->vencimientoendia ?? 0),
                    pesoAprox: (float) ($art->peso ?? 0),
                    localidadSenasaCodigo: $loc && $loc->codigosenasa ? (int) $loc->codigosenasa : null,
                    clienteNombre: trim((string) ($cliente->nombre ?? '')),
                    clienteDireccion: trim((string) ($cliente->domicilio ?? $cliente->direccion ?? '')),
                    clienteCp: trim((string) ($cliente->codigopostal ?? '')),
                    clienteTelefono: trim((string) ($cliente->telefono ?? '')),
                    localidadNombre: trim((string) ($loc->nombre ?? '')),
                    provinciaNombre: trim((string) ($prov->nombre ?? '')),
                ));
            }
        }

        return $this->filtrarRangoTransporte($out, $filtros);
    }

    /**
     * @param  list<string>  $codigosYaEnErp
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, PedidoCertificadoLinea>
     */
    private function lineasDesdeAnita(Carbon $fecha, array $filtros, array $codigosYaEnErp): Collection
    {
        $fechaAnita = (int) $fecha->format('Ymd');
        $api = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'pendmae',
            'campos' => '
                penm_tipo, penm_letra, penm_sucursal, penm_nro,
                penm_cliente, penm_fecha_ent, penm_expreso, penm_zonavta,
                penm_estado, penm_subzona
            ',
            'whereArmado' => " WHERE penm_tipo = 'PED' AND penm_fecha_ent = ".$fechaAnita
                ." AND penm_estado <> 'S' AND penm_estado <> 'A' ",
        ];
        $cabeceras = json_decode($api->apiCall($data));
        if (! is_array($cabeceras) || $cabeceras === []) {
            return collect();
        }

        $codigosErpNorm = array_map(static fn ($c) => strtoupper(trim((string) $c)), $codigosYaEnErp);
        $out = collect();

        foreach ($cabeceras as $cab) {
            $codigo = sprintf(
                'PED-%s-%s-%s',
                trim((string) $cab->penm_letra),
                str_pad((string) (int) $cab->penm_sucursal, 5, '0', STR_PAD_LEFT),
                str_pad((string) (int) $cab->penm_nro, 8, '0', STR_PAD_LEFT)
            );
            if (in_array(strtoupper($codigo), $codigosErpNorm, true)) {
                continue;
            }

            $codigoCliente = ltrim(trim((string) $cab->penm_cliente), '0');
            $codigoClienteAnita = trim((string) $cab->penm_cliente);
            $cliente = Cliente::query()
                ->with('coeficientes')
                ->where(function ($q) use ($codigoCliente, $codigoClienteAnita) {
                    $q->where('codigo', $codigoCliente);
                    if ($codigoClienteAnita !== '' && $codigoClienteAnita !== $codigoCliente) {
                        $q->orWhere('codigo', $codigoClienteAnita);
                    }
                })
                ->first();
            if ($cliente && ! empty($filtros['cliente_id']) && (int) $cliente->id !== (int) $filtros['cliente_id']) {
                continue;
            }

            $transporte = Transporte::query()->where('codigo', (string) (int) $cab->penm_expreso)->first();
            if (! empty($filtros['transporte_id']) && (! $transporte || (int) $transporte->id !== (int) $filtros['transporte_id'])) {
                continue;
            }

            $zonaCodigo = (string) (int) $cab->penm_zonavta;
            $zona = Zonavta::query()
                ->where(function ($q) use ($zonaCodigo, $cab) {
                    $q->where('codigo', $zonaCodigo)
                        ->orWhere('id', (int) $cab->penm_zonavta);
                })
                ->first();
            if (! empty($filtros['zonavta_id']) && (! $zona || (int) $zona->id !== (int) $filtros['zonavta_id'])) {
                continue;
            }

            $movs = $this->leerPendmovAnita(
                (string) $cab->penm_tipo,
                (string) $cab->penm_letra,
                (int) $cab->penm_sucursal,
                (int) $cab->penm_nro,
                $fechaAnita
            );

            $loc = $cliente?->localidades;
            $prov = $loc?->provincias;

            foreach ($movs as $mov) {
                $sku = trim((string) ($mov->penv_articulo ?? ''));
                if ($sku === '' || stripos($sku, 'texto') === 0) {
                    continue;
                }
                $skuAlt = ltrim($sku, '0');
                $art = Articulo::query()
                    ->with(['codigosenasas.envasesenasas', 'lineas', 'mventas'])
                    ->where(function ($q) use ($sku, $skuAlt) {
                        $q->where('sku', $sku);
                        if ($skuAlt !== '' && $skuAlt !== $sku) {
                            $q->orWhere('sku', $skuAlt);
                        }
                    })
                    ->first();
                $cods = $art?->codigosenasas;
                if (! $this->tieneCodigoSenasa($cods)) {
                    $this->registrarOmitidoSinSenasa(
                        $filtros,
                        sku: $sku,
                        articuloId: $art?->id,
                        articuloNombre: trim((string) ($art?->descripcion ?? $art?->nombre ?? $sku)),
                        codigoPedido: $codigo,
                        origen: 'anita',
                        codigoCliente: $codigoCliente !== '' ? $codigoCliente : trim((string) $cab->penm_cliente),
                        clienteNombre: trim((string) ($cliente->nombre ?? '')),
                        codigoTransporte: (string) (int) $cab->penm_expreso,
                    );
                    continue;
                }

                $piezas = (float) ($mov->penv_pieza ?? 0);
                $cantidades = CertificadoSanitarioCoeficienteSupport::cantidadesParaCertificado(
                    $cliente,
                    $transporte,
                    (float) ($mov->penv_cantidad ?? 0),
                    $this->cajasDesdePiezas($piezas, (float) ($art->unidadesxenvase ?? 0)),
                    $piezas
                );
                if ($cantidades === null) {
                    continue;
                }

                $out->push(new PedidoCertificadoLinea(
                    codigoPedido: $codigo,
                    origen: 'anita',
                    codigoCliente: $codigoCliente !== '' ? $codigoCliente : trim((string) $cab->penm_cliente),
                    clienteId: $cliente?->id,
                    transporteId: $transporte?->id,
                    codigoTransporte: (string) (int) $cab->penm_expreso,
                    zonavtaId: $zona?->id,
                    codigoZona: (int) $cab->penm_zonavta,
                    sku: $sku,
                    articuloNombre: trim((string) ($art->descripcion ?? $art->nombre ?? '')),
                    articuloId: $art?->id,
                    kilos: $cantidades['kilos'],
                    cajas: $cantidades['cajas'],
                    piezas: $cantidades['piezas'],
                    codigosenasaId: (int) $cods->id,
                    llevafrio: Codigosenasa::codigoFrio($cods->llevafrio ?? 'N'),
                    registroSenasa: trim((string) ($cods->registro ?? '')),
                    prefijoSenasa: trim((string) ($cods->prefijo ?? '')),
                    envasesenasaId: $cods->envasesenasa_id ? (int) $cods->envasesenasa_id : null,
                    envaseNombre: trim((string) ($cods->envasesenasas->nombre ?? '')),
                    marca: trim((string) ($art->mventas->nombre ?? $art->lineas->nombre ?? $art->nombre ?? '')),
                    vencimientoEnDias: (int) ($art->vencimientoendia ?? 0),
                    pesoAprox: (float) ($art->peso ?? 0),
                    localidadSenasaCodigo: $loc && $loc->codigosenasa ? (int) $loc->codigosenasa : null,
                    clienteNombre: trim((string) ($cliente->nombre ?? '')),
                    clienteDireccion: trim((string) ($cliente->domicilio ?? $cliente->direccion ?? '')),
                    clienteCp: trim((string) ($cliente->codigopostal ?? '')),
                    clienteTelefono: trim((string) ($cliente->telefono ?? '')),
                    localidadNombre: trim((string) ($loc->nombre ?? '')),
                    provinciaNombre: trim((string) ($prov->nombre ?? '')),
                ));
            }
        }

        return $this->filtrarRangoTransporte($out, $filtros);
    }

    /**
     * @return list<object>
     */
    private function leerPendmovAnita(string $tipo, string $letra, int $sucursal, int $nro, int $fechaAnita): array
    {
        $api = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'pendmov',
            'campos' => '
                penv_tipo, penv_letra, penv_sucursal, penv_nro,
                penv_articulo, penv_cantidad, penv_pieza, penv_fecha_ent
            ',
            'whereArmado' => " WHERE penv_tipo = '".$this->esc($tipo)."' AND penv_letra = '".$this->esc($letra)
                ."' AND penv_sucursal = ".$sucursal." AND penv_nro = ".$nro
                .' AND penv_fecha_ent = '.$fechaAnita.' ',
        ];
        $rows = json_decode($api->apiCall($data));

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param  Collection<int, PedidoCertificadoLinea>  $lineas
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, PedidoCertificadoLinea>
     */
    private function filtrarRangoTransporte(Collection $lineas, array $filtros): Collection
    {
        $desde = isset($filtros['transporte_desde']) ? (int) $filtros['transporte_desde'] : null;
        $hasta = isset($filtros['transporte_hasta']) ? (int) $filtros['transporte_hasta'] : null;
        if ($desde === null && $hasta === null) {
            return $lineas;
        }

        return $lineas->filter(function (PedidoCertificadoLinea $l) use ($desde, $hasta) {
            return $this->pasaRangoTransporte($l->codigoTransporte, $desde, $hasta);
        })->values();
    }

    private function pasaRangoTransporte(?string $codigoTransporte, ?int $desde, ?int $hasta): bool
    {
        if ($desde === null && $hasta === null) {
            return true;
        }
        $cod = (int) ($codigoTransporte ?? 0);
        if ($desde !== null && $cod < $desde) {
            return false;
        }
        if ($hasta !== null && $cod > $hasta) {
            return false;
        }

        return true;
    }

    private function tieneCodigoSenasa(?Codigosenasa $cods): bool
    {
        if (! $cods) {
            return false;
        }

        return trim((string) ($cods->registro ?? '')) !== ''
            || trim((string) ($cods->codigo ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function registrarOmitidoSinSenasa(
        array $filtros,
        string $sku,
        ?int $articuloId,
        string $articuloNombre,
        string $codigoPedido,
        string $origen,
        string $codigoCliente,
        string $clienteNombre,
        ?string $codigoTransporte,
    ): void {
        $desde = isset($filtros['transporte_desde']) ? (int) $filtros['transporte_desde'] : null;
        $hasta = isset($filtros['transporte_hasta']) ? (int) $filtros['transporte_hasta'] : null;
        if (! $this->pasaRangoTransporte($codigoTransporte, $desde, $hasta)) {
            return;
        }

        $this->omitidosSinSenasa->push(new CertificadoSanitarioArticuloSinSenasa(
            sku: $sku,
            articuloId: $articuloId,
            articuloNombre: $articuloNombre,
            codigoPedido: $codigoPedido,
            origen: $origen,
            codigoCliente: $codigoCliente,
            clienteNombre: $clienteNombre,
        ));
    }

    private function cajasDesdePiezas(float $piezas, float $unidadesPorEnvase): float
    {
        if ($unidadesPorEnvase <= 0) {
            return 0.0;
        }

        return $piezas / $unidadesPorEnvase;
    }

    private function esc(string $v): string
    {
        return str_replace("'", "''", $v);
    }
}
