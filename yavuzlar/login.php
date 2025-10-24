<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string)post('email', '')));
    $password = (string)post('password', '');

    if ($email === '' || $password === '') {
        $error = 'E-posta ve sifre zorunludur.';
    } else {
        $user = find_user_by_email($email);
        if (!$user || !verify_password($password, $user['password'])) {
            $error = 'Gecersiz giris bilgileri.';
        } else {
            session_regenerate_id(true);
            store_user_session($user);
            $target = isset($_GET['redirect']) ? (string)$_GET['redirect'] : 'dashboard.php';
            flash('success', 'Hos geldiniz, ' . $user['full_name'] . '!');
            redirect($target);
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="row justify-content-center">
    <div class="col-md-8 col-lg-5">
        <div class="card border-0 shadow-sm card-lift">
            <div class="card-body p-4 p-lg-5">
                <h1 class="h4 mb-2">Giris Yap</h1>
                <p class="text-muted small mb-4">Hesabiniza giris yapin ve seferlerinizi yonetin.</p>
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert"><?php echo sanitize($error); ?></div>
                <?php endif; ?>
                <form method="post" class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="email">E-posta</label>
                        <input class="form-control" type="email" name="email" id="email" required value="<?php echo sanitize((string)post('email', '')); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="password">Sifre</label>
                        <input class="form-control" type="password" name="password" id="password" required>
                    </div>
                    <div class="col-12 d-grid">
                        <button class="btn btn-primary" type="submit">Giris Yap</button>
                    </div>
                </form>
                <p class="form-note text-center mt-4">Henuz hesabiniz yok mu? <a href="register.php">Kayit olun.</a></p>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>