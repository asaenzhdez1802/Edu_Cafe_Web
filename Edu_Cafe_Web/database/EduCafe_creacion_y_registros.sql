-- EDU CAFE: creacion de base de datos, tablas y registros.
-- Extraido del archivo educafe (1)(1).sql. Conserva sus nombres y datos.
-- Instalacion en base vacia; NO es una migracion de una base existente.
-- Sin DROP, vistas, triggers, procedimientos ni ejemplos de operaciones PHP.
-- La reserva, el vencimiento y el cobro requieren logica PHP posterior.
SET NAMES utf8mb4;
SET time_zone = '-06:00';
CREATE DATABASE IF NOT EXISTS educafe
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE educafe;

-- TABLAS
CREATE TABLE usuarios (
  id_usuario      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre          VARCHAR(100) NOT NULL,
  usuario         VARCHAR(50)  NOT NULL,
  celular         VARCHAR(10)  NULL,
  password_hash   VARCHAR(255) NOT NULL,
  rol             ENUM('admin','cajero','cliente') NOT NULL DEFAULT 'cliente',
  puntos          INT UNSIGNED NOT NULL DEFAULT 0,
  estado          ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  fecha_registro  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_acceso   DATETIME NULL,
  PRIMARY KEY (id_usuario),
  UNIQUE KEY uq_usuarios_usuario (usuario),
  UNIQUE KEY uq_usuarios_celular (celular),
  KEY idx_usuarios_rol (rol, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categorias (
  id_categoria    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre          VARCHAR(60)  NOT NULL,
  descripcion     VARCHAR(200) NULL,
  mostrar_filtro  TINYINT(1)   NOT NULL DEFAULT 1,
  estado          ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  PRIMARY KEY (id_categoria),
  UNIQUE KEY uq_categorias_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE productos (
  id_producto      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo           VARCHAR(20)  NOT NULL,
  nombre           VARCHAR(100) NOT NULL,
  descripcion      VARCHAR(255) NULL,
  id_categoria     INT UNSIGNED NOT NULL,
  precio_compra    DECIMAL(10,2) NOT NULL DEFAULT 0,
  precio_venta     DECIMAL(10,2) NOT NULL,
  stock            INT NOT NULL DEFAULT 0,
  stock_reservado  INT NOT NULL DEFAULT 0,
  stock_minimo     INT NOT NULL DEFAULT 5,
  imagen           VARCHAR(255) NULL,
  visible          TINYINT(1)   NOT NULL DEFAULT 1,
  disponible_hoy   TINYINT(1)   NOT NULL DEFAULT 1,
  estado           ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  fecha_registro   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_producto),
  UNIQUE KEY uq_productos_codigo (codigo),
  KEY idx_productos_menu (estado, visible, disponible_hoy),
  CONSTRAINT fk_productos_categoria FOREIGN KEY (id_categoria)
    REFERENCES categorias (id_categoria) ON UPDATE CASCADE,
  CONSTRAINT chk_productos_precios CHECK (precio_venta >= 0 AND precio_compra >= 0),
  CONSTRAINT chk_productos_stock CHECK (stock >= 0 AND stock_reservado >= 0 AND stock_reservado <= stock)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventario_movimientos (
  id_movimiento   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_producto     INT UNSIGNED NOT NULL,
  tipo            ENUM('entrada','salida','ajuste') NOT NULL,
  cantidad        INT NOT NULL,
  stock_anterior  INT NOT NULL,
  stock_nuevo     INT NOT NULL,
  motivo          VARCHAR(150) NULL,
  id_usuario      INT UNSIGNED NULL,
  fecha           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_movimiento),
  KEY idx_movimientos_fecha (fecha),
  CONSTRAINT fk_movimientos_producto FOREIGN KEY (id_producto)
    REFERENCES productos (id_producto) ON UPDATE CASCADE,
  CONSTRAINT fk_movimientos_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recompensas (
  id_recompensa     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre            VARCHAR(80)  NOT NULL,
  descripcion       VARCHAR(200) NULL,
  puntos_requeridos INT UNSIGNED NOT NULL,
  tipo              ENUM('producto','descuento') NOT NULL DEFAULT 'producto',
  id_producto       INT UNSIGNED NULL,
  valor_descuento   DECIMAL(10,2) NULL,
  estado            ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  PRIMARY KEY (id_recompensa),
  CONSTRAINT fk_recompensas_producto FOREIGN KEY (id_producto)
    REFERENCES productos (id_producto) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_recompensas_puntos CHECK (puntos_requeridos > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE regla_puntos (
  id_regla        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  monto           DECIMAL(10,2) NOT NULL,
  puntos          INT UNSIGNED NOT NULL,
  fecha_inicio    DATE NOT NULL,
  fecha_fin       DATE NULL,
  id_usuario      INT UNSIGNED NULL,
  PRIMARY KEY (id_regla),
  CONSTRAINT fk_regla_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_regla_valores CHECK (monto > 0 AND puntos > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pedidos (
  id_pedido           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  folio               VARCHAR(12)  NULL,
  codigo_recogida     CHAR(7)      NOT NULL,
  id_cliente          INT UNSIGNED NOT NULL,
  fecha               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_vence         DATETIME NOT NULL,
  total               DECIMAL(10,2) NOT NULL DEFAULT 0,
  notas               VARCHAR(255) NULL,
  estado              ENUM('pendiente','listo','entregado','cancelado') NOT NULL DEFAULT 'pendiente',
  id_cajero           INT UNSIGNED NULL,
  fecha_listo         DATETIME NULL,
  fecha_entregado     DATETIME NULL,
  fecha_cancelacion   DATETIME NULL,
  motivo_cancelacion  ENUM('cliente','cajero','vencido','venta_cancelada') NULL,
  PRIMARY KEY (id_pedido),
  UNIQUE KEY uq_pedidos_folio (folio),
  UNIQUE KEY uq_pedidos_codigo (codigo_recogida),
  KEY idx_pedidos_estado (estado, fecha),
  CONSTRAINT fk_pedidos_cliente FOREIGN KEY (id_cliente)
    REFERENCES usuarios (id_usuario) ON UPDATE CASCADE,
  CONSTRAINT fk_pedidos_cajero FOREIGN KEY (id_cajero)
    REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_pedidos_total CHECK (total >= 0),
  CONSTRAINT chk_pedidos_cancelacion CHECK (estado <> 'cancelado' OR motivo_cancelacion IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE detalle_pedido (
  id_detalle       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_pedido        INT UNSIGNED NOT NULL,
  id_producto      INT UNSIGNED NOT NULL,
  cantidad         INT UNSIGNED NOT NULL,
  precio_unitario  DECIMAL(10,2) NOT NULL,
  subtotal         DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (id_detalle),
  UNIQUE KEY uq_detped_producto (id_pedido, id_producto),
  CONSTRAINT fk_detped_pedido FOREIGN KEY (id_pedido)
    REFERENCES pedidos (id_pedido) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_detped_producto FOREIGN KEY (id_producto)
    REFERENCES productos (id_producto) ON UPDATE CASCADE,
  CONSTRAINT chk_detped_cantidad CHECK (cantidad > 0 AND subtotal = cantidad * precio_unitario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ventas (
  id_venta            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  folio               VARCHAR(12)  NULL,
  fecha               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  id_pedido           INT UNSIGNED NULL,
  id_cajero           INT UNSIGNED NOT NULL,
  id_cliente          INT UNSIGNED NULL,
  subtotal            DECIMAL(10,2) NOT NULL DEFAULT 0,
  descuento           DECIMAL(10,2) NOT NULL DEFAULT 0,
  total               DECIMAL(10,2) NOT NULL DEFAULT 0,
  efectivo            DECIMAL(10,2) NOT NULL DEFAULT 0,
  cambio              DECIMAL(10,2) NOT NULL DEFAULT 0,
  id_recompensa       INT UNSIGNED NULL,
  puntos_ganados      INT UNSIGNED NOT NULL DEFAULT 0,
  puntos_canjeados    INT UNSIGNED NOT NULL DEFAULT 0,
  estado              ENUM('completada','cancelada') NOT NULL DEFAULT 'completada',
  fecha_cancelacion   DATETIME NULL,
  motivo_cancelacion  VARCHAR(150) NULL,
  id_usuario_cancela  INT UNSIGNED NULL,
  PRIMARY KEY (id_venta),
  UNIQUE KEY uq_ventas_folio (folio),
  UNIQUE KEY uq_ventas_pedido (id_pedido),
  KEY idx_ventas_fecha (fecha),
  CONSTRAINT fk_ventas_pedido FOREIGN KEY (id_pedido)
    REFERENCES pedidos (id_pedido) ON UPDATE CASCADE,
  CONSTRAINT fk_ventas_cajero FOREIGN KEY (id_cajero)
    REFERENCES usuarios (id_usuario) ON UPDATE CASCADE,
  CONSTRAINT fk_ventas_cliente FOREIGN KEY (id_cliente)
    REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_ventas_recompensa FOREIGN KEY (id_recompensa)
    REFERENCES recompensas (id_recompensa) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_ventas_cancela FOREIGN KEY (id_usuario_cancela)
    REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_ventas_importes CHECK (
    subtotal >= 0 AND descuento >= 0 AND descuento <= subtotal
    AND total = subtotal - descuento
    AND efectivo >= total AND cambio = efectivo - total),
  CONSTRAINT chk_ventas_cancelacion CHECK (estado <> 'cancelada' OR fecha_cancelacion IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE detalle_venta (
  id_detalle       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_venta         INT UNSIGNED NOT NULL,
  id_producto      INT UNSIGNED NOT NULL,
  cantidad         INT UNSIGNED NOT NULL,
  precio_unitario  DECIMAL(10,2) NOT NULL,
  costo_unitario   DECIMAL(10,2) NOT NULL DEFAULT 0,
  subtotal         DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (id_detalle),
  UNIQUE KEY uq_detalle_producto (id_venta, id_producto),
  CONSTRAINT fk_detalle_venta FOREIGN KEY (id_venta)
    REFERENCES ventas (id_venta) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_detalle_producto FOREIGN KEY (id_producto)
    REFERENCES productos (id_producto) ON UPDATE CASCADE,
  CONSTRAINT chk_detalle_cantidad CHECK (cantidad > 0 AND subtotal = cantidad * precio_unitario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE informacion_sitio (
  id_info              TINYINT UNSIGNED NOT NULL DEFAULT 1,
  nombre_negocio       VARCHAR(80)  NOT NULL,
  eslogan              VARCHAR(150) NULL,
  nosotros             TEXT NULL,
  mision               TEXT NULL,
  vision               TEXT NULL,
  horario              VARCHAR(150) NULL,
  direccion            VARCHAR(200) NULL,
  telefono             VARCHAR(20)  NULL,
  correo               VARCHAR(100) NULL,
  facebook             VARCHAR(150) NULL,
  instagram            VARCHAR(150) NULL,
  fecha_actualizacion  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  id_usuario           INT UNSIGNED NULL,
  PRIMARY KEY (id_info),
  CONSTRAINT fk_info_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_info_una_fila CHECK (id_info = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sesiones_app (
  id_sesion       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario      INT UNSIGNED NOT NULL,
  token_hash      CHAR(64)     NOT NULL,
  dispositivo     VARCHAR(100) NULL,
  fecha_creacion  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_expira    DATETIME NOT NULL,
  PRIMARY KEY (id_sesion),
  UNIQUE KEY uq_sesiones_token (token_hash),
  CONSTRAINT fk_sesiones_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuarios (id_usuario) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- REGISTROS DE PRUEBA
START TRANSACTION;
INSERT INTO usuarios (id_usuario, nombre, usuario, celular, password_hash, rol, puntos, estado, fecha_registro) VALUES
(1, 'Administrador General', 'admin', NULL, '$2y$10$H6581Op3RK5poJ13A/ZFCupLnameY2KS4JhD1qodOW6kX5OexMrNW', 'admin', 0, 'activo', TIMESTAMP(CURDATE() - INTERVAL 30 DAY, '10:00:00')),
(2, 'Laura Gómez Reyes', 'cajero1', '7891002030', '$2y$10$m1//tpyYp2kTOFEkgPdY9eY6UItPhH1EZFVLMUHcJifrhtNyuFEvG', 'cajero', 0, 'activo', TIMESTAMP(CURDATE() - INTERVAL 30 DAY, '10:00:00')),
(3, 'Carlos Ruiz Hernández', 'cajero2', '7891002031', '$2y$10$MPsXBs6y6kPsIzA55PYMXOCIclb4/cPIiRq4nQSnhPjNA0M/kQT0u', 'cajero', 0, 'activo', TIMESTAMP(CURDATE() - INTERVAL 30 DAY, '10:00:00')),
(4, 'Pedro Sánchez Cruz', 'cajero3', '7891002032', '$2y$10$Q5RFL0utzXix8em0bJ3MFOWNBdcecNbtZpuy7XiRdwQpDObg2g7Ma', 'cajero', 0, 'inactivo', TIMESTAMP(CURDATE() - INTERVAL 30 DAY, '10:00:00')),
(5, 'Juan Pérez', 'juan.perez', '5523456789', '$2y$10$gacvJuFE/xdCTeEpXvj0r.jqISgpj9ibO4vHgM55BktqTEx/cC6n.', 'cliente', 53, 'activo', TIMESTAMP(CURDATE() - INTERVAL 10 DAY, '10:00:00')),
(6, 'Ana Hernández Bautista', 'ana.hernandez', '7891234567', '$2y$10$sp3xsnHDY0AJgCmfiiNGnOlas1Bs1bIPA4yd4FnXPrLZoWsKDLC7K', 'cliente', 5, 'activo', TIMESTAMP(CURDATE() - INTERVAL 8 DAY, '10:00:00')),
(7, 'Luis Bautista Martínez', 'luis.bautista', '7892345678', '$2y$10$4lkmgoosoSPgTRXb7qZwRuS/UOfz6CmftUb03dPGZeA.oGjHcxoOW', 'cliente', 14, 'activo', TIMESTAMP(CURDATE() - INTERVAL 5 DAY, '10:00:00'));

INSERT INTO categorias (id_categoria, nombre, descripcion) VALUES
(1, 'Bebidas calientes', 'Café de grano, capuchinos y chocolate'),
(2, 'Bebidas frías', 'Cafés helados, jugos y frappés'),
(3, 'Comida', 'Sándwiches y ensaladas'),
(4, 'Panadería y postres', 'Galletas, pays y pan dulce'),
(5, 'Comida del día', 'Platillos preparados; el admin elige cuáles hay hoy');

INSERT INTO productos (id_producto, codigo, nombre, descripcion, id_categoria, precio_compra, precio_venta, stock, stock_reservado, stock_minimo, imagen, disponible_hoy, fecha_registro) VALUES
(1, 'BC-001', 'Café americano', 'Café de grano recién preparado, 12 oz', 1, 10.00, 25.00, 30, 1, 10, 'img/productos/cafe-americano.jpg', 1, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '06:30:00')),
(2, 'BC-002', 'Capuchino', 'Espresso con leche espumada, 12 oz', 1, 14.00, 35.00, 25, 0, 10, 'img/productos/capuchino.jpg', 1, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '06:30:00')),
(3, 'BF-001', 'Café helado', 'Café frío con leche y hielo, 16 oz', 2, 16.00, 40.00, 15, 1, 8, 'img/productos/cafe-helado.jpg', 1, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '06:30:00')),
(4, 'BF-002', 'Jugo de naranja', 'Jugo natural recién exprimido, 500 ml', 2, 12.00, 28.00, 3, 0, 5, 'img/productos/jugo-naranja.jpg', 1, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '06:30:00')),
(5, 'CO-001', 'Sándwich de pollo', 'Pan integral, pollo, lechuga, jitomate y aderezo', 3, 25.00, 55.00, 8, 1, 5, 'img/productos/sandwich-pollo.jpg', 1, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '06:30:00')),
(6, 'CO-002', 'Ensalada universitaria', 'Lechuga, pollo, manzana, nuez y aderezo de la casa', 3, 28.00, 60.00, 7, 1, 5, 'img/productos/ensalada-universitaria.jpg', 1, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '06:30:00')),
(7, 'PP-001', 'Galleta de chocolate', 'Galleta horneada con chispas de chocolate', 4, 7.00, 18.00, 6, 0, 10, 'img/productos/galleta-chocolate.jpg', 1, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '06:30:00')),
(8, 'PP-002', 'Pay de queso', 'Rebanada de pay de queso horneado', 4, 14.00, 35.00, 7, 0, 5, 'img/productos/pay-queso.jpg', 1, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '06:30:00')),
(9, 'PP-003', 'Muffin de arándano', 'Muffin esponjoso con arándanos', 4, 11.00, 28.00, 0, 0, 6, 'img/productos/muffin-arandano.jpg', 1, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '06:30:00')),
(10, 'CD-001', 'Enchiladas verdes', 'Tres enchiladas de pollo con arroz', 5, 30.00, 65.00, 10, 0, 3, 'img/productos/enchiladas-verdes.jpg', 1, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '06:30:00')),
(11, 'CD-002', 'Pollo en mole', 'Pieza de pollo con mole, arroz y tortillas', 5, 34.00, 70.00, 6, 0, 3, 'img/productos/pollo-mole.jpg', 1, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '06:30:00')),
(12, 'CD-003', 'Chiles rellenos', 'Dos chiles rellenos de queso con arroz', 5, 33.00, 70.00, 0, 0, 3, 'img/productos/chiles-rellenos.jpg', 0, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '06:30:00'));

INSERT INTO recompensas (id_recompensa, nombre, descripcion, puntos_requeridos, tipo, id_producto, valor_descuento, estado) VALUES
(1, 'Galleta gratis', 'Una galleta de chocolate sin costo', 30, 'producto', 7, NULL, 'activo'),
(2, 'Café americano gratis', 'Un café americano de 12 oz sin costo', 50, 'producto', 1, NULL, 'activo'),
(3, '$20 de descuento', 'Descuento de $20 en tu compra', 100, 'descuento', NULL, 20.00, 'activo'),
(4, 'Postre gratis', 'Una rebanada de pay de queso sin costo', 200, 'producto', 8, NULL, 'activo'),
(5, 'Comida del día gratis', 'Un platillo de la comida del día (promoción terminada)', 400, 'descuento', NULL, 65.00, 'inactivo');

INSERT INTO regla_puntos (id_regla, monto, puntos, fecha_inicio, fecha_fin, id_usuario) VALUES
(1, 10.00, 1, CURDATE() - INTERVAL 30 DAY, NULL, 1);

INSERT INTO informacion_sitio (id_info, nombre_negocio, eslogan, nosotros, mision, vision, horario, direccion, telefono, correo, facebook, instagram, id_usuario) VALUES
(1, 'Edu Café', 'El café que te acompaña en cada clase',
 'Somos la cafetería de la universidad. Preparamos café de grano, comida casera y postres para que estudiantes y maestros recarguen energía entre clases.',
 'Ofrecer a la comunidad universitaria alimentos y bebidas de calidad, a precios accesibles y con un servicio rápido y amable.',
 'Ser el punto de encuentro favorito de la universidad, reconocido por su sabor, su atención y por premiar la lealtad de sus clientes.',
 'Lunes a viernes de 7:00 a 18:00 h', 'Edificio de cafetería, UTHH, Huejutla de Reyes, Hgo.', '789 100 2030', 'contacto@educafe.mx',
 'facebook.com/educafe.uthh', '@educafe.uthh', 1);

INSERT INTO inventario_movimientos (id_producto, tipo, cantidad, stock_anterior, stock_nuevo, motivo, id_usuario, fecha) VALUES
(1, 'entrada', 34, 0, 34, 'Inventario inicial', 1, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '07:00:00')),
(2, 'entrada', 32, 0, 32, 'Inventario inicial', 1, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '07:00:00')),
(3, 'entrada', 17, 0, 17, 'Inventario inicial', 1, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '07:00:00')),
(4, 'entrada', 6, 0, 6, 'Inventario inicial', 1, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '07:00:00')),
(5, 'entrada', 9, 0, 9, 'Inventario inicial', 1, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '07:00:00')),
(6, 'entrada', 9, 0, 9, 'Inventario inicial', 1, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '07:00:00')),
(7, 'entrada', 11, 0, 11, 'Inventario inicial', 1, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '07:00:00')),
(8, 'entrada', 10, 0, 10, 'Inventario inicial', 1, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '07:00:00')),
(9, 'entrada', 3, 0, 3, 'Inventario inicial', 1, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '07:00:00')),
(10, 'entrada', 12, 0, 12, 'Porciones preparadas', 1, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '07:00:00')),
(11, 'entrada', 9, 0, 9, 'Porciones preparadas', 1, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '07:00:00')),
(9, 'salida', 2, 3, 1, 'Caducidad', 1, TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '07:05:00'));

INSERT INTO pedidos (id_pedido, folio, codigo_recogida, id_cliente, fecha, fecha_vence, total, notas, estado, id_cajero, fecha_listo, fecha_entregado, fecha_cancelacion, motivo_cancelacion) VALUES
(1, 'PED-0001', 'K4M-2QX', 5, TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '10:05:00'), TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '11:05:00'), 125.00, 'Poco hielo', 'entregado', 2, TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '10:15:00'), TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '10:22:00'), NULL, NULL),
(2, 'PED-0002', 'R7T-8HN', 6, TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '12:30:00'), TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '13:30:00'), 145.00, NULL, 'entregado', 3, TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '12:45:00'), TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '12:50:00'), NULL, NULL),
(3, 'PED-0003', 'B3W-5ZP', 7, TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '09:40:00'), TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '10:40:00'), 53.00, NULL, 'entregado', 2, TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '09:50:00'), TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '09:55:00'), NULL, NULL),
(4, 'PED-0004', 'M9C-4DU', 6, TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '11:00:00'), TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '12:00:00'), 55.00, NULL, 'cancelado', NULL, NULL, NULL, TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '12:00:00'), 'vencido'),
(5, 'PED-0005', 'H2Y-7RA', 7, NOW() - INTERVAL 30 MINUTE, NOW() + INTERVAL 30 MINUTE, 98.00, 'Para llevar', 'pendiente', NULL, NULL, NULL, NULL, NULL),
(6, 'PED-0006', 'P6F-3KV', 6, NOW() - INTERVAL 22 MINUTE, NOW() + INTERVAL 38 MINUTE, 85.00, NULL, 'listo', 3, NOW() - INTERVAL 10 MINUTE, NULL, NULL, NULL),
(7, 'PED-0007', 'A7K-93F', 5, NOW() - INTERVAL 6 MINUTE, NOW() + INTERVAL 54 MINUTE, 95.00, 'Sin cebolla en el sándwich', 'pendiente', NULL, NULL, NULL, NULL, NULL);

INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario, subtotal) VALUES
(1, 1, 1, 25.00, 25.00),
(1, 3, 1, 40.00, 40.00),
(1, 6, 1, 60.00, 60.00),
(2, 3, 1, 40.00, 40.00),
(2, 8, 1, 35.00, 35.00),
(2, 11, 1, 70.00, 70.00),
(3, 1, 1, 25.00, 25.00),
(3, 9, 1, 28.00, 28.00),
(4, 5, 1, 55.00, 55.00),
(5, 4, 1, 28.00, 28.00),
(5, 11, 1, 70.00, 70.00),
(6, 1, 1, 25.00, 25.00),
(6, 6, 1, 60.00, 60.00),
(7, 3, 1, 40.00, 40.00),
(7, 5, 1, 55.00, 55.00);

INSERT INTO ventas (id_venta, folio, fecha, id_pedido, id_cajero, id_cliente, subtotal, descuento, total, efectivo, cambio, id_recompensa, puntos_ganados, puntos_canjeados, estado, fecha_cancelacion, motivo_cancelacion, id_usuario_cancela) VALUES
(1, 'VTA-00001', TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '08:10:00'), NULL, 2, NULL, 43.00, 0.00, 43.00, 50.00, 7.00, NULL, 0, 0, 'completada', NULL, NULL, NULL),
(2, 'VTA-00002', TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '09:30:00'), NULL, 3, 5, 108.00, 0.00, 108.00, 150.00, 42.00, NULL, 10, 0, 'completada', NULL, NULL, NULL),
(3, 'VTA-00003', TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '10:22:00'), 1, 2, 5, 125.00, 0.00, 125.00, 150.00, 25.00, NULL, 12, 0, 'completada', NULL, NULL, NULL),
(4, 'VTA-00004', TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '12:50:00'), 2, 3, 6, 145.00, 0.00, 145.00, 150.00, 5.00, NULL, 14, 0, 'completada', NULL, NULL, NULL),
(5, 'VTA-00005', TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '13:15:00'), NULL, 2, 6, 93.00, 0.00, 93.00, 100.00, 7.00, NULL, 9, 0, 'completada', NULL, NULL, NULL),
(6, 'VTA-00006', TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '08:05:00'), NULL, 3, NULL, 70.00, 0.00, 70.00, 70.00, 0.00, NULL, 0, 0, 'completada', NULL, NULL, NULL),
(7, 'VTA-00007', TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '09:55:00'), 3, 2, 7, 53.00, 0.00, 53.00, 100.00, 47.00, NULL, 5, 0, 'completada', NULL, NULL, NULL),
(8, 'VTA-00008', TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '12:40:00'), NULL, 2, 5, 168.00, 0.00, 168.00, 200.00, 32.00, NULL, 16, 0, 'completada', NULL, NULL, NULL),
(9, 'VTA-00009', TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '13:20:00'), NULL, 3, NULL, 36.00, 0.00, 36.00, 50.00, 14.00, NULL, 0, 0, 'cancelada', TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '13:25:00'), 'Cobro duplicado por error', 1),
(10, 'VTA-00010', TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '16:00:00'), NULL, 3, 6, 95.00, 0.00, 95.00, 100.00, 5.00, NULL, 9, 0, 'completada', NULL, NULL, NULL),
(11, 'VTA-00011', TIMESTAMP(CURDATE(), '07:40:00'), NULL, 2, NULL, 43.00, 0.00, 43.00, 50.00, 7.00, NULL, 0, 0, 'completada', NULL, NULL, NULL),
(12, 'VTA-00012', TIMESTAMP(CURDATE(), '08:05:00'), NULL, 2, 6, 53.00, 18.00, 35.00, 50.00, 15.00, 1, 3, 30, 'completada', NULL, NULL, NULL),
(13, 'VTA-00013', TIMESTAMP(CURDATE(), '08:30:00'), NULL, 3, 5, 153.00, 0.00, 153.00, 200.00, 47.00, NULL, 15, 0, 'completada', NULL, NULL, NULL),
(14, 'VTA-00014', NOW() - INTERVAL 25 MINUTE, 5, 2, 7, 98.00, 0.00, 98.00, 100.00, 2.00, NULL, 9, 0, 'completada', NULL, NULL, NULL);

INSERT INTO detalle_venta (id_venta, id_producto, cantidad, precio_unitario, costo_unitario, subtotal) VALUES
(1, 1, 1, 25.00, 10.00, 25.00),
(1, 7, 1, 18.00, 7.00, 18.00),
(2, 2, 1, 35.00, 14.00, 35.00),
(2, 5, 1, 55.00, 25.00, 55.00),
(2, 7, 1, 18.00, 7.00, 18.00),
(3, 1, 1, 25.00, 10.00, 25.00),
(3, 3, 1, 40.00, 16.00, 40.00),
(3, 6, 1, 60.00, 28.00, 60.00),
(4, 3, 1, 40.00, 16.00, 40.00),
(4, 8, 1, 35.00, 14.00, 35.00),
(4, 11, 1, 70.00, 34.00, 70.00),
(5, 4, 1, 28.00, 12.00, 28.00),
(5, 10, 1, 65.00, 30.00, 65.00),
(6, 2, 2, 35.00, 14.00, 70.00),
(7, 1, 1, 25.00, 10.00, 25.00),
(7, 9, 1, 28.00, 11.00, 28.00),
(8, 2, 1, 35.00, 14.00, 35.00),
(8, 4, 1, 28.00, 12.00, 28.00),
(8, 8, 1, 35.00, 14.00, 35.00),
(8, 11, 1, 70.00, 34.00, 70.00),
(9, 7, 2, 18.00, 7.00, 36.00),
(10, 2, 1, 35.00, 14.00, 35.00),
(10, 6, 1, 60.00, 28.00, 60.00),
(11, 1, 1, 25.00, 10.00, 25.00),
(11, 7, 1, 18.00, 7.00, 18.00),
(12, 2, 1, 35.00, 14.00, 35.00),
(12, 7, 1, 18.00, 7.00, 18.00),
(13, 2, 1, 35.00, 14.00, 35.00),
(13, 7, 1, 18.00, 7.00, 18.00),
(13, 8, 1, 35.00, 14.00, 35.00),
(13, 10, 1, 65.00, 30.00, 65.00),
(14, 4, 1, 28.00, 12.00, 28.00),
(14, 11, 1, 70.00, 34.00, 70.00);
COMMIT;
