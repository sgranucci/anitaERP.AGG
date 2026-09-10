<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\ApiAnita;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Venta;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Comprobantes a referenciar en NC mostrador: une venta ERP + venta Anita.
 */
final class ComprobanteReferenciaConsultaSupport
{
    private const LIMITE = 80;

    private const MESES_ANITA = 36;

    public function __construct(
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    /**
     * @return list<array{codigo:string,fecha:string,fecha_orden:string,total:float,origen:string,venta_id:?int}>
     */
    public function listar(int $clienteId, int $empresaId, string $consulta, bool $soloFce): array
    {
        if ($clienteId <= 0) {
            return [];
        }

        $porCodigo = [];
        foreach ($this->listarErp($clienteId, $empresaId, $consulta, $soloFce) as $item) {
            $porCodigo[$item['codigo']] = $item;
        }
        foreach ($this->listarAnita($clienteId, $consulta, $soloFce) as $item) {
            if (! isset($porCodigo[$item['codigo']])) {
                $porCodigo[$item['codigo']] = $item;
            }
        }

        $filas = array_values($porCodigo);
        usort($filas, static function (array $a, array $b): int {
            $cmp = strcmp($b['fecha_orden'], $a['fecha_orden']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp($b['codigo'], $a['codigo']);
        });

        return array_slice($filas, 0, self::LIMITE);
    }

    /**
     * @return array{codigo:string,fecha:string,total:float,origen:string,venta_id:?int}|null
     */
    public function resolver(int $clienteId, int $empresaId, string $valor): ?array
    {
        $valor = trim($valor);
        if ($clienteId <= 0 || $valor === '') {
            return null;
        }

        $erp = $this->resolverErp($clienteId, $empresaId, $valor);
        if ($erp !== null) {
            return $erp;
        }

        return $this->resolverAnita($clienteId, $valor);
    }

    /**
     * @return list<array{codigo:string,fecha:string,fecha_orden:string,total:float,origen:string,venta_id:?int}>
     */
    private function listarErp(int $clienteId, int $empresaId, string $consulta, bool $soloFce): array
    {
        $query = Venta::query()
            ->with([
                'tipotransacciones:id,abreviatura,operacion',
                'puntoventas:id,codigo,empresa_id',
            ])
            ->where('cliente_id', $clienteId)
            ->whereHas('tipotransacciones', static function ($q) {
                $q->where('operacion', '!=', 'C');
            })
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(self::LIMITE);

        $this->aplicarFiltroEmpresa($query, $empresaId);

        if ($soloFce) {
            $query->where(function ($q) {
                $q->where('codigo', 'like', 'FCE %')
                    ->orWhereHas('tipotransacciones', static function ($tq) {
                        $tq->where('abreviatura', 'like', 'FCE%');
                    });
            });
        }

        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('codigo', 'like', '%'.$consulta.'%')
                    ->orWhere('numerocomprobante', 'like', '%'.$consulta.'%')
                    ->orWhere('id', 'like', '%'.$consulta.'%');
            });
        }

        $out = [];
        foreach ($query->get() as $row) {
            $codigo = $this->codigoDesdeVentaErp($row);
            if ($codigo === '') {
                continue;
            }
            $fechaOrden = $this->fechaOrdenDesdeValor($row->fecha);
            $out[] = [
                'codigo' => $codigo,
                'fecha' => $this->formatearFechaDisplay($fechaOrden),
                'fecha_orden' => $fechaOrden,
                'total' => (float) ($row->total ?? 0),
                'origen' => 'ERP',
                'venta_id' => (int) $row->id,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{codigo:string,fecha:string,fecha_orden:string,total:float,origen:string,venta_id:?int}>
     */
    private function listarAnita(int $clienteId, string $consulta, bool $soloFce): array
    {
        $clienteAnita = $this->codigoClienteAnita($clienteId);
        if ($clienteAnita === '') {
            return [];
        }

        $tipos = $soloFce ? ["'FCE'"] : ["'FAC'", "'FCE'", "'FAU'"];
        $desde = (int) Carbon::now()->subMonths(self::MESES_ANITA)->format('Ymd');
        $where = " WHERE ven_cliente = '".addslashes($clienteAnita)."'"
            .' AND ven_tipo IN ('.implode(',', $tipos).')'
            .' AND ven_fecha >= '.$desde;

        $consultaSql = $this->consultaAnitaWhereExtra($consulta);
        if ($consultaSql !== '') {
            $where .= ' AND '.$consultaSql;
        }

        try {
            $api = new ApiAnita;
            $raw = $api->apiCall([
                'acc' => 'list',
                'sistema' => 'ventas',
                'tabla' => 'venta',
                'campos' => 'ven_tipo,ven_letra,ven_sucursal,ven_nro,ven_fecha,ven_monto',
                'whereArmado' => $where,
                'orderBy' => 'ven_fecha DESC, ven_nro DESC',
                'limit' => 'FIRST '.self::LIMITE,
            ]);
            $filas = ApiAnita::decodificarListaFilas($raw);
        } catch (\Throwable $e) {
            Log::warning('ComprobanteReferenciaConsultaSupport: fallo lectura Anita', [
                'cliente_id' => $clienteId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $out = [];
        foreach ($filas as $fila) {
            $codigo = $this->codigoDesdeFilaAnita($fila);
            if ($codigo === '') {
                continue;
            }
            $fechaOrden = $this->fechaOrdenDesdeAnita((string) ($fila->ven_fecha ?? ''));
            $out[] = [
                'codigo' => $codigo,
                'fecha' => $this->formatearFechaDisplay($fechaOrden),
                'fecha_orden' => $fechaOrden,
                'total' => (float) ($fila->ven_monto ?? 0),
                'origen' => 'Anita',
                'venta_id' => null,
            ];
        }

        return $out;
    }

    /**
     * @return array{codigo:string,fecha:string,total:float,origen:string,venta_id:?int}|null
     */
    private function resolverErp(int $clienteId, int $empresaId, string $valor): ?array
    {
        $query = Venta::query()
            ->with([
                'tipotransacciones:id,abreviatura,operacion',
                'puntoventas:id,codigo,empresa_id',
            ])
            ->where('cliente_id', $clienteId)
            ->whereHas('tipotransacciones', static function ($q) {
                $q->where('operacion', '!=', 'C');
            });
        $this->aplicarFiltroEmpresa($query, $empresaId);

        if (ctype_digit($valor)) {
            $query->where(function ($q) use ($valor) {
                $q->where('id', (int) $valor)
                    ->orWhere('numerocomprobante', (int) $valor);
            });
        } else {
            $query->where(function ($q) use ($valor) {
                $q->where('codigo', $valor)
                    ->orWhere('codigo', 'like', '%'.$valor.'%');
            });
        }

        $venta = $query->orderByDesc('id')->first();
        if (! $venta) {
            return null;
        }

        $codigo = $this->codigoDesdeVentaErp($venta);
        $fechaOrden = $this->fechaOrdenDesdeValor($venta->fecha);

        return [
            'codigo' => $codigo,
            'fecha' => $this->formatearFechaDisplay($fechaOrden),
            'total' => (float) ($venta->total ?? 0),
            'origen' => 'ERP',
            'venta_id' => (int) $venta->id,
        ];
    }

    /**
     * @return array{codigo:string,fecha:string,total:float,origen:string,venta_id:?int}|null
     */
    private function resolverAnita(int $clienteId, string $valor): ?array
    {
        $clienteAnita = $this->codigoClienteAnita($clienteId);
        if ($clienteAnita === '') {
            return null;
        }

        if (preg_match('/^([A-Z]{3})\s+([A-Z])-(\d+)-(\d+)$/i', $valor, $m)) {
            $where = " WHERE ven_cliente = '".addslashes($clienteAnita)."'"
                ." AND ven_tipo = '".addslashes(strtoupper($m[1]))."'"
                ." AND ven_letra = '".addslashes(strtoupper($m[2]))."'"
                .' AND ven_sucursal = '.(int) $m[3]
                .' AND ven_nro = '.(int) $m[4];
        } elseif (ctype_digit($valor)) {
            $where = " WHERE ven_cliente = '".addslashes($clienteAnita)."'"
                ." AND (ven_tipo = 'FAC' OR ven_tipo = 'FCE' OR ven_tipo = 'FAU')"
                .' AND ven_nro = '.(int) $valor;
        } else {
            return null;
        }

        try {
            $api = new ApiAnita;
            $raw = $api->apiCall([
                'acc' => 'list',
                'sistema' => 'ventas',
                'tabla' => 'venta',
                'campos' => 'ven_tipo,ven_letra,ven_sucursal,ven_nro,ven_fecha,ven_monto',
                'whereArmado' => $where,
                'orderBy' => 'ven_fecha DESC',
                'limit' => 'FIRST 1',
            ]);
            $fila = ApiAnita::primeraFilaLista((string) $raw);
        } catch (\Throwable $e) {
            Log::warning('ComprobanteReferenciaConsultaSupport: fallo resolver Anita', [
                'cliente_id' => $clienteId,
                'valor' => $valor,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($fila === null) {
            return null;
        }

        $codigo = $this->codigoDesdeFilaAnita($fila);
        $fechaOrden = $this->fechaOrdenDesdeAnita((string) ($fila->ven_fecha ?? ''));

        return [
            'codigo' => $codigo,
            'fecha' => $this->formatearFechaDisplay($fechaOrden),
            'total' => (float) ($fila->ven_monto ?? 0),
            'origen' => 'Anita',
            'venta_id' => null,
        ];
    }

    private function codigoClienteAnita(int $clienteId): string
    {
        $codigo = trim((string) (Cliente::query()->whereKey($clienteId)->value('codigo') ?? ''));
        if ($codigo === '') {
            return '';
        }
        $soloDigitos = preg_replace('/\D+/', '', $codigo) ?? '';
        if ($soloDigitos === '') {
            return '';
        }

        return str_pad($soloDigitos, 6, '0', STR_PAD_LEFT);
    }

    private function codigoDesdeVentaErp(Venta $venta): string
    {
        $codigo = trim((string) ($venta->codigo ?? ''));
        if ($codigo !== '') {
            return $codigo;
        }
        $abrev = (string) ($venta->tipotransacciones?->abreviatura ?? '');
        $pv = str_pad((string) ($venta->puntoventas?->codigo ?? 0), 5, '0', STR_PAD_LEFT);
        $nro = str_pad((string) ($venta->numerocomprobante ?? 0), 8, '0', STR_PAD_LEFT);

        return trim($abrev.' '.$pv.'-'.$nro);
    }

    private function codigoDesdeFilaAnita(object $fila): string
    {
        $tipo = strtoupper(trim((string) ($fila->ven_tipo ?? '')));
        $letra = strtoupper(trim((string) ($fila->ven_letra ?? '')));
        if ($tipo === '' || $letra === '') {
            return '';
        }
        $pv = str_pad((string) ((int) ($fila->ven_sucursal ?? 0)), 5, '0', STR_PAD_LEFT);
        $nro = str_pad((string) ((int) ($fila->ven_nro ?? 0)), 8, '0', STR_PAD_LEFT);

        return $tipo.' '.$letra.'-'.$pv.'-'.$nro;
    }

    private function consultaAnitaWhereExtra(string $consulta): string
    {
        $consulta = trim($consulta);
        if ($consulta === '') {
            return '';
        }

        if (preg_match('/^([A-Z]{3})\s+([A-Z])-(\d+)-(\d+)$/i', $consulta, $m)) {
            return "ven_tipo = '".addslashes(strtoupper($m[1]))."'"
                ." AND ven_letra = '".addslashes(strtoupper($m[2]))."'"
                .' AND ven_sucursal = '.(int) $m[3]
                .' AND ven_nro = '.(int) $m[4];
        }

        if (ctype_digit($consulta)) {
            $n = (int) $consulta;

            return '(ven_nro = '.$n.' OR ven_sucursal = '.$n.')';
        }

        $digitos = (int) (preg_replace('/\D+/', '', $consulta) ?? '0');
        $esc = addslashes(strtoupper($consulta));

        return "(UPPER(ven_tipo) LIKE '%".$esc."%'"
            ." OR UPPER(ven_letra) LIKE '%".$esc."%'"
            .($digitos > 0 ? (' OR ven_nro = '.$digitos.' OR ven_sucursal = '.$digitos) : '')
            .')';
    }

    private function aplicarFiltroEmpresa($query, int $empresaId): void
    {
        if ($empresaId > 0) {
            $query->whereHas('puntoventas', static fn ($q) => $q->where('empresa_id', $empresaId));

            return;
        }
        $asignadas = $this->empresaRepository->traeEmpresasAsignadas();
        if (is_array($asignadas) && count($asignadas) > 0) {
            $query->whereHas('puntoventas', static fn ($q) => $q->whereIn('empresa_id', $asignadas));
        }
    }

    private function fechaOrdenDesdeValor(mixed $fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }
        $raw = trim((string) $fecha);
        if ($raw === '') {
            return '0000-00-00';
        }
        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable $e) {
            return '0000-00-00';
        }
    }

    private function fechaOrdenDesdeAnita(string $fechaEntera): string
    {
        $n = preg_replace('/\D+/', '', $fechaEntera) ?? '';
        if (strlen($n) !== 8) {
            return '0000-00-00';
        }

        return substr($n, 0, 4).'-'.substr($n, 4, 2).'-'.substr($n, 6, 2);
    }

    private function formatearFechaDisplay(string $fechaOrden): string
    {
        if ($fechaOrden === '' || $fechaOrden === '0000-00-00') {
            return '';
        }
        try {
            return Carbon::parse($fechaOrden)->format('d/m/Y');
        } catch (\Throwable $e) {
            return $fechaOrden;
        }
    }
}
