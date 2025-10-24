<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

require_login();
require_role(['admin']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    if ($action === 'create') {
        $name = trim((string)post('name', ''));
        $logo = trim((string)post('logo_path', ''));
        if ($name === '') {
            flash('error', 'Firma adi zorunludur.');
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO bus_companies (id, name, logo_path) VALUES (:id, :name, :logo)');
                $stmt->execute([
                    ':id' => uuidv4(),
                    ':name' => $name,
                    ':logo' => $logo !== '' ? $logo : null,
                ]);
                flash('success', 'Firma eklendi.');
            } catch (Throwable $e) {
                flash('error', 'Firma eklenemedi: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'update') {
        $id = trim((string)post('id', ''));
        $name = trim((string)post('name', ''));
        $logo = trim((string)post('logo_path', ''));
        if ($id === '' || $name === '') {
            flash('error', 'Firma guncelleme icin veri eksik.');
        } else {
            try {
                $stmt = $pdo->prepare('UPDATE bus_companies SET name = :name, logo_path = :logo WHERE id = :id');
                $stmt->execute([
                    ':name' => $name,
                    ':logo' => $logo !== '' ? $logo : null,
                    ':id' => $id,
                ]);
                flash('success', 'Firma guncellendi.');
            } catch (Throwable $e) {
                flash('error', 'Firma guncellenemedi: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'delete') {
        $id = trim((string)post('id', ''));
        if ($id === '') {
            flash('error', 'Silinecek firma bulunamadi.');
        } else {
            try {
                $stmt = $pdo->prepare('DELETE FROM bus_companies WHERE id = :id');
                $stmt->execute([':id' => $id]);
                flash('success', 'Firma silindi.');
            } catch (Throwable $e) {
                flash('error', 'Firma silinemedi: ' . $e->getMessage());
            }
        }
    }

    redirect('admin_firms.php');
}

$companies = $pdo->query('SELECT * FROM bus_companies ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/includes/header.php';
?>
<section class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm card-lift h-100">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">Yeni Firma</h2>
                <form method="post" class="row g-3">
                    <input type="hidden" name="action" value="create">
                    <div class="col-12">
                        <label class="form-label" for="firm-name">Firma Adi</label>
                        <input class="form-control" type="text" name="name" id="firm-name" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="firm-logo">Logo Yolu</label>
                        <input class="form-control" type="text" name="logo_path" id="firm-logo" placeholder="Orn: assets/images/logo.png">
                    </div>
                    <div class="col-12 d-grid">
                        <button class="btn btn-primary" type="submit">Firma Ekle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm card-lift h-100">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">Kayitli Firmalar</h2>
                <?php if (!$companies): ?>
                    <div class="alert alert-info mb-0" role="alert">Henuz firma bulunmuyor.</div>
                <?php else: ?>
                    <div class="d-grid gap-3">
                        <?php foreach ($companies as $company): ?>
                            <div class="border rounded p-3">
                                <form method="post" class="row g-3 align-items-end">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?php echo sanitize($company['id']); ?>">
                                    <div class="col-md-5">
                                        <label class="form-label">Firma Adi</label>
                                        <input class="form-control" type="text" name="name" value="<?php echo sanitize($company['name']); ?>" required>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label">Logo Yolu</label>
                                        <input class="form-control" type="text" name="logo_path" value="<?php echo sanitize((string)$company['logo_path']); ?>">
                                    </div>
                                    <div class="col-md-2 d-grid">
                                        <button class="btn btn-outline-primary" type="submit">Guncelle</button>
                                    </div>
                                </form>
                                <form method="post" action="admin_firms.php" class="mt-2 text-end" onsubmit="return confirm('Firmayi silmek istediginize emin misiniz?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo sanitize($company['id']); ?>">
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