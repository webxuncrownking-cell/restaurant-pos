<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#2563eb">
  <link rel="manifest" href="<?= htmlspecialchars(BASE_URL . '/manifest.json', ENT_QUOTES, 'UTF-8') ?>">
  <title><?= htmlspecialchars($title ?? 'Restaurant POS & Management') ?></title>
  
  <!-- CSRF Meta Tag for Fetch API Requests -->
  <?= \App\Core\CSRF::metaHeader() ?>
  
  <!-- Bootstrap 5.3 CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <!-- Custom POS Theme Variables -->
  <?php
    $primaryColor = $restaurant['pos_primary_color'] ?? '#d97706';
    $secondaryColor = $restaurant['pos_secondary_color'] ?? '#059669';
  ?>
  <style>
    :root {
      --pos-primary: <?= htmlspecialchars($primaryColor) ?>;
      --pos-secondary: <?= htmlspecialchars($secondaryColor) ?>;
    }
    body {
      background-color: #f8fafc;
      font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    .navbar-brand-badge {
      background-color: var(--pos-primary);
      color: #fff;
      font-weight: 700;
      padding: 4px 10px;
      border-radius: 6px;
    }
    .btn-pos-primary {
      background-color: var(--pos-primary);
      border-color: var(--pos-primary);
      color: #fff;
    }
    .btn-pos-primary:hover {
      filter: brightness(0.9);
      color: #fff;
    }
    .status-pill {
      font-size: 0.75rem;
      padding: 4px 8px;
      border-radius: 20px;
      font-weight: 600;
    }
    .card-metric {
      border: none;
      border-radius: 12px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.03);
      transition: transform 0.15s ease;
    }
    .card-metric:hover {
      transform: translateY(-2px);
    }
  </style>
</head>
<body>
