<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

require_login();
refresh_user_session();
$user = current_user();

require __DIR__ . '/includes/header.php';
?>
<section class="mb-4">
    <div class="card border-0 shadow-sm card-lift">
        <div class="card-body p-4 p-lg-5">
            <p class="text-muted small mb-2">Rolunuz: <strong><?php echo strtoupper($user['role']); ?></strong></p>
            <h1 class="h3 mb-0">Hos geldiniz, <?php echo sanitize($user['name']); ?></h1>
        </div>
    </div>
</section>

<?php if ($user['role'] === 'user'): ?>
    <section class="card border-0 shadow-sm card-lift mb-4">
        <div class="card-body p-4">
            <h2 class="h5 mb-3">Hizli Islemler</h2>
            <div class="row g-3">
                <div class="col-sm-6 col-lg-4 d-grid">
                    <a class="btn btn-outline-primary" href="index.php">Sefer Ara</a>
                </div>
                <div class="col-sm-6 col-lg-4 d-grid">
                    <a class="btn btn-outline-primary" href="tickets.php">Biletlerimi Goruntule</a>
                </div>
                <div class="col-sm-6 col-lg-4 d-grid">
                    <a class="btn btn-outline-primary" href="balance_topup.php">Bakiye Yukle</a>
                </div>
            </div>
        </div>
    </section>
<?php elseif ($user['role'] === 'company'): ?>
    <section class="card border-0 shadow-sm card-lift mb-4">
        <div class="card-body p-4">
            <h2 class="h5 mb-3">Firma Yetkilisi Paneli</h2>
            <div class="row g-3">
                <div class="col-sm-6 d-grid">
                    <a class="btn btn-outline-primary" href="trips_manage.php">Seferleri Yonet</a>
                </div>
                <div class="col-sm-6 d-grid">
                    <a class="btn btn-outline-primary" href="tickets_overview.php">Satislari Izle</a>
                </div>
            </div>
        </div>
    </section>
<?php elseif ($user['role'] === 'admin'): ?>
    <section class="card border-0 shadow-sm card-lift mb-4">
        <div class="card-body p-4">
            <h2 class="h5 mb-3">Yonetici Paneli</h2>
            <div class="row g-3">
                <div class="col-md-4 d-grid">
                    <a class="btn btn-outline-primary" href="admin_firms.php">Firmalari Yonet</a>
                </div>
                <div class="col-md-4 d-grid">
                    <a class="btn btn-outline-primary" href="admin_users.php">Firma Yetkililerini Ata</a>
                </div>
                <div class="col-md-4 d-grid">
                    <a class="btn btn-outline-primary" href="admin_coupons.php">Kuponlari Yonet</a>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
