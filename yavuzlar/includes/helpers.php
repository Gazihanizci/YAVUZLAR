<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function uuidv4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($hex, 4));
}

function find_user_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email');
    $stmt->execute([':email' => strtolower(trim($email))]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

function find_user_by_id(string $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

function store_user_session(array $user): void
{
    $_SESSION['user'] = [
        'id' => $user['id'],
        'name' => $user['full_name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'company_id' => $user['company_id'] !== null ? $user['company_id'] : null,
        'balance' => (float)$user['balance'],
    ];
}

function refresh_user_session(): void
{
    $user = current_user();
    if (!$user) {
        return;
    }
    $fresh = find_user_by_id($user['id']);
    if ($fresh) {
        store_user_session($fresh);
    }
}

function fetch_trips(?string $origin = null, ?string $destination = null, ?string $date = null): array
{
    $query = <<<SQL
SELECT
    trips.id,
    trips.company_id,
    trips.departure_city,
    trips.destination_city,
    trips.departure_time,
    trips.arrival_time,
    trips.price,
    trips.capacity,
    bus_companies.name AS company_name,
    (
        SELECT COUNT(bs.id)
        FROM tickets t2
        JOIN booked_seats bs ON bs.ticket_id = t2.id
        WHERE t2.trip_id = trips.id AND t2.status = 'active'
    ) AS booked_count
FROM trips
JOIN bus_companies ON trips.company_id = bus_companies.id
WHERE trips.departure_time >= :now
SQL;

    $params = [':now' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s')];

    if ($origin) {
        $query .= ' AND LOWER(trips.departure_city) = LOWER(:origin)';
        $params[':origin'] = trim($origin);
    }
    if ($destination) {
        $query .= ' AND LOWER(trips.destination_city) = LOWER(:destination)';
        $params[':destination'] = trim($destination);
    }
    if ($date) {
        $query .= ' AND DATE(trips.departure_time) = :date';
        $params[':date'] = $date;
    }

    $query .= ' ORDER BY trips.departure_time ASC';

    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $booked = isset($row['booked_count']) ? (int)$row['booked_count'] : 0;
        $row['seats_total'] = (int)$row['capacity'];
        $row['seats_available'] = max(0, $row['seats_total'] - $booked);
    }

    return $rows;
}

function list_locations(): array
{
    $stmt = db()->query('SELECT DISTINCT departure_city AS location FROM trips UNION SELECT DISTINCT destination_city AS location FROM trips ORDER BY location');
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function verify_password(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

function create_user(string $name, string $email, string $password): string
{
    $id = uuidv4();
    $stmt = db()->prepare('INSERT INTO users (id, full_name, email, role, password, balance) VALUES (:id, :full_name, :email, :role, :password, :balance)');
    $stmt->execute([
        ':id' => $id,
        ':full_name' => trim($name),
        ':email' => strtolower(trim($email)),
        ':role' => 'user',
        ':password' => password_hash($password, PASSWORD_DEFAULT),
        ':balance' => 200,
    ]);
    return $id;
}

function upcoming_trip(string $tripId): ?array
{
    $stmt = db()->prepare(
        'SELECT trips.*, bus_companies.name AS company_name,
            (
                SELECT COUNT(bs.id) FROM tickets t2
                JOIN booked_seats bs ON bs.ticket_id = t2.id
                WHERE t2.trip_id = trips.id AND t2.status = "active"
            ) AS booked_count
         FROM trips JOIN bus_companies ON trips.company_id = bus_companies.id WHERE trips.id = :id'
    );
    $stmt->execute([':id' => $tripId]);
    $trip = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$trip) {
        return null;
    }

    $takenSeats = taken_seats_for_trip($tripId);
    $trip['taken_seats'] = $takenSeats;
    $trip['seats_total'] = (int)$trip['capacity'];
    $trip['seats_available'] = max(0, $trip['seats_total'] - count($takenSeats));

    return $trip;
}

function taken_seats_for_trip(string $tripId): array
{
    $stmt = db()->prepare('SELECT bs.seat_number FROM tickets t JOIN booked_seats bs ON bs.ticket_id = t.id WHERE t.trip_id = :trip AND t.status = "active"');
    $stmt->execute([':trip' => $tripId]);
    $seats = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return array_map('intval', $seats ?: []);
}

function generate_seat_layout(int $capacity): array
{
    $layout = [];
    $seat = 1;
    while ($seat <= $capacity) {
        $row = ['left' => [], 'right' => []];
        for ($i = 0; $i < 2 && $seat <= $capacity; $i++, $seat++) {
            $row['left'][] = $seat;
        }
        for ($i = 0; $i < 2 && $seat <= $capacity; $i++, $seat++) {
            $row['right'][] = $seat;
        }
        $layout[] = $row;
    }
    return $layout;
}

function fetch_user_tickets(string $userId): array
{
    $sql = <<<SQL
SELECT
    tickets.id,
    tickets.status,
    tickets.total_price,
    tickets.created_at,
    trips.departure_city,
    trips.destination_city,
    trips.departure_time,
    trips.arrival_time,
    trips.price AS trip_price,
    bus_companies.name AS company_name,
    GROUP_CONCAT(booked_seats.seat_number, ',') AS seat_numbers
FROM tickets
JOIN trips ON tickets.trip_id = trips.id
JOIN bus_companies ON trips.company_id = bus_companies.id
LEFT JOIN booked_seats ON booked_seats.ticket_id = tickets.id
WHERE tickets.user_id = :user
GROUP BY tickets.id
ORDER BY tickets.created_at DESC
SQL;

    $stmt = db()->prepare($sql);
    $stmt->execute([':user' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function coupon_by_code(string $code): ?array
{
    $stmt = db()->prepare('SELECT * FROM coupons WHERE code = :code');
    $stmt->execute([':code' => strtoupper(trim($code))]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
    return $coupon ?: null;
}

function coupon_usage_count(string $couponId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM user_coupons WHERE coupon_id = :coupon');
    $stmt->execute([':coupon' => $couponId]);
    return (int)$stmt->fetchColumn();
}

function coupon_already_used_by_user(string $couponId, string $userId): bool
{
    $stmt = db()->prepare('SELECT 1 FROM user_coupons WHERE coupon_id = :coupon AND user_id = :user');
    $stmt->execute([':coupon' => $couponId, ':user' => $userId]);
    return (bool)$stmt->fetchColumn();
}

function format_datetime(string $value): string
{
    $dt = new DateTimeImmutable($value);
    return $dt->format('d.m.Y H:i');
}

function fetch_ticket_for_user(string $ticketId, string $userId): ?array
{
    $sql = <<<SQL
SELECT
    tickets.id,
    tickets.status,
    tickets.total_price,
    tickets.created_at,
    trips.departure_city,
    trips.destination_city,
    trips.departure_time,
    trips.arrival_time,
    trips.price AS trip_price,
    trips.company_id,
    bus_companies.name AS company_name,
    GROUP_CONCAT(booked_seats.seat_number, ',') AS seat_numbers
FROM tickets
JOIN trips ON tickets.trip_id = trips.id
JOIN bus_companies ON trips.company_id = bus_companies.id
LEFT JOIN booked_seats ON booked_seats.ticket_id = tickets.id
WHERE tickets.id = :ticket AND tickets.user_id = :user
GROUP BY tickets.id
SQL;

    $stmt = db()->prepare($sql);
    $stmt->execute([
        ':ticket' => $ticketId,
        ':user' => $userId,
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function can_cancel_ticket(array $ticket): bool
{
    if ($ticket['status'] !== 'active') {
        return false;
    }
    $departure = new DateTimeImmutable($ticket['departure_time']);
    $limit = (new DateTimeImmutable('now'))->modify('+' . (string)CANCEL_LIMIT_HOURS . ' hour');
    return $departure > $limit;
}

function calculate_coupon_discount(array $coupon, float $price): float
{
    $percent = max(0.0, min(100.0, (float)$coupon['discount']));
    return round($price * ($percent / 100), 2);
}

function coupon_can_be_used(array $coupon, ?string $userId = null): bool
{
    if ((int)$coupon['usage_limit'] <= coupon_usage_count($coupon['id'])) {
        return false;
    }
    if ($userId !== null && coupon_already_used_by_user($coupon['id'], $userId)) {
        return false;
    }
    if (!empty($coupon['expire_date'])) {
        $today = new DateTimeImmutable('today');
        if (new DateTimeImmutable($coupon['expire_date']) < $today) {
            return false;
        }
    }
    return true;
}

function record_coupon_usage(string $couponId, string $userId): void
{
    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO user_coupons (id, coupon_id, user_id) VALUES (:id, :coupon, :user)');
    $stmt->execute([
        ':id' => uuidv4(),
        ':coupon' => $couponId,
        ':user' => $userId,
    ]);
}

function generate_ticket_pdf(array $ticket, array $user): string
{
    $escape = static function (string $value): string {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    };

    $lines = [
        'Yavuzlar Bilet',
        'Bilet No: ' . $ticket['id'],
        'Yolcu: ' . $user['name'],
        'Guzergah: ' . $ticket['departure_city'] . ' - ' . $ticket['destination_city'],
        'Kalkis: ' . format_datetime($ticket['departure_time']),
        'Fiyat: ' . number_format((float)$ticket['total_price'], 2) . ' TL',
    ];

    if (!empty($ticket['seat_numbers'])) {
        $lines[] = 'Koltuk: ' . $ticket['seat_numbers'];
    }

    $ops = [];
    foreach ($lines as $index => $line) {
        $escaped = $escape($line);
        if ($index === 0) {
            $ops[] = '1 0 0 1 50 780 Tm (' . $escaped . ') Tj';
        } else {
            $ops[] = '0 -24 Td (' . $escaped . ') Tj';
        }
    }

    $content = 'BT /F1 14 Tf ' . implode(' ', $ops) . ' ET';

    $objects = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
    $objects[3] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>';
    $objects[4] = ['stream' => $content];
    $objects[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

    $output = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $index => $data) {
        $offsets[$index] = strlen($output);
        if (is_array($data)) {
            $streamData = $data['stream'];
            $output .= $index . " 0 obj\n<< /Length " . strlen($streamData) . " >>\nstream\n" . $streamData . "\nendstream\nendobj\n";
        } else {
            $output .= $index . " 0 obj\n" . $data . "\nendobj\n";
        }
    }

    $xrefOffset = strlen($output);
    $output .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $output .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
    }
    $output .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";
    return $output;
}

function adjust_user_balance(string $userId, float $amount): bool
{
    $pdo = db();
    $stmt = $pdo->prepare('UPDATE users SET balance = balance + :amount WHERE id = :id AND balance + :amount >= 0');
    $stmt->execute([
        ':amount' => $amount,
        ':id' => $userId,
    ]);
    return $stmt->rowCount() > 0;
}
?>
