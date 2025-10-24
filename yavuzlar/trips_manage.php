<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

require_login();
require_role(['company']);
refresh_user_session();
$user = current_user();

if (empty($user['company_id'])) {
    flash('error', 'Henuz bir firmaya bagli degilsiniz. Yoneticiye basvurun.');
    redirect('dashboard.php');
}

$pdo = db();

function parse_datetime(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);
    if (!$dt) {
        return null;
    }
    return $dt->format('Y-m-d H:i:s');
}

function booked_seat_count(PDO $pdo, string $tripId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(bs.id) FROM tickets t JOIN booked_seats bs ON bs.ticket_id = t.id WHERE t.trip_id = :trip AND t.status = "active"');
    $stmt->execute([':trip' => $tripId]);
    return (int)$stmt->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    if ($action === 'create') {
        $departureCity = trim((string)post('departure_city', ''));
        $destinationCity = trim((string)post('destination_city', ''));
        $departureAt = parse_datetime((string)post('departure_at', ''));
        $arrivalAt = parse_datetime((string)post('arrival_at', ''));
        $price = (float)post('price', 0);
        $capacity = (int)post('capacity', 0);

        if ($departureCity === '' || $destinationCity === '' || !$departureAt || $price <= 0 || $capacity <= 0) {
            flash('error', 'Sefer bilgilerini kontrol edin.');
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO trips (id, company_id, departure_city, destination_city, departure_time, arrival_time, price, capacity) VALUES (:id, :company, :departure_city, :destination_city, :departure_time, :arrival_time, :price, :capacity)');
                $stmt->execute([
                    ':id' => uuidv4(),
                    ':company' => $user['company_id'],
                    ':departure_city' => $departureCity,
                    ':destination_city' => $destinationCity,
                    ':departure_time' => $departureAt,
                    ':arrival_time' => $arrivalAt,
                    ':price' => $price,
                    ':capacity' => $capacity,
                ]);
                flash('success', 'Sefer eklendi.');
            } catch (Throwable $e) {
                flash('error', 'Sefer eklenemedi: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'update') {
        $id = trim((string)post('id', ''));
        $departureCity = trim((string)post('departure_city', ''));
        $destinationCity = trim((string)post('destination_city', ''));
        $departureAt = parse_datetime((string)post('departure_at', ''));
        $arrivalAt = parse_datetime((string)post('arrival_at', ''));
        $price = (float)post('price', 0);
        $capacity = (int)post('capacity', 0);

        if ($id === '' || $departureCity === '' || $destinationCity === '' || !$departureAt || $price <= 0 || $capacity <= 0) {
            flash('error', 'Guncelleme icin verileri kontrol edin.');
        } else {
            $stmt = $pdo->prepare('SELECT capacity FROM trips WHERE id = :id AND company_id = :company');
            $stmt->execute([':id' => $id, ':company' => $user['company_id']]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                flash('error', 'Sefer bulunamadi.');
            } else {
                $booked = booked_seat_count($pdo, $id);
                if ($capacity < $booked) {
                    flash('error', 'Koltuk kapasitesi, satilan koltuklardan az olamaz.');
                } else {
                    try {
                        $pdo->prepare('UPDATE trips SET departure_city = :departure_city, destination_city = :destination_city, departure_time = :departure_time, arrival_time = :arrival_time, price = :price, capacity = :capacity WHERE id = :id AND company_id = :company')->execute([
                            ':departure_city' => $departureCity,
                            ':destination_city' => $destinationCity,
                            ':departure_time' => $departureAt,
                            ':arrival_time' => $arrivalAt,
                            ':price' => $price,
                            ':capacity' => $capacity,
                            ':id' => $id,
                            ':company' => $user['company_id'],
                        ]);
                        flash('success', 'Sefer guncellendi.');
                    } catch (Throwable $e) {
                        flash('error', 'Guncelleme basarisiz: ' . $e->getMessage());
                    }
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = trim((string)post('id', ''));
        if ($id === '') {
            flash('error', 'Sefer bulunamadi.');
        } else {
            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM tickets WHERE trip_id = :id AND status = "active"');
            $countStmt->execute([':id' => $id]);
            $activeTickets = (int)$countStmt->fetchColumn();
            if ($activeTickets > 0) {
                flash('error', 'Aktif bileti olan sefer silinemez.');
            } else {
                try {
                    $stmt = $pdo->prepare('DELETE FROM trips WHERE id = :id AND company_id = :company');
                    $stmt->execute([
                        ':id' => $id,
                        ':company' => $user['company_id'],
                    ]);
                    flash('success', 'Sefer silindi.');
                } catch (Throwable $e) {
                    flash('error', 'Sefer silinemedi: ' . $e->getMessage());
                }
            }
        }
    }

    redirect('trips_manage.php');
}

$stmt = $pdo->prepare(
    'SELECT trips.*,
        (
            SELECT COUNT(bs.id) FROM tickets t2
            JOIN booked_seats bs ON bs.ticket_id = t2.id
            WHERE t2.trip_id = trips.id AND t2.status = "active"
        ) AS booked_count
     FROM trips
     WHERE trips.company_id = :company
     ORDER BY trips.departure_time'
);
$stmt->execute([':company' => $user['company_id']]);
$trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($trips as &$trip) {
    $booked = isset($trip['booked_count']) ? (int)$trip['booked_count'] : 0;
    $trip['seats_available'] = max(0, (int)$trip['capacity'] - $booked);
}

require __DIR__ . '/includes/header.php';
?>
<section class="row g-4 mb-4">
    <div class="col-xl-5">
        <div class="card border-0 shadow-sm card-lift h-100">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">Yeni Sefer</h2>
                <form method="post" class="row g-3">
                    <input type="hidden" name="action" value="create">
                    <div class="col-md-6">
                        <label class="form-label">Kalkis Sehir</label>
                        <input class="form-control" type="text" name="departure_city" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Varis Sehir</label>
                        <input class="form-control" type="text" name="destination_city" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Kalkis Tarihi/Saati</label>
                        <input class="form-control" type="datetime-local" name="departure_at" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Varis Tarihi/Saati</label>
                        <input class="form-control" type="datetime-local" name="arrival_at">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Fiyat (TL)</label>
                        <input class="form-control" type="number" step="0.01" name="price" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Koltuk Kapasitesi</label>
                        <input class="form-control" type="number" name="capacity" min="1" required>
                    </div>
                    <div class="col-12 d-grid">
                        <button class="btn btn-primary" type="submit">Sefer Ekle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card border-0 shadow-sm card-lift h-100">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">Mevcut Seferler</h2>
                <?php if (!$trips): ?>
                    <div class="alert alert-info mb-0" role="alert">Kayitli sefer bulunmuyor.</div>
                <?php else: ?>
                    <div class="d-grid gap-3">
                        <?php foreach ($trips as $trip): ?>
                            <div class="border rounded p-3">
                                <form method="post" class="row g-3 align-items-end">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?php echo sanitize($trip['id']); ?>">
                                    <div class="col-md-4">
                                        <label class="form-label">Kalkis</label>
                                        <input class="form-control" type="text" name="departure_city" value="<?php echo sanitize($trip['departure_city']); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Varis</label>
                                        <input class="form-control" type="text" name="destination_city" value="<?php echo sanitize($trip['destination_city']); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Fiyat</label>
                                        <input class="form-control" type="number" step="0.01" name="price" value="<?php echo (float)$trip['price']; ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Kalkis</label>
                                        <input class="form-control" type="datetime-local" name="departure_at" value="<?php echo sanitize(str_replace(' ', 'T', substr($trip['departure_time'], 0, 16))); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Varis</label>
                                        <input class="form-control" type="datetime-local" name="arrival_at" value="<?php echo $trip['arrival_time'] ? sanitize(str_replace(' ', 'T', substr($trip['arrival_time'], 0, 16))) : ''; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Koltuk Kapasitesi</label>
                                        <input class="form-control" type="number" name="capacity" min="1" value="<?php echo (int)$trip['capacity']; ?>" required>
                                        <span class="text-muted small">Bos: <?php echo (int)$trip['seats_available']; ?></span>
                                    </div>
                                    <div class="col-12 d-grid">
                                        <button class="btn btn-outline-primary" type="submit">Guncelle</button>
                                    </div>
                                </form>
                                <form method="post" action="trips_manage.php" class="mt-2 text-end" onsubmit="return confirm('Seferi silmek istediginize emin misiniz?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo sanitize($trip['id']); ?>">
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