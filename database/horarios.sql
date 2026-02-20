-- ============================================================================
-- MANUAL DE OPERACIÓN DE HORARIOS VÍA SQL (BIOTIME)
-- ============================================================================
SELECT table_schema, table_name 
FROM information_schema.tables 
WHERE table_name LIKE '%employee%' 
   OR table_name LIKE '%personnel%';

-- B. Listar todas las tablas relacionadas con horarios (shifts/intervals)
SELECT table_name 
FROM information_schema.tables 
WHERE table_schema = 'public'
ORDER BY table_name;
-- ----------------------------------------------------------------------------
-- 1. VER EL HISTORIAL DE HORARIOS DE UNA PERSONA
-- ----------------------------------------------------------------------------
-- Esta consulta te dice qué turnos ha tenido, tiene y tendrá un empleado.
SELECT 
    e.emp_code AS nomina,
    e.first_name || ' ' || e.last_name AS empleado,
    s.alias AS nombre_del_turno,
    -- Obtenemos las horas desde el intervalo vinculado
    ti.in_time AS hora_entrada,
    (ti.in_time::time + (ti.work_time_duration || ' minutes')::interval)::time AS hora_salida,
    sch.start_date AS desde,
    sch.end_date AS hasta,
    sch.id AS id_asignacion
FROM public.personnel_employee e
JOIN public.att_attschedule sch ON e.id = sch.employee_id
JOIN public.att_attshift s ON sch.shift_id = s.id
-- Cruzamos con el detalle del turno para obtener el horario real
LEFT JOIN LATERAL (
    SELECT ti_inner.in_time, ti_inner.work_time_duration
    FROM public.att_shiftdetail sd
    JOIN public.att_timeinterval ti_inner ON sd.time_interval_id = ti_inner.id
    WHERE sd.shift_id = s.id
    ORDER BY sd.day_index ASC
    LIMIT 1
) ti ON TRUE
WHERE e.emp_code = '918561' -- <--- CAMBIA LA NÓMINA AQUÍ
ORDER BY sch.start_date DESC;


-- ----------------------------------------------------------------------------
-- 2. CREAR UN HORARIO DESDE CERO (9:00 AM - 3:00 PM como ejemplo)
-- ----------------------------------------------------------------------------
BEGIN;

-- PASO A: Crear la regla de tiempo (Intervalo)
-- Entrada 09:00, Duración 360 min (6 horas), Tolerancia 10 min (BioTime guarda 11)
INSERT INTO public.att_timeinterval (
    alias, in_time, work_time_duration, allow_late, 
    in_ahead_margin, out_above_margin, is_default
) VALUES (
    'Entrada 09:00 (6hrs)', '09:00:00', 360, 11, 120, 120, true
) RETURNING id; -- Supongamos que nos da el ID 500

-- PASO B: Crear el nombre del Turno (Shift)
INSERT INTO public.att_attshift (alias) 
VALUES ('TURNO ESPECIAL 9 A 3') 
RETURNING id; -- Supongamos que nos da el ID 200

-- PASO C: Vincular días de la semana (ShiftDetail)
-- day_index: 0=Dom, 1=Lun, 2=Mar, 3=Mie, 4=Jue, 5=Vie, 6=Sab
-- Usamos el ID del Shift (200) y el ID del Intervalo (500)
INSERT INTO public.att_shiftdetail (shift_id, day_index, time_interval_id)
VALUES 
(200, 1, 500), (200, 2, 500), (200, 3, 500), (200, 4, 500), (200, 5, 500);

COMMIT;


-- ----------------------------------------------------------------------------
-- 3. MODIFICAR UN HORARIO EXISTENTE
-- ----------------------------------------------------------------------------
-- OPCIÓN A: Cambiar la hora de entrada de un horario que ya usan muchas personas.
-- [CUIDADO]: Esto afectará a todos los que tengan este horario asignado.
UPDATE public.att_timeinterval 
SET in_time = '08:00:00' 
WHERE id = (SELECT time_interval_id FROM public.att_shiftdetail WHERE shift_id = 115 LIMIT 1);

-- OPCIÓN B: Cambiar el nombre visual del turno
UPDATE public.att_attshift 
SET alias = 'ADMINISTRATIVO ACTUALIZADO' 
WHERE id = 115;


-- ----------------------------------------------------------------------------
-- 4. ASIGNAR HORARIO A UNA PERSONA (SIN BORRAR EL ANTERIOR)
-- ----------------------------------------------------------------------------
-- Idealmente, los horarios no se borran, se "vencen".
-- Primero le ponemos fecha de fin al horario actual para que termine ayer.
UPDATE public.att_attschedule 
SET end_date = CURRENT_DATE - interval '1 day'
WHERE employee_id = (SELECT id FROM public.personnel_employee WHERE emp_code = '806006')
AND CURRENT_DATE BETWEEN start_date AND end_date;

-- Luego insertamos el nuevo que empieza hoy.
INSERT INTO public.att_attschedule (employee_id, shift_id, start_date, end_date)
SELECT id, 200, CURRENT_DATE, '2030-12-31'
FROM public.personnel_employee WHERE emp_code = '806006';


-- ----------------------------------------------------------------------------
-- 5. CONSULTA RÁPIDA: ¿Qué horario tiene alguien HOY?
-- ----------------------------------------------------------------------------
SELECT 
    e.emp_code, e.first_name, s.alias as turno_hoy,
    ti.in_time, ti.work_time_duration
FROM public.personnel_employee e
JOIN public.att_attschedule sch ON e.id = sch.employee_id
JOIN public.att_attshift s ON sch.shift_id = s.id
JOIN public.att_shiftdetail sd ON s.id = sd.shift_id AND sd.day_index = extract(dow from CURRENT_DATE)::int
JOIN public.att_timeinterval ti ON sd.time_interval_id = ti.id
WHERE e.emp_code = '806006' 
AND CURRENT_DATE BETWEEN sch.start_date AND sch.end_date;