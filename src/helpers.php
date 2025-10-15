<?php
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function now(){ return date('Y-m-d H:i:s'); }
function today(){ return date('Y-m-d'); }
function format_baht($n){ return number_format((float)$n, 0); }

/**
 * ===== Core URL helpers (รองรับ Dev Tunnels / Proxy) =====
 */

/** scheme ที่ถูกต้อง: http หรือ https (รองรับ X-Forwarded-Proto) */
function url_scheme(): string {
  if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    // eg. "https" จาก Dev Tunnels / ngrok / Cloudflare Tunnel
    return $_SERVER['HTTP_X_FORWARDED_PROTO'];
  }
  if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return 'https';
  if (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) return 'https';
  return 'http';
}

/** host(+port) ที่ถูกต้อง (รองรับ X-Forwarded-Host) */
function url_host(): string {
  if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
    // บาง proxy จะส่งมาเป็น host:port อยู่แล้ว
    return $_SERVER['HTTP_X_FORWARDED_HOST'];
  }
  return $_SERVER['HTTP_HOST'] ?? 'localhost';
}

/** origin เต็ม ๆ เช่น https://xxxx-80.asse.devtunnels.ms */
function site_origin(): string {
  return url_scheme() . '://' . url_host();
}

/** base path (โฟลเดอร์ public) เช่น "" หรือ "/Little-Inn/public" (ไม่มี slash ท้าย) */
function base_path(): string {
  $script = $_SERVER['SCRIPT_NAME'] ?? '/';
  $dir = str_replace('\\','/', dirname($script));
  // ปรับ "/" ให้เป็นค่าว่าง เพื่อไม่ให้กลายเป็น "https://host//file"
  if ($dir === '/' || $dir === '\\') return '';
  return rtrim($dir, '/');
}

/**
 * base_url() => URL เต็มของฐานโปรเจกต์ (origin + base_path)
 * ตัวอย่าง:
 *   - บน localhost root:      https://localhost
 *   - บน subdir:              https://localhost/Little-Inn/public
 *   - บน Dev Tunnels:         https://xxxx-80.asse.devtunnels.ms[/subdir]
 */
function base_url(): string {
  $origin = site_origin();
  $base   = base_path();
  return $base ? ($origin . $base) : $origin;
}

/** สร้างลิงก์ absolute จาก path สัมพัทธ์ */
function url_to(string $path): string {
  $base = rtrim(base_url(), '/');
  $path = '/' . ltrim($path, '/');
  return $base . $path;
}

/** (ออปชัน) current URL (มี query) – เผื่อดีบัก */
function current_url(): string {
  $uri = $_SERVER['REQUEST_URI'] ?? '/';
  return site_origin() . $uri;
}


function normalize_phone(string $s): string {
  // เก็บเฉพาะตัวเลข
  $digits = preg_replace('/\D+/', '', $s);
  // รองรับ 10 หลักไทยขึ้นต้น 0
  if (strlen($digits) === 10 && $digits[0] === '0') return $digits;
  // กรณีได้ 9–11 หลัก ก็เก็บไว้ตามจริง (กันกรณีพิเศษ)
  return $digits;
}

function format_phone(?string $s): string {
  $d = preg_replace('/\D+/', '', (string)$s);
  if (strlen($d) === 10) {
    return substr($d,0,3).'-'.substr($d,3,3).'-'.substr($d,6);
  }
  return $s ?? '';
}