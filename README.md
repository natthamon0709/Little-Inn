# Little-Inn
little_inn_php/
├─ config/
│  ├─ config.sample.php   # ก๊อปเป็น config.php แล้วใส่ค่าฐานข้อมูล
│  └─ db.php              # สร้าง PDO เชื่อม MySQL
├─ public/
│  ├─ index.php           # Dashboard + ห้องว่าง/ไม่ว่าง + ปุ่ม Check-in/Check-out
│  ├─ checkin.php         # รับฟอร์มเช็คอิน
│  ├─ checkout.php        # รับฟอร์มเช็คเอาท์ + คิดเงิน
│  └─ report.php          # รายงานช่วงวันที่ + ปุ่มพิมพ์
├─ src/
│  ├─ Pricing.php         # กติกาคิดราคา (ชั่วคราว/ค้างคืน)
│  ├─ RoomRepository.php  # จัดการห้อง
│  ├─ BookingRepository.php # จัดการการจอง
│  └─ helpers.php         # ยูทิลิตี้ (format, now ฯลฯ)
├─ templates/
│  ├─ header.php          # <head> + Tailwind + header
│  ├─ footer.php          # footer
│  └─ alerts.php          # แสดงข้อความแจ้งเตือน
├─ sql/
│  └─ schema.sql          # สร้างตาราง + seed ห้องเริ่มต้น
└─ README.md

กติกาคิดเงิน (ทำไว้ใน src/Pricing.php)
ชั่วคราว (รายชั่วโมง): ชั่วโมงแรก 130 บาท, ทุกชั่วโมงถัดไป +60 บาท/ชม. (ปัดขึ้นเป็นชั่วโมง)
ค้างคืน (ตามเวลาเช็คอิน):
หลัง 18:00 → 350
หลัง 15:00 → 450
ตั้งแต่ 12:00 ขึ้นไป → 550
ก่อนเที่ยง → 550

ขั้นตอนติดตั้งเร็วๆ
สร้างฐานข้อมูล MySQL (เช่น little_inn).
รันไฟล์ sql/schema.sql ในฐานข้อมูลนั้น (จะสร้างตาราง + ห้องตัวอย่าง 5 ห้อง).
คัดลอก config/config.sample.php เป็น config/config.php แล้วใส่ค่า db_host / db_name / db_user / db_pass.

วางโฟลเดอร์ทั้งชุดไว้ใต้เว็บรูท แล้วเปิด public/index.php.
การใช้งาน
หน้า Dashboard (index.php): ดูจำนวนห้องว่าง/ไม่ว่าง + รายได้วันนี้ (นับจากรายการที่เช็คเอาท์แล้ว)
ห้อง ว่าง → ฟอร์ม Check-in (เลือก ชั่วคราว หรือ ค้างคืน, ใส่เวลา/ชื่อ/โน้ต)
ห้อง ไม่ว่าง → ฟอร์ม Check-out (ใส่เวลาออก ระบบคำนวณราคาให้ตามกติกา)
หน้า รายงาน (report.php): เลือกช่วงวันที่, ดูยอดรวม, กด พิมพ์รายงาน ได้ทันที
ใช้ Tailwind CDN ตกแต่ง (ไม่ต้องติดตั้งเพิ่ม)
