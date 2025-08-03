<?php
// add_to_cart.php

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$conn = $connection;

// Get menu_id from query string
$menu_id = $_GET['menu_id'] ?? '';
if (!$menu_id) { die("Menu not found in cart"); }

// Get menu details
$stmt = $conn->prepare("SELECT m.*, c.name AS category_name FROM menus m LEFT JOIN menu_categories c ON m.category_id=c.id WHERE m.id=? LIMIT 1");
$stmt->bind_param("s", $menu_id);
$stmt->execute();
$menu = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$menu) { die("This menu item was not found in the system"); }

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity = max(1, intval($_POST['quantity'] ?? 1));
    $spice_level = $_POST['spice_level'] ?? 'medium';
    $no_coriander = isset($_POST['no_coriander']) ? 1 : 0;
    $extra_protein = isset($_POST['extra_protein']) ? 1 : 0;
    $note = trim($_POST['note'] ?? '');

    // Add to session cart
    $_SESSION['cart'][] = [
        'menu_id' => $menu['id'],
        'name' => $menu['name'],
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
    'mild' => 'Mild',
    'medium' => 'Medium',
    'hot' => 'Hot',
    'extra_hot' => 'Extra Hot!'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add to Cart - Krua Thai</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --curry: #cf723a;
            --brown: #bd9379;
            --cream: #ece8e1;
            --sage: #adb89d;
            --text-dark: #2c3e50;
            --text-gray: #7f8c8d;
        }

        body { 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; 
            background: var(--cream);
            color: var(--text-dark);
            line-height: 1.6;
        }

        .container {
            max-width: 900px;
        }

        .card { 
            border-radius: 20px; 
            box-shadow: 0 8px 32px rgba(207, 114, 58, 0.15);
            border: none;
            background: white;
        }

        .card-body {
            padding: 2rem;
        }

        .menu-image {
            border-radius: 16px;
            width: 100%;
            height: 300px;
            object-fit: cover;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }

        .menu-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .menu-subtitle {
            font-size: 1rem;
            color: var(--text-gray);
            margin-bottom: 1rem;
        }

        .category-badge {
            background: linear-gradient(135deg, var(--sage), #95a695);
            color: white;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
        }

        .price-badge {
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: white;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-size: 1.25rem;
            display: inline-block;
            margin: 1rem 0;
        }

        .description {
            color: var(--text-gray);
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        .quantity-input {
            width: 120px;
        }

        .form-check {
            margin-bottom: 1rem;
        }

        .form-check-input {
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            width: 1.25rem;
            height: 1.25rem;
        }

        .form-check-input:checked {
            background-color: var(--curry);
            border-color: var(--curry);
        }

        .form-check-label {
            font-weight: 500;
            color: var(--text-dark);
            margin-left: 0.5rem;
        }

        .btn-curry { 
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: white;
            font-weight: 600;
            padding: 1rem 2rem;
            border-radius: 50px;
            border: none;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(207, 114, 58, 0.3);
        }

        .btn-curry:hover { 
            background: linear-gradient(135deg, var(--brown), var(--curry));
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(207, 114, 58, 0.4);
        }

        .btn-link {
            color: var(--curry);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: color 0.3s ease;
        }

        .btn-link:hover {
            color: var(--brown);
        }

        .nutrition-info {
            background: rgba(173, 184, 157, 0.1);
            border-radius: 12px;
            padding: 1rem;
            margin: 1rem 0;
        }

        .nutrition-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .special-requests {
            background: rgba(207, 114, 58, 0.05);
            border-radius: 12px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }

        .special-requests h6 {
            color: var(--curry);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .card-body {
                padding: 1.5rem;
            }
            
            .menu-title {
                font-size: 1.5rem;
            }
            
            .row {
                --bs-gutter-x: 0;
            }
            
            .col-md-5, .col-md-7 {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-start">
                        <!-- Image Column -->
                        <div class="col-md-5 mb-4 mb-md-0">
                            <img src="<?= htmlspecialchars($menu['main_image_url'] ?? 'https://images.unsplash.com/photo-1559847844-d721426d6edc?w=400&h=300&fit=crop') ?>" 
                                 class="menu-image" 
                                 alt="<?= htmlspecialchars($menu['name']) ?>">
                            
                            <!-- Nutrition Info (if available) -->
                            <?php if (!empty($menu['calories'])): ?>
                            <div class="nutrition-info mt-3">
                                <h6 style="color: var(--sage); font-weight: 600; margin-bottom: 0.75rem;">Nutrition Facts</h6>
                                <div class="nutrition-item">
                                    <span>Calories:</span>
                                    <strong><?= number_format($menu['calories']) ?></strong>
                                </div>
                                <?php if (!empty($menu['protein'])): ?>
                                <div class="nutrition-item">
                                    <span>Protein:</span>
                                    <strong><?= number_format($menu['protein']) ?>g</strong>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($menu['carbs'])): ?>
                                <div class="nutrition-item">
                                    <span>Carbs:</span>
                                    <strong><?= number_format($menu['carbs']) ?>g</strong>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Details Column -->
                        <div class="col-md-7">
                            <div class="mb-3">
                                <h1 class="menu-title"><?= htmlspecialchars($menu['name']) ?></h1>
                                <?php if (!empty($menu['name_thai'])): ?>
                                <p class="menu-subtitle"><?= htmlspecialchars($menu['name_thai']) ?></p>
                                <?php endif; ?>
                                <span class="category-badge"><?= htmlspecialchars($menu['category_name']) ?></span>
                            </div>

                            <div class="description">
                                <?= htmlspecialchars($menu['description']) ?>
                            </div>

                            <div class="price-badge">
                                $<?= number_format($menu['base_price'], 2) ?>
                            </div>

                            <!-- Order Form -->
                            <form method="POST" class="mt-4">
                                <!-- Quantity -->
                                <div class="mb-3">
                                    <label class="form-label">Quantity</label>
                                    <input type="number" 
                                           name="quantity" 
                                           value="1" 
                                           min="1" 
                                           max="10"
                                           class="form-control quantity-input" 
                                           required>
                                </div>

                                <!-- Spice Level -->
                                <div class="mb-3">
                                    <label class="form-label">Spice Level</label>
                                    <select name="spice_level" class="form-select" required>
                                        <?php foreach($spice as $key => $label): ?>
                                            <option value="<?= $key ?>" <?= ($menu['spice_level'] ?? 'medium') == $key ? 'selected' : '' ?>>
                                                <?= $label ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Special Requests -->
                                <div class="special-requests">
                                    <h6>Special Requests</h6>
                                    
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               name="no_coriander" 
                                               id="no_coriander">
                                        <label class="form-check-label" for="no_coriander">
                                            No Cilantro
                                        </label>
                                    </div>

                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               name="extra_protein" 
                                               id="extra_protein">
                                        <label class="form-check-label" for="extra_protein">
                                            Extra Protein (+$3.00)
                                        </label>
                                    </div>
                                </div>

                                <!-- Special Instructions -->
                                <div class="mb-4">
                                    <label class="form-label">Special Instructions</label>
                                    <textarea name="note" 
                                            class="form-control" 
                                            rows="3"
                                            placeholder="Any additional requests or dietary restrictions (optional)"></textarea>
                                </div>

                                <!-- Action Buttons -->
                                <div class="d-grid gap-2">
                                    <button class="btn btn-curry" type="submit">
                                        🛒 Add to Cart
                                    </button>
                                    <div class="text-center mt-3">
                                        <a href="menus.php" class="btn-link">
                                            ← Back to Menu
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Enhanced quantity controls
document.addEventListener('DOMContentLoaded', function() {
    const quantityInput = document.querySelector('input[name="quantity"]');
    
    // Add quantity controls
    const quantityContainer = quantityInput.parentElement;
    quantityContainer.style.position = 'relative';
    
    // Create quantity controls wrapper
    const controlsWrapper = document.createElement('div');
    controlsWrapper.style.cssText = 'display: flex; align-items: center; gap: 0.5rem;';
    
    // Minus button
    const minusBtn = document.createElement('button');
    minusBtn.type = 'button';
    minusBtn.innerHTML = '-';
    minusBtn.style.cssText = 'width: 40px; height: 40px; border: 2px solid var(--curry); background: white; color: var(--curry); border-radius: 8px; font-weight: 600; cursor: pointer;';
    minusBtn.addEventListener('click', () => {
        if (quantityInput.value > 1) {
            quantityInput.value = parseInt(quantityInput.value) - 1;
        }
    });
    
    // Plus button
    const plusBtn = document.createElement('button');
    plusBtn.type = 'button';
    plusBtn.innerHTML = '+';
    plusBtn.style.cssText = 'width: 40px; height: 40px; border: 2px solid var(--curry); background: var(--curry); color: white; border-radius: 8px; font-weight: 600; cursor: pointer;';
    plusBtn.addEventListener('click', () => {
        if (parseInt(quantityInput.value) < 10) {
            quantityInput.value = parseInt(quantityInput.value) + 1;
        }
    });
    
    // Style quantity input
    quantityInput.style.cssText = 'text-align: center; font-weight: 600; width: 80px;';
    
    // Replace input with controls
    quantityInput.parentNode.insertBefore(controlsWrapper, quantityInput);
    controlsWrapper.appendChild(minusBtn);
    controlsWrapper.appendChild(quantityInput);
    controlsWrapper.appendChild(plusBtn);
});

// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const quantity = document.querySelector('input[name="quantity"]').value;
    const spiceLevel = document.querySelector('select[name="spice_level"]').value;
    
    if (!quantity || quantity < 1) {
        e.preventDefault();
        alert('Please select a valid quantity');
        return;
    }
    
    if (!spiceLevel) {
        e.preventDefault();
        alert('Please select a spice level');
        return;
    }
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '🛒 Adding to Cart...';
    submitBtn.disabled = true;
});

// Smooth scroll to form on mobile
if (window.innerWidth <= 768) {
    document.querySelector('form').scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>
</body>
</html>