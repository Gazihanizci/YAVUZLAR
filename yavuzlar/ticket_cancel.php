<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

require_login();
require_role(['user']);
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('tickets.php');
}

$ticketId = isset($_POST['ticket_id']) ? trim((string)$_POST['ticket_id']) : '';
$ticket = $ticketId !== '' ? fetch_ticket_for_user($ticketId, $user['id']) : null;
if (!$ticket) {
    flash('error', 'Bilet bulunamadi.');
    redirect('tickets.php');
}

if (!can_cancel_ticket($ticket)) {
    flash('error', 'Bu bilet iptal edilemez.');
    redirect('tickets.php');
}

$pdo = db();
try {
    $pdo->beginTransaction();

    $update = $pdo->prepare('UPDATE tickets SET status = "cancelled" WHERE id = :id AND status = "active"');
    $update->execute([':id' => $ticket['id']]);
    if ($update->rowCount() === 0) {
        $pdo->rollBack();
        flash('error', 'Bilet iptal edilirken hata olustu.');
        redirect('tickets.php');
    }

    $pdo->prepare('DELETE FROM booked_seats WHERE ticket_id = :ticket')->execute([':ticket' => $ticket['id']]);
    $pdo->prepare('UPDATE users SET balance = balance + :amount WHERE id = :id')->execute([
        ':amount' => $ticket['total_price'],
        ':id' => $user['id'],
    ]);

    $pdo->commit();
    flash('success', 'Bilet iptal edildi ve tutar bakiyenize iade edildi.');
    refresh_user_session();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', 'Islem sirasinda hata olustu: ' . $e->getMessage());
}

redirect('tickets.php');
