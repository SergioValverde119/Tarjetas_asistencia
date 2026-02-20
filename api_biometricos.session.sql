-- ============================================================================
-- SCRIPT DE CARGA INICIAL PARA REGLAS DE ASISTENCIA
-- ============================================================================
-- Propósito: Insertar o actualizar los parámetros de tolerancia y retardos
-- en la tabla 'settings' (Estructura Llave-Valor).
-- ============================================================================

BEGIN;

-- 1. Tolerancia de Entrada (Minutos de gracia)
INSERT INTO public.settings (key, value, created_at, updated_at)
VALUES ('tolerancia_entrada', '10', NOW(), NOW())
ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, updated_at = NOW();

-- 2. Límite para Retardo Leve (RL)
-- Los minutos que pasan de la tolerancia hasta este valor se consideran leves.
INSERT INTO public.settings (key, value, created_at, updated_at)
VALUES ('limite_retardo_leve', '15', NOW(), NOW())
ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, updated_at = NOW();

-- 3. Límite para Retardo Grave (RG)
-- A partir de qué minuto se empieza a considerar una incidencia mayor.
INSERT INTO public.settings (key, value, created_at, updated_at)
VALUES ('limite_retardo_grave', '40', NOW(), NOW())
ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, updated_at = NOW();

-- 4. REGLA DE ORO: 4 Retardos Leves = 1 Retardo Grave
-- Esta es la variable que el AsistenciaService leerá para la conversión.
INSERT INTO public.settings (key, value, created_at, updated_at)
VALUES ('conteo_rl_para_rg', '4', NOW(), NOW())
ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, updated_at = NOW();

-- 5. Minutos para Falta Automática
-- Tiempo máximo permitido de retardo antes de marcar falta total.
INSERT INTO public.settings (key, value, created_at, updated_at)
VALUES ('minutos_falta_automatica', '41', NOW(), NOW())
ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, updated_at = NOW();


-- ----------------------------------------------------------------------------
-- VERIFICACIÓN FINAL: Consultar cómo quedaron los valores
-- ----------------------------------------------------------------------------
SELECT id, key, value, updated_at 
FROM public.settings 
WHERE key IN (
    'tolerancia_entrada', 
    'limite_retardo_leve', 
    'limite_retardo_grave', 
    'conteo_rl_para_rg', 
    'minutos_falta_automatica'
)
ORDER BY key ASC;

COMMIT;