-- ============================================================================
-- SCRIPT DE ALINEACIÓN DE FOLIOS Y LIMPIEZA DE HUÉRFANOS
-- Ejecutar en la base de datos ORIGINAL (o donde recibas las incidencias)
-- ============================================================================

BEGIN;

-- 1. BORRAR REGISTROS HUÉRFANOS
-- Eliminamos los folios que tienen "cascarón" en la tabla padre (workflow) 
-- pero no tienen el detalle en la tabla hija (att_leave). 
-- Estos son los restos de intentos fallidos que bloquean el sistema.
DELETE FROM public.workflow_abstractexception
WHERE id NOT IN (SELECT abstractexception_ptr_id FROM public.att_leave);

-- 2. REINICIAR LA SECUENCIA AL VALOR REAL
-- Buscamos el ID más alto que existe actualmente en la tabla y le decimos 
-- a la secuencia que el siguiente número disponible debe ser ese + 1.
SELECT setval(
    'public.workflow_abstractexception_id_seq', 
    (SELECT MAX(id) FROM public.workflow_abstractexception), 
    true
);

COMMIT;

-- ============================================================================
-- VERIFICACIÓN FINAL
-- ============================================================================
-- El número que salga aquí es el que usará el PRÓXIMO registro que insertes.
SELECT nextval('public.workflow_abstractexception_id_seq') AS proximo_folio_disponible;