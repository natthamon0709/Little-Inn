<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../src/Pricing.php';
require __DIR__ . '/../src/RoomRepository.php';

$roomRepo = new RoomRepository($pdo);

$room_id        = (int)($_POST['room_id'] ?? 0);
$checkout_at    = $_POST['checkout_at'] ?? date('Y-m-d H:i:s');
$payment_method = $_POST['payment_method'] ?? null;

// ปรับรูปแบบเวลา
if (strpos($checkout_at,'T') !== false) $checkout_at = str_replace('T',' ',$checkout_at);
if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/',$checkout_at)) $checkout_at .= ':00';

// กันค่าชำระเงินผิด
$allowed_pm = ['cash','transfer','card','other'];
if ($payment_method !== null && !in_array($payment_method, $allowed_pm, true)) {
  $payment_method = null;
}

$room = $roomRepo->find($room_id);
if(!$room || $room['status']!=='occupied' || empty($room['current_booking_id'])){
  header('Location: index.php?err='.urlencode('ห้องนี้ไม่ได้อยู่ระหว่างเข้าพัก')); exit;
}

// ดึง booking ปัจจุบัน
$st = $pdo->prepare('SELECT * FROM bookings WHERE id=?');
$st->execute([$room['current_booking_id']]);
$booking = $st->fetch(PDO::FETCH_ASSOC);
if(!$booking){ header('Location: index.php?err='.urlencode('ไม่พบข้อมูลการจอง')); exit; }

try{
  $pdo->beginTransaction();

  if ($booking['type']==='temp'){
    $hrs   = Pricing::ceilHours($booking['checkin_at'], $checkout_at);
    $price = Pricing::tempByHours($hrs);

    // ฟรี 1 ชม. ทุกครั้งที่ 10 ตามเบอร์
    $phone = $booking['phone'] ?? '';
    if ($phone !== '') {
      $q = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE phone=? AND checkout_at IS NOT NULL");
      $q->execute([$phone]);
      $prev = (int)$q->fetchColumn(); // ที่เคยเช็คเอาท์เสร็จไปแล้ว
      if ( ($prev + 1) % 10 === 0 ) {
        $price = max(0, $price - 60);
      }
    }
  } else {
    // overnight: ใช้ราคาที่บันทึกไว้ตอนเช็คอิน (สำรองด้วยกฎเรทห้อง)
    $price = (float)($booking['price'] ?? Pricing::overnightByCheckin($booking['checkin_at'], (int)$booking['room_id']));
  }

  // อัปเดต booking
  $st = $pdo->prepare("
    UPDATE bookings
       SET checkout_at=?,
           price=?,
           payment_method=COALESCE(NULLIF(?,''), payment_method)
     WHERE id=?
  ");
  $st->execute([$checkout_at, $price, $payment_method, (int)$booking['id']]);

  // คืนห้อง
  $roomRepo->setVacant($room_id);

  $pdo->commit();
  header('Location: index.php?msg='.urlencode('เช็คเอาท์สำเร็จ รับเงิน '.number_format($price,0).' บาท'));
}catch(Throwable $e){
  $pdo->rollBack();
  header('Location: index.php?err='.urlencode('ผิดพลาด: '.$e->getMessage()));
}
