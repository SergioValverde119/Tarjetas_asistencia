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
                    pd.dept_name as department_name, -- Agregamos el departamento aquí
                    COALESCE(sch.shift_id, ds.shift_id) as shift_id
                FROM public.personnel_employee e
                LEFT JOIN public.personnel_department pd ON e.department_id = pd.id
                LEFT JOIN public.att_attschedule sch ON e.id = sch.employee_id 
                    AND ?::date BETWEEN sch.start_date AND sch.end_date
                LEFT JOIN public.att_departmentschedule ds ON e.department_id = ds.department_id
                WHERE e.id = ?
                LIMIT 1
            ),
            jornada_esperada AS (
                SELECT 
                    cd.fecha,
                    ah.emp_id,
                    ah.enable_holiday,
                    ah.department_name, -- Lo pasamos al siguiente bloque
                    ti.alias as timetable_alias, 
                    ti.in_time,
                    ti.work_time_duration as duration, 
                    ti.allow_late
                FROM calendario_dias cd
                CROSS JOIN asignacion_horario ah
                LEFT JOIN public.att_shiftdetail sd ON ah.shift_id = sd.shift_id 
                    AND sd.day_index = extract(dow from cd.fecha)::int 
                LEFT JOIN public.att_timeinterval ti ON sd.time_interval_id = ti.id
            )
            SELECT 
                je.fecha as att_date,
                
                -- Nombre del horario para el servicio
                je.timetable_alias as timetable_name,

                -- Nombre del departamento solicitado
                je.department_name,
                
                -- USAMOS TO_CHAR para que el string llegue limpio a Laravel sin el '-06'
                (SELECT TO_CHAR(MIN(punch_time), 'YYYY-MM-DD HH24:MI:SS') 
                 FROM public.iclock_transaction 
                 WHERE emp_id = je.emp_id AND punch_time::date = je.fecha) as clock_in,
                 
                (SELECT TO_CHAR(MAX(punch_time), 'YYYY-MM-DD HH24:MI:SS') 
                 FROM public.iclock_transaction 
                 WHERE emp_id = je.emp_id AND punch_time::date = je.fecha 
                 AND punch_time > (SELECT MIN(p2.punch_time) FROM public.iclock_transaction p2 WHERE p2.emp_id = je.emp_id AND p2.punch_time::date = je.fecha)) as clock_out,

                je.in_time,
                je.duration,
                je.allow_late,
                (je.fecha || ' ' || COALESCE(je.in_time, '00:00:00'))::timestamp as check_in,
                
                -- Cálculo de salida oficial (Entrada + Duración)
                (COALESCE(je.in_time, '00:00:00')::time + (COALESCE(je.duration, 0) || ' minutes')::interval)::time as off_time,
                
                je.enable_holiday,
                al.apply_reason

            FROM jornada_esperada je
            LEFT JOIN public.att_leave al ON je.emp_id = al.employee_id 
                AND al.start_time::date = je.fecha
            ORDER BY je.fecha ASC;