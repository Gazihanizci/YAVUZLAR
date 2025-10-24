<?php
declare(strict_types=1);

require __DIR__ . '/includes/config.php';

$pdo = db();

function uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($hex, 4));
}

try {
    $pdo->beginTransaction();

    $dropStatements = [
        'DROP TABLE IF EXISTS booked_seats',
        'DROP TABLE IF EXISTS tickets',
        'DROP TABLE IF EXISTS trips',
        'DROP TABLE IF EXISTS user_coupons',
        'DROP TABLE IF EXISTS coupons',
        'DROP TABLE IF EXISTS users',
        'DROP TABLE IF EXISTS firms',
        'DROP TABLE IF EXISTS bus_companies'
    ];

    foreach ($dropStatements as $sql) {
        $pdo->exec($sql);
    }

    $createStatements = [
        'CREATE TABLE bus_companies (
            id TEXT PRIMARY KEY,
            name TEXT NOT NULL UNIQUE,
            logo_path TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )',
        'CREATE TABLE users (
            id TEXT PRIMARY KEY,
            full_name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            role TEXT NOT NULL CHECK(role IN ("user", "company", "admin")),
            password TEXT NOT NULL,
            company_id TEXT,
            balance REAL NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES bus_companies(id) ON DELETE SET NULL
        )',
        'CREATE TABLE coupons (
            id TEXT PRIMARY KEY,
            code TEXT NOT NULL UNIQUE,
            discount REAL NOT NULL,
            usage_limit INTEGER NOT NULL,
            expire_date TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )',
        'CREATE TABLE trips (
            id TEXT PRIMARY KEY,
            company_id TEXT NOT NULL,
            departure_city TEXT NOT NULL,
            destination_city TEXT NOT NULL,
            departure_time TEXT NOT NULL,
            arrival_time TEXT,
            price REAL NOT NULL,
            capacity INTEGER NOT NULL,
            created_date TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES bus_companies(id) ON DELETE CASCADE
        )',
        'CREATE TABLE tickets (
            id TEXT PRIMARY KEY,
            trip_id TEXT NOT NULL,
            user_id TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT "active" CHECK(status IN ("active", "cancelled", "expired")),
            total_price REAL NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )',
        'CREATE TABLE booked_seats (
            id TEXT PRIMARY KEY,
            ticket_id TEXT NOT NULL,
            seat_number INTEGER NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
            UNIQUE (ticket_id, seat_number)
        )',
        'CREATE TABLE user_coupons (
            id TEXT PRIMARY KEY,
            coupon_id TEXT NOT NULL,
            user_id TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE (coupon_id, user_id)
        )'
    ];

    foreach ($createStatements as $sql) {
        $pdo->exec($sql);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    echo 'Hata: ' . $e->getMessage() . PHP_EOL;
    return;
}

$existingUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($existingUsers > 0) {
    echo "Veritabani yapisi guncellendi." . PHP_EOL;
    return;
}

try {
    $pdo->beginTransaction();

    $companyId = uuid();
    $insertCompany = $pdo->prepare('INSERT INTO bus_companies (id, name, logo_path) VALUES (:id, :name, :logo_path)');
    $insertCompany->execute([
        ':id' => $companyId,
        ':name' => 'Yavuzlar Turizm',
        ':logo_path' => 'assets/images/yavuzlar-logo.png'
    ]);

    $adminId = uuid();
    $companyAdminId = uuid();
    $userId = uuid();

    $insertUser = $pdo->prepare('INSERT INTO users (id, full_name, email, role, password, company_id, balance) VALUES (:id, :full_name, :email, :role, :password, :company_id, :balance)');

    $insertUser->execute([
        ':id' => $adminId,
        ':full_name' => 'Site Admin',
        ':email' => 'admin@yavuzlar.com',
        ':role' => 'admin',
        ':password' => password_hash('Admin123!', PASSWORD_DEFAULT),
        ':company_id' => null,
        ':balance' => 0
    ]);

    $insertUser->execute([
        ':id' => $companyAdminId,
        ':full_name' => 'Firma Yetkilisi',
        ':email' => 'firma@yavuzlar.com',
        ':role' => 'company',
        ':password' => password_hash('Firma123!', PASSWORD_DEFAULT),
        ':company_id' => $companyId,
        ':balance' => 0
    ]);

    $insertUser->execute([
        ':id' => $userId,
        ':full_name' => 'Deneme Kullanici',
        ':email' => 'user@yavuzlar.com',
        ':role' => 'user',
        ':password' => password_hash('User123!', PASSWORD_DEFAULT),
        ':company_id' => null,
        ':balance' => 250
    ]);

    $tripStmt = $pdo->prepare('INSERT INTO trips (id, company_id, departure_city, destination_city, departure_time, arrival_time, price, capacity) VALUES (:id, :company_id, :departure_city, :destination_city, :departure_time, :arrival_time, :price, :capacity)');
    $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Istanbul'));

    for ($i = 1; $i <= 4; $i++) {
        $departure = $now->add(new DateInterval('P' . $i . 'D'))->setTime(18, 0);
        $arrival = $departure->add(new DateInterval('PT5H'));
        $tripStmt->execute([
            ':id' => uuid(),
            ':company_id' => $companyId,
            ':departure_city' => $i % 2 === 0 ? 'Istanbul' : 'Ankara',
            ':destination_city' => $i % 2 === 0 ? 'Ankara' : 'Istanbul',
            ':departure_time' => $departure->format('Y-m-d H:i:s'),
            ':arrival_time' => $arrival->format('Y-m-d H:i:s'),
            ':price' => 350 + ($i * 25),
            ':capacity' => 40
        ]);
    }

    $couponId = uuid();
    $couponStmt = $pdo->prepare('INSERT INTO coupons (id, code, discount, usage_limit, expire_date) VALUES (:id, :code, :discount, :usage_limit, :expire_date)');
    $couponStmt->execute([
        ':id' => $couponId,
        ':code' => 'HOSGELDIN',
        ':discount' => 15,
        ':usage_limit' => 100,
        ':expire_date' => $now->add(new DateInterval('P6M'))->format('Y-m-d H:i:s')
    ]);

    $userCouponStmt = $pdo->prepare('INSERT INTO user_coupons (id, coupon_id, user_id) VALUES (:id, :coupon_id, :user_id)');
    $userCouponStmt->execute([
        ':id' => uuid(),
        ':coupon_id' => $couponId,
        ':user_id' => $userId
    ]);

    $pdo->commit();
    echo "Veritabani ve baslangic verileri hazir." . PHP_EOL;
} catch (Throwable $e) {
    $pdo->rollBack();
    echo 'Hata: ' . $e->getMessage() . PHP_EOL;
}