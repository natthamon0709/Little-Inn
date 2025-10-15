CREATE TABLE rooms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50),
  status ENUM('vacant','occupied') DEFAULT 'vacant',
  current_booking_id INT NULL
);

CREATE TABLE bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room_id INT NOT NULL,
  type ENUM('temp','overnight'),
  guest_name VARCHAR(100),
  note VARCHAR(255),
  checkin_at DATETIME,
  checkout_at DATETIME NULL,
  price DECIMAL(10,2)
);

INSERT INTO rooms (name) VALUES ('101'),('102'),('103'),('104'),('201');
