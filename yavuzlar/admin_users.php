<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

require_login();
require_role(['admin']);

$pdo = db();
$companies = $pdo->query('SELECT id, name FROM bus_companies ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    if ($action === 'create') {
        $name = trim((string)post('name', ''));
        $email = strtolower(trim((string)post('email', '')));
        $password = (string)post('password', '');
        $companyId = trim((string)post('company_id', ''));

        if ($name === '' || $email === '' || $password === '' || $companyId === '') {
            flash('error', 'Tum alanlari doldurunuz.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Gecerli bir e-posta giriniz.');
        } elseif (find_user_by_email($email)) {
            flash('error', 'Bu e-posta zaten kullaniliyor.');
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO users (id, full_name, email, role, password, company_id, balance) VALUES (:id, :full_name, :email, :role, :password, :company_id, 0)');
                $stmt->execute([
                    ':id' => uuidv4(),
                    ':full_name' => $name,
                    ':email' => $email,
                    ':role' => 'company',
                    ':password' => password_hash($password, PASSWORD_DEFAULT),
                    ':company_id' => $companyId,
                ]);
                flash('success', 'Firma yetkilisi olusturuldu.');
            } catch (Throwable $e) {
                flash('error', 'Kayit basarisiz: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'update') {
        $id = trim((string)post('id', ''));
        $name = trim((string)post('name', ''));
        $email = strtolower(trim((string)post('email', '')));
        $companyId = trim((string)post('company_id', ''));
        $password = (string)post('password', '');

        if ($id === '' || $name === '' || $email === '' || $companyId === '') {
            flash('error', 'Guncelleme icin veri eksik.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Gecerli bir e-posta giriniz.');
        } else {
            $existing = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id');
            $existing->execute([':email' => $email, ':id' => $id]);
            if ($existing->fetch()) {
                flash('error', 'Bu e-posta baska bir kullaniciya ait.');
            } else {
                try {
                    $pdo->prepare('UPDATE users SET full_name = :full_name, email = :email, company_id = :company_id WHERE id = :id AND role = "company"')->execute([
                        ':full_name' => $name,
                        ':email' => $email,
                        ':company_id' => $companyId,
                        ':id' => $id,
                    ]);
                    if ($password !== '') {
                        $pdo->prepare('UPDATE users SET password = :password WHERE id = :id')->execute([
                            ':password' => password_hash($password, PASSWORD_DEFAULT),
                            ':id' => $id,
                        ]);
                    }
                    flash('success', 'Yetkili guncellendi.');
                } catch (Throwable $e) {
                    flash('error', 'Guncelleme basarisiz: ' . $e->getMessage());
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = trim((string)post('id', ''));
        if ($id === '') {
            flash('error', 'Silinecek kayit bulunamadi.');
        } else {
            try {
                $pdo->prepare('DELETE FROM users WHERE id = :id AND role = "company"')->execute([':id' => $id]);
                flash('success', 'Yetkili silindi.');
            } catch (Throwable $e) {
                flash('error', 'Silme islemi basarisiz: ' . $e->getMessage());
            }
        }
    }

    redirect('admin_users.php');
}

$stmt = $pdo->query('SELECT users.*, bus_companies.name AS company_name FROM users LEFT JOIN bus_companies ON users.company_id = bus_companies.id WHERE users.role = "company" ORDER BY users.created_at DESC');
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/includes/header.php';
?>
<section class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm card-lift h-100">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">Yeni Firma Yetkilisi</h2>
                <form method="post" class="row g-3">
                    <input type="hidden" name="action" value="create">
                    <div class="col-12">
                        <label class="form-label">Ad Soyad</label>
                        <input class="form-control" type="text" name="name" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">E-posta</label>
                        <input class="form-control" type="email" name="email" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Sifre</label>
                        <input class="form-control" type="password" name="password" required minlength="6">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Firma</label>
                        <select class="form-select" name="company_id" required>
                            <option value="">Firma secin</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?php echo sanitize($company['id']); ?>"><?php echo sanitize($company['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 d-grid">
                        <button class="btn btn-primary" type="submit">Yetkili Olustur</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm card-lift h-100">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">Yetkili Listesi</h2>
                <?php if (!$admins): ?>
                    <div class="alert alert-info mb-0" role="alert">Kayitli firma yetkilisi bulunmuyor.</div>
                <?php else: ?>
                    <div class="d-grid gap-3">
                        <?php foreach ($admins as $admin): ?>
                            <div class="border rounded p-3">
                                <form method="post" class="row g-3 align-items-end">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?php echo sanitize($admin['id']); ?>">
                                    <div class="col-md-4">
                                        <label class="form-label">Ad Soyad</label>
                                        <input class="form-control" type="text" name="name" value="<?php echo sanitize($admin['full_name']); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">E-posta</label>
                                        <input class="form-control" type="email" name="email" value="<?php echo sanitize($admin['email']); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Firma</label>
                                        <select class="form-select" name="company_id" required>
                                            <option value="">Firma secin</option>
                                            <?php foreach ($companies as $company): ?>
                                                <option value="<?php echo sanitize($company['id']); ?>" <?php echo $admin['company_id'] === $company['id'] ? 'selected' : ''; ?>><?php echo sanitize($company['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Yeni Sifre (opsiyonel)</label>
                                        <input class="form-control" type="password" name="password" placeholder="Degistirmek icin giriniz">
                                    </div>
                                    <div class="col-md-6 text-muted small">
                                        <div>ID: <?php echo sanitize(substr($admin['id'], 0, 8)); ?>...</div>
                                        <div>Firma: <?php echo sanitize((string)$admin['company_name']); ?></div>
                                    </div>
                                    <div class="col-12 d-grid">
                                        <button class="btn btn-outline-primary" type="submit">Guncelle</button>
                                    </div>
                                </form>
                                <form method="post" action="admin_users.php" class="mt-2 text-end" onsubmit="return confirm('Kullaniciyi silmek istediginize emin misiniz?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo sanitize($admin['id']); ?>">
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