<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

require_login();
require_role(['admin']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    if ($action === 'create') {
        $code = strtoupper(trim((string)post('code', '')));
        $percent = (float)post('discount_percent', 0);
        $limit = (int)post('usage_limit', 0);
        $expires = trim((string)post('expires_at', ''));

        if ($code === '' || $percent <= 0 || $percent > 100 || $limit <= 0) {
            flash('error', 'Kupon bilgilerini kontrol edin.');
        } elseif (coupon_by_code($code)) {
            flash('error', 'Bu kupon kodu zaten kayitli.');
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO coupons (id, code, discount, usage_limit, expire_date) VALUES (:id, :code, :discount, :usage_limit, :expire_date)');
                $stmt->execute([
                    ':id' => uuidv4(),
                    ':code' => $code,
                    ':discount' => $percent,
                    ':usage_limit' => $limit,
                    ':expire_date' => $expires !== '' ? $expires : null,
                ]);
                flash('success', 'Kupon olusturuldu.');
            } catch (Throwable $e) {
                flash('error', 'Kupon olusturulurken hata: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'update') {
        $id = trim((string)post('id', ''));
        $code = strtoupper(trim((string)post('code', '')));
        $percent = (float)post('discount_percent', 0);
        $limit = (int)post('usage_limit', 0);
        $expires = trim((string)post('expires_at', ''));

        if ($id === '' || $code === '' || $percent <= 0 || $percent > 100 || $limit <= 0) {
            flash('error', 'Guncelleme icin verileri kontrol edin.');
        } else {
            $existing = $pdo->prepare('SELECT id FROM coupons WHERE code = :code AND id != :id');
            $existing->execute([':code' => $code, ':id' => $id]);
            if ($existing->fetch()) {
                flash('error', 'Bu kod baska bir kupona ait.');
            } else {
                try {
                    $pdo->prepare('UPDATE coupons SET code = :code, discount = :discount, usage_limit = :limit, expire_date = :expire WHERE id = :id')->execute([
                        ':code' => $code,
                        ':discount' => $percent,
                        ':limit' => $limit,
                        ':expire' => $expires !== '' ? $expires : null,
                        ':id' => $id,
                    ]);
                    flash('success', 'Kupon guncellendi.');
                } catch (Throwable $e) {
                    flash('error', 'Guncelleme basarisiz: ' . $e->getMessage());
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = trim((string)post('id', ''));
        if ($id === '') {
            flash('error', 'Kupon bulunamadi.');
        } else {
            try {
                $pdo->prepare('DELETE FROM coupons WHERE id = :id')->execute([':id' => $id]);
                flash('success', 'Kupon silindi.');
            } catch (Throwable $e) {
                flash('error', 'Silme islemi basarisiz: ' . $e->getMessage());
            }
        }
    }

    redirect('admin_coupons.php');
}

$coupons = $pdo->query('SELECT * FROM coupons ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/includes/header.php';
?>
<section class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm card-lift h-100">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">Kupon Olustur</h2>
                <form method="post" class="row g-3">
                    <input type="hidden" name="action" value="create">
                    <div class="col-12">
                        <label class="form-label">Kod</label>
                        <input class="form-control" type="text" name="code" maxlength="32" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Indirim Orani (%)</label>
                        <input class="form-control" type="number" name="discount_percent" min="1" max="100" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Kullanim Limiti</label>
                        <input class="form-control" type="number" name="usage_limit" min="1" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Son Kullanma Tarihi</label>
                        <input class="form-control" type="date" name="expires_at">
                    </div>
                    <div class="col-12 d-grid">
                        <button class="btn btn-primary" type="submit">Kupon Ekle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm card-lift h-100">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">Mevcut Kuponlar</h2>
                <?php if (!$coupons): ?>
                    <div class="alert alert-info mb-0" role="alert">Kupon kaydi bulunmuyor.</div>
                <?php else: ?>
                    <div class="d-grid gap-3">
                        <?php foreach ($coupons as $coupon): ?>
                            <?php $used = coupon_usage_count($coupon['id']); ?>
                            <div class="border rounded p-3">
                                <form method="post" class="row g-3">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?php echo sanitize($coupon['id']); ?>">
                                    <div class="col-md-4">
                                        <label class="form-label">Kod</label>
                                        <input class="form-control" type="text" name="code" value="<?php echo sanitize($coupon['code']); ?>" maxlength="32" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Indirim (%)</label>
                                        <input class="form-control" type="number" name="discount_percent" value="<?php echo (float)$coupon['discount']; ?>" min="1" max="100" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Kullanim Limiti</label>
                                        <input class="form-control" type="number" name="usage_limit" value="<?php echo (int)$coupon['usage_limit']; ?>" min="1" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Son Tarih</label>
                                        <input class="form-control" type="date" name="expires_at" value="<?php echo $coupon['expire_date'] ? sanitize(substr($coupon['expire_date'], 0, 10)) : ''; ?>">
                                    </div>
                                    <div class="col-md-6 text-muted small d-flex flex-column justify-content-center">
                                        <span>Kullanildi: <?php echo $used; ?> / <?php echo (int)$coupon['usage_limit']; ?></span>
                                    </div>
                                    <div class="col-md-6 d-grid">
                                        <button class="btn btn-outline-primary" type="submit">Guncelle</button>
                                    </div>
                                </form>
                                <form method="post" action="admin_coupons.php" class="mt-2 text-end" onsubmit="return confirm('Kuponu silmek istediginize emin misiniz?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo sanitize($coupon['id']); ?>">
                                    <button class="btn btn-outline-danger btn-sm" type="submit">Sil</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>