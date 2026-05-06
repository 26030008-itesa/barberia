<?php
session_start();
include("../conexion.php");

if (!isset($_SESSION['usuario']) || !in_array($_SESSION['rol'], ['admin', 'vendedor'])) { header("Location: ../dashboard.php"); exit; }

$cveVenta = $_GET['clv'] ?? null;
if ($cveVenta === null) die("CLV de venta no especificada.");

while($conn->more_results() && $conn->next_result()){;}
$stmt = $conn->prepare("CALL sp_ver_venta_header(?)");
$stmt->bind_param("i", $cveVenta);
$stmt->execute();
$venta_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$venta_data) die("Venta no encontrada.");

while($conn->more_results() && $conn->next_result()){;}
$stmt = $conn->prepare("CALL sp_ver_venta_detalles(?)");
$stmt->bind_param("i", $cveVenta);
$stmt->execute();
$detalles_result = $stmt->get_result();

$detalles = [];
while($row = $detalles_result->fetch_assoc()) { $detalles[] = $row; }
$stmt->close();

$total_final = 0; 
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Venta #<?= htmlspecialchars($cveVenta) ?></title>
    <link rel="stylesheet" href="../css/style.css"> 
    <style>
        .crud-container { background: rgba(30, 30, 30, 0.9); backdrop-filter: blur(10px); padding: 30px; max-width: 900px; margin: 50px auto; border-radius: 4px; border-top: 3px solid #B88E3E; box-shadow: 0 20px 50px rgba(0,0,0,0.8); color: #FDFEFE; }
        h2 { border-bottom: 2px solid #3A3A3A; padding-bottom: 10px; margin-bottom: 20px; color: #B88E3E; font-family: 'Oswald', sans-serif; text-transform: uppercase;}
        h3 { color: #FDFEFE; margin-top: 30px; border-left: 5px solid #B88E3E; padding-left: 10px; font-family: 'Oswald', sans-serif;}
        .info-box { background: #111111; padding: 20px; border-radius: 2px; margin-bottom: 20px; border: 1px solid #3A3A3A;}
        .info-box p { margin: 5px 0; color: #A99F92;}
        .info-box strong { color: #FDFEFE; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; margin-top: 15px; border-collapse: collapse; }
        th, td { padding: 12px 10px; text-align: left; border-bottom: 1px dashed #3A3A3A; }
        th { background-color: #111111; font-family: 'Oswald', sans-serif; color: #B88E3E; text-transform: uppercase;}
        td { color: #FDFEFE; }
        .total-box { text-align: right; font-size: 1.8em; margin-top: 20px; padding-top: 15px; border-top: 3px solid #B88E3E; color: #B88E3E; font-family: 'Oswald', sans-serif; }
        .estado-box { display: inline-block; padding: 5px 10px; border-radius: 2px; color: #111; font-weight: bold; text-transform: uppercase; font-size: 0.8em; margin-left: 10px;}
        .estado-pagada { background-color: #2ecc71; } .estado-pendiente { background-color: #f1c40f; } .estado-cancelada { background-color: #9E2A2A; color: white;}
        .btn-secundario { display: inline-block; padding: 12px 25px; margin-top: 30px; background-color: transparent; border: 2px solid #A99F92; color: #A99F92; text-decoration: none; border-radius: 2px; font-weight: bold; font-family: 'Oswald', sans-serif; text-transform: uppercase; transition: 0.3s;}
        .btn-secundario:hover { background-color: #A99F92; color: #111;}
        @media (max-width: 768px) { .crud-container { margin: 20px 10px; padding: 20px; } }
    </style>
</head>
<body class="fondo-crud">
    <div class="crud-container">
        <h2>Detalle de Venta Ticket #<?= htmlspecialchars($venta_data['cveVenta']) ?></h2>
        
        <div class="info-box">
            <p><strong>Cliente:</strong> <?= htmlspecialchars($venta_data['nombre1'] . ' ' . $venta_data['apellidoP']) ?></p>
            <p><strong>Fecha de Venta:</strong> <?= htmlspecialchars($venta_data['fecha_venta']) ?></p>
            <p style="display: flex; align-items: center; margin-top: 10px;"><strong>Estado:</strong> 
                <?php
                    $estado = htmlspecialchars($venta_data['estado']);
                    $clase_estado = 'estado-' . strtolower($venta_data['estado']);
                    echo "<span class='estado-box $clase_estado'>$estado</span>";
                ?>
            </p>
        </div>

        <h3>Artículos de la cuenta</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr><th>Tipo</th><th>Artículo</th><th>Cant.</th><th>P. Unitario</th><th>Subtotal</th></tr>
                </thead>
                <tbody>
                    <?php foreach($detalles as $d): 
                        $nombre_item = ($d['tipo'] == 'producto') ? $d['nombre_producto'] : $d['nombre_servicio'];
                        $subtotal = $d['cantidad'] * $d['precio_unitario'];
                        $total_final += $subtotal;
                    ?>
                    <tr>
                        <td style="color: #A99F92; font-size: 0.9em; text-transform: uppercase;"><?= htmlspecialchars($d['tipo']) ?></td>
                        <td><?= htmlspecialchars($nombre_item) ?></td>
                        <td><?= htmlspecialchars($d['cantidad']) ?></td>
                        <td style="color: #A99F92;">$<?= htmlspecialchars(number_format($d['precio_unitario'], 2)) ?></td>
                        <td style="font-weight: bold;">$<?= htmlspecialchars(number_format($subtotal, 2)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="total-box">
            Total Pagado: $<?= htmlspecialchars(number_format($total_final, 2)) ?>
        </div>

        <a href="ventas.php" class="btn-secundario">← Volver al Historial</a>
    </div>
</body>
</html>