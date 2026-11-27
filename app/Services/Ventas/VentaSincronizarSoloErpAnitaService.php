<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\ApiAnita;
use App\Models\Ventas\Venta;
use App\Services\Ventas\Gastronomia\GastronomiaChequeoVentasAnitaErpService;
use App\Support\Ventas\Gastronomia\GastronomiaVentasSoloErpSupport;
use App\Support\Ventas\PedidoFacturaAnitaArchivosSupport;
use App\Support\Ventas\VentaCtamovAnitaVerificadorSupport as CtamovVerificador;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Ventas presentes en ERP sin cabecera en Anita: sincronizar (nunca borrar).
 *
 * Además verifica que el asiento ERP de cada venta esté en `ctamov` de Anita (con la clave del
 * comprobante) y, con --ejecutar, lo graba si falta o tiene la clave en blanco. La réplica de la
 * venta (`replicarVentaErpEnAnita`) no escribe asientos: sin esta fase el mayor queda sin la venta.
 *
 * Sustituye el saneamiento destructivo “solo ERP → delete”. Las jornadas POS
 * intencionalmente solo-ERP (GASTRONOMIA_VENTAS_SOLO_ERP_DESDE) se omiten salvo
 * que se pida incluirlas.
 */
final class VentaSincronizarSoloErpAnitaService
{
    public function __construct(
        private FacturacionService $facturacionService,
        private GastronomiaChequeoVentasAnitaErpService $chequeoAnita,
    ) {
    }

    /**
     * @param  list<int>  $puntoventaIds
     * @param  list<int>  $ventaIds
     * @return array{
     *   candidatas:int,
     *   ya_en_anita:int,
     *   omitidas_intencionales:int,
     *   replicadas:int,
     *   errores:list<array<string,mixed>>,
     *   detalle:list<array<string,mixed>>
     * }
     */
    public function sincronizar(
        string $fechaDesde,
        string $fechaHasta,
        bool $dryRun = true,
        int $empresaId = 0,
        array $puntoventaIds = [],
        array $ventaIds = [],
        int $limite = 0,
        bool $incluirGastroIntencional = false,
        bool $verificarCtamov = true,
    ): array {
        $resultado = [
            'candidatas' => 0,
            'ya_en_anita' => 0,
            'omitidas_intencionales' => 0,
            'replicadas' => 0,
            'ctamov_ok' => 0,
            'ctamov_a_grabar' => 0,
            'ctamov_grabados' => 0,
            'ctamov_revisar' => 0,
            'errores' => [],
            'detalle' => [],
        ];

        $query = Venta::query()
            ->with(['puntoventas:id,codigo,empresa_id,modofacturacion', 'condicionivas:id,letra', 'tipotransacciones:id,codigo,signo'])
            ->whereBetween('fecha', [$fechaDesde, $fechaHasta])
            ->orderBy('puntoventa_id')
            ->orderBy('numerocomprobante');

        if ($ventaIds !== []) {
            $query->whereIn('id', $ventaIds);
        }
        if ($puntoventaIds !== []) {
            $query->whereIn('puntoventa_id', $puntoventaIds);
        }
        if ($empresaId > 0) {
            $query->whereHas('puntoventas', static fn ($q) => $q->where('empresa_id', $empresaId));
        }

        $ventas = $query->get();
        $procesadas = 0;

        foreach ($ventas as $venta) {
            if ($limite > 0 && $procesadas >= $limite) {
                break;
            }

            $fila = [
                'venta_id' => (int) $venta->id,
                'codigo' => (string) $venta->codigo,
                'fecha' => (string) $venta->fecha,
                'pv' => (string) ($venta->puntoventas->codigo ?? ''),
                'total' => round((float) $venta->total, 2),
                'estado' => 'pendiente',
                'mensaje' => '',
            ];

            $letra = $this->resolverLetra($venta);
            try {
                $consulta = $this->chequeoAnita->consultarCabeceraAnitaDesdeVenta($venta, $letra);
            } catch (\Throwable $e) {
                $fila['estado'] = 'error_lectura_anita';
                $fila['mensaje'] = $e->getMessage();
                $resultado['errores'][] = $fila;
                $resultado['detalle'][] = $fila;
                $procesadas++;

                continue;
            }

            if (($consulta['error_lectura'] ?? null) === null && ($consulta['cabecera'] ?? null) === null) {
                // El chequeo compartido mira solo el sistema por defecto; los PV de Villafranca viven en otro path.
                $consulta = $this->reconsultarCabeceraSegunPath($venta, $letra, $consulta);
            }

            if (($consulta['error_lectura'] ?? null) !== null) {
                $fila['estado'] = 'error_lectura_anita';
                $fila['mensaje'] = (string) $consulta['error_lectura'];
                $resultado['errores'][] = $fila;
                $resultado['detalle'][] = $fila;
                $procesadas++;

                continue;
            }

            if (($consulta['cabecera'] ?? null) !== null) {
                $resultado['ya_en_anita']++;
                $fila['estado'] = 'ya_en_anita';
                $resultado['detalle'][] = $fila;
                $procesadas++;

                continue;
            }

            $resultado['candidatas']++;

            $empresaPv = (int) ($venta->puntoventas->empresa_id ?? 0);
            $fechaJornada = (string) ($venta->fechajornada ?: $venta->fecha);
            if (! $incluirGastroIntencional
                && $this->esPuntoventaPos($venta)
                && GastronomiaVentasSoloErpSupport::esJornada($empresaPv, $fechaJornada)
            ) {
                $resultado['omitidas_intencionales']++;
                $fila['estado'] = 'omitida_solo_erp_intencional';
                $fila['mensaje'] = 'POS en ventana solo-ERP (no se replica a Anita).';
                $resultado['detalle'][] = $fila;
                $procesadas++;

                continue;
            }

            if ($dryRun) {
                $resultado['replicadas']++;
                $fila['estado'] = 'simulado_replica';
                $resultado['detalle'][] = $fila;
                $procesadas++;

                continue;
            }

            try {
                $rep = $this->facturacionService->replicarVentaErpEnAnita((int) $venta->id);
                if (! empty($rep['ok'])) {
                    if (! empty($rep['ya_en_anita'])) {
                        $resultado['ya_en_anita']++;
                        $fila['estado'] = 'ya_en_anita';
                    } else {
                        $resultado['replicadas']++;
                        $fila['estado'] = 'replicada';
                    }
                } else {
                    $fila['estado'] = 'error';
                    $fila['mensaje'] = (string) ($rep['error'] ?? 'Error desconocido');
                    $resultado['errores'][] = $fila;
                    Log::error('venta.sincronizar_solo_erp_anita.fallo', [
                        'venta_id' => $venta->id,
                        'codigo' => $venta->codigo,
                        'msg' => $fila['mensaje'],
                    ]);
                }
            } catch (\Throwable $e) {
                $fila['estado'] = 'error';
                $fila['mensaje'] = $e->getMessage();
                $resultado['errores'][] = $fila;
                Log::error('venta.sincronizar_solo_erp_anita.excepcion', [
                    'venta_id' => $venta->id,
                    'msg' => $e->getMessage(),
                ]);
            }

            $resultado['detalle'][] = $fila;
            $procesadas++;
        }

        if ($verificarCtamov) {
            $this->verificarYGrabarCtamov($resultado, $ventas, $dryRun);
        }

        return $resultado;
    }

    /**
     * Fase ctamov: sobre las ventas que ya están (o quedaron) en Anita, comprueba el asiento ERP.
     * Dry-run: informa qué grabaría. Ejecutar: graba solo lo faltante o con clave en blanco.
     *
     * @param  array<string,mixed>  $resultado
     * @param  \Illuminate\Support\Collection<int,Venta>  $ventas
     */
    private function verificarYGrabarCtamov(array &$resultado, $ventas, bool $dryRun): void
    {
        $porId = $ventas->keyBy('id');
        $items = [];
        $indices = [];

        foreach ($resultado['detalle'] as $idx => $fila) {
            $estado = (string) ($fila['estado'] ?? '');
            if (! in_array($estado, ['ya_en_anita', 'replicada'], true)) {
                if ($estado === 'simulado_replica') {
                    $resultado['detalle'][$idx]['ctamov'] = 'se_evalua_tras_replicar';
                }

                continue;
            }

            /** @var Venta|null $venta */
            $venta = $porId->get((int) $fila['venta_id']);
            if ($venta === null) {
                continue;
            }

            $items[] = [
                'venta_id' => (int) $venta->id,
                'tipo' => substr((string) $venta->codigo, 0, 3),
                'letra' => $this->resolverLetra($venta),
                'sucursal' => (int) ($venta->puntoventas->codigo ?? 0),
                'nro' => (int) $venta->numerocomprobante,
            ];
            $indices[(int) $venta->id] = $idx;
        }

        if ($items === []) {
            return;
        }

        $evaluadas = CtamovVerificador::evaluar($items);
        $itemsPorVenta = [];
        foreach ($items as $it) {
            $itemsPorVenta[$it['venta_id']] = $it;
        }

        foreach ($evaluadas as $ventaId => $ev) {
            $idx = $indices[$ventaId] ?? null;
            if ($idx === null) {
                continue;
            }

            $estado = $ev['estado'];
            $resultado['detalle'][$idx]['ctamov'] = $estado;
            $resultado['detalle'][$idx]['asiento'] = $ev['numeroasiento'];
            if ($ev['mensaje'] !== '') {
                $resultado['detalle'][$idx]['mensaje'] = trim(($resultado['detalle'][$idx]['mensaje'] ?? '').' '.$ev['mensaje']);
            }

            if ($estado === CtamovVerificador::OK) {
                $resultado['ctamov_ok']++;

                continue;
            }

            if (! in_array($estado, CtamovVerificador::estadosReparables(), true)) {
                if ($estado === CtamovVerificador::ERROR_LECTURA) {
                    $resultado['errores'][] = $resultado['detalle'][$idx];
                } elseif (! in_array($estado, [CtamovVerificador::SIN_ASIENTO, CtamovVerificador::NATIVO_ANITA], true)) {
                    $resultado['ctamov_revisar']++;
                }

                continue;
            }

            $resultado['ctamov_a_grabar']++;
            if ($dryRun) {
                $resultado['detalle'][$idx]['ctamov'] = $estado === CtamovVerificador::FALTANTE
                    ? 'simulado_grabar'
                    : 'simulado_reparar_clave';

                continue;
            }

            $it = $itemsPorVenta[$ventaId];
            try {
                $this->facturacionService->sincronizarCtamovAnitaDeVenta(
                    $ventaId,
                    $it['tipo'],
                    $it['letra'],
                    $it['sucursal'],
                    $it['nro'],
                );
                $despues = CtamovVerificador::evaluar([$it])[$ventaId] ?? null;
                if ($despues !== null && $despues['estado'] === CtamovVerificador::OK) {
                    $resultado['ctamov_grabados']++;
                    $resultado['detalle'][$idx]['ctamov'] = 'grabado';
                } else {
                    $resultado['detalle'][$idx]['ctamov'] = 'error_verificacion';
                    $resultado['detalle'][$idx]['mensaje'] = 'Se grabó pero ctamov quedó en estado '
                        .($despues['estado'] ?? 'desconocido').'.';
                    $resultado['errores'][] = $resultado['detalle'][$idx];
                }
            } catch (\Throwable $e) {
                $resultado['detalle'][$idx]['ctamov'] = 'error_grabar';
                $resultado['detalle'][$idx]['mensaje'] = $e->getMessage();
                $resultado['errores'][] = $resultado['detalle'][$idx];
                Log::error('venta.sincronizar_solo_erp_anita.ctamov_fallo', [
                    'venta_id' => $ventaId,
                    'msg' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Si el PV pertenece a la división Villafranca, busca la cabecera de venta en ese path.
     * Sin esto una factura que ya está en Anita (Villafranca) figura como «solo ERP» y se replicaría de nuevo.
     *
     * @param  array<string,mixed>  $consulta
     * @return array<string,mixed>
     */
    private function reconsultarCabeceraSegunPath(Venta $venta, string $letra, array $consulta): array
    {
        $sucursal = (int) ($venta->puntoventas->codigo ?? 0);
        $path = PedidoFacturaAnitaArchivosSupport::pathSistemaParaSucursal($sucursal);
        if ($path === null) {
            return $consulta;
        }

        $data = [
            'acc' => 'list',
            'tabla' => 'venta',
            'sistema' => 'ventas',
            'campos' => 'ven_tipo,ven_letra,ven_sucursal,ven_nro',
            'whereArmado' => " WHERE ven_tipo = '".str_replace("'", "''", substr((string) $venta->codigo, 0, 3))."'"
                ." AND ven_letra = '".str_replace("'", "''", $letra)."'"
                .' AND ven_sucursal = '.$sucursal
                .' AND ven_nro = '.(int) $venta->numerocomprobante.' ',
            'path_sistema' => $path,
        ];

        $api = new ApiAnita();
        // Dos lecturas antes de decir que no está: un vacío del bridge bajo carga duplicaría la factura.
        for ($intento = 1; $intento <= 2; $intento++) {
            $parsed = ApiAnita::parsearRespuestaLista((string) $api->apiCall($data));
            if ($parsed['error_lectura'] !== null) {
                return ['cabecera' => null, 'error_lectura' => (string) $parsed['error_lectura']];
            }
            if ($parsed['filas'] !== []) {
                return ['cabecera' => (object) (array) $parsed['filas'][0], 'error_lectura' => null];
            }
        }

        return $consulta;
    }

    private function resolverLetra(Venta $venta): string
    {
        $codigo = trim((string) ($venta->codigo ?? ''));
        if (preg_match('/\s+([A-Z])(?:[\s\-]|$)/', $codigo, $m)) {
            return $m[1];
        }
        $letra = trim((string) ($venta->condicionivas->letra ?? ''));

        return $letra !== '' ? $letra : 'A';
    }

    private function esPuntoventaPos(Venta $venta): bool
    {
        $pvId = (int) $venta->puntoventa_id;
        if ($pvId <= 0) {
            return false;
        }

        if (Schema::hasTable('configuracion_puntoventa_gastronomia')) {
            $q = DB::table('configuracion_puntoventa_gastronomia')
                ->where('puntoventa_cae_id', $pvId);
            if (Schema::hasColumn('configuracion_puntoventa_gastronomia', 'puntoventa_caea_id')) {
                $q->orWhere('puntoventa_caea_id', $pvId);
            }
            if ($q->exists()) {
                return true;
            }
        }

        if (Schema::hasTable('configuracion_puntoventa_estacionamiento')) {
            $q = DB::table('configuracion_puntoventa_estacionamiento')
                ->where('puntoventa_cae_id', $pvId);
            if (Schema::hasColumn('configuracion_puntoventa_estacionamiento', 'puntoventa_caea_id')) {
                $q->orWhere('puntoventa_caea_id', $pvId);
            }
            if ($q->exists()) {
                return true;
            }
        }

        return false;
    }
}
