<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)post('name', ''));
    $email = strtolower(trim((string)post('email', '')));
    $password = (string)post('password', '');
    $confirm = (string)post('confirm', '');

    if ($name === '' || $email === '' || $password === '') {
        $error = 'Tum alanlar zorunludur.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Gecerli bir e-posta adresi giriniz.';
    } elseif ($password !== $confirm) {
        $error = 'Sifreler eslesmiyor.';
    } elseif (find_user_by_email($email)) {
        $error = 'Bu e-posta ile kayit bulunuyor.';
    } else {
        $userId = create_user($name, $email, $password);
        $user = find_user_by_id($userId);
        if ($user) {
            session_regenerate_id(true);
            store_user_session($user);
            flash('success', 'Kaydiniz tamamlandi. Bakiye: 200 TL');
            redirect('dashboard.php');
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm card-lift">
            <div class="card-body p-4 p-lg-5">
                <h1 class="h4 mb-2">Kayit Ol</h1>
                <p class="text-muted small mb-4">Yeni hesap olusturun ve seferlerinizi kolayca yonetin.</p>
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert"><?php echo sanitize($error); ?></div>
                <?php endif; ?>
                <form method="post" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="name">Ad Soyad</label>
                        <input class="form-control" type="text" name="name" id="name" required value="<?php echo sanitize((string)post('name', '')); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">E-posta</label>
                        <input class="form-control" type="email" name="email" id="email" required value="<?php echo sanitize((string)post('email', '')); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="password">Sifre</label>
                        <input class="form-control" type="password" name="password" id="password" required minlength="6">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="confirm">Sifre Tekrar</label>
                        <input class="form-control" type="password" name="confirm" id="confirm" required minlength="6">
                    </div>
                    <div class="col-12 d-grid">
                        <button class="btn btn-primary" type="submit">Kayit Ol</button>
                    </div>
                </form>
                <p class="form-note text-center mt-4">Hesabiniz var mi? <a href="login.php">Giris yapin.</a></p>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>