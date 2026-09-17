<?php

namespace App\Services\Ventas\CotElectronico;

use App\Models\Ventas\Cliente;
use App\Models\Ventas\CotGuia;
use App\Models\Ventas\CotGuiaLinea;
use App\Models\Ventas\Transporte;
use App\Support\Ventas\ClienteEntregaPedidoSupport;
use App\Support\Ventas\CotGuiaSuburbanoSupport;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Arma filas del Excel "Control de remitos" (guía suburbano Ferli),
 * equivalente a lista_control() de a-controlrem.c.
 */
class CotGuiaSuburbanoExcelService
{
    /**
     * @return array{
     *   titulo: string,
     *   encabezados: list<string>,
     *   filas: list<list<string|int|float>>,
     *   total: list<string|int|float>
     * }
     */
    public function armar(CotGuia $guia): array
    {
        $guia->loadMissing(['lineas.transportes', 'transportes']);

        if (! CotGuiaSuburbanoSupport::esSuburbano($guia->transportes)) {
            throw new InvalidArgumentException(
                'La guía Excel suburbano solo aplica cuando el expreso es suburbano.'
            );
        }

        $lineas = $guia->lineas
            ->sortBy(fn (CotGuiaLinea $l) => mb_strtoupper(trim((string) $l->cliente_nombre)))
            ->values();

        $clientes = $this->clientesPorCodigo($lineas);
        $transportesLinea = $this->transportesPorCodigo($lineas);

        $filas = [];
        $totBultos = 0.0;
        $totPares = 0.0;
        $totValor = 0.0;

        foreach ($lineas as $idx => $linea) {
            $cliente = $clientes->get($this->claveCodigo((string) $linea->cliente_codigo));
            $domicilioData = $this->domicilioCliente($cliente);
            $partesDir = CotGuiaSuburbanoSupport::separarDireccionYAltura($domicilioData['domicilio']);
            $expreso = $this->nombreExpreso($linea, $guia, $transportesLinea);

            $bultos = (float) $linea->bultos;
            $pares = (float) $linea->cantidad;
            $valor = (float) $linea->valor_declarado;
            $totBultos += $bultos;
            $totPares += $pares;
            $totValor += $valor;

            $filas[] = [
                $idx,
                (int) $linea->numero,
                trim((string) ($linea->tipo ?: 'FAC')),
                CotGuiaSuburbanoSupport::etiquetaFactura(
                    (string) $linea->tipo,
                    (string) $linea->letra,
                    (int) $linea->sucursal,
                    (int) $linea->numero
                ),
                trim((string) ($linea->cliente_nombre ?: ($cliente->nombre ?? ''))),
                CotGuiaSuburbanoSupport::contactoConTelefono(
                    $cliente->contacto ?? null,
                    $cliente->telefono ?? null
                ),
                $partesDir['direccion'],
                $partesDir['altura'],
                $domicilioData['provincia'],
                $domicilioData['ciudad'],
                '',
                $domicilioData['cp'],
                '',
                $this->enteroSiCorresponde($bultos),
                '',
                $this->enteroSiCorresponde($pares),
                $this->enteroSiCorresponde($valor),
                'Si',
                $expreso,
                '',
                '',
            ];
        }

        return [
            'titulo' => 'Control de remitos Nro.: '.(int) $guia->numero,
            'encabezados' => [
                'Id',
                'Nro.',
                'Tipo',
                'Factura',
                'Destinatario',
                'Contacto',
                'Direccion',
                'Altura',
                'Provincia',
                'Ciudad',
                'Barrio',
                'CP',
                'Puerta',
                'Bultos',
                'Pallets',
                'Pares',
                'Valor declar.',
                'Despacha por Expreso',
                'Expreso',
                'Bultos Generados',
                'Observaciones',
            ],
            'filas' => $filas,
            'total' => [
                'Total Envio',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                $this->enteroSiCorresponde($totBultos),
                '',
                $this->enteroSiCorresponde($totPares),
                $this->enteroSiCorresponde($totValor),
            ],
        ];
    }

    /**
     * @param  Collection<int, CotGuiaLinea>  $lineas
     * @return Collection<string, Cliente>
     */
    private function clientesPorCodigo(Collection $lineas): Collection
    {
        $codigos = $lineas
            ->pluck('cliente_codigo')
            ->map(fn ($c) => trim((string) $c))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($codigos === []) {
            return collect();
        }

        return Cliente::query()
            ->with(['provincias', 'localidades', 'cliente_entregas.provincias', 'cliente_entregas.localidades'])
            ->whereIn('codigo', $codigos)
            ->get()
            ->keyBy(fn (Cliente $c) => $this->claveCodigo((string) $c->codigo));
    }

    /**
     * @param  Collection<int, CotGuiaLinea>  $lineas
     * @return Collection<string, Transporte>
     */
    private function transportesPorCodigo(Collection $lineas): Collection
    {
        $codigos = $lineas
            ->pluck('transporte_codigo')
            ->map(fn ($c) => trim((string) $c))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($codigos === []) {
            return collect();
        }

        return Transporte::query()
            ->whereIn('codigo', $codigos)
            ->get()
            ->keyBy(fn (Transporte $t) => $this->claveCodigo((string) $t->codigo));
    }

    /**
     * @return array{domicilio: string, provincia: string, ciudad: string, cp: string}
     */
    private function domicilioCliente(?Cliente $cliente): array
    {
        if ($cliente === null) {
            return ['domicilio' => '', 'provincia' => '', 'ciudad' => '', 'cp' => ''];
        }

        $entrega = ClienteEntregaPedidoSupport::entregasDeCliente((int) $cliente->id)->first();
        if ($entrega !== null && trim((string) ($entrega->domicilio ?? '')) !== '') {
            return [
                'domicilio' => trim((string) $entrega->domicilio),
                'provincia' => trim((string) (optional($entrega->provincias)->nombre ?? '')),
                'ciudad' => trim((string) (optional($entrega->localidades)->nombre ?? '')),
                'cp' => trim((string) ($entrega->codigopostal ?? '')),
            ];
        }

        return [
            'domicilio' => trim((string) ($cliente->domicilio ?? '')),
            'provincia' => trim((string) (optional($cliente->provincias)->nombre ?? '')),
            'ciudad' => trim((string) (optional($cliente->localidades)->nombre ?? '')),
            'cp' => trim((string) ($cliente->codigopostal ?? '')),
        ];
    }

    /**
     * @param  Collection<string, Transporte>  $transportesLinea
     */
    private function nombreExpreso(CotGuiaLinea $linea, CotGuia $guia, Collection $transportesLinea): string
    {
        $desdeRelacion = trim((string) (optional($linea->transportes)->nombre ?? ''));
        if ($desdeRelacion !== '') {
            return $desdeRelacion;
        }

        $codigo = trim((string) ($linea->transporte_codigo ?? ''));
        if ($codigo !== '') {
            $porCodigo = $transportesLinea->get($this->claveCodigo($codigo));
            $nombre = trim((string) ($porCodigo->nombre ?? ''));
            if ($nombre !== '') {
                return $nombre;
            }
        }

        return trim((string) (optional($guia->transportes)->nombre ?? ''));
    }

    private function claveCodigo(string $codigo): string
    {
        return ltrim(trim($codigo), '0') ?: '0';
    }

    private function enteroSiCorresponde(float $valor): int|float
    {
        if (abs($valor - round($valor)) < 0.00001) {
            return (int) round($valor);
        }

        return round($valor, 2);
    }
}
