<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Empleado_Contrato_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Support\Sueldos\ContratoModalidadSijp;
use App\Support\Sueldos\NumeroALetrasEs;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Arma el mapa de tags para merge (compatibilidad Anita l-empcontr / lista_cert_*).
 */
class ContratoEmisionDatosService
{
    /** @var array<int, string> */
    private const ESTADO_CIVIL = [
        1 => 'Soltero',
        2 => 'Casado',
        3 => 'Divorciado',
        4 => 'Viudo',
        5 => 'Separado',
    ];

    /**
     * @return array<string, string>
     */
    public function armar(
        Empleado_Sueldos $empleado,
        ?Empleado_Contrato_Sueldos $contrato,
        CarbonInterface $fechaEmision,
        ?string $modalidadOverride = null,
    ): array {
        $empleado->loadMissing([
            'categoria',
            'lugartrabajo',
            'centrocosto',
            'sindicato',
            'provinciaDomicilio',
            'localidadDomicilio',
        ]);

        if ($contrato) {
            $contrato->loadMissing(['categoria', 'centrocosto', 'empleadoReemplazo']);
        }

        $desde = $contrato?->desde_fecha;
        $hasta = $contrato?->hasta_fecha;
        $modalidad = $modalidadOverride
            ?? $contrato?->modalidad
            ?? $empleado->modalidad_sijp;

        $categoria = $contrato?->categoria ?? $empleado->categoria;
        $sueldo = (float) ($empleado->sueldo_basico ?? $empleado->jornal_dia ?? 0);

        $lugar = (string) ($empleado->lugartrabajo->nombre ?? '');
        $dirLugar = (string) ($empleado->lugartrabajo->direccion ?? '');
        $centro = (string) ($contrato?->centrocosto->nombre
            ?? $empleado->centrocosto->nombre
            ?? '');

        $diasVig = '';
        if ($desde && $hasta) {
            $diasVig = (string) ($desde->diffInDays($hasta) + 1);
        } elseif ($contrato?->dias) {
            $diasVig = (string) $contrato->dias;
        }

        $tags = [
            'nombre_empleado' => (string) $empleado->nombre,
            'documento' => (string) $empleado->documento,
            'cuil' => (string) $empleado->cuil,
            'direccion' => (string) $empleado->domicilio,
            'localidad' => (string) ($empleado->localidadDomicilio->nombre ?? $empleado->localidad ?? ''),
            'provincia' => (string) ($empleado->provinciaDomicilio->nombre ?? $empleado->provincia ?? ''),
            'nacionalidad' => (string) $empleado->nacionalidad,
            'est_civil' => self::ESTADO_CIVIL[(int) $empleado->estado_civil] ?? '',
            'categoria' => (string) ($categoria->descripcion ?? ''),
            'sueldo' => number_format($sueldo, 2, ',', '.'),
            'sueldo_en_letras' => NumeroALetrasEs::monto($sueldo),
            'mod_contrato' => ContratoModalidadSijp::label($modalidad),
            'lugar' => $lugar,
            'casino' => $lugar,
            'dir_cas' => $dirLugar,
            'casino_direccion' => $dirLugar,
            'centro' => $centro,
            'comentario' => (string) ($contrato?->comentario ?? ''),
            'desdefecha' => $this->fmtFecha($desde),
            'hastafecha' => $this->fmtFecha($hasta),
            'dini' => $desde ? $desde->format('d') : '',
            'mini' => $desde ? $desde->format('m') : '',
            'mini_txt' => $desde ? $this->mesTxt((int) $desde->format('n')) : '',
            'aini' => $desde ? $desde->format('Y') : '',
            'dfin' => $hasta ? $hasta->format('d') : '',
            'mfin' => $hasta ? $hasta->format('m') : '',
            'mfin_txt' => $hasta ? $this->mesTxt((int) $hasta->format('n')) : '',
            'afin' => $hasta ? $hasta->format('Y') : '',
            'd_vig' => $diasVig,
            'dias_vigencia' => $diasVig,
            'nombre_empleado_reemplazado' => (string) ($contrato?->empleadoReemplazo->nombre ?? ''),
            'dia' => $fechaEmision->format('d'),
            'mes' => $fechaEmision->format('m'),
            'mes_txt' => $this->mesTxt((int) $fechaEmision->format('n')),
            'anio' => $fechaEmision->format('Y'),
            'd' => $fechaEmision->format('d'),
            'm' => $this->mesTxt((int) $fechaEmision->format('n')),
            'a' => $fechaEmision->format('Y'),
            'fechaing' => $this->fmtFecha($empleado->fecha_ingreso),
            'fing' => $this->fmtFecha($empleado->fecha_ingreso),
            'fechaegr' => $this->fmtFecha($empleado->fecha_egreso),
            'fecha_entrega' => $empleado->fecha_egreso
                ? $this->fmtFecha(Carbon::parse($empleado->fecha_egreso)->addDays(30))
                : '',
        ];

        return $tags;
    }

    private function fmtFecha(mixed $fecha): string
    {
        if (! $fecha) {
            return '';
        }
        if ($fecha instanceof CarbonInterface) {
            return $fecha->format('d/m/Y');
        }

        try {
            return Carbon::parse($fecha)->format('d/m/Y');
        } catch (\Throwable) {
            return '';
        }
    }

    private function mesTxt(int $mes): string
    {
        $nombres = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ];

        return $nombres[$mes] ?? '';
    }
}
