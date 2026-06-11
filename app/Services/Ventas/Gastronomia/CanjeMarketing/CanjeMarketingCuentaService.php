<?php

namespace App\Services\Ventas\Gastronomia\CanjeMarketing;

use App\Models\Ventas\ClienteVipGastronomia;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\DescuentoGastronomia;
use App\Models\Ventas\MozoGastronomia;
use App\Repositories\Ventas\ClienteVipGastronomiaRepositoryInterface;
use App\Services\Ventas\Gastronomia\GastronomiaCuentaService;
use App\Services\Ventas\Gastronomia\WigosAccountInfoService;
use App\Support\Ventas\GastronomiaIdentificadorPc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

final class CanjeMarketingCuentaService
{
    public function __construct(
        private readonly GastronomiaCuentaService $cuentaService,
        private readonly ClienteVipGastronomiaRepositoryInterface $clienteVipRepository,
        private readonly WigosAccountInfoService $wigosAccountInfoService,
    ) {
    }

    public function resolverConfiguracionPv(?Request $request = null)
    {
        return $this->cuentaService->resolverConfiguracionPv($request);
    }

    /**
     * @return array{mozo: MozoGastronomia, session_token: string}
     */
    public function autenticarMozo(string $codigo, string $clave, int $empresaId): array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            throw new InvalidArgumentException('Debe indicar el código de mozo.');
        }
        if (trim($clave) === '') {
            throw new InvalidArgumentException('Debe indicar la clave.');
        }

        $mozo = MozoGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->first();

        if (! $mozo) {
            throw new InvalidArgumentException('Mozo no encontrado para esta empresa.');
        }

        $claveAlmacenada = (string) ($mozo->clave ?? '');
        $claveOk = false;
        if ($claveAlmacenada !== '' && str_starts_with($claveAlmacenada, '$2y$')) {
            $claveOk = Hash::check($clave, $claveAlmacenada);
        } else {
            $claveOk = hash_equals($claveAlmacenada, $clave);
        }
        if (! $claveOk) {
            throw new InvalidArgumentException('Clave incorrecta.');
        }

        $token = hash('sha256', $mozo->id.'|'.microtime(true).'|'.random_bytes(8));

        return [
            'mozo' => $mozo,
            'session_token' => $token,
        ];
    }

    public function abrirCuentaParaMozo(int $empresaId, $cfg, int $mozoId): CuentaGastronomia
    {
        $cubiertos = max(0, (int) config('gastronomia.canje_marketing_cubiertos_default', 1));

        $cuenta = $this->cuentaService->abrirCuentaLibre($empresaId, $cfg, [
            'mozo_gastronomia_id' => $mozoId,
            'cubiertos' => $cubiertos,
        ]);

        $cuenta->origen_pos = CuentaGastronomia::ORIGEN_CANJE_MARKETING;
        $cuenta->save();

        return $this->cuentaService->cuentaConLineas($cuenta->id);
    }

    /**
     * Reutiliza la cuenta abierta del mozo en esta PC; evita duplicar cuentas al reingresar.
     */
    public function abrirOCargarCuentaParaMozo(int $empresaId, $cfg, int $mozoId, ?Request $request = null): CuentaGastronomia
    {
        $pc = GastronomiaIdentificadorPc::resolver($request);

        $existente = CuentaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->where('origen_pos', CuentaGastronomia::ORIGEN_CANJE_MARKETING)
            ->where('tipo', CuentaGastronomia::TIPO_CUENTA)
            ->where('estado', CuentaGastronomia::ESTADO_ABIERTA)
            ->where('identificador_pc', $pc)
            ->where('mozo_gastronomia_id', $mozoId)
            ->orderByDesc('id')
            ->first();

        if ($existente) {
            return $this->cuentaService->cuentaConLineas($existente->id);
        }

        return $this->abrirCuentaParaMozo($empresaId, $cfg, $mozoId);
    }

    /**
     * @return int Cantidad de cuentas cerradas
     */
    public function cerrarTodasCuentasActivasPc(?Request $request = null): int
    {
        $cuentas = $this->listarCuentasActivasPc($request);
        $cerradas = 0;

        foreach ($cuentas as $cuenta) {
            if ($cuenta->estado !== CuentaGastronomia::ESTADO_ABIERTA) {
                continue;
            }
            $this->cuentaService->cerrarSinFacturar($cuenta);
            $cerradas++;
        }

        return $cerradas;
    }

    /**
     * @return \Illuminate\Support\Collection<int, CuentaGastronomia>
     */
    public function listarCuentasActivasPc(?Request $request = null)
    {
        $cfg = $this->resolverConfiguracionPv($request);
        if (! $cfg) {
            return collect();
        }

        $pc = GastronomiaIdentificadorPc::resolver($request);

        return CuentaGastronomia::query()
            ->where('empresa_id', $cfg->empresa_id)
            ->where('origen_pos', CuentaGastronomia::ORIGEN_CANJE_MARKETING)
            ->where('tipo', CuentaGastronomia::TIPO_CUENTA)
            ->where('estado', CuentaGastronomia::ESTADO_ABIERTA)
            ->where('identificador_pc', $pc)
            ->orderByDesc('id')
            ->with(['lineas.articulo', 'mozo', 'clienteVip', 'descuentoGastronomia'])
            ->get()
            ->map(fn (CuentaGastronomia $c) => $this->cuentaService->cuentaConLineas($c->id));
    }

    public function exigirCuentaCanjeMarketing(int $cuentaId): CuentaGastronomia
    {
        $cuenta = CuentaGastronomia::query()->findOrFail($cuentaId);
        if (! $cuenta->esCanjeMarketing()) {
            throw new InvalidArgumentException('La cuenta no pertenece al facturador de canjes marketing.');
        }

        return $cuenta;
    }

    public function actualizarCabecera(CuentaGastronomia $cuenta, array $datos): CuentaGastronomia
    {
        $this->exigirCuentaCanjeMarketing((int) $cuenta->id);

        if ($cuenta->estado !== CuentaGastronomia::ESTADO_ABIERTA) {
            throw new InvalidArgumentException('La cuenta no está abierta.');
        }

        $patch = [];
        foreach (['descuento_gastronomia_id', 'cliente_vip_gastronomia_id'] as $campo) {
            if (array_key_exists($campo, $datos)) {
                $valor = $datos[$campo];
                $patch[$campo] = ($valor === '' || $valor === null) ? null : (int) $valor;
            }
        }

        if (array_key_exists('descuento_gastronomia_id', $patch) && empty($patch['descuento_gastronomia_id'])) {
            $patch['cliente_vip_gastronomia_id'] = null;
        }

        $cuenta->fill($patch);

        $vipId = (int) ($cuenta->cliente_vip_gastronomia_id ?? 0);
        $descuentoId = (int) ($cuenta->descuento_gastronomia_id ?? 0);

        if ($vipId > 0) {
            $vip = ClienteVipGastronomia::query()
                ->where('id', $vipId)
                ->where('empresa_id', (int) $cuenta->empresa_id)
                ->first();
            if (! $vip) {
                throw new InvalidArgumentException('El cliente VIP no existe para esta empresa.');
            }
        }

        if ($descuentoId > 0) {
            $codigoEsperado = trim((string) config('gastronomia.canje_marketing_descuento_codigo', '40'));
            $desc = DescuentoGastronomia::query()->find($descuentoId);
            if (! $desc || trim((string) $desc->codigo) !== $codigoEsperado) {
                throw new InvalidArgumentException(
                    'El descuento indicado no corresponde al canje marketing (código '.$codigoEsperado.').'
                );
            }
        }

        $cuenta->cliente_id = null;
        $cuenta->cliente_interno_descuento_id = null;
        $cuenta->save();

        return $this->cuentaService->cuentaConLineas($cuenta->id);
    }

    public function aplicarDescuentoPrefijado(CuentaGastronomia $cuenta): CuentaGastronomia
    {
        $codigoDesc = trim((string) config('gastronomia.canje_marketing_descuento_codigo', '40'));
        $desc = DescuentoGastronomia::query()
            ->where('codigo', $codigoDesc)
            ->first();

        if (! $desc) {
            throw new InvalidArgumentException(
                'No existe descuento gastronomía código '.$codigoDesc.'.'
            );
        }

        $cuenta->descuento_gastronomia_id = $desc->id;
        $cuenta->cliente_id = null;
        $cuenta->cliente_interno_descuento_id = null;
        $cuenta->save();

        return $this->cuentaService->cuentaConLineas($cuenta->id);
    }

    /**
     * @return array{cliente_vip: ClienteVipGastronomia, creado: bool, wigos: array<string,mixed>|null}
     */
    public function resolverClienteVipPorDocumento(int $empresaId, string $documento): array
    {
        $documento = preg_replace('/\D+/', '', trim($documento)) ?? '';
        if ($documento === '') {
            throw new InvalidArgumentException('Documento inválido.');
        }

        $vip = ClienteVipGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->where('nrodocumento', $documento)
            ->first();

        return [
            'cliente_vip' => $vip ?? $this->crearClienteVipMinimo($empresaId, $documento, '', ''),
            'creado' => $vip === null,
            'wigos' => null,
        ];
    }

    /**
     * @return array{cliente_vip: ClienteVipGastronomia, creado: bool, wigos: array<string,mixed>}
     */
    public function resolverClienteVipDesdeWigos(int $empresaId, string $trackdata): array
    {
        $info = $this->wigosAccountInfoService->consultarPorTrackdata($trackdata, $empresaId);
        $documento = preg_replace('/\D+/', '', (string) ($info['documento'] ?? '')) ?? '';
        if ($documento === '') {
            throw new InvalidArgumentException('La tarjeta Wigos no informa documento.');
        }

        $vip = ClienteVipGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->where('nrodocumento', $documento)
            ->first();

        $creado = false;
        if (! $vip) {
            $vip = $this->crearClienteVipMinimo(
                $empresaId,
                $documento,
                (string) ($info['apellido'] ?? ''),
                (string) ($info['nombre'] ?? ''),
            );
            $creado = true;
        }

        return [
            'cliente_vip' => $vip,
            'creado' => $creado,
            'wigos' => $info,
        ];
    }

    public function crearClienteVipMinimo(int $empresaId, string $documento, string $apellido, string $nombre): ClienteVipGastronomia
    {
        $apellido = mb_substr(trim($apellido) !== '' ? trim($apellido) : 'SIN', 0, 40);
        $nombre = mb_substr(trim($nombre) !== '' ? trim($nombre) : 'APELLIDO', 0, 40);

        return $this->clienteVipRepository->create([
            'empresa_id' => $empresaId,
            'nrodocumento' => mb_substr($documento, 0, 20),
            'apellido' => $apellido,
            'nombre' => $nombre,
            'nickname' => null,
            'localidad' => null,
        ]);
    }

    public function serializarClienteVip(?ClienteVipGastronomia $vip): ?array
    {
        if (! $vip) {
            return null;
        }

        return [
            'id' => (int) $vip->id,
            'numeroid' => (int) $vip->numeroid,
            'nrodocumento' => (string) $vip->nrodocumento,
            'apellido' => (string) $vip->apellido,
            'nombre' => (string) $vip->nombre,
            'nombre_completo' => trim($vip->apellido.' '.$vip->nombre),
            'empresa_id' => (int) $vip->empresa_id,
        ];
    }
}
