<?php
// public/points.php
$active = 'points';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../src/helpers.php';

// ===== รับค่า filter =====
$q      = trim($_GET['q'] ?? '');       // ค้นหาเบอร์ (บางส่วนก็ได้)
$from   = $_GET['from'] ?? '';          // ช่วงวันที่ (ออปชัน) - อ้างอิง DATE(checkin_at)
$to     = $_GET['to']   ?? '';
$from   = $from === '' ? null : $from;
$to     = $to   === '' ? null : $to;

// แก้กรณี from > to
if ($from && $to && strtotime($from) > strtotime($to)) { [$from, $to] = [$to, $from]; }

// ===== รับค่าหน้า =====
$perPage = max(1, (int)($_GET['per_page'] ?? 20));
$page    = max(1, (int)($_GET['page'] ?? 1));

// ===== สร้าง where/params =====
$where = [];
$params = [];

$where[] = "phone IS NOT NULL AND phone <> ''";

if ($q !== '') {
  // ค้นหาแบบคำใกล้เคียง
  $where[] = "phone LIKE ?";
  $params[] = "%" . preg_replace('/\D+/', '', $q) . "%"; // เก็บเฉพาะตัวเลข
}
if ($from) {
  $where[] = "DATE(checkin_at) >= ?";
  $params[] = $from;
}
if ($to) {
  $where[] = "DATE(checkin_at) <= ?";
  $params[] = $to;
}
$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// ===== นับกลุ่มทั้งหมด =====
$sqlCount = "
  SELECT COUNT(*) AS cnt
  FROM (
    SELECT phone
    FROM bookings
    $whereSql
    GROUP BY phone
  ) t
";
$stmt = $pdo->prepare($sqlCount);
$stmt->execute($params);
$totalRows = (int)$stmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = max(1, min($page, $totalPages));
$offset = ($page - 1) * $perPage;

// ===== ดึงสรุปตามเบอร์ (หน้า/offset) =====
// visits = จำนวนการเช็คอิน, revenue = ยอดรวม (จาก price ที่เช็คเอาท์แล้ว)
// free_eligible = floor(visits/10), remainder = visits % 10
$sql = "
  SELECT
    phone,
    COUNT(*) AS visits,
    SUM(CASE WHEN price IS NOT NULL THEN price ELSE 0 END) AS revenue,
    MAX(checkin_at) AS last_checkin
  FROM bookings
  $whereSql
  GROUP BY phone
  ORDER BY last_checkin DESC
  LIMIT ? OFFSET ?
";
$stmt = $pdo->prepare($sql);
$bind = $params;
$bind[] = $perPage;
$bind[] = $offset;
$stmt->execute($bind);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// รวมภาพรวม (เฉพาะในช่วง filter)
$sqlAgg = "
  SELECT
    COUNT(DISTINCT phone) AS uniq_phone,
    COUNT(*) AS total_visits,
    SUM(CASE WHEN price IS NOT NULL THEN price ELSE 0 END) AS total_revenue
  FROM bookings
  $whereSql
";
$stmt = $pdo->prepare($sqlAgg);
$stmt->execute($params);
$agg = $stmt->fetch(PDO::FETCH_ASSOC);
$uniqPhone = (int)($agg['uniq_phone'] ?? 0);
$totalVisits= (int)($agg['total_visits'] ?? 0);
$totalRevenue = (float)($agg['total_revenue'] ?? 0);

// ===== Export CSV =====
if (isset($_GET['export']) && $_GET['export']==='csv') {
  header('Content-Type: text/csv; charset=utf-8');
  $fname = 'points_' . ($from ?: 'all') . '_to_' . ($to ?: 'all') . '.csv';
  header('Content-Disposition: attachment; filename="'.$fname.'"');
  $out = fopen('php://output','w');
  fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
  fputcsv($out, ['phone','visits','free_eligible','remainder','revenue','last_checkin']);

  // export ทั้งช่วง (ไม่จำกัดหน้า)
  $sqlAll = "
    SELECT
      phone,
      COUNT(*) AS visits,
      SUM(CASE WHEN price IS NOT NULL THEN price ELSE 0 END) AS revenue,
      MAX(checkin_at) AS last_checkin
    FROM bookings
    $whereSql
    GROUP BY phone
    ORDER BY last_checkin DESC
  ";
  $stmt = $pdo->prepare($sqlAll);
  $stmt->execute($params);
  while($r = $stmt->fetch(PDO::FETCH_ASSOC)){
    $vis = (int)$r['visits'];
    $freeEligible = intdiv($vis, 10);
    $remainder    = $vis % 10;
    fputcsv($out, [
      $r['phone'],
      $vis,
      $freeEligible,
      $remainder,
      (int)$r['revenue'],
      $r['last_checkin'],
    ]);
  }
  fclose($out);
  exit;
}

// ===== ฟังก์ชันช่วยสร้างลิงก์เลขหน้า =====
function page_url_points($page, $perPage, $q, $from, $to){
  $qarr = [
    'q' => $q,
    'from' => $from ?: '',
    'to' => $to ?: '',
    'page' => $page,
    'per_page' => $perPage,
  ];
  return base_url() . '/points.php?' . http_build_query($qarr);
}

include __DIR__ . '/../templates/header.php';
?>
<style>
  .num{ font-variant-numeric: tabular-nums; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; }
  .card-table{ border-radius:16px; box-shadow:0 1px 2px rgba(16,24,40,.06); border:1px solid #e5e7eb; overflow:hidden; background:#fff; }
  .table-head-sticky thead{ position:sticky; top:0; z-index:10; background:#f9fafb; }
</style>

<section class="print-area space-y-4">
  <!-- หัวข้อ + สรุป -->
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
    <div>
      <h2 class="text-2xl md:text-3xl font-bold tracking-tight text-gray-900">ระบบสะสมแต้ม</h2>
      <p class="text-sm text-gray-500">ครบ 10 ครั้ง ฟรี 1 ชั่วโมง (นับตามจำนวนเช็คอินของเบอร์โทร)</p>
      <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 text-emerald-700 px-3 py-1 ring-1 ring-emerald-100">
          ทั้งหมด: <b><?php echo $uniqPhone; ?></b> เบอร์ • เช็คอินรวม: <b><?php echo number_format($totalVisits); ?></b> ครั้ง
        </span>
        <span class="inline-flex items-center gap-2 rounded-full bg-sky-50 text-sky-700 px-3 py-1 ring-1 ring-sky-100">
          รายได้รวมในช่วง: <b class="num"><?php echo number_format($totalRevenue,0); ?></b> ฿
        </span>
      </div>
    </div>

    <div class="no-print flex flex-wrap gap-2">
      <a href="<?php echo page_url_points($page, $perPage, $q, $from, $to) . '&export=csv'; ?>"
         class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white shadow">
        Export CSV
      </a>
      <button onclick="window.print()"
        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-600 text-white shadow">
        พิมพ์
      </button>
    </div>
  </div>

  <!-- ฟอร์มค้นหา/กรอง -->
  <form method="get" class="no-print bg-white border border-gray-200 rounded-2xl shadow-sm p-4 md:p-5 grid grid-cols-1 md:grid-cols-7 gap-3 md:gap-4">
    <label class="block md:col-span-3">
      <span class="text-sm font-medium text-gray-700">ค้นหาเบอร์โทร</span>
      <input type="text" name="q" value="<?php echo h($q); ?>" placeholder="เช่น 0812345678"
             class="mt-1 w-full rounded-xl border-2 border-gray-300 h-11 px-3 focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500">
    </label>

    <label class="block md:col-span-2">
      <span class="text-sm font-medium text-gray-700">จากวันที่</span>
      <input type="date" name="from" value="<?php echo h($from); ?>"
             class="mt-1 w-full rounded-xl border-2 border-gray-300 h-11 px-3 focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500">
    </label>

    <label class="block md:col-span-2">
      <span class="text-sm font-medium text-gray-700">ถึงวันที่</span>
      <input type="date" name="to" value="<?php echo h($to); ?>"
             class="mt-1 w-full rounded-xl border-2 border-gray-300 h-11 px-3 focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500">
    </label>

    <div class="md:col-span-7 flex items-end justify-between gap-2">
      <div class="flex items-center gap-2">
        <span class="text-sm text-gray-500">แถว/หน้า</span>
        <select name="per_page" class="rounded-xl border-2 border-gray-300 h-10 px-2 focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500">
          <?php foreach([10,20,50,100] as $pp): ?>
            <option value="<?php echo $pp; ?>" <?php echo $pp==$perPage?'selected':''; ?>><?php echo $pp; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="flex gap-2">
        <!-- <button class="inline-flex items-center justify-center gap-2 px-4 h-11 rounded-xl bg-gray-900 hover:bg-black text-white shadow">
          ดูรายการ
        </button> -->
        <a href="<?php echo base_url(); ?>/points.php"
           class="inline-flex items-center justify-center gap-2 px-3 h-11 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 border border-gray-200">
          รีเซ็ต
        </a>
      </div>
    </div>
  </form>

  <!-- ตารางแต้ม -->
  <div class="card-table">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm table-head-sticky">
        <thead>
          <tr class="text-gray-600">
            <th class="text-left px-4 py-3 font-semibold">เบอร์โทร</th>
            <th class="text-right px-4 py-3 font-semibold">เช็คอิน (ครั้ง)</th>
            <th class="text-right px-4 py-3 font-semibold">สิทธิ์ฟรี (ชม.)</th>
            <th class="text-right px-4 py-3 font-semibold">เหลือสะสม</th>
            <th class="text-right px-4 py-3 font-semibold">รายได้รวม (฿)</th>
            <th class="text-left px-4 py-3 font-semibold">ล่าสุด</th>
            <!-- <th class="text-left px-4 py-3 font-semibold">ดูประวัติ</th> -->
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php if(empty($rows)): ?>
            <tr><td colspan="7" class="px-6 py-10 text-center text-gray-500">— ไม่มีข้อมูล —</td></tr>
          <?php else: foreach($rows as $r): 
            $vis = (int)$r['visits'];
            $freeEligible = intdiv($vis, 10);
            $remainder = $vis % 10;
          ?>
            <tr class="hover:bg-gray-50">
              <td class="px-4 py-2.5 font-medium text-gray-900"><?php echo h($r['phone']); ?></td>
              <td class="px-4 py-2.5 text-right num"><?php echo number_format($vis); ?></td>
              <td class="px-4 py-2.5 text-right num text-emerald-700 font-semibold"><?php echo $freeEligible; ?></td>
              <td class="px-4 py-2.5 text-right num"><?php echo $remainder; ?></td>
              <td class="px-4 py-2.5 text-right num"><?php echo number_format((int)$r['revenue'],0); ?></td>
              <td class="px-4 py-2.5 text-gray-700"><?php echo h(substr($r['last_checkin'],0,16)); ?></td>
              <!-- <td class="px-4 py-2.5">
                <a class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-700"
                   href="<?php echo base_url(); ?>/report.php?from=<?php echo h($from ?: ''); ?>&to=<?php echo h($to ?: ''); ?>&q_phone=<?php echo h($r['phone']); ?>">
                   เปิดรายงาน
                </a>
              </td> -->
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
    <?php
      // หน้าต่างเล็ก ๆ
      $window = 2;
      $range = [1];
      for($i=$page-$window; $i<=$page+$window; $i++){
        if ($i>1 && $i<$totalPages) $range[] = $i;
      }
      if ($totalPages > 1) $range[] = $totalPages;
      $range = array_values(array_unique($range)); sort($range);
    ?>
    <nav class="no-print mt-4 flex items-center justify-center" aria-label="Pagination">
      <div class="pager flex flex-wrap items-center gap-1">
        <a class="nav <?php echo $page==1?'pointer-events-none opacity-50':''; ?>"
           href="<?php echo $page==1?'#':page_url_points(1,$perPage,$q,$from,$to); ?>">«</a>
        <a class="nav <?php echo $page==1?'pointer-events-none opacity-50':''; ?>"
           href="<?php echo $page==1?'#':page_url_points($page-1,$perPage,$q,$from,$to); ?>">‹</a>

        <?php $prev=null; foreach($range as $p): ?>
          <?php if(!is_null($prev) && $p != $prev+1): ?>
            <span class="px-2 text-gray-400">…</span>
          <?php endif; $prev=$p; ?>
          <a class="<?php echo $p==$page?'bg-gray-900 text-white border border-gray-900 rounded-full px-3 py-1':'border border-gray-300 rounded-full px-3 py-1 hover:bg-gray-100'; ?>"
             href="<?php echo page_url_points($p,$perPage,$q,$from,$to); ?>"><?php echo $p; ?></a>
        <?php endforeach; ?>

        <a class="nav <?php echo $page>=$totalPages?'pointer-events-none opacity-50':''; ?>"
           href="<?php echo $page>=$totalPages?'#':page_url_points($page+1,$perPage,$q,$from,$to); ?>">›</a>
        <a class="nav <?php echo $page==$totalPages?'pointer-events-none opacity-50':''; ?>"
           href="<?php echo $page==$totalPages?'#':page_url_points($totalPages,$perPage,$q,$from,$to); ?>">»</a>
      </div>
    </nav>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/../templates/footer.php'; ?>
