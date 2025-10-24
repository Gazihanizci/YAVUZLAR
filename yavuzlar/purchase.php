<?php declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

require_login();
require_role(['user']);
refresh_user_session();
$user = current_user();
$tripId = isset($_GET['trip_id']) ? trim((string)$_GET['trip_id']) : '';
$trip = $tripId !== '' ? upcoming_trip($tripId) : null;
if (!$trip) {
    flash('error', 'Sefer bulunamadi.');
    redirect('index.php');
}

$takenSeats = taken_seats_for_trip($trip['id']);
$seatLayout = generate_seat_layout((int)$trip['capacity']);
$availableSeats = array_values(array_diff(range(1, (int)$trip['capacity']), $takenSeats));
$selectedSeat = $availableSeats[0] ?? null;

$error = '';
$appliedCoupon = null;
$finalPrice = (float)$trip['price'];
$couponCode = '';
$canPurchase = !empty($availableSeats);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $couponCode = strtoupper(trim((string)post('coupon', '')));
    $selectedSeat = (int)post('seat_number', 0);

    if (!$canPurchase) {
        $error = 'Bu sefere ait bos koltuk kalmamistir.';
    } elseif ($selectedSeat <= 0) {
        $error = 'Lutfen bir koltuk seciniz.';
    } elseif ($selectedSeat > (int)$trip['capacity']) {
        $error = 'Gecersiz koltuk numarasi.';
    } elseif (in_array($selectedSeat, $takenSeats, true)) {
        $error = 'Sectiginiz koltuk artik uygun degil.';
    }

    if ($couponCode !== '') {
        $coupon = coupon_by_code($couponCode);
        if (!$coupon) {
            $error = 'Kupon kodu bulunamadi.';
        } elseif (!coupon_can_be_used($coupon, $user['id'])) {
            $error = 'Kupon kullanma kosullari saglanmiyor.';
        } else {
            $appliedCoupon = $coupon;
            $discount = calculate_coupon_discount($coupon, (float)$trip['price']);
            $finalPrice = max(0.0, (float)$trip['price'] - $discount);
        }
    }

    if ($error === '') {
        $finalPrice = $appliedCoupon ? max(0.0, (float)$trip['price'] - calculate_coupon_discount($appliedCoupon, (float)$trip['price'])) : (float)$trip['price'];
        if ($finalPrice > (float)$user['balance']) {
            $error = 'Bakiye yetersiz. Lutfen bakiyenizi yukleyin.';
        } else {
            $pdo = db();
            try {
                $pdo->beginTransaction();

                $seatCheck = $pdo->prepare('SELECT 1 FROM tickets t JOIN booked_seats bs ON bs.ticket_id = t.id WHERE t.trip_id = :trip AND t.status = "active" AND bs.seat_number = :seat LIMIT 1');
                $seatCheck->execute([
                    ':trip' => $trip['id'],
                    ':seat' => $selectedSeat,
                ]);
                if ($seatCheck->fetchColumn()) {
                    $pdo->rollBack();
                    $error = 'Sectiginiz koltuk artik uygun degil.';
                } else {
                    $updateBalance = $pdo->prepare('UPDATE users SET balance = balance - :price WHERE id = :id AND balance >= :price');
                    $updateBalance->execute([
                        ':price' => $finalPrice,
                        ':id' => $user['id'],
                    ]);
                    if ($updateBalance->rowCount() === 0) {
                        $pdo->rollBack();
                        $error = 'Islem basarisiz, bakiye yetersiz.';
                    } else {
                        $ticketId = uuidv4();
                        $pdo->prepare('INSERT INTO tickets (id, trip_id, user_id, status, total_price) VALUES (:id, :trip, :user, "active", :price)')->execute([
                            ':id' => $ticketId,
                            ':trip' => $trip['id'],
                            ':user' => $user['id'],
                            ':price' => $finalPrice,
                        ]);

                        $pdo->prepare('INSERT INTO booked_seats (id, ticket_id, seat_number) VALUES (:id, :ticket, :seat)')->execute([
                            ':id' => uuidv4(),
                            ':ticket' => $ticketId,
                            ':seat' => $selectedSeat,
                        ]);

                        if ($appliedCoupon) {
                            record_coupon_usage($appliedCoupon['id'], $user['id']);
                        }

                        $pdo->commit();
                        flash('success', 'Bilet satin alma islemi tamamlandi.');
                        refresh_user_session();
                        redirect('tickets.php');
                    }
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Beklenmeyen bir hata olustu: ' . $e->getMessage();
            }
        }
    }

    if ($error !== '') {
        $takenSeats = taken_seats_for_trip($trip['id']);
        $availableSeats = array_values(array_diff(range(1, (int)$trip['capacity']), $takenSeats));
        if ($selectedSeat && in_array($selectedSeat, $takenSeats, true)) {
            $selectedSeat = !empty($availableSeats) ? $availableSeats[0] : null;
        }
        $trip['seats_available'] = max(0, (int)$trip['capacity'] - count($takenSeats));
        $canPurchase = !empty($availableSeats);
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm card-lift h-100">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">Sefer Ozeti</h2>
                <p class="mb-1"><strong>Guzergah:</strong> <?php echo sanitize($trip['departure_city']); ?> - <?php echo sanitize($trip['destination_city']); ?></p>
                <p class="mb-1 text-muted">Firma: <?php echo sanitize($trip['company_name']); ?></p>
                <p class="mb-1 text-muted">Kalkis: <?php echo format_datetime($trip['departure_time']); ?></p>
                <?php if (!empty($trip['arrival_time'])): ?>
                    <p class="mb-1 text-muted">Varis: <?php echo format_datetime($trip['arrival_time']); ?></p>
                <?php endif; ?>
                <p class="mb-0 text-muted">Bos Koltuk: <?php echo max(0, (int)$trip['seats_available']); ?> / <?php echo (int)$trip['seats_total']; ?></p>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm card-lift h-100">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">Koltuk Secimi ve Odeme</h2>
                <form method="post" class="row g-3">
                    <div class="col-12">
                        <div class="seat-map-wrapper">
                            <div class="bus-front">Surucu</div>
                            <div class="seat-map">
                                <?php foreach ($seatLayout as $row): ?>
                                    <div class="seat-row">
                                        <div class="seat-block">
                                            <?php foreach ($row['left'] as $seatNumber): ?>
                                                <?php
                                                $isReserved = in_array($seatNumber, $takenSeats, true);
                                                $isSelected = $selectedSeat === $seatNumber;
                                                ?>
                                                <label class="seat <?php echo $isReserved ? 'seat-reserved' : ($isSelected ? 'seat-selected' : ''); ?>">
                                                    <input type="radio" name="seat_number" value="<?php echo $seatNumber; ?>" <?php echo $isReserved ? 'disabled' : ''; ?> <?php echo $isSelected ? 'checked' : ''; ?>>
                                                    <span><?php echo $seatNumber; ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="seat-aisle"></div>
                                        <div class="seat-block">
                                            <?php foreach ($row['right'] as $seatNumber): ?>
                                                <?php
                                                $isReserved = in_array($seatNumber, $takenSeats, true);
                                                $isSelected = $selectedSeat === $seatNumber;
                                                ?>
                                                <label class="seat <?php echo $isReserved ? 'seat-reserved' : ($isSelected ? 'seat-selected' : ''); ?>">
                                                    <input type="radio" name="seat_number" value="<?php echo $seatNumber; ?>" <?php echo $isReserved ? 'disabled' : ''; ?> <?php echo $isSelected ? 'checked' : ''; ?>>
                                                    <span><?php echo $seatNumber; ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="seat-legend">
                                <span class="seat-legend-item"><span class="seat-sample seat-sample-available">1</span> Musait</span>
                                <span class="seat-legend-item"><span class="seat-sample seat-sample-selected">1</span> Seciminiz</span>
                                <span class="seat-legend-item"><span class="seat-sample seat-sample-reserved">1</span> Dolu</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="coupon">Kupon Kodu (opsiyonel)</label>
                        <input class="form-control" type="text" name="coupon" id="coupon" value="<?php echo sanitize($couponCode); ?>" maxlength="20" placeholder="Kupon kodu girin">
                    </div>
                    <div class="col-12">
                        <div class="d-flex flex-column gap-1">
                            <span>Odeme Tutari: <strong><?php echo number_format($appliedCoupon ? max(0.0, (float)$trip['price'] - calculate_coupon_discount($appliedCoupon, (float)$trip['price'])) : (float)$trip['price'], 2); ?> TL</strong></span>
                            <span class="text-muted small">Bakiyeniz: <?php echo number_format((float)$user['balance'], 2); ?> TL</span>
                        </div>
                    </div>
                    <?php if ($error): ?>
                        <div class="col-12">
                            <div class="alert alert-danger" role="alert"><?php echo sanitize($error); ?></div>
                        </div>
                    <?php endif; ?>
                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-primary" type="submit" <?php echo !$canPurchase ? 'disabled' : ''; ?>>Satin Almayi Tamamla</button>
                        <a class="btn btn-outline-secondary" href="index.php">Iptal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var seatInputs = Array.prototype.slice.call(document.querySelectorAll('.seat input[type="radio"]'));
    if (!seatInputs.length) {
        return;
    }
    var refresh = function () {
        seatInputs.forEach(function (input) {
            var label = input.parentElement;
            if (label && !label.classList.contains('seat-reserved')) {
                if (input.checked) {
                    label.classList.add('seat-selected');
                } else {
                    label.classList.remove('seat-selected');
                }
            }
        });
    };
    seatInputs.forEach(function (input) {
        input.addEventListener('change', refresh);
    });
    refresh();
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>


