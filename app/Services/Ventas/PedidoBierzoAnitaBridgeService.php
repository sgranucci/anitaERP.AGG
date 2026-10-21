<?php

namespace App\Services\Ventas;

use App\ApiAnita;
use App\Models\Ventas\Pedido;
use App\Models\Ventas\Pedido_Articulo;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\PedidoBierzoAnitaEscrituraSupport;
use App\Support\Ventas\PedidoBierzoAnitaNumeracionSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Réplica ERP → Anita de pedidos El Bierzo (pendmae + pendmov).
 * En otro entorno no hace nada.
 */
class PedidoBierzoAnitaBridgeService
{
    public function habilitado(): bool
    {
        return EntornoEmpresaSupport::esElBierzo();
    }

    /**
     * Graba o actualiza el pedido en Anita. Devuelve el código ERP (puede asignar numeración Anita).
     */
    public function sincronizar(int $pedidoId): string
    {
        if (! $this->habilitado()) {
            $codigo = (string) (Pedido::query()->whereKey($pedidoId)->value('codigo') ?? '');

            return $codigo;
        }

        $pedido = $this->cargar($pedidoId);
        $clave = PedidoBierzoAnitaNumeracionSupport::parsearCodigo($pedido->codigo);
        if ($clave === null) {
            $nro = PedidoBierzoAnitaNumeracionSupport::reservarSiguiente();
            $pedido->codigo = PedidoBierzoAnitaNumeracionSupport::formatearCodigo($nro);
            $pedido->save();
            $clave = PedidoBierzoAnitaNumeracionSupport::parsearCodigo($pedido->codigo);
        }
        if ($clave === null) {
            throw new RuntimeException('No se pudo armar la clave Anita del pedido '.$pedidoId.'.');
        }

        $lineas = $this->lineas($pedido);
        $cabeceraNueva = false;

        try {
            if ($this->existeCabecera($clave)) {
                $this->actualizarCabecera($pedido, $clave, $lineas);
            } else {
                $this->insertarCabecera($pedido, $clave, $lineas);
                $cabeceraNueva = true;
            }

            if ($this->lineasYaEntregadasOFacturadas($clave)) {
                Log::info('pedido.anita.lineas_conservadas', [
                    'pedido_id' => $pedidoId,
                    'codigo' => $pedido->codigo,
                ]);
            } else {
                if (! $cabeceraNueva) {
                    $this->eliminarLineas($clave);
                }
                $this->insertarLineas($pedido, $clave, $lineas);
            }
        } catch (\Throwable $e) {
            if ($cabeceraNueva) {
                $this->revertirAlta($clave);
            }
            throw new RuntimeException(
                'Error al grabar el pedido en Anita: '.$e->getMessage(),
                0,
                $e
            );
        }

        Log::info('pedido.anita.grabado', [
            'pedido_id' => (int) $pedido->id,
            'codigo' => (string) $pedido->codigo,
            'alta' => $cabeceraNueva,
            'lineas' => $lineas->count(),
        ]);

        return (string) $pedido->codigo;
    }

    private function cargar(int $pedidoId): Pedido
    {
        $pedido = Pedido::query()
            ->with([
                'clientes.vendedores',
                'clientes.zonavtas',
                'condicionesdeventa',
                'vendedores',
                'zonavtas',
                'transportes',
                'pedido_articulos.articulos.categorias',
                'pedido_articulos.articulos.unidadesdemedidas',
                'pedido_articulos.articulos.impuestos',
                'pedido_articulos.monedas',
            ])
            ->find($pedidoId);

        if ($pedido === null) {
            throw new RuntimeException('Pedido '.$pedidoId.' inexistente.');
        }

        return $pedido;
    }

    /**
     * @return Collection<int, Pedido_Articulo>
     */
    private function lineas(Pedido $pedido): Collection
    {
        return $pedido->pedido_articulos
            ->filter(static fn (Pedido_Articulo $linea): bool => (int) ($linea->articulo_id ?? 0) > 0 && $linea->articulos !== null)
            ->sortBy(static fn (Pedido_Articulo $linea): string => sprintf('%08d-%08d', (int) $linea->numeroitem, (int) $linea->id))
            ->values();
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private function existeCabecera(array $clave): bool
    {
        $parsed = $this->listar('pendmae', 'penm_nro', PedidoBierzoAnitaNumeracionSupport::whereCabecera($clave), 'pedido pendmae existe');

        return $parsed !== [];
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private function lineasYaEntregadasOFacturadas(array $clave): bool
    {
        $filas = $this->listar(
            'pendmov',
            'penv_cantentr, penv_cantfact',
            PedidoBierzoAnitaNumeracionSupport::whereLineas($clave),
            'pedido pendmov entregado'
        );
        foreach ($filas as $fila) {
            if ((float) ($fila->penv_cantentr ?? 0) > 0.00001 || (float) ($fila->penv_cantfact ?? 0) > 0.00001) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @param  Collection<int, Pedido_Articulo>  $lineas
     */
    private function insertarCabecera(Pedido $pedido, array $clave, Collection $lineas): void
    {
        $insert = PedidoBierzoAnitaEscrituraSupport::cabeceraInsert($pedido, $clave, $lineas, true);
        $this->api()->apiCallEscritura([
            'acc' => 'insert',
            'sistema' => 'ventas',
            'tabla' => 'pendmae',
            'campos' => $insert['campos'],
            'valores' => $insert['valores'],
        ], 'pedido pendmae insert');
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @param  Collection<int, Pedido_Articulo>  $lineas
     */
    private function actualizarCabecera(Pedido $pedido, array $clave, Collection $lineas): void
    {
        $this->api()->apiCallEscritura([
            'acc' => 'update',
            'sistema' => 'ventas',
            'tabla' => 'pendmae',
            'valores' => PedidoBierzoAnitaEscrituraSupport::cabeceraUpdateSet($pedido, $clave, $lineas),
            'whereArmado' => PedidoBierzoAnitaNumeracionSupport::whereCabecera($clave),
        ], 'pedido pendmae update', 'anita_bridge.fallo', true);
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private function eliminarLineas(array $clave): void
    {
        $this->api()->apiCallEscritura([
            'acc' => 'delete',
            'sistema' => 'ventas',
            'tabla' => 'pendmov',
            'whereArmado' => PedidoBierzoAnitaNumeracionSupport::whereLineas($clave),
        ], 'pedido pendmov delete');
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @param  Collection<int, Pedido_Articulo>  $lineas
     */
    private function insertarLineas(Pedido $pedido, array $clave, Collection $lineas): void
    {
        $cliente6 = PedidoBierzoAnitaEscrituraSupport::cliente6($pedido);
        $vendedor = max(0, (int) (optional($pedido->vendedores)->codigo ?? optional(optional($pedido->clientes)->vendedores)->codigo));
        $zona = max(0, (int) (optional($pedido->zonavtas)->codigo ?? optional(optional($pedido->clientes)->zonavtas)->codigo));
        $reparto = max(0, (int) (optional($pedido->transportes)->codigo));
        $fecha = (int) ($pedido->fecha?->format('Ymd') ?: date('Ymd'));
        $fechaEntrega = (int) (($pedido->fechaentrega ?: $pedido->fecha)?->format('Ymd') ?: $fecha);

        $orden = 0;
        foreach ($lineas as $linea) {
            $insert = PedidoBierzoAnitaEscrituraSupport::lineaInsert(
                $linea,
                $clave,
                $orden,
                $cliente6,
                $vendedor,
                $zona,
                $reparto,
                $fecha,
                $fechaEntrega,
            );
            $this->api()->apiCallEscritura([
                'acc' => 'insert',
                'sistema' => 'ventas',
                'tabla' => 'pendmov',
                'campos' => $insert['campos'],
                'valores' => $insert['valores'],
            ], 'pedido pendmov insert');
            $orden++;
        }
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private function revertirAlta(array $clave): void
    {
        try {
            $this->eliminarLineas($clave);
            $this->api()->apiCallEscritura([
                'acc' => 'delete',
                'sistema' => 'ventas',
                'tabla' => 'pendmae',
                'whereArmado' => PedidoBierzoAnitaNumeracionSupport::whereCabecera($clave),
            ], 'pedido pendmae revertir alta');
        } catch (\Throwable $e) {
            Log::warning('pedido.anita.revertir_alta', [
                'nro' => $clave['nro'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return list<object>
     */
    private function listar(string $tabla, string $campos, string $where, string $contexto): array
    {
        $raw = $this->api()->apiCallEscritura([
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => $tabla,
            'campos' => $campos,
            'whereArmado' => $where,
        ], $contexto);
        $parsed = ApiAnita::parsearRespuestaLista($raw);
        if (($parsed['error_lectura'] ?? null) !== null) {
            throw new RuntimeException($parsed['error_lectura']);
        }

        return $parsed['filas'] ?? [];
    }

    private function api(): ApiAnita
    {
        return new ApiAnita();
    }
}
