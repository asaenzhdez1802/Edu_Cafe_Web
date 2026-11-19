# Edu Café · Página web

Sistema web de la cafetería Edu Café: menú público, cuenta de cliente con puntos y recompensas, punto de venta del cajero y panel del administrador. Comparte la base de datos con la app móvil.

**Equipo 4 · 4° "A" · UTHH**

- Angel Manuel Saenz Hernandez
- Itzel Guadalupe Sanchez Bautista
- Oscar Cortes Solares
- Angel Angelino Gonzalez

**Tecnologías:** PHP 8, MySQL, Bootstrap 5, XAMPP (local) y Hostinger (hosting).

## Cómo correrlo en tu computadora

1. Clona este repositorio dentro de `C:\xampp\htdocs`.
2. En el XAMPP Control Panel prende **Apache** y **MySQL**.
3. Entra a `http://localhost/phpmyadmin` → **Importar** → `database/EduCafe_creacion_y_registros.sql`. Se crea la base `educafe`.
4. Abre `http://localhost/Edu_Cafe_Web/`.

En `config/conexion.php` van los datos de XAMPP (`localhost`, `root`, sin contraseña, base `educafe`). Los datos de Hostinger **no se suben** al repositorio.

## Usuarios de prueba

| Usuario | Contraseña | Rol |
|---|---|---|
| admin | Admin123 | Administrador |
| cajero1 | Cajero123 | Cajero |
| juan.perez | Cliente123 | Cliente |

## Carpetas

| Carpeta | Qué va |
|---|---|
| `config/` | Conexión a la base de datos |
| `includes/` | Partes que se repiten: header (Bootstrap), menús por rol, footer y sesión |
| `css/`, `js/` | Estilos con los colores del Figma y scripts |
| `img/productos/` | Fotos de los productos |
| `admin/` | Pantallas del administrador |
| `cajero/` | Pantallas del cajero |
| `cliente/` | Pantallas del cliente |
| `database/` | Script de la base de datos |

Las páginas públicas (`index.php`, `menu.php`, `login.php`, `registro.php`) van en la raíz.

## Reparto

| Integrante | Le toca |
|---|---|
| Angel Manuel | Repositorio, base de datos, Iniciar sesión, Crear cuenta, Usuarios, Dashboard del administrador |
| Itzel | Plantillas compartidas, Categorías, Productos, Comida del día, Menú |
| Oscar | Inventario, Punto de venta, Dashboard del cajero, Mis ventas, subir la página al hosting |
| Angelino | Información del sitio, Inicio, Recompensas, Clientes, Mi panel, Mis compras |

## Cómo trabajamos

- Antes de empezar: **Pull** (bajar lo último).
- Al terminar algo que funciona: **Commit** con un mensaje claro (ej. `Productos: alta y edición`) y **Push**.
- Cada quien modifica solo sus archivos; si hay que tocar una plantilla compartida, avisar al equipo.
