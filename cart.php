<?php
session_start();
include "db.php";
include "cost_lib.php";

if (!isset($_SESSION["cart"])) $_SESSION["cart"] = [];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($x){ return number_format((float)$x, 2); }

// Load global costs
$costs = getCosts($conn);
$TAX_RATE     = (float)$costs["tax_rate"];
$DELIVERY_FEE = (float)$costs["delivery_fee"];
$DISCOUNT     = (float)$costs["discount"];

// Logo path
$LOGO_PATH = "admin/uploads/logo/Screenshot 2026-01-10 at 7.30.43 PM.png";

// AJAX handling
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true) ?: [];
    $action = $data["action"] ?? "";
    
    if ($action === "set_qty") {
        $idx = (int)($data["index"] ?? -1);
        $qty = max(1, (int)($data["qty"] ?? 1));
        
        if ($idx >= 0 && isset($_SESSION["cart"][$idx])) {
            $_SESSION["cart"][$idx]["qty"] = $qty;
        }
        
        // Recalculate totals
        $subtotal = 0.0;
        foreach ($_SESSION["cart"] as $item) {
            $q = max(1, (int)($item["qty"] ?? 1));
            $base = (float)($item["base_unit_price"] ?? 0);
            
            $boostSum = 0.0;
            foreach (($item["boosters"] ?? []) as $b) {
                $boostSum += (float)($b["booster_price"] ?? 0);
            }
            
            $unit = $base + $boostSum;
            $subtotal += $unit * $q;
        }
        
        $delivery_fee = !empty($_SESSION["cart"]) ? $DELIVERY_FEE : 0.0;
        $discount = $DISCOUNT;
        $totals = computeTotalsFromSubtotal($subtotal, $TAX_RATE, $delivery_fee, $discount);
        
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode([
            "ok" => true,
            "subtotal" => (float)$subtotal,
            "tax_amount" => (float)$totals["tax_amount"],
            "delivery_fee" => (float)$delivery_fee,
            "discount" => (float)$discount,
            "grand_total" => (float)$totals["grand_total"]
        ]);
        exit();
    }
    
    if ($action === "remove_item") {
        $idx = (int)($data["index"] ?? -1);
        if ($idx >= 0 && isset($_SESSION["cart"][$idx])) {
            array_splice($_SESSION["cart"], $idx, 1);
        }
        
        // Recalculate totals
        $subtotal = 0.0;
        foreach ($_SESSION["cart"] as $item) {
            $q = max(1, (int)($item["qty"] ?? 1));
            $base = (float)($item["base_unit_price"] ?? 0);
            
            $boostSum = 0.0;
            foreach (($item["boosters"] ?? []) as $b) {
                $boostSum += (float)($b["booster_price"] ?? 0);
            }
            
            $unit = $base + $boostSum;
            $subtotal += $unit * $q;
        }
        
        $delivery_fee = !empty($_SESSION["cart"]) ? $DELIVERY_FEE : 0.0;
        $discount = $DISCOUNT;
        $totals = computeTotalsFromSubtotal($subtotal, $TAX_RATE, $delivery_fee, $discount);
        
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode([
            "ok" => true,
            "subtotal" => (float)$subtotal,
            "tax_amount" => (float)$totals["tax_amount"],
            "delivery_fee" => (float)$delivery_fee,
            "discount" => (float)$discount,
            "grand_total" => (float)$totals["grand_total"],
            "count" => count($_SESSION["cart"])
        ]);
        exit();
    }
    
    if ($action === "clear_cart") {
        $_SESSION["cart"] = [];
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(["ok" => true, "count" => 0]);
        exit();
    }
    
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["ok" => false, "error" => "Invalid action"]);
    exit();
}

// Calculate current totals
$subtotal = 0.0;
foreach ($_SESSION["cart"] as $item) {
    $qty = max(1, (int)($item["qty"] ?? 1));
    $base = (float)($item["base_unit_price"] ?? 0);
    
    $boostSum = 0.0;
    foreach (($item["boosters"] ?? []) as $b) {
        $boostSum += (float)($b["booster_price"] ?? 0);
    }
    
    $unit = $base + $boostSum;
    $subtotal += $unit * $qty;
}

$delivery_fee = !empty($_SESSION["cart"]) ? $DELIVERY_FEE : 0.0;
$discount = $DISCOUNT;
$totals = computeTotalsFromSubtotal($subtotal, $TAX_RATE, $delivery_fee, $discount);
$tax_amount = $totals["tax_amount"];
$grand_total = $totals["grand_total"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>THE VIBE | Cart</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* ===== VARIABLES & RESET ===== */
    :root {
      --primary: #FF6B35;
      --secondary: #FFB347;
      --accent: #00D4AA;
      --dark: #1A1A2E;
      --light: #F8F9FA;
      --muted: #6C757D;
      --success: #28A745;
      --danger: #DC3545;
      --glass: rgba(255, 255, 255, 0.9);
      --shadow-sm: 0 4px 6px rgba(0, 0, 0, 0.07);
      --shadow-md: 0 10px 25px rgba(0, 0, 0, 0.1);
      --shadow-lg: 0 20px 40px rgba(255, 107, 53, 0.15);
      --radius: 16px;
      --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      -webkit-tap-highlight-color: transparent;
    }
    
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
      color: var(--dark);
      min-height: 100vh;
      overflow-x: hidden;
    }
    
    /* ===== ANIMATIONS ===== */
    @keyframes float {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-10px); }
    }
    
    @keyframes slideIn {
      from { transform: translateX(50px); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.05); }
      100% { transform: scale(1); }
    }
    
    @keyframes shimmer {
      0% { background-position: -1000px 0; }
      100% { background-position: 1000px 0; }
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    /* ===== BACKGROUND ELEMENTS ===== */
    .bg-elements {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      z-index: -1;
      overflow: hidden;
    }
    
    .bg-circle {
      position: absolute;
      border-radius: 50%;
      filter: blur(40px);
      opacity: 0.15;
      animation: float 20s infinite ease-in-out;
    }
    
    .circle-1 {
      width: 300px;
      height: 300px;
      background: var(--primary);
      top: 10%;
      left: 5%;
      animation-delay: 0s;
    }
    
    .circle-2 {
      width: 400px;
      height: 400px;
      background: var(--secondary);
      bottom: 10%;
      right: 5%;
      animation-delay: -10s;
    }
    
    .circle-3 {
      width: 200px;
      height: 200px;
      background: var(--accent);
      top: 50%;
      left: 80%;
      animation-delay: -5s;
    }
    
    /* ===== NAVIGATION ===== */
    .nav-container {
      background: var(--glass);
      backdrop-filter: blur(20px);
      border-bottom: 1px solid rgba(255, 255, 255, 0.2);
      position: sticky;
      top: 0;
      z-index: 100;
      padding: 1rem 2rem;
      box-shadow: var(--shadow-sm);
    }
    
    .nav-content {
      max-width: 1400px;
      margin: 0 auto;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
    }
    
    .logo {
      display: flex;
      align-items: center;
      gap: 1rem;
      text-decoration: none;
      color: var(--dark);
    }
    
    .logo-img {
      width: 50px;
      height: 50px;
      border-radius: 12px;
      object-fit: cover;
      border: 3px solid var(--primary);
      box-shadow: var(--shadow-md);
    }
    
    .logo-text h1 {
      font-size: 1.5rem;
      font-weight: 800;
      background: linear-gradient(90deg, var(--primary), var(--secondary));
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }
    
    .logo-text p {
      font-size: 0.8rem;
      color: var(--muted);
      font-weight: 600;
    }
    
    .nav-buttons {
      display: flex;
      gap: 0.75rem;
    }
    
    .nav-btn {
      padding: 0.75rem 1.5rem;
      border-radius: 50px;
      background: white;
      border: 2px solid rgba(255, 107, 53, 0.1);
      color: var(--dark);
      text-decoration: none;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      transition: var(--transition);
      box-shadow: var(--shadow-sm);
    }
    
    .nav-btn:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
      border-color: var(--primary);
    }
    
    .nav-btn.active {
      background: linear-gradient(90deg, var(--primary), var(--secondary));
      color: white;
      border-color: transparent;
    }
    
    .cart-count {
      background: white;
      color: var(--primary);
      width: 24px;
      height: 24px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.8rem;
      font-weight: 700;
    }
    
    /* ===== MAIN CONTENT ===== */
    .container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 2rem;
    }
    
    /* ===== EMPTY STATE ===== */
    .empty-state {
      text-align: center;
      padding: 4rem 2rem;
      animation: fadeIn 0.6s ease;
    }
    
    .empty-icon {
      width: 120px;
      height: 120px;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 2rem;
      color: white;
      font-size: 3rem;
      box-shadow: var(--shadow-lg);
    }
    
    .empty-state h2 {
      font-size: 2.5rem;
      margin-bottom: 1rem;
      background: linear-gradient(90deg, var(--primary), var(--secondary));
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }
    
    .empty-state p {
      color: var(--muted);
      margin-bottom: 2rem;
      max-width: 500px;
      margin-left: auto;
      margin-right: auto;
    }
    
    .primary-btn {
      padding: 1rem 2rem;
      background: linear-gradient(90deg, var(--primary), var(--secondary));
      border: none;
      border-radius: 50px;
      color: white;
      font-weight: 700;
      font-size: 1.1rem;
      display: inline-flex;
      align-items: center;
      gap: 0.75rem;
      text-decoration: none;
      transition: var(--transition);
      box-shadow: var(--shadow-lg);
    }
    
    .primary-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 15px 30px rgba(255, 107, 53, 0.3);
    }
    
    /* ===== CART LAYOUT ===== */
    .cart-layout {
      display: grid;
      grid-template-columns: 1fr 400px;
      gap: 2rem;
      animation: fadeIn 0.6s ease;
    }
    
    @media (max-width: 1024px) {
      .cart-layout {
        grid-template-columns: 1fr;
      }
    }
    
    /* ===== CART ITEMS ===== */
    .cart-items {
      display: flex;
      flex-direction: column;
      gap: 1.5rem;
    }
    
    .cart-item {
      background: var(--glass);
      backdrop-filter: blur(20px);
      border-radius: var(--radius);
      padding: 1.5rem;
      border: 1px solid rgba(255, 255, 255, 0.3);
      box-shadow: var(--shadow-md);
      animation: slideIn 0.4s ease forwards;
      opacity: 0;
      transition: var(--transition);
    }
    
    .cart-item:hover {
      transform: translateY(-5px);
      box-shadow: var(--shadow-lg);
    }
    
    .item-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 1rem;
    }
    
    .item-name {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--dark);
    }
    
    .item-price {
      font-size: 1.5rem;
      font-weight: 800;
      color: var(--primary);
    }
    
    .item-meta {
      display: flex;
      gap: 0.75rem;
      margin-bottom: 1rem;
      flex-wrap: wrap;
    }
    
    .badge {
      padding: 0.5rem 1rem;
      background: white;
      border-radius: 50px;
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--dark);
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      border: 1px solid rgba(0, 0, 0, 0.1);
    }
    
    .badge.highlight {
      background: linear-gradient(90deg, rgba(255, 107, 53, 0.1), rgba(255, 179, 71, 0.1));
      border-color: rgba(255, 107, 53, 0.2);
    }
    
    /* ===== BOOSTERS ===== */
    .boosters-toggle {
      background: none;
      border: none;
      color: var(--primary);
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin: 1rem 0;
      padding: 0.5rem;
      transition: var(--transition);
    }
    
    .boosters-toggle:hover {
      color: var(--secondary);
    }
    
    .boosters-list {
      display: none;
      background: white;
      border-radius: 12px;
      padding: 1rem;
      margin-top: 0.5rem;
      border: 1px solid rgba(0, 0, 0, 0.1);
    }
    
    .boosters-list.active {
      display: block;
      animation: fadeIn 0.3s ease;
    }
    
    .booster-item {
      display: flex;
      justify-content: space-between;
      padding: 0.75rem 0;
      border-bottom: 1px dashed rgba(0, 0, 0, 0.1);
    }
    
    .booster-item:last-child {
      border-bottom: none;
    }
    
    /* ===== QUANTITY CONTROLS ===== */
    .quantity-section {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin: 1.5rem 0;
    }
    
    .qty-btn {
      width: 40px;
      height: 40px;
      border-radius: 12px;
      border: 2px solid rgba(255, 107, 53, 0.2);
      background: white;
      color: var(--dark);
      font-size: 1.2rem;
      cursor: pointer;
      transition: var(--transition);
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .qty-btn:hover {
      border-color: var(--primary);
      background: rgba(255, 107, 53, 0.1);
      transform: scale(1.1);
    }
    
    .qty-input {
      width: 80px;
      padding: 0.75rem;
      border-radius: 12px;
      border: 2px solid rgba(255, 107, 53, 0.2);
      background: white;
      text-align: center;
      font-size: 1.2rem;
      font-weight: 700;
      color: var(--dark);
      transition: var(--transition);
    }
    
    .qty-input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
    }
    
    .line-total {
      text-align: right;
      margin-top: 1rem;
      padding-top: 1rem;
      border-top: 2px dashed rgba(0, 0, 0, 0.1);
    }
    
    .line-total .label {
      color: var(--muted);
      font-weight: 600;
      margin-bottom: 0.5rem;
    }
    
    .line-total .value {
      font-size: 1.8rem;
      font-weight: 800;
      color: var(--primary);
    }
    
    .remove-btn {
      width: 100%;
      padding: 1rem;
      background: rgba(220, 53, 69, 0.1);
      border: 2px solid rgba(220, 53, 69, 0.2);
      border-radius: 12px;
      color: var(--danger);
      font-weight: 700;
      cursor: pointer;
      transition: var(--transition);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      margin-top: 1rem;
    }
    
    .remove-btn:hover {
      background: var(--danger);
      color: white;
      transform: translateY(-2px);
    }
    
    /* ===== ORDER SUMMARY ===== */
    .order-summary {
      position: sticky;
      top: 100px;
      background: var(--glass);
      backdrop-filter: blur(20px);
      border-radius: var(--radius);
      padding: 2rem;
      border: 1px solid rgba(255, 255, 255, 0.3);
      box-shadow: var(--shadow-lg);
      animation: slideIn 0.4s ease 0.2s forwards;
      opacity: 0;
    }
    
    .summary-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
      padding-bottom: 1rem;
      border-bottom: 2px solid rgba(0, 0, 0, 0.1);
    }
    
    .summary-header h3 {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--dark);
    }
    
    .item-count {
      padding: 0.5rem 1rem;
      background: white;
      border-radius: 50px;
      font-weight: 700;
      color: var(--primary);
      border: 2px solid rgba(255, 107, 53, 0.2);
    }
    
    .summary-line {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1rem 0;
      border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }
    
    .summary-line:last-child {
      border-bottom: none;
    }
    
    .summary-label {
      color: var(--muted);
      font-weight: 600;
    }
    
    .summary-value {
      font-weight: 700;
      color: var(--dark);
    }
    
    .summary-total {
      margin-top: 1.5rem;
      padding-top: 1.5rem;
      border-top: 3px solid rgba(255, 107, 53, 0.3);
    }
    
    .total-label {
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--dark);
    }
    
    .total-value {
      font-size: 2.5rem;
      font-weight: 800;
      background: linear-gradient(90deg, var(--primary), var(--secondary));
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      position: relative;
      overflow: hidden;
    }
    
    .total-value::after {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
      animation: shimmer 2s infinite;
    }
    
    .summary-actions {
      margin-top: 2rem;
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }
    
    .checkout-btn {
      padding: 1.25rem;
      background: linear-gradient(90deg, var(--primary), var(--secondary));
      border: none;
      border-radius: 12px;
      color: white;
      font-weight: 700;
      font-size: 1.1rem;
      cursor: pointer;
      transition: var(--transition);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.75rem;
      box-shadow: var(--shadow-lg);
    }
    
    .checkout-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 15px 30px rgba(255, 107, 53, 0.3);
    }
    
    .clear-btn {
      padding: 1rem;
      background: white;
      border: 2px solid rgba(0, 0, 0, 0.1);
      border-radius: 12px;
      color: var(--muted);
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }
    
    .clear-btn:hover {
      border-color: var(--danger);
      color: var(--danger);
      transform: translateY(-2px);
    }
    
    /* ===== LOADING INDICATOR ===== */
    .saving-indicator {
      display: none;
      align-items: center;
      gap: 0.75rem;
      margin-top: 1rem;
      padding: 1rem;
      background: rgba(255, 255, 255, 0.9);
      border-radius: 12px;
      border: 1px solid rgba(255, 107, 53, 0.2);
    }
    
    .saving-indicator.show {
      display: flex;
      animation: fadeIn 0.3s ease;
    }
    
    .saving-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: linear-gradient(90deg, var(--primary), var(--secondary));
      animation: pulse 1s infinite;
    }
    
    /* ===== DELIVERY TRACKER ===== */
    .delivery-tracker {
      margin-top: 2rem;
      padding: 1.5rem;
      background: linear-gradient(135deg, rgba(255, 107, 53, 0.1), rgba(0, 212, 170, 0.1));
      border-radius: var(--radius);
      border: 1px solid rgba(255, 255, 255, 0.3);
      animation: float 6s infinite ease-in-out;
    }
    
    .tracker-header {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1rem;
    }
    
    .tracker-icon {
      width: 60px;
      height: 60px;
      background: linear-gradient(135deg, var(--primary), var(--accent));
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 1.5rem;
      box-shadow: var(--shadow-md);
    }
    
    .tracker-text h4 {
      font-weight: 700;
      color: var(--dark);
      margin-bottom: 0.25rem;
    }
    
    .tracker-text p {
      color: var(--muted);
      font-size: 0.9rem;
    }
    
    /* ===== RESPONSIVE ===== */
    @media (max-width: 768px) {
      .nav-content {
        flex-direction: column;
        text-align: center;
      }
      
      .nav-buttons {
        width: 100%;
        justify-content: center;
      }
      
      .container {
        padding: 1rem;
      }
      
      .cart-item {
        padding: 1rem;
      }
      
      .item-header {
        flex-direction: column;
        gap: 1rem;
      }
      
      .quantity-section {
        justify-content: center;
      }
      
      .order-summary {
        position: static;
      }
    }
    
    @media (max-width: 480px) {
      .nav-btn {
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
      }
      
      .empty-state h2 {
        font-size: 2rem;
      }
      
      .primary-btn {
        padding: 0.75rem 1.5rem;
        font-size: 1rem;
      }
    }
    
    /* ===== UTILITY CLASSES ===== */
    .text-primary { color: var(--primary); }
    .text-success { color: var(--success); }
    .hidden { display: none; }
    .fade-in { animation: fadeIn 0.6s ease; }
    .slide-in { animation: slideIn 0.4s ease; }
    .pulse { animation: pulse 2s infinite; }
  </style>
</head>
<body>
  <!-- Background Elements -->
  <div class="bg-elements">
    <div class="bg-circle circle-1"></div>
    <div class="bg-circle circle-2"></div>
    <div class="bg-circle circle-3"></div>
  </div>
  
  <!-- Navigation -->
  <nav class="nav-container">
    <div class="nav-content">
      <a href="index.php" class="logo">
        <img src="<?php echo h($LOGO_PATH); ?>" alt="THE VIBE" class="logo-img">
        <div class="logo-text">
          <h1>THE VIBE</h1>
          <p>Energy • Protein • Boba</p>
        </div>
      </a>
      
      <div class="nav-buttons">
        <a href="index.php#menuList" class="nav-btn">
          <i class="fas fa-utensils"></i>
          Menu
        </a>
        <a href="tel:5074294152" class="nav-btn">
          <i class="fas fa-phone"></i>
          Call
        </a>
        <a href="cart.php" class="nav-btn active">
          <i class="fas fa-shopping-cart"></i>
          Cart
          <span class="cart-count"><?php echo count($_SESSION["cart"]); ?></span>
        </a>
      </div>
    </div>
  </nav>
  
  <!-- Main Content -->
  <div class="container">
    <?php if (empty($_SESSION["cart"])): ?>
      <div class="empty-state">
        <div class="empty-icon">
          <i class="fas fa-shopping-bag"></i>
        </div>
        <h2>Your Cart is Empty</h2>
        <p>Add delicious drinks from our menu! Customize with sizes and boosters to create your perfect drink.</p>
        <a href="index.php#menuList" class="primary-btn">
          <i class="fas fa-bolt"></i>
          Browse Menu
        </a>
      </div>
    <?php else: ?>
      <div class="cart-layout">
        <!-- Cart Items -->
        <div class="cart-items">
          <?php foreach ($_SESSION["cart"] as $i => $item): 
            $qty = max(1, (int)($item["qty"] ?? 1));
            $base = (float)($item["base_unit_price"] ?? 0);
            $boosters = $item["boosters"] ?? [];
            $boostSum = 0.0;
            foreach ($boosters as $b) $boostSum += (float)($b["booster_price"] ?? 0);
            $unit = $base + $boostSum;
            $line = $unit * $qty;
            $boosterCount = count($boosters);
            ?>
            
            <div class="cart-item" style="animation-delay: <?php echo $i * 0.1; ?>s;"
                 data-index="<?php echo $i; ?>"
                 data-unit="<?php echo $unit; ?>">
              
              <div class="item-header">
                <h3 class="item-name"><?php echo h($item["product_name"] ?? ""); ?></h3>
                <div class="item-price">$<?php echo money($unit); ?></div>
              </div>
              
              <div class="item-meta">
                <span class="badge">
                  <i class="fas fa-glass-water"></i>
                  <?php echo h($item["size_name"] ?? ""); ?>
                </span>
                <?php if ($boosterCount > 0): ?>
                  <span class="badge">
                    <i class="fas fa-plus-circle"></i>
                    <?php echo $boosterCount; ?> booster<?php echo $boosterCount>1?"s":""; ?>
                  </span>
                <?php endif; ?>
                <span class="badge highlight">
                  <i class="fas fa-tag"></i>
                  Base: $<?php echo money($base); ?>
                  <?php if ($boostSum > 0): ?>+ $<?php echo money($boostSum); ?> boosters<?php endif; ?>
                </span>
              </div>
              
              <?php if ($boosterCount > 0): ?>
                <button class="boosters-toggle" data-target="boosters-<?php echo $i; ?>">
                  <i class="fas fa-chevron-down"></i>
                  View <?php echo $boosterCount; ?> booster<?php echo $boosterCount>1?"s":""; ?>
                </button>
                
                <div class="boosters-list" id="boosters-<?php echo $i; ?>">
                  <?php foreach ($boosters as $b): ?>
                    <div class="booster-item">
                      <span>+ <?php echo h($b["booster_name"] ?? ""); ?></span>
                      <span class="text-primary">+$<?php echo money($b["booster_price"] ?? 0); ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              
              <div class="quantity-section">
                <button class="qty-btn decrease" data-index="<?php echo $i; ?>">−</button>
                <input type="number" class="qty-input" value="<?php echo $qty; ?>" min="1" data-index="<?php echo $i; ?>">
                <button class="qty-btn increase" data-index="<?php echo $i; ?>">+</button>
              </div>
              
              <div class="line-total">
                <div class="label">Line Total</div>
                <div class="value js-line-total" data-index="<?php echo $i; ?>">$<?php echo money($line); ?></div>
              </div>
              
              <button class="remove-btn" data-index="<?php echo $i; ?>">
                <i class="fas fa-trash-alt"></i>
                Remove Item
              </button>
            </div>
          <?php endforeach; ?>
          
          <!-- Delivery Tracker -->
          <div class="delivery-tracker">
            <div class="tracker-header">
              <div class="tracker-icon">
                <i class="fas fa-motorcycle"></i>
              </div>
              <div class="tracker-text">
                <h4>Fast Delivery Ready</h4>
                <p>Your order is being prepared</p>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Order Summary -->
        <div class="order-summary">
          <div class="summary-header">
            <h3>Order Summary</h3>
            <span class="item-count" id="cartCount"><?php echo count($_SESSION["cart"]); ?> items</span>
          </div>
          
          <div class="summary-lines">
            <div class="summary-line">
              <span class="summary-label">Subtotal</span>
              <span class="summary-value" id="sumSubtotal">$<?php echo money($subtotal); ?></span>
            </div>
            <div class="summary-line">
              <span class="summary-label">Tax (<?php echo money($TAX_RATE*100); ?>%)</span>
              <span class="summary-value" id="sumTax">$<?php echo money($tax_amount); ?></span>
            </div>
            <div class="summary-line">
              <span class="summary-label">Delivery Fee</span>
              <span class="summary-value" id="sumDelivery">$<?php echo money($delivery_fee); ?></span>
            </div>
            <div class="summary-line">
              <span class="summary-label">Discount</span>
              <span class="summary-value text-success" id="sumDiscount">-$<?php echo money($discount); ?></span>
            </div>
          </div>
          
          <div class="summary-total">
            <div class="summary-line">
              <span class="total-label">Total</span>
              <span class="total-value" id="sumGrand">$<?php echo money($grand_total); ?></span>
            </div>
          </div>
          
          <div class="summary-actions">
            <a href="checkout.php" class="checkout-btn">
              <i class="fas fa-lock"></i>
              Proceed to Checkout
            </a>
            <button class="clear-btn" id="clearCartBtn">
              <i class="fas fa-trash"></i>
              Clear Entire Cart
            </button>
          </div>
          
          <div class="saving-indicator" id="savingIndicator">
            <div class="saving-dot"></div>
            <span>Saving changes...</span>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
  
  <script>
    (function() {
      'use strict';
      
      // Elements
      const savingIndicator = document.getElementById('savingIndicator');
      const cartCount = document.getElementById('cartCount');
      const navCartCount = document.querySelector('.cart-count');
      
      // Format money
      function money(n) {
        return '$' + parseFloat(n || 0).toFixed(2);
      }
      
      // Show saving indicator
      function showSaving() {
        if (savingIndicator) {
          savingIndicator.classList.add('show');
          setTimeout(() => savingIndicator.classList.remove('show'), 800);
        }
      }
      
      // Update UI with new totals
      function updateTotals(data) {
        console.log('Updating totals:', data);
        if (data.subtotal !== undefined) {
          document.getElementById('sumSubtotal').textContent = money(data.subtotal);
          document.getElementById('sumTax').textContent = money(data.tax_amount);
          document.getElementById('sumDelivery').textContent = money(data.delivery_fee);
          document.getElementById('sumDiscount').textContent = '-' + money(data.discount);
          document.getElementById('sumGrand').textContent = money(data.grand_total);
          
          // Animate price updates
          ['sumSubtotal', 'sumTax', 'sumDelivery', 'sumDiscount', 'sumGrand'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
              el.classList.add('pulse');
              setTimeout(() => el.classList.remove('pulse'), 500);
            }
          });
        }
        
        if (data.count !== undefined) {
          const count = data.count;
          if (cartCount) cartCount.textContent = count + ' item' + (count !== 1 ? 's' : '');
          if (navCartCount) navCartCount.textContent = count;
          
          if (count === 0) {
            setTimeout(() => location.reload(), 500);
          }
        }
      }
      
      // AJAX request
      async function ajaxRequest(action, data = {}) {
        try {
          const response = await fetch('cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, ...data })
          });
          return await response.json();
        } catch (error) {
          console.error('AJAX error:', error);
          return { ok: false };
        }
      }
      
      // Update line total for an item
      function updateLineTotal(index, qty) {
        const item = document.querySelector(`.cart-item[data-index="${index}"]`);
        if (!item) return;
        
        const unit = parseFloat(item.dataset.unit) || 0;
        const lineTotalEl = item.querySelector('.js-line-total');
        if (lineTotalEl) {
          lineTotalEl.textContent = money(unit * qty);
          lineTotalEl.classList.add('pulse');
          setTimeout(() => lineTotalEl.classList.remove('pulse'), 300);
        }
      }
      
      // Initialize boosters toggle
      document.querySelectorAll('.boosters-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
          const target = document.getElementById(btn.dataset.target);
          if (target) {
            target.classList.toggle('active');
            const icon = btn.querySelector('i');
            icon.classList.toggle('fa-chevron-down');
            icon.classList.toggle('fa-chevron-up');
          }
        });
      });
      
      // Quantity decrease button
      document.querySelectorAll('.qty-btn.decrease').forEach(btn => {
        btn.addEventListener('click', function() {
          const index = this.dataset.index;
          const input = document.querySelector(`.qty-input[data-index="${index}"]`);
          if (input) {
            let qty = parseInt(input.value) || 1;
            if (qty > 1) {
              qty--;
              input.value = qty;
              updateLineTotal(index, qty);
              setQuantity(index, qty);
            }
          }
        });
      });
      
      // Quantity increase button
      document.querySelectorAll('.qty-btn.increase').forEach(btn => {
        btn.addEventListener('click', function() {
          const index = this.dataset.index;
          const input = document.querySelector(`.qty-input[data-index="${index}"]`);
          if (input) {
            let qty = parseInt(input.value) || 1;
            qty++;
            input.value = qty;
            updateLineTotal(index, qty);
            setQuantity(index, qty);
          }
        });
      });
      
      // Quantity input change
      document.querySelectorAll('.qty-input').forEach(input => {
        let timeout;
        input.addEventListener('input', function() {
          const index = this.dataset.index;
          let qty = parseInt(this.value) || 1;
          if (qty < 1) qty = 1;
          
          updateLineTotal(index, qty);
          
          // Debounce the API call
          clearTimeout(timeout);
          timeout = setTimeout(() => {
            setQuantity(index, qty);
          }, 500);
        });
      });
      
      // Set quantity via AJAX
      async function setQuantity(index, qty) {
        console.log('Setting quantity:', index, qty);
        showSaving();
        const result = await ajaxRequest('set_qty', { index: parseInt(index), qty: parseInt(qty) });
        if (result.ok) {
          updateTotals(result);
        }
      }
      
      // Remove item
      document.querySelectorAll('.remove-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
          const index = this.dataset.index;
          if (!confirm('Remove this item from cart?')) return;
          
          // Animate removal
          const item = document.querySelector(`.cart-item[data-index="${index}"]`);
          if (item) {
            item.style.transform = 'translateX(100%)';
            item.style.opacity = '0';
            item.style.marginBottom = '-100%';
            
            setTimeout(async () => {
              showSaving();
              const result = await ajaxRequest('remove_item', { index: parseInt(index) });
              if (result.ok) {
                item.remove();
                updateTotals(result);
              }
            }, 300);
          }
        });
      });
      
      // Clear cart button
      document.getElementById('clearCartBtn')?.addEventListener('click', async () => {
        if (!confirm('Clear entire cart?')) return;
        
        showSaving();
        const result = await ajaxRequest('clear_cart');
        if (result.ok) {
          // Animate all items out
          document.querySelectorAll('.cart-item').forEach((item, i) => {
            setTimeout(() => {
              item.style.transform = 'translateX(-100%)';
              item.style.opacity = '0';
            }, i * 100);
          });
          
          setTimeout(() => location.reload(), 800);
        }
      });
      
      // Touch gestures for quantity
      document.addEventListener('touchstart', e => {
        if (e.target.classList.contains('qty-input')) {
          e.target.dataset.touchStartX = e.touches[0].clientX;
        }
      }, { passive: true });
      
      document.addEventListener('touchend', e => {
        if (e.target.classList.contains('qty-input')) {
          const input = e.target;
          const startX = parseFloat(input.dataset.touchStartX) || 0;
          const endX = e.changedTouches[0].clientX;
          const diff = endX - startX;
          
          if (Math.abs(diff) > 30) {
            const index = input.dataset.index;
            let qty = parseInt(input.value) || 1;
            if (diff > 0) {
              qty++;
            } else if (diff < 0 && qty > 1) {
              qty--;
            }
            input.value = qty;
            updateLineTotal(index, qty);
            setQuantity(index, qty);
          }
        }
      }, { passive: true });
      
      // iOS 100vh fix
      function setVH() {
        document.documentElement.style.setProperty('--vh', window.innerHeight * 0.01 + 'px');
      }
      
      setVH();
      window.addEventListener('resize', setVH);
      
      // Initialize animations
      document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.cart-item').forEach((item, i) => {
          setTimeout(() => {
            item.style.opacity = '1';
            item.style.transform = 'translateY(0)';
          }, i * 100);
        });
      });
    })();
  </script>
</body>
</html>