SELECT 
    e.id as id_interno, 
    e.emp_code as nomina, 
    e.first_name, 
    e.last_name, 
    d.dept_name, 
    e.status
FROM public.personnel_employee e
LEFT JOIN public.personnel_department d ON e.department_id = d.id
WHERE e.emp_code = '1206977' OR e.id = 1385;