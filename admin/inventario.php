<?php
// admin/inventario.php — 14 · Inventario (10 productos por página) y registro de movimientos
session_start();
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/plantilla.php';

$u = requerir_rol('admin');
cancelarPedidosVencidos($conexion);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $idProducto = (int)($_POST['id_producto'] ?? 0);
    $tipo = $_POST['tipo'] ?? '';
    $cantidad = (string)($_POST['cantidad'] ?? '');
    $fecha = (string)($_POST['fecha'] ?? date('Y-m-d'));
    $motivo = trim((string)($_POST['motivo'] ?? ''));
    $errores = [];
    if (!in_array($tipo, ['entrada', 'salida', 'ajuste'], true)) $errores[] = 'Elige el tipo de movimiento.';
    if (!ctype_digit($cantidad) || ($tipo !== 'ajuste' && (int)$cantidad <= 0) || (int)$cantidad > 100000) $errores[] = 'Escribe una cantidad válida.';
    $f = DateTime::createFromFormat('!Y-m-d', $fecha);
    if (!$f || $fecha > date('Y-m-d')) $errores[] = 'La fecha no es válida (no puede ser futura).';
    if (mb_strlen($motivo) > 150) $errores[] = 'El motivo admite hasta 150 caracteres.';

    if (!$errores) {
        mysqli_begin_transaction($conexion);
        $stmt = mysqli_prepare($conexion, "SELECT nombre, stock, stock_reservado FROM productos WHERE id_producto = ? FOR UPDATE");
        mysqli_stmt_bind_param($stmt, "i", $idProducto);
        mysqli_stmt_execute($stmt);
        $p = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if (!$p) {
            $errores[] = 'Selecciona un producto.';
        } else {
            $anterior = (int)$p['stock'];
            $cant = (int)$cantidad;
            $nuevo = $tipo === 'entrada' ? $anterior + $cant : ($tipo === 'salida' ? $anterior - $cant : $cant);
            $movido = $tipo === 'ajuste' ? $nuevo - $anterior : $cant;
            if ($tipo === 'ajuste' && $movido === 0) $errores[] = 'El ajuste no cambia las existencias de ' . $p['nombre'] . '.';
            if ($nuevo < (int)$p['stock_reservado']) {
                $res = (int)$p['stock_reservado'];
                $errores[] = 'No puedes dejar ' . $p['nombre'] . ' con menos de ' . $res . ($res === 1 ? ' unidad: está apartada' : ' unidades: están apartadas') . ' por pedidos de la app.';
            }
            if (!$errores) {
                $stmt = mysqli_prepare($conexion, "UPDATE productos SET stock = ? WHERE id_producto = ?");
                mysqli_stmt_bind_param($stmt, "ii", $nuevo, $idProducto);
                mysqli_stmt_execute($stmt);

                $momento = $fecha === date('Y-m-d') ? date('Y-m-d H:i:s') : $fecha . ' 12:00:00';
                $motivoMov = $motivo !== '' ? $motivo : null;
                $sql = "INSERT INTO inventario_movimientos (id_producto, tipo, cantidad, stock_anterior, stock_nuevo, motivo, id_usuario, fecha)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conexion, $sql);
                mysqli_stmt_bind_param($stmt, "isiiisis", $idProducto, $tipo, $movido, $anterior, $nuevo, $motivoMov, $u['id_usuario'], $momento);
                mysqli_stmt_execute($stmt);
                mysqli_commit($conexion);
                aviso('success', 'Movimiento registrado: ' . $p['nombre'] . ' pasó de ' . $anterior . ' a ' . $nuevo . ' unidades.');
                regresar();
            }
        }
        mysqli_rollback($conexion);
    }
    aviso('danger', implode(' ', $errores));
    recordar_formulario(['id_producto' => $idProducto, 'tipo' => $tipo, 'cantidad' => $cantidad, 'fecha' => $fecha, 'motivo' => $motivo]);
    regresar();
}

$sql = "SELECT COUNT(*) AS total, SUM(stock - stock_reservado > 0) AS con, SUM(stock - stock_reservado <= 0) AS agotados,
               SUM(stock - stock_reservado BETWEEN 1 AND " . LIMITE_POCAS . ") AS pocas
          FROM productos WHERE estado = 'activo'";
$resultado = mysqli_query($conexion, $sql);
$st = mysqli_fetch_assoc($resultado);

$filtro = in_array($_GET['filtro'] ?? '', ['pocas', 'agotados'], true) ? $_GET['filtro'] : 'todos';
$q = trim((string)($_GET['q'] ?? ''));
$where = "p.estado = 'activo'";
$tipos = '';
$params = [];
if ($filtro === 'pocas') $where .= " AND p.stock - p.stock_reservado BETWEEN 1 AND " . LIMITE_POCAS;
if ($filtro === 'agotados') $where .= " AND p.stock - p.stock_reservado <= 0";
if ($q !== '') { $where .= " AND (p.nombre LIKE ? OR p.codigo LIKE ?)"; $tipos .= "ss"; $params[] = "%$q%"; $params[] = "%$q%"; }
$stmt = mysqli_prepare($conexion, "SELECT COUNT(*) AS total FROM productos p WHERE $where");
if ($params) mysqli_stmt_bind_param($stmt, $tipos, ...$params);
mysqli_stmt_execute($stmt);
$total = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];
[$pagina, $paginas, $offset] = paginar($total, POR_PAGINA_INVENTARIO);
$sql = "SELECT p.id_producto, p.codigo, p.nombre, p.precio_venta, p.stock, p.stock_reservado, c.nombre AS categoria
          FROM productos p JOIN categorias c ON c.id_categoria = p.id_categoria
         WHERE $where ORDER BY p.id_producto LIMIT " . POR_PAGINA_INVENTARIO . " OFFSET $offset";
$stmt = mysqli_prepare($conexion, $sql);
if ($params) mysqli_stmt_bind_param($stmt, $tipos, ...$params);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$productos = mysqli_fetch_all($resultado, MYSQLI_ASSOC);

$resultado = mysqli_query($conexion, "SELECT id_producto, codigo, nombre, stock FROM productos WHERE estado = 'activo' ORDER BY nombre");
$opciones = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
$sql = "SELECT m.fecha, m.tipo, m.cantidad, m.stock_anterior, m.stock_nuevo, m.motivo, p.nombre, u.nombre AS usuario
          FROM inventario_movimientos m JOIN productos p ON p.id_producto = m.id_producto
          LEFT JOIN usuarios u ON u.id_usuario = m.id_usuario
         ORDER BY m.fecha DESC, m.id_movimiento DESC LIMIT 6";
$resultado = mysqli_query($conexion, $sql);
$movimientos = mysqli_fetch_all($resultado, MYSQLI_ASSOC);

function enlace_filtro($f)
{
    $q = $_GET; $q['filtro'] = $f; unset($q['pagina']);
    return '?' . http_build_query($q);
}

encabezado(['titulo' => 'Inventario', 'menu' => 'admin', 'activo' => 'inventario']);
portada_interna('Control de inventario', 'Inventario', 'Consulta las existencias y registra entradas y salidas de producto.');
?>
<section class="seccion-corta bg-crema">
    <div class="container-ec">
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3"><div class="stat"><span class="icono-circulo"><i class="bi bi-box-seam"></i></span><div><div class="stat-titulo">Productos activos</div><div class="stat-valor"><?= (int)$st['total'] ?></div></div></div></div>
            <div class="col-6 col-lg-3"><div class="stat"><span class="icono-circulo verde"><i class="bi bi-check-circle"></i></span><div><div class="stat-titulo">Con existencias</div><div class="stat-valor"><?= (int)$st['con'] ?></div></div></div></div>
            <div class="col-6 col-lg-3"><div class="stat"><span class="icono-circulo amarillo"><i class="bi bi-exclamation-triangle"></i></span><div><div class="stat-titulo">Pocas existencias</div><div class="stat-valor"><?= (int)$st['pocas'] ?></div></div></div></div>
            <div class="col-6 col-lg-3"><div class="stat"><span class="icono-circulo gris"><i class="bi bi-x-circle"></i></span><div><div class="stat-titulo">Agotados</div><div class="stat-valor"><?= (int)$st['agotados'] ?></div></div></div></div>
        </div>

        <div class="tarjeta mb-4">
            <form class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3" method="get">
                <h2 class="titulo-3 mb-0">Lista de productos</h2>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <input type="hidden" name="filtro" value="<?= e($filtro) ?>">
                    <div class="buscador"><i class="bi bi-search"></i><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar producto…"></div>
                    <a class="filtro<?= $filtro === 'todos' ? ' active' : '' ?>" href="<?= e(enlace_filtro('todos')) ?>">Todos</a>
                    <a class="filtro<?= $filtro === 'pocas' ? ' active' : '' ?>" href="<?= e(enlace_filtro('pocas')) ?>">Pocas existencias</a>
                    <a class="filtro<?= $filtro === 'agotados' ? ' active' : '' ?>" href="<?= e(enlace_filtro('agotados')) ?>">Agotados</a>
                </div>
            </form>
            <div class="table-responsive">
                <table class="tabla-ec">
                    <thead><tr><th>ID</th><th>Producto</th><th>Categoría</th><th>Precio</th><th>Cantidad</th><th>Apartado por la app</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php foreach ($productos as $p): [$txt, $col] = estado_stock($p['stock'] - $p['stock_reservado']); ?>
                        <tr>
                            <td class="pequeno"><?= e($p['codigo']) ?></td>
                            <td class="fw-semi"><?= e($p['nombre']) ?></td>
                            <td><?= e($p['categoria']) ?></td>
                            <td><?= dinero($p['precio_venta']) ?></td>
                            <td><?= (int)$p['stock'] ?></td>
                            <td><?= $p['stock_reservado'] > 0 ? (int)$p['stock_reservado'] : '—' ?></td>
                            <td><?= etiqueta($txt, $col) ?></td>
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

        <div class="row g-4">
            <div class="col-lg-7">
                <form class="tarjeta h-100" method="post">
                    <?= csrf_campo() ?>
                    <h2 class="titulo-3 mb-1">Registrar movimiento</h2>
                    <p class="pequeno texto-sec">Registra las entradas (compras a proveedor), las salidas (merma o consumo interno) o un ajuste con el conteo real. Las ventas descuentan las existencias automáticamente.</p>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="id_producto">Producto</label>
                            <select class="form-select" id="id_producto" name="id_producto" required>
                                <option value="">Selecciona un producto</option>
                                <?php foreach ($opciones as $o): ?><option value="<?= $o['id_producto'] ?>" <?= valor_previo('id_producto') == $o['id_producto'] ? 'selected' : '' ?>><?= e($o['nombre']) ?> (<?= (int)$o['stock'] ?>)</option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3"><label class="form-label" for="tipo">Tipo de movimiento</label>
                            <select class="form-select" id="tipo" name="tipo">
                                <?php foreach (['entrada' => 'Entrada', 'salida' => 'Salida', 'ajuste' => 'Ajuste'] as $k => $t): ?><option value="<?= $k ?>" <?= valor_previo('tipo', 'entrada') === $k ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3"><label class="form-label" for="cantidad" id="etiquetaCantidad">Cantidad</label><input class="form-control" id="cantidad" name="cantidad" type="number" min="0" step="1" value="<?= e(valor_previo('cantidad')) ?>" placeholder="0" required></div>
                        <div class="col-md-4"><label class="form-label" for="fecha">Fecha</label><input class="form-control" id="fecha" name="fecha" type="date" max="<?= date('Y-m-d') ?>" value="<?= e(valor_previo('fecha', date('Y-m-d'))) ?>"></div>
                        <div class="col-md-8"><label class="form-label" for="motivo">Motivo</label><input class="form-control" id="motivo" name="motivo" maxlength="150" value="<?= e(valor_previo('motivo')) ?>" placeholder="Ej. Compra a proveedor"></div>
                    </div>
                    <p class="pequeno texto-sec mt-2 mb-3" id="ayudaTipo">Entrada: suma la cantidad a las existencias.</p>
                    <div class="text-end"><button class="btn btn-ec btn-cafe" type="submit">Registrar movimiento</button></div>
                </form>
            </div>
            <div class="col-lg-5">
                <div class="tarjeta h-100">
                    <h2 class="titulo-4 mb-3">Últimos movimientos</h2>
                    <?php foreach ($movimientos as $m): ?>
                        <div class="renglon">
                            <div>
                                <div class="fw-semi" style="color:var(--cafe)"><?= e($m['nombre']) ?></div>
                                <div class="pequeno texto-sec"><?= fecha_corta($m['fecha']) ?> <?= hora($m['fecha']) ?> · <?= e($m['motivo'] ?: ucfirst($m['tipo'])) ?></div>
                            </div>
                            <div class="text-end">
                                <?= etiqueta(ucfirst($m['tipo']), $m['tipo'] === 'entrada' ? 'verde' : ($m['tipo'] === 'salida' ? 'naranja' : 'gris')) ?>
                                <div class="pequeno texto-sec"><?= (int)$m['stock_anterior'] ?> → <?= (int)$m['stock_nuevo'] ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$movimientos): ?><p class="texto-sec mb-0">Aún no hay movimientos.</p><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<script>
(() => {
    const tipo = document.getElementById('tipo');
    const ayudas = {
        entrada: ['Cantidad', 'Entrada: suma la cantidad a las existencias.'],
        salida: ['Cantidad', 'Salida: resta la cantidad (merma, caducidad o consumo interno).'],
        ajuste: ['Existencia real', 'Ajuste: escribe cuántas unidades contaste; el sistema calcula la diferencia.'],
    };
    const pintar = () => {
        document.getElementById('etiquetaCantidad').textContent = ayudas[tipo.value][0];
        document.getElementById('ayudaTipo').textContent = ayudas[tipo.value][1];
    };
    tipo.addEventListener('change', pintar); pintar();
})();
</script>
<?php pie(['menu' => 'admin']); ?>
