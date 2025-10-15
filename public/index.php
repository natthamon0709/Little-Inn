<?php
// ไฮไลต์เมนู
$active = 'dashboard';

// โหลด dependency
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/Pricing.php';
require __DIR__ . '/../src/RoomRepository.php';
require __DIR__ . '/../src/BookingRepository.php';

// สร้าง repo + ดึงข้อมูล
$roomRepo    = new RoomRepository($pdo);
$bookingRepo = new BookingRepository($pdo);
$rooms       = $roomRepo->all();
$vacant      = $roomRepo->countVacant();
$occ         = $roomRepo->countOccupied();
$today_rev   = $bookingRepo->revenueOnDate(today());

// เปิดหน้า (มี <html> และ <body> อยู่ใน header.php แล้ว)
include __DIR__ . '/../templates/header.php';
?>

<!-- ====== เนื้อหาหน้านี้ เริ่ม ====== -->
<section class="space-y-6 min-h-0 flex flex-col">

  <!-- Hero/หัวข้อ + action -->
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
    <div>
      <h2 class="text-2xl md:text-3xl font-bold tracking-tight text-gray-900">แดชบอร์ด</h2>
      <p class="text-sm text-gray-500 mt-0.5">ภาพรวมสถานะห้องพักและการเช็คอิน/เอาท์แบบรวดเร็ว</p>
    </div>
    <div class="no-print flex gap-2">
      <a href="index.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gray-900 hover:bg-black text-white shadow">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6M20 20v-6h-6"/></svg>
        รีเฟรช
      </a>
      <a href="report.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white shadow">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6h6v6M7 20h10a2 2 0 002-2V7l-5-5H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
        รายงาน
      </a>
    </div>
  </div>

  <!-- สรุปสถิติ (Cards) -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="relative overflow-hidden rounded-2xl bg-white shadow border border-emerald-200">
      <div class="absolute -right-8 -top-8 h-24 w-24 rounded-full bg-emerald-50"></div>
      <div class="p-4">
        <div class="flex items-center justify-between">
          <div class="text-gray-500">ห้องว่าง</div>
          <div class="rounded-full p-2 bg-emerald-100 text-emerald-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h18M4 8h16M4 16h16"/></svg>
          </div>
        </div>
        <div class="mt-2 text-3xl font-semibold text-emerald-700"><?php echo $vacant; ?></div>
        <div class="mt-1 text-xs text-emerald-700/70">พร้อมสำหรับเช็คอินทันที</div>
      </div>
    </div>

    <div class="relative overflow-hidden rounded-2xl bg-white shadow border border-amber-200">
      <div class="absolute -right-8 -top-8 h-24 w-24 rounded-full bg-amber-50"></div>
      <div class="p-4">
        <div class="flex items-center justify-between">
          <div class="text-gray-500">ห้องไม่ว่าง</div>
          <div class="rounded-full p-2 bg-amber-100 text-amber-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h10"/></svg>
          </div>
        </div>
        <div class="mt-2 text-3xl font-semibold text-amber-600"><?php echo $occ; ?></div>
        <div class="mt-1 text-xs text-amber-700/70">กำลังมีผู้เข้าพัก</div>
      </div>
    </div>

    <div class="relative overflow-hidden rounded-2xl bg-white shadow border border-sky-200">
      <div class="absolute -right-8 -top-8 h-24 w-24 rounded-full bg-sky-50"></div>
      <div class="p-4">
        <div class="flex items-center justify-between">
          <div class="text-gray-500">รายได้วันนี้ (ตามเช็คเอาท์)</div>
          <div class="rounded-full p-2 bg-sky-100 text-sky-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 1.12-3 2.5S10.343 13 12 13s3 1.12 3 2.5S13.657 18 12 18m0-10V6m0 12v-2"/></svg>
          </div>
        </div>
        <div class="mt-2 text-3xl font-semibold text-sky-700"><?php echo format_baht($today_rev); ?> ฿</div>
        <div class="mt-1 text-xs text-sky-700/70">อัปเดตเมื่อ <?php echo date('H:i'); ?> น.</div>
      </div>
    </div>
  </div>

  <!-- หัวข้อห้อง + sticky sub-toolbar -->
  <div class="sticky top-0 z-10 -mx-2 px-2 pt-2 bg-gray-50/60 backdrop-blur supports-[backdrop-filter]:bg-gray-50/40">
    <div class="flex items-center justify-between">
      <h3 class="text-lg md:text-xl font-bold text-gray-800">สถานะห้องพัก</h3>
      <div class="text-xs md:text-sm text-gray-500">
        ทั้งหมด <span class="font-semibold"><?php echo count($rooms); ?></span> ห้อง • ว่าง <span class="font-semibold text-emerald-700"><?php echo $vacant; ?></span> • ไม่ว่าง <span class="font-semibold text-amber-700"><?php echo $occ; ?></span>
      </div>
    </div>
  </div>

  <!-- กริดห้อง -->
  <div class="flex-1 min-h-0">
    <div class="flex-1 min-h-0 overflow-y-auto pr-1 pb-36">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-4">
        <?php foreach ($rooms as $r): ?>
          <div class="group relative rounded-2xl bg-white shadow border <?php echo $r['status']==='vacant'?'border-emerald-200':'border-amber-200'; ?> p-4 transition hover:shadow-md">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <div class="h-2.5 w-2.5 rounded-full <?php echo $r['status']==='vacant'?'bg-emerald-500':'bg-amber-500'; ?>"></div>
                <div class="text-lg font-semibold tracking-tight"><?php echo h($r['name']); ?></div>
              </div>
              <span class="text-xs px-2 py-1 rounded-full <?php echo $r['status']==='vacant'?'bg-emerald-100 text-emerald-800':'bg-amber-100 text-amber-800'; ?>">
                <?php echo $r['status']==='vacant'?'ว่าง':'ไม่ว่าง'; ?>
              </span>
            </div>

            <?php if ($r['status']==='vacant'): ?>
              <!-- ฟอร์ม Check-in -->
              <form class="mt-4 space-y-3" method="post" action="checkin.php">
                <input type="hidden" name="room_id" value="<?php echo (int)$r['id']; ?>">

                <label class="block">
                  <span class="text-sm font-medium text-gray-700">ประเภทการพัก</span>
                  <select name="type"
                          class="mt-1 w-full h-11 rounded-xl border-2 border-gray-300 bg-white/90 shadow-inner
                                 text-gray-900 placeholder-gray-400
                                 focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500">
                    <option value="temp">ชั่วคราว (รายชั่วโมง)</option>
                    <option value="overnight">ค้างคืน (คิดตามเวลาเช็คอิน)</option>
                  </select>
                </label>

                <!-- วิธีชำระเงิน (คาดการณ์) ตอนเช็คอิน -->
                <label class="block">
                  <span class="text-sm font-medium text-gray-700">วิธีชำระเงิน (คาดการณ์)</span>
                  <select name="payment_method"
                          class="mt-1 w-full h-11 rounded-xl border-2 border-gray-300 bg-white/90 shadow-inner
                                 text-gray-900 focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500">
                    <option value="">— ยังไม่ระบุ —</option>
                    <option value="cash">เงินสด</option>
                    <option value="transfer">โอน</option>
                    <option value="card">บัตร</option>
                    <option value="other">อื่น ๆ</option>
                  </select>
                </label>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <!-- เดิม: label ชื่อผู้เข้าพัก -->
                <label class="block">
                  <span class="text-sm font-medium text-gray-700">เบอร์โทรผู้เข้าพัก</span>
                  <input type="tel" name="phone" id="phone-<?php echo (int)$r['id'];?>"
                        placeholder="เช่น 081-234-5678"
                        inputmode="numeric" pattern="[0-9\- ]{9,13}"
                        class="mt-1 w-full h-11 rounded-xl border-2 border-gray-300 bg-white/90 shadow-inner
                                text-gray-900 placeholder-gray-400
                                focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500">
                  <small class="text-xs text-gray-500">ใส่ 10 หลัก ระบบจะฟอร์แมตให้อัตโนมัติ</small>
                </label>

                  <label class="block">
                    <span class="text-sm font-medium text-gray-700">เวลาเช็คอิน</span>
                    <input type="datetime-local" name="checkin_at" value="<?php echo date('Y-m-d\\TH:i'); ?>"
                           class="mt-1 w-full h-11 rounded-xl border-2 border-gray-300 bg-white/90 shadow-inner
                                  text-gray-900
                                  focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500">
                  </label>
                </div>

                <label class="block">
                  <span class="text-sm font-medium text-gray-700">หมายเหตุ</span>
                  <input type="text" name="note" placeholder="เช่น ขอผ้าห่มเพิ่ม"
                         class="mt-1 w-full h-11 rounded-xl border-2 border-gray-300 bg-white/90 shadow-inner
                                text-gray-900 placeholder-gray-400
                                focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500">
                </label>

                <button class="w-full mt-1 inline-flex items-center justify-center gap-2 h-11 px-4 rounded-xl
                               bg-emerald-600 hover:bg-emerald-700 text-white font-medium shadow">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                  Check-in
                </button>
                <!-- ปุ่มดูรายละเอียด - วางใต้ปุ่ม Check-in -->
                <a href="<?php echo base_url(); ?>/room.php?id=<?php echo (int)$r['id']; ?>"
                  class="mt-2 inline-flex items-center gap-1.5 text-xs px-2 py-1 rounded-lg
                          border border-gray-200 hover:bg-gray-50 text-gray-700">
                  ดูรายละเอียดห้องนี้
                </a>

                <p class="text-xs text-gray-500">
                  ชั่วคราว: 1 ชม.แรก 130, ชั่วโมงถัดไป +60/ชม. • ค้างคืน: 12:00=550, 15:00=450, 18:00=350
                </p>
              </form>

            <?php else: ?>
              <?php
                // ดึงข้อมูล booking ปัจจุบันของห้อง
                $stmt = $pdo->prepare('SELECT b.* FROM rooms r JOIN bookings b ON b.id=r.current_booking_id WHERE r.id=?');
                $stmt->execute([$r['id']]);
                $bk = $stmt->fetch();
              ?>

              <div class="mt-3 text-sm space-y-1.5">
                <?php if($bk): ?>
                  <div class="text-gray-600">
                    เข้าอยู่:
                    <span class="font-medium text-gray-800"><?php echo h(substr($bk['checkin_at'],0,16)); ?></span>
                  </div>

                  <!-- นาฬิกาจับเวลา: แสดงเวลาที่พักตั้งแต่เช็คอิน -->
                  <div class="text-gray-600">
                    ระยะเวลาที่พัก:
                    <span class="font-medium text-gray-900">
                      <span class="live-timer" data-checkin="<?php echo h($bk['checkin_at']); ?>">—</span>
                    </span>
                  </div>

                  <div class="text-gray-600">
                    ประเภท:
                    <span class="font-medium"><?php echo $bk['type']==='temp'?'ชั่วคราว':'ค้างคืน'; ?></span>
                    <?php if ($bk['type']==='overnight'): ?>
                      <span class="ml-2 text-xs text-gray-500">(เรต: <?php echo format_baht(Pricing::overnightByCheckin($bk['checkin_at'], (int)$r['id'])); ?>)</span>
                    <?php endif; ?>
                  </div>

                  <?php
                    // ป้ายแสดงวิธีชำระเงินปัจจุบัน (ถ้ามีบันทึกไว้)
                    $pm = $bk['payment_method'] ?? '';
                    $pmLabel = 'ไม่ได้ระบุ';
                    $pmClass = 'bg-gray-100 text-gray-700 ring-1 ring-gray-200';
                    if ($pm === 'cash')         { $pmLabel = 'เงินสด';  $pmClass = 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100'; }
                    elseif ($pm === 'transfer') { $pmLabel = 'โอน';     $pmClass = 'bg-sky-50 text-sky-700 ring-1 ring-sky-100'; }
                    elseif ($pm === 'card')     { $pmLabel = 'บัตร';    $pmClass = 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-100'; }
                    elseif ($pm === 'other')    { $pmLabel = 'อื่น ๆ';  $pmClass = 'bg-amber-50 text-amber-700 ring-1 ring-amber-100'; }
                  ?>
                  <div class="text-gray-600">
                    การชำระเงิน:
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium <?php echo $pmClass; ?>">
                      <?php echo $pmLabel; ?>
                    </span>
                  </div>

                  <?php if(!empty($bk['guest_name'])): ?>
                    <div class="text-gray-600">ผู้เข้าพัก: <span class="font-medium"><?php echo h($bk['guest_name']); ?></span></div>
                  <?php endif; ?>
                  <?php if(!empty($bk['note'])): ?>
                    <div class="text-gray-600">หมายเหตุ: <span class="font-medium"><?php echo h($bk['note']); ?></span></div>
                  <?php endif; ?>
                <?php endif; ?>
              </div>

              <!-- ฟอร์ม Check-out -->
              <form class="mt-3 space-y-3" method="post" action="checkout.php">
                <input type="hidden" name="room_id" value="<?php echo (int)$r['id']; ?>">

                <label class="block">
                  <span class="text-sm font-medium text-gray-700">เวลาเช็คเอาท์</span>
                  <input type="datetime-local" name="checkout_at" value="<?php echo date('Y-m-d\\TH:i'); ?>"
                         class="mt-1 w-full h-11 rounded-xl border-2 border-amber-300 bg-white/90 shadow-inner
                                text-gray-900 focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500">
                </label>

                <!-- วิธีชำระเงิน ตอนเช็คเอาท์ -->
                <label class="block">
                  <span class="text-sm font-medium text-gray-700">วิธีชำระเงิน</span>
                  <select name="payment_method"
                          class="mt-1 w-full h-11 rounded-xl border-2 border-gray-300 bg-white/90 shadow-inner
                                 text-gray-900 focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500">
                    <option value="cash"     <?php echo (($bk['payment_method'] ?? '')==='cash')?'selected':''; ?>>เงินสด</option>
                    <option value="transfer" <?php echo (($bk['payment_method'] ?? '')==='transfer')?'selected':''; ?>>โอน</option>
                    <option value="card"     <?php echo (($bk['payment_method'] ?? '')==='card')?'selected':''; ?>>บัตร</option>
                    <option value="other"    <?php echo (($bk['payment_method'] ?? '')==='other')?'selected':''; ?>>อื่น ๆ</option>
                  </select>
                </label>

                <button class="w-full inline-flex items-center justify-center gap-2 h-11 px-4 rounded-xl
                               bg-amber-600 hover:bg-amber-700 text-white font-medium shadow">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                  Check-out & คิดเงิน
                </button>
                <!-- ปุ่มดูรายละเอียด - วางใต้ปุ่ม Check-out -->
                <a href="<?php echo base_url(); ?>/room.php?id=<?php echo (int)$r['id']; ?>"
                  class="mt-2 inline-flex items-center gap-1.5 text-xs px-2 py-1 rounded-lg
                          border border-gray-200 hover:bg-gray-50 text-gray-700">
                  ดูรายละเอียดห้องนี้
                </a>
                <p class="text-xs text-gray-500">ชั่วคราวปัดชั่วโมงขึ้นอัตโนมัติ</p>
              </form>
              
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</section>
<!-- ====== เนื้อหาหน้านี้ จบ ====== -->

<!-- นาฬิกาจับเวลา: อัปเดตทุก 1 วินาที -->
<script>
(function(){
  function toDateLocal(s){
    if(!s) return null;
    // รองรับฟอร์แมต "YYYY-MM-DD HH:MM:SS" หรือ "YYYY-MM-DDTHH:MM:SS"
    const iso = s.replace(' ', 'T');
    const d = new Date(iso);
    return isNaN(d) ? null : d;
  }
  function fmtElapsed(ms){
    let sec = Math.floor(ms / 1000);
    if (sec < 0) sec = 0;
    const h = Math.floor(sec / 3600);
    sec -= h * 3600;
    const m = Math.floor(sec / 60);
    sec -= m * 60;
    const pad = n => String(n).padStart(2, '0');
    return `${h} ชม ${pad(m)} นาที ${pad(sec)} วินาที`;
  }
  function tick(){
    const now = Date.now();
    document.querySelectorAll('.live-timer[data-checkin]').forEach(el=>{
      const s = el.dataset.checkin;
      const d = toDateLocal(s);
      if (!d) { el.textContent = '—'; return; }
      el.textContent = fmtElapsed(now - d.getTime());
    });
  }
  tick();
  setInterval(tick, 1000);
})();
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
