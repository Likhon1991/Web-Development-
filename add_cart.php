<?php
session_start();
include 'db.php';

/* ---------- Helpers ---------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function resolveImagePath($img): string {
    $img = trim((string)$img);
    if ($img === "") return "";
    if (str_starts_with($img, "uploads/")) return "admin/" . $img;
    return $img;
}

/* ---------- Validate product ---------- */
if (!isset($_GET["product_id"])) die("Missing product_id.");
$product_id = (int)$_GET["product_id"];

/* ---------- Load product ---------- */
$p = $conn->prepare("
  SELECT product_id, product_name, description, image_path
  FROM products
  WHERE product_id=? AND status='available'
");
$p->bind_param("i", $product_id);
$p->execute();
$product = $p->get_result()->fetch_assoc();
if (!$product) die("Product not found or unavailable.");

/* ---------- Load sizes ---------- */
$sizes = [];
$s = $conn->prepare("
  SELECT size_name, price
  FROM product_sizes
  WHERE product_id=? AND is_active=1
  ORDER BY FIELD(size_name,'Small','Medium','Large')
");
$s->bind_param("i", $product_id);
$s->execute();
$sr = $s->get_result();
while ($row = $sr->fetch_assoc()) $sizes[] = $row;
if (count($sizes) === 0) die("No sizes found for this product. Add sizes in admin.");

/* ---------- Load boosters ---------- */
$boosters = [];
$br = $conn->query("
  SELECT booster_id, booster_name, booster_price, booster_discriptions
  FROM boosters
  WHERE is_active=1
  ORDER BY booster_name ASC
");
if ($br) while ($b = $br->fetch_assoc()) $boosters[] = $b;

$showSuccessMessage = false;

/* ---------------- Add to cart ---------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $size_name = $_POST["size_name"] ?? "";
  $qty = 1;

  $base_price = null;
  foreach ($sizes as $sz) {
    if ($sz["size_name"] === $size_name) {
      $base_price = (float)$sz["price"];
      break;
    }
  }

  if ($base_price === null) {
    $message = "❌ Please select a valid size.";
  } else {
    $selected = $_POST["boosters"] ?? [];
    if (!is_array($selected)) $selected = [];

    $boosterList = [];
    foreach ($selected as $bid) {
      $bid = (int)$bid;
      foreach ($boosters as $b) {
        if ((int)$b["booster_id"] === $bid) {
          $boosterList[] = [
            "booster_id" => $bid,
            "booster_price" => (float)$b["booster_price"],
            "booster_name" => $b["booster_name"],
          ];
          break;
        }
      }
    }

    if (!isset($_SESSION["cart"])) $_SESSION["cart"] = [];

    $newKeyBoosters = array_map(fn($x) => (int)$x["booster_id"], $boosterList);
    sort($newKeyBoosters);
    $merged = false;

    for ($i=0; $i<count($_SESSION["cart"]); $i++) {
      $it = $_SESSION["cart"][$i];
      if ((int)$it["product_id"] === $product_id && $it["size_name"] === $size_name) {
        $old = $it["boosters"] ?? [];
        $oldIds = array_map(fn($x) => (int)$x["booster_id"], $old);
        sort($oldIds);

        if ($oldIds === $newKeyBoosters) {
          $_SESSION["cart"][$i]["qty"] += $qty;
          $merged = true;
          break;
        }
      }
    }

    if (!$merged) {
      $_SESSION["cart"][] = [
        "product_id" => $product_id,
        "product_name" => $product["product_name"],
        "size_name" => $size_name,
        "qty" => $qty,
        "base_unit_price" => $base_price,
        "boosters" => $boosterList
      ];
    }

    // Set success flag instead of redirecting immediately
    $showSuccessMessage = true;
    
    // JavaScript will handle the redirect after showing success message
  }
}

$img = resolveImagePath($product["image_path"] ?? "");
$LOGO_PATH = "admin/uploads/logo/Screenshot 2026-01-10 at 7.30.43 PM.png";
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
  <title>THE VIBE | Customize <?php echo h($product["product_name"]); ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    /* === UNIQUE ANIMATION VARIABLES === */
    :root {
      --ink: #1f1a17;
      --muted: #6a5f58;
      --brand: #ff6b3d;
      --brand2: #ffb038;
      --brand-light: #ffd8c2;
      --brand-lighter: #fff3e3;
      --glass: rgba(255, 255, 255, 0.85);
      --glass-dark: rgba(255, 255, 255, 0.95);
      --radius: 20px;
      --radius-sm: 14px;
      --radius-lg: 28px;
      --vh: 1vh;
    }

    /* === HYDRODYNAMIC ANIMATIONS === */
    @keyframes fluidFloat {
      0%, 100% { 
        transform: translate(0, 0) rotate(0deg); 
        border-radius: 60% 40% 30% 70% / 60% 30% 70% 40%;
      }
      25% { 
        transform: translate(2%, -1%) rotate(90deg);
        border-radius: 30% 60% 70% 40% / 50% 60% 30% 60%;
      }
      50% { 
        transform: translate(0, 2%) rotate(180deg);
        border-radius: 40% 60% 30% 70% / 60% 50% 50% 40%;
      }
      75% { 
        transform: translate(-2%, -1%) rotate(270deg);
        border-radius: 70% 30% 50% 50% / 30% 40% 60% 70%;
      }
    }

    @keyframes successPop {
      0% { transform: scale(0.8) translateY(20px); opacity: 0; }
      70% { transform: scale(1.05); opacity: 1; }
      100% { transform: scale(1); opacity: 1; }
    }

    @keyframes slideOut {
      from { transform: translateY(0); opacity: 1; }
      to { transform: translateY(-20px); opacity: 0; }
    }

    @keyframes slideInFromRight {
      from {
        transform: translateX(100%);
        opacity: 0;
      }
      to {
        transform: translateX(0);
        opacity: 1;
      }
    }

    /* === BASE STYLES === */
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

    /* === SUCCESS OVERLAY === */
    .success-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(31, 26, 23, 0.9);
      backdrop-filter: blur(10px);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 2000;
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s ease;
    }

    .success-overlay.active {
      opacity: 1;
      visibility: visible;
    }

    .success-card {
      background: linear-gradient(135deg, var(--glass-dark), var(--glass));
      backdrop-filter: blur(20px);
      border-radius: var(--radius-lg);
      padding: 40px 30px;
      max-width: 500px;
      width: 90%;
      border: 2px solid rgba(255, 255, 255, 0.9);
      box-shadow: 0 30px 80px rgba(0, 0, 0, 0.3);
      text-align: center;
      transform: scale(0.9);
      opacity: 0;
    }

    .success-overlay.active .success-card {
      animation: successPop 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
    }

    .success-icon {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, var(--brand), var(--brand2));
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 25px;
      font-size: 36px;
      color: white;
      box-shadow: 0 15px 40px rgba(255, 107, 61, 0.4);
    }

    .success-title {
      font-size: 1.8rem;
      font-weight: 950;
      margin-bottom: 15px;
      color: white;
    }

    .success-message {
      color: rgba(255, 255, 255, 0.9);
      margin-bottom: 30px;
      font-weight: 600;
      line-height: 1.6;
    }

    .success-actions {
      display: flex;
      gap: 15px;
      justify-content: center;
      flex-wrap: wrap;
    }

    .success-btn {
      padding: 14px 28px;
      border-radius: var(--radius-sm);
      border: none;
      font-weight: 900;
      font-size: 1rem;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      text-decoration: none;
      min-width: 160px;
    }

    .success-btn.primary {
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      color: white;
      box-shadow: 0 10px 30px rgba(255, 107, 61, 0.4);
    }

    .success-btn.primary:hover {
      transform: translateY(-3px);
      box-shadow: 0 15px 40px rgba(255, 107, 61, 0.5);
    }

    .success-btn.secondary {
      background: rgba(255, 255, 255, 0.9);
      color: var(--ink);
      border: 2px solid rgba(255, 255, 255, 0.9);
    }

    .success-btn.secondary:hover {
      background: white;
      border-color: var(--brand-light);
      transform: translateY(-3px);
    }

    /* === FLUID BACKGROUND ELEMENTS === */
    .fluid-orb {
      position: fixed;
      pointer-events: none;
      z-index: -1;
      filter: blur(60px);
      animation: fluidFloat 20s ease-in-out infinite;
      mix-blend-mode: overlay;
    }

    .orb-1 {
      width: 300px;
      height: 300px;
      top: 10%;
      left: -150px;
      background: radial-gradient(circle, var(--brand) 0%, transparent 70%);
      animation-duration: 25s;
    }

    .orb-2 {
      width: 250px;
      height: 250px;
      bottom: 20%;
      right: -100px;
      background: radial-gradient(circle, var(--brand2) 0%, transparent 70%);
      animation-duration: 30s;
      animation-delay: -10s;
    }

    /* === NAVIGATION BAR === */
    .nav-container {
      position: sticky;
      top: 0;
      z-index: 1000;
      background: var(--glass);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border-bottom: 1px solid rgba(255, 255, 255, 0.3);
      padding: 12px 16px;
      animation: slideInFromRight 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .nav-inner {
      max-width: 1400px;
      margin: 0 auto;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
    }

    .logo-brand {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      color: inherit;
    }

    .logo-img {
      width: 45px;
      height: 45px;
      border-radius: 14px;
      object-fit: cover;
      border: 2px solid var(--brand);
      box-shadow: 0 8px 32px rgba(255, 107, 61, 0.3);
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .logo-brand:hover .logo-img {
      transform: rotate(15deg) scale(1.1);
      box-shadow: 0 12px 40px rgba(255, 107, 61, 0.5);
    }

    .logo-text {
      display: flex;
      flex-direction: column;
    }

    .logo-text .main {
      font-weight: 950;
      letter-spacing: .1em;
      font-size: 14px;
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }

    .logo-text .sub {
      font-size: 10px;
      color: var(--muted);
      font-weight: 700;
      margin-top: 2px;
    }

    .nav-actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .nav-action {
      padding: 10px 16px;
      border-radius: 50px;
      background: rgba(255, 255, 255, 0.9);
      border: 1px solid rgba(255, 255, 255, 0.9);
      text-decoration: none;
      color: var(--ink);
      font-weight: 900;
      font-size: 13px;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      overflow: hidden;
    }

    .nav-action::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 107, 61, 0.1), transparent);
      transition: left 0.6s ease;
    }

    .nav-action:hover::before {
      left: 100%;
    }

    .nav-action:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(255, 107, 61, 0.2);
      border-color: var(--brand-light);
    }

    .nav-action.cart-btn {
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      color: white;
      border: none;
      box-shadow: 0 8px 24px rgba(255, 107, 61, 0.3);
    }

    .nav-action.cart-btn:hover {
      transform: translateY(-2px) scale(1.05);
      box-shadow: 0 12px 32px rgba(255, 107, 61, 0.4);
    }

    /* === HERO PRODUCT SECTION === */
    .product-hero {
      padding: 40px 16px 30px;
      position: relative;
      overflow: hidden;
    }

    .hero-content {
      max-width: 1400px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: 1fr;
      gap: 30px;
    }

    @media (min-width: 1024px) {
      .hero-content {
        grid-template-columns: 1fr 1fr;
        gap: 50px;
      }
    }

    /* PRODUCT VISUAL */
    .product-visual {
      position: relative;
      border-radius: var(--radius-lg);
      overflow: hidden;
      box-shadow: 0 25px 60px rgba(24, 16, 12, 0.15);
      height: 350px;
    }

    @media (min-width: 768px) {
      .product-visual {
        height: 450px;
      }
    }

    .product-image {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .product-visual:hover .product-image {
      transform: scale(1.05);
    }

    /* PRODUCT INFO */
    .product-info {
      padding: 24px;
      background: var(--glass-dark);
      backdrop-filter: blur(30px);
      border-radius: var(--radius-lg);
      border: 1px solid rgba(255, 255, 255, 0.9);
      box-shadow: 0 20px 50px rgba(24, 16, 12, 0.1);
      animation: slideInFromRight 0.8s cubic-bezier(0.4, 0, 0.2, 1) 0.2s both;
    }

    .product-title {
      font-size: 2rem;
      font-weight: 950;
      margin-bottom: 12px;
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      position: relative;
      display: inline-block;
    }

    .product-title::after {
      content: '';
      position: absolute;
      bottom: -8px;
      left: 0;
      width: 60px;
      height: 3px;
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      border-radius: 2px;
    }

    .product-desc {
      color: var(--muted);
      font-weight: 600;
      line-height: 1.6;
      margin-bottom: 30px;
      font-size: 1.1rem;
    }

    /* === CUSTOMIZATION CONTAINER === */
    .customize-container {
      padding: 0 16px 40px;
      max-width: 1400px;
      margin: 0 auto;
    }

    /* SIZES SECTION */
    .size-section {
      background: var(--glass-dark);
      backdrop-filter: blur(30px);
      border-radius: var(--radius-lg);
      padding: 30px;
      margin-bottom: 30px;
      border: 1px solid rgba(255, 255, 255, 0.9);
      box-shadow: 0 20px 50px rgba(24, 16, 12, 0.08);
      animation: slideInFromRight 0.8s cubic-bezier(0.4, 0, 0.2, 1) 0.3s both;
    }

    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
    }

    .section-title {
      font-size: 1.5rem;
      font-weight: 950;
      color: var(--ink);
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .section-title i {
      color: var(--brand);
      font-size: 1.2rem;
    }

    .section-hint {
      color: var(--muted);
      font-weight: 700;
      font-size: 0.9rem;
      padding: 4px 12px;
      background: rgba(255, 255, 255, 0.9);
      border-radius: 50px;
      border: 1px solid rgba(255, 255, 255, 0.9);
    }

    .sizes-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
      gap: 16px;
    }

    @media (max-width: 480px) {
      .sizes-grid {
        grid-template-columns: 1fr;
      }
    }

    .size-option {
      position: relative;
      cursor: pointer;
    }

    .size-card {
      padding: 24px 16px;
      background: white;
      border-radius: var(--radius);
      border: 2px solid rgba(255, 255, 255, 0.9);
      text-align: center;
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      overflow: hidden;
    }

    .size-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 107, 61, 0.1), transparent);
      transition: left 0.6s ease;
    }

    .size-card:hover::before {
      left: 100%;
    }

    .size-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 40px rgba(255, 107, 61, 0.15);
    }

    .size-card.selected {
      border-color: var(--brand);
      box-shadow: 0 15px 40px rgba(255, 107, 61, 0.2);
      background: linear-gradient(135deg, rgba(255, 107, 61, 0.05), rgba(255, 176, 56, 0.05));
    }

    .size-card.selected::after {
      content: '';
      position: absolute;
      top: 10px;
      right: 10px;
      width: 12px;
      height: 12px;
      background: linear-gradient(45deg, var(--brand), var(--brand2));
      border-radius: 50%;
    }

    .size-name {
      font-weight: 900;
      font-size: 1.1rem;
      margin-bottom: 8px;
      color: var(--ink);
    }

    .size-price {
      font-weight: 950;
      font-size: 1.4rem;
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }

    .size-option input {
      position: absolute;
      opacity: 0;
      width: 0;
      height: 0;
    }

    /* BOOSTERS SECTION */
    .boosters-section {
      background: var(--glass-dark);
      backdrop-filter: blur(30px);
      border-radius: var(--radius-lg);
      padding: 30px;
      margin-bottom: 30px;
      border: 1px solid rgba(255, 255, 255, 0.9);
      box-shadow: 0 20px 50px rgba(24, 16, 12, 0.08);
      animation: slideInFromRight 0.8s cubic-bezier(0.4, 0, 0.2, 1) 0.4s both;
    }

    .boosters-toggle {
      padding: 18px;
      background: white;
      border-radius: var(--radius);
      border: 2px solid rgba(255, 255, 255, 0.9);
      display: flex;
      justify-content: space-between;
      align-items: center;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      margin-bottom: 20px;
    }

    .boosters-toggle:hover {
      border-color: var(--brand-light);
      transform: translateY(-2px);
    }

    .boosters-toggle.active {
      border-color: var(--brand);
    }

    .toggle-label {
      font-weight: 900;
      font-size: 1.1rem;
      color: var(--ink);
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .toggle-arrow {
      color: var(--brand);
      transition: transform 0.3s ease;
    }

    .boosters-toggle.active .toggle-arrow {
      transform: rotate(180deg);
    }

    .boosters-grid {
      display: none;
      grid-template-columns: 1fr;
      gap: 16px;
      animation: slideInFromRight 0.5s ease;
    }

    @media (min-width: 768px) {
      .boosters-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    .boosters-grid.active {
      display: grid;
    }

    .booster-card {
      padding: 20px;
      background: white;
      border-radius: var(--radius);
      border: 2px solid rgba(255, 255, 255, 0.9);
      display: flex;
      justify-content: space-between;
      align-items: center;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      cursor: pointer;
      position: relative;
      overflow: hidden;
    }

    .booster-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 107, 61, 0.05), transparent);
      transition: left 0.6s ease;
    }

    .booster-card:hover::before {
      left: 100%;
    }

    .booster-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 30px rgba(255, 107, 61, 0.1);
      border-color: var(--brand-light);
    }

    .booster-card.selected {
      border-color: var(--brand);
      background: linear-gradient(135deg, rgba(255, 107, 61, 0.05), rgba(255, 176, 56, 0.05));
    }

    .booster-info {
      flex: 1;
    }

    .booster-name {
      font-weight: 900;
      font-size: 1rem;
      color: var(--ink);
      margin-bottom: 4px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .booster-desc {
      color: var(--muted);
      font-weight: 600;
      font-size: 0.85rem;
      line-height: 1.4;
    }

    .booster-price {
      font-weight: 950;
      font-size: 1.1rem;
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      margin-left: 16px;
      white-space: nowrap;
    }

    .booster-checkbox {
      display: none;
    }

    .booster-card.selected .booster-name::before {
      content: '✓';
      color: var(--brand);
      font-weight: 900;
      animation: slideInFromRight 0.3s ease;
    }

    /* SELECTED BOOSTERS */
    .selected-boosters {
      margin-top: 24px;
      display: none;
    }

    .selected-boosters.has-items {
      display: block;
      animation: slideInFromRight 0.5s ease;
    }

    .selected-title {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
    }

    .selected-title span {
      color: var(--muted);
      font-weight: 700;
      font-size: 0.9rem;
    }

    .clear-boosters {
      background: none;
      border: none;
      color: var(--brand);
      font-weight: 900;
      font-size: 0.8rem;
      cursor: pointer;
      padding: 4px 12px;
      border-radius: 50px;
      transition: all 0.3s ease;
    }

    .clear-boosters:hover {
      background: rgba(255, 107, 61, 0.1);
    }

    .boosters-tags {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }

    .booster-tag {
      padding: 8px 16px;
      background: white;
      border-radius: 50px;
      border: 1px solid rgba(255, 107, 61, 0.2);
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 0.9rem;
      font-weight: 700;
      animation: slideInFromRight 0.3s ease;
    }

    .booster-tag .price {
      color: var(--brand);
      font-weight: 900;
    }

    .booster-tag .remove {
      background: none;
      border: none;
      color: var(--muted);
      font-size: 1.1rem;
      cursor: pointer;
      padding: 0;
      width: 20px;
      height: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      transition: all 0.2s ease;
    }

    .booster-tag .remove:hover {
      background: rgba(255, 107, 61, 0.1);
      color: var(--brand);
    }

    /* ORDER SUMMARY */
    .order-summary {
      background: var(--glass-dark);
      backdrop-filter: blur(30px);
      border-radius: var(--radius-lg);
      padding: 30px;
      border: 1px solid rgba(255, 255, 255, 0.9);
      box-shadow: 0 20px 50px rgba(24, 16, 12, 0.08);
      animation: slideInFromRight 0.8s cubic-bezier(0.4, 0, 0.2, 1) 0.5s both;
    }

    .summary-lines {
      margin-bottom: 30px;
    }

    .summary-line {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 16px 0;
      border-bottom: 1px dashed rgba(60, 40, 30, 0.15);
    }

    .summary-line:last-child {
      border-bottom: none;
    }

    .summary-label {
      color: var(--muted);
      font-weight: 700;
      font-size: 1rem;
    }

    .summary-value {
      font-weight: 900;
      font-size: 1rem;
      color: var(--ink);
    }

    .summary-total {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 24px 0 10px;
      border-top: 2px solid rgba(60, 40, 30, 0.2);
      margin-top: 10px;
    }

    .total-label {
      font-weight: 900;
      font-size: 1.3rem;
      color: var(--ink);
    }

    .total-value {
      font-weight: 950;
      font-size: 2rem;
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }

    .action-buttons {
      display: grid;
      grid-template-columns: 1fr;
      gap: 16px;
      margin-top: 30px;
    }

    @media (min-width: 768px) {
      .action-buttons {
        grid-template-columns: 1fr 1fr;
      }
    }

    .action-btn {
      padding: 18px 24px;
      border-radius: var(--radius);
      border: none;
      font-weight: 900;
      font-size: 1.1rem;
      cursor: pointer;
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      text-decoration: none;
      position: relative;
      overflow: hidden;
    }

    .action-btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
      transition: left 0.6s ease;
    }

    .action-btn:hover::before {
      left: 100%;
    }

    .action-btn.primary {
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      color: white;
      box-shadow: 0 15px 40px rgba(255, 107, 61, 0.3);
    }

    .action-btn.primary:hover {
      transform: translateY(-5px);
      box-shadow: 0 25px 60px rgba(255, 107, 61, 0.4);
    }

    .action-btn.secondary {
      background: white;
      border: 2px solid rgba(255, 255, 255, 0.9);
      color: var(--ink);
    }

    .action-btn.secondary:hover {
      border-color: var(--brand-light);
      transform: translateY(-3px);
      box-shadow: 0 15px 40px rgba(255, 107, 61, 0.1);
    }

    /* FOOTER */
    .page-footer {
      margin-top: 40px;
      padding: 40px 16px;
      background: linear-gradient(135deg, rgba(255, 107, 61, 0.05), rgba(255, 176, 56, 0.05));
      border-top: 1px solid rgba(255, 255, 255, 0.3);
      backdrop-filter: blur(20px);
    }

    .footer-content {
      max-width: 1400px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: 1fr;
      gap: 40px;
    }

    @media (min-width: 768px) {
      .footer-content {
        grid-template-columns: repeat(3, 1fr);
        gap: 60px;
      }
    }

    .footer-section h3 {
      font-size: 1rem;
      font-weight: 900;
      margin-bottom: 16px;
      color: var(--ink);
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .footer-section p {
      color: var(--muted);
      margin-bottom: 12px;
      font-weight: 600;
      font-size: 0.95rem;
      line-height: 1.5;
    }

    .footer-logo {
      width: 60px;
      height: 60px;
      border-radius: 16px;
      object-fit: cover;
      border: 2px solid var(--brand);
      margin-bottom: 16px;
      box-shadow: 0 8px 32px rgba(255, 107, 61, 0.2);
    }

    .social-links {
      display: flex;
      gap: 10px;
      margin-top: 20px;
    }

    .social-link {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: white;
      border: 1px solid rgba(255, 255, 255, 0.9);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--brand);
      text-decoration: none;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .social-link:hover {
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      color: white;
      transform: translateY(-3px) rotate(360deg);
      box-shadow: 0 8px 20px rgba(255, 107, 61, 0.3);
    }

    .footer-bottom {
      max-width: 1400px;
      margin: 40px auto 0;
      padding-top: 24px;
      border-top: 1px solid rgba(255, 255, 255, 0.3);
      text-align: center;
      color: var(--muted);
      font-size: 0.85rem;
      font-weight: 600;
    }

    /* MOBILE OPTIMIZATIONS */
    @media (max-width: 768px) {
      .product-visual {
        height: 280px;
      }
      
      .product-title {
        font-size: 1.8rem;
      }
      
      .section-title {
        font-size: 1.3rem;
      }
      
      .size-card {
        padding: 20px 12px;
      }
      
      .size-price {
        font-size: 1.2rem;
      }
      
      .total-value {
        font-size: 1.8rem;
      }
      
      .action-btn {
        padding: 16px 20px;
        font-size: 1rem;
      }
      
      .fluid-orb {
        display: none;
      }
      
      .success-card {
        padding: 30px 20px;
      }
      
      .success-title {
        font-size: 1.5rem;
      }
      
      .success-actions {
        flex-direction: column;
      }
      
      .success-btn {
        width: 100%;
      }
    }

    @media (max-width: 480px) {
      .nav-actions {
        width: 100%;
        justify-content: center;
      }
      
      .sizes-grid {
        grid-template-columns: 1fr;
      }
      
      .boosters-grid {
        grid-template-columns: 1fr;
      }
      
      .product-hero {
        padding: 30px 12px 20px;
      }
      
      .customize-container {
        padding: 0 12px 30px;
      }
      
      .size-section,
      .boosters-section,
      .order-summary {
        padding: 24px;
      }
    }

    /* TOUCH OPTIMIZATIONS */
    @media (hover: none) and (pointer: coarse) {
      .size-card:hover,
      .booster-card:hover,
      .action-btn:hover,
      .social-link:hover {
        transform: none;
      }
      
      .size-card:active,
      .booster-card:active,
      .action-btn:active {
        transform: scale(0.98);
      }
    }

    /* IOS SAFARI FIXES */
    @supports (-webkit-touch-callout: none) {
      body {
        -webkit-overflow-scrolling: touch;
      }
      
      .nav-container {
        position: -webkit-sticky;
      }
    }
  </style>
</head>
<body>

<!-- Success Overlay (Hidden by default) -->
<div class="success-overlay" id="successOverlay">
  <div class="success-card">
    <div class="success-icon">
      <i class="fas fa-check"></i>
    </div>
    <h2 class="success-title">Item Added!</h2>
    <p class="success-message">
      <strong><?php echo h($product["product_name"]); ?></strong> has been added to your cart.<br>
      Continue shopping or view your cart.
    </p>
    <div class="success-actions">
      <a href="index.php#menuList" class="success-btn primary">
        <i class="fas fa-utensils"></i>
        Back to Menu
      </a>
      <a href="cart.php" class="success-btn secondary">
        <i class="fas fa-shopping-cart"></i>
        View Cart
      </a>
    </div>
  </div>
</div>

<!-- Fluid Background Elements -->
<div class="fluid-orb orb-1"></div>
<div class="fluid-orb orb-2"></div>

<!-- Navigation -->
<nav class="nav-container">
  <div class="nav-inner">
    <a href="index.php" class="logo-brand">
      <img class="logo-img" src="<?php echo h($LOGO_PATH); ?>" alt="THE VIBE">
      <div class="logo-text">
        <div class="main">THE VIBE</div>
        <div class="sub">Winona, MN</div>
      </div>
    </a>
    
    <div class="nav-actions">
      <a href="index.php#menuList" class="nav-action">
        <i class="fas fa-utensils"></i>
        <span class="mobile-hide">Menu</span>
      </a>
      <a href="tel:5074294152" class="nav-action">
        <i class="fas fa-phone"></i>
        <span class="mobile-hide">Call</span>
      </a>
      <a href="cart.php" class="nav-action cart-btn">
        <i class="fas fa-shopping-cart"></i>
        <span class="mobile-hide">Cart</span>
        <span class="cart-count"><?php echo isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0; ?></span>
      </a>
    </div>
  </div>
</nav>

<!-- Product Hero -->
<section class="product-hero">
  <div class="hero-content">
    <div class="product-visual">
      <?php if ($img !== ""): ?>
        <img class="product-image" src="<?php echo h($img); ?>" alt="<?php echo h($product["product_name"]); ?>">
      <?php else: ?>
        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(45deg,var(--brand-light),var(--brand-lighter));">
          <i class="fas fa-glass-water" style="font-size:3rem;color:var(--brand);opacity:0.3;"></i>
        </div>
      <?php endif; ?>
    </div>
    
    <div class="product-info">
      <h1 class="product-title"><?php echo h($product["product_name"]); ?></h1>
      <p class="product-desc"><?php echo h($product["description"]); ?></p>
      
      <div style="display: flex; align-items: center; gap: 16px; margin-top: 30px;">
        <div style="display: flex; align-items: center; gap: 8px;">
          <i class="fas fa-star" style="color: var(--brand2);"></i>
          <span style="font-weight: 700; color: var(--muted);">Featured Item</span>
        </div>
        <div style="height: 20px; width: 1px; background: rgba(60, 40, 30, 0.2);"></div>
        <div style="display: flex; align-items: center; gap: 8px;">
          <i class="fas fa-clock" style="color: var(--brand);"></i>
          <span style="font-weight: 700; color: var(--muted);">Ready in 5-10 mins</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Customization Form -->
<section class="customize-container">
  <form method="POST" id="addForm">
    
    <!-- Size Selection -->
    <div class="size-section">
      <div class="section-header">
        <h2 class="section-title">
          <i class="fas fa-arrows-alt-v"></i>
          Select Your Size
        </h2>
        <span class="section-hint">Choose one</span>
      </div>
      
      <div class="sizes-grid">
        <?php foreach ($sizes as $i => $sz): ?>
          <label class="size-option">
            <input type="radio" 
                   name="size_name" 
                   value="<?php echo h($sz["size_name"]); ?>"
                   data-price="<?php echo number_format((float)$sz["price"], 2, '.', ''); ?>"
                   <?php echo $i===0 ? "checked" : ""; ?>>
            <div class="size-card <?php echo $i===0 ? 'selected' : ''; ?>">
              <div class="size-name"><?php echo h($sz["size_name"]); ?></div>
              <div class="size-price">$<?php echo number_format((float)$sz["price"],2); ?></div>
            </div>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
    
    <!-- Boosters Selection -->
    <div class="boosters-section">
      <div class="section-header">
        <h2 class="section-title">
          <i class="fas fa-plus-circle"></i>
          Add Boosters
        </h2>
        <span class="section-hint">Optional extras</span>
      </div>
      
      <div class="boosters-toggle" id="boostersToggle">
        <div class="toggle-label">
          <i class="fas fa-cogs"></i>
          Customize your drink
        </div>
        <div class="toggle-arrow">
          <i class="fas fa-chevron-down"></i>
        </div>
      </div>
      
      <div class="boosters-grid" id="boostersGrid">
        <?php if (count($boosters) === 0): ?>
          <div style="grid-column:1/-1;text-align:center;padding:40px 20px;color:var(--muted);font-weight:600;">
            <i class="fas fa-star" style="font-size:2rem;margin-bottom:12px;opacity:0.3;"></i>
            <p>No boosters available at the moment</p>
          </div>
        <?php else: ?>
          <?php foreach ($boosters as $b): ?>
            <label class="booster-card">
              <input type="checkbox"
                     class="booster-checkbox"
                     name="boosters[]"
                     value="<?php echo (int)$b["booster_id"]; ?>"
                     data-price="<?php echo number_format((float)$b["booster_price"], 2, '.', ''); ?>"
                     data-name="<?php echo h($b["booster_name"]); ?>"
                     data-desc="<?php echo h($b["booster_discriptions"]); ?>">
              <div class="booster-info">
                <div class="booster-name"><?php echo h($b["booster_name"]); ?></div>
                <div class="booster-desc"><?php echo h($b["booster_discriptions"]); ?></div>
              </div>
              <div class="booster-price">+$<?php echo number_format((float)$b["booster_price"],2); ?></div>
            </label>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      
      <div class="selected-boosters" id="selectedBoosters">
        <div class="selected-title">
          <span>SELECTED BOOSTERS</span>
          <button type="button" class="clear-boosters" onclick="clearAllBoosters()">Clear All</button>
        </div>
        <div class="boosters-tags" id="boostersTags"></div>
      </div>
    </div>
    
    <!-- Order Summary -->
    <div class="order-summary">
      <div class="section-header">
        <h2 class="section-title">
          <i class="fas fa-receipt"></i>
          Order Summary
        </h2>
        <span class="section-hint">Live pricing</span>
      </div>
      
      <div class="summary-lines">
        <div class="summary-line">
          <span class="summary-label">Base Price</span>
          <span class="summary-value" id="summaryBase">$0.00</span>
        </div>
        <div class="summary-line">
          <span class="summary-label">Boosters</span>
          <span class="summary-value" id="summaryBoost">$0.00</span>
        </div>
        <div class="summary-total">
          <span class="total-label">Total</span>
          <span class="total-value" id="summaryTotal">$0.00</span>
        </div>
      </div>
      
      <div class="action-buttons">
        <button class="action-btn primary" type="submit" id="addToCartBtn">
          <i class="fas fa-cart-plus"></i>
          Add to Cart
        </button>
        <a href="index.php#menuList" class="action-btn secondary">
          <i class="fas fa-arrow-left"></i>
          Back to Menu
        </a>
      </div>
    </div>
    
  </form>
</section>

<!-- Footer -->
<footer class="page-footer">
  <div class="footer-content">
    <div class="footer-section">
      <img class="footer-logo" src="<?php echo h($LOGO_PATH); ?>" alt="THE VIBE">
      <p>Winona, MN • Premium energy drinks, protein shakes, boba teas, and more.</p>
      <div class="social-links">
        <a href="#" class="social-link" title="Instagram">
          <i class="fab fa-instagram"></i>
        </a>
        <a href="#" class="social-link" title="Facebook">
          <i class="fab fa-facebook"></i>
        </a>
        <a href="#" class="social-link" title="TikTok">
          <i class="fab fa-tiktok"></i>
        </a>
      </div>
    </div>
    
    <div class="footer-section">
      <h3>Contact Info</h3>
      <p><i class="fas fa-phone"></i> 507-429-4152</p>
      <p><i class="fas fa-map-marker-alt"></i> 350 E Sarnia St</p>
      <p><i class="fas fa-clock"></i> Mon–Fri: 9am–1pm</p>
      <p><i class="fas fa-bolt"></i> Always available online</p>
    </div>
    
    <div class="footer-section">
      <h3>Delivery</h3>
      <p>Free delivery on <b>5+ drinks</b></p>
      <p>Call/text to place your order</p>
      <p>Fast & reliable service</p>
      <p>Customization available</p>
    </div>
  </div>
  
  <div class="footer-bottom">
    <p>&copy; 2024 THE VIBE. All rights reserved. | Winona, MN</p>
    <p style="margin-top: 8px; font-size: 0.8rem;">
      Energy • Protein • Boba • Coffee • Smoothies • Refreshers
    </p>
  </div>
</footer>

<script>
// === PRICING ENGINE ===
class PricingEngine {
  constructor() {
    this.basePrice = 0;
    this.boosters = new Map();
    
    this.init();
  }
  
  init() {
    // Size selection
    document.querySelectorAll('input[name="size_name"]').forEach(radio => {
      radio.addEventListener('change', (e) => {
        this.basePrice = parseFloat(e.target.dataset.price) || 0;
        this.update();
        // Update selected card styling
        document.querySelectorAll('.size-card').forEach(card => {
          card.classList.remove('selected');
        });
        e.target.closest('.size-option').querySelector('.size-card').classList.add('selected');
      });
    });
    
    // Booster selection
    document.querySelectorAll('.booster-checkbox').forEach(cb => {
      cb.addEventListener('change', (e) => {
        const id = e.target.value;
        const name = e.target.dataset.name;
        const price = parseFloat(e.target.dataset.price) || 0;
        
        if (e.target.checked) {
          this.boosters.set(id, { name, price });
          e.target.closest('.booster-card').classList.add('selected');
        } else {
          this.boosters.delete(id);
          e.target.closest('.booster-card').classList.remove('selected');
        }
        
        this.updateSelectedBoosters();
        this.update();
      });
    });
    
    // Set initial price
    const initialSize = document.querySelector('input[name="size_name"]:checked');
    if (initialSize) {
      this.basePrice = parseFloat(initialSize.dataset.price) || 0;
      this.update();
    }
  }
  
  updateSelectedBoosters() {
    const container = document.getElementById('selectedBoosters');
    const tagsContainer = document.getElementById('boostersTags');
    
    if (this.boosters.size === 0) {
      container.classList.remove('has-items');
      return;
    }
    
    container.classList.add('has-items');
    
    let html = '';
    this.boosters.forEach((booster, id) => {
      html += `
        <div class="booster-tag" data-id="${id}">
          ${booster.name}
          <span class="price">+$${booster.price.toFixed(2)}</span>
          <button type="button" class="remove" onclick="pricing.removeBooster('${id}')">
            ×
          </button>
        </div>
      `;
    });
    
    tagsContainer.innerHTML = html;
  }
  
  removeBooster(id) {
    const checkbox = document.querySelector(`input[value="${id}"]`);
    if (checkbox) {
      checkbox.checked = false;
      checkbox.closest('.booster-card').classList.remove('selected');
      this.boosters.delete(id);
      this.updateSelectedBoosters();
      this.update();
    }
  }
  
  update() {
    const boostersTotal = Array.from(this.boosters.values())
      .reduce((sum, b) => sum + b.price, 0);
    const total = this.basePrice + boostersTotal;
    
    // Update display
    document.getElementById('summaryBase').textContent = `$${this.basePrice.toFixed(2)}`;
    document.getElementById('summaryBoost').textContent = `$${boostersTotal.toFixed(2)}`;
    document.getElementById('summaryTotal').textContent = `$${total.toFixed(2)}`;
  }
}

// === BOOSTERS TOGGLE ===
const boostersToggle = document.getElementById('boostersToggle');
const boostersGrid = document.getElementById('boostersGrid');

boostersToggle.addEventListener('click', () => {
  boostersToggle.classList.toggle('active');
  boostersGrid.classList.toggle('active');
});

// === FORM SUBMISSION WITH SUCCESS MESSAGE ===
document.getElementById('addForm').addEventListener('submit', function(e) {
  const selectedSize = document.querySelector('input[name="size_name"]:checked');
  if (!selectedSize) {
    e.preventDefault();
    
    // Show error animation on size cards
    document.querySelectorAll('.size-card').forEach(card => {
      card.style.animation = 'shake 0.5s ease';
      card.style.borderColor = 'rgba(255, 87, 61, 0.8)';
      setTimeout(() => {
        card.style.animation = '';
        card.style.borderColor = '';
      }, 500);
    });
    
    // Create shake animation if not exists
    if (!document.querySelector('style#shake-animation')) {
      const style = document.createElement('style');
      style.id = 'shake-animation';
      style.textContent = `
        @keyframes shake {
          0%, 100% { transform: translateX(0); }
          25% { transform: translateX(-10px); }
          75% { transform: translateX(10px); }
        }
      `;
      document.head.appendChild(style);
    }
    
    return false;
  }
  
  // If form submission was successful (PHP sets $showSuccessMessage), show overlay
  <?php if ($showSuccessMessage): ?>
    e.preventDefault();
    showSuccessMessage();
  <?php endif; ?>
});

function showSuccessMessage() {
  const overlay = document.getElementById('successOverlay');
  const addBtn = document.getElementById('addToCartBtn');
  
  // Animate the add button
  addBtn.innerHTML = '<i class="fas fa-check"></i> Added!';
  addBtn.style.background = 'linear-gradient(90deg, #4CAF50, #45a049)';
  
  // Show success overlay
  overlay.classList.add('active');
  
  // Update cart count in navigation
  updateCartCount();
}

function updateCartCount() {
  const cartCounts = document.querySelectorAll('.cart-count');
  cartCounts.forEach(count => {
    const current = parseInt(count.textContent) || 0;
    count.textContent = current + 1;
  });
}

// === CLEAR ALL BOOSTERS ===
window.clearAllBoosters = function() {
  document.querySelectorAll('.booster-checkbox:checked').forEach(cb => {
    cb.checked = false;
    cb.closest('.booster-card').classList.remove('selected');
  });
  
  if (window.pricing) {
    window.pricing.boosters.clear();
    window.pricing.updateSelectedBoosters();
    window.pricing.update();
  }
};

// === MOBILE OPTIMIZATIONS ===
function adjustForMobile() {
  const isMobile = window.innerWidth <= 768;
  
  if (isMobile) {
    // Simplify animations
    document.querySelectorAll('.size-card, .booster-card').forEach(card => {
      card.style.transition = 'all 0.2s ease';
    });
    
    // Hide fluid orbs on mobile
    document.querySelectorAll('.fluid-orb').forEach(orb => {
      orb.style.display = 'none';
    });
  }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
  window.pricing = new PricingEngine();
  adjustForMobile();
  
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
  
  // Auto-show success message if PHP flag is set
  <?php if ($showSuccessMessage): ?>
    setTimeout(() => {
      showSuccessMessage();
    }, 100);
  <?php endif; ?>
});

// Update cart count on page load (in case coming from another page)
document.addEventListener('DOMContentLoaded', () => {
  // Cart count is already set by PHP, but we can verify
  const cartCount = <?php echo isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0; ?>;
  document.querySelectorAll('.cart-count').forEach(el => {
    el.textContent = cartCount;
  });
});
</script>

</body>
</html>