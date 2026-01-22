WITH RECURSIVE calendario_dias AS (
                SELECT ?::date AS fecha
                UNION ALL
                SELECT (fecha + interval '1 day')::date
                FROM calendario_dias
                WHERE fecha < ?::date
            ),
            asignacion_horario AS (
                SELECT 
                    e.id as emp_id,
                    e.enable_holiday,
                    pd.dept_name as department_name,
                    COALESCE(sch.shift_id, ds.shift_id) as shift_id
                FROM public.personnel_employee e
                LEFT JOIN public.personnel_department pd ON e.department_id = pd.id
                LEFT JOIN public.att_attschedule sch ON e.id = sch.employee_id 
                    AND ?::date BETWEEN sch.start_date AND sch.end_date
                LEFT JOIN public.att_departmentschedule ds ON e.department_id = ds.department_id
                WHERE e.id = ?
                LIMIT 1
            ),
            horario_base AS (
                SELECT DISTINCT ON (sd.shift_id)
                    sd.shift_id,
                    ti.in_time as shift_in_time,
                    ti.work_time_duration as shift_duration
                FROM public.att_shiftdetail sd
                JOIN public.att_timeinterval ti ON sd.time_interval_id = ti.id
                ORDER BY sd.shift_id, sd.day_index ASC
            ),
            jornada_esperada AS (
                SELECT 
                    cd.fecha,
                    ah.emp_id,
                    ah.enable_holiday,
                    ah.department_name,
                    ti.alias as timetable_alias,
                    ti.in_time,
                    ti.work_time_duration as duration, 
                    ti.allow_late,
                    hb.shift_in_time,
                    hb.shift_duration
                FROM calendario_dias cd
                CROSS JOIN asignacion_horario ah
                LEFT JOIN horario_base hb ON ah.shift_id = hb.shift_id
                LEFT JOIN public.att_shiftdetail sd ON ah.shift_id = sd.shift_id 
                    AND sd.day_index = extract(dow from cd.fecha)::int 
                LEFT JOIN public.att_timeinterval ti ON sd.time_interval_id = ti.id
            )
            SELECT 
                je.fecha as att_date,
                je.timetable_alias as timetable_name,
                je.department_name,
                
                -- Procesamiento de huellas del reloj
                TO_CHAR(p.entrada, 'YYYY-MM-DD HH24:MI:SS') as clock_in,
                TO_CHAR(p.salida, 'YYYY-MM-DD HH24:MI:SS') as clock_out,

                -- Datos de horario y duración
                COALESCE(je.in_time, je.shift_in_time) as in_time,
                COALESCE(je.duration, je.shift_duration) as duration,
                je.allow_late,
                (je.fecha || ' ' || COALESCE(je.in_time, je.shift_in_time, '00:00:00'))::timestamp as check_in,
                (COALESCE(je.in_time, je.shift_in_time, '00:00:00')::time + (COALESCE(je.duration, je.shift_duration, 0) || ' minutes')::interval)::time as off_time,
                
                je.enable_holiday,
                al.apply_reason

            FROM jornada_esperada je
            LEFT JOIN LATERAL (
                SELECT 
                    MIN(punch_time) as entrada,
                    CASE WHEN MAX(punch_time) > MIN(punch_time) THEN MAX(punch_time) ELSE NULL END as salida
                FROM public.iclock_transaction 
                WHERE emp_id = je.emp_id AND punch_time::date = je.fecha
            ) p ON true
            
            -- LÓGICA DE INCIDENCIAS: Prioriza la que inicia el día actual y resta 1 segundo al final
            LEFT JOIN LATERAL (
                SELECT apply_reason 
                FROM public.att_leave 
                WHERE employee_id = je.emp_id 
                AND je.fecha BETWEEN start_time::date AND (end_time - interval '1 second')::date
                ORDER BY 
                    (start_time::date = je.fecha) DESC, -- Prioridad 1: Inicia hoy
                    (end_time - start_time) ASC,       -- Prioridad 2: La más específica/corta
                    start_time DESC                      -- Prioridad 3: La más reciente
                LIMIT 1
            ) al ON true
            
            ORDER BY je.fecha ASC;