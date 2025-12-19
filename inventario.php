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

if ($action === 'add_product') {
    $name = trim($_POST['name'] ?? '');
    $barcode = trim($_POST['barcode'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);

    if ($name === '' || $barcode === '') {
        $error = 'Completá nombre y código de barras.';
    } else {
        $stmt = $db->prepare('INSERT INTO products (name, barcode, price, stock) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssdi', $name, $barcode, $price, $stock);
        if ($stmt->execute()) {
            $message = 'Producto agregado correctamente.';
        } else {
            $error = 'No se pudo agregar el producto. Verificá que el código no exista.';
        }
        $stmt->close();
    }
}

if ($action === 'update_product') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $barcode = trim($_POST['barcode'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);

    if ($product_id <= 0 || $name === '' || $barcode === '') {
        $error = 'Datos inválidos para actualizar.';
    } else {
        $stmt = $db->prepare('UPDATE products SET name = ?, barcode = ?, price = ?, stock = ? WHERE id = ?');
        $stmt->bind_param('ssdii', $name, $barcode, $price, $stock, $product_id);
        if ($stmt->execute()) {
            $message = 'Producto actualizado.';
        } else {
            $error = 'No se pudo actualizar el producto. Revisá el código.';
        }
        $stmt->close();
    }
}

if ($action === 'adjust_stock') {
    $barcode = trim($_POST['barcode'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);

    if ($barcode === '' || $quantity <= 0) {
        $error = 'Ingresá un código y cantidad válida.';
    } else {
        $stmt = $db->prepare('SELECT id, stock FROM products WHERE barcode = ? LIMIT 1');
        $stmt->bind_param('s', $barcode);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$product) {
            $error = 'No se encontró un producto con ese código.';
        } else {
            $new_stock = (int)$product['stock'] + $quantity;
            $stmt = $db->prepare('UPDATE products SET stock = ? WHERE id = ?');
            $stmt->bind_param('ii', $new_stock, $product['id']);
            if ($stmt->execute()) {
                $message = 'Stock actualizado. Nuevo stock: ' . $new_stock;
            } else {
                $error = 'No se pudo actualizar el stock.';
            }
            $stmt->close();
        }
    }
}

$products = $db->query('SELECT id, name, barcode, price, stock FROM products ORDER BY name ASC');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Intenso Tandil - Inventario</title>
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
        header a {
            color: #93c5fd;
            text-decoration: none;
        }
        main {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 16px 32px;
        }
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
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
            width: 100%;
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
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        th, td {
            text-align: left;
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            background: #f3f4f6;
        }
        .table-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 8px;
        }
        .inline-input {
            width: 100%;
            padding: 6px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
        }
        @media (max-width: 768px) {
            table, thead, tbody, th, td, tr {
                display: block;
            }
            thead {
                display: none;
            }
            tr {
                margin-bottom: 12px;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                background: #fff;
                padding: 8px;
            }
            td {
                border-bottom: none;
                padding: 6px 8px;
            }
            td::before {
                content: attr(data-label);
                font-weight: bold;
                display: block;
                margin-bottom: 4px;
                color: #4b5563;
            }
        }
    </style>
</head>
<body>
<header>
    <div class="toolbar">
        <h1>Inventario</h1>
        <a href="panel.php">← Volver al panel</a>
    </div>
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
            <h2>Agregar producto</h2>
            <form method="post">
                <input type="hidden" name="action" value="add_product">
                <label for="new_name">Nombre</label>
                <input type="text" name="name" id="new_name" placeholder="Nombre del producto" required>
                <label for="new_barcode">Código de barras</label>
                <input type="text" name="barcode" id="new_barcode" placeholder="Escaneá o ingresá el código" required>
                <label for="new_price">Precio</label>
                <input type="number" step="0.01" name="price" id="new_price" placeholder="0.00">
                <label for="new_stock">Stock inicial</label>
                <input type="number" name="stock" id="new_stock" value="0" min="0">
                <button type="submit">Agregar al inventario</button>
            </form>
        </section>

        <section class="card">
            <h2>Agregar stock por código</h2>
            <form method="post">
                <input type="hidden" name="action" value="adjust_stock">
                <label for="scan_barcode">Código de barras</label>
                <input type="text" name="barcode" id="scan_barcode" placeholder="Escaneá el código" autofocus>
                <label for="scan_quantity">Cantidad a sumar</label>
                <input type="number" name="quantity" id="scan_quantity" value="1" min="1">
                <button type="submit">Sumar stock</button>
            </form>
            <p>Usá este formulario con el escáner para sumar stock rápido.</p>
        </section>
    </div>

    <section style="margin-top: 24px;">
        <h2>Listado de productos</h2>
        <?php if ($products && $products->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Código</th>
                        <th>Precio</th>
                        <th>Stock</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($product = $products->fetch_assoc()): ?>
                        <tr>
                            <td data-label="Producto">
                                <input class="inline-input" type="text" name="name" form="product-<?php echo (int)$product['id']; ?>" value="<?php echo h($product['name']); ?>">
                            </td>
                            <td data-label="Código">
                                <input class="inline-input" type="text" name="barcode" form="product-<?php echo (int)$product['id']; ?>" value="<?php echo h($product['barcode']); ?>">
                            </td>
                            <td data-label="Precio">
                                <input class="inline-input" type="number" step="0.01" name="price" form="product-<?php echo (int)$product['id']; ?>" value="<?php echo number_format((float)$product['price'], 2, '.', ''); ?>">
                            </td>
                            <td data-label="Stock">
                                <input class="inline-input" type="number" name="stock" form="product-<?php echo (int)$product['id']; ?>" value="<?php echo (int)$product['stock']; ?>" min="0">
                            </td>
                            <td data-label="Acciones">
                                <form method="post" id="product-<?php echo (int)$product['id']; ?>">
                                    <input type="hidden" name="action" value="update_product">
                                    <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
                                    <div class="table-actions">
                                        <button type="submit">Guardar cambios</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay productos cargados aún.</p>
        <?php endif; ?>
    </section>
</main>
</body>
</html>