CREATE TABLE `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL,
  `type` enum('temp','overnight') NOT NULL,  -- ประเภท: ชั่วคราว / ค้างคืน
  `phone` varchar(20) DEFAULT NULL,          -- เบอร์โทร (ใช้ทำโปรโมชั่นพักครบ 10 ครั้งฟรี 1 ชม.)
  `note` text DEFAULT NULL,                  -- หมายเหตุ
  `checkin_at` datetime NOT NULL,            -- เวลาเข้า
  `checkout_at` datetime DEFAULT NULL,       -- เวลาออก
  `price` decimal(10,2) DEFAULT 0.00,        -- ยอดเงินที่จ่าย
  `payment_method` enum('cash','transfer','card','other') DEFAULT NULL, -- วิธีชำระเงิน
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,               -- ชื่อห้อง เช่น 101, 102
  `status` enum('vacant','occupied') DEFAULT 'vacant', -- สถานะ: ว่าง / ไม่ว่าง
  `current_booking_id` int(11) DEFAULT NULL, -- ID ของการจองปัจจุบัน (ถ้ามีคนพักอยู่)
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;