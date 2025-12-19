<?php
require_once __DIR__ . '/wp-config.php';

function db_connect(): mysqli {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    if ($db->connect_error) {
        http_response_code(500);
        echo 'Error de conexión a la base de datos: ' . htmlspecialchars($db->connect_error, ENT_QUOTES, 'UTF-8');
        exit;
    }
    $db->set_charset('utf8');
    return $db;
}

function ensure_schema(mysqli $db): void {
    $db->query(
        "CREATE TABLE IF NOT EXISTS cash_shifts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            opened_at DATETIME NOT NULL,
            closed_at DATETIME NULL,
            opening_amount DECIMAL(10,2) DEFAULT 0,
            closing_amount DECIMAL(10,2) DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
    );

    $db->query(
        "CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            barcode VARCHAR(128) NOT NULL UNIQUE,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            stock INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
    );

    $db->query(
        "CREATE TABLE IF NOT EXISTS sales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            shift_id INT NULL,
            barcode VARCHAR(128) NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            total DECIMAL(10,2) NOT NULL DEFAULT 0,
            sold_at DATETIME NOT NULL,
            CONSTRAINT fk_sales_product FOREIGN KEY (product_id) REFERENCES products(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
    );
}

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$db = db_connect();
ensure_schema($db);

$message = '';
$error = '';

$action = $_POST['action'] ?? '';

if ($action === 'open_shift') {
    $opening_amount = (float)($_POST['opening_amount'] ?? 0);
    $result = $db->query("SELECT id FROM cash_shifts WHERE closed_at IS NULL ORDER BY opened_at DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $error = 'Ya hay una caja/turno abierto.';
    } else {
        $stmt = $db->prepare('INSERT INTO cash_shifts (opened_at, opening_amount) VALUES (NOW(), ?)');
        $stmt->bind_param('d', $opening_amount);
        if ($stmt->execute()) {
            $message = 'Caja/turno abierto correctamente.';
        } else {
            $error = 'No se pudo abrir la caja.';
        }
        $stmt->close();
    }
}

if ($action === 'close_shift') {
    $closing_amount = (float)($_POST['closing_amount'] ?? 0);
    $result = $db->query("SELECT id FROM cash_shifts WHERE closed_at IS NULL ORDER BY opened_at DESC LIMIT 1");
    $shift = $result ? $result->fetch_assoc() : null;
    if (!$shift) {
        $error = 'No hay una caja/turno abierto para cerrar.';
    } else {
        $stmt = $db->prepare('UPDATE cash_shifts SET closed_at = NOW(), closing_amount = ? WHERE id = ?');
        $stmt->bind_param('di', $closing_amount, $shift['id']);
        if ($stmt->execute()) {
            $message = 'Caja/turno cerrado correctamente.';
        } else {
            $error = 'No se pudo cerrar la caja.';
        }
        $stmt->close();
    }
}

if ($action === 'sell_product') {
    $barcode = trim($_POST['barcode'] ?? '');
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));

    if ($barcode === '') {
        $error = 'Ingresá o escaneá un código de barras.';
    } else {
        $stmt = $db->prepare('SELECT id, name, price, stock FROM products WHERE barcode = ? LIMIT 1');
        $stmt->bind_param('s', $barcode);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$product) {
            $error = 'No se encontró el producto con ese código.';
        } elseif ($product['stock'] < $quantity) {
            $error = 'Stock insuficiente. Disponible: ' . (int)$product['stock'];
        } else {
            $new_stock = (int)$product['stock'] - $quantity;
            $price = (float)$product['price'];
            $total = $price * $quantity;

            $db->begin_transaction();
            $stmt = $db->prepare('UPDATE products SET stock = ? WHERE id = ?');
            $stmt->bind_param('ii', $new_stock, $product['id']);
            $ok = $stmt->execute();
            $stmt->close();

            $shift_id = null;
            $shift_result = $db->query("SELECT id FROM cash_shifts WHERE closed_at IS NULL ORDER BY opened_at DESC LIMIT 1");
            if ($shift_result && $shift_result->num_rows > 0) {
                $shift_row = $shift_result->fetch_assoc();
                $shift_id = (int)$shift_row['id'];
            }

            if ($ok) {
                $stmt = $db->prepare('INSERT INTO sales (product_id, shift_id, barcode, quantity, price, total, sold_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
                $stmt->bind_param('iisidd', $product['id'], $shift_id, $barcode, $quantity, $price, $total);
                $ok = $stmt->execute();
                $stmt->close();
            }

            if ($ok) {
                $db->commit();
                $message = 'Venta registrada. Stock restante: ' . $new_stock;
            } else {
                $db->rollback();
                $error = 'No se pudo registrar la venta.';
            }
        }
    }
}

$shift_result = $db->query("SELECT * FROM cash_shifts WHERE closed_at IS NULL ORDER BY opened_at DESC LIMIT 1");
$current_shift = $shift_result ? $shift_result->fetch_assoc() : null;

$recent_sales = $db->query(
    "SELECT s.id, s.barcode, s.quantity, s.total, s.sold_at, p.name
     FROM sales s
     INNER JOIN products p ON p.id = s.product_id
     ORDER BY s.sold_at DESC
     LIMIT 5"
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Intenso Tandil - Panel Principal</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f6f6f6;
            margin: 0;
            padding: 0;
        }
        header {
            background: #1f2937;
            color: #fff;
            padding: 20px;
        }
        main {
            max-width: 1100px;
            margin: 20px auto;
            padding: 0 16px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
        }
        .card {
            background: #fff;
            border-radius: 8px;
            padding: 16px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        h2 {
            margin-top: 0;
        }
        label {
            display: block;
            margin: 8px 0 4px;
        }
        input[type="text"],
        input[type="number"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        button {
            margin-top: 12px;
            padding: 10px 16px;
            border: none;
            border-radius: 4px;
            background: #2563eb;
            color: white;
            cursor: pointer;
        }
        .status {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 16px;
        }
        .status.success {
            background: #d1fae5;
            color: #065f46;
        }
        .status.error {
            background: #fee2e2;
            color: #991b1b;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            text-align: left;
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
<header>
    <h1>Intenso Tandil - Panel Principal</h1>
</header>
<main>
    <?php if ($message): ?>
        <div class="status success"><?php echo h($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="status error"><?php echo h($error); ?></div>
    <?php endif; ?>

    <div class="grid">
        <section class="card">
            <h2>Caja / Turno</h2>
            <p><strong>Estado:</strong> <?php echo $current_shift ? 'Abierto' : 'Cerrado'; ?></p>
            <?php if ($current_shift): ?>
                <p><strong>Abierto desde:</strong> <?php echo h($current_shift['opened_at']); ?></p>
                <form method="post">
                    <input type="hidden" name="action" value="close_shift">
                    <label for="closing_amount">Monto de cierre</label>
                    <input type="number" step="0.01" name="closing_amount" id="closing_amount" placeholder="0.00">
                    <button type="submit">Cerrar caja/turno</button>
                </form>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="action" value="open_shift">
                    <label for="opening_amount">Monto de apertura</label>
                    <input type="number" step="0.01" name="opening_amount" id="opening_amount" placeholder="0.00">
                    <button type="submit">Abrir caja/turno</button>
                </form>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>Venta rápida</h2>
            <form method="post">
                <input type="hidden" name="action" value="sell_product">
                <label for="barcode">Código de barras</label>
                <input type="text" name="barcode" id="barcode" placeholder="Escaneá o ingresá el código" autofocus>
                <label for="quantity">Cantidad</label>
                <input type="number" name="quantity" id="quantity" value="1" min="1">
                <button type="submit">Vender producto</button>
            </form>
            <p>El stock se actualiza automáticamente al registrar la venta.</p>
        </section>

        <section class="card">
            <h2>Ventas recientes</h2>
            <?php if ($recent_sales && $recent_sales->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Código</th>
                            <th>Cant.</th>
                            <th>Total</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($sale = $recent_sales->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo h($sale['name']); ?></td>
                            <td><?php echo h($sale['barcode']); ?></td>
                            <td><?php echo (int)$sale['quantity']; ?></td>
                            <td>$<?php echo number_format((float)$sale['total'], 2, ',', '.'); ?></td>
                            <td><?php echo h($sale['sold_at']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No hay ventas registradas aún.</p>
            <?php endif; ?>
        </section>
    </div>
</main>
</body>
</html>