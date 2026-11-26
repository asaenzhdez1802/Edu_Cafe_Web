-- migracion_web.sql
-- Ejecutar UNA VEZ en la base que ya tiene el SQL del equipo y la migración de la API.
-- Agrega "Nuestra historia" y la foto de la cafetería a informacion_sitio.
-- No borra datos y no afecta a la API (obtener_info.php selecciona columnas por nombre).

SET NAMES utf8mb4;

ALTER TABLE informacion_sitio
  ADD COLUMN historia TEXT NULL AFTER nosotros,
  ADD COLUMN foto_historia VARCHAR(255) NULL AFTER historia;

-- Texto inicial (el admin lo cambia en Información del sitio)
UPDATE informacion_sitio
   SET historia = 'Edu Café nació como un pequeño espacio dentro de la universidad para compartir un café entre clases. Con el tiempo sumamos comida casera, postres y un lugar cómodo para quedarse un rato.\n\nHoy somos el punto de encuentro de alumnos y maestros, y seguimos con la misma idea: buen sabor, precios accesibles y un trato amable.',
       eslogan  = 'Una pausa con *más sabor* y más color.'
 WHERE id_info = 1;
