<?php
class BookingRepository {
    private PDO $db;
    public function __construct(PDO $db){ $this->db = $db; }

    public function createTemp($roomId, $guest, $note, $checkinAt){
        $this->db->prepare("INSERT INTO bookings (room_id, type, guest_name, note, checkin_at) VALUES (?, 'temp', ?, ?, ?)")
            ->execute([$roomId, $guest, $note, $checkinAt]);
        return $this->db->lastInsertId();
    }

    public function createOvernight($roomId, $guest, $note, $checkinAt, $price){
        $this->db->prepare("INSERT INTO bookings (room_id, type, guest_name, note, checkin_at, price) VALUES (?, 'overnight', ?, ?, ?, ?)")
            ->execute([$roomId, $guest, $note, $checkinAt, $price]);
        return $this->db->lastInsertId();
    }

    public function find($id){
        $stmt=$this->db->prepare("SELECT * FROM bookings WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function checkout($id, $checkoutAt, $price){
        $this->db->prepare("UPDATE bookings SET checkout_at=?, price=? WHERE id=?")->execute([$checkoutAt, $price, $id]);
    }

    public function revenueOnDate($date){
        $stmt=$this->db->prepare("SELECT COALESCE(SUM(price),0) AS rev FROM bookings WHERE DATE(checkout_at)=?");
        $stmt->execute([$date]);
        return $stmt->fetch()['rev'];
    }

    public function reportBetween(string $from, string $to): array {
        $stmt = $this->db->prepare("
            SELECT b.*, r.name AS room_name
              FROM bookings b
              JOIN rooms r ON r.id = b.room_id
             WHERE b.checkout_at IS NOT NULL
               AND DATE(b.checkout_at) BETWEEN ? AND ?
             ORDER BY b.checkout_at DESC
        ");
        $stmt->execute([$from, $to]);
        return $stmt->fetchAll();
    }
}
