<?php require_once __DIR__ . '/../src/helpers.php'; ?>
<!doctype html>
<html lang="th" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Little Inn Management</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  body { font-family: 'Kanit', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Liberation Sans', sans-serif; }
  @media print { .no-print{display:none!important} .print-area{margin:0} }
</style>
</head>

<body class="h-full min-h-screen flex flex-col bg-gray-50">
  <!-- Topbar -->
  <header class="bg-emerald-700 text-white">
    <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <!-- Hamburger -->
        <button id="btnSidebarToggle" type="button"
          class="inline-flex items-center justify-center w-10 h-10 rounded-lg hover:bg-emerald-600 focus:outline-none"
          aria-label="Toggle menu" aria-expanded="true" aria-controls="sidebar">
          <!-- ไอคอน: เมนู (สามขีด) -->
          <svg id="iconMenu" class="w-6 h-6 block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"></path>
          </svg>
          <!-- ไอคอน: กากบาท (ซ่อนตอนเริ่ม) -->
          <svg id="iconClose" class="w-6 h-6 hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>


        <!-- Title -->
        <div class="flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-emerald-100" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h11M9 21V3m12 18h-6a2 2 0 01-2-2V5a2 2 0 012-2h6v18z" />
          </svg>
          <!-- <h1 class="text-xl md:text-2xl font-semibold">Little Inn <span class="text-emerald-200">Management</span></h1> -->
        </div>
      </div>

      <div class="hidden md:flex items-center gap-2 text-sm">
        <span class="text-emerald-100"><?php echo date('d M Y • H:i'); ?></span>
        <a href="<?php echo base_url(); ?>/index.php" class="px-3 py-1.5 rounded-lg <?php echo ($active ?? '')==='dashboard'?'bg-white text-emerald-700':'bg-emerald-600 hover:bg-emerald-500 text-white'; ?>">แดชบอร์ด</a>
        <a href="<?php echo base_url(); ?>/report.php"   class="px-3 py-1.5 rounded-lg <?php echo ($active ?? '')==='report'   ?'bg-white text-emerald-700':'bg-emerald-600 hover:bg-emerald-500 text-white'; ?>">รายงาน</a>
        <a href="<?php echo base_url(); ?>/points.php"class="px-3 py-1.5 rounded-lg <?php echo ($active ?? '')==='points' ? 'bg-white text-emerald-700' : 'bg-emerald-600 hover:bg-emerald-500 text-white'; ?>">สะสมแต้ม</a>
      </div>
    </div>
  </header>

  <!-- Content wrapper -->
  <div class="flex-1 flex min-h-0">
    <!-- Sidebar -->
    <aside id="sidebar"
      class="fixed inset-y-0 left-0 z-40 w-72 bg-white border-r border-gray-200 shadow
             transform -translate-x-full transition-transform duration-200 ease-out"> <!-- ลบ md:translate-x-0 -->
      <div class="h-full flex flex-col">
        <div class="px-4 py-4 border-b flex items-center justify-between md:hidden">
          <div class="font-medium text-gray-700">เมนูระบบ</div>
          <button id="btnSidebarClose" class="inline-flex items-center justify-center w-9 h-9 rounded-lg hover:bg-gray-100" aria-label="Close menu">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <div class="px-4 py-4 border-b hidden md:block">
          <div class="text-sm text-gray-500">เมนูระบบ</div>
        </div>

        <nav class="flex-1 p-2 space-y-1 overflow-y-auto">
          <a href="<?php echo base_url(); ?>/index.php"
             class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm
             <?php echo ($active ?? '')==='dashboard' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'text-gray-700 hover:bg-gray-100'; ?>">
             <span class="inline-flex w-6 h-6 items-center justify-center">
               <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                 <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M13 5v6h6"/>
               </svg>
             </span>
             แดชบอร์ด
          </a>

          <a href="<?php echo base_url(); ?>/report.php"
             class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm
             <?php echo ($active ?? '')==='report' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'text-gray-700 hover:bg-gray-100'; ?>">
             <span class="inline-flex w-6 h-6 items-center justify-center">
               <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                 <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6h6v6M7 20h10a2 2 0 002-2V7l-5-5H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
               </svg>
             </span>
             รายงาน
          </a>
          <!-- Sidebar (ต่อจากลิงก์รายงาน) -->
            <a href="<?php echo base_url(); ?>/points.php"
              class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm
              <?php echo ($active ?? '')==='points' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'text-gray-700 hover:bg-gray-100'; ?>">
              <span class="inline-flex w-6 h-6 items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-2.21 0-4 1.79-4 4m8 0a4 4 0 01-4 4m0-12v2m0 12v2m8-8h-2M6 12H4"/>
                </svg>
              </span>
              สะสมแต้ม
            </a>
        </nav>

        <div class="px-4 py-4 border-t text-xs text-gray-500">
          เวลาระบบ: <?php echo date('d/m/Y H:i'); ?>
        </div>
      </div>
    </aside>

    <div id="overlay" class="fixed inset-0 bg-black/30 backdrop-blur-sm hidden md:hidden z-30"></div>

    <!-- Main content -->
    <main id="content" class="flex-1 min-w-0 min-h-0 md:px-6 px-4 py-6 transition-all duration-200 ease-out md:ml-72">
      
    <?php include __DIR__ . '/alerts.php'; ?>
