<?php
session_start();
include "db.php";
include "cost_lib.php";

if (!isset($_SESSION["cart"])) $_SESSION["cart"] = [];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($x){ return number_format((float)$x, 2); }

// ✅ Load global costs from DB (tax_rate, delivery_fee, discount)
$costs = getCosts($conn);
$TAX_RATE     = (float)$costs["tax_rate"];
$DELIVERY_FEE = (float)$costs["delivery_fee"];
$DISCOUNT     = (float)$costs["discount"];

// Calculate cart totals
$subtotal = 0.0;
foreach ($_SESSION["cart"] as $item) {
    $qty = (int)($item["qty"] ?? 1);
    if ($qty < 1) $qty = 1;

    $base = (float)($item["base_unit_price"] ?? 0);
    $boosters = $item["boosters"] ?? [];
    if (!is_array($boosters)) $boosters = [];

    $boostSum = 0.0;
    foreach ($boosters as $b) $boostSum += (float)($b["booster_price"] ?? 0);

    $unit = $base + $boostSum;
    $subtotal += ($unit * $qty);
}

$delivery_fee = (count($_SESSION["cart"]) > 0) ? $DELIVERY_FEE : 0.0;

// Initialize promo variables
$promo_discount = 0;
$promo_code = '';
$promo_details = null;

// Check for applied promo in session
if (isset($_SESSION['applied_promo'])) {
    $promo_details = $_SESSION['applied_promo'];
    $promo_code = $promo_details['code'] ?? '';
    
    // Calculate promo discount
    if ($subtotal >= ($promo_details['min_order_amount'] ?? 0)) {
        if ($promo_details['discount_type'] === 'percentage') {
            $promo_discount = $subtotal * ($promo_details['discount_amount'] / 100);
        } else {
            $promo_discount = $promo_details['discount_amount'];
        }
        
        // Ensure discount doesn't exceed subtotal
        if ($promo_discount > $subtotal) {
            $promo_discount = $subtotal;
        }
    }
}

// Calculate total discount (global + promo)
$total_discount = $DISCOUNT + $promo_discount;

$totals = computeTotalsFromSubtotal($subtotal, $TAX_RATE, $delivery_fee, $total_discount);
$tax_amount  = $totals["tax_amount"];
$grand_total = $totals["grand_total"];

// Handle promo code application via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply_promo') {
    $code = strtoupper(trim($_POST['promo_code'] ?? ''));
    
    if (empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a promo code']);
        exit;
    }
    
    // Validate promo code
    $stmt = $conn->prepare("
        SELECT * FROM promo_codes 
        WHERE code = ? 
        AND is_active = TRUE 
        AND (valid_until IS NULL OR valid_until > NOW())
        AND (max_uses IS NULL OR uses_count < max_uses)
    ");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();
    $promo = $result->fetch_assoc();
    
    if (!$promo) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired promo code']);
        exit;
    }
    
    // Check minimum order amount
    if ($subtotal < $promo['min_order_amount']) {
        $min_order_formatted = money($promo['min_order_amount']);
        echo json_encode(['success' => false, 'message' => "Minimum order amount of \${$min_order_formatted} required"]);
        exit;
    }
    
    // Calculate discount
    $discount_amount = 0;
    if ($promo['discount_type'] === 'percentage') {
        $discount_amount = $subtotal * ($promo['discount_amount'] / 100);
    } else {
        $discount_amount = $promo['discount_amount'];
    }
    
    // Ensure discount doesn't exceed subtotal
    if ($discount_amount > $subtotal) {
        $discount_amount = $subtotal;
    }
    
    // Store in session
    $_SESSION['applied_promo'] = [
        'id' => $promo['id'],
        'code' => $promo['code'],
        'discount_type' => $promo['discount_type'],
        'discount_amount' => $promo['discount_amount'],
        'calculated_discount' => $discount_amount,
        'min_order_amount' => $promo['min_order_amount'],
        'description' => $promo['description']
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Promo code applied successfully!',
        'code' => $promo['code'],
        'discount' => money($discount_amount),
        'discount_type' => $promo['discount_type'],
        'discount_value' => $promo['discount_amount']
    ]);
    exit;
}

// Handle promo code removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_promo') {
    unset($_SESSION['applied_promo']);
    echo json_encode(['success' => true, 'message' => 'Promo code removed']);
    exit;
}

// Store order data in session for redirect to separate payment processors
$_SESSION['order_data'] = [
    'customer_name' => '',
    'customer_email' => '',
    'customer_phone' => '',
    'delivery_address' => '',
    'subtotal' => $subtotal,
    'tax_amount' => $tax_amount,
    'delivery_fee' => $delivery_fee,
    'total_discount' => $total_discount,
    'promo_discount' => $promo_discount,
    'promo_code' => $promo_code,
    'global_discount' => $DISCOUNT,
    'grand_total' => $grand_total,
    'cart' => $_SESSION["cart"]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="format-detection" content="telephone=no">
  <meta name="mobile-web-app-capable" content="yes">
  <title>Checkout - THE VIBE</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    /* ===== VIBRANT ANIMATION VARIABLES ===== */
    :root {
      --ink: #1f1a17;
      --muted: #6a5f58;
      --brand: #ff6b3d;
      --brand2: #ffb038;
      --brand-light: #ffd8c2;
      --brand-lighter: #fff3e3;
      --accent: #00d4aa;
      --success: #10b981;
      --error: #ef4444;
      --warning: #f59e0b;
      --glass: rgba(255, 255, 255, 0.85);
      --glass-dark: rgba(255, 255, 255, 0.95);
      --radius: 20px;
      --radius-sm: 14px;
      --radius-lg: 28px;
      --shadow-xl: 0 35px 60px -15px rgba(255, 107, 61, 0.25);
      --shadow-lg: 0 20px 40px rgba(255, 107, 61, 0.15);
      --shadow-md: 0 10px 25px rgba(24, 16, 12, 0.1);
      --shadow-sm: 0 4px 6px rgba(0, 0, 0, 0.07);
      --line: rgba(60, 40, 30, .14);
      --vh: 1vh;
    }

    /* ===== DYNAMIC ANIMATIONS ===== */
    @keyframes fluidRipple {
      0%, 100% { transform: translate(-50%, -50%) scale(1); opacity: 0.1; }
      50% { transform: translate(-50%, -50%) scale(1.2); opacity: 0.2; }
    }

    @keyframes cardPop {
      0% { transform: scale(0.9) translateY(20px); opacity: 0; }
      70% { transform: scale(1.02) translateY(-5px); }
      100% { transform: scale(1) translateY(0); opacity: 1; }
    }

    @keyframes slideInLeft {
      from { transform: translateX(-30px); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }

    @keyframes slideInRight {
      from { transform: translateX(30px); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }

    @keyframes floatProgress {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-8px); }
    }

    @keyframes pulseSuccess {
      0%, 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
      50% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
    }

    @keyframes shimmer {
      0% { background-position: -1000px 0; }
      100% { background-position: 1000px 0; }
    }

    @keyframes bounceIn {
      0% { transform: scale(0.3); opacity: 0; }
      50% { transform: scale(1.05); }
      70% { transform: scale(0.9); }
      100% { transform: scale(1); opacity: 1; }
    }

    @keyframes shakeError {
      0%, 100% { transform: translateX(0); }
      10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
      20%, 40%, 60%, 80% { transform: translateX(5px); }
    }

    @keyframes loadingDots {
      0%, 20% { content: "."; }
      40% { content: ".."; }
      60%, 100% { content: "..."; }
    }

    /* ===== BASE STYLES ===== */
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      -webkit-tap-highlight-color: transparent;
    }

    html {
      scroll-behavior: smooth;
      overflow-x: hidden;
    }

    body {
      font-family: 'Inter', system-ui, -apple-system, sans-serif;
      color: var(--ink);
      background: 
        radial-gradient(ellipse at 15% 5%, rgba(255, 107, 61, 0.08), transparent 50%),
        radial-gradient(ellipse at 85% 15%, rgba(255, 176, 56, 0.08), transparent 50%),
        radial-gradient(ellipse at 50% 90%, rgba(255, 216, 194, 0.05), transparent 50%),
        linear-gradient(180deg, #fff3e3 0%, #fff 100%);
      min-height: 100vh;
      min-height: calc(var(--vh, 1vh) * 100);
      overflow-x: hidden;
      line-height: 1.5;
      position: relative;
    }

    /* ===== FLUID BACKGROUND ELEMENTS ===== */
    .checkout-ripple {
      position: fixed;
      width: 400px;
      height: 400px;
      border-radius: 50%;
      background: radial-gradient(circle, var(--brand) 0%, transparent 70%);
      top: 20%;
      left: 5%;
      filter: blur(60px);
      opacity: 0.1;
      z-index: -1;
      animation: fluidRipple 25s ease-in-out infinite;
    }

    .checkout-ripple-2 {
      width: 300px;
      height: 300px;
      top: 70%;
      left: 85%;
      background: radial-gradient(circle, var(--brand2) 0%, transparent 70%);
      animation-delay: -12s;
    }

    .checkout-ripple-3 {
      width: 200px;
      height: 200px;
      top: 40%;
      left: 75%;
      background: radial-gradient(circle, var(--accent) 0%, transparent 70%);
      animation-delay: -6s;
    }

    /* ===== PROGRESS STEPS - ANIMATED ===== */
    .progress-tracker {
      max-width: 800px;
      margin: 30px auto;
      padding: 0 20px;
      position: relative;
    }

    .progress-line {
      position: absolute;
      top: 24px;
      left: 80px;
      right: 80px;
      height: 3px;
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      z-index: 1;
      border-radius: 2px;
      overflow: hidden;
    }

    .progress-fill {
      position: absolute;
      top: 0;
      left: 0;
      height: 100%;
      width: 50%;
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      border-radius: 2px;
      transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .progress-steps {
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: relative;
      z-index: 2;
    }

    .progress-step {
      display: flex;
      flex-direction: column;
      align-items: center;
      position: relative;
    }

    .step-indicator {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      background: white;
      border: 3px solid var(--line);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 900;
      font-size: 18px;
      color: var(--muted);
      margin-bottom: 12px;
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      z-index: 2;
      box-shadow: var(--shadow-sm);
    }

    .progress-step.active .step-indicator {
      background: linear-gradient(135deg, var(--brand), var(--brand2));
      border-color: transparent;
      color: white;
      transform: scale(1.1);
      box-shadow: 0 0 0 10px rgba(255, 107, 61, 0.15);
      animation: floatProgress 2s ease-in-out infinite;
    }

    .progress-step.completed .step-indicator {
      background: var(--success);
      border-color: transparent;
      color: white;
    }

    .step-label {
      font-size: 12px;
      font-weight: 800;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 1px;
      transition: all 0.3s ease;
    }

    .progress-step.active .step-label {
      color: var(--brand);
      font-weight: 900;
    }

    /* ===== CHECKOUT CONTAINER ===== */
    .checkout-container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 0 16px 60px;
      animation: fadeIn 0.8s ease;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* ===== HEADER ===== */
    .checkout-header {
      text-align: center;
      padding: 20px 0 40px;
      position: relative;
    }

    .checkout-title {
      font-size: 3rem;
      font-weight: 950;
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      margin-bottom: 12px;
      letter-spacing: -0.5px;
      position: relative;
      display: inline-block;
    }

    .checkout-title::after {
      content: '';
      position: absolute;
      bottom: -12px;
      left: 50%;
      transform: translateX(-50%);
      width: 100px;
      height: 4px;
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      border-radius: 2px;
    }

    .checkout-subtitle {
      color: var(--muted);
      font-weight: 600;
      font-size: 1.1rem;
      max-width: 600px;
      margin: 24px auto 0;
      line-height: 1.6;
    }

    /* ===== CHECKOUT GRID ===== */
    .checkout-grid {
      display: grid;
      grid-template-columns: 1fr 1.2fr;
      gap: 30px;
      align-items: start;
    }

    @media (max-width: 1024px) {
      .checkout-grid {
        grid-template-columns: 1fr;
        gap: 24px;
      }
    }

    /* ===== ORDER SUMMARY CARD ===== */
    .order-summary {
      background: var(--glass-dark);
      backdrop-filter: blur(30px);
      border-radius: var(--radius-lg);
      border: 2px solid rgba(255, 255, 255, 0.9);
      box-shadow: var(--shadow-xl);
      padding: 30px;
      position: sticky;
      top: 100px;
      animation: slideInLeft 0.6s cubic-bezier(0.4, 0, 0.2, 1);
      transform-origin: left center;
    }

    @media (max-width: 1024px) {
      .order-summary {
        position: static;
      }
    }

    .summary-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
      padding-bottom: 20px;
      border-bottom: 2px solid rgba(255, 255, 255, 0.6);
    }

    .summary-title {
      font-size: 1.8rem;
      font-weight: 900;
      color: var(--ink);
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .secure-badge {
      padding: 10px 20px;
      background: linear-gradient(90deg, rgba(255, 107, 61, 0.1), rgba(255, 176, 56, 0.1));
      border-radius: 50px;
      font-size: 13px;
      font-weight: 800;
      color: var(--brand);
      display: flex;
      align-items: center;
      gap: 8px;
      border: 2px solid rgba(255, 255, 255, 0.9);
      animation: pulseSuccess 2s infinite;
    }

    /* ===== CART ITEMS PREVIEW ===== */
    .cart-preview {
      margin-bottom: 25px;
      max-height: 200px;
      overflow-y: auto;
      padding-right: 10px;
    }

    .cart-preview::-webkit-scrollbar {
      width: 6px;
    }

    .cart-preview::-webkit-scrollbar-track {
      background: rgba(106, 95, 88, 0.1);
      border-radius: 10px;
    }

    .cart-preview::-webkit-scrollbar-thumb {
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      border-radius: 10px;
    }

    .cart-item-preview {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 15px 0;
      border-bottom: 1px dashed rgba(106, 95, 88, 0.2);
      transition: all 0.3s ease;
    }

    .cart-item-preview:hover {
      transform: translateX(5px);
      padding-left: 10px;
      background: rgba(255, 255, 255, 0.5);
      border-radius: var(--radius-sm);
    }

    .preview-name {
      font-weight: 700;
      color: var(--ink);
      font-size: 15px;
      flex: 1;
    }

    .preview-qty {
      color: var(--brand);
      font-weight: 800;
      font-size: 12px;
      background: rgba(255, 107, 61, 0.1);
      padding: 4px 10px;
      border-radius: 12px;
      margin-left: 10px;
    }

    .preview-price {
      font-weight: 800;
      color: var(--ink);
      font-size: 15px;
    }

    /* ===== PRICE BREAKDOWN ===== */
    .price-breakdown {
      margin: 25px 0;
    }

    .price-line {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px 0;
      border-bottom: 1px solid rgba(106, 95, 88, 0.1);
      transition: all 0.3s ease;
    }

    .price-line:hover {
      transform: translateX(3px);
      border-color: var(--brand-light);
    }

    .price-label {
      color: var(--muted);
      font-weight: 600;
      font-size: 14px;
    }

    .price-value {
      font-weight: 700;
      color: var(--ink);
      font-size: 14px;
    }

    .price-discount {
      color: var(--success) !important;
      font-weight: 800 !important;
    }

    .price-total {
      margin-top: 20px;
      padding-top: 20px;
      border-top: 3px solid rgba(106, 95, 88, 0.2);
    }

    .total-label {
      font-size: 1.3rem;
      font-weight: 900;
      color: var(--ink);
    }

    .total-value {
      font-size: 2.2rem;
      font-weight: 950;
      background: linear-gradient(90deg, var(--brand), var(--brand2));
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
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
      animation: shimmer 3s infinite;
    }

    /* ===== PROMO CODE SECTION ===== */
    .promo-section {
      margin: 30px 0;
      padding: 25px;
      background: linear-gradient(135deg, rgba(255, 107, 61, 0.05), rgba(255, 176, 56, 0.05));
      border-radius: var(--radius);
      border: 2px solid rgba(255, 255, 255, 0.8);
      animation: cardPop 0.5s ease 0.2s both;
    }

    .promo-header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 20px;
    }

    .promo-icon {
      width: 40px;
      height: 40px;
      border-radius: 12px;
      background: linear-gradient(135deg, var(--brand), var(--brand2));
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 18px;
    }

    .promo-title {
      font-size: 1.1rem;
      font-weight: 800;
      color: var(--ink);
    }

    .promo-form {
      display: flex;
      gap: 12px;
      margin-bottom: 15px;
    }

    .promo-input {
      flex: 1;
      padding: 16px 20px;
      border-radius: var(--radius-sm);
      border: 2px solid rgba(255, 255, 255, 0.9);
      background: white;
      font-size: 15px;
      font-weight: 600;
      color: var(--ink);
      transition: all 0.3s ease;
    }

    .promo-input:focus {
      outline: none;
      border-color: var(--brand);
      box-shadow: 0 0 0 4px rgba(255, 107, 61, 0.2);
      transform: translateY(-2px);
    }

    .promo-btn {
      padding: 16px 30px;
      background: var(--ink);
      color: white;
      border: none;
      border-radius: var(--radius-sm);
      font-weight: 800;
      font-size: 14px;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      display: flex;
      align-items: center;
      gap: 8px;
      white-space: nowrap;
    }

    .promo-btn:hover {
      background: #333;
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    .promo-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none !important;
    }

    .promo-success {
      background: rgba(16, 185, 129, 0.1);
      border: 2px solid rgba(16, 185, 129, 0.3);
      padding: 20px;
      border-radius: var(--radius-sm);
      animation: bounceIn 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }

    .promo-success-content {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 15px;
    }

    .promo-success-left {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .promo-success-icon {
      color: var(--success);
      font-size: 22px;
    }

    .promo-success-text {
      font-size: 14px;
      font-weight: 600;
    }

    .promo-success-code {
      font-weight: 800;
      color: var(--ink);
      background: rgba(16, 185, 129, 0.2);
      padding: 4px 12px;
      border-radius: 20px;
      margin: 0 5px;
    }

    .promo-success-remove {
      background: none;
      border: 2px solid rgba(239, 68, 68, 0.3);
      color: var(--error);
      padding: 10px 20px;
      border-radius: var(--radius-sm);
      font-weight: 800;
      font-size: 13px;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .promo-success-remove:hover {
      background: rgba(239, 68, 68, 0.1);
      transform: translateY(-2px);
    }

    .promo-error {
      background: rgba(239, 68, 68, 0.1);
      border: 2px solid rgba(239, 68, 68, 0.3);
      padding: 15px;
      border-radius: var(--radius-sm);
      margin-top: 15px;
      animation: shakeError 0.5s ease;
    }

    .promo-error-text {
      color: var(--error);
      font-size: 14px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    /* ===== CHECKOUT FORM ===== */
    .checkout-form-container {
      background: var(--glass-dark);
      backdrop-filter: blur(30px);
      border-radius: var(--radius-lg);
      border: 2px solid rgba(255, 255, 255, 0.9);
      box-shadow: var(--shadow-xl);
      overflow: hidden;
      animation: slideInRight 0.6s cubic-bezier(0.4, 0, 0.2, 1) 0.1s both;
      transform-origin: right center;
    }

    .form-section {
      padding: 30px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.6);
      transition: all 0.3s ease;
    }

    .form-section:hover {
      background: rgba(255, 255, 255, 0.3);
    }

    .form-section:last-child {
      border-bottom: none;
    }

    .section-header {
      display: flex;
      align-items: center;
      gap: 15px;
      margin-bottom: 30px;
    }

    .section-icon {
      width: 50px;
      height: 50px;
      border-radius: 14px;
      background: linear-gradient(135deg, var(--brand), var(--brand2));
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 20px;
      box-shadow: var(--shadow-md);
    }

    .section-title {
      font-size: 1.5rem;
      font-weight: 900;
      color: var(--ink);
    }

    /* ===== FORM STYLES ===== */
    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }

    @media (max-width: 768px) {
      .form-grid {
        grid-template-columns: 1fr;
      }
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-label {
      display: block;
      font-size: 13px;
      font-weight: 800;
      color: var(--ink);
      margin-bottom: 10px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      transition: all 0.3s ease;
    }

    .form-label:hover {
      color: var(--brand);
    }

    .form-input {
      width: 100%;
      padding: 18px 22px;
      border-radius: var(--radius-sm);
      border: 2px solid rgba(255, 255, 255, 0.9);
      background: rgba(255, 255, 255, 0.9);
      font-size: 16px;
      font-weight: 600;
      color: var(--ink);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .form-input:focus {
      outline: none;
      border-color: var(--brand);
      background: white;
      box-shadow: 0 0 0 6px rgba(255, 107, 61, 0.15);
      transform: translateY(-2px);
    }

    .form-input::placeholder {
      color: rgba(106, 95, 88, 0.5);
    }

    textarea.form-input {
      min-height: 120px;
      resize: vertical;
    }

    /* ===== PAYMENT OPTIONS - SINGLE OPTION ===== */
    .payment-options {
      margin: 20px 0;
    }

    .payment-option {
      display: inline-block;
      cursor: pointer;
    }

    .payment-option input {
      display: none;
    }

    .payment-card {
      padding: 30px;
      border-radius: var(--radius);
      background: rgba(255, 255, 255, 0.7);
      border: 3px solid var(--brand);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      text-align: center;
      max-width: 400px;
      margin: 0 auto;
      box-shadow: var(--shadow-lg);
    }

    .payment-card:hover {
      transform: translateY(-5px);
      box-shadow: var(--shadow-xl);
    }

    .payment-icon {
      font-size: 48px;
      margin-bottom: 20px;
      color: var(--brand);
      height: 60px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .payment-name {
      font-weight: 900;
      font-size: 1.8rem;
      margin-bottom: 10px;
      color: var(--ink);
    }

    .payment-desc {
      font-size: 16px;
      color: var(--muted);
      font-weight: 600;
      margin-bottom: 15px;
    }

    .payment-note {
      font-size: 14px;
      color: var(--success);
      font-weight: 700;
      background: rgba(16, 185, 129, 0.1);
      padding: 10px 15px;
      border-radius: var(--radius-sm);
      display: inline-block;
    }

    /* ===== ACTION BUTTONS ===== */
    .checkout-actions {
      display: flex;
      gap: 15px;
      margin: 30px 0 20px;
    }

    @media (max-width: 640px) {
      .checkout-actions {
        flex-direction: column;
      }
    }

    .btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      padding: 20px 32px;
      border-radius: var(--radius-sm);
      font-weight: 800;
      font-size: 16px;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      text-decoration: none;
      text-align: center;
      flex: 1;
      border: none;
    }

    .btn-primary {
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      color: white;
      box-shadow: 0 8px 25px rgba(255, 107, 61, 0.3);
    }

    .btn-primary:hover:not(:disabled) {
      transform: translateY(-3px);
      box-shadow: 0 15px 35px rgba(255, 107, 61, 0.4);
    }

    .btn-primary:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none !important;
      box-shadow: none !important;
    }

    .btn-secondary {
      background: rgba(255, 255, 255, 0.9);
      color: var(--ink);
      border: 2px solid rgba(255, 255, 255, 0.9);
    }

    .btn-secondary:hover {
      background: white;
      border-color: var(--brand-light);
      transform: translateY(-3px);
    }

    /* ===== DELIVERY INFO ===== */
    .delivery-tracker {
      margin-top: 30px;
      padding: 25px;
      background: linear-gradient(135deg, rgba(255, 107, 61, 0.08), rgba(0, 212, 170, 0.08));
      border-radius: var(--radius);
      border: 2px solid rgba(255, 255, 255, 0.9);
      animation: floatProgress 6s ease-in-out infinite;
    }

    .tracker-header {
      display: flex;
      align-items: center;
      gap: 20px;
      margin-bottom: 15px;
    }

    .tracker-icon {
      width: 70px;
      height: 70px;
      background: linear-gradient(135deg, var(--brand), var(--accent));
      border-radius: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 28px;
      box-shadow: var(--shadow-lg);
    }

    .tracker-info h4 {
      font-weight: 900;
      font-size: 18px;
      margin-bottom: 5px;
      color: var(--ink);
    }

    .tracker-info p {
      color: var(--muted);
      font-size: 14px;
      font-weight: 600;
    }

    /* ===== SECURITY FOOTER ===== */
    .security-footer {
      text-align: center;
      padding: 20px;
      margin-top: 20px;
      border-top: 1px solid rgba(255, 255, 255, 0.6);
    }

    .security-text {
      font-size: 13px;
      color: var(--muted);
      font-weight: 600;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
    }

    /* ===== LOADING STATES ===== */
    .loading::after {
      content: "";
      animation: loadingDots 1.5s infinite;
    }

    .spinner {
      animation: spin 1s linear infinite;
    }

    /* ===== MOBILE OPTIMIZATIONS ===== */
    @media (max-width: 768px) {
      .checkout-ripple,
      .checkout-ripple-2,
      .checkout-ripple-3 {
        display: none;
      }
      
      .checkout-title {
        font-size: 2.2rem;
      }
      
      .checkout-subtitle {
        font-size: 1rem;
        padding: 0 10px;
      }
      
      .progress-tracker {
        padding: 0 10px;
        margin: 20px auto;
      }
      
      .progress-line {
        left: 60px;
        right: 60px;
      }
      
      .step-indicator {
        width: 40px;
        height: 40px;
        font-size: 16px;
      }
      
      .step-label {
        font-size: 10px;
      }
      
      .order-summary,
      .checkout-form-container {
        padding: 20px;
        border-radius: 20px;
      }
      
      .summary-title {
        font-size: 1.5rem;
      }
      
      .section-title {
        font-size: 1.3rem;
      }
      
      .form-section {
        padding: 20px;
      }
      
      .payment-card {
        padding: 20px;
      }
      
      .payment-name {
        font-size: 1.5rem;
      }
      
      .btn {
        padding: 18px 20px;
        font-size: 15px;
      }
      
      .tracker-icon {
        width: 60px;
        height: 60px;
        font-size: 24px;
      }
    }

    @media (max-width: 480px) {
      .checkout-container {
        padding: 0 12px 40px;
      }
      
      .checkout-title {
        font-size: 1.8rem;
      }
      
      .progress-line {
        display: none;
      }
      
      .progress-steps {
        justify-content: center;
        gap: 30px;
      }
      
      .promo-form {
        flex-direction: column;
      }
      
      .promo-btn {
        width: 100%;
        justify-content: center;
      }
      
      .total-value {
        font-size: 1.8rem;
      }
      
      .checkout-actions {
        flex-direction: column;
      }
      
      .payment-card {
        padding: 15px;
      }
      
      .payment-icon {
        font-size: 36px;
        margin-bottom: 15px;
      }
      
      .payment-name {
        font-size: 1.3rem;
      }
    }

    /* ===== TOUCH OPTIMIZATIONS ===== */
    @media (hover: none) and (pointer: coarse) {
      .cart-item-preview:hover,
      .price-line:hover,
      .payment-card:hover,
      .btn-primary:hover,
      .btn-secondary:hover,
      .form-input:focus,
      .promo-input:focus {
        transform: none;
      }
      
      .cart-item-preview:active,
      .price-line:active,
      .payment-card:active,
      .btn-primary:active,
      .btn-secondary:active {
        transform: scale(0.98);
      }
    }

    /* ===== UTILITY CLASSES ===== */
    .fade-in { animation: fadeIn 0.6s ease; }
    .slide-in-left { animation: slideInLeft 0.5s ease; }
    .slide-in-right { animation: slideInRight 0.5s ease; }
    .pulse { animation: pulseSuccess 2s infinite; }
    .shake { animation: shakeError 0.5s ease; }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
  </style>
</head>
<body>

<!-- Animated Background Elements -->
<div class="checkout-ripple"></div>
<div class="checkout-ripple checkout-ripple-2"></div>
<div class="checkout-ripple checkout-ripple-3"></div>

<!-- Animated Progress Tracker -->
<div class="progress-tracker">
  <div class="progress-line">
    <div class="progress-fill" style="width: 50%;"></div>
  </div>
  
  <div class="progress-steps">
    <div class="progress-step completed">
      <div class="step-indicator">
        <i class="fas fa-check"></i>
      </div>
      <div class="step-label">Cart</div>
    </div>
    
    <div class="progress-step active">
      <div class="step-indicator">2</div>
      <div class="step-label">Checkout</div>
    </div>
    
    <div class="progress-step">
      <div class="step-indicator">3</div>
      <div class="step-label">Payment</div>
    </div>
    
    <div class="progress-step">
      <div class="step-indicator">4</div>
      <div class="step-label">Confirm</div>
    </div>
  </div>
</div>

<div class="checkout-container">
  <!-- Header -->
  <div class="checkout-header">
    <h1 class="checkout-title">Complete Your Order</h1>
    <p class="checkout-subtitle">
      <i class="fas fa-bolt" style="color: var(--brand); margin-right: 8px;"></i>
      Your energy boost is just a few clicks away! Review and secure your order.
    </p>
  </div>

  <div class="checkout-grid">
    <!-- LEFT: Order Summary -->
    <div class="order-summary">
      <div class="summary-header">
        <h2 class="summary-title">
          <i class="fas fa-receipt"></i>
          Order Summary
        </h2>
        <div class="secure-badge">
          <i class="fas fa-lock"></i>
          Secure Checkout
        </div>
      </div>

      <!-- Cart Items Preview -->
      <div class="cart-preview">
        <h3 style="font-size: 15px; font-weight: 800; margin-bottom: 20px; color: var(--ink); display: flex; align-items: center; gap: 10px;">
          <i class="fas fa-shopping-bag" style="color: var(--brand);"></i>
          Your Items
          <span style="margin-left: auto; font-size: 12px; color: var(--muted);">
            <?php echo count($_SESSION["cart"]); ?> items
          </span>
        </h3>
        
        <?php if(count($_SESSION["cart"]) > 0): ?>
          <?php foreach ($_SESSION["cart"] as $i => $it): ?>
            <?php
              $qty  = (int)($it["qty"] ?? 1);
              if ($qty < 1) $qty = 1;

              $base = (float)($it["base_unit_price"] ?? 0);
              $boostSum = 0.0;
              $boosters = $it["boosters"] ?? [];
              if (!is_array($boosters)) $boosters = [];
              foreach ($boosters as $b) $boostSum += (float)($b["booster_price"] ?? 0);
              $unit = $base + $boostSum;
              $line = $unit * $qty;
            ?>
            <div class="cart-item-preview">
              <div class="preview-name">
                <?php echo h($it["product_name"] ?? "Item"); ?>
                <span class="preview-qty">×<?php echo $qty; ?></span>
              </div>
              <div class="preview-price">$<?php echo money($line); ?></div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="cart-item-preview">
            <div class="preview-name" style="color: var(--muted); font-style: italic;">
              Your cart is empty
            </div>
            <div class="preview-price">$0.00</div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Price Breakdown -->
      <div class="price-breakdown">
        <div class="price-line">
          <span class="price-label">Subtotal</span>
          <span class="price-value" id="summary-subtotal">$<?php echo money($subtotal); ?></span>
        </div>

        <div class="price-line">
          <span class="price-label">Tax (<?php echo money($TAX_RATE*100); ?>%)</span>
          <span class="price-value" id="summary-tax">$<?php echo money($tax_amount); ?></span>
        </div>

        <div class="price-line">
          <span class="price-label">Delivery Fee</span>
          <span class="price-value" id="summary-delivery">
            <?php if($delivery_fee > 0): ?>
              $<?php echo money($delivery_fee); ?>
            <?php else: ?>
              <span style="color: var(--success); font-weight: 800;">FREE</span>
            <?php endif; ?>
          </span>
        </div>

        <div class="price-line">
          <span class="price-label">Global Discount</span>
          <span class="price-value price-discount">-$<?php echo money($DISCOUNT); ?></span>
        </div>

        <?php if($promo_discount > 0): ?>
          <div class="price-line">
            <span class="price-label">
              Promo Discount
              <?php if($promo_details): ?>
                <br><small style="font-size: 11px; color: var(--brand); font-weight: 700;">(<?php echo h($promo_details['code']); ?>)</small>
              <?php endif; ?>
            </span>
            <span class="price-value price-discount">-$<?php echo money($promo_discount); ?></span>
          </div>
        <?php endif; ?>

        <div class="price-line price-total">
          <span class="total-label">Total Amount</span>
          <span class="total-value" id="summary-total">$<?php echo money($grand_total); ?></span>
        </div>
      </div>

      <!-- Promo Code Section -->
      <div class="promo-section">
        <div class="promo-header">
          <div class="promo-icon">
            <i class="fas fa-tag"></i>
          </div>
          <h3 class="promo-title">Apply Promo Code</h3>
        </div>
        
        <?php if($promo_discount > 0 && $promo_details): ?>
          <!-- Show applied promo -->
          <div class="promo-success">
            <div class="promo-success-content">
              <div class="promo-success-left">
                <div class="promo-success-icon">
                  <i class="fas fa-check-circle"></i>
                </div>
                <div class="promo-success-text">
                  Code <span class="promo-success-code"><?php echo h($promo_details['code']); ?></span> applied!
                  <span style="color: var(--success); font-weight: 800; margin-left: 5px;">
                    -$<?php echo money($promo_discount); ?>
                  </span>
                </div>
              </div>
              <button type="button" class="promo-success-remove" id="remove-discount-btn">
                <i class="fas fa-times"></i>
                Remove
              </button>
            </div>
          </div>
        <?php else: ?>
          <!-- Show promo form -->
          <form id="discount-form" class="promo-form">
            <input type="text" class="promo-input" placeholder="ENTER PROMO CODE" id="discount-code" 
                   value="<?php echo h($promo_code); ?>" style="text-transform: uppercase;">
            <button type="button" class="promo-btn" id="apply-discount-btn">
              <i class="fas fa-check"></i>
              Apply Code
            </button>
          </form>
          <div id="discount-message"></div>
        <?php endif; ?>
      </div>

      <!-- Quick Cart Stats -->
      <div style="margin-top: 20px; text-align: center;">
        <div style="display: inline-flex; align-items: center; gap: 10px; padding: 12px 24px; background: rgba(255, 255, 255, 0.8); border-radius: 50px; border: 2px solid rgba(255, 255, 255, 0.9);">
          <i class="fas fa-shopping-cart" style="color: var(--brand); font-size: 18px;"></i>
          <div style="text-align: left;">
            <div style="font-size: 13px; font-weight: 700; color: var(--muted);">Total Items</div>
            <div style="font-size: 16px; font-weight: 900; color: var(--ink);">
              <?php echo count($_SESSION["cart"]); ?> items • $<?php echo money($subtotal); ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- RIGHT: Checkout Form -->
    <div class="checkout-form-container">
      <!-- Customer Information -->
      <div class="form-section">
        <div class="section-header">
          <div class="section-icon">
            <i class="fas fa-user-circle"></i>
          </div>
          <h2 class="section-title">Your Information</h2>
        </div>

        <form method="POST" action="place_order.php" autocomplete="on" id="checkout-form">
          <!-- Hidden fields for order data -->
          <input type="hidden" name="subtotal" value="<?php echo $subtotal; ?>">
          <input type="hidden" name="tax_amount" value="<?php echo $tax_amount; ?>">
          <input type="hidden" name="delivery_fee" value="<?php echo $delivery_fee; ?>">
          <input type="hidden" name="total_discount" value="<?php echo $total_discount; ?>">
          <input type="hidden" name="promo_discount" value="<?php echo $promo_discount; ?>">
          <input type="hidden" name="promo_code" value="<?php echo h($promo_code); ?>">
          <input type="hidden" name="grand_total" value="<?php echo $grand_total; ?>">
          
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label" for="customer_name">
                <i class="fas fa-user"></i> Full Name *
              </label>
              <input type="text" class="form-input" id="customer_name" name="customer_name" 
                     placeholder="Your full name" required>
            </div>
            
            <div class="form-group">
              <label class="form-label" for="customer_phone">
                <i class="fas fa-phone"></i> Phone Number *
              </label>
              <input type="tel" class="form-input" id="customer_phone" name="customer_phone"
                     placeholder="(507) XXX-XXXX" required>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="customer_email">
              <i class="fas fa-envelope"></i> Email Address *
            </label>
            <input type="email" class="form-input" id="customer_email" name="customer_email"
                   placeholder="your.email@example.com" required>
            <div style="margin-top: 8px; display: flex; align-items: center; gap: 8px;">
              <i class="fas fa-info-circle" style="color: var(--brand); font-size: 13px;"></i>
              <small style="color: var(--muted); font-size: 12px; font-weight: 600;">
                Your receipt will be sent here
              </small>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="delivery_address">
              <i class="fas fa-map-marker-alt"></i> Delivery Address *
            </label>
            <textarea class="form-input" id="delivery_address" name="delivery_address" 
                      placeholder="Enter your complete delivery address including apartment number if any" 
                      rows="3" required></textarea>
          </div>
      </div>

      <!-- Payment Method - Only Cash on Delivery -->
      <div class="form-section">
        <div class="section-header">
          <div class="section-icon">
            <i class="fas fa-money-bill-wave"></i>
          </div>
          <h2 class="section-title">Payment Method</h2>
        </div>

        <div class="payment-options">
          <!-- Cash on Delivery - Only Option -->
          <label class="payment-option">
            <input type="radio" name="payment_method" value="Cash on Delivery" checked style="display: none;">
            <div class="payment-card">
              <div class="payment-icon">
                <i class="fas fa-money-bill-wave"></i>
              </div>
              <div class="payment-name">Cash on Delivery</div>
              <div class="payment-desc">Pay with cash when you receive your order</div>
              <div class="payment-note">
                <i class="fas fa-check-circle"></i> No payment required now
              </div>
            </div>
          </label>
        </div>
        
        <!-- Payment Notice -->
        <div style="text-align: center; margin-top: 25px; padding: 20px; background: rgba(255, 255, 255, 0.9); border-radius: var(--radius); border: 2px dashed rgba(255, 107, 61, 0.3);">
          <div style="display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 10px;">
            <i class="fas fa-info-circle" style="color: var(--brand); font-size: 20px;"></i>
            <h3 style="font-size: 16px; font-weight: 800; color: var(--ink);">Payment Instructions</h3>
          </div>
          <p style="color: var(--muted); font-size: 14px; font-weight: 600; line-height: 1.6;">
            Your order will be prepared immediately. Please have the exact amount ready when our delivery agent arrives.
            You'll receive a confirmation call shortly after placing your order.
          </p>
        </div>
      </div>

      <!-- Action Buttons & Delivery Info -->
      <div class="form-section" style="border-bottom: none;">
        <div class="checkout-actions">
          <a href="cart.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Back to Cart
          </a>
          <button type="submit" class="btn btn-primary" form="checkout-form" id="place-order-btn">
            <i class="fas fa-check-circle"></i>
            Confirm Order
          </button>
        </div>
        
        <div class="delivery-tracker">
          <div class="tracker-header">
            <div class="tracker-icon">
              <i class="fas fa-shipping-fast"></i>
            </div>
            <div class="tracker-info">
              <h4>Fast Delivery Guaranteed</h4>
              <p>Your order will be delivered in 20-30 minutes after confirmation</p>
            </div>
          </div>
        </div>
        
        <div class="security-footer">
          <p class="security-text">
            <i class="fas fa-shield-alt"></i>
            All transactions are 100% secure and encrypted
          </p>
        </div>
      </div>
      </form>
    </div>
  </div>
</div>

<script>
  // ===== FORM VALIDATION & SUBMISSION =====
  document.getElementById('checkout-form').addEventListener('submit', function(e) {
    // Basic validation
    const name = document.getElementById('customer_name').value.trim();
    const phone = document.getElementById('customer_phone').value.trim();
    const email = document.getElementById('customer_email').value.trim();
    const address = document.getElementById('delivery_address').value.trim();
    
    // Validate required fields with animation
    const requiredFields = [
      { id: 'customer_name', value: name, message: 'Please enter your full name' },
      { id: 'customer_phone', value: phone, message: 'Please enter your phone number' },
      { id: 'customer_email', value: email, message: 'Please enter your email address' },
      { id: 'delivery_address', value: address, message: 'Please enter your delivery address' }
    ];
    
    let hasError = false;
    requiredFields.forEach(field => {
      const input = document.getElementById(field.id);
      if (!field.value) {
        input.style.borderColor = 'var(--error)';
        input.style.animation = 'shakeError 0.5s ease';
        setTimeout(() => {
          input.style.animation = '';
        }, 500);
        
        if (!hasError) {
          input.focus();
          hasError = true;
        }
      } else {
        input.style.borderColor = '';
      }
    });
    
    if (hasError) {
      e.preventDefault();
      return false;
    }
    
    // Validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      e.preventDefault();
      const emailInput = document.getElementById('customer_email');
      emailInput.style.borderColor = 'var(--error)';
      emailInput.style.animation = 'shakeError 0.5s ease';
      setTimeout(() => {
        emailInput.style.animation = '';
      }, 500);
      emailInput.focus();
      return false;
    }
    
    // For cash on delivery, show processing animation
    const submitBtn = document.getElementById('place-order-btn');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin spinner"></i> Placing Order...';
    submitBtn.disabled = true;
    
    // Add a small delay to show processing state
    setTimeout(() => {
      // Let the form submit normally
    }, 500);
  });

  // ===== PROMO CODE SYSTEM =====
  document.addEventListener('DOMContentLoaded', function() {
    const applyDiscountBtn = document.getElementById('apply-discount-btn');
    const removeDiscountBtn = document.getElementById('remove-discount-btn');
    const discountMessage = document.getElementById('discount-message');
    
    // Apply discount
    if (applyDiscountBtn) {
      applyDiscountBtn.addEventListener('click', function() {
        const discountCode = document.getElementById('discount-code').value.trim().toUpperCase();
        
        if (!discountCode) {
          showDiscountMessage('Please enter a promo code', 'error');
          document.getElementById('discount-code').focus();
          return;
        }
        
        applyDiscountBtn.disabled = true;
        const originalText = applyDiscountBtn.innerHTML;
        applyDiscountBtn.innerHTML = '<i class="fas fa-spinner fa-spin spinner"></i> Applying...';
        
        // Send AJAX request
        const formData = new FormData();
        formData.append('action', 'apply_promo');
        formData.append('promo_code', discountCode);
        
        fetch('', {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showDiscountMessage(data.message, 'success');
            
            // Animate success
            applyDiscountBtn.innerHTML = '<i class="fas fa-check"></i> Applied!';
            applyDiscountBtn.style.background = 'linear-gradient(90deg, #10b981, #059669)';
            
            // Reload page with animation
            setTimeout(() => {
              document.body.style.opacity = '0.7';
              document.body.style.transition = 'opacity 0.3s ease';
              setTimeout(() => {
                window.location.reload();
              }, 300);
            }, 1000);
          } else {
            showDiscountMessage(data.message, 'error');
            applyDiscountBtn.disabled = false;
            applyDiscountBtn.innerHTML = originalText;
            
            // Animate error
            document.getElementById('discount-code').style.animation = 'shakeError 0.5s ease';
            setTimeout(() => {
              document.getElementById('discount-code').style.animation = '';
            }, 500);
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showDiscountMessage('Network error. Please try again.', 'error');
          applyDiscountBtn.disabled = false;
          applyDiscountBtn.innerHTML = originalText;
        });
      });
      
      // Enter key support
      document.getElementById('discount-code').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          applyDiscountBtn.click();
        }
      });
    }
    
    // Remove discount
    if (removeDiscountBtn) {
      removeDiscountBtn.addEventListener('click', function() {
        if (!confirm('Remove promo code from your order?')) return;
        
        removeDiscountBtn.disabled = true;
        removeDiscountBtn.innerHTML = '<i class="fas fa-spinner fa-spin spinner"></i> Removing...';
        
        const formData = new FormData();
        formData.append('action', 'remove_promo');
        
        fetch('', {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Animate removal
            removeDiscountBtn.innerHTML = '<i class="fas fa-check"></i> Removed!';
            removeDiscountBtn.style.background = 'linear-gradient(90deg, #10b981, #059669)';
            
            // Reload page
            setTimeout(() => {
              window.location.reload();
            }, 500);
          } else {
            alert('Error removing promo code');
            removeDiscountBtn.disabled = false;
            removeDiscountBtn.innerHTML = 'Remove';
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Network error. Please try again.');
          removeDiscountBtn.disabled = false;
          removeDiscountBtn.innerHTML = 'Remove';
        });
      });
    }
    
    // Auto-format promo code
    const discountCodeInput = document.getElementById('discount-code');
    if (discountCodeInput) {
      discountCodeInput.addEventListener('input', function(e) {
        this.value = this.value.toUpperCase();
      });
    }
    
    // Discount message display
    function showDiscountMessage(message, type) {
      discountMessage.innerHTML = '';
      
      const messageDiv = document.createElement('div');
      messageDiv.className = `promo-${type}`;
      messageDiv.innerHTML = `
        <div class="promo-${type}-text">
          <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
          ${message}
        </div>
      `;
      
      discountMessage.appendChild(messageDiv);
      
      // Auto-hide success messages
      if (type === 'success') {
        setTimeout(() => {
          if (messageDiv.parentNode === discountMessage) {
            messageDiv.style.opacity = '0';
            messageDiv.style.transform = 'translateY(-10px)';
            messageDiv.style.transition = 'all 0.3s ease';
            setTimeout(() => {
              if (messageDiv.parentNode === discountMessage) {
                discountMessage.removeChild(messageDiv);
              }
            }, 300);
          }
        }, 3000);
      }
    }
  });

  // ===== PHONE NUMBER FORMATTING =====
  document.getElementById('customer_phone')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    
    if (value.length > 3 && value.length <= 6) {
      value = value.replace(/(\d{3})(\d+)/, '($1) $2');
    } else if (value.length > 6) {
      value = value.replace(/(\d{3})(\d{3})(\d+)/, '($1) $2-$3');
    }
    
    e.target.value = value.substring(0, 14);
  });

  // ===== INPUT VALIDATION ANIMATIONS =====
  document.querySelectorAll('.form-input').forEach(input => {
    input.addEventListener('focus', function() {
      this.parentElement.classList.add('focused');
    });
    
    input.addEventListener('blur', function() {
      this.parentElement.classList.remove('focused');
      if (this.value.trim() && !this.checkValidity()) {
        this.style.borderColor = 'var(--error)';
        this.style.animation = 'shakeError 0.5s ease';
        setTimeout(() => {
          this.style.animation = '';
        }, 500);
      }
    });
  });

  // ===== INITIALIZE ON LOAD =====
  document.addEventListener('DOMContentLoaded', function() {
    // Add hidden field for promo code if applied
    <?php if($promo_details): ?>
    if (!document.querySelector('input[name="promo_code"]')) {
      const promoInput = document.createElement('input');
      promoInput.type = 'hidden';
      promoInput.name = 'promo_code';
      promoInput.value = '<?php echo h($promo_details['code']); ?>';
      document.getElementById('checkout-form').appendChild(promoInput);
    }
    <?php endif; ?>
    
    // iOS 100vh fix
    function setVH() {
      const vh = window.innerHeight * 0.01;
      document.documentElement.style.setProperty('--vh', `${vh}px`);
    }
    
    setVH();
    window.addEventListener('resize', setVH);
    
    // Prevent zoom on double-tap
    let lastTouchEnd = 0;
    document.addEventListener('touchend', (e) => {
      const now = Date.now();
      if (now - lastTouchEnd <= 300) e.preventDefault();
      lastTouchEnd = now;
    }, false);
    
    // Animate elements on load
    setTimeout(() => {
      document.querySelectorAll('.fade-in').forEach((el, i) => {
        setTimeout(() => {
          el.style.opacity = '1';
          el.style.transform = 'translateY(0)';
        }, i * 100);
      });
    }, 300);
  });
</script>

</body>
</html>