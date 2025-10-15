<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../src/Pricing.php';
require __DIR__ . '/../src/RoomRepository.php';

$roomRepo = new RoomRepository($pdo);

$room_id       = (int)($_POST['room_id'] ?? 0);
$type          = $_POST['type'] ?? 'temp';                // temp / overnight
$phone_raw     = $_POST['phone'] ?? '';
$phone         = preg_replace('/\D+/', '', $phone_raw);   // เก็บเฉพาะตัวเลข
$note          = trim($_POST['note'] ?? '');
$checkin_at    = $_POST['checkin_at'] ?? date('Y-m-d H:i:s');
$pay_predict   = $_POST['payment_method'] ?? null;        // คาดการณ์ไว้ก่อนได้

// ปรับ format เวลา
if (strpos($checkin_at, 'T') !== false) $checkin_at = str_replace('T',' ',$checkin_at);
if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $checkin_at)) $checkin_at .= ':00';

$room = $roomRepo->find($room_id);
if (!$room || $room['status'] !== 'vacant') {
  header('Location: index.php?err=' . urlencode('ไม่พบห้องหรือห้องไม่ว่าง'));
  exit;
}

try {
  $pdo->beginTransaction();

  if ($type === 'overnight') {
    // รองรับเรทพิเศษบางห้อง (เช่น id 6,7,8)
    $price = Pricing::overnightByCheckin($checkin_at, (int)$room_id);
    $st = $pdo->prepare("
      INSERT INTO bookings (room_id, type, phone, note, checkin_at, price, payment_method)
      VALUES (?, 'overnight', ?, ?, ?, ?, ?)
    ");
    $st->execute([$room_id, $phone ?: null, $note ?: null, $checkin_at, $price, $pay_predict ?: null]);
  } else {
    $st = $pdo->prepare("
      INSERT INTO bookings (room_id, type, phone, note, checkin_at, payment_method)
      VALUES (?, 'temp', ?, ?, ?, ?)
    ");
    $st->execute([$room_id, $phone ?: null, $note ?: null, $checkin_at, $pay_predict ?: null]);
  }

  $bid = (int)$pdo->lastInsertId();
  $roomRepo->setOccupied($room_id, $bid);

  $pdo->commit();
  header('Location: index.php?msg=' . urlencode('เช็คอินสำเร็จ ห้อง ' . $room['name']));
} catch (Throwable $e) {
  $pdo->rollBack();
  header('Location: index.php?err=' . urlencode('ผิดพลาด: ' . $e->getMessage()));
}
