-- ============================================================================
-- SCRIPT DE AJUSTE TEMPORAL: CASO CARLOS ALBERTO LEMUS (918561)
-- ============================================================================
-- Objetivo: Insertar el turno T20-10 (08:30 - 15:30) del 15 al 19 de Dic 2025.
-- Horario Original afectado: T62-10 (ID Asignación 1619)
-- Se agregaron conversiones de tipo (::text) para evitar el error de bigint.
-- ============================================================================

BEGIN;

DO $$ 
DECLARE 
    v_nomina VARCHAR := '918561';           -- Nómina del empleado
    v_turno_objetivo VARCHAR := 'T20-10';  -- El que queremos (15:30)
    v_id_asignacion_actual INTEGER := 1619; -- El ID que vamos a fragmentar
    
    v_emp_id INTEGER;
    v_shift_especial_id INTEGER;
    v_shift_original_id INTEGER;
    
    v_inicio_ajuste DATE := '2025-12-15';
    v_fin_ajuste    DATE := '2025-12-19';
    v_fin_original  DATE := '2025-12-31';   -- Fecha hasta donde llegaba el original
BEGIN
    -- 1. Obtener ID interno del empleado (Casting a TEXT para evitar error de tipos)
    SELECT id INTO v_emp_id 
    FROM public.personnel_employee 
    WHERE emp_code::text = v_nomina::text;
    
    -- 2. Obtener ID del turno T20-10 (El de las 3:30)
    SELECT id INTO v_shift_especial_id 
    FROM public.att_attshift 
    WHERE alias::text = v_turno_objetivo::text;

    -- 3. CERRAR EL TRAMO ACTUAL (PARTE 1 DEL SÁNDWICH)
    UPDATE public.att_attschedule 
    SET end_date = v_inicio_ajuste - interval '1 day'
    WHERE id = v_id_asignacion_actual
    RETURNING shift_id INTO v_shift_original_id;

    -- 4. INSERTAR EL TRAMO ESPECIAL (PARTE 2 - EL RELLENO)
    INSERT INTO public.att_attschedule (employee_id, shift_id, start_date, end_date)
    VALUES (v_emp_id, v_shift_especial_id, v_inicio_ajuste, v_fin_ajuste);

    -- 5. RE-ABRIR EL HORARIO ORIGINAL (PARTE 3 - EL REGRESO)
    INSERT INTO public.att_attschedule (employee_id, shift_id, start_date, end_date)
    VALUES (v_emp_id, v_shift_original_id, v_fin_ajuste + interval '1 day', v_fin_original);

    RAISE NOTICE 'Sándwich de horarios aplicado: El registro 1619 se fragmentó en 3 partes.';
END $$;

-- ----------------------------------------------------------------------------
-- VERIFICACIÓN DEL RESULTADO (Con casting de tipos para seguridad)
-- ----------------------------------------------------------------------------
SELECT 
    sch.id AS id_registro,
    s.alias AS turno,
    ti.in_time AS entrada,
    (ti.in_time::time + (ti.work_time_duration || ' minutes')::interval)::time AS salida,
    sch.start_date AS desde,
    sch.end_date AS hasta
FROM public.att_attschedule sch
JOIN public.att_attshift s ON sch.shift_id = s.id
JOIN public.att_shiftdetail sd ON s.id = sd.shift_id AND sd.day_index = 1 
JOIN public.att_timeinterval ti ON sd.time_interval_id = ti.id
WHERE sch.employee_id = (SELECT id FROM public.personnel_employee WHERE emp_code::text = '918561')
  AND sch.start_date >= '2025-08-01'
ORDER BY sch.start_date ASC;

-- IMPORTANTE: Revisa bien las fechas en la tabla de arriba.
-- Si todo es correcto, ejecuta: COMMIT;
-- Si no, ejecuta: ROLLBACK;
COMMIT;