<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

require_login();
require_role(['user']);
$user = current_user();

$ticketId = isset($_GET['id']) ? trim((string)$_GET['id']) : '';
$ticket = $ticketId !== '' ? fetch_ticket_for_user($ticketId, $user['id']) : null;
if (!$ticket) {
    flash('error', 'Bilet bulunamadi.');
    redirect('tickets.php');
}

$pdf = generate_ticket_pdf($ticket, $user);
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="bilet-' . $ticket['id'] . '.pdf"');
echo $pdf;
exit;
