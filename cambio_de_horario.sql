-- ============================================================================
-- 1. VER EL CATÁLOGO DE HORARIOS DISPONIBLES (CON DÍAS Y HORAS)
-- ============================================================================
-- Esta consulta te muestra el ID del turno, su nombre y un resumen de qué 
-- días aplica junto con su hora de entrada y salida calculada.

SELECT 
    s.id AS shift_id, 
    s.alias AS nombre_del_turno,
    STRING_AGG(
        CASE 
            WHEN sd.day_index = 0 THEN 'Dom'
            WHEN sd.day_index = 1 THEN 'Lun'
            WHEN sd.day_index = 2 THEN 'Mar'
            WHEN sd.day_index = 3 THEN 'Mie'
            WHEN sd.day_index = 4 THEN 'Jue'
            WHEN sd.day_index = 5 THEN 'Vie'
            WHEN sd.day_index = 6 THEN 'Sab'
        END || ': ' || ti.in_time || ' a ' || (ti.in_time::time + (ti.work_time_duration || ' minutes')::interval)::time,
        ' | ' ORDER BY sd.day_index
    ) AS detalle_semanal
FROM public.att_attshift s
LEFT JOIN public.att_shiftdetail sd ON s.id = sd.shift_id
LEFT JOIN public.att_timeinterval ti ON sd.time_interval_id = ti.id
GROUP BY s.id, s.alias
ORDER BY s.alias;


-- ============================================================================
-- 2. IDENTIFICAR AL EMPLEADO Y SU HORARIO ACTUAL
-- ============================================================================
-- Cambia '242407' por la nómina del empleado para ver qué tiene asignado.

SELECT 
    e.id AS employee_id, 
    e.emp_code AS nomina, 
    e.first_name || ' ' || e.last_name AS nombre,
    s.alias AS turno_actual,
    sch.start_date,
    sch.end_date
FROM public.personnel_employee e
LEFT JOIN public.att_attschedule sch ON e.id = sch.employee_id
LEFT JOIN public.att_attshift s ON sch.shift_id = s.id
WHERE e.emp_code = '242407'; -- <--- PON AQUÍ LA NÓMINA


-- ============================================================================
-- 3. ASIGNAR UN NUEVO HORARIO A UN SOLO EMPLEADO (MÉTODO MEJORADO)
-- ============================================================================
-- MEJORA: Ya no necesitas buscar el ID interno manualmente. El SQL lo busca por ti 
-- usando la nómina. Descomenta (quita los /* */) de la opción que necesites.

-- ----------------------------------------------------------------------------
-- OPCIÓN A: El empleado NO tenía horario individual (Crear nuevo)
-- ----------------------------------------------------------------------------
/*
BEGIN;

INSERT INTO public.att_attschedule (
    employee_id, 
    shift_id, 
    start_date, 
    end_date
) VALUES (
    (SELECT id FROM public.personnel_employee WHERE emp_code = '242407'), -- [NÓMINA]
    115,          -- [CAMBIAR] ID del turno que elegiste en el paso 1
    '2024-01-01', -- Fecha desde cuándo aplica
    '2030-12-31'  -- Fecha fin límite (2030 recomendado)
);

COMMIT;
*/


-- ----------------------------------------------------------------------------
-- OPCIÓN B: El empleado YA TENÍA UNO y solo quieres REEMPLAZARLO completamente
-- ----------------------------------------------------------------------------
/*
BEGIN;

UPDATE public.att_attschedule 
SET 
    shift_id = 120,               -- [CAMBIAR] Nuevo ID del turno
    end_date = '2030-12-31'       -- [CAMBIAR] Nueva fecha de vigencia
WHERE employee_id = (SELECT id FROM public.personnel_employee WHERE emp_code = '242407'); -- [NÓMINA]

COMMIT;
*/


-- ----------------------------------------------------------------------------
-- OPCIÓN C: CORTAR EL ANTERIOR Y EMPEZAR UNO NUEVO (RECOMENDADO PARA HISTORIAL)
-- Si tenía un horario viejo, lo cerramos ayer, y le abrimos uno nuevo hoy.
-- ----------------------------------------------------------------------------
/*
BEGIN;

-- 1. Cerramos el horario viejo acortando su fecha de fin
UPDATE public.att_attschedule 
SET end_date = '2025-12-31' -- [CAMBIAR] Un día ANTES de que empiece el nuevo
WHERE employee_id = (SELECT id FROM public.personnel_employee WHERE emp_code = '242407')
  AND end_date > '2025-12-31'; -- Asegura que solo cortamos si superaba esta fecha

-- 2. Insertamos el nuevo horario en su propia línea
INSERT INTO public.att_attschedule (
    employee_id, 
    shift_id, 
    start_date, 
    end_date
) VALUES (
    (SELECT id FROM public.personnel_employee WHERE emp_code = '242407'), -- [NÓMINA]
    115,          -- [CAMBIAR] ID del NUEVO turno
    '2026-01-01', -- [CAMBIAR] Fecha desde cuándo aplica el nuevo
    '2026-12-31'  -- Fecha fin límite
);

COMMIT;
*/


-- ============================================================================
-- 4. VERIFICAR EL CAMBIO
-- ============================================================================
-- Vuelve a correr esta consulta para confirmar que el 'turno_asignado' y la fecha cambiaron.

SELECT 
    e.emp_code, 
    e.first_name || ' ' || e.last_name AS empleado, 
    s.alias as turno_asignado,
    sch.start_date as inicia_el,
    sch.end_date as vence_el
FROM public.personnel_employee e
JOIN public.att_attschedule sch ON e.id = sch.employee_id
JOIN public.att_attshift s ON sch.shift_id = s.id
WHERE e.emp_code = '242407'
ORDER BY sch.start_date DESC;