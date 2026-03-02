BEGIN;

-- 1. Borramos cualquier intento previo de ese día para no tener basura
DELETE FROM public.iclock_transaction 
WHERE emp_id = (SELECT id FROM public.personnel_employee WHERE emp_code = '206173')
  AND punch_time::date = '2025-07-07';

-- 2. Insertamos la checada perfecta con el ajuste de +6 horas
INSERT INTO public.iclock_transaction (
    emp_id, emp_code, punch_time, punch_state, verify_type, 
    terminal_sn, terminal_alias, upload_time, terminal_id, 
    is_attendance, source, purpose, work_code, is_mask, temperature
)
SELECT 
    e.id AS emp_id, 
    e.emp_code, 
    -- AQUÍ ESTÁ LA MAGIA: 09:30 + 6 horas para compensar a Laravel
    '2025-07-07 09:30:00'::timestamp + interval '6 hours' AS punch_time, 
    '0' AS punch_state, -- Entrada
    1 AS verify_type, 
    'PRUEBA_ZONA' AS terminal_sn, 
    'API_SYNC_MANUAL' AS terminal_alias, 
    NOW() AS upload_time, 
    COALESCE((SELECT id FROM public.iclock_terminal LIMIT 1), 1) AS terminal_id, 
    1 AS is_attendance, 
    1 AS source, 
    9 AS purpose, 
    '0' AS work_code, 
    255 AS is_mask, 
    0.0 AS temperature
FROM public.personnel_employee e 
WHERE e.emp_code = '206173';

COMMIT;