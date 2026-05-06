<?php
include("../conexion.php"); session_start();
function limpiar_buffer($conn) { while ($conn->more_results()) { $conn->next_result(); if ($res = $conn->store_result()) { $res->free(); } } }

if (!isset($_SESSION['usuario']) || !in_array($_SESSION['rol'], ['admin', 'vendedor'])) { header("Location: ../dashboard.php"); exit; }

$cveVenta = $_GET['clv'] ?? $_POST['clv'] ?? null; $mensaje = ''; $can_edit = false; $currentUser = $_SESSION['usuario'];
if (!$cveVenta) die("Error: CLV no especificada.");

$venta_data = null; $detalles_existentes = [];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        limpiar_buffer($conn);
        $stmt = $conn->prepare("CALL sp_iniciar_edicion_venta(?, ?)"); $stmt->bind_param("is", $cveVenta, $currentUser);
        if($stmt->execute()){
            $row = $stmt->get_result()->fetch_assoc();
            if($row) {
                if($row['status'] === 'OK') { $can_edit = true; $venta_data = $row; } 
                elseif($row['status'] === 'LOCKED') { $mensaje = "BLOQUEADO: Editado por " . $row['locked_by']; }
            }
        }
        $stmt->close(); limpiar_buffer($conn); 
    } catch (Exception $e) { $mensaje = "Error bloqueo: " . $e->getMessage(); }
}

if (!$venta_data) {
    limpiar_buffer($conn); $stmt = $conn->prepare("CALL sp_obtener_venta(?)"); $stmt->bind_param("i", $cveVenta); $stmt->execute();
    $venta_data = $stmt->get_result()->fetch_assoc(); $stmt->close(); limpiar_buffer($conn);
}
if (!$venta_data) die("Venta no encontrada.");

limpiar_buffer($conn); $stmt = $conn->prepare("CALL sp_obtener_detalles_venta(?)"); $stmt->bind_param("i", $cveVenta); $stmt->execute();
$res = $stmt->get_result(); while($row = $res->fetch_assoc()) $detalles_existentes[] = $row; $stmt->close(); limpiar_buffer($conn);

$clientes = []; $productos = []; $servicios = [];
limpiar_buffer($conn); if ($r = $conn->query("CALL sp_listar_clientes_simple()")) { while($x=$r->fetch_assoc()) $clientes[]=$x; $r->close(); limpiar_buffer($conn); }
limpiar_buffer($conn); if ($r = $conn->query("CALL sp_listar_productos()")) { while($x=$r->fetch_assoc()) $productos[]=$x; $r->close(); limpiar_buffer($conn); }
limpiar_buffer($conn); if ($r = $conn->query("CALL sp_listar_servicios_simple()")) { while($x=$r->fetch_assoc()) $servicios[]=$x; $r->close(); limpiar_buffer($conn); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cveVenta = $_POST['clv']; $cveCliente = $_POST['cveCliente']; $estado = $_POST['estado']; $version = $_POST['version'];
    $tipos = $_POST['item_tipo'] ?? []; $ids = $_POST['item_id'] ?? []; $cantidades = $_POST['item_cantidad'] ?? []; $precios = $_POST['item_precio_unitario'] ?? [];
    $retry_count = 0; $MAX_RETRIES = 3;

    while($retry_count < $MAX_RETRIES) {
        limpiar_buffer($conn); $conn->query("SET TRANSACTION ISOLATION LEVEL READ COMMITTED"); $conn->begin_transaction(); $total_venta = 0;
        try {
            limpiar_buffer($conn); $stmt = $conn->prepare("CALL sp_check_lock_venta(?)"); $stmt->bind_param("i", $cveVenta);
            if(!$stmt->execute()) throw new Exception("Error locking.");
            if($stmt->get_result()->num_rows == 0) throw new Exception("El bloqueo expiró.", 1337); $stmt->close();

            limpiar_buffer($conn); $stmt = $conn->prepare("CALL sp_limpiar_detalles_venta(?)"); $stmt->bind_param("i", $cveVenta); $stmt->execute(); $stmt->close();

            limpiar_buffer($conn); $r = $conn->query("CALL sp_get_next_detalle_id()"); $next_id = $r->fetch_assoc()['next_id']; $r->close();

            if (count($ids) > 0) {
                foreach ($ids as $k => $itemId) {
                    $tipo = $tipos[$k]; $cant = $cantidades[$k]; $precio = $precios[$k]; $total_venta += ($cant * $precio);
                    $prod = ($tipo=='producto') ? $itemId : null; $serv = ($tipo=='servicio') ? $itemId : null;
                    limpiar_buffer($conn); 
                    $stmt = $conn->prepare("CALL sp_insertar_detalle_manual(?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("iisiidi", $next_id, $cveVenta, $tipo, $prod, $serv, $cant, $precio);
                    if (!$stmt->execute()) throw new Exception("Error al insertar detalle."); $stmt->close(); $next_id++;
                }
            }
            limpiar_buffer($conn); $stmt = $conn->prepare("CALL sp_actualizar_venta_header(?, ?, ?, ?, ?)");
            $stmt->bind_param("iisdi", $cveVenta, $cveCliente, $estado, $total_venta, $version);
            if($stmt->execute()) {
                $stmt->close(); limpiar_buffer($conn); $conn->commit();
                $_SESSION['mensaje_status'] = "Venta actualizada correctamente."; header("Location: ventas.php"); exit;
            } else { throw new Exception("Conflicto de versión (Optimista)."); }
        } catch (Exception $e) {
            limpiar_buffer($conn); $conn->rollback();
            if ($conn->errno == 1213 && $retry_count < $MAX_RETRIES) { $retry_count++; usleep(50000); continue; }
            limpiar_buffer($conn); $stmt = $conn->prepare("CALL sp_liberar_bloqueo_venta(?, ?)"); $stmt->bind_param("is", $cveVenta, $currentUser); $stmt->execute(); $stmt->close(); limpiar_buffer($conn);
            $mensaje = "Error: " . $e->getMessage(); break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Venta</title><link rel="stylesheet" href="../css/style.css">
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
        .msg-success { background: #B88E3E; color: #111111; }
        .btn-secundario { display: block; text-align: center; margin-top: 15px; color: #A99F92; text-decoration: none; font-weight: 600; }
        .btn-secundario:hover { color: #FDFEFE; }
        fieldset { border: none; padding: 0; margin: 0; } fieldset:disabled { opacity: 0.5; pointer-events: none; }
        @media (max-width: 768px) { .item-row { flex-direction: column; align-items: stretch; border-bottom: 2px solid #3A3A3A; } .item-row > div { width: 100%; } .login-container.wide { padding: 20px; margin: 15px; } }
    </style>
</head>
<body class="fondo-crud">
<div class="login-container wide">
    <h2>Editar Venta #<?= htmlspecialchars($venta_data['cveVenta']) ?></h2>
    <?php if (!empty($mensaje)): ?>
        <p class="msg-box <?= strpos($mensaje, 'correctamente') !== false ? 'msg-success' : '' ?>"><?= $mensaje ?></p>
        <?php if (!$can_edit): ?><a href="ventas.php" class="btn-secundario">← Volver al Historial</a><?php endif; ?>
    <?php endif; ?>

    <form method="POST" action="editar_venta.php">
        <fieldset <?= !$can_edit ? 'disabled' : '' ?>>
            <input type="hidden" name="clv" value="<?= htmlspecialchars($cveVenta) ?>">
            <input type="hidden" name="version" value="<?= htmlspecialchars($venta_data['version'] ?? 1) ?>">
            
            <div class="item-form-section">
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <div style="flex: 2; min-width: 200px;">
                        <label>Cliente:</label>
                        <select name="cveCliente" required>
                            <?php foreach ($clientes as $c): ?><option value="<?= $c['cveCliente'] ?>" <?= ($c['cveCliente'] == $venta_data['cveCliente']) ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre1'] . ' ' . $c['apellidoP']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex: 1; min-width: 150px;">
                        <label>Estado:</label>
                        <select name="estado">
                            <option value="pagada" <?= ($venta_data['estado'] == 'pagada') ? 'selected' : '' ?>>Pagada</option>
                            <option value="pendiente" <?= ($venta_data['estado'] == 'pendiente') ? 'selected' : '' ?>>Pendiente</option>
                            <option value="cancelada" <?= ($venta_data['estado'] == 'cancelada') ? 'selected' : '' ?>>Cancelada</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="item-form-section">
                <h3>Detalles de Venta</h3>
                <div id="items-container"></div>
                <button type="button" onclick="addItemRow()" class="btn-add-item">+ Agregar Ítem</button>
            </div>
            
            <div class="total-display">Total: <span id="total-final">$0.00</span></div>
            <button type="submit" class="btn-principal">Actualizar Ticket</button>
        </fieldset>
        <a href="ventas.php" class="btn-secundario">← Volver a Ventas</a>
    </form>
</div>

<script>
    const productosJS = <?= json_encode($productos) ?>; const serviciosJS = <?= json_encode($servicios) ?>; const detallesExistentes = <?= json_encode($detalles_existentes) ?>;
    const container = document.getElementById('items-container'); let index = 0;
    function updateOptions(select, type, selectedId) {
        select.innerHTML = '<option value="">-- Seleccionar --</option>'; const list = (type === 'producto') ? productosJS : serviciosJS; const idField = (type === 'producto') ? 'cveProducto' : 'cveServicio';
        list.forEach(item => {
            const opt = document.createElement('option'); opt.value = item[idField]; opt.dataset.price = item.precio;
            opt.text = item.nombre + ' ($' + item.precio + ')'; if(item[idField] == selectedId) opt.selected = true; select.appendChild(opt);
        });
    }
    function calcTotal() {
        let total = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('.qty').value) || 0; const price = parseFloat(row.querySelector('.price-hidden').value) || 0; total += (qty * price);
        });
        document.getElementById('total-final').textContent = '$' + total.toFixed(2);
    }
    function addItemRow(data = null) {
        const row = document.createElement('div'); row.className = 'item-row'; const i = index++;
        const type = data ? data.tipo : 'servicio'; const id = data ? (data.tipo=='producto'?data.cveProducto:data.cveServicio) : ''; const qty = data ? data.cantidad : 1; const price = data ? data.precio_unitario : 0;
        row.innerHTML = `
            <div style="flex:1"><label>Tipo</label><select name="item_tipo[${i}]" class="type-select" onchange="changeType(this, ${i})"><option value="servicio" ${type=='servicio'?'selected':''}>Servicio</option><option value="producto" ${type=='producto'?'selected':''}>Producto</option></select></div>
            <div style="flex:3"><label>Descripción</label><select name="item_id[${i}]" class="id-select" required onchange="updatePrice(this, ${i})"></select><input type="text" class="price-display" value="$${parseFloat(price).toFixed(2)}" readonly style="border:none; background:transparent; color:#B88E3E; font-weight:bold; text-align:right; font-size:1em; margin-top:5px; padding:0;"><input type="hidden" name="item_precio_unitario[${i}]" class="price-hidden" value="${price}"></div>
            <div style="flex:1"><label>Cant.</label><input type="number" name="item_cantidad[${i}]" class="qty" value="${qty}" min="1" oninput="calcTotal()"></div>
            <div><button type="button" class="btn-remove" onclick="this.parentElement.parentElement.remove(); calcTotal()">X</button></div>
        `;
        container.appendChild(row); const typeSelect = row.querySelector('.type-select'); const idSelect = row.querySelector('.id-select'); updateOptions(idSelect, type, id); calcTotal();
    }
    window.changeType = function(select, i) { const row = select.parentElement.parentElement; updateOptions(row.querySelector('.id-select'), select.value, null); row.querySelector('.price-hidden').value = 0; row.querySelector('.price-display').value = '$0.00'; calcTotal(); }
    window.updatePrice = function(select, i) { const price = select.options[select.selectedIndex].dataset.price || 0; select.parentElement.querySelector('.price-hidden').value = price; select.parentElement.querySelector('.price-display').value = '$' + parseFloat(price).toFixed(2); calcTotal(); }
    if(detallesExistentes.length > 0) { detallesExistentes.forEach(d => addItemRow(d)); } else { addItemRow(); }
</script>
</body>
</html>