<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Mail\Ventas\FacturacionHuecoArcaMail;
use App\Models\Configuracion\Impuesto;
use App\Models\Configuracion\Moneda;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\FacturacionHuecoArca;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use App\Services\Arca\ArcaWsfeFacturaElectronicaService;
use App\Support\Ventas\TipotransaccionCodigoAfipSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Busca números que ARCA autorizó y el ERP no tiene, y los graba en el ERP
 * (cabecera, IVA, cuenta corriente y asiento). No mueve stock ni escribe Anita.
 * Sin ítems del comprobante original, arma un renglón genérico con el neto y el IVA de ARCA.
 */
final class FacturacionHuecoArcaCargaService
{
    private const TOLERANCIA_TOTAL = 0.05;

    public function __construct(
        private readonly ArcaWsfeFacturaElectronicaService $arca,
        private readonly FacturacionService $facturacion,
    ) {
    }

    /**
     * @param  array{
     *   dry_run?: bool,
     *   dias?: int,
     *   sin_mail?: bool,
     *   puntoventa?: ?string,
     *   tipo?: ?int,
     *   numero?: ?int,
     *   nombre?: ?string
     * }  $opciones
     * @return array{
     *   candidatos: int,
     *   resultados: list<array<string, mixed>>,
     *   pendientes: int,
     *   mail: string
     * }
     */
    public function ejecutar(array $opciones = []): array
    {
        $dryRun = (bool) ($opciones['dry_run'] ?? false);
        $sinMail = $dryRun || (bool) ($opciones['sin_mail'] ?? false);
        $diasPedido = $opciones['dias'] ?? null;
        $dias = ($diasPedido === null || $diasPedido === '')
            ? max(1, (int) config('arca.huecos_facturacion.dias', 21))
            : max(1, (int) $diasPedido);
        $max = max(1, (int) config('arca.huecos_facturacion.max_por_corrida', 40));

        $candidatos = $this->candidatos($dias, $opciones);
        $pendientes = max(0, count($candidatos) - $max);
        $lote = array_slice($candidatos, 0, $max);
        $resultados = [];

        foreach ($lote as $candidato) {
            $resultados[] = $this->procesar($candidato, $dryRun, $dias);
        }

        $mail = 'no enviado';
        if (! $sinMail) {
            $mail = $this->avisar($resultados);
        }

        return [
            'candidatos' => count($candidatos),
            'resultados' => $resultados,
            'pendientes' => $pendientes,
            'mail' => $mail,
        ];
    }

    /**
     * @param  array{puntoventa?: ?string, tipo?: ?int, numero?: ?int}  $opciones
     * @return list<array{puntoventa: Puntoventa, codigo_afip: int, numero: int}>
     */
    private function candidatos(int $dias, array $opciones): array
    {
        $numero = (int) ($opciones['numero'] ?? 0);
        $tipo = (int) ($opciones['tipo'] ?? 0);
        $pvFiltro = trim((string) ($opciones['puntoventa'] ?? ''));
        $excluidos = $this->idsPuntoVentaExcluidos();
        $desde = Carbon::today()->subDays($dias)->toDateString();
        $punta = max(1, (int) config('arca.huecos_facturacion.punta', 15));
        $nombreReceptor = trim((string) ($opciones['nombre'] ?? ''));

        $pvs = Puntoventa::query()
            ->whereIn('modofacturacion', ['C', 'E'])
            ->where('webservice', 'wsfev1')
            ->orderBy('codigo')
            ->get();

        if ($pvFiltro !== '') {
            $codigo = str_pad(ltrim($pvFiltro, '0') === '' ? '0' : (string) (int) $pvFiltro, 5, '0', STR_PAD_LEFT);
            $pvs = $pvs->filter(function (Puntoventa $pv) use ($pvFiltro, $codigo): bool {
                return (string) $pv->codigo === $pvFiltro
                    || (string) $pv->codigo === $codigo
                    || (int) $pv->codigo === (int) $pvFiltro;
            })->values();
        }

        $salida = [];
        foreach ($pvs as $pv) {
            if ($numero > 0) {
                if ($tipo <= 0) {
                    continue;
                }
                $salida[] = [
                    'puntoventa' => $pv,
                    'codigo_afip' => $tipo,
                    'numero' => $numero,
                    'forzado' => true,
                    'nombre_receptor' => $nombreReceptor,
                    'es_local' => in_array((int) $pv->id, $excluidos, true),
                ];
                continue;
            }

            $filas = DB::table('venta')
                ->where('puntoventa_id', $pv->id)
                ->where('fecha', '>=', $desde)
                ->where('codigo_afip', '>', 0)
                ->get(['codigo_afip', 'codigo', 'numerocomprobante']);

            $porTipo = [];
            foreach ($filas as $fila) {
                $codigoAfip = TipotransaccionCodigoAfipSupport::codigoAfipDesdeVentaGrabada(
                    (int) $fila->codigo_afip,
                    (string) $fila->codigo,
                );
                if ($codigoAfip <= 0 || ($tipo > 0 && $codigoAfip !== $tipo)) {
                    continue;
                }
                $porTipo[$codigoAfip][(int) $fila->numerocomprobante] = true;
            }

            $revisados = [];
            foreach ($porTipo as $codigoAfip => $numeros) {
                $minimo = min(array_keys($numeros));
                $maximo = max(array_keys($numeros));
                $hasta = $maximo;
                try {
                    $ultimo = $this->arca->feCompUltimoAutorizado((int) $pv->empresa_id, (int) $pv->codigo, $codigoAfip);
                    $hasta = max($maximo, min($ultimo, $maximo + $punta));
                } catch (Throwable $e) {
                    Log::warning('facturacion.hueco_arca.ultimo', [
                        'puntoventa' => $pv->codigo,
                        'codigo_afip' => $codigoAfip,
                        'error' => $e->getMessage(),
                    ]);
                }
                if ($minimo <= 0 || $hasta < $minimo || ($hasta - $minimo) > 5000) {
                    continue;
                }

                $tiene = $numeros;
                $anteriores = DB::table('venta')
                    ->where('puntoventa_id', $pv->id)
                    ->where('codigo_afip', '>', 0)
                    ->whereBetween('numerocomprobante', [$minimo, $hasta])
                    ->get(['codigo_afip', 'codigo', 'numerocomprobante']);
                foreach ($anteriores as $fila) {
                    $tipoFila = TipotransaccionCodigoAfipSupport::codigoAfipDesdeVentaGrabada(
                        (int) $fila->codigo_afip,
                        (string) $fila->codigo,
                    );
                    if ($tipoFila === (int) $codigoAfip) {
                        $tiene[(int) $fila->numerocomprobante] = true;
                    }
                }

                $resueltos = FacturacionHuecoArca::query()
                    ->where('puntoventa_id', $pv->id)
                    ->where('codigo_afip', $codigoAfip)
                    ->whereIn('estado', [
                        FacturacionHuecoArca::CARGADO,
                        FacturacionHuecoArca::INEXISTENTE,
                        FacturacionHuecoArca::OMITIDO,
                    ])
                    ->pluck('numerocomprobante')
                    ->all();
                foreach ($resueltos as $nro) {
                    $tiene[(int) $nro] = true;
                }

                for ($n = $minimo; $n <= $hasta; $n++) {
                    if (! isset($tiene[$n])) {
                        $salida[] = [
                            'puntoventa' => $pv,
                            'codigo_afip' => (int) $codigoAfip,
                            'numero' => $n,
                            'forzado' => false,
                            'en_punta' => $n > $maximo,
                        ];
                    }
                }
                $revisados[(int) $codigoAfip] = true;
            }

            $this->agregarPuntaSerieQuieta($pv, $tipo, $punta, $revisados, $salida);
        }

        usort($salida, static function (array $a, array $b): int {
            $pa = self::prioridadTipo((int) $a['codigo_afip']);
            $pb = self::prioridadTipo((int) $b['codigo_afip']);
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            return ((int) $a['numero']) <=> ((int) $b['numero']);
        });

        return $salida;
    }

    /**
     * Si la serie no tuvo comprobantes en la ventana, igual se compara el último
     * número del ERP contra el último autorizado en ARCA. Así entra una NC que
     * ARCA emitió después del último comprobante local, aunque ese último sea viejo.
     *
     * @param  array<int, true>  $revisados
     * @param  list<array<string, mixed>>  $salida
     */
    private function agregarPuntaSerieQuieta(Puntoventa $pv, int $tipoFiltro, int $punta, array $revisados, array &$salida): void
    {
        $filas = DB::table('venta')
            ->where('puntoventa_id', $pv->id)
            ->where('codigo_afip', '>', 0)
            ->groupBy('codigo_afip', DB::raw("SUBSTRING_INDEX(codigo, '-', 1)"))
            ->selectRaw("codigo_afip, SUBSTRING_INDEX(codigo, '-', 1) as prefijo, MAX(numerocomprobante) as maximo")
            ->get();

        $maxPorTipo = [];
        foreach ($filas as $fila) {
            $codigoAfip = TipotransaccionCodigoAfipSupport::codigoAfipDesdeVentaGrabada(
                (int) $fila->codigo_afip,
                (string) $fila->prefijo.'-00000-00000000',
            );
            if ($codigoAfip <= 0 || ($tipoFiltro > 0 && $codigoAfip !== $tipoFiltro)) {
                continue;
            }
            $maxPorTipo[$codigoAfip] = max($maxPorTipo[$codigoAfip] ?? 0, (int) $fila->maximo);
        }

        $ya = [];
        foreach ($salida as $candidato) {
            if ((int) $candidato['puntoventa']->id === (int) $pv->id) {
                $ya[(int) $candidato['codigo_afip'].'|'.(int) $candidato['numero']] = true;
            }
        }

        foreach ($maxPorTipo as $codigoAfip => $maximo) {
            if ($maximo <= 0 || isset($revisados[$codigoAfip])) {
                continue;
            }
            try {
                $ultimo = $this->arca->feCompUltimoAutorizado((int) $pv->empresa_id, (int) $pv->codigo, (int) $codigoAfip);
            } catch (Throwable $e) {
                Log::warning('facturacion.hueco_arca.ultimo', [
                    'puntoventa' => $pv->codigo,
                    'codigo_afip' => $codigoAfip,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
            $hasta = min((int) $ultimo, $maximo + $punta);
            if ($hasta <= $maximo) {
                continue;
            }

            $resueltos = FacturacionHuecoArca::query()
                ->where('puntoventa_id', $pv->id)
                ->where('codigo_afip', $codigoAfip)
                ->whereBetween('numerocomprobante', [$maximo + 1, $hasta])
                ->whereIn('estado', [
                    FacturacionHuecoArca::CARGADO,
                    FacturacionHuecoArca::INEXISTENTE,
                    FacturacionHuecoArca::OMITIDO,
                ])
                ->pluck('numerocomprobante')
                ->all();
            $saltear = [];
            foreach ($resueltos as $nro) {
                $saltear[(int) $nro] = true;
            }

            for ($n = $maximo + 1; $n <= $hasta; $n++) {
                if (isset($ya[$codigoAfip.'|'.$n]) || isset($saltear[$n])) {
                    continue;
                }
                $salida[] = [
                    'puntoventa' => $pv,
                    'codigo_afip' => (int) $codigoAfip,
                    'numero' => $n,
                    'forzado' => false,
                    'en_punta' => true,
                ];
            }
        }
    }

    private static function prioridadTipo(int $codigoAfip): int
    {
        $base = $codigoAfip >= 200 && $codigoAfip < 250 ? $codigoAfip - 200 : $codigoAfip;

        return match (true) {
            in_array($base, [3, 8, 13, 53], true) => 0,
            in_array($base, [2, 7, 12, 52], true) => 1,
            default => 2,
        };
    }

    /**
     * @param  array{puntoventa: Puntoventa, codigo_afip: int, numero: int}  $candidato
     * @return array<string, mixed>
     */
    private function procesar(array $candidato, bool $dryRun, int $dias): array
    {
        /** @var Puntoventa $pv */
        $pv = $candidato['puntoventa'];
        $codigoAfip = (int) $candidato['codigo_afip'];
        $numero = (int) $candidato['numero'];
        $base = [
            'puntoventa_id' => (int) $pv->id,
            'puntoventa' => (string) $pv->codigo,
            'empresa_id' => (int) $pv->empresa_id,
            'tipo' => TipotransaccionCodigoAfipSupport::etiqueta($codigoAfip),
            'codigo_afip' => $codigoAfip,
            'numero' => $numero,
            'notificar' => false,
        ];

        $ya = $this->ventaYaGrabada($pv, $codigoAfip, $numero);
        if ($ya !== null) {
            return $base + [
                'estado' => 'ya_existe',
                'detalle' => 'Ya está en el ERP: '.$ya->codigo,
                'venta_id' => (int) $ya->id,
            ];
        }

        try {
            $consulta = $this->consultar($pv, $codigoAfip, $numero);
        } catch (Throwable $e) {
            return $this->cerrar($base, FacturacionHuecoArca::ERROR, $e->getMessage(), $dryRun, true);
        }

        if ($consulta['estado'] !== 'autorizado') {
            $estadoConsulta = (string) $consulta['estado'];
            $detalleConsulta = (string) ($consulta['detalle'] ?? '');
            $notificarConsulta = $estadoConsulta === FacturacionHuecoArca::ERROR
                || ($estadoConsulta === FacturacionHuecoArca::OMITIDO && str_contains($detalleConsulta, 'percepciones'));
            $extra = isset($consulta['arca']) && is_array($consulta['arca'])
                ? $this->datosArca($consulta['arca'])
                : [];

            return $this->cerrar($base + $extra, $estadoConsulta, $detalleConsulta, $dryRun, $notificarConsulta);
        }

        $arca = $consulta['arca'];
        $fecha = (string) $arca['fecha'];
        $corte = Carbon::today()->subDays($dias)->toDateString();
        if ($fecha !== '' && $fecha < $corte && empty($candidato['forzado']) && empty($candidato['en_punta'])) {
            return $this->cerrar($base, FacturacionHuecoArca::OMITIDO, 'Fecha ARCA '.$fecha.' anterior a la ventana.', $dryRun, false);
        }

        try {
            $armado = $this->armar($pv, $codigoAfip, $numero, $arca, $candidato);
        } catch (Throwable $e) {
            $esTributo = str_contains($e->getMessage(), 'percepciones');
            return $this->cerrar(
                $base + $this->datosArca($arca),
                $esTributo ? FacturacionHuecoArca::OMITIDO : FacturacionHuecoArca::ERROR,
                $e->getMessage(),
                $dryRun,
                true,
            );
        }

        if ($dryRun) {
            try {
                $calculo = $this->facturacion->calculaFacturaGeneral($armado['payload']);
            } catch (Throwable $e) {
                return $base + $this->datosArca($arca) + [
                    'estado' => 'preview_error',
                    'detalle' => $e->getMessage(),
                    'notificar' => false,
                ];
            }
            if (! is_array($calculo) || isset($calculo['error'])) {
                return $base + $this->datosArca($arca) + [
                    'estado' => 'preview_error',
                    'detalle' => (string) ($calculo['error'] ?? 'No se pudo calcular el comprobante.'),
                    'notificar' => false,
                ];
            }
            $total = round(abs((float) ($calculo['totalcomprobante'] ?? 0)), 2);
            $diff = abs($total - (float) $arca['imp_total']);
            return $base + $this->datosArca($arca) + [
                'estado' => $diff <= self::TOLERANCIA_TOTAL ? 'preview' : 'preview_error',
                'detalle' => $diff <= self::TOLERANCIA_TOTAL
                    ? 'Se cargaría '.$armado['cliente'].' por '.$total
                    : 'El total calculado '.$total.' no cierra con ARCA '.$arca['imp_total'],
                'total' => $total,
                'cliente' => $armado['cliente'],
                'notificar' => false,
            ];
        }

        try {
            $cargado = DB::transaction(function () use ($armado, $arca): array {
                $resultado = $this->facturacion->generaComprobanteGeneral($armado['payload']);
                if (! is_array($resultado) || ! empty($resultado['error'])) {
                    throw new \RuntimeException((string) ($resultado['mensaje'] ?? $resultado['error'] ?? 'Error al grabar la venta.'));
                }
                $ventaId = (int) ($resultado['venta_id'] ?? 0);
                $venta = Venta::query()->find($ventaId);
                if ($venta === null) {
                    throw new \RuntimeException('La grabación no devolvió la venta.');
                }
                $diff = abs(round(abs((float) $venta->total), 2) - (float) $arca['imp_total']);
                if ($diff > self::TOLERANCIA_TOTAL) {
                    throw new \RuntimeException(sprintf(
                        'Total ERP %.2f no cierra con ARCA %.2f.',
                        abs((float) $venta->total),
                        (float) $arca['imp_total'],
                    ));
                }
                $pendiente = $resultado['cae_pendiente'] ?? null;
                if (! is_array($pendiente)) {
                    throw new \RuntimeException('Faltó el CAE pendiente para aplicar el de ARCA.');
                }
                $this->facturacion->completarSolicitudCaePendiente(
                    $pendiente,
                    false,
                    [
                        'cae' => (string) $arca['cae'],
                        'fechavencimientocae' => (string) $arca['vto'],
                    ],
                    true,
                );
                $venta->refresh();

                return [
                    'venta_id' => (int) $venta->id,
                    'codigo' => (string) $venta->codigo,
                    'total' => round((float) $venta->total, 2),
                    'cae' => (string) $venta->cae,
                ];
            });
        } catch (Throwable $e) {
            Log::error('facturacion.hueco_arca.carga', [
                'puntoventa' => $pv->codigo,
                'codigo_afip' => $codigoAfip,
                'numero' => $numero,
                'error' => $e->getMessage(),
            ]);

            return $this->cerrar($base + $this->datosArca($arca), FacturacionHuecoArca::ERROR, $e->getMessage(), false, true);
        }

        Log::info('facturacion.hueco_arca.cargado', $cargado + [
            'puntoventa' => $pv->codigo,
            'numero' => $numero,
        ]);

        return $this->cerrar($base + $this->datosArca($arca) + $cargado + [
            'cliente' => $armado['cliente'],
            'fecha' => $fecha,
            'url_pdf' => urlAppAbsoluta('ventas/listaunafacturapdf/'.$cargado['venta_id']),
            'url_editar' => urlAppAbsoluta('ventas/factura/'.$cargado['venta_id'].'/editar'),
        ], FacturacionHuecoArca::CARGADO, 'Cargado '.$cargado['codigo'], false, true);
    }

    /**
     * @return array{estado: string, detalle?: string, arca?: array<string, mixed>}
     */
    private function consultar(Puntoventa $pv, int $codigoAfip, int $numero): array
    {
        $result = $this->arca->feCompConsultar((int) $pv->empresa_id, (int) $pv->codigo, $codigoAfip, $numero);
        $errores = $this->erroresArca($result);
        $rg = $result->ResultGet ?? null;
        if ($rg === null) {
            $texto = $errores === [] ? 'ARCA no devolvió el comprobante.' : implode(' | ', $errores);
            $inexistente = $errores === [] || $this->esInexistente($texto);

            return [
                'estado' => $inexistente ? FacturacionHuecoArca::INEXISTENTE : FacturacionHuecoArca::ERROR,
                'detalle' => $texto,
            ];
        }

        $resultado = strtoupper(trim((string) ($rg->Resultado ?? '')));
        if (! in_array($resultado, ['A', 'P'], true)) {
            return [
                'estado' => FacturacionHuecoArca::OMITIDO,
                'detalle' => 'Resultado ARCA '.$resultado,
            ];
        }

        $cae = trim((string) ($rg->CodAutorizacion ?? ''));
        $vto = trim((string) ($rg->FchVto ?? ''));
        if ($cae === '' || $vto === '') {
            return [
                'estado' => FacturacionHuecoArca::ERROR,
                'detalle' => 'ARCA no informó CAE o vencimiento.',
            ];
        }

        $tributo = round((float) ($rg->ImpTrib ?? 0), 2);
        if ($tributo > self::TOLERANCIA_TOTAL) {
            return [
                'estado' => FacturacionHuecoArca::OMITIDO,
                'detalle' => 'Tiene percepciones o tributos por '.number_format($tributo, 2, ',', '.').'. Hay que cargarlo a mano.',
                'arca' => $this->arcaDesdeResultado($rg, $cae, $vto),
            ];
        }

        return [
            'estado' => 'autorizado',
            'arca' => $this->arcaDesdeResultado($rg, $cae, $vto),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function arcaDesdeResultado(object $rg, string $cae, string $vto): array
    {
        return [
            'cae' => $cae,
            'vto' => $vto,
            'fecha' => $this->fechaArca((string) ($rg->CbteFch ?? '')),
            'imp_total' => round((float) ($rg->ImpTotal ?? 0), 2),
            'imp_neto' => round((float) ($rg->ImpNeto ?? 0), 2),
            'imp_iva' => round((float) ($rg->ImpIVA ?? 0), 2),
            'imp_op_ex' => round((float) ($rg->ImpOpEx ?? 0), 2),
            'imp_tot_conc' => round((float) ($rg->ImpTotConc ?? 0), 2),
            'doc_tipo' => (int) ($rg->DocTipo ?? 99),
            'doc_nro' => preg_replace('/\D+/', '', (string) ($rg->DocNro ?? '')) ?: '0',
            'mon_id' => strtoupper(trim((string) ($rg->MonId ?? 'PES'))),
            'mon_cotiz' => (float) ($rg->MonCotiz ?? 1),
            'alicuotas' => $this->alicuotas($rg),
            'asociado' => $this->textoAsociado($rg),
        ];
    }

    /**
     * @param  array<string, mixed>  $arca
     * @return array{payload: array<string, mixed>, cliente: string}
     */
    private function armar(Puntoventa $pv, int $codigoAfip, int $numero, array $arca, array $candidato = []): array
    {
        $receptor = null;
        try {
            $cliente = $this->resolverCliente((int) $arca['doc_tipo'], (string) $arca['doc_nro']);
        } catch (\RuntimeException $e) {
            $docNro = (string) $arca['doc_nro'];
            $mensaje = $e->getMessage();
            $sinFicha = str_contains($mensaje, 'Ningún cliente')
                || str_contains($mensaje, 'consumidor final')
                || str_contains($mensaje, 'clientes con el documento');
            if (! $sinFicha) {
                throw $e;
            }
            $cliente = Cliente::query()->where('codigo', '0')->orderBy('id')->first();
            if ($cliente === null) {
                throw $e;
            }
            $nombre = trim((string) ($candidato['nombre_receptor'] ?? ''));
            if ($nombre === '' && $docNro !== '' && $docNro !== '0') {
                $nombre = 'DNI '.$docNro;
            }
            if ($nombre === '') {
                $nombre = (string) $cliente->nombre;
            }
            $receptor = [
                'nombre' => $nombre,
                'numerodocumento' => ($docNro !== '' && $docNro !== '0') ? $docNro : '0',
            ];
        }
        $tipo = $this->resolverTipo($pv, $codigoAfip);
        $moneda = $this->resolverMoneda((string) $arca['mon_id']);
        $lineas = $this->lineas($arca);
        $fecha = (string) $arca['fecha'] !== '' ? (string) $arca['fecha'] : Carbon::today()->toDateString();
        $leyenda = 'Autorizado en ARCA y recuperado porque no había quedado en el ERP. No mueve stock.';
        if ((string) $arca['asociado'] !== '') {
            $leyenda .= ' '.$arca['asociado'];
        }
        $leyenda = mb_substr($leyenda, 0, 240);

        $n = count($lineas);
        $payload = [
            '_sin_guardar_preferencia' => true,
            'tipotransaccion_id' => (int) $tipo->id,
            'puntoventa_id' => (int) $pv->id,
            'fechafactura' => $fecha,
            'leyendafactura' => $leyenda,
            'cliente_id' => (int) $cliente->id,
            'moneda_id' => (int) $moneda['id'],
            'cotizacion' => $moneda['cotizacion'] > 0 ? $moneda['cotizacion'] : (float) ($arca['mon_cotiz'] ?: 1),
            'descuentopie' => 0,
            'descuentolinea' => 0,
            'descuentoimportepie' => 0,
            'articulo_ids' => array_fill(0, $n, 0),
            'cantidades' => array_fill(0, $n, 1),
            'precios' => array_column($lineas, 'base'),
            'descripcionarticulos' => array_column($lineas, 'detalle'),
            'impuesto_ids' => array_column($lineas, 'impuesto_id'),
            'incluyeimpuestos' => array_fill(0, $n, 'N'),
            'omitir_percepciones' => true,
            'numerocomprobante_forzado' => $numero,
            'letra_forzada' => $this->letraDesdeCodigoAfip($codigoAfip),
            'opciones_emision' => [
                'omitir_movimiento_stock' => true,
                'omitir_solicitud_arca_cae' => true,
                'omitir_sincronizacion_anita' => true,
                'omitir_stkmov_anita' => true,
                'omitir_numera_anita_fin' => true,
                'fechajornada' => $fecha,
            ],
        ];
        if ($receptor !== null) {
            $payload['venta_receptor'] = $receptor;
        }
        if ((int) ($pv->actividad_arca_id ?? 0) > 0) {
            $payload['actividad_arca_id'] = (int) $pv->actividad_arca_id;
        }
        if ((int) $moneda['id'] === 1) {
            $payload['cotizacion'] = 1;
        }

        return [
            'payload' => $payload,
            'cliente' => (string) ($receptor['nombre'] ?? $cliente->nombre),
        ];
    }

    /**
     * @param  array<string, mixed>  $arca
     * @return list<array{base: float, impuesto_id: int, detalle: string}>
     */
    private function lineas(array $arca): array
    {
        $lineas = [];
        foreach ($arca['alicuotas'] as $alicuota) {
            $base = round((float) $alicuota['base'], 2);
            if ($base <= 0) {
                continue;
            }
            $impuesto = Impuesto::query()->where('codigoarca', (string) $alicuota['id'])->orderBy('id')->first();
            if ($impuesto === null) {
                throw new \RuntimeException('No hay impuesto para la alícuota ARCA '.$alicuota['id'].'.');
            }
            $lineas[] = [
                'base' => $base,
                'impuesto_id' => (int) $impuesto->id,
                'detalle' => 'Recuperado de ARCA — '.$impuesto->nombre,
            ];
        }

        $exento = Impuesto::query()->where('codigoarca', '3')->orderBy('id')->first();
        $extra = round((float) $arca['imp_op_ex'] + (float) $arca['imp_tot_conc'], 2);
        if ($extra > self::TOLERANCIA_TOTAL) {
            if ($exento === null) {
                throw new \RuntimeException('ARCA trae exento o no gravado y no hay impuesto exento.');
            }
            $lineas[] = [
                'base' => $extra,
                'impuesto_id' => (int) $exento->id,
                'detalle' => 'Recuperado de ARCA — exento / no gravado',
            ];
        }

        if ($lineas === []) {
            $lineas = array_merge($lineas, $this->lineasGenericas($arca));
        }

        return $lineas;
    }

    /**
     * Sin el detalle del comprobante original: un renglón que cierra con el total de ARCA.
     *
     * @param  array<string, mixed>  $arca
     * @return list<array{base: float, impuesto_id: int, detalle: string}>
     */
    private function lineasGenericas(array $arca): array
    {
        $neto = round((float) ($arca['imp_neto'] ?? 0), 2);
        $iva = round((float) ($arca['imp_iva'] ?? 0), 2);
        $total = round((float) ($arca['imp_total'] ?? 0), 2);
        if ($neto > self::TOLERANCIA_TOTAL && $iva > self::TOLERANCIA_TOTAL) {
            $tasa = round($iva / $neto * 100, 2);
            $codigoArca = match (true) {
                abs($tasa - 10.5) < 0.2 => '4',
                abs($tasa - 27) < 0.2 => '6',
                abs($tasa - 21) < 0.2 => '5',
                default => '5',
            };
            $impuesto = Impuesto::query()->where('codigoarca', $codigoArca)->orderBy('id')->first();
            if ($impuesto === null) {
                throw new \RuntimeException('No hay impuesto para armar el renglón genérico.');
            }

            return [[
                'base' => $neto,
                'impuesto_id' => (int) $impuesto->id,
                'detalle' => 'Recuperado de ARCA — genérico',
            ]];
        }
        if ($total <= self::TOLERANCIA_TOTAL) {
            throw new \RuntimeException('ARCA no trajo alícuotas ni total para armar el comprobante.');
        }
        $exento = Impuesto::query()->where('codigoarca', '3')->orderBy('id')->first();
        if ($exento === null) {
            throw new \RuntimeException('ARCA no trajo IVA y no hay impuesto exento para el renglón genérico.');
        }

        return [[
            'base' => $total,
            'impuesto_id' => (int) $exento->id,
            'detalle' => 'Recuperado de ARCA — genérico',
        ]];
    }

    private function resolverCliente(int $docTipo, string $docNro): Cliente
    {
        if ($docTipo === 99 || $docNro === '' || $docNro === '0') {
            throw new \RuntimeException('ARCA lo emitió a consumidor final. No se puede asignar el cliente.');
        }

        $formas = [$docNro];
        if (strlen($docNro) === 11) {
            $formas[] = substr($docNro, 0, 2).'-'.substr($docNro, 2, 8).'-'.substr($docNro, 10, 1);
        }

        $clientes = Cliente::query()->whereIn('numerodocumento', $formas)->get();
        if ($clientes->count() === 1) {
            return $clientes->first();
        }
        if ($clientes->isEmpty()) {
            throw new \RuntimeException('Ningún cliente tiene el documento '.$docNro.'.');
        }

        throw new \RuntimeException('Hay '.$clientes->count().' clientes con el documento '.$docNro.'.');
    }

    private function resolverTipo(Puntoventa $pv, int $codigoAfip): Tipotransaccion
    {
        $usado = (int) DB::table('venta')
            ->where('puntoventa_id', $pv->id)
            ->where('codigo_afip', $codigoAfip)
            ->selectRaw('tipotransaccion_id, COUNT(*) as c')
            ->groupBy('tipotransaccion_id')
            ->orderByDesc('c')
            ->value('tipotransaccion_id');
        if ($usado > 0) {
            $tipo = Tipotransaccion::query()->find($usado);
            if ($tipo !== null) {
                return $tipo;
            }
        }

        $letra = $this->letraDesdeCodigoAfip($codigoAfip);
        $bases = $letra === '' ? [] : TipotransaccionCodigoAfipSupport::codigosBaseAlmacenadosPosibles($codigoAfip, $letra);
        $codigos = [];
        foreach ($bases as $base) {
            $codigos[] = (string) $base;
            $codigos[] = str_pad((string) $base, 3, '0', STR_PAD_LEFT);
        }
        $tipos = Tipotransaccion::query()
            ->where('estado', 'A')
            ->whereIn('codigo', array_values(array_unique($codigos)))
            ->get();
        $preferidas = ['FAC', 'NCD', 'NDB', 'FCE', 'NCE', 'DCE'];
        $elegido = null;
        foreach ($tipos as $tipo) {
            if ($letra !== '' && TipotransaccionCodigoAfipSupport::codigoAfipParaEmision($tipo->codigo, $letra) !== $codigoAfip) {
                continue;
            }
            if (in_array(strtoupper((string) $tipo->abreviatura), $preferidas, true)) {
                return $tipo;
            }
            $elegido ??= $tipo;
        }
        if ($elegido !== null) {
            return $elegido;
        }

        throw new \RuntimeException('No hay tipo de comprobante para el código ARCA '.$codigoAfip.'.');
    }

    /**
     * @return array{id: int, cotizacion: float}
     */
    private function resolverMoneda(string $monId): array
    {
        if (in_array($monId, ['', 'PES', 'ARS', '$'], true)) {
            return ['id' => 1, 'cotizacion' => 1.0];
        }
        $moneda = Moneda::query()->where('abreviatura', $monId)->first();
        if ($moneda === null) {
            throw new \RuntimeException('Moneda ARCA '.$monId.' sin equivalente en el ERP.');
        }

        return ['id' => (int) $moneda->id, 'cotizacion' => 0.0];
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private function cerrar(array $fila, string $estado, string $detalle, bool $dryRun, bool $notificar): array
    {
        $detalle = mb_substr(trim($detalle), 0, 500);
        $fila['estado'] = $estado;
        $fila['detalle'] = $detalle;
        $fila['notificar'] = false;

        if ($dryRun) {
            $fila['estado'] = 'preview_'.$estado;
            $fila['notificar'] = false;

            return $fila;
        }

        $previo = FacturacionHuecoArca::query()
            ->where('puntoventa_id', $fila['puntoventa_id'])
            ->where('codigo_afip', $fila['codigo_afip'])
            ->where('numerocomprobante', $fila['numero'])
            ->first();

        $repite = $previo !== null
            && $previo->estado === $estado
            && (string) $previo->detalle === $detalle
            && $previo->avisado_at !== null;

        $fila['notificar'] = $notificar && ! $repite && in_array($estado, [
            FacturacionHuecoArca::CARGADO,
            FacturacionHuecoArca::ERROR,
            FacturacionHuecoArca::OMITIDO,
        ], true);
        if ($estado === FacturacionHuecoArca::OMITIDO && ! str_contains($detalle, 'percepciones')) {
            $fila['notificar'] = false;
        }

        FacturacionHuecoArca::query()->updateOrCreate(
            [
                'puntoventa_id' => $fila['puntoventa_id'],
                'codigo_afip' => $fila['codigo_afip'],
                'numerocomprobante' => $fila['numero'],
            ],
            [
                'empresa_id' => $fila['empresa_id'],
                'estado' => $estado,
                'venta_id' => $fila['venta_id'] ?? null,
                'cae' => $fila['cae'] ?? null,
                'importe' => $fila['imp_total'] ?? ($fila['total'] ?? null),
                'fecha_comprobante' => ($fila['fecha'] ?? '') !== '' ? $fila['fecha'] : null,
                'detalle' => $detalle,
            ],
        );

        return $fila;
    }

    /**
     * @param  list<array<string, mixed>>  $resultados
     */
    private function avisar(array $resultados): string
    {
        $avisar = array_values(array_filter($resultados, fn (array $f): bool => ! empty($f['notificar'])));
        if ($avisar === []) {
            return 'sin novedades';
        }

        $informe = ['cargados' => [], 'errores' => [], 'omitidos' => []];
        foreach ($avisar as $fila) {
            $estado = (string) ($fila['estado'] ?? '');
            if ($estado === FacturacionHuecoArca::CARGADO) {
                $informe['cargados'][] = $fila;
            } elseif ($estado === FacturacionHuecoArca::OMITIDO) {
                $informe['omitidos'][] = $fila;
            } else {
                $informe['errores'][] = $fila;
            }
        }

        $destinos = $this->mails();
        if ($destinos === []) {
            Log::warning('facturacion.hueco_arca.sin_mails', ['cantidad' => count($avisar)]);

            return 'sin destinatarios';
        }

        try {
            Mail::to($destinos)->send(new FacturacionHuecoArcaMail($informe));
        } catch (Throwable $e) {
            if (! str_contains($e->getMessage(), 'tempnam')) {
                Log::error('facturacion.hueco_arca.mail', ['error' => $e->getMessage()]);

                return 'falló el mail: '.$e->getMessage();
            }
            try {
                $this->avisarHtmlPlano($destinos, $informe);
            } catch (Throwable $e2) {
                Log::error('facturacion.hueco_arca.mail', ['error' => $e2->getMessage()]);

                return 'falló el mail: '.$e2->getMessage();
            }
        }

        $claves = [];
        foreach ($avisar as $fila) {
            $claves[] = [(int) $fila['puntoventa_id'], (int) $fila['codigo_afip'], (int) $fila['numero']];
        }
        FacturacionHuecoArca::query()
            ->where(function ($q) use ($claves): void {
                foreach ($claves as [$pv, $tipo, $nro]) {
                    $q->orWhere(function ($q2) use ($pv, $tipo, $nro): void {
                        $q2->where('puntoventa_id', $pv)
                            ->where('codigo_afip', $tipo)
                            ->where('numerocomprobante', $nro);
                    });
                }
            })
            ->update(['avisado_at' => now()]);

        return 'enviado a '.implode(', ', $destinos);
    }

    /**
     * @param  list<string>  $destinos
     * @param  array{cargados: list<array<string, mixed>>, errores: list<array<string, mixed>>, omitidos: list<array<string, mixed>>}  $informe
     */
    private function avisarHtmlPlano(array $destinos, array $informe): void
    {
        $cargados = count($informe['cargados'] ?? []);
        $errores = count($informe['errores'] ?? []);
        $fecha = now()->format('d/m/Y');
        if ($cargados > 0 && $errores === 0) {
            $asunto = 'Comprobantes de ARCA recuperados en el ERP — '.$fecha;
        } elseif ($cargados > 0) {
            $asunto = 'Comprobantes de ARCA recuperados, con errores — '.$fecha;
        } else {
            $asunto = 'No se pudieron cargar comprobantes autorizados en ARCA — '.$fecha;
        }

        $html = '<p>El proceso diario encontró comprobantes autorizados en ARCA que no estaban en el ERP.</p>';
        foreach (['cargados' => 'Cargados', 'omitidos' => 'Sin carga automática', 'errores' => 'No se pudieron cargar'] as $clave => $titulo) {
            $filas = $informe[$clave] ?? [];
            if ($filas === []) {
                continue;
            }
            $html .= '<h3>'.e($titulo).'</h3><ul>';
            foreach ($filas as $fila) {
                $codigo = (string) ($fila['codigo'] ?? (($fila['puntoventa'] ?? '').' '.($fila['tipo'] ?? '').' '.($fila['numero'] ?? '')));
                $html .= '<li>'.e($codigo.' — '.($fila['detalle'] ?? '').' — '.($fila['cliente'] ?? ''));
                if (! empty($fila['url_editar'])) {
                    $html .= ' <a href="'.e((string) $fila['url_editar']).'">Abrir</a>';
                }
                $html .= '</li>';
            }
            $html .= '</ul>';
        }

        Mail::html($html, function ($message) use ($destinos, $asunto): void {
            $message->to($destinos)->subject($asunto);
        });
    }

    /**
     * @return list<string>
     */
    private function mails(): array
    {
        $raw = (string) config('arca.huecos_facturacion.mails', '');
        $partes = preg_split('/[,\s;]+/', $raw) ?: [];
        $out = [];
        foreach ($partes as $parte) {
            $mail = trim((string) $parte);
            if ($mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                $out[] = $mail;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  array<string, mixed>  $arca
     * @return array<string, mixed>
     */
    private function datosArca(array $arca): array
    {
        return [
            'cae' => $arca['cae'] ?? null,
            'fecha' => $arca['fecha'] ?? '',
            'imp_total' => $arca['imp_total'] ?? null,
        ];
    }

    /**
     * @return list<array{id: int, base: float, importe: float}>
     */
    private function alicuotas(object $rg): array
    {
        $iva = $rg->Iva ?? null;
        if ($iva === null) {
            return [];
        }
        $raw = is_object($iva) ? ($iva->AlicIva ?? $iva) : $iva;
        if (is_object($raw)) {
            $raw = [$raw];
        }
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            if (! is_object($item) || ! isset($item->Id)) {
                continue;
            }
            $out[] = [
                'id' => (int) $item->Id,
                'base' => (float) ($item->BaseImp ?? 0),
                'importe' => (float) ($item->Importe ?? 0),
            ];
        }

        return $out;
    }

    private function textoAsociado(object $rg): string
    {
        $nodo = $rg->CbtesAsoc->CbteAsoc ?? $rg->CbtesAsoc ?? null;
        if ($nodo === null) {
            return '';
        }
        if (is_object($nodo)) {
            $nodo = [$nodo];
        }
        if (! is_array($nodo)) {
            return '';
        }
        $partes = [];
        foreach ($nodo as $asoc) {
            if (! is_object($asoc)) {
                continue;
            }
            $tipo = (int) ($asoc->Tipo ?? 0);
            $pto = (int) ($asoc->PtoVta ?? 0);
            $nro = (int) ($asoc->Nro ?? 0);
            if ($tipo <= 0 || $nro <= 0) {
                continue;
            }
            $partes[] = 'Asociado en ARCA a tipo '.$tipo.' PV '.$pto.' nro '.$nro.'.';
        }

        return implode(' ', $partes);
    }

    /**
     * @return list<string>
     */
    private function erroresArca(object $result): array
    {
        $errors = $result->Errors->Err ?? $result->Errors ?? null;
        if ($errors === null) {
            return [];
        }
        if (is_object($errors)) {
            $errors = [$errors];
        }
        if (! is_array($errors)) {
            return [];
        }
        $out = [];
        foreach ($errors as $error) {
            if (! is_object($error)) {
                continue;
            }
            $out[] = trim((string) ($error->Code ?? '').' '.(string) ($error->Msg ?? ''));
        }

        return array_values(array_filter($out, fn (string $t): bool => $t !== ''));
    }

    private function esInexistente(string $texto): bool
    {
        $t = mb_strtolower($texto);

        return str_contains($t, '602')
            || str_contains($t, '601')
            || str_contains($t, 'inexist')
            || str_contains($t, 'no existe')
            || str_contains($t, 'sin resultados');
    }

    private function fechaArca(string $raw): string
    {
        $raw = preg_replace('/\D+/', '', $raw) ?? '';
        if (strlen($raw) !== 8) {
            return '';
        }
        $dt = \DateTime::createFromFormat('Ymd', $raw);

        return $dt ? $dt->format('Y-m-d') : '';
    }

    private function letraDesdeCodigoAfip(int $codigo): string
    {
        $n = $codigo >= 200 && $codigo < 250 ? $codigo - 200 : $codigo;

        return match (true) {
            in_array($n, [1, 2, 3, 4], true) => 'A',
            in_array($n, [6, 7, 8, 9], true) => 'B',
            in_array($n, [11, 12, 13], true) => 'C',
            in_array($n, [51, 52, 53], true) => 'M',
            default => '',
        };
    }

    private function ventaYaGrabada(Puntoventa $pv, int $codigoAfip, int $numero): ?Venta
    {
        $filas = Venta::query()
            ->where('puntoventa_id', $pv->id)
            ->where('numerocomprobante', $numero)
            ->get(['id', 'codigo', 'codigo_afip']);
        foreach ($filas as $fila) {
            $tipo = TipotransaccionCodigoAfipSupport::codigoAfipDesdeVentaGrabada(
                (int) $fila->codigo_afip,
                (string) $fila->codigo,
            );
            if ($tipo === $codigoAfip) {
                return $fila;
            }
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private function idsPuntoVentaExcluidos(): array
    {
        $ids = [];
        $tablas = [
            'configuracion_puntoventa_gastronomia' => ['puntoventa_cae_id', 'puntoventa_caea_id'],
            'configuracion_puntoventa_estacionamiento' => ['puntoventa_cae_id', 'puntoventa_caea_id'],
            'local_venta' => ['puntoventa_id'],
        ];
        foreach ($tablas as $tabla => $columnas) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }
            foreach ($columnas as $columna) {
                if (! Schema::hasColumn($tabla, $columna)) {
                    continue;
                }
                foreach (DB::table($tabla)->pluck($columna) as $id) {
                    if ((int) $id > 0) {
                        $ids[] = (int) $id;
                    }
                }
            }
        }

        return array_values(array_unique($ids));
    }
}
