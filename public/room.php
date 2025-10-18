<?php
// public/room.php  — เวอร์ชันแก้ไขข้อมูลจบในไฟล์เดียว
$active = 'dashboard';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../src/helpers.php';

// ====== รับ room id ======
$roomId = max(0, (int)($_GET['id'] ?? 0));
if ($roomId <= 0) {
  http_response_code(400);
  echo "ต้องระบุ id ห้อง เช่น room.php?id=1";
  exit;
}

// ====== ถ้าเป็น POST (บันทึกจาก Modal) ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['__edit_booking'])) {
  $id            = (int)($_POST['id'] ?? 0);
  $room_id       = (int)($_POST['room_id'] ?? 0); // optional
  $type          = $_POST['type'] ?? 'temp';      // temp|overnight
  $checkin_at    = trim($_POST['checkin_at'] ?? '');
  $checkout_at   = trim($_POST['checkout_at'] ?? '');
  $payment_method= trim($_POST['payment_method'] ?? '');
  $price         = (float)($_POST['price'] ?? 0);
  $phone         = preg_replace('/\D+/', '', $_POST['phone'] ?? ''); // เก็บตัวเลขล้วน
  $note          = trim($_POST['note'] ?? '');

  // ปรับ datetime-local -> "Y-m-d H:i:s"
  $fix = function($s){
    if ($s==='') return null;
    $s = str_replace('T',' ', $s);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $s)) $s .= ':00';
    return $s;
  };
  $checkin_at  = $fix($checkin_at);
  $checkout_at = $fix($checkout_at);

  // จำกัดค่าที่อนุญาต
  if (!in_array($type, ['temp','overnight'], true)) $type = 'temp';
  if (!in_array($payment_method, ['','cash','transfer','card','other'], true)) $payment_method = '';

  try {
    $sql = "
      UPDATE bookings
      SET type = :type,
          checkin_at = :checkin_at,
          checkout_at = :checkout_at,
          payment_method = :payment_method,
          price = :price,
          phone = :phone,
          note = :note
      WHERE id = :id
    ";
    // ถ้าส่ง room_id มาด้วยและไม่เป็น 0 ก็ล็อก room_id ให้ตรงกัน
    if ($room_id > 0) {
      $sql .= " AND room_id = :room_id";
    }

    $stmt = $pdo->prepare($sql);
    $params = [
      ':type'           => $type,
      ':checkin_at'     => $checkin_at,
      ':checkout_at'    => $checkout_at,
      ':payment_method' => ($payment_method !== '' ? $payment_method : null),
      ':price'          => $price,
      ':phone'          => ($phone !== '' ? $phone : null),
      ':note'           => ($note !== '' ? $note : null),
      ':id'             => $id,
    ];
    if ($room_id > 0) $params[':room_id'] = $room_id;

    $stmt->execute($params);

    // กลับมาที่หน้าเดิมพร้อมข้อความ
    $q = $_GET;
    $q['msg'] = 'อัปเดตสำเร็จ';
    header('Location: '.$_SERVER['PHP_SELF'].'?'.http_build_query($q));
    exit;
  } catch(Throwable $e) {
    $q = $_GET;
    $q['err'] = 'อัปเดตไม่สำเร็จ: '.$e->getMessage();
    header('Location: '.$_SERVER['PHP_SELF'].'?'.http_build_query($q));
    exit;
  }
}

// ====== ดึงข้อมูลห้อง ======
$stmt = $pdo->prepare("SELECT * FROM rooms WHERE id=?");
$stmt->execute([$roomId]);
$room = $stmt->fetch();
if (!$room) {
  http_response_code(404);
  echo "ไม่พบห้องที่ระบุ";
  exit;
}

// ====== รับ filter ======
$from = $_GET['from'] ?? today();
$to   = $_GET['to']   ?? today();
if (strtotime($from) > strtotime($to)) { [$from, $to] = [$to, $from]; }

// ====== รับ paging ======
$perPage = max(1, (int)($_GET['per_page'] ?? 20));
$page    = max(1, (int)($_GET['page'] ?? 1));

// ====== Export CSV ======
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  header('Content-Type: text/csv; charset=utf-8');
  $fname = 'room_'.$room['name'].'_'.$from.'_to_'.$to.'.csv';
  header('Content-Disposition: attachment; filename="'. $fname .'"');

  $out = fopen('php://output', 'w');
  // BOM
  fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
  fputcsv($out, ['checkout_date','room','type','payment_method','checkin_at','checkout_at','price','phone','note']);

  $stmt = $pdo->prepare("
    SELECT b.*, r.name AS room_name
    FROM bookings b
    JOIN rooms r ON r.id = b.room_id
    WHERE b.checkout_at IS NOT NULL
      AND b.room_id = ?
      AND DATE(b.checkout_at) BETWEEN ? AND ?
    ORDER BY b.checkout_at DESC
  ");
  $stmt->execute([$roomId, $from, $to]);

  while ($row = $stmt->fetch()) {
    fputcsv($out, [
      substr($row['checkout_at'],0,10),
      $row['room_name'],
      $row['type'],
      $row['payment_method'] ?? '',
      $row['checkin_at'],
      $row['checkout_at'],
      (int)$row['price'],
      $row['phone'] ?? '',
      $row['note'] ?? '',
    ]);
  }
  fclose($out);
  exit;
}

// ====== นับ + รวมช่วง ======
$stmt = $pdo->prepare("
  SELECT COUNT(*) AS cnt, COALESCE(SUM(price),0) AS sum_all
  FROM bookings
  WHERE checkout_at IS NOT NULL
    AND room_id = ?
    AND DATE(checkout_at) BETWEEN ? AND ?
");
$stmt->execute([$roomId, $from, $to]);
$meta = $stmt->fetch();
$totalRows = (int)$meta['cnt'];
$sumAll    = (float)$meta['sum_all'];

// ====== หน้า/offset ======
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = max(1, min($page, $totalPages));
$offset     = ($page - 1) * $perPage;

// ====== ดึงรายการหน้าปัจจุบัน (เพิ่ม phone, note มาด้วย) ======
$stmt = $pdo->prepare("
  SELECT b.id, b.room_id, b.type, b.payment_method, b.checkin_at, b.checkout_at, b.price, b.phone, b.note,
         r.name AS room_name
  FROM bookings b
  JOIN rooms r ON r.id = b.room_id
  WHERE b.checkout_at IS NOT NULL
    AND b.room_id = ?
    AND DATE(b.checkout_at) BETWEEN ? AND ?
  ORDER BY b.checkout_at DESC
  LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $roomId, PDO::PARAM_INT);
$stmt->bindValue(2, $from);
$stmt->bindValue(3, $to);
$stmt->bindValue(4, $perPage, PDO::PARAM_INT);
$stmt->bindValue(5, $offset,  PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

// รวมเฉพาะหน้า
$sumPage = 0.0; foreach ($rows as $r) { $sumPage += (float)$r['price']; }

// ===== Helper =====
function page_url_room($id, $page, $perPage, $from, $to){
  $q = http_build_query([
    'id'       => $id,
    'from'     => $from,
    'to'       => $to,
    'page'     => $page,
    'per_page' => $perPage
  ]);
  return base_url() . "/room.php?{$q}";
}
function page_range($page, $totalPages, $window = 2){
  $range = [1];
  for ($i=$page-$window; $i<=$page+$window; $i++) if ($i>1 && $i<$totalPages) $range[]=$i;
  if ($totalPages>1) $range[]=$totalPages;
  $range = array_values(array_unique($range));
  sort($range);
  return $range;
}

include __DIR__ . '/../templates/header.php';
?>

<style>
  .card-table{ border-radius:16px; box-shadow:0 1px 2px rgba(16,24,40,.06); border:1px solid #e5e7eb; overflow:hidden; background:#fff;}
  .table-head-sticky thead{ position:sticky; top:0; z-index:10; background:#f9fafb;}
  .num{ font-variant-numeric: tabular-nums; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;}
  .chip{ padding:.4rem .7rem; border-radius:.75rem; border:1px solid #e5e7eb; background:#fff}
  .chip:hover{ background:#f9fafb }
  .pager a, .pager span.gap{
    border:1px solid #e5e7eb; border-radius:9999px; padding:.45rem .75rem; min-width:2.25rem;
    display:inline-flex; align-items:center; justify-content:center; line-height:1;
  }
  .pager a:hover{ background:#f9fafb; }
  .pager a.active{ background:#111827; color:#fff; border-color:#111827; }
  .pager a.disabled{ pointer-events:none; opacity:.45; }
  .pager .nav{ padding:.45rem .6rem; }
  @media print{ .no-print{display:none!important;} .card-table{box-shadow:none;border:0;} }
</style>

<section class="print-area space-y-5">

  <!-- หัวเรื่อง + ปุ่ม -->
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
    <div>
      <h2 class="text-2xl md:text-3xl font-bold tracking-tight text-gray-900">
        รายละเอียดห้อง: <?php echo h($room['name']); ?>
      </h2>
      <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 text-emerald-700 px-3 py-1 ring-1 ring-emerald-100">
          ช่วง: <b><?php echo h($from); ?></b> - <b><?php echo h($to); ?></b>
        </span>
        <span class="inline-flex items-center gap-2 rounded-full bg-gray-100 text-gray-700 px-3 py-1 ring-1 ring-gray-200">
          ทั้งช่วง: <b><?php echo format_baht($sumAll); ?></b> ฿
        </span>
        <span class="inline-flex items-center gap-2 rounded-full bg-gray-100 text-gray-700 px-3 py-1 ring-1 ring-gray-200">
          รายการทั้งหมด: <b><?php echo number_format($totalRows); ?></b>
        </span>
      </div>
    </div>

    <div class="no-print flex flex-wrap gap-2">
      <a href="<?php
        echo base_url().'/room.php?'.
          http_build_query(['id'=>$roomId,'from'=>$from,'to'=>$to,'export'=>'csv']);
      ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white shadow">
        Export CSV
      </a>
      <a href="<?php echo base_url(); ?>/index.php"
         class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 border border-gray-200">
        กลับหน้าแดชบอร์ด
      </a>
      <button onclick="window.print()"
        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-600 text-white shadow">
        พิมพ์ทั้งหน้า
      </button>
    </div>
  </div>

  <!-- ฟอร์ม Filter -->
  <form id="filterForm" method="get"
        class="no-print bg-white border border-gray-200 rounded-2xl shadow-sm p-4 md:p-5
               grid grid-cols-1 md:grid-cols-7 gap-3 md:gap-4">
    <input type="hidden" name="id" value="<?php echo (int)$roomId; ?>">

    <label class="block md:col-span-2">
      <span class="text-sm font-medium text-gray-700">จากวันที่</span>
      <input type="date" name="from" value="<?php echo h($from); ?>"
             class="mt-1 w-full rounded-xl border-gray-300 px-3 py-2
                    focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30 bg-white text-gray-800">
    </label>

    <label class="block md:col-span-2">
      <span class="text-sm font-medium text-gray-700">ถึงวันที่</span>
      <input type="date" name="to" value="<?php echo h($to); ?>"
             class="mt-1 w-full rounded-xl border-gray-300 px-3 py-2
                    focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30 bg-white text-gray-800">
    </label>

    <label class="block md:col-span-1">
      <span class="text-sm font-medium text-gray-700">แถว/หน้า</span>
      <select name="per_page" id="perPage"
              class="mt-1 w-full rounded-xl border-gray-300 px-3 py-2 bg-white
                     focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30">
        <?php foreach ([10,20,50,100] as $pp): ?>
          <option value="<?php echo $pp; ?>" <?php echo $pp==$perPage?'selected':''; ?>><?php echo $pp; ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <div class="md:col-span-2 flex items-end">
      <div class="w-full flex gap-2">
        <button class="flex-1 inline-flex items-center justify-center gap-2
                       px-4 py-2 rounded-xl bg-gray-900 hover:bg-black text-white shadow">
          ดูรายงาน
        </button>
        <a href="<?php echo base_url(); ?>/room.php?id=<?php echo (int)$roomId; ?>"
           class="inline-flex items-center justify-center gap-2 px-3 py-2
                  rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 border border-gray-200">
          รีเซ็ต
        </a>
      </div>
    </div>

    <div class="md:col-span-7 -mt-1 text-xs text-gray-600 flex flex-wrap items-center gap-2">
      ทางลัด:
      <button type="button" data-range="today"      class="chip">วันนี้</button>
      <button type="button" data-range="yesterday"  class="chip">เมื่อวาน</button>
      <button type="button" data-range="last7"      class="chip">7 วันล่าสุด</button>
      <button type="button" data-range="thismonth"  class="chip">เดือนนี้</button>
    </div>
  </form>

  <!-- ตาราง -->
  <div class="card-table">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm table-head-sticky">
        <colgroup>
          <col class="w-[160px]" />
          <col class="w-[120px]" />
          <col class="w-[160px]" />
          <col class="w-[160px]" />
          <col class="w-[140px]" />
          <col class="w-[140px]" />
          <col class="w-[90px]" />
        </colgroup>
        <thead>
          <tr class="text-gray-600">
            <th class="text-left px-4 py-3 font-semibold">เช็คเอาท์</th>
            <th class="text-left px-4 py-3 font-semibold">ประเภท</th>
            <th class="text-left px-4 py-3 font-semibold">วิธีชำระเงิน</th>
            <th class="text-left px-4 py-3 font-semibold">เช็คอิน</th>
            <th class="text-left px-4 py-3 font-semibold">เช็คเอาท์</th>
            <th class="text-right px-4 py-3 font-semibold">ยอดเงิน (฿)</th>
            <th class="text-center px-4 py-3 font-semibold">แก้ไข</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php if (empty($rows)): ?>
            <tr>
              <td colspan="7" class="px-6 py-12 text-center text-gray-500">— ไม่มีรายการในช่วงวันที่เลือก —</td>
            </tr>
          <?php else: foreach ($rows as $row): ?>
            <?php
              $pay = trim((string)($row['payment_method'] ?? ''));
              $payBadge = $pay ?: '—';
              $payClass = 'bg-gray-50 text-gray-700 ring-1 ring-gray-200';
              if ($pay === 'cash')        { $payBadge = 'เงินสด';  $payClass = 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100'; }
              elseif ($pay === 'transfer'){ $payBadge = 'โอน';     $payClass = 'bg-sky-50 text-sky-700 ring-1 ring-sky-100'; }
              elseif ($pay === 'card')    { $payBadge = 'บัตร';    $payClass = 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-100'; }
              elseif ($pay === 'other')   { $payBadge = 'อื่น ๆ';  $payClass = 'bg-amber-50 text-amber-700 ring-1 ring-amber-100'; }
            ?>
            <tr class="hover:bg-gray-50 transition">
              <td class="px-4 py-2.5 text-gray-700"><?php echo h(substr($row['checkout_at'], 0, 16)); ?></td>
              <td class="px-4 py-2.5">
                <?php if ($row['type'] === 'temp'): ?>
                  <span class="inline-flex items-center rounded-full bg-amber-50 text-amber-700 px-2.5 py-0.5 text-xs font-medium ring-1 ring-amber-100">ชั่วคราว</span>
                <?php else: ?>
                  <span class="inline-flex items-center rounded-full bg-indigo-50 text-indigo-700 px-2.5 py-0.5 text-xs font-medium ring-1 ring-indigo-100">ค้างคืน</span>
                <?php endif; ?>
              </td>
              <td class="px-4 py-2.5">
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium <?php echo $payClass; ?>"><?php echo $payBadge; ?></span>
              </td>
              <td class="px-4 py-2.5 text-gray-700"><?php echo h(substr($row['checkin_at'], 0, 16)); ?></td>
              <td class="px-4 py-2.5 text-gray-700"><?php echo h(substr($row['checkout_at'], 0, 16)); ?></td>
              <td class="px-4 py-2.5 text-right font-semibold num"><?php echo format_baht($row['price']); ?></td>
              <td class="px-4 py-2.5 text-center">
                <button type="button"
                  class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs"
                  data-edit
                  data-id="<?php echo (int)$row['id']; ?>"
                  data-room-id="<?php echo (int)$row['room_id']; ?>"
                  data-type="<?php echo h($row['type']); ?>"
                  data-payment="<?php echo h($row['payment_method'] ?? ''); ?>"
                  data-checkin="<?php echo h(substr($row['checkin_at'],0,16)); ?>"
                  data-checkout="<?php echo h(substr($row['checkout_at'],0,16)); ?>"
                  data-price="<?php echo (float)$row['price']; ?>"
                  data-phone="<?php echo h($row['phone'] ?? ''); ?>"
                  data-note="<?php echo h($row['note'] ?? ''); ?>"
                >แก้ไข</button>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
        <tfoot class="bg-gray-50">
          <tr class="border-t border-gray-200">
            <td colspan="6" class="px-4 py-3 text-right font-semibold text-gray-700">รวม (หน้านี้)</td>
            <td class="px-4 py-3 text-right font-semibold num"><?php echo format_baht($sumPage); ?> ฿</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
    <?php $pgs = page_range($page, $totalPages, 2); ?>
    <nav class="no-print mt-4 flex items-center justify-center" aria-label="Pagination">
      <div class="pager flex flex-wrap items-center gap-1">
        <a href="<?php echo $page==1 ? '#' : page_url_room($roomId,1,$perPage,$from,$to); ?>"
           class="nav <?php echo $page==1?'disabled':''; ?>" aria-label="หน้าแรก">«</a>
        <a href="<?php echo $page<=1 ? '#' : page_url_room($roomId,$page-1,$perPage,$from,$to); ?>"
           class="nav <?php echo $page<=1?'disabled':''; ?>" aria-label="ก่อนหน้า">‹</a>

        <?php $prev=null; foreach ($pgs as $p): ?>
          <?php if (!is_null($prev) && $p != $prev + 1): ?>
            <span class="gap px-2 text-gray-400">…</span>
          <?php endif; $prev = $p; ?>
          <a href="<?php echo page_url_room($roomId,$p,$perPage,$from,$to); ?>"
             class="<?php echo $p==$page?'active':''; ?>"><?php echo $p; ?></a>
        <?php endforeach; ?>

        <a href="<?php echo $page>=$totalPages ? '#' : page_url_room($roomId,$page+1,$perPage,$from,$to); ?>"
           class="nav <?php echo $page>=$totalPages?'disabled':''; ?>" aria-label="ถัดไป">›</a>
        <a href="<?php echo $page==$totalPages ? '#' : page_url_room($roomId,$totalPages,$perPage,$from,$to); ?>"
           class="nav <?php echo $page==$totalPages?'disabled':''; ?>" aria-label="หน้าสุดท้าย">»</a>
      </div>
    </nav>
  <?php endif; ?>

</section>

<!-- ===== Modal แก้ไขข้อมูลการจอง (จบในไฟล์เดียว) ===== -->
<div id="editModal" class="fixed inset-0 z-[100] hidden">
  <div class="absolute inset-0 bg-black/40"></div>
  <div class="relative max-w-xl mx-auto mt-20 bg-white rounded-2xl shadow-lg border border-gray-200">
    <div class="px-5 py-4 border-b flex items-center justify-between">
      <h3 class="text-lg font-semibold">แก้ไขข้อมูลการจอง</h3>
      <button id="editClose" class="w-9 h-9 rounded-lg hover:bg-gray-100 inline-flex items-center justify-center">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>

    <form id="editForm" method="post" class="p-5 grid grid-cols-1 gap-3">
      <input type="hidden" name="__edit_booking" value="1">
      <input type="hidden" name="id">
      <input type="hidden" name="room_id" value="<?php echo (int)$roomId; ?>">

      <label class="block">
        <span class="text-sm font-medium text-gray-700">ประเภท</span>
        <select name="type"
          class="mt-1 w-full rounded-xl border-gray-300 px-3 py-2 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30">
          <option value="temp">ชั่วคราว (รายชั่วโมง)</option>
          <option value="overnight">ค้างคืน</option>
        </select>
      </label>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <label class="block">
          <span class="text-sm font-medium text-gray-700">เช็คอิน</span>
          <input type="datetime-local" name="checkin_at"
            class="mt-1 w-full rounded-xl border-gray-300 px-3 py-2 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30">
        </label>
        <label class="block">
          <span class="text-sm font-medium text-gray-700">เช็คเอาท์</span>
          <input type="datetime-local" name="checkout_at"
            class="mt-1 w-full rounded-xl border-gray-300 px-3 py-2 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30">
        </label>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <label class="block">
          <span class="text-sm font-medium text-gray-700">วิธีชำระเงิน</span>
          <select name="payment_method"
            class="mt-1 w-full rounded-xl border-gray-300 px-3 py-2 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30">
            <option value="">— ไม่ระบุ —</option>
            <option value="cash">เงินสด</option>
            <option value="transfer">โอน</option>
            <option value="card">บัตร</option>
            <option value="other">อื่น ๆ</option>
          </select>
        </label>

        <label class="block">
          <span class="text-sm font-medium text-gray-700">ยอดเงิน (บาท)</span>
          <input type="number" step="1" min="0" name="price"
            class="mt-1 w-full rounded-xl border-gray-300 px-3 py-2 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30">
        </label>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <label class="block">
          <span class="text-sm font-medium text-gray-700">เบอร์โทร</span>
          <input type="tel" name="phone"
            class="mt-1 w-full rounded-xl border-gray-300 px-3 py-2 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30"
            placeholder="0812345678">
        </label>
        <label class="block">
          <span class="text-sm font-medium text-gray-700">หมายเหตุ</span>
          <input type="text" name="note"
            class="mt-1 w-full rounded-xl border-gray-300 px-3 py-2 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30"
            placeholder="">
        </label>
      </div>

      <div class="mt-3 flex items-center justify-end gap-2">
        <button type="button" id="editCancel"
          class="px-4 py-2 rounded-xl border border-gray-200 hover:bg-gray-50">ยกเลิก</button>
        <button
          class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white">บันทึก</button>
      </div>
    </form>
  </div>
</div>

<!-- JS: per_page autosubmit + ช่วงด่วน + Modal -->
<script>
(function(){
  // per page autosubmit
  document.getElementById('perPage')?.addEventListener('change', function(){
    const form = document.getElementById('filterForm');
    const hidden = document.createElement('input');
    hidden.type = 'hidden'; hidden.name = 'page'; hidden.value = '1';
    form.appendChild(hidden);
    form.submit();
  });

  const form = document.getElementById('filterForm');
  const from = form?.querySelector('input[name="from"]');
  const to   = form?.querySelector('input[name="to"]');
  if (from && to){
    function fmt(d){ const z=n=>String(n).padStart(2,'0'); return d.getFullYear()+'-'+z(d.getMonth()+1)+'-'+z(d.getDate()); }
    form.querySelectorAll('button[data-range]').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const t = new Date(); t.setHours(0,0,0,0);
        let a = new Date(t), b2 = new Date(t);
        switch(btn.dataset.range){
          case 'today':     a=t; b2=t; break;
          case 'yesterday': a=new Date(t); a.setDate(a.getDate()-1); b2=new Date(a); break;
          case 'last7':     b2=t; a=new Date(t); a.setDate(a.getDate()-6); break;
          case 'thismonth': a=new Date(t.getFullYear(), t.getMonth(), 1); b2=new Date(t.getFullYear(), t.getMonth()+1, 0); break;
        }
        from.value = fmt(a); to.value = fmt(b2);
        const hidden = document.createElement('input');
        hidden.type = 'hidden'; hidden.name = 'page'; hidden.value = '1';
        form.appendChild(hidden);
        form.submit();
      });
    });
  }

  // ===== Modal =====
  const m  = document.getElementById('editModal');
  const f  = document.getElementById('editForm');
  const bX = document.getElementById('editClose');
  const bC = document.getElementById('editCancel');

  function openModal(){ m.classList.remove('hidden'); document.body.classList.add('overflow-hidden'); }
  function closeModal(){ m.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); }

  document.querySelectorAll('[data-edit]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      f.elements['id'].value            = btn.dataset.id || '';
      f.elements['room_id'].value       = btn.dataset.roomId || '';
      f.elements['type'].value          = btn.dataset.type || 'temp';
      f.elements['payment_method'].value= btn.dataset.payment || '';
      f.elements['checkin_at'].value    = (btn.dataset.checkin || '').replace(' ', 'T');
      f.elements['checkout_at'].value   = (btn.dataset.checkout || '').replace(' ', 'T');
      f.elements['price'].value         = btn.dataset.price || 0;
      f.elements['phone'].value         = btn.dataset.phone || '';
      f.elements['note'].value          = btn.dataset.note || '';
      openModal();
    });
  });

  bX?.addEventListener('click', closeModal);
  bC?.addEventListener('click', closeModal);
  m.addEventListener('click', (e)=>{ if(e.target===m) closeModal(); });

})();
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
