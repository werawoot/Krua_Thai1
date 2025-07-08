<?php
// add_to_cart.php

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$conn = $connection;

// Get menu_id จาก query string
$menu_id = $_GET['menu_id'] ?? '';
if (!$menu_id) { die("ไม่พบเมนูที่ต้องการเพิ่มในตะกร้า"); }

// ดึงรายละเอียดเมนู
$stmt = $conn->prepare("SELECT m.*, c.name AS category_name FROM menus m LEFT JOIN menu_categories c ON m.category_id=c.id WHERE m.id=? LIMIT 1");
$stmt->bind_param("s", $menu_id);
$stmt->execute();
$menu = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$menu) { die("ไม่พบเมนูนี้ในระบบ"); }

// กด submit ฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity = max(1, intval($_POST['quantity'] ?? 1));
    $spice_level = $_POST['spice_level'] ?? 'medium';
    $no_coriander = isset($_POST['no_coriander']) ? 1 : 0;
    $extra_protein = isset($_POST['extra_protein']) ? 1 : 0;
    $note = trim($_POST['note'] ?? '');

    // เพิ่มลง session cart
    $_SESSION['cart'][] = [
        'menu_id' => $menu['id'],
        'name' => $menu['name_thai'],
        'quantity' => $quantity,
        'spice_level' => $spice_level,
        'no_coriander' => $no_coriander,
        'extra_protein' => $extra_protein,
        'note' => $note,
        'base_price' => $menu['base_price']
    ];

    header("Location: cart.php?added=1");
    exit();
}

// Spice options
$spice = [
    'mild' => 'ไม่เผ็ด',
    'medium' => 'กลาง',
    'hot' => 'เผ็ด',
    'extra_hot' => 'เผ็ดมาก!'
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เพิ่มเมนูลงตะกร้า - Krua Thai</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', Arial, sans-serif; background: #ece8e1; }
        .card { border-radius: 20px; box-shadow: 0 3px 18px #cf723a25;}
        .btn-curry { background: #cf723a; color: #fff; font-weight:600; }
        .btn-curry:hover { background: #bd9379; color: #fff;}
        label.form-check-label { font-weight: 400;}
    </style>
</head>
<body>
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card p-3">
                <div class="row">
                    <div class="col-md-5">
                        <img src="<?= htmlspecialchars($menu['main_image_url'] ?? 'https://placehold.co/400x300') ?>" class="img-fluid rounded mb-2" alt="<?= htmlspecialchars($menu['name_thai']) ?>">
                    </div>
                    <div class="col-md-7">
                        <h3 class="mb-1"><?= htmlspecialchars($menu['name_thai']) ?></h3>
                        <div class="mb-1"><small class="text-muted"><?= htmlspecialchars($menu['name']) ?></small></div>
                        <span class="badge bg-secondary mb-2"><?= htmlspecialchars($menu['category_name']) ?></span>
                        <div class="mb-2"><?= htmlspecialchars($menu['description']) ?></div>
                        <div class="mb-3"><span class="badge bg-info text-dark">฿<?= number_format($menu['base_price'],2) ?></span></div>

                        <form method="POST">
                            <div class="mb-2">
                                <label class="form-label">จำนวน (กล่อง)</label>
                                <input type="number" name="quantity" value="1" min="1" class="form-control" style="width:100px;" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">ระดับความเผ็ด</label>
                                <select name="spice_level" class="form-select" required>
                                    <?php foreach($spice as $key=>$label): ?>
                                        <option value="<?= $key ?>" <?= $menu['spice_level']==$key?'selected':'' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="no_coriander" id="no_coriander">
                                <label class="form-check-label" for="no_coriander">ไม่ใส่ผักชี</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="extra_protein" id="extra_protein">
                                <label class="form-check-label" for="extra_protein">เพิ่มโปรตีน (+20฿)</label>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">หมายเหตุ/คำขอเพิ่มเติม</label>
                                <input type="text" name="note" class="form-control" placeholder="ระบุความต้องการเพิ่มเติม (ถ้ามี)">
                            </div>
                            <button class="btn btn-curry w-100" type="submit">เพิ่มลงตะกร้า</button>
                            <a href="menus.php" class="btn btn-link mt-2">กลับไปเลือกเมนู</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
