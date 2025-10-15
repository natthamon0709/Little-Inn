<?php
// public/report.php
$active = 'report';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../src/helpers.php';

// ========= รับค่า filter =========
$from = $_GET['from'] ?? today();
$to   = $_GET['to']   ?? today();
if (strtotime($from) > strtotime($to)) { [$from, $to] = [$to, $from]; }

// ========= รับค่าหน้า =========
$perPage = max(1, (int)($_GET['per_page'] ?? 20));
$page    = max(1, (int)($_GET['page'] ?? 1));

// ========= Export CSV (ตาม filter) =========
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="little_inn_report_' . $from . '_to_' . $to . '.csv"');

  $out = fopen('php://output', 'w');
  // UTF-8 BOM เผื่อเปิดใน Excel
  fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
  fputcsv($out, ['checkout_date','room','type','payment_method','checkin_at','checkout_at','price']);

  $stmt = $pdo->prepare("
    SELECT r.name AS room_name, b.type, COALESCE(b.payment_method,'') AS payment_method,
           b.checkin_at, b.checkout_at, b.price
    FROM bookings b
    JOIN rooms r ON r.id = b.room_id
    WHERE b.checkout_at IS NOT NULL
      AND DATE(b.checkout_at) BETWEEN ? AND ?
    ORDER BY b.checkout_at DESC
  ");
  $stmt->execute([$from, $to]);
  while ($row = $stmt->fetch()) {
    fputcsv($out, [
      substr($row['checkout_at'],0,10),
      $row['room_name'],
      $row['type'],
      $row['payment_method'],
      $row['checkin_at'],
      $row['checkout_at'],
      (int)$row['price'],
    ]);
  }
  fclose($out);
  exit;
}

// ========= นับแถวทั้งหมด + รวมยอดทั้งหมดในช่วง =========
$stmt = $pdo->prepare("
  SELECT COUNT(*) AS cnt, COALESCE(SUM(price),0) AS sum_all
  FROM bookings
  WHERE checkout_at IS NOT NULL
    AND DATE(checkout_at) BETWEEN ? AND ?
");
$stmt->execute([$from, $to]);
$meta = $stmt->fetch();
$totalRows = (int)$meta['cnt'];
$sumAll    = (float)$meta['sum_all'];

// ========= คำนวณหน้า/offset =========
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = max(1, min($page, $totalPages));
$offset     = ($page - 1) * $perPage;

// ========= ดึงรายการเฉพาะหน้าปัจจุบัน =========
$stmt = $pdo->prepare("
  SELECT b.*, r.name AS room_name, COALESCE(b.payment_method,'') AS payment_method
  FROM bookings b
  JOIN rooms r ON r.id = b.room_id
  WHERE b.checkout_at IS NOT NULL
    AND DATE(b.checkout_at) BETWEEN ? AND ?
  ORDER BY b.checkout_at DESC
  LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $from);
$stmt->bindValue(2, $to);
$stmt->bindValue(3, $perPage, PDO::PARAM_INT);
$stmt->bindValue(4, $offset,  PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

// รวมเฉพาะหน้าปัจจุบัน
$sumPage = 0.0;
foreach ($rows as $r) { $sumPage += (float)$r['price']; }

// helper ทำลิงก์คงค่า filter
function page_url($page, $perPage, $from, $to){
  $q = http_build_query([
    'from'     => $from,
    'to'       => $to,
    'page'     => $page,
    'per_page' => $perPage
  ]);
  return base_url() . "/report.php?{$q}";
}

// สร้างช่วงเลขหน้าแบบมี ... อัตโนมัติ (หน้าต่างกว้างขึ้น)
function page_range($page, $totalPages, $window = 2){
  $range = [1];
  for ($i = $page - $window; $i <= $page + $window; $i++) {
    if ($i > 1 && $i < $totalPages) $range[] = $i;
  }
  if ($totalPages > 1) $range[] = $totalPages;
  $range = array_values(array_unique($range));
  sort($range);
  return $range;
}

include __DIR__ . '/../templates/header.php';
?>

<!-- สไตล์โมเดิร์นเฉพาะหน้านี้ -->
<style>
  :root{ --font-sans:'Kanit', ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif; }
  html, body{ font-family: var(--font-sans); }
  .card-table{ border-radius:16px; box-shadow:0 1px 2px rgba(16,24,40,.06); border:1px solid #e5e7eb; overflow:hidden; background:#fff;}
  .table-head-sticky thead{ position:sticky; top:0; z-index:10; background:#f9fafb; }
  .table-modern tbody tr:nth-child(odd){ background:#fff; }
  .table-modern tbody tr:nth-child(even){ background:#fcfcfd; }
  .table-modern tbody tr:hover{ background:#f8fafc; }
  .num{ font-variant-numeric: tabular-nums; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; }
  .chip{ padding:.4rem .7rem; border-radius:.75rem; border:1px solid #e5e7eb; background:#fff }
  .chip:hover{ background:#f9fafb }

  /* Pager */
  .pager a, .pager span.gap{
    border:1px solid #e5e7eb; border-radius:9999px; padding:.45rem .75rem; min-width:2.25rem;
    display:inline-flex; align-items:center; justify-content:center; line-height:1;
  }
  .pager a:hover{ background:#f9fafb; }
  .pager a.active{ background:#111827; color:#fff; border-color:#111827; }
  .pager a.disabled{ pointer-events:none; opacity:.45; }
  .pager .nav{ padding:.45rem .6rem; }

  @media print{
    .no-print{ display:none !important; }
    body{ background:#fff; }
    .card-table{ box-shadow:none; border:0; }
  }
</style>

<section class="print-area">
  <!-- ActionBar -->
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-5">
    <div>
      <h2 class="text-2xl md:text-3xl font-bold tracking-tight text-gray-900">รายงานรายได้</h2>
      <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 text-emerald-700 px-3 py-1 ring-1 ring-emerald-100">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M3 11h18M5 19h14a2 2 0 002-2v-6H3v6a2 2 0 002 2z"/></svg>
          ช่วง: <b><?php echo h($from); ?></b> - <b><?php echo h($to); ?></b>
        </span>
        <span class="inline-flex items-center gap-2 rounded-full bg-gray-100 text-gray-700 px-3 py-1 ring-1 ring-gray-200">
          ทั้งช่วง: <b><?php echo format_baht($sumAll); ?></b> ฿
        </span>
      </div>
    </div>

    <div class="no-print flex flex-wrap gap-2">
      <a href="<?php echo base_url(); ?>/report.php?<?php echo http_build_query(['from'=>$from,'to'=>$to,'export'=>'csv']); ?>"
         class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white shadow">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h8m-8 4h8m-8 4h5M4 5h16a2 2 0 012 2v9a2 2 0 01-2 2h-6l-4 4v-4H4a2 2 0 01-2-2V7a2 2 0 012-2z"/></svg>
        Export CSV
      </a>
      <button onclick="window.print()"
        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-600 text-white shadow">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18h12M6 22h12"/></svg>
        พิมพ์ทั้งหน้า
      </button>
    </div>
  </div>

  <!-- ฟอร์ม Filter + PerPage (การ์ด) -->
  <form id="filterForm" method="get"
    class="no-print bg-white border border-gray-200 rounded-2xl shadow-sm p-4 md:p-5
           grid grid-cols-1 md:grid-cols-7 gap-3 md:gap-4">

    <!-- from -->
    <label class="block md:col-span-2">
      <span class="text-sm font-medium text-gray-700">จากวันที่</span>
      <div class="mt-1 relative">
        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center">
          <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M3 11h18M5 19h14a2 2 0 002-2v-6H3v6a2 2 0 002 2z"/>
          </svg>
        </span>
        <input type="date" name="from" value="<?php echo h($from); ?>"
          class="w-full rounded-xl border-gray-300 pl-10 pr-3 py-2
                 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30
                 bg-white text-gray-800" />
      </div>
    </label>

    <!-- to -->
    <label class="block md:col-span-2">
      <span class="text-sm font-medium text-gray-700">ถึงวันที่</span>
      <div class="mt-1 relative">
        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center">
          <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M3 11h18M5 19h14a2 2 0 002-2v-6H3v6a2 2 0 002 2z"/>
          </svg>
        </span>
        <input type="date" name="to" value="<?php echo h($to); ?>"
          class="w-full rounded-xl border-gray-300 pl-10 pr-3 py-2
                 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30
                 bg-white text-gray-800" />
      </div>
    </label>

    <!-- per page -->
    <label class="block md:col-span-1">
      <span class="text-sm font-medium text-gray-700">แถว/หน้า</span>
      <div class="mt-1 relative">
        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center">
          <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>
          </svg>
        </span>
        <select name="per_page" id="perPage"
          class="w-full rounded-xl border-gray-300 pl-10 pr-8 py-2 appearance:none
                 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30
                 bg-white text-gray-800">
          <?php foreach ([10,20,50,100] as $pp): ?>
            <option value="<?php echo $pp; ?>" <?php echo $pp==$perPage?'selected':''; ?>><?php echo $pp; ?></option>
          <?php endforeach; ?>
        </select>
        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
          <svg class="w-4 h-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/>
          </svg>
        </span>
      </div>
    </label>

    <!-- actions -->
    <div class="md:col-span-2 flex items-end">
        <div class="w-full flex flex-col sm:flex-row gap-2">

            <!-- ปุ่มดูรายงาน -->
            <button type="submit"
            class="flex-1 inline-flex items-center justify-center gap-2 h-11
                    rounded-xl bg-gray-900 text-white shadow-sm
                    hover:bg-black focus:outline-none focus:ring-2 focus:ring-gray-400/50">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5a6 6 0 100 12 6 6 0 000-12zm10 10l-3.5-3.5"/>
            </svg>
            ดูรายงาน
            </button>

            <!-- ปุ่มรีเซ็ต -->
            <a href="<?php echo base_url(); ?>/report.php"
            class="inline-flex items-center justify-center gap-2 h-11 px-3
                    rounded-xl border border-gray-200 bg-white text-gray-700 shadow-sm
                    hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-400/40">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6M20 20v-6h-6M5.64 18.36A9 9 0 1018.36 5.64"/>
            </svg>
            รีเซ็ต
            </a>

        </div>
    </div>


    <!-- ทางลัด -->
    <div class="md:col-span-7 -mt-1 text-xs text-gray-600 flex flex-wrap items-center gap-2">
      ทางลัด:
      <button type="button" data-range="today"      class="chip">วันนี้</button>
      <button type="button" data-range="yesterday"  class="chip">เมื่อวาน</button>
      <button type="button" data-range="last7"      class="chip">7 วันล่าสุด</button>
      <button type="button" data-range="thismonth"  class="chip">เดือนนี้</button>
    </div>
  </form>

  <!-- ตาราง (การ์ดโมเดิร์น) -->
  <div class="card-table">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm table-modern table-head-sticky">
        <colgroup>
          <col class="w-[160px]" />  <!-- เช็คเอาท์(วันที่) -->
          <col class="w-[140px]" />  <!-- ห้อง -->
          <col class="w-[120px]" />  <!-- ประเภท -->
          <col class="w-[140px]" />  <!-- วิธีชำระเงิน -->
          <col class="w-[160px]" />  <!-- เช็คอิน -->
          <col class="w-[160px]" />  <!-- เช็คเอาท์ -->
          <col class="w-[140px]" />  <!-- ยอดเงิน -->
          <col class="w-[160px]" />  <!-- ทำรายการ -->
        </colgroup>
        <thead>
          <tr class="text-gray-600">
            <th class="text-left px-4 py-3 font-semibold">เช็คเอาท์</th>
            <th class="text-left px-4 py-3 font-semibold">ห้อง</th>
            <th class="text-left px-4 py-3 font-semibold">ประเภท</th>
            <th class="text-left px-4 py-3 font-semibold">วิธีชำระเงิน</th>
            <th class="text-left px-4 py-3 font-semibold">เช็คอิน</th>
            <th class="text-left px-4 py-3 font-semibold">เช็คเอาท์</th>
            <th class="text-right px-4 py-3 font-semibold">ยอดเงิน (฿)</th>
            <th class="text-right px-4 py-3 font-semibold no-print">ทำรายการ</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php if (empty($rows)): ?>
            <tr>
              <td colspan="8" class="px-6 py-12 text-center">
                <div class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-gray-50 text-gray-500 ring-1 ring-gray-200">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6h6v6M7 20h10a2 2 0 002-2V7l-5-5H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                  — ไม่มีรายการในช่วงวันที่เลือก —
                </div>
              </td>
            </tr>
          <?php else: foreach ($rows as $row): ?>
            <?php
              $pay = trim((string)$row['payment_method']);
              $payBadge = $pay ?: '—';
              $payClass = 'bg-gray-50 text-gray-700 ring-1 ring-gray-200';
              if ($pay === 'cash')    { $payBadge = 'เงินสด'; $payClass = 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100'; }
              if ($pay === 'transfer'){ $payBadge = 'โอน';   $payClass = 'bg-sky-50 text-sky-700 ring-1 ring-sky-100'; }
              if ($pay === 'card')    { $payBadge = 'บัตร';   $payClass = 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-100'; }
            ?>
            <tr class="transition hover:bg-gray-50"
                data-room="<?php echo h($row['room_name']); ?>"
                data-type="<?php echo $row['type']==='temp'?'ชั่วคราว':'ค้างคืน'; ?>"
                data-checkin="<?php echo h(substr($row['checkin_at'], 0, 16)); ?>"
                data-checkout="<?php echo h(substr($row['checkout_at'], 0, 16)); ?>"
                data-price="<?php echo h(format_baht($row['price'])); ?>">
              <td class="px-4 py-2.5 text-gray-700"><?php echo h(substr($row['checkout_at'], 0, 16)); ?></td>
              <td class="px-4 py-2.5 font-medium text-gray-900"><?php echo h($row['room_name']); ?></td>
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
              <td class="px-4 py-2.5 text-right no-print">
                <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 hover:bg-gray-50"
                        onclick="printRow(this)">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18h12M6 22h12"/></svg>
                  พิมพ์ใบสรุป
                </button>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
        <tfoot class="bg-gray-50">
          <tr class="border-t border-gray-200">
            <td colspan="6" class="px-4 py-3 text-right font-semibold text-gray-700">รวม (หน้านี้)</td>
            <td class="px-4 py-3 text-right font-semibold num"><?php echo format_baht($sumPage); ?> ฿</td>
            <td class="px-4 py-3 no-print"></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <!-- Pagination: ท้ายตาราง แสดงเสมอ -->
  <?php $pgs = page_range($page, $totalPages, 2); ?>
  <nav class="no-print mt-4 flex w-full items-center justify-center" aria-label="Pagination">
    <div class="pager flex flex-wrap items-center gap-1">
      <!-- First -->
      <a href="<?php echo $page==1 ? '#' : page_url(1,$perPage,$from,$to); ?>"
         class="nav <?php echo $page==1?'disabled':''; ?>" aria-label="หน้าแรก">«</a>

      <!-- Prev -->
      <a href="<?php echo $page<=1 ? '#' : page_url($page-1,$perPage,$from,$to); ?>"
         class="nav <?php echo $page<=1?'disabled':''; ?>" aria-label="ก่อนหน้า">‹</a>

      <!-- Numbers -->
      <?php $prev = null; foreach ($pgs as $p): ?>
        <?php if (!is_null($prev) && $p != $prev + 1): ?>
          <span class="gap px-2 text-gray-400">…</span>
        <?php endif; $prev = $p; ?>

        <a href="<?php echo page_url($p,$perPage,$from,$to); ?>"
           class="<?php echo $p==$page?'active':''; ?>"
           aria-current="<?php echo $p==$page?'page':'false'; ?>">
          <?php echo $p; ?>
        </a>
      <?php endforeach; ?>

      <!-- Next -->
      <a href="<?php echo $page>=$totalPages ? '#' : page_url($page+1,$perPage,$from,$to); ?>"
         class="nav <?php echo $page>=$totalPages?'disabled':''; ?>" aria-label="ถัดไป">›</a>

      <!-- Last -->
      <a href="<?php echo $page==$totalPages ? '#' : page_url($totalPages,$perPage,$from,$to); ?>"
         class="nav <?php echo $page==$totalPages?'disabled':''; ?>" aria-label="หน้าสุดท้าย">»</a>
    </div>
  </nav>
</section>

<!-- JS: chips, autosubmit per_page, และ print รายแถว -->
<script>
(function(){
  // autosubmit per_page -> รีเซ็ตเป็นหน้า 1
  document.getElementById('perPage')?.addEventListener('change', function(){
    const form = document.getElementById('filterForm');
    const hidden = document.createElement('input');
    hidden.type = 'hidden'; hidden.name = 'page'; hidden.value = '1';
    form.appendChild(hidden);
    form.submit();
  });

  // chips เลือกช่วงเร็ว
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
          case 'today':      a=t; b2=t; break;
          case 'yesterday':  a=new Date(t); a.setDate(a.getDate()-1); b2=new Date(a); break;
          case 'last7':      b2=t; a=new Date(t); a.setDate(a.getDate()-6); break;
          case 'thismonth':  a=new Date(t.getFullYear(), t.getMonth(), 1); b2=new Date(t.getFullYear(), t.getMonth()+1, 0); break;
        }
        from.value = fmt(a); to.value = fmt(b2);
        const hidden = document.createElement('input');
        hidden.type = 'hidden'; hidden.name = 'page'; hidden.value = '1';
        form.appendChild(hidden);
        form.submit();
      });
    });
  }
})();

// พิมพ์ใบสรุปรายการ (รายแถว)
function printRow(btn){
  const tr = btn.closest('tr');
  const data = {
    room: tr.dataset.room,
    type: tr.dataset.type,
    checkin: tr.dataset.checkin,
    checkout: tr.dataset.checkout,
    price: tr.dataset.price
  };

  const html = `<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>ใบสรุปรายการ | Little Inn</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Kanit:wght@400;600;700&display=swap');
  body{font-family:'Kanit',system-ui,-apple-system,Segoe UI,Roboto,'Helvetica Neue',Arial,'Noto Sans',sans-serif;margin:24px;color:#111;}
  .card{border:1px solid #e5e7eb;border-radius:16px;padding:20px;}
  .title{font-weight:700;font-size:20px;margin-bottom:8px;}
  .muted{color:#6b7280;font-size:12px;margin-bottom:16px;}
  .grid{display:grid;grid-template-columns:140px 1fr;gap:8px 16px;font-size:14px;}
  .sum{margin-top:16px;text-align:right;font-weight:700;font-size:18px;}
  .footer{margin-top:16px;color:#6b7280;font-size:12px;text-align:center;}
  @media print{.noprint{display:none} body{margin:0;padding:24px}}
  .btn{display:inline-block;padding:8px 14px;border-radius:10px;border:1px solid #e5e7eb;text-decoration:none;color:#111;margin-right:8px;}
</style>
</head>
<body>
  <div class="card">
    <div class="title">ใบสรุปรายการเช็คเอาท์</div>
    <div class="muted">Little Inn • พิมพ์เมื่อ ${new Date().toLocaleString()}</div>
    <div class="grid">
      <div>ห้อง</div><div><b>${data.room}</b></div>
      <div>ประเภท</div><div>${data.type}</div>
      <div>เช็คอิน</div><div>${data.checkin}</div>
      <div>เช็คเอาท์</div><div>${data.checkout}</div>
    </div>
    <div class="sum">ยอดชำระทั้งหมด: ${data.price} ฿</div>
    <div class="footer">ขอบคุณที่ใช้บริการ • ระบบบริหารห้องพัก Little Inn</div>
  </div>
  <div class="noprint" style="margin-top:12px;">
    <a href="#" onclick="window.print();return false;" class="btn">พิมพ์</a>
    <a href="#" onclick="window.close();return false;" class="btn">ปิด</a>
  </div>
  <script>
    window.addEventListener('load', function(){
      window.print();
      setTimeout(function(){ window.close(); }, 100);
    });
  <\/script>
</body>
</html>`;

  const w = window.open('', '_blank', 'width=720,height=900');
  w.document.open(); w.document.write(html); w.document.close();
}
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
