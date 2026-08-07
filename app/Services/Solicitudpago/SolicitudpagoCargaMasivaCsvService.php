<?php

namespace App\Services\Solicitudpago;

use App\Models\Compras\Proveedor;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use App\Models\Solicitudpago\Concepto_Solicitudpago;
use App\Models\Solicitudpago\Formapagosol;
use App\Models\Solicitudpago\Sector_Solicitudpago;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Solicitudpago\SolicitudpagoRepositoryInterface;
use App\Support\Archivo\TextoUtf8Support;
use App\Support\Solicitudpago\SolicitudpagoCargaMasivaCsvParser;
use App\Support\Solicitudpago\SolicitudpagoEstados;
use App\Support\Solicitudpago\SolicitudpagoTratamientos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Carga masiva de SP desde CSV Anita (p-cargasolpm).
 */
class SolicitudpagoCargaMasivaCsvService
{
    public const SESSION_KEY = 'solicitudpago_carga_masiva_csv';

    public function __construct(
        private SolicitudpagoCargaMasivaCsvParser $parser,
        private SolicitudpagoRepositoryInterface $repository,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    /**
     * @return array{
     *   token: string,
     *   resumen: array<string, mixed>,
     *   filas: list<array<string, mixed>>,
     *   errores: list<string>,
     *   por_empresa: list<array{empresa_codigo: int, empresa_nombre: string, cantidad: int, monto: float}>
     * }
     */
    public function preview(UploadedFile|string $archivo): array
    {
        $contenido = $this->leerContenido($archivo);
        $crudas = $this->parser->parsear($contenido);
        $mapas = $this->armarMapas();

        $filas = [];
        $payloads = [];
        $errores = [];
        $omitidas = 0;
        $ok = 0;
        $montoTotal = 0.0;
        $porEmpresa = [];

        foreach ($crudas as $raw) {
            $resuelta = $this->resolverFila($raw, $mapas);
            $filas[] = $resuelta['vista'];

            if ($resuelta['ok']) {
                $ok++;
                $montoTotal += (float) $resuelta['vista']['monto'];
                $payloads[] = $resuelta['payload'];
                $empCod = (int) $raw['empresa_codigo'];
                if (! isset($porEmpresa[$empCod])) {
                    $porEmpresa[$empCod] = [
                        'empresa_codigo' => $empCod,
                        'empresa_nombre' => (string) ($resuelta['vista']['empresa_nombre'] ?? ''),
                        'cantidad' => 0,
                        'monto' => 0.0,
                    ];
                }
                $porEmpresa[$empCod]['cantidad']++;
                $porEmpresa[$empCod]['monto'] += (float) $resuelta['vista']['monto'];
            } else {
                $omitidas++;
                foreach ($resuelta['errores'] as $msg) {
                    $errores[] = 'Línea '.$raw['nro_linea'].': '.$msg;
                }
            }
        }

        $proximo = $this->estimarProximoCodigo();
        $token = (string) Str::uuid();

        session([
            self::SESSION_KEY => [
                'token' => $token,
                'payloads' => $payloads,
                'created_at' => now()->toDateTimeString(),
            ],
        ]);

        return [
            'token' => $token,
            'resumen' => [
                'leidas' => count($crudas),
                'a_generar' => $ok,
                'con_error' => $omitidas,
                'monto_total' => round($montoTotal, 2),
                'empresas' => count($porEmpresa),
                'codigo_desde_estimado' => $ok > 0 ? $proximo : null,
                'codigo_hasta_estimado' => $ok > 0 ? ($proximo + $ok - 1) : null,
            ],
            'filas' => $filas,
            'errores' => $errores,
            'por_empresa' => array_values($porEmpresa),
        ];
    }

    /**
     * @return array{
     *   creadas: int,
     *   desde_codigo: ?int,
     *   hasta_codigo: ?int,
     *   ids: list<int>,
     *   codigos: list<int>,
     *   errores_runtime: list<string>
     * }
     */
    public function confirmar(string $token): array
    {
        $sess = session(self::SESSION_KEY);
        if (! is_array($sess) || ($sess['token'] ?? '') !== $token) {
            throw new \InvalidArgumentException('La vista previa expiró o es inválida. Vuelva a subir el archivo.');
        }

        /** @var list<array<string, mixed>> $payloads */
        $payloads = $sess['payloads'] ?? [];
        if ($payloads === []) {
            throw new \InvalidArgumentException('No hay solicitudes válidas para generar.');
        }

        $creadas = 0;
        $desde = null;
        $hasta = null;
        $ids = [];
        $codigos = [];
        $errores = [];

        // Una SP por create (transacción propia + escritura Anita). No envolver todo:
        // un fallo no debe deshacer las ya grabadas en Anita.
        foreach ($payloads as $idx => $payload) {
            try {
                $sp = $this->repository->create($payload);
                $creadas++;
                $codigo = (int) $sp->codigo;
                $ids[] = (int) $sp->id;
                $codigos[] = $codigo;
                if ($desde === null) {
                    $desde = $codigo;
                }
                $hasta = $codigo;
            } catch (\Throwable $e) {
                $errores[] = 'Fila '.($idx + 1).': '.$e->getMessage();
            }
        }

        session()->forget(self::SESSION_KEY);

        return [
            'creadas' => $creadas,
            'desde_codigo' => $desde,
            'hasta_codigo' => $hasta,
            'ids' => $ids,
            'codigos' => $codigos,
            'errores_runtime' => $errores,
        ];
    }

    private function leerContenido(UploadedFile|string $archivo): string
    {
        if ($archivo instanceof UploadedFile) {
            $path = $archivo->getRealPath() ?: $archivo->path();
            if ($path === false || $path === '') {
                throw new \InvalidArgumentException('No se pudo leer el archivo.');
            }
            $contenido = file_get_contents($path);
        } else {
            $contenido = is_file($archivo) ? file_get_contents($archivo) : $archivo;
        }

        if ($contenido === false || $contenido === '') {
            throw new \InvalidArgumentException('El archivo está vacío o no se pudo leer.');
        }

        return TextoUtf8Support::normalizar($contenido);
    }

    /**
     * @return array<string, mixed>
     */
    private function armarMapas(): array
    {
        $proveedores = [];
        foreach (Proveedor::query()->get(['id', 'codigo', 'nombre']) as $p) {
            $cod = (string) $p->codigo;
            $proveedores[$cod] = $p;
            $sinCeros = ltrim($cod, '0') ?: '0';
            $proveedores[$sinCeros] = $p;
            $pad = str_pad(substr($sinCeros, -6), 6, '0', STR_PAD_LEFT);
            $proveedores[$pad] = $p;
        }

        $cuentas = [];
        foreach (Cuentacontable::query()->get(['id', 'empresa_id', 'codigo', 'nombre']) as $c) {
            $emp = (int) $c->empresa_id;
            $cod = (string) (int) preg_replace('/\D/', '', (string) $c->codigo);
            $cuentas[$emp][$cod] = $c;
            $cuentas[$emp][(string) $c->codigo] = $c;
        }

        return [
            'empresas' => Empresa::query()->get(['id', 'codigo', 'nombre'])->keyBy(fn ($e) => (int) $e->codigo),
            'conceptos' => Concepto_Solicitudpago::query()->get(['id', 'codigo', 'nombre'])->keyBy(fn ($c) => (int) $c->codigo),
            'sectores' => Sector_Solicitudpago::query()->get(['id', 'codigo', 'nombre', 'centrocosto_id'])->keyBy(fn ($s) => (int) $s->codigo),
            'formapagos' => Formapagosol::query()->get(['id', 'codigo', 'nombre'])->keyBy(fn ($f) => (int) $f->codigo),
            'monedas' => Moneda::query()->get(['id', 'codigo', 'nombre'])->keyBy(fn ($m) => (string) (int) $m->codigo),
            'proveedores' => $proveedores,
            'cuentas' => $cuentas,
            'cc99' => Centrocosto::query()->where('codigo', 99)->orWhere('codigo', '99')->value('id'),
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $mapas
     * @return array{ok: bool, errores: list<string>, vista: array<string, mixed>, payload: ?array<string, mixed>}
     */
    private function resolverFila(array $raw, array $mapas): array
    {
        $errores = [];
        $empCod = (int) $raw['empresa_codigo'];
        /** @var Empresa|null $empresa */
        $empresa = $mapas['empresas'][$empCod] ?? null;
        if ($empresa === null) {
            $errores[] = "Empresa {$empCod} inexistente";
        } elseif (! $this->empresaRepository->empresaIdPermitida((int) $empresa->id)) {
            $errores[] = "Empresa {$empCod} no asignada al usuario";
        }

        $conceptoCod = (int) $raw['concepto_codigo'];
        /** @var Concepto_Solicitudpago|null $concepto */
        $concepto = $mapas['conceptos'][$conceptoCod] ?? null;
        if ($concepto === null) {
            $errores[] = "Concepto {$conceptoCod} inexistente";
        }

        $provKey = (string) $raw['proveedor_codigo'];
        $provSin = ltrim($provKey, '0') ?: '0';
        /** @var Proveedor|null $proveedor */
        $proveedor = $mapas['proveedores'][$provKey]
            ?? $mapas['proveedores'][$provSin]
            ?? null;
        if ($proveedor === null) {
            $errores[] = "Proveedor {$provSin} inexistente";
        }

        $sectorCod = (int) $raw['sector_codigo'];
        /** @var Sector_Solicitudpago|null $sector */
        $sector = $mapas['sectores'][$sectorCod] ?? null;
        if ($sector === null && $sectorCod > 0) {
            $errores[] = "Sector {$sectorCod} inexistente";
        }

        $fpCod = (int) $raw['forma_pago_codigo'];
        /** @var Formapagosol|null $fp */
        $fp = $mapas['formapagos'][$fpCod] ?? null;
        if ($fp === null && $fpCod > 0) {
            $errores[] = "Forma de pago {$fpCod} inexistente";
        }

        $monCod = (string) (int) ($raw['moneda_codigo'] ?: '1');
        /** @var Moneda|null $moneda */
        $moneda = $mapas['monedas'][$monCod] ?? null;
        if ($moneda === null) {
            $errores[] = "Moneda {$monCod} inexistente";
        }

        $monto = (float) $raw['monto'];
        if (abs($monto) < 0.0000001) {
            $errores[] = 'Monto en cero (se omite como en Anita)';
        }

        $ccLinea = $sector?->centrocosto_id ? (int) $sector->centrocosto_id : null;
        if ($ccLinea === null || $ccLinea <= 0) {
            $ccLinea = $mapas['cc99'] ? (int) $mapas['cc99'] : null;
        }

        $empresaIds = [];
        $cuentaIds = [];
        $ccIds = [];
        $dhs = [];
        $montos = [];
        $cuentasVista = [];

        if ($empresa !== null) {
            foreach ($raw['cuentas'] as $cta) {
                $codCta = (string) (int) ($cta['cuenta_codigo'] ?? 0);
                $cuenta = $mapas['cuentas'][(int) $empresa->id][$codCta] ?? null;
                if ($cuenta === null) {
                    $errores[] = "Cuenta {$codCta} inexistente en empresa {$empCod}";
                    continue;
                }
                $importe = (float) ($cta['monto'] ?? 0);
                if ($importe <= 0) {
                    $errores[] = "Importe de cuenta {$codCta} inválido";
                    continue;
                }
                $empresaIds[] = (int) $empresa->id;
                $cuentaIds[] = (int) $cuenta->id;
                $ccIds[] = $ccLinea;
                $dhs[] = ($cta['debe_haber'] ?? 'D') === 'H' ? 'H' : 'D';
                $montos[] = $importe;
                $cuentasVista[] = [
                    'codigo' => $codCta,
                    'nombre' => (string) $cuenta->nombre,
                    'debe_haber' => end($dhs),
                    'monto' => $importe,
                ];
            }
        }

        if ($errores === [] && $cuentaIds === []) {
            $errores[] = 'Sin cuentas contables válidas';
        }

        if ($errores === [] && $cuentaIds !== []) {
            $totalDebe = 0.0;
            $totalHaber = 0.0;
            foreach ($montos as $i => $importeCta) {
                if (($dhs[$i] ?? 'D') === 'H') {
                    $totalHaber += (float) $importeCta;
                } else {
                    $totalDebe += (float) $importeCta;
                }
            }
            if (abs($totalDebe - $totalHaber) >= 0.009) {
                $errores[] = 'Asiento no balancea: Debe ('.number_format($totalDebe, 2, ',', '.').') '
                    .'≠ Haber ('.number_format($totalHaber, 2, ',', '.').')';
            } elseif (abs($totalDebe - $monto) >= 0.009) {
                $errores[] = 'Total asiento ('.number_format($totalDebe, 2, ',', '.').') '
                    .'≠ monto SP ('.number_format($monto, 2, ',', '.').')';
            }
        }

        $vista = [
            'nro_linea' => $raw['nro_linea'],
            'ok' => $errores === [],
            'empresa_codigo' => $empCod,
            'empresa_nombre' => $empresa?->nombre ?? '',
            'proveedor_codigo' => $provSin,
            'proveedor_nombre' => $proveedor?->nombre ?? '',
            'concepto_codigo' => $conceptoCod,
            'concepto_nombre' => $concepto?->nombre ?? '',
            'sector_codigo' => $sectorCod,
            'sector_nombre' => $sector?->nombre ?? '',
            'forma_pago_codigo' => $fpCod,
            'forma_pago_nombre' => $fp?->nombre ?? '',
            'beneficiario' => $raw['beneficiario'],
            'detalle' => $raw['detalle'],
            'fecha_vencimiento' => $raw['fecha_vencimiento'],
            'monto' => $monto,
            'n_cuentas' => count($cuentaIds),
            'cuentas' => $cuentasVista,
            'errores' => $errores,
            'estado_label' => $errores === [] ? 'OK' : 'Error',
        ];

        if ($errores !== []) {
            return ['ok' => false, 'errores' => $errores, 'vista' => $vista, 'payload' => null];
        }

        $hoy = now()->toDateString();
        $payload = [
            'empresa_id' => (int) $empresa->id,
            'fecha' => $hoy,
            'tratamiento' => SolicitudpagoTratamientos::NORMAL,
            'proveedor_id' => (int) $proveedor->id,
            'concepto_solicitudpago_id' => (int) $concepto->id,
            'formapagosol_id' => $fp ? (int) $fp->id : null,
            'moneda_id' => (int) $moneda->id,
            'beneficiario' => $raw['beneficiario'] !== '' ? $raw['beneficiario'] : null,
            'endoso' => null,
            'fecha_entrega' => $hoy,
            'fecha_vencimiento' => $raw['fecha_vencimiento'],
            'monto' => $monto,
            'observacion' => null,
            'estado' => SolicitudpagoEstados::AUTORIZADA,
            'sector_solicitudpago_id' => $sector ? (int) $sector->id : null,
            'centrocosto_id' => $ccLinea,
            'detalle' => $raw['detalle'] !== '' ? $raw['detalle'] : null,
            'solicitudpago_madre_id' => null,
            'empresa_ids' => $empresaIds,
            'cuentacontable_ids' => $cuentaIds,
            'centrocosto_ids' => $ccIds,
            'debe_haberes' => $dhs,
            'montos_cuenta' => $montos,
        ];

        return ['ok' => true, 'errores' => [], 'vista' => $vista, 'payload' => $payload];
    }

    private function estimarProximoCodigo(): int
    {
        // Misma lógica que el repository privado: max local (+ Anita si escritura activa).
        $maxLocal = (int) (\App\Models\Solicitudpago\Solicitudpago::query()->max('codigo') ?? 0);
        if (! config('solicitudpago.anita_escritura', true)) {
            return $maxLocal + 1;
        }

        try {
            $api = new \App\ApiAnita();
            $parsed = \App\ApiAnita::parsearRespuestaLista($api->apiCall([
                'acc' => 'list',
                'sistema' => (string) config('solicitudpago.anita_sistema', 'che_ban'),
                'tabla' => 'solpagomae',
                'campos' => 'max(solpm_id) as max_codigo',
            ]));
            $maxAnita = 0;
            if ($parsed['filas'] !== []) {
                $maxAnita = (int) ($parsed['filas'][0]->max_codigo ?? 0);
            }

            return max($maxLocal, $maxAnita) + 1;
        } catch (\Throwable) {
            return $maxLocal + 1;
        }
    }
}
