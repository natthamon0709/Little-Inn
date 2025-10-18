    </main>
  </div><!-- /content wrapper -->

  <footer class="mt-auto bg-emerald-700 text-emerald-50 relative z-10">
  </footer>

  <script>
  (function(){
    const mq       = window.matchMedia('(min-width: 768px)');
    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('overlay');
    const content  = document.getElementById('content');
    const btn      = document.getElementById('btnSidebarToggle');
    const btnClose = document.getElementById('btnSidebarClose');
    const iconMenu = document.getElementById('iconMenu');
    const iconClose= document.getElementById('iconClose');

    const thMonths = ["ม.ค.","ก.พ.","มี.ค.","เม.ย.","พ.ค.","มิ.ย.","ก.ค.","ส.ค.","ก.ย.","ต.ค.","พ.ย.","ธ.ค."];
  const pad = n => String(n).padStart(2,'0');

  // แสดงผลสำหรับคนดู: "12 ต.ค. 2568 14:23:05"
  function formatThaiDateTime(d){
    const y = d.getFullYear() + 543;
    return `${d.getDate()} ${thMonths[d.getMonth()]} ${y} ` +
           `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
  }

  // ค่าที่ส่งไปเซิร์ฟเวอร์ใน hidden input: "YYYY-MM-DD HH:MM:SS"
  function toServerDateTime(d){
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ` +
           `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
  }

  function tick(){
    const now = new Date();

    // อัปเดตข้อความแสดงผล (ไทย)
    document.querySelectorAll('.live-now-th').forEach(el=>{
      el.textContent = formatThaiDateTime(now);
    });

    // อัปเดต hidden input name="checkin_at"
    document.querySelectorAll('input[name="checkin_at"][data-autonow]').forEach(inp=>{
      inp.value = toServerDateTime(now);
    });
  }

  tick();
  setInterval(tick, 1000);

    // ป้องกัน null
    if(!sidebar || !content || !btn || !iconMenu || !iconClose){
      console.warn('Sidebar toggle: required elements missing.');
      return;
    }

    // --- เดสก์ท็อป: ใช้ md:* เท่านั้น เพื่อไม่สู้กับ tailwind ---
    function setDesktop(open){
      if(open){
        sidebar.classList.remove('md:-translate-x-full');
        sidebar.classList.add('md:translate-x-0');
        content.classList.remove('md:ml-0');
        content.classList.add('md:ml-72');
        overlay?.classList.add('hidden');
        iconMenu.classList.add('hidden');
        iconClose.classList.remove('hidden');
        btn.setAttribute('aria-expanded', 'true');
      }else{
        sidebar.classList.remove('md:translate-x-0');
        sidebar.classList.add('md:-translate-x-full');
        content.classList.remove('md:ml-72');
        content.classList.add('md:ml-0');
        overlay?.classList.add('hidden');
        iconMenu.classList.remove('hidden');
        iconClose.classList.add('hidden');
        btn.setAttribute('aria-expanded', 'false');
      }
    }

    // --- มือถือ: ไม่ใช้ md:* (เพราะต่ำกว่า breakpoint) ---
    function setMobile(open){
      if(open){
        sidebar.classList.remove('-translate-x-full');
        overlay?.classList.remove('hidden');
        iconMenu.classList.add('hidden');
        iconClose.classList.remove('hidden');
        btn.setAttribute('aria-expanded', 'true');
      }else{
        sidebar.classList.add('-translate-x-full');
        overlay?.classList.add('hidden');
        iconMenu.classList.remove('hidden');
        iconClose.classList.add('hidden');
        btn.setAttribute('aria-expanded', 'false');
      }
    }

    function toggle(){
      if(mq.matches){
        const isClosed = sidebar.classList.contains('md:-translate-x-full');
        setDesktop(isClosed);
      }else{
        const isClosed = sidebar.classList.contains('-translate-x-full');
        setMobile(isClosed);
      }
    }

    function applyInitial(){
      if(mq.matches){
        setDesktop(true);   // เริ่ม "เปิด" บนเดสก์ท็อป
      }else{
        setMobile(false);   // เริ่ม "ปิด" บนมือถือ
      }
    }

    // === ฟังก์ชันฟอร์แมตเบอร์โทร ===
    function fmtPhone(v){
      const d = v.replace(/\D+/g,'').slice(0,10);
      if (d.length <= 3) return d;
      if (d.length <= 6) return d.slice(0,3)+'-'+d.slice(3);
      return d.slice(0,3)+'-'+d.slice(3,6)+'-'+d.slice(6);
    }

    // === event listeners ===
    btn.addEventListener('click', toggle);
    btnClose?.addEventListener('click', () => (mq.matches ? setDesktop(false) : setMobile(false)));
    overlay?.addEventListener('click', () => setMobile(false));
    mq.addEventListener('change', applyInitial);

    // ฟอร์แมตเบอร์โทร (ทำหลัง DOM พร้อมแล้ว — เราอยู่ท้าย <body> อยู่แล้ว)
    document.querySelectorAll('input[name="phone"]').forEach(inp=>{
      inp.addEventListener('input', ()=>{
        const pos = inp.selectionStart;
        const before = inp.value;
        inp.value = fmtPhone(inp.value);
        if (pos !== null && before.length < inp.value.length) {
          inp.selectionStart = inp.selectionEnd = pos+1;
        }
      });
    });

    // init
    applyInitial();
  })();
</script>




</body>
</html>
