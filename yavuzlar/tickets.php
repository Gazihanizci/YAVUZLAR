<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

require_login();
require_role(['user']);
refresh_user_session();
$user = current_user();
$tickets = fetch_user_tickets($user['id']);

require __DIR__ . '/includes/header.php';
?>
<section class="card border-0 shadow-sm card-lift">
    <div class="card-body p-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
            <div>
                <h1 class="h4 mb-1">Biletlerim</h1>
                <p class="text-muted small mb-0">Satin aldiginiz biletleri goruntuleyin, iptal edin veya PDF olarak indirin.</p>
            </div>
            <a class="btn btn-outline-primary btn-sm" href="index.php">Yeni Sefer Ara</a>
        </div>
        <?php if (!$tickets): ?>
            <div class="alert alert-info mb-0" role="alert">
                Henuz satin aldiginiz bilet bulunmuyor. <a class="alert-link" href="index.php">Seferleri inceleyin</a>.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle table-nowrap mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Guzergah</th>
                            <th scope="col">Kalkis</th>
                            <th scope="col">Durum</th>
                            <th scope="col">Fiyat</th>
                            <th scope="col" class="text-end">Islemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                            <tr>
                                <td>#<?php echo sanitize(substr($ticket['id'], 0, 8)); ?>...</td>
                                <td>
                                    <div class="fw-semibold"><?php echo sanitize($ticket['departure_city']); ?> - <?php echo sanitize($ticket['destination_city']); ?></div>
                                    <div class="text-muted small">Firma: <?php echo sanitize($ticket['company_name']); ?></div>
                                    <?php if (!empty($ticket['seat_numbers'])): ?>
                                        <div class="text-muted small">Koltuk: <?php echo sanitize($ticket['seat_numbers']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo format_datetime($ticket['departure_time']); ?></td>
                                <td>
                                    <?php $statusClass = $ticket['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>
                                    <span class="badge <?php echo $statusClass; ?>"><?php echo strtoupper($ticket['status']); ?></span>
                                </td>
                                <td><?php echo number_format((float)$ticket['total_price'], 2); ?> TL</td>
                                <td class="text-end">
                                    <a class="btn btn-outline-secondary btn-sm" href="ticket_pdf.php?id=<?php echo urlencode($ticket['id']); ?>" target="_blank">PDF</a>
                                    <?php if ($ticket['status'] === 'active'): ?>
                                        <?php if (can_cancel_ticket($ticket)): ?>
                                            <form class="d-inline" method="post" action="ticket_cancel.php" onsubmit="return confirm('Bileti iptal etmek istediginize emin misiniz?');">
                                                <input type="hidden" name="ticket_id" value="<?php echo sanitize($ticket['id']); ?>">
                                                <button class="btn btn-outline-danger btn-sm" type="submit">Iptal Et</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted small">Iptal icin sure doldu</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">Iptal edildi</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
