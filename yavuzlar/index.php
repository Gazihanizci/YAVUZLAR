<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

$departure = isset($_GET['origin']) ? trim((string)$_GET['origin']) : '';
$destination = isset($_GET['destination']) ? trim((string)$_GET['destination']) : '';
$date = isset($_GET['date']) ? trim((string)$_GET['date']) : '';

$trips = fetch_trips($departure ?: null, $destination ?: null, $date ?: null);
$locations = list_locations();

require __DIR__ . '/includes/header.php';
?>
<section class="mb-5">
    <div class="card border-0 shadow-sm card-lift">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center mb-4">
                <div>
                    <h1 class="h3 mb-1">Sefer Ara</h1>
                    <p class="text-muted mb-0">Kalkis, varis ve tarih bilgilerini secerek uygun seferleri listeleyin.</p>
                </div>
                <?php if ($departure || $destination || $date): ?>
                    <a class="btn btn-outline-secondary btn-sm" href="index.php">Filtreleri Temizle</a>
                <?php endif; ?>
            </div>
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="origin">Kalkis</label>
                    <select class="form-select" name="origin" id="origin">
                        <option value="">Tum Kalkis Noktalari</option>
                        <?php foreach ($locations as $item): ?>
                            <option value="<?php echo sanitize($item); ?>" <?php echo $departure === $item ? 'selected' : ''; ?>><?php echo sanitize($item); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="destination">Varis</label>
                    <select class="form-select" name="destination" id="destination">
                        <option value="">Tum Varis Noktalari</option>
                        <?php foreach ($locations as $item): ?>
                            <option value="<?php echo sanitize($item); ?>" <?php echo $destination === $item ? 'selected' : ''; ?>><?php echo sanitize($item); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 col-lg-3">
                    <label class="form-label" for="date">Tarih</label>
                    <input class="form-control" type="date" name="date" id="date" value="<?php echo sanitize($date); ?>">
                </div>
                <div class="col-md-12 col-lg-1 d-grid align-self-end">
                    <button class="btn btn-primary" type="submit">Seferleri Listele</button>
                </div>
            </form>
        </div>
    </div>
</section>

<section class="d-grid gap-4">
    <?php if (count($trips) === 0): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <p class="mb-0">Seciminize uygun sefer bulunamadi. Filtreleri degistirerek tekrar deneyin.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($trips as $trip): ?>
            <div class="card border-0 shadow-sm card-lift">
                <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-4">
                    <div>
                        <h2 class="h5 mb-2"><?php echo sanitize($trip['departure_city']); ?> &rarr; <?php echo sanitize($trip['destination_city']); ?></h2>
                        <div class="text-muted small mb-1">Firma: <?php echo sanitize($trip['company_name']); ?></div>
                        <div class="text-muted small mb-1">Kalkis: <?php echo format_datetime($trip['departure_time']); ?></div>
                        <?php if (!empty($trip['arrival_time'])): ?>
                            <div class="text-muted small mb-1">Varis: <?php echo format_datetime($trip['arrival_time']); ?></div>
                        <?php endif; ?>
                        <div class="text-muted small">Bos Koltuk: <?php echo (int)$trip['seats_available']; ?> / <?php echo (int)$trip['seats_total']; ?></div>
                    </div>
                    <div class="text-md-end">
                        <span class="d-block fw-semibold text-primary fs-4 mb-2"><?php echo number_format((float)$trip['price'], 2); ?> TL</span>
                        <a class="btn btn-primary" href="purchase.php?trip_id=<?php echo urlencode($trip['id']); ?>">Bilet Satin Al</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>