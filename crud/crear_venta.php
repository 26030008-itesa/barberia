<?php
include("../conexion.php");
session_start();

function limpiar_buffer($conn) { while ($conn->more_results()) { $conn->next_result(); if ($res = $conn->store_result()) { $res->free(); } } }

if (!isset($_SESSION['usuario']) || !isset($_SESSION['rol'])) { header("Location: ../index.html"); exit; }
if (!in_array($_SESSION['rol'], ['admin', 'vendedor'])) { header("Location: ../dashboard.php"); exit; }

function cargar_lista($conn, $sp) {
    $lista = []; limpiar_buffer($conn); 
    if ($res = $conn->query("CALL $sp")) { while ($row = $res->fetch_assoc()) $lista[] = $row; $res->close(); }
    limpiar_buffer($conn); return $lista;
}

$clientes = cargar_lista($conn, "sp_listar_clientes_simple()");
$productos = cargar_lista($conn, "sp_listar_productos()"); 
$servicios = cargar_lista($conn, "sp_listar_servicios_simple()");

$cveCliente = ''; $estado = 'pagada'; $mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cveCliente = $_POST['cveCliente']; $estado = $_POST['estado'];
    $tipos = $_POST['item_tipo'] ?? []; $ids = $_POST['item_id'] ?? [];
    $cantidades = $_POST['item_cantidad'] ?? []; $precios = $_POST['item_precio_unitario'] ?? []; 

    if (empty($ids)) { $mensaje = "ERROR: Debes agregar al menos un producto o servicio."; } 
    else {
        $retry_count = 0; $MAX_RETRIES = 3;
        while($retry_count < $MAX_RETRIES) {
            limpiar_buffer($conn);
            $conn->query("SET TRANSACTION ISOLATION LEVEL READ COMMITTED");
            $conn->begin_transaction();
            try {
                // Auto ID para la venta
                $q_id = $conn->query("SELECT IFNULL(MAX(cveVenta), 0) + 1 AS nv FROM ventas");
                $cveVenta = $q_id->fetch_assoc()['nv'];

                limpiar_buffer($conn);
                $stmt = $conn->prepare("CALL sp_crear_venta_header(?, ?, ?)");
                $stmt->bind_param("iis", $cveVenta, $cveCliente, $estado);
                if (!$stmt->execute()) throw new Exception("Error al crear cabecera.");
                $stmt->close();

                limpiar_buffer($conn);
                $r = $conn->query("CALL sp_get_next_detalle_id()");
                $next_id = $r->fetch_assoc()['next_id']; $r->close();

                foreach ($ids as $k => $itemId) {
                    $tipo = $tipos[$k]; $cant = $cantidades[$k]; $precio = $precios[$k];
                    $prod = ($tipo=='producto') ? $itemId : null; $serv = ($tipo=='servicio') ? $itemId : null;
                    limpiar_buffer($conn); 
                    $stmt = $conn->prepare("CALL sp_insertar_detalle_manual(?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("iisiidi", $next_id, $cveVenta, $tipo, $prod, $serv, $cant, $precio);
                    if (!$stmt->execute()) throw new Exception("Error al insertar detalle. Verifique stock.");
                    $stmt->close(); $next_id++;
                }
                $conn->commit();
                $_SESSION['mensaje_status'] = "Venta creada exitosamente (Ticket: $cveVenta).";
                header("Location: ventas.php"); exit;

            } catch (Exception $e) {
                limpiar_buffer($conn); $conn->rollback();
                if ($conn->errno == 1213 && $retry_count < $MAX_RETRIES) { $retry_count++; usleep(50000); continue; }
                $mensaje = "Error: " . $e->getMessage(); break;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Venta</title><link rel="stylesheet" href="../css/style.css"> 
    <style>
        .login-container.wide { max-width: 800px; padding: 30px; margin: 40px auto; background: rgba(30,30,30,0.85); backdrop-filter: blur(10px); border-top: 3px solid #B88E3E; border-radius: 4px; box-shadow: 0 20px 50px rgba(0,0,0,0.8); color: #FDFEFE; }
        h2 { color: #B88E3E; text-transform: uppercase; font-family: 'Oswald', sans-serif; text-align: center; margin-bottom: 25px; }
        label { display: block; margin-top: 10px; margin-bottom:5px; color: #A99F92; font-weight: 600; font-size: 0.85em; text-transform: uppercase; }
        input, select { width: 100%; padding: 10px; background: #111111; color: #FDFEFE; border: 1px solid #3A3A3A; border-radius: 2px; box-sizing: border-box; font-family: 'Montserrat', sans-serif; transition: 0.3s; }
        input:focus, select:focus { border-color: #B88E3E; outline: none; }
        .item-form-section { border: 1px solid #3A3A3A; padding: 20px; border-radius: 2px; margin-bottom: 20px; background: rgba(17,17,17,0.5); }
        .item-form-section h3 { color: #FDFEFE; font-family: 'Oswald', sans-serif; margin-top:0; border-bottom: 1px solid #3A3A3A; padding-bottom: 10px;}
        .item-row { display: flex; gap: 15px; align-items: flex-end; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px dashed #3A3A3A; }
        .btn-principal { width: 100%; padding: 14px; margin-top: 20px; background-color: #B88E3E; color: #111111; border: none; border-radius: 2px; cursor: pointer; font-family: 'Oswald', sans-serif; font-weight: bold; font-size: 1.1em; text-transform: uppercase; transition: 0.3s; }
        .btn-principal:hover { background-color: #A37D35; transform: translateY(-2px); }
        .btn-add-item { background-color: #3A3A3A; color: #FDFEFE; padding: 10px 20px; border: none; border-radius: 2px; cursor: pointer; font-weight: bold; transition: 0.3s; }
        .btn-add-item:hover { background-color: #555; }
        .btn-remove { background-color: #9E2A2A; color: white; border: none; padding: 10px 15px; cursor: pointer; border-radius: 2px; font-weight:bold; }
        .total-display { text-align: right; font-size: 1.6em; font-weight: bold; margin-top: 10px; color: #B88E3E; font-family: 'Oswald', sans-serif; }
        .msg-box { padding: 12px; font-weight: bold; border-radius: 2px; margin-bottom: 20px; font-size: 0.9em; background: #9E2A2A; color: #FDFEFE; }
        .btn-secundario { display: block; text-align: center; margin-top: 15px; color: #A99F92; text-decoration: none; font-weight: 600; }
        .btn-secundario:hover { color: #FDFEFE; }
        @media (max-width: 768px) { .item-row { flex-direction: column; align-items: stretch; border-bottom: 2px solid #3A3A3A; } .item-row > div { width: 100%; } .login-container.wide { padding: 20px; margin: 15px; } }
    </style>
</head>
<body class="fondo-crud">
    <div class="login-container wide">
        <h2>Registrar Venta</h2>
        <?php if (!empty($mensaje)): ?><p class="msg-box"><?= $mensaje ?></p><?php endif; ?>
        <form method="POST" action="crear_venta.php">
            <div class="item-form-section">
                <h3>Datos Generales</h3>
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <div style="flex: 2; min-width: 200px;">
                        <label>Cliente*:</label>
                        <select name="cveCliente" required>
                            <option value="">-- Seleccionar --</option>
                            <?php foreach ($clientes as $c): ?><option value="<?= $c['cveCliente'] ?>" <?= ($cveCliente == $c['cveCliente']) ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre1'] . ' ' . $c['apellidoP']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex: 1; min-width: 150px;">
                        <label>Estado de Pago:</label>
                        <select name="estado">
                            <option value="pagada" <?= ($estado == 'pagada') ? 'selected' : '' ?>>Pagada</option>
                            <option value="pendiente" <?= ($estado == 'pendiente') ? 'selected' : '' ?>>Pendiente</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="item-form-section">
                <h3>Servicios y Productos</h3>
                <div id="items-container"></div>
                <button type="button" onclick="addItemRow()" class="btn-add-item">+ Agregar otro servicio o producto</button>
            </div>
            <div class="total-display">Total: <span id="total-final">$0.00</span></div>
            <button type="submit" class="btn-principal">Procesar Pago</button>
            <a href="ventas.php" class="btn-secundario">← Cancelar y Volver</a>
        </form>
    </div>
<script>
    const productosJS = <?= json_encode($productos) ?>; const serviciosJS = <?= json_encode($servicios) ?>;
    const container = document.getElementById('items-container'); let index = 0;
    function updateOptions(select, type) {
        select.innerHTML = '<option value="">-- Seleccionar --</option>';
        const list = (type === 'producto') ? productosJS : serviciosJS;
        const idField = (type === 'producto') ? 'cveProducto' : 'cveServicio';
        list.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item[idField]; opt.dataset.price = item.precio;
            opt.text = `${item.nombre} - $${item.precio}` + ((type === 'producto') ? ` (Stock: ${item.stock})` : '');
            select.appendChild(opt);
        });
    }
    function calcTotal() {
        let total = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('.qty').value) || 0;
            const price = parseFloat(row.querySelector('.price-hidden').value) || 0;
            total += (qty * price);
        });
        document.getElementById('total-final').textContent = '$' + total.toFixed(2);
    }
    function addItemRow() {
        const row = document.createElement('div'); row.className = 'item-row'; const i = index++;
        row.innerHTML = `
            <div style="flex:1"><label>Tipo</label><select name="item_tipo[${i}]" class="type-select" onchange="changeType(this, ${i})"><option value="servicio">Servicio</option><option value="producto">Producto</option></select></div>
            <div style="flex:3"><label>Descripción</label><select name="item_id[${i}]" class="id-select" required onchange="updatePrice(this, ${i})"></select>
                <input type="text" class="price-display" value="$0.00" readonly style="border:none; background:transparent; color:#B88E3E; font-weight:bold; text-align:right; font-size:1em; margin-top:5px; padding:0;">
                <input type="hidden" name="item_precio_unitario[${i}]" class="price-hidden" value="0">
            </div>
            <div style="flex:1"><label>Cant.</label><input type="number" name="item_cantidad[${i}]" class="qty" value="1" min="1" oninput="calcTotal()"></div>
            <div style="display:flex; align-items:flex-end;"><button type="button" class="btn-remove" onclick="this.parentElement.parentElement.remove(); calcTotal()">Borrar</button></div>
        `;
        container.appendChild(row); updateOptions(row.querySelector('.id-select'), 'servicio');
    }
    window.changeType = function(select, i) {
        const row = select.parentElement.parentElement;
        updateOptions(row.querySelector('.id-select'), select.value);
        row.querySelector('.price-hidden').value = 0; row.querySelector('.price-display').value = '$0.00'; calcTotal();
    }
    window.updatePrice = function(select, i) {
        const row = select.parentElement.parentElement;
        const price = select.options[select.selectedIndex].dataset.price || 0;
        row.querySelector('.price-hidden').value = price; row.querySelector('.price-display').value = '$' + parseFloat(price).toFixed(2); calcTotal();
    }
    addItemRow();
</script>
</body>
</html>