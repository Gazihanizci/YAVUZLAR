<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

require_login();
require_role(['company']);
refresh_user_session();
$user = current_user();

if (empty($user['company_id'])) {
    flash('error', 'Henuz bir firmaya bagli degilsiniz.');
    redirect('dashboard.php');
}

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT tickets.*, users.full_name AS customer_name, users.email AS customer_email,
            trips.departure_city, trips.destination_city, trips.departure_time
     FROM tickets
     JOIN trips ON tickets.trip_id = trips.id
     JOIN users ON tickets.user_id = users.id
     WHERE trips.company_id = :company
     ORDER BY tickets.created_at DESC'
);
$stmt->execute([':company' => $user['company_id']]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$activeCount = 0;
$cancelledCount = 0;
$revenue = 0.0;
foreach ($tickets as $ticket) {
    if ($ticket['status'] === 'active') {
        $activeCount++;
        $revenue += (float)$ticket['total_price'];
    } else {
        $cancelledCount++;
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm card-lift h-100">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">Satis Ozeti</h2>
                <p class="mb-2">Aktif Bilet: <strong><?php echo $activeCount; ?></strong></p>
                <p class="mb-2">Iptal Edilen: <strong><?php echo $cancelledCount; ?></strong></p>
                <p class="mb-0">Toplam Gelir: <strong><?php echo number_format($revenue, 2); ?> TL</strong></p>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm card-lift h-100">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">Bilet Listesi</h2>
                <?php if (!$tickets): ?>
                    <div class="alert alert-info mb-0" role="alert">Bu firmaya ait bilet bulunmuyor.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle table-nowrap mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Yolcu</th>
                                    <th scope="col">Guzergah</th>
                                    <th scope="col">Kalkis</th>
                                    <th scope="col">Durum</th>
                                    <th scope="col">Fiyat</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $ticket): ?>
                                    <tr>
                                        <td>#<?php echo sanitize(substr($ticket['id'], 0, 8)); ?>...</td>
                                        <td>
                                            <div class="fw-semibold"><?php echo sanitize($ticket['customer_name']); ?></div>
                                            <div class="text-muted small"><?php echo sanitize($ticket['customer_email']); ?></div>
                                        </td>
                                        <td><?php echo sanitize($ticket['departure_city']); ?> - <?php echo sanitize($ticket['destination_city']); ?></td>
                                        <td><?php echo format_datetime($ticket['departure_time']); ?></td>
                                        <td><?php echo strtoupper($ticket['status']); ?></td>
                                        <td><?php echo number_format((float)$ticket['total_price'], 2); ?> TL</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>