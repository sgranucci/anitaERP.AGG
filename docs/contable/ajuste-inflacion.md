# Ajuste por inflación contable

## Alcance

Proceso RT 6 con el flujo usado por sistemas contables premium:

1. Maestro mensual de índices FACPCE.
2. Configuración por empresa de cuenta RECPAM, centro de costo y cuentas alcanzadas.
3. Simulación sin asiento.
4. Papel de trabajo trazable y exportable.
5. Confirmación idempotente y generación de asiento `AJ`.

La primera versión anticuará por mes los movimientos contables. Las capas específicas de
bienes de uso, bienes de cambio, aportes y NIIF 16 se incorporarán como métodos de anticuación
especializados; no deben forzarse dentro del método mensual general.

## Convenciones

- Período del índice: primer día del mes (`YYYY-MM-01`).
- Coeficiente: `índice de cierre / índice de origen`.
- Importe reexpresado: `saldo de origen × coeficiente`.
- Ajuste: `importe reexpresado − saldo de origen`.
- Movimiento positivo: Debe.
- Movimiento negativo: Haber.
- La contrapartida es el negativo de la suma de ajustes y se imputa a RECPAM.
- Los asientos de cierre e inflación preexistentes se excluyen de la base de cálculo.
- Un asiento `APE` al inicio del período se considera expresado al cierre del mes anterior.

## Configuración AGG verificada

| Empresa | Cuenta RECPAM | Centro de costo |
|---|---|---|
| Biyemas | `533030001` — RECPAM DEL EJERCICIO | `97` — Gcia de Administración |
| Kandiko | `533030001` — RECPAM DEL EJERCICIO | `97` — Gcia de Administración |
| Rebisco | `533030001` — RECPAM DEL EJERCICIO | `97` — Gcia de Administración |

Los IDs no se parametrizan ni se migran: siempre se resuelven por empresa y código.

## Inicialización de cuentas

La acción **Inicializar desde último AJ** toma, para la empresa elegida, el asiento `AJ`
con más líneas de la última fecha disponible. Incorpora sus cuentas imputables y excluye
la cuenta RECPAM.

El resultado debe ser revisado por Contaduría antes de la primera simulación. El campo
legacy `cuentacontable.monetaria` no se usa para decidir el alcance porque su etiqueta ERP
y el campo Anita `ctam_ajustable` tienen semánticas históricas contradictorias.

## Confirmación e idempotencia

- Solo puede existir una corrida confirmada por empresa y fecha de cierre.
- Antes de confirmar se recalcula la firma con los índices, cuentas y movimientos vigentes.
- Si cambió cualquier dato desde la simulación, la confirmación se rechaza y se debe simular otra vez.
- La corrida se graba primero sin escribir Anita; luego se crean las líneas, se valida el balance
  y finalmente se sincroniza `ctamov`.
- Una corrida confirmada no se elimina. Su corrección requiere un proceso de reverso explícito.

## Índices

La instalación precarga la serie FACPCE desde diciembre de 2024 hasta el último mes publicado
al momento del desarrollo. La pantalla permite:

- alta o corrección manual;
- marcar un valor como provisorio;
- importar CSV con `periodo,valor` y opcionalmente `fuente,provisorio`.

El motor corta si falta el índice de cierre o cualquiera de los meses de origen utilizados.
