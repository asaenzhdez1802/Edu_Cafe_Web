<?php
// admin/productos.php — 11 · Productos (20 registros por página)
session_start();
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/plantilla.php';

$u = requerir_rol('admin');
$idComida = id_categoria_comida($conexion);

/* ---------- Guardar (nuevo o editar) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'estado') {
        $id = (int)($_POST['id_producto'] ?? 0);
        $stmt = mysqli_prepare($conexion, "UPDATE productos SET estado = IF(estado = 'activo', 'inactivo', 'activo') WHERE id_producto = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        aviso('success', 'Se cambió el estado del producto.');
        regresar();
    }

    if ($accion === 'guardar') {
        $id = (int)($_POST['id_producto'] ?? 0);
        $d = [
            'codigo' => strtoupper(trim((string)($_POST['codigo'] ?? ''))),
            'nombre' => trim((string)($_POST['nombre'] ?? '')),
            'descripcion' => trim((string)($_POST['descripcion'] ?? '')),
            'id_categoria' => (int)($_POST['id_categoria'] ?? 0),
            'precio_compra' => str_replace(',', '', (string)($_POST['precio_compra'] ?? '0')),
            'precio_venta' => str_replace(',', '', (string)($_POST['precio_venta'] ?? '')),
            'stock_minimo' => (string)($_POST['stock_minimo'] ?? '5'),
            'estado' => ($_POST['estado'] ?? 'activo') === 'inactivo' ? 'inactivo' : 'activo',
            'visible' => isset($_POST['visible']) ? 1 : 0,
        ];
        $errores = [];
        if (!preg_match('/^[A-Z0-9-]{2,20}$/', $d['codigo'])) $errores[] = 'El código debe tener de 2 a 20 letras, números o guiones.';
        if ($d['nombre'] === '' || mb_strlen($d['nombre']) > 100) $errores[] = 'Escribe el nombre del producto.';
        if (mb_strlen($d['descripcion']) > 255) $errores[] = 'La descripción admite hasta 255 caracteres.';
        if (!is_numeric($d['precio_compra']) || $d['precio_compra'] < 0) $errores[] = 'El precio de compra no es válido.';
        if (!is_numeric($d['precio_venta']) || $d['precio_venta'] <= 0) $errores[] = 'El precio de venta debe ser mayor a 0.';
        if (!ctype_digit($d['stock_minimo'])) $errores[] = 'Las existencias mínimas deben ser un número entero.';
        $stmt = mysqli_prepare($conexion, "SELECT id_categoria FROM categorias WHERE id_categoria = ?");
        mysqli_stmt_bind_param($stmt, "i", $d['id_categoria']);
        mysqli_stmt_execute($stmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) === 0) $errores[] = 'Selecciona una categoría.';
        $stmt = mysqli_prepare($conexion, "SELECT id_producto FROM productos WHERE codigo = ? AND id_producto <> ?");
        mysqli_stmt_bind_param($stmt, "si", $d['codigo'], $id);
        mysqli_stmt_execute($stmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) $errores[] = 'Ya existe otro producto con ese código.';

        $imagen = null;
        if (!$errores) {
            try {
                $imagen = subir_imagen($_FILES['imagen'] ?? [], __DIR__ . '/../' . CARPETA_API . '/img/productos', 'img/productos', $d['nombre']);
            } catch (Exception $e) {
                $errores[] = $e->getMessage();
            }
        }

        if ($errores) {
            aviso('danger', implode(' ', $errores));
            recordar_formulario($d);
            redirigir('admin/productos.php' . ($id ? '?editar=' . $id : '?nuevo=1'));
        }

        $descripcion = $d['descripcion'] !== '' ? $d['descripcion'] : null;
        $stockMinimo = (int)$d['stock_minimo'];
        if ($id) {
            $sql = "UPDATE productos SET codigo = ?, nombre = ?, descripcion = ?, id_categoria = ?, precio_compra = ?, precio_venta = ?,
                           stock_minimo = ?, estado = ?, visible = ?" . ($imagen ? ", imagen = ?" : "") . " WHERE id_producto = ?";
            $tipos = "sssiddisi";
            $params = [$d['codigo'], $d['nombre'], $descripcion, $d['id_categoria'], $d['precio_compra'], $d['precio_venta'],
                       $stockMinimo, $d['estado'], $d['visible']];
            if ($imagen) { $tipos .= "s"; $params[] = $imagen; }
            $tipos .= "i";
            $params[] = $id;
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param($stmt, $tipos, ...$params);
            mysqli_stmt_execute($stmt);
            aviso('success', 'Se guardaron los cambios de ' . $d['nombre'] . '.');
        } else {
            // Los productos nuevos empiezan con 0 existencias; se surten desde Inventario.
            // Un platillo nuevo de Comida del día empieza apagado hasta que el admin lo active.
            $disponibleHoy = $d['id_categoria'] === $idComida ? 0 : 1;
            $fecha = date('Y-m-d H:i:s');
            $sql = "INSERT INTO productos (codigo, nombre, descripcion, id_categoria, precio_compra, precio_venta, stock,
                                           stock_reservado, stock_minimo, imagen, visible, disponible_hoy, estado, fecha_registro)
                    VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param($stmt, "sssiddisiiss", $d['codigo'], $d['nombre'], $descripcion, $d['id_categoria'],
                                   $d['precio_compra'], $d['precio_venta'], $stockMinimo, $imagen, $d['visible'],
                                   $disponibleHoy, $d['estado'], $fecha);
            mysqli_stmt_execute($stmt);
            aviso('success', $d['nombre'] . ' se agregó con 0 existencias. Regístralas en Inventario con una entrada.');
        }
        redirigir('admin/productos.php');
    }
}

/* ---------- Estadísticas ---------- */
$sql = "SELECT COUNT(*) AS total, SUM(estado = 'activo') AS activos,
               SUM(estado = 'activo' AND stock - stock_reservado BETWEEN 1 AND " . LIMITE_POCAS . ") AS pocas,
               SUM(estado = 'activo' AND stock - stock_reservado <= 0) AS agotados
          FROM productos";
$resultado = mysqli_query($conexion, $sql);
$st = mysqli_fetch_assoc($resultado);

/* ---------- Lista ---------- */
$q = trim((string)($_GET['q'] ?? ''));
$cat = (int)($_GET['categoria'] ?? 0);
$where = '1';
$tipos = '';
$params = [];
if ($q !== '') { $where .= " AND (p.nombre LIKE ? OR p.codigo LIKE ?)"; $tipos .= "ss"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($cat) { $where .= " AND p.id_categoria = ?"; $tipos .= "i"; $params[] = $cat; }
$stmt = mysqli_prepare($conexion, "SELECT COUNT(*) AS total FROM productos p WHERE $where");
if ($params) mysqli_stmt_bind_param($stmt, $tipos, ...$params);
mysqli_stmt_execute($stmt);
$total = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];
[$pagina, $paginas, $offset] = paginar($total, POR_PAGINA_PRODUCTOS);
$sql = "SELECT p.*, c.nombre AS categoria FROM productos p JOIN categorias c ON c.id_categoria = p.id_categoria
         WHERE $where ORDER BY p.codigo LIMIT " . POR_PAGINA_PRODUCTOS . " OFFSET $offset";
$stmt = mysqli_prepare($conexion, $sql);
if ($params) mysqli_stmt_bind_param($stmt, $tipos, ...$params);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$productos = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
$resultado = mysqli_query($conexion, "SELECT id_categoria, nombre, estado FROM categorias ORDER BY nombre");
$categorias = mysqli_fetch_all($resultado, MYSQLI_ASSOC);

/* ---------- Formulario ---------- */
$editar = null;
if (!empty($_GET['editar'])) {
    $idEditar = (int)$_GET['editar'];
    $stmt = mysqli_prepare($conexion, "SELECT * FROM productos WHERE id_producto = ?");
    mysqli_stmt_bind_param($stmt, "i", $idEditar);
    mysqli_stmt_execute($stmt);
    $editar = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
}
$f = $editar ?: ['id_producto' => 0, 'codigo' => '', 'nombre' => '', 'descripcion' => '', 'id_categoria' => 0, 'precio_compra' => '',
                 'precio_venta' => '', 'stock_minimo' => 5, 'estado' => 'activo', 'visible' => 1, 'imagen' => null];
foreach (['codigo', 'nombre', 'descripcion', 'id_categoria', 'precio_compra', 'precio_venta', 'stock_minimo', 'estado', 'visible'] as $c) {
    $f[$c] = valor_previo($c, $f[$c]);
}

encabezado(['titulo' => 'Productos', 'menu' => 'admin', 'activo' => 'catalogo']);
portada_interna('Catálogo', 'Productos', 'Agrega, edita y desactiva los productos que se venden y se muestran en el menú público.',
    '<a class="btn btn-ec btn-naranja" href="' . e(url('admin/productos.php?nuevo=1')) . '#formulario"><i class="bi bi-plus-lg"></i> Nuevo producto</a>');
?>
<section class="seccion-corta bg-crema">
    <div class="container-ec">
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3"><div class="stat"><span class="icono-circulo"><i class="bi bi-box-seam"></i></span><div><div class="stat-titulo">Total de productos</div><div class="stat-valor"><?= (int)$st['total'] ?></div></div></div></div>
            <div class="col-6 col-lg-3"><div class="stat"><span class="icono-circulo verde"><i class="bi bi-check-circle"></i></span><div><div class="stat-titulo">Activos</div><div class="stat-valor"><?= (int)$st['activos'] ?></div></div></div></div>
            <div class="col-6 col-lg-3"><div class="stat"><span class="icono-circulo amarillo"><i class="bi bi-exclamation-triangle"></i></span><div><div class="stat-titulo">Pocas existencias</div><div class="stat-valor"><?= (int)$st['pocas'] ?></div></div></div></div>
            <div class="col-6 col-lg-3"><div class="stat"><span class="icono-circulo gris"><i class="bi bi-x-circle"></i></span><div><div class="stat-titulo">Agotados</div><div class="stat-valor"><?= (int)$st['agotados'] ?></div></div></div></div>
        </div>

        <div class="row g-4 align-items-start">
            <div class="col-lg-8">
                <div class="tarjeta">
                    <form class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3" method="get">
                        <h2 class="titulo-3 mb-0">Lista de productos</h2>
                        <div class="d-flex flex-wrap gap-2">
                            <div class="buscador"><i class="bi bi-search"></i><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar producto…"></div>
                            <select class="form-select" style="width:auto;border-radius:40px" name="categoria" onchange="this.form.submit()">
                                <option value="0">Todas las categorías</option>
                                <?php foreach ($categorias as $c): ?><option value="<?= $c['id_categoria'] ?>" <?= $cat == $c['id_categoria'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="tabla-ec tabla-compacta">
                            <thead><tr><th>Código</th><th>Producto</th><th>Categoría</th><th>Precio</th><th>Existencias</th><th>Estado</th><th>Acciones</th></tr></thead>
                            <tbody>
                            <?php foreach ($productos as $p):
                                $disp = (int)$p['stock'] - (int)$p['stock_reservado'];
                                [$txt, $col] = $p['estado'] === 'inactivo' ? ['Inactivo', 'gris'] : estado_stock($disp);
                                $q2 = $_GET; $q2['editar'] = $p['id_producto']; unset($q2['nuevo']);
                            ?>
                                <tr class="<?= $editar && $editar['id_producto'] == $p['id_producto'] ? 'seleccionada' : '' ?>">
                                    <td class="text-nowrap-celda"><?= e($p['codigo']) ?></td>
                                    <td><div class="d-flex align-items-center gap-2"><img class="mini-foto" src="<?= e(img_producto($p['imagen'])) ?>" alt=""><span class="fw-semi"><?= e($p['nombre']) ?></span></div></td>
                                    <td><?= e($p['categoria']) ?></td>
                                    <td><?= dinero($p['precio_venta']) ?></td>
                                    <td><?= (int)$p['stock'] ?><?php if ($p['stock_reservado'] > 0): ?><div class="pequeno texto-sec"><?= (int)$p['stock_reservado'] ?> apartado</div><?php endif; ?></td>
                                    <td class="text-nowrap-celda"><?= etiqueta($txt, $col) ?></td>
                                    <td class="text-nowrap">
                                        <a class="btn-icono" href="?<?= e(http_build_query($q2)) ?>#formulario" title="Editar"><i class="bi bi-pencil"></i></a>
                                        <form method="post" class="d-inline"><?= csrf_campo() ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="id_producto" value="<?= $p['id_producto'] ?>">
                                            <button class="btn-icono <?= $p['estado'] === 'activo' ? 'rojo' : 'verde' ?>" title="<?= $p['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>" data-confirmar="<?= $p['estado'] === 'activo' ? '¿Desactivar ' . e($p['nombre']) . '? Ya no se podrá vender ni se verá en el menú.' : '¿Activar ' . e($p['nombre']) . '?' ?>"><i class="bi <?= $p['estado'] === 'activo' ? 'bi-slash-circle' : 'bi-check-lg' ?>"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$productos): ?><tr><td colspan="7" class="tabla-vacia">No hay productos con ese filtro.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                        <span class="pequeno texto-sec"><?= e(texto_mostrando($offset, count($productos), $total, 'productos')) ?></span>
                        <?= enlaces_paginacion($pagina, $paginas) ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4" id="formulario">
                <form class="tarjeta" method="post" enctype="multipart/form-data">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id_producto" value="<?= (int)$f['id_producto'] ?>">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <span class="icono-circulo chico"><i class="bi bi-box-seam"></i></span>
                        <h2 class="titulo-3 mb-0"><?= $editar ? 'Editar producto' : 'Información del producto' ?></h2>
                    </div>
                    <p class="pequeno texto-sec"><?= $editar ? 'Estás editando ' . e($editar['nombre']) . '.' : 'Llena los datos para agregar un producto nuevo.' ?></p>
                    <div class="mb-3"><label class="form-label" for="codigo">Código</label><input class="form-control text-uppercase" id="codigo" name="codigo" maxlength="20" value="<?= e($f['codigo']) ?>" placeholder="Ej. BC-003" required></div>
                    <div class="mb-3"><label class="form-label" for="nombre">Nombre del producto</label><input class="form-control" id="nombre" name="nombre" maxlength="100" value="<?= e($f['nombre']) ?>" placeholder="Ej. Café americano" required></div>
                    <div class="mb-3"><label class="form-label" for="descripcion">Descripción</label><textarea class="form-control" id="descripcion" name="descripcion" rows="2" maxlength="255" placeholder="Ej. Café de grano recién preparado, 12 oz"><?= e($f['descripcion']) ?></textarea></div>
                    <div class="mb-3">
                        <label class="form-label">Imagen del producto</label>
                        <label class="caja-subir">
                            <img id="vistaProducto" src="<?= e(img_producto($f['imagen'])) ?>" alt="">
                            <span class="pequeno texto-sec">Arrastra una foto aquí o elígela desde tu equipo.<br><span class="btn btn-ec btn-contorno btn-sm mt-2"><i class="bi bi-upload"></i> Subir imagen</span><br>JPG, PNG o WEBP · máximo 2 MB</span>
                            <input type="file" name="imagen" accept="image/jpeg,image/png,image/webp" class="d-none" data-vista-previa="#vistaProducto">
                        </label>
                        <div class="form-text">La misma foto se ve en la app móvil.</div>
                    </div>
                    <div class="mb-3"><label class="form-label" for="id_categoria">Categoría</label>
                        <select class="form-select" id="id_categoria" name="id_categoria" required>
                            <option value="">Selecciona una categoría</option>
                            <?php foreach ($categorias as $c): if ($c['estado'] !== 'activo' && $c['id_categoria'] != $f['id_categoria']) continue; ?>
                                <option value="<?= $c['id_categoria'] ?>" <?= $f['id_categoria'] == $c['id_categoria'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6"><label class="form-label" for="precio_compra">Precio de compra</label><input class="form-control" id="precio_compra" name="precio_compra" type="number" min="0" step="0.01" value="<?= e($f['precio_compra']) ?>" placeholder="$0.00"></div>
                        <div class="col-6"><label class="form-label" for="precio_venta">Precio de venta</label><input class="form-control" id="precio_venta" name="precio_venta" type="number" min="0.01" step="0.01" value="<?= e($f['precio_venta']) ?>" placeholder="$0.00" required></div>
                    </div>
                    <div class="mb-1"><label class="form-label" for="stock_minimo">Existencias mínimas</label><input class="form-control" id="stock_minimo" name="stock_minimo" type="number" min="0" step="1" value="<?= e($f['stock_minimo']) ?>"></div>
                    <p class="pequeno texto-sec mb-3"><?= $editar ? 'Existencias actuales: ' . (int)$editar['stock'] . '. Para cambiarlas registra un movimiento en Inventario.' : 'Los productos nuevos empiezan con 0 existencias. Regístralas en Inventario con una entrada.' ?></p>
                    <div class="mb-3"><label class="form-label" for="estado">Estado</label>
                        <select class="form-select" id="estado" name="estado"><option value="activo" <?= $f['estado'] === 'activo' ? 'selected' : '' ?>>Activo</option><option value="inactivo" <?= $f['estado'] === 'inactivo' ? 'selected' : '' ?>>Inactivo</option></select>
                    </div>
                    <div class="interruptor mb-4">
                        <div><div class="fw-semi" style="color:var(--cafe)">Visible en el menú público</div><div class="pequeno texto-sec">Se mostrará en el inicio y en el catálogo.</div></div>
                        <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="visible" value="1" <?= $f['visible'] ? 'checked' : '' ?> aria-label="Visible en el menú público"></div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-ec btn-cafe flex-grow-1"><?= $editar ? 'Editar producto' : 'Guardar producto' ?></button>
                        <a class="btn btn-ec btn-contorno" href="<?= e(url('admin/productos.php')) ?>">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
        <p class="nota mt-4 mb-0"><i class="bi bi-info-circle"></i> Los productos marcados como visibles aparecen automáticamente en el menú público y en la app.</p>
    </div>
</section>
<?php pie(['menu' => 'admin']); ?>
