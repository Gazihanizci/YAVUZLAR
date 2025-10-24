<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

require_login();
require_role(['user']);
refresh_user_session();
$user = current_user();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amountRaw = (string)post('amount', '0');
    $amount = (float)$amountRaw;

    if (!is_numeric($amountRaw) || $amount <= 0) {
        $error = 'Gecerli bir tutar giriniz.';
    } elseif ($amount > 10000) {
        $error = 'Tek seferde en fazla 10.000 TL yukleyebilirsiniz.';
    } else {
        if (adjust_user_balance($user['id'], $amount)) {
            flash('success', number_format($amount, 2) . ' TL bakiyenize eklendi.');
            refresh_user_session();
            redirect('balance_topup.php');
        } else {
            $error = 'Bakiye guncellenemedi. Lutfen tekrar deneyin.';
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="row justify-content-center">
    <div class="col-md-8 col-lg-5">
        <div class="card border-0 shadow-sm card-lift">
            <div class="card-body p-4 p-lg-5">
                <h1 class="h4 mb-2">Bakiye Yukle</h1>
                <p class="text-muted small mb-4">Mevcut bakiye: <strong><?php echo number_format((float)$user['balance'], 2); ?> TL</strong></p>
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert"><?php echo sanitize($error); ?></div>
                <?php endif; ?>
                <form method="post" class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="amount">Yuklenecek Tutar (TL)</label>
                        <input class="form-control" type="number" name="amount" id="amount" min="1" max="10000" step="0.01" required value="<?php echo sanitize((string)post('amount', '100')); ?>">
                    </div>
                    <div class="col-12 d-grid gap-2 d-md-flex justify-content-md-start">
                        <button class="btn btn-primary" type="submit">Bakiyeye Ekle</button>
                        <a class="btn btn-outline-secondary" href="dashboard.php">Panele Don</a>
                    </div>
                </form>
                <p class="form-note mt-4">Bu islem sanal bir bakiye yuklemesidir; gercek odeme sistemine baglanmaz.</p>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>