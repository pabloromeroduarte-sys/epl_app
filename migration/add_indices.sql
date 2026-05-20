-- Índices para mejorar performance en consultas frecuentes
-- Ejecutar una sola vez. Usa IF NOT EXISTS (MySQL 8+) o se puede ignorar error de "duplicate key".

-- partidos: búsquedas por estado, fecha y liga
ALTER TABLE partidos
  ADD INDEX IF NOT EXISTS idx_estado_fecha   (estado, fecha_programada),
  ADD INDEX IF NOT EXISTS idx_liga_jornada   (liga_id, jornada),
  ADD INDEX IF NOT EXISTS idx_fecha_prog     (fecha_programada);

-- notificaciones: ya tiene idx_jugador_leida — agregar por tipo
ALTER TABLE notificaciones
  ADD INDEX IF NOT EXISTS idx_tipo_created   (tipo, created_at);

-- solicitudes_reprogramacion: búsquedas por partido y estado
ALTER TABLE solicitudes_reprogramacion
  ADD INDEX IF NOT EXISTS idx_partido_estado (partido_id, estado);
