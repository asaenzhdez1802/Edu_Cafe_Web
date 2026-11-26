<?php
// admin/index.php — 10 · Resumen del administrador (solo informativo, tablas de 5 registros)
session_start();
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/plantilla.php';

$u = requerir_rol('admin');
cancelarPedidosVencidos($conexion);

function ventas_periodo($conexion, $periodo)
{
    [$desde, $hasta] = rango($periodo);
    $sql = "SELECT COUNT(*) AS num, COALESCE(SUM(total), 0) AS total FROM ventas
             WHERE estado = 'completada' AND fecha >= ? AND fecha < ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $desde, $hasta);
    mysqli_stmt_execute($stmt);
    $v = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    $sql = "SELECT COALESCE(SUM(d.cantidad * d.costo_unitario), 0) AS costo FROM detalle_venta d
              JOIN ventas v ON v.id_venta = d.id_venta
             WHERE v.estado = 'completada' AND v.fecha >= ? AND v.fecha < ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $desde, $hasta);
    mysqli_stmt_execute($stmt);
    $costo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['costo'];
    $v['ganancia'] = $v['total'] - (float)$costo; // cobrado menos costo de lo vendido
    return $v;
}
$hoy = ventas_periodo($conexion, 'hoy');
$semana = ventas_periodo($conexion, 'semana');
$mes = ventas_periodo($conexion, 'mes');

// Pocas existencias (1 a 5) y agotados
$sql = "SELECT p.nombre, c.nombre AS categoria, GREATEST(p.stock - p.stock_reservado, 0) AS disponibles
          FROM productos p JOIN categorias c ON c.id_categoria = p.id_categoria
         WHERE p.estado = 'activo' AND p.stock - p.stock_reservado <= " . LIMITE_POCAS . "
         ORDER BY disponibles, p.nombre LIMIT 5";
$resultado = mysqli_query($conexion, $sql);
$pocas = mysqli_fetch_all($resultado, MYSQLI_ASSOC);

// Más vendidos del periodo elegido
$periodo = in_array($_GET['periodo'] ?? '', ['hoy', 'semana', 'mes'], true) ? $_GET['periodo'] : 'semana';
[$desde, $hasta] = rango($periodo);
$sql = "SELECT p.nombre, SUM(d.cantidad) AS unidades, SUM(d.subtotal) AS importe
          FROM detalle_venta d
          JOIN ventas v ON v.id_venta = d.id_venta
          JOIN productos p ON p.id_producto = d.id_producto
         WHERE v.estado = 'completada' AND v.fecha >= ? AND v.fecha < ?
         GROUP BY d.id_producto, p.nombre ORDER BY unidades DESC, importe DESC LIMIT 5";
$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "ss", $desde, $hasta);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$masVendidos = mysqli_fetch_all($resultado, MYSQLI_ASSOC);

// Ventas del día
[$hd, $hh] = rango('hoy');
$sql = "SELECT v.folio, v.total, v.puntos_ganados, v.fecha, c.nombre AS cliente, k.nombre AS cajero
          FROM ventas v
          LEFT JOIN usuarios c ON c.id_usuario = v.id_cliente
          JOIN usuarios k ON k.id_usuario = v.id_cajero
         WHERE v.estado = 'completada' AND v.fecha >= ? AND v.fecha < ?
         ORDER BY v.fecha DESC LIMIT 5";
$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "ss", $hd, $hh);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$ventasDia = mysqli_fetch_all($resultado, MYSQLI_ASSOC);

encabezado(['titulo' => 'Panel de administración', 'menu' => 'admin', 'activo' => 'resumen']);
portada_interna('Administración ' . info_sitio($conexion)['nombre_negocio'], 'Panel de administración',
    'Consulta las ventas del día, las ganancias, el inventario y los productos más vendidos.', '', 'assets/img/cafeteria.jpg');
?>
<section class="seccion-corta bg-crema">
    <div class="container-ec">
        <div class="encabezado-seccion mb-4">
            <span class="chip chip-durazno">Control de ventas</span>
            <h2 class="titulo-1 mt-2">Ventas y ganancias</h2>
            <p class="texto-sec mb-0">Resultados de la cafetería por día, semana y mes.</p>
        </div>
        <div class="row g-3">
            <div class="col-6 col-lg-3"><div class="stat"><span class="icono-circulo verde"><i class="bi bi-cash-coin"></i></span><div><div class="stat-titulo">Vendido hoy</div><div class="stat-valor"><?= dinero($hoy['total']) ?></div></div></div></div>
            <div class="col-6 col-lg-3"><div class="stat"><span class="icono-circulo"><i class="bi bi-calendar-week"></i></span><div><div class="stat-titulo">Ventas de la semana</div><div class="stat-valor"><?= dinero($semana['total']) ?></div></div></div></div>
            <div class="col-6 col-lg-3"><div class="stat"><span class="icono-circulo"><i class="bi bi-calendar3"></i></span><div><div class="stat-titulo">Ventas del mes</div><div class="stat-valor"><?= dinero($mes['total']) ?></div></div></div></div>
            <div class="col-6 col-lg-3"><div class="stat"><span class="icono-circulo amarillo"><i class="bi bi-receipt"></i></span><div><div class="stat-titulo">Ventas de hoy</div><div class="stat-valor"><?= (int)$hoy['num'] ?></div></div></div></div>
        </div>

        <h3 class="titulo-3 mt-5 mb-3"><i class="bi bi-graph-up-arrow text-caramelo"></i> Ganancias brutas</h3>
        <div class="row g-3">
            <div class="col-md-4"><div class="stat stat-centro"><span class="icono-circulo verde"><i class="bi bi-sun"></i></span><div class="fw-semi">Ganancia del día</div><div class="stat-titulo">Ventas cobradas menos costo de lo vendido.</div><div class="stat-valor"><?= dinero($hoy['ganancia']) ?></div></div></div>
            <div class="col-md-4"><div class="stat stat-centro"><span class="icono-circulo"><i class="bi bi-calendar-week"></i></span><div class="fw-semi">Ganancia semanal</div><div class="stat-titulo">Ganancia bruta de esta semana.</div><div class="stat-valor"><?= dinero($semana['ganancia']) ?></div></div></div>
            <div class="col-md-4"><div class="stat stat-centro"><span class="icono-circulo"><i class="bi bi-calendar3"></i></span><div class="fw-semi">Ganancia mensual</div><div class="stat-titulo">Ganancia bruta de este mes.</div><div class="stat-valor"><?= dinero($mes['ganancia']) ?></div></div></div>
        </div>
        <p class="nota mt-3"><i class="bi bi-info-circle"></i> La ganancia bruta se calcula con el precio de venta y el costo de cada producto. No incluye gastos generales.</p>
    </div>
</section>

<section class="seccion-corta bg-crema-2">
    <div class="container-ec">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="tarjeta h-100">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                        <div><h2 class="titulo-3 mb-1"><i class="bi bi-exclamation-triangle text-caramelo"></i> Productos con pocas existencias</h2><p class="pequeno texto-sec mb-0">Productos que necesitan reabastecimiento.</p></div>
                        <a class="btn btn-ec btn-naranja btn-sm" href="<?= e(url('admin/inventario.php?filtro=pocas')) ?>">Ver inventario</a>
                    </div>
                    <p class="pequeno texto-sec">Pocas existencias = de 1 a <?= LIMITE_POCAS ?> unidades. Con 0 el producto aparece como agotado.</p>
                    <div class="table-responsive">
                        <table class="tabla-ec">
                            <thead><tr><th>Producto</th><th>Categoría</th><th>Existencias</th><th>Estado</th></tr></thead>
                            <tbody>
                            <?php foreach ($pocas as $p): [$txt, $col] = estado_stock($p['disponibles']); ?>
                                <tr><td class="fw-semi"><?= e($p['nombre']) ?></td><td><?= e($p['categoria']) ?></td><td><?= (int)$p['disponibles'] ?></td><td><?= etiqueta($txt, $col) ?></td></tr>
                            <?php endforeach; ?>
                            <?php if (!$pocas): ?><tr><td colspan="4" class="tabla-vacia">Todo el inventario está bien surtido.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="tarjeta h-100">
                    <h2 class="titulo-3 mb-1"><i class="bi bi-trophy text-caramelo"></i> Productos más vendidos</h2>
                    <p class="pequeno texto-sec">Los favoritos de los clientes y sus unidades vendidas.</p>
                    <div class="filtros justify-content-start mb-3">
                        <?php foreach (['hoy' => 'Hoy', 'semana' => 'Esta semana', 'mes' => 'Este mes'] as $k => $t): ?>
                            <a class="filtro<?= $periodo === $k ? ' active' : '' ?>" href="?periodo=<?= $k ?>#vendidos"><?= $t ?></a>
                        <?php endforeach; ?>
                    </div>
                    <div class="table-responsive" id="vendidos">
                        <table class="tabla-ec">
                            <thead><tr><th>Posición</th><th>Producto</th><th>Unidades</th><th>Total vendido</th></tr></thead>
                            <tbody>
                            <?php foreach ($masVendidos as $i => $p): ?>
                                <tr><td><span class="etiqueta <?= $i === 0 ? 'etiqueta-cafe' : 'etiqueta-naranja' ?>"><?= $i + 1 ?></span></td><td class="fw-semi"><?= e($p['nombre']) ?></td><td><?= (int)$p['unidades'] ?></td><td><?= dinero($p['importe']) ?></td></tr>
                            <?php endforeach; ?>
                            <?php if (!$masVendidos): ?><tr><td colspan="4" class="tabla-vacia">Aún no hay ventas en este periodo.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="tarjeta mt-4">
            <h2 class="titulo-3 mb-1"><i class="bi bi-receipt text-caramelo"></i> Ventas del día</h2>
            <p class="pequeno texto-sec">Folio, cliente, cajero e importe de las últimas ventas de hoy.</p>
            <div class="table-responsive">
                <table class="tabla-ec">
                    <thead><tr><th>Folio</th><th>Hora</th><th>Cliente</th><th>Total</th><th>Cajero</th><th>Puntos</th></tr></thead>
                    <tbody>
                    <?php foreach ($ventasDia as $v): ?>
                        <tr><td class="fw-semi"><?= e($v['folio']) ?></td><td><?= hora($v['fecha']) ?></td><td><?= e($v['cliente'] ?: 'Sin cuenta') ?></td><td><?= dinero($v['total']) ?></td><td><?= e($v['cajero']) ?></td><td><?= $v['puntos_ganados'] ? etiqueta(puntos_texto($v['puntos_ganados']), 'naranja') : '—' ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$ventasDia): ?><tr><td colspan="6" class="tabla-vacia">Todavía no hay ventas registradas hoy.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<section class="seccion-corta bg-crema">
    <div class="container-ec">
        <div class="encabezado-seccion mb-4">
            <h2 class="titulo-1">Herramientas de administración</h2>
            <p class="texto-sec mb-0">Acceso rápido a las funciones principales.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4"><div class="tarjeta text-center h-100"><span class="icono-circulo mb-3"><i class="bi bi-box-seam"></i></span><h3 class="titulo-3">Inventario</h3><p class="pequeno texto-sec">Consulta productos, existencias y disponibilidad.</p><a class="btn btn-ec btn-naranja btn-sm" href="<?= e(url('admin/inventario.php')) ?>">Abrir inventario</a></div></div>
            <div class="col-md-4"><div class="tarjeta text-center h-100"><span class="icono-circulo mb-3"><i class="bi bi-cup-hot"></i></span><h3 class="titulo-3">Productos</h3><p class="pequeno texto-sec">Consulta el catálogo de bebidas, postres y snacks.</p><a class="btn btn-ec btn-naranja btn-sm" href="<?= e(url('admin/productos.php')) ?>">Ver productos</a></div></div>
            <div class="col-md-4"><div class="tarjeta text-center h-100"><span class="icono-circulo mb-3"><i class="bi bi-people"></i></span><h3 class="titulo-3">Clientes</h3><p class="pequeno texto-sec">Consulta a los clientes y los puntos que han acumulado.</p><a class="btn btn-ec btn-naranja btn-sm" href="<?= e(url('admin/clientes.php')) ?>">Ver clientes</a></div></div>
        </div>
    </div>
</section>
<?php pie(['menu' => 'admin']); ?>
