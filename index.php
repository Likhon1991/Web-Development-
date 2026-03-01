<?php
session_start();
include 'db.php';

/* ---------- Helpers ---------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function resolveImagePath($img): string {
    $img = trim((string)$img);
    if ($img === "") return "";
    if (str_starts_with($img, "admin/uploads/")) return "admin/" . $img;
    return $img;
}

/* ---------- Load categories ---------- */
$categories = [];
$catRes = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
if ($catRes) while ($c = $catRes->fetch_assoc()) $categories[] = $c;

/* ---------- Category filter ---------- */
$selectedCat = (int)($_GET["cat"] ?? 0);
$selectedCatName = "";
foreach ($categories as $c) {
    if ((int)$c["category_id"] === $selectedCat) { $selectedCatName = $c["category_name"]; break; }
}

/* ---------- Menu query ---------- */
$sql = "
SELECT p.product_id, p.product_name, p.description, p.image_path,
       c.category_name, c.category_id
FROM products p
JOIN categories c ON p.category_id = c.category_id
WHERE p.status='available'
";
if ($selectedCat > 0) {
    $sql .= " AND c.category_id = $selectedCat ";
}
$sql .= " ORDER BY p.product_id DESC";

$res = $conn->query($sql);

/* ---------- Build slideshow data ---------- */
$slideList = [];
if ($res && $res->num_rows > 0) {
    $res2 = $conn->query($sql);
    while ($row = $res2->fetch_assoc()) {
        $pid = (int)$row["product_id"];
        $img = resolveImagePath($row["image_path"] ?? "");
        if ($img === "") continue;

        $slideList[] = [
            "product_id" => $pid,
            "name"       => (string)$row["product_name"],
            "cat_id"     => (int)$row["category_id"],
            "cat_name"   => (string)$row["category_name"],
            "img"        => $img,
            "desc"       => (string)$row["description"],
        ];
    }
}

/* ---------- Re-run for menu rendering ---------- */
$res = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
  <title>THE VIBE | Energy • Protein • Boba & More</title>
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="format-detection" content="telephone=no">
  <meta name="mobile-web-app-capable" content="yes">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
  <style>
    /* === KEEP YOUR ORIGINAL COLOR PALETTE === */
    :root {
      --ink: #1f1a17;
      --muted: #6a5f58;
      --brand: #ff6b3d;
      --brand2: #ffb038;
      --brand-light: #ffd8c2;
      --brand-lighter: #fff3e3;
      --line: rgba(60, 40, 30, .14);
      --glass: rgba(255, 255, 255, .75);
      --glass2: rgba(255, 255, 255, .45);
      --shadow: 0 14px 44px rgba(24, 16, 12, .12);
      --shadow-light: 0 8px 24px rgba(255, 107, 61, .15);
      --radius: 20px;
      --radius-sm: 14px;
      --radius-lg: 28px;
      
      /* New animation variables */
      --gradient-brand: linear-gradient(135deg, #ff6b3d 0%, #ffb038 100%);
      --gradient-brand-light: linear-gradient(135deg, rgba(255, 107, 61, 0.1) 0%, rgba(255, 176, 56, 0.1) 100%);
      --shadow-xl: 0 35px 60px -15px rgba(255, 107, 61, 0.25);
      --vh: 1vh;
    }

    /* === NEW ANIMATIONS === */
    @keyframes float {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-20px); }
    }

    @keyframes pulse-glow {
      0%, 100% { box-shadow: 0 0 20px rgba(255, 107, 61, 0.3); }
      50% { box-shadow: 0 0 40px rgba(255, 107, 61, 0.6); }
    }

    @keyframes slideInUp {
      from {
        transform: translateY(50px);
        opacity: 0;
      }
      to {
        transform: translateY(0);
        opacity: 1;
      }
    }

    @keyframes fadeInScale {
      from {
        transform: scale(0.9);
        opacity: 0;
      }
      to {
        transform: scale(1);
        opacity: 1;
      }
    }

    /* === BASE STYLES WITH ANIMATIONS === */
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
        radial-gradient(900px 420px at 10% 0%, #ffd8c2, transparent 70%),
        radial-gradient(850px 420px at 90% 10%, #ffe7d6, transparent 70%),
        linear-gradient(180deg, #fff3e3, #fff);
      min-height: 100vh;
      min-height: calc(var(--vh, 1vh) * 100);
      overflow-x: hidden;
      line-height: 1.5;
      position: relative;
      font-size: 16px;
    }

    /* === DECORATIVE ANIMATED ELEMENTS === */
    .floating-orbs {
      position: fixed;
      pointer-events: none;
      z-index: -1;
      filter: blur(40px);
      opacity: 0.3;
      animation: float 20s ease-in-out infinite;
    }

    .orb-1 {
      width: 400px;
      height: 400px;
      top: 10%;
      left: -100px;
      background: radial-gradient(circle, #ff6b3d 0%, transparent 70%);
      animation-delay: 0s;
    }

    .orb-2 {
      width: 300px;
      height: 300px;
      bottom: 20%;
      right: -50px;
      background: radial-gradient(circle, #ffb038 0%, transparent 70%);
      animation-delay: -5s;
    }

    .orb-3 {
      width: 200px;
      height: 200px;
      top: 50%;
      left: 50%;
      background: radial-gradient(circle, #ff6b3d 0%, transparent 70%);
      animation-delay: -10s;
    }

    /* === ENHANCED TOP BAR === */
    .topbar {
      position: sticky;
      top: 0;
      z-index: 1000;
      background: rgba(255, 255, 255, .95);
      border-bottom: 1px solid var(--line);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      padding: 12px 20px;
      animation: slideInUp 0.8s cubic-bezier(0.4, 0, 0.2, 1) forwards;
      opacity: 0;
      animation-delay: 0.2s;
    }

    .topbar-inner {
      width: 100%;
      max-width: 1200px;
      margin: 0 auto;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 15px;
      flex-wrap: wrap;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      color: inherit;
      transition: all 0.3s ease;
    }

    .brand:hover {
      transform: translateX(5px);
    }

    .logoImg {
      width: 50px;
      height: 50px;
      border-radius: 14px;
      object-fit: cover;
      border: 2px solid var(--brand);
      box-shadow: 0 8px 25px rgba(255, 107, 61, 0.3);
      transition: all 0.3s ease;
      animation: pulse-glow 3s infinite;
    }

    .brand:hover .logoImg {
      transform: rotate(10deg) scale(1.1);
      box-shadow: 0 12px 35px rgba(255, 107, 61, 0.5);
    }

    .brandText {
      display: flex;
      flex-direction: column;
    }

    .brandText .name {
      font-weight: 950;
      letter-spacing: .1em;
      font-size: 14px;
      line-height: 1.2;
      white-space: nowrap;
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }

    .brandText .sub {
      display: none;
    }

    @media (min-width: 640px) {
      .brandText .sub {
        display: block;
        font-size: 11px;
        color: var(--muted);
        font-weight: 750;
        line-height: 1.2;
        margin-top: 2px;
      }
    }

    .topActions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .nav-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      padding: 10px 18px;
      border-radius: 999px;
      border: 1px solid var(--line);
      background: rgba(255, 255, 255, .9);
      text-decoration: none;
      color: var(--ink);
      font-weight: 900;
      font-size: 13px;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
      min-height: 44px;
    }

    .nav-btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 107, 61, 0.1), transparent);
      transition: left 0.6s ease;
    }

    .nav-btn:hover::before {
      left: 100%;
    }

    .nav-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 25px rgba(255, 107, 61, 0.2);
      border-color: var(--brand);
    }

    .nav-btn.primary {
      border-color: transparent;
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      color: white;
      box-shadow: 0 8px 22px rgba(255, 107, 61, 0.3);
    }

    .nav-btn.primary:hover {
      transform: translateY(-3px) scale(1.05);
      box-shadow: 0 15px 35px rgba(255, 107, 61, 0.4);
    }

    /* === ENHANCED HERO SECTION === */
    .hero {
      min-height: 80vh;
      min-height: calc(var(--vh, 1vh) * 80);
      display: flex;
      align-items: center;
      padding: 100px 20px 60px;
      position: relative;
      overflow: hidden;
    }

    .hero::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: radial-gradient(circle at 30% 30%, rgba(255, 214, 194, 0.4), transparent 50%),
                  radial-gradient(circle at 70% 70%, rgba(255, 231, 214, 0.3), transparent 50%);
      z-index: -1;
    }

    .hero-content {
      max-width: 1200px;
      margin: 0 auto;
      width: 100%;
      animation: fadeInScale 1s ease-out 0.5s both;
    }

    .hero-title {
      font-size: clamp(2.5rem, 6vw, 4.5rem);
      font-weight: 950;
      line-height: 1.1;
      margin-bottom: 20px;
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      text-shadow: 0 10px 30px rgba(255, 107, 61, 0.2);
    }

    .hero-title span {
      display: block;
      color: var(--ink);
      -webkit-text-fill-color: var(--ink);
    }

    .hero-subtitle {
      font-size: clamp(1.1rem, 2.5vw, 1.5rem);
      color: var(--muted);
      margin-bottom: 40px;
      max-width: 600px;
      font-weight: 600;
    }

    .hero-stats {
      display: flex;
      gap: 40px;
      margin-bottom: 50px;
      flex-wrap: wrap;
    }

    .stat-item {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .stat-number {
      font-size: 2.5rem;
      font-weight: 950;
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }

    .stat-label {
      font-size: 0.9rem;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 1px;
      font-weight: 700;
    }

    .hero-cta {
      display: flex;
      gap: 20px;
      flex-wrap: wrap;
    }

    .cta-btn {
      padding: 16px 32px;
      font-size: 1rem;
      font-weight: 900;
      border-radius: var(--radius-lg);
      text-decoration: none;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      position: relative;
      overflow: hidden;
      min-height: 44px;
    }

    .cta-btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
      transition: left 0.6s ease;
    }

    .cta-btn:hover::before {
      left: 100%;
    }

    .cta-btn.primary {
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      color: white;
      border: none;
      box-shadow: 0 15px 40px rgba(255, 107, 61, 0.3);
    }

    .cta-btn.primary:hover {
      transform: translateY(-5px);
      box-shadow: 0 25px 60px rgba(255, 107, 61, 0.4);
    }

    .cta-btn.secondary {
      background: rgba(255, 255, 255, 0.9);
      border: 2px solid var(--brand-light);
      color: var(--ink);
    }

    .cta-btn.secondary:hover {
      background: white;
      border-color: var(--brand);
      transform: translateY(-3px);
      box-shadow: 0 15px 40px rgba(255, 107, 61, 0.2);
    }

    /* === ENHANCED FEATURED SLIDER === */
    .featured-section {
      padding: 80px 20px;
      position: relative;
    }

    .section-title {
      text-align: center;
      font-size: clamp(2rem, 4vw, 3rem);
      font-weight: 950;
      margin-bottom: 50px;
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      animation: slideInUp 0.8s ease-out;
    }

    .featured-slider {
      max-width: 1200px;
      margin: 0 auto;
      position: relative;
      height: 500px;
      border-radius: var(--radius-lg);
      overflow: hidden;
      box-shadow: var(--shadow-xl);
    }

    .slide {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      opacity: 0;
      transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1);
      display: flex;
      align-items: center;
      padding: 40px;
    }

    .slide.active {
      opacity: 1;
    }

    .slide-bg {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-size: cover;
      background-position: center;
      filter: brightness(0.7);
      z-index: 1;
      transition: transform 0.8s ease;
    }

    .slide.active .slide-bg {
      transform: scale(1.05);
    }

    .slide-content {
      position: relative;
      z-index: 2;
      max-width: 600px;
      animation: fadeInScale 0.6s ease-out 0.3s both;
    }

    .slide-category {
      display: inline-block;
      padding: 8px 20px;
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      color: white;
      border-radius: var(--radius-lg);
      font-weight: 900;
      font-size: 0.9rem;
      letter-spacing: 1px;
      margin-bottom: 20px;
      text-transform: uppercase;
      box-shadow: 0 8px 20px rgba(255, 107, 61, 0.3);
    }

    .slide-title {
      font-size: clamp(2rem, 4vw, 3.5rem);
      font-weight: 950;
      line-height: 1.1;
      margin-bottom: 20px;
      color: white;
      text-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    .slide-desc {
      font-size: 1.1rem;
      color: rgba(255, 255, 255, 0.95);
      margin-bottom: 30px;
      line-height: 1.6;
      max-width: 500px;
    }

    .slider-btn {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.9);
      border: 1px solid var(--line);
      color: var(--brand);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.3s ease;
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      z-index: 3;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
      min-height: 44px;
    }

    .slider-btn:hover {
      background: var(--brand);
      color: white;
      transform: translateY(-50%) scale(1.1);
      box-shadow: 0 12px 30px rgba(255, 107, 61, 0.4);
    }

    .slider-prev {
      left: 20px;
    }

    .slider-next {
      right: 20px;
    }

    .slider-dots {
      position: absolute;
      bottom: 30px;
      left: 50%;
      transform: translateX(-50%);
      display: flex;
      gap: 12px;
      z-index: 3;
    }

    .slider-dot {
      width: 12px;
      height: 12px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.4);
      cursor: pointer;
      transition: all 0.3s ease;
      min-width: 36px;
      min-height: 36px;
    }

    .slider-dot.active {
      background: var(--brand);
      transform: scale(1.3);
      box-shadow: 0 0 15px rgba(255, 107, 61, 0.6);
    }

    /* === ENHANCED CATEGORIES === */
    .categories-section {
      padding: 80px 20px;
    }

    .category-grid {
      max-width: 1200px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 25px;
    }

    .category-card {
      background: var(--glass);
      backdrop-filter: blur(20px);
      border-radius: var(--radius-lg);
      padding: 30px 25px;
      text-align: center;
      border: 1px solid rgba(255, 255, 255, 0.9);
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      cursor: pointer;
      position: relative;
      overflow: hidden;
      text-decoration: none;
      color: inherit;
      min-height: 44px;
    }

    .category-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 107, 61, 0.1), transparent);
      transition: left 0.6s ease;
    }

    .category-card:hover::before {
      left: 100%;
    }

    .category-card:hover {
      transform: translateY(-10px);
      border-color: var(--brand);
      box-shadow: 0 25px 50px rgba(255, 107, 61, 0.2);
      background: rgba(255, 255, 255, 0.95);
    }

    .category-icon {
      font-size: 2.5rem;
      margin-bottom: 20px;
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      transition: all 0.3s ease;
    }

    .category-card:hover .category-icon {
      transform: scale(1.2) rotate(10deg);
    }

    .category-name {
      font-size: 1.3rem;
      font-weight: 900;
      margin-bottom: 10px;
      color: var(--ink);
    }

    .category-count {
      color: var(--muted);
      font-size: 0.9rem;
      font-weight: 700;
    }

    /* === ENHANCED MENU GRID === */
    .menu-section {
      padding: 80px 20px;
    }

    .menu-grid {
      max-width: 1400px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 25px;
    }

    .product-card {
      background: var(--glass);
      backdrop-filter: blur(20px);
      border-radius: var(--radius-lg);
      overflow: hidden;
      border: 1px solid rgba(255, 255, 255, 0.9);
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      animation: fadeInScale 0.6s ease-out;
      animation-fill-mode: both;
      will-change: transform;
      backface-visibility: hidden;
      -webkit-backface-visibility: hidden;
      cursor: pointer;
      text-decoration: none;
      color: inherit;
      display: block;
    }

    .product-card:hover {
      transform: translateY(-10px);
      border-color: var(--brand);
      box-shadow: 0 30px 60px rgba(255, 107, 61, 0.25);
      background: rgba(255, 255, 255, 0.95);
    }

    .product-card::after {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      border-radius: var(--radius-lg);
      box-shadow: 0 0 0 0 rgba(255, 107, 61, 0);
      transition: all 0.4s ease;
      z-index: -1;
    }

    .product-card:hover::after {
      box-shadow: 0 30px 60px rgba(255, 107, 61, 0.25);
    }

    .product-card::before {
      content: 'Click to View';
      position: absolute;
      bottom: 10px;
      left: 50%;
      transform: translateX(-50%);
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      color: white;
      padding: 8px 16px;
      border-radius: var(--radius-sm);
      font-size: 0.8rem;
      font-weight: 900;
      opacity: 0;
      transition: opacity 0.3s ease;
      z-index: 10;
      white-space: nowrap;
      pointer-events: none;
    }

    .product-card:hover::before {
      opacity: 1;
    }

    .product-card:nth-child(2) { animation-delay: 0.1s; }
    .product-card:nth-child(3) { animation-delay: 0.2s; }
    .product-card:nth-child(4) { animation-delay: 0.3s; }
    .product-card:nth-child(5) { animation-delay: 0.4s; }
    .product-card:nth-child(6) { animation-delay: 0.5s; }

    .product-image {
      width: 100%;
      height: 220px;
      object-fit: cover;
      transition: transform 0.6s ease;
      pointer-events: none;
    }

    .product-card:hover .product-image {
      transform: scale(1.1);
    }

    .product-badge {
      position: absolute;
      top: 15px;
      left: 15px;
      padding: 8px 16px;
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      color: white;
      border-radius: var(--radius-sm);
      font-weight: 900;
      font-size: 0.8rem;
      letter-spacing: 1px;
      z-index: 2;
      box-shadow: 0 8px 20px rgba(255, 107, 61, 0.3);
      pointer-events: none;
    }

    .product-content {
      padding: 25px;
      pointer-events: none;
    }

    .product-title {
      font-size: 1.3rem;
      font-weight: 900;
      margin-bottom: 12px;
      color: var(--ink);
      text-align: center;
    }

    .product-desc {
      color: var(--muted);
      margin-bottom: 20px;
      line-height: 1.5;
      font-size: 0.95rem;
      text-align: center;
    }

    /* === ENHANCED FOOTER === */
    footer {
      margin-top: 80px;
      border-top: 1px solid rgba(255, 255, 255, 0.6);
      background: rgba(255, 255, 255, 0.4);
      backdrop-filter: blur(20px);
      padding: 60px 20px 40px;
      position: relative;
    }

    footer::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: radial-gradient(circle at 20% 80%, rgba(255, 176, 56, 0.1), transparent 50%),
                  radial-gradient(circle at 80% 20%, rgba(255, 107, 61, 0.1), transparent 50%);
      z-index: -1;
    }

    .footer-content {
      max-width: 1200px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 40px;
      margin-bottom: 40px;
    }

    .footer-section h3 {
      font-size: 1rem;
      font-weight: 900;
      margin-bottom: 20px;
      color: var(--ink);
      text-transform: uppercase;
      letter-spacing: 2px;
    }

    .footer-section p {
      color: var(--muted);
      margin-bottom: 16px;
      line-height: 1.6;
      font-weight: 600;
    }

    .social-links {
      display: flex;
      gap: 12px;
      margin-top: 20px;
    }

    .social-link {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.9);
      border: 1px solid var(--brand-light);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--brand);
      text-decoration: none;
      transition: all 0.3s ease;
      min-height: 44px;
    }

    .social-link:hover {
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      color: white;
      transform: translateY(-3px) rotate(360deg);
      box-shadow: 0 8px 20px rgba(255, 107, 61, 0.3);
    }

    .footer-bottom {
      max-width: 1200px;
      margin: 0 auto;
      padding-top: 40px;
      border-top: 1px solid rgba(255, 255, 255, 0.6);
      text-align: center;
      color: var(--muted);
      font-size: 0.9rem;
      font-weight: 600;
    }

    /* === FLOATING CART BUTTON === */
    .floating-cart {
      position: fixed;
      bottom: 30px;
      right: 30px;
      width: 70px;
      height: 70px;
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      text-decoration: none;
      box-shadow: 0 20px 50px rgba(255, 107, 61, 0.4);
      z-index: 1000;
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      animation: float 6s ease-in-out infinite;
    }

    .floating-cart:hover {
      transform: scale(1.1) rotate(15deg);
      box-shadow: 0 30px 70px rgba(255, 107, 61, 0.6);
    }

    .cart-badge {
      position: absolute;
      top: -8px;
      right: -8px;
      width: 28px;
      height: 28px;
      background: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.8rem;
      font-weight: 900;
      color: var(--brand);
      box-shadow: 0 4px 12px rgba(255, 107, 61, 0.3);
      animation: pulse-glow 2s infinite;
    }

    /* === SCROLL TO TOP BUTTON === */
    .scroll-to-top {
      position: fixed;
      bottom: 100px;
      right: 30px;
      width: 50px;
      height: 50px;
      background: rgba(255, 255, 255, 0.9);
      border: 1px solid var(--brand-light);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--brand);
      text-decoration: none;
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s ease;
      z-index: 999;
      box-shadow: 0 8px 20px rgba(255, 107, 61, 0.2);
    }

    .scroll-to-top.visible {
      opacity: 1;
      visibility: visible;
    }

    .scroll-to-top:hover {
      background: var(--brand);
      color: white;
      transform: translateY(-3px);
      box-shadow: 0 12px 30px rgba(255, 107, 61, 0.3);
    }

    /* === UTILITY CLASSES === */
    .animate-on-scroll {
      opacity: 0;
      transform: translateY(50px);
      transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .animate-on-scroll.visible {
      opacity: 1;
      transform: translateY(0);
    }

    .mobile-hide {
      display: none !important;
    }

    .mobile-show {
      display: block !important;
    }

    .mobile-stack {
      flex-direction: column !important;
    }

    .mobile-center {
      text-align: center !important;
      justify-content: center !important;
    }

    .mobile-full-width {
      width: 100% !important;
      max-width: 100% !important;
    }

    .mobile-truncate {
      display: -webkit-box !important;
      -webkit-line-clamp: 2 !important;
      -webkit-box-orient: vertical !important;
      overflow: hidden !important;
      text-overflow: ellipsis !important;
    }

    /* === MOBILE RESPONSIVENESS === */
    @media (max-width: 768px) {
      body {
        font-size: 14px;
      }
      
      .hero {
        min-height: 60vh !important;
        padding: 60px 15px 30px !important;
      }
      
      .hero-title {
        font-size: 1.8rem !important;
        line-height: 1.2 !important;
        margin-bottom: 15px !important;
      }
      
      .hero-subtitle {
        font-size: 0.95rem !important;
        margin-bottom: 25px !important;
      }
      
      .hero-stats {
        gap: 15px !important;
        margin-bottom: 30px !important;
      }
      
      .stat-item {
        flex: 1;
        min-width: calc(50% - 8px);
      }
      
      .stat-number {
        font-size: 1.5rem !important;
      }
      
      .stat-label {
        font-size: 0.75rem !important;
      }
      
      .cta-btn {
        padding: 12px 20px !important;
        font-size: 0.9rem !important;
        width: 100% !important;
        justify-content: center !important;
      }
      
      .topbar {
        padding: 8px 15px !important;
      }
      
      .topbar-inner {
        gap: 10px !important;
      }
      
      .logoImg {
        width: 40px !important;
        height: 40px !important;
      }
      
      .nav-btn {
        padding: 8px 15px !important;
        font-size: 0.85rem !important;
      }
      
      .featured-section {
        padding: 40px 15px !important;
      }
      
      .section-title {
        font-size: 1.5rem !important;
        margin-bottom: 25px !important;
      }
      
      .featured-slider {
        height: 300px !important;
        border-radius: 15px !important;
      }
      
      .slide {
        padding: 20px 15px !important;
      }
      
      .slide-category {
        padding: 6px 15px !important;
        font-size: 0.75rem !important;
        margin-bottom: 10px !important;
      }
      
      .slide-title {
        font-size: 1.3rem !important;
        margin-bottom: 10px !important;
      }
      
      .slide-desc {
        font-size: 0.85rem !important;
        margin-bottom: 15px !important;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
      }
      
      .slider-btn {
        width: 40px !important;
        height: 40px !important;
      }
      
      .slider-prev {
        left: 10px !important;
      }
      
      .slider-next {
        right: 10px !important;
      }
      
      .categories-section {
        padding: 40px 15px !important;
      }
      
      .category-grid {
        gap: 15px !important;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)) !important;
      }
      
      .category-card {
        padding: 20px 15px !important;
        border-radius: 15px !important;
      }
      
      .category-icon {
        font-size: 1.8rem !important;
        margin-bottom: 10px !important;
      }
      
      .category-name {
        font-size: 1rem !important;
        margin-bottom: 5px !important;
      }
      
      .menu-section {
        padding: 40px 15px !important;
      }
      
      .menu-grid {
        gap: 15px !important;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)) !important;
      }
      
      .product-card {
        border-radius: 15px !important;
        transition: transform 0.2s ease, box-shadow 0.2s ease !important;
      }
      
      .product-card::before {
        content: 'Tap to View';
        font-size: 0.7rem;
        padding: 4px 8px;
        bottom: 5px;
      }
      
      .product-image {
        height: 150px !important;
      }
      
      .product-content {
        padding: 15px !important;
      }
      
      .product-title {
        font-size: 1rem !important;
        margin-bottom: 8px !important;
      }
      
      .product-desc {
        font-size: 0.8rem !important;
        margin-bottom: 15px !important;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
      }
      
      footer {
        margin-top: 40px !important;
        padding: 40px 15px 30px !important;
      }
      
      .footer-content {
        gap: 25px !important;
        grid-template-columns: 1fr !important;
      }
      
      .footer-section h3 {
        font-size: 0.9rem !important;
        margin-bottom: 15px !important;
      }
      
      .footer-section p {
        font-size: 0.85rem !important;
        margin-bottom: 12px !important;
      }
      
      .social-links {
        margin-top: 15px !important;
      }
      
      .social-link {
        width: 35px !important;
        height: 35px !important;
        font-size: 0.9rem !important;
      }
      
      .footer-bottom {
        padding-top: 25px !important;
        font-size: 0.8rem !important;
      }
      
      .floating-cart {
        width: 50px !important;
        height: 50px !important;
        bottom: 15px !important;
        right: 15px !important;
      }
      
      .cart-badge {
        width: 22px !important;
        height: 22px !important;
        font-size: 0.7rem !important;
      }
      
      .scroll-to-top {
        width: 40px !important;
        height: 40px !important;
        bottom: 80px !important;
        right: 15px !important;
      }
      
      .floating-orbs {
        display: none;
      }
    }

    @media (max-width: 480px) {
      .hero {
        min-height: 50vh !important;
        padding: 50px 10px 25px !important;
      }
      
      .hero-title {
        font-size: 1.5rem !important;
      }
      
      .hero-subtitle br {
        display: none;
      }
      
      .hero-stats {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 10px !important;
      }
      
      .stat-item {
        min-width: 100%;
      }
      
      .featured-slider {
        height: 250px !important;
      }
      
      .slide-title {
        font-size: 1.1rem !important;
      }
      
      .category-grid {
        grid-template-columns: repeat(2, 1fr) !important;
      }
      
      .menu-grid {
        grid-template-columns: repeat(2, 1fr) !important;
      }
      
      .product-card {
        min-height: auto !important;
      }
      
      .product-image {
        height: 120px !important;
      }
    }

    @media (max-width: 360px) {
      .topActions {
        width: 100%;
        justify-content: space-between;
      }
      
      .nav-btn {
        flex: 1;
        text-align: center;
        justify-content: center;
        min-width: 0;
      }
      
      .nav-btn span {
        font-size: 0.75rem;
      }
      
      .category-grid,
      .menu-grid {
        grid-template-columns: 1fr !important;
      }
      
      .hero-stats {
        grid-template-columns: 1fr !important;
      }
    }

    /* === TOUCH-FRIENDLY ELEMENTS === */
    @media (hover: none) and (pointer: coarse) {
      .nav-btn,
      .cta-btn,
      .slider-btn,
      .social-link {
        min-height: 44px !important;
      }
      
      .slider-dot {
        min-height: 36px !important;
        min-width: 36px !important;
      }
      
      .product-card:hover {
        transform: none !important;
      }
      
      .category-card:hover {
        transform: none !important;
      }
      
      .product-card:active {
        transform: scale(0.98) !important;
      }
      
      .category-card:active {
        transform: scale(0.98) !important;
      }
      
      .product-card::before {
        display: none !important;
      }
    }

    /* === PERFORMANCE OPTIMIZATIONS === */
    @media (max-width: 768px) {
      .featured-slider,
      .category-card {
        will-change: transform;
        backface-visibility: hidden;
        -webkit-backface-visibility: hidden;
      }
      
      @media (max-resolution: 1dppx) {
        .topbar,
        footer,
        .product-card,
        .category-card {
          backdrop-filter: none !important;
          -webkit-backdrop-filter: none !important;
        }
      }
    }

    /* === FIX FOR IOS SAFARI === */
    @supports (-webkit-touch-callout: none) {
      .hero,
      .featured-section,
      .categories-section,
      .menu-section {
        -webkit-overflow-scrolling: touch;
      }
      
      .topbar {
        position: -webkit-sticky;
      }
    }
  </style>
</head>

<body>

<!-- Decorative Orbs -->
<div class="floating-orbs orb-1"></div>
<div class="floating-orbs orb-2"></div>
<div class="floating-orbs orb-3"></div>

<!-- Top Navigation -->
<nav class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="index.php">
      <img class="logoImg" src="/admin/uploads/logo/Screenshot 2026-01-10 at 7.30.43 PM.png" alt="THE VIBE Logo">
      <div class="brandText">
        <div class="name">THE VIBE</div>
        <div class="sub mobile-hide">Winona, MN • ENERGY, PROTEIN, BOBA, & MORE!!!</div>
      </div>
    </a>

    <div class="topActions">
      <a class="nav-btn" href="tel:5074294152">
        <i class="fas fa-phone"></i>
        <span class="mobile-hide">Call</span>
      </a>
      <a class="nav-btn" href="#menu">
        <i class="fas fa-utensils"></i>
        <span class="mobile-hide">Menu</span>
      </a>
      <a class="nav-btn primary" href="cart.php">
        <i class="fas fa-shopping-cart"></i>
        <span class="mobile-hide">Cart</span>
      </a>
    </div>
  </div>
</nav>

<!-- Hero Section -->
<section class="hero">
  <div class="hero-content">
    <h1 class="hero-title">
      FUEL YOUR<br>
      <span>VIBE</span>
    </h1>
    <p class="hero-subtitle">
      Premium energy drinks, protein shakes, boba teas, and more.<br class="mobile-hide">
      Delivered fresh to Winona, MN.
    </p>
    
    <div class="hero-stats">
      <div class="stat-item">
        <div class="stat-number">24/7</div>
        <div class="stat-label">Order Online</div>
      </div>
      <div class="stat-item">
        <div class="stat-number">30+</div>
        <div class="stat-label">Unique Drinks</div>
      </div>
      <div class="stat-item">
        <div class="stat-number">Free</div>
        <div class="stat-label">5+ Drink Delivery</div>
      </div>
    </div>
    
    <div class="hero-cta">
      <a href="#menu" class="cta-btn primary">
        <i class="fas fa-bolt"></i>
        Order Now
      </a>
      <a href="tel:5074294152" class="cta-btn secondary">
        <i class="fas fa-phone"></i>
        <span class="mobile-hide">Call: </span>507-429-4152
      </a>
    </div>
  </div>
</section>

<!-- Featured Slideshow -->
<section class="featured-section" id="menu">
  <h2 class="section-title">FEATURED FAVORITES</h2>
  
  <div class="featured-slider">
    <?php if (!empty($slideList)): ?>
      <?php foreach ($slideList as $index => $slide): ?>
        <div class="slide <?php echo $index === 0 ? 'active' : ''; ?>" data-index="<?php echo $index; ?>">
          <div class="slide-bg" style="background-image: url('<?php echo h($slide['img']); ?>');"></div>
          <div class="slide-content">
            <span class="slide-category"><?php echo h($slide['cat_name']); ?></span>
            <h3 class="slide-title"><?php echo h($slide['name']); ?></h3>
            <p class="slide-desc mobile-truncate"><?php echo h($slide['desc']); ?></p>
            <div class="slide-controls">
              <a href="add_cart.php?product_id=<?php echo $slide['product_id']; ?>" class="cta-btn primary">
                <i class="fas fa-cart-plus"></i>
                Add to Cart
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
    
    <button class="slider-btn slider-prev">
      <i class="fas fa-chevron-left"></i>
    </button>
    <button class="slider-btn slider-next">
      <i class="fas fa-chevron-right"></i>
    </button>
</section>

<!-- Categories -->
<section class="categories-section">
  <h2 class="section-title">OUR CATEGORIES</h2>
  
  <div class="category-grid">
    <?php foreach ($categories as $category): ?>
      <a href="index.php?cat=<?php echo (int)$category['category_id']; ?>" class="category-card">
        <div class="category-icon">
          <i class="fas fa-<?php 
            switch(strtolower($category['category_name'])) {
              case 'coffee': echo 'coffee'; break;
              case 'tea': echo 'mug-hot'; break;
              case 'energy': echo 'bolt'; break;
              case 'protein': echo 'dumbbell'; break;
              case 'boba': echo 'bubble'; break;
              default: echo 'glass-water';
            }
          ?>"></i>
        </div>
        <h3 class="category-name"><?php echo h($category['category_name']); ?></h3>
        <p class="category-count mobile-hide">Browse Collection</p>
      </a>
    <?php endforeach; ?>
    
    <a href="index.php" class="category-card">
      <div class="category-icon">
        <i class="fas fa-layer-group"></i>
      </div>
      <h3 class="category-name">All Products</h3>
      <p class="category-count mobile-hide">Complete Menu</p>
    </a>
  </div>
</section>

<!-- Menu Grid -->
<section class="menu-section" id="menuList">
  <h2 class="section-title">OUR MENU</h2>
  
  <?php if ($selectedCatName): ?>
    <div style="text-align: center; margin-bottom: 40px;">
      <span class="slide-category">Currently Viewing: <?php echo h($selectedCatName); ?></span>
    </div>
  <?php endif; ?>
  
  <div class="menu-grid">
    <?php if ($res && $res->num_rows > 0): ?>
      <?php while ($row = $res->fetch_assoc()): ?>
        <?php
          $pid = (int)$row["product_id"];
          $img = resolveImagePath($row["image_path"] ?? "");
        ?>
        <a href="add_cart.php?product_id=<?php echo $pid; ?>" class="product-card animate-on-scroll">
          <?php if ($img !== ""): ?>
            <img class="product-image" src="<?php echo h($img); ?>" alt="<?php echo h($row["product_name"]); ?>">
          <?php endif; ?>
          
          <div class="product-badge"><?php echo h($row["category_name"]); ?></div>
          
          <div class="product-content">
            <h3 class="product-title"><?php echo h($row["product_name"]); ?></h3>
            <p class="product-desc mobile-truncate"><?php echo h($row["description"]); ?></p>
          </div>
        </a>
      <?php endwhile; ?>
    <?php else: ?>
      <div style="grid-column: 1 / -1; text-align: center; padding: 60px 20px;">
        <div style="font-size: 4rem; color: var(--muted); margin-bottom: 20px;">
          <i class="fas fa-glass-water"></i>
        </div>
        <h3 style="font-size: 2rem; color: var(--ink); margin-bottom: 20px;">No Items Found</h3>
        <p style="color: var(--muted); font-size: 1.2rem;">Try selecting a different category!</p>
        <a href="index.php" class="cta-btn primary" style="margin-top: 30px;">
          <i class="fas fa-layer-group"></i>
          View All Products
        </a>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Footer -->
<footer>
  <div class="footer-content">
    <div class="footer-section">
      <h3>Contact Us</h3>
      <p><i class="fas fa-phone"></i> 507-429-4152</p>
      <p><i class="fas fa-map-marker-alt"></i> 350 E Sarnia St, Winona, MN</p>
      <p><i class="fas fa-clock"></i> Mon–Fri: 9am–1pm</p>
      <p><i class="fas fa-bolt"></i> Always available online</p>
    </div>
    
    <div class="footer-section">
      <h3>Quick Links</h3>
      <p><a href="#menu" style="color: var(--muted); text-decoration: none;">Our Menu</a></p>
      <p><a href="cart.php" style="color: var(--muted); text-decoration: none;">Your Cart</a></p>
      <p><a href="tel:5074294152" style="color: var(--muted); text-decoration: none;">Place Order</a></p>
      <p><a href="#menuList" style="color: var(--muted); text-decoration: none;">Browse All</a></p>
    </div>
    
    <div class="footer-section">
      <h3>Follow Us</h3>
      <div class="social-links">
        <a href="#" class="social-link">
          <i class="fab fa-instagram"></i>
        </a>
        <a href="#" class="social-link">
          <i class="fab fa-facebook"></i>
        </a>
        <a href="#" class="social-link">
          <i class="fab fa-tiktok"></i>
        </a>
        <a href="#" class="social-link">
          <i class="fab fa-twitter"></i>
        </a>
      </div>
    </div>
  </div>
  
  <div class="footer-bottom">
    <p>&copy; 2024 THE VIBE. All rights reserved. | Winona, MN</p>
    <p style="margin-top: 10px; font-size: 0.9rem; color: var(--muted);">
      Energy • Protein • Boba • Coffee • Smoothies • Refreshers
    </p>
  </div>
</footer>

<!-- Floating Cart Button -->
<a href="cart.php" class="floating-cart">
  <i class="fas fa-shopping-cart"></i>
  <div class="cart-badge">
    <?php 
      $cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
      echo $cartCount > 9 ? '9+' : $cartCount;
    ?>
  </div>
</a>

<!-- Scroll to Top Button -->
<a href="#" class="scroll-to-top">
  <i class="fas fa-arrow-up"></i>
</a>

<!-- JavaScript -->
<script>
// === MOBILE ADJUSTMENTS ===
function adjustForMobile() {
  const isMobile = window.innerWidth <= 768;
  
  if (isMobile) {
    // Simplify animations on mobile
    document.querySelectorAll('.animate-on-scroll').forEach(el => {
      el.style.animation = 'none';
      el.style.opacity = '1';
      el.style.transform = 'none';
    });
    
    // Adjust font sizes dynamically
    const scale = Math.min(1, window.innerWidth / 375);
    document.documentElement.style.fontSize = `${14 * scale}px`;
  }
}

// iOS 100vh fix
function setVH() {
  const vh = window.innerHeight * 0.01;
  document.documentElement.style.setProperty('--vh', `${vh}px`);
}

// Initial adjustments
adjustForMobile();
setVH();

// Adjust on resize (with debounce)
let resizeTimer;
window.addEventListener('resize', () => {
  clearTimeout(resizeTimer);
  resizeTimer = setTimeout(() => {
    adjustForMobile();
    setVH();
  }, 250);
});

// Prevent zoom on double-tap (iOS)
let lastTouchEnd = 0;
document.addEventListener('touchend', (e) => {
  const now = Date.now();
  if (now - lastTouchEnd <= 300) {
    e.preventDefault();
  }
  lastTouchEnd = now;
}, false);

// === FEATURED SLIDER ===
const slides = document.querySelectorAll('.slide');
const dots = document.querySelectorAll('.slider-dot');
const prevBtn = document.querySelector('.slider-prev');
const nextBtn = document.querySelector('.slider-next');
let currentSlide = 0;
let slideInterval;

function showSlide(index) {
  slides.forEach(slide => slide.classList.remove('active'));
  dots.forEach(dot => dot.classList.remove('active'));
  
  currentSlide = (index + slides.length) % slides.length;
  slides[currentSlide].classList.add('active');
  dots[currentSlide].classList.add('active');
}

function nextSlide() {
  showSlide(currentSlide + 1);
}

function prevSlide() {
  showSlide(currentSlide - 1);
}

function startSlideshow() {
  slideInterval = setInterval(nextSlide, 5000);
}

// Event Listeners
prevBtn.addEventListener('click', () => {
  prevSlide();
  clearInterval(slideInterval);
  startSlideshow();
});

nextBtn.addEventListener('click', () => {
  nextSlide();
  clearInterval(slideInterval);
  startSlideshow();
});

dots.forEach(dot => {
  dot.addEventListener('click', () => {
    const index = parseInt(dot.dataset.index);
    showSlide(index);
    clearInterval(slideInterval);
    startSlideshow();
  });
});

// Start slideshow
if (slides.length > 0) {
  startSlideshow();
}

// === ANIMATE ON SCROLL ===
const observerOptions = {
  threshold: 0.1,
  rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('visible');
    }
  });
}, observerOptions);

document.querySelectorAll('.animate-on-scroll').forEach(el => {
  observer.observe(el);
});

// === SCROLL TO TOP BUTTON ===
const scrollToTopBtn = document.querySelector('.scroll-to-top');

window.addEventListener('scroll', () => {
  if (window.scrollY > 500) {
    scrollToTopBtn.classList.add('visible');
  } else {
    scrollToTopBtn.classList.remove('visible');
  }
});

scrollToTopBtn.addEventListener('click', (e) => {
  e.preventDefault();
  window.scrollTo({
    top: 0,
    behavior: 'smooth'
  });
});

// === PARALLAX EFFECT FOR ORBS ===
window.addEventListener('scroll', () => {
  const scrolled = window.pageYOffset;
  const orbs = document.querySelectorAll('.floating-orbs');
  
  orbs.forEach((orb, index) => {
    const speed = 0.2 + (index * 0.1);
    const yPos = -(scrolled * speed);
    orb.style.transform = `translateY(${yPos}px)`;
  });
});

// === SMOOTH SCROLLING ===
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function (e) {
    e.preventDefault();
    const targetId = this.getAttribute('href');
    if (targetId === '#') return;
    
    const targetElement = document.querySelector(targetId);
    if (targetElement) {
      window.scrollTo({
        top: targetElement.offsetTop - 100,
        behavior: 'smooth'
      });
    }
  });
});

// === HOVER EFFECTS FOR CARDS ===
document.querySelectorAll('.product-card, .category-card').forEach(card => {
  card.addEventListener('mouseenter', () => {
    card.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
  });
});

// === CART BADGE UPDATE ===
function updateCartBadge() {
  const badge = document.querySelector('.cart-badge');
  if (badge) {
    // Could implement AJAX for real-time cart updates
  }
}

// Initialize
updateCartBadge();

// === PRODUCT CARD CLICK EFFECT ===
document.querySelectorAll('.product-card').forEach(card => {
  card.addEventListener('click', function(e) {
    // Add a click feedback effect
    this.style.transform = 'scale(0.98)';
    setTimeout(() => {
      this.style.transform = '';
    }, 150);
  });
});
</script>
<a href="admin/admin_login.php"> .</a>

</body>
</html>