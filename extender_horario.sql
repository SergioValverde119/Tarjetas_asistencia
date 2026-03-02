-- ============================================================================
-- SCRIPT DE 2 PASOS: REVISAR Y EXTENDER HORARIO POR NÓMINA
-- ============================================================================
-- Propósito: Primero vemos la fecha de vencimiento, luego ejecutamos 
-- la transacción para extenderlo, usando únicamente el emp_code.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- PASO 1: VER CUÁNDO VENCE EL HORARIO ACTUAL (Ejecuta esto solo primero)
-- ----------------------------------------------------------------------------
-- Reemplaza '918561' con el número de nómina que quieres revisar.
SELECT 
    e.emp_code AS nomina,
    e.first_name || ' ' || e.last_name AS empleado,
    sch.start_date AS inicia,
    sch.end_date AS vence_el,
    s.alias AS turno_asignado
FROM public.personnel_employee e
JOIN public.att_attschedule sch ON e.id = sch.employee_id
JOIN public.att_attshift s ON sch.shift_id = s.id
WHERE e.emp_code = '918561'
ORDER BY sch.end_date DESC;


-- ----------------------------------------------------------------------------
-- PASO 2: LA TRANSACCIÓN PARA EXTENDER EL LÍMITE (Ejecuta este bloque completo)
-- ----------------------------------------------------------------------------
-- Una vez que confirmes el vencimiento arriba, ejecuta este bloque para 
-- ponerle una fecha lejana (ej. 2030-12-31).

BEGIN;

-- Actualizamos específicamente el último horario registrado del empleado
UPDATE public.att_attschedule
SET end_date = '2030-12-31'  -- <--- La nueva fecha de vencimiento
WHERE id = (
    SELECT sch.id 
    FROM public.att_attschedule sch
    JOIN public.personnel_employee e ON sch.employee_id = e.id
    WHERE e.emp_code = '918561' -- <--- La misma nómina
    ORDER BY sch.end_date DESC 
    LIMIT 1
);

-- Si todo está bien, confirma el cambio:
COMMIT;

-- Si cometiste un error antes del COMMIT, ejecuta:
-- ROLLBACK;