-- ============================================================================
-- 1. VER EL CATÁLOGO DE HORARIOS DISPONIBLES
-- ============================================================================
-- Usa esta consulta para identificar el 'shift_id' (ID del turno) 
-- que quieres asignar.

SELECT 
    id AS shift_id, 
    alias AS nombre_del_turno 
FROM public.att_shift 
ORDER BY alias;


-- ============================================================================
-- 2. IDENTIFICAR AL EMPLEADO Y SU HORARIO ACTUAL
-- ============================================================================
-- Cambia '936049' por la nómina del empleado para obtener su ID interno.

SELECT 
    e.id AS employee_id, 
    e.emp_code AS nomina, 
    e.first_name || ' ' || e.last_name AS nombre,
    s.alias AS turno_actual,
    sch.start_date,
    sch.end_date
FROM public.personnel_employee e
LEFT JOIN public.att_attschedule sch ON e.id = sch.employee_id
LEFT JOIN public.att_shift s ON sch.shift_id = s.id
WHERE e.emp_code = '936049'; -- <--- PON AQUÍ LA NÓMINA


-- ============================================================================
-- 3. ASIGNAR UN NUEVO HORARIO A UN SOLO EMPLEADO
-- ============================================================================
-- BioTime usa la tabla 'att_attschedule' para las excepciones individuales.
-- Si el empleado ya tiene un registro aquí, debes usar UPDATE. 
-- Si no tiene nada (usa el de su departamento), debes usar INSERT.

-- OPCIÓN A: El empleado NO tenía horario individual (Crear nuevo)
/*
INSERT INTO public.att_attschedule (
    employee_id, 
    shift_id, 
    start_date, 
    end_date
) VALUES (
    726,          -- [CAMBIAR] ID interno que obtuviste en el paso 2
    115,          -- [CAMBIAR] ID del turno que elegiste en el paso 1
    '2025-01-01', -- Fecha desde cuándo aplica
    '2099-12-31'  -- Fecha fin (permanente)
);
*/

-- OPCIÓN B: El empleado YA tenía uno y solo quieres cambiarlo
/*
UPDATE public.att_attschedule 
SET 
    shift_id = 120,      -- [CAMBIAR] Nuevo ID del turno
    start_date = '2025-02-01'
WHERE employee_id = 726; -- [CAMBIAR] ID interno del empleado
*/


-- ============================================================================
-- 4. VERIFICAR EL CAMBIO
-- ============================================================================
-- Vuelve a correr esta consulta para confirmar que el 'turno_actual' cambió.

SELECT 
    e.emp_code, 
    e.first_name, 
    s.alias as turno_asignado 
FROM public.personnel_employee e
JOIN public.att_attschedule sch ON e.id = sch.employee_id
JOIN public.att_shift s ON sch.shift_id = s.id
WHERE e.emp_code = '936049';