<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
$user = current_user();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yavuzlar Bilet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="bg-light d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="index.php">Yavuzlar Bilet</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Menuyu Ac">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="index.php">Seferler</a></li>
                <?php if ($user): ?>
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Panel</a></li>
                    <?php if ($user['role'] === 'user'): ?>
                        <li class="nav-item"><a class="nav-link" href="balance_topup.php">Bakiye Yukle</a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <?php if ($user): ?>
                    <span class="navbar-text text-white small">
                        Merhaba, <?php echo sanitize($user['name']); ?> (<?php echo strtoupper($user['role']); ?>)
                    </span>
                    <?php if ($user['role'] === 'user'): ?>
                        <span class="badge badge-balance fw-semibold">Bakiye <?php echo number_format((float)$user['balance'], 2); ?> TL</span>
                    <?php endif; ?>
                    <a class="btn btn-outline-light btn-sm" href="logout.php">Cikis Yap</a>
                <?php else: ?>
                    <a class="btn btn-outline-light btn-sm" href="login.php">Giris Yap</a>
                    <a class="btn btn-light btn-sm" href="register.php">Kayit Ol</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<main class="container py-4">
    <?php if ($message = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo sanitize($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
        </div>
    <?php endif; ?>
    <?php if ($message = flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo sanitize($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
        </div>
    <?php endif; ?>