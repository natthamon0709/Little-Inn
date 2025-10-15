<?php
class RoomRepository {
  public function __construct(private PDO $pdo) {}

  public function all(){
    return $this->pdo->query("SELECT * FROM rooms ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
  }
  public function find(int $id){
    $st = $this->pdo->prepare("SELECT * FROM rooms WHERE id=?");
    $st->execute([$id]);
    return $st->fetch(PDO::FETCH_ASSOC);
  }
  public function countVacant(){
    return (int)$this->pdo->query("SELECT COUNT(*) FROM rooms WHERE status='vacant'")->fetchColumn();
  }
  public function countOccupied(){
    return (int)$this->pdo->query("SELECT COUNT(*) FROM rooms WHERE status='occupied'")->fetchColumn();
  }
  public function setOccupied(int $roomId, int $bookingId): void {
    $st = $this->pdo->prepare("UPDATE rooms SET status='occupied', current_booking_id=? WHERE id=?");
    $st->execute([$bookingId, $roomId]);
  }
  public function setVacant(int $roomId): void {
    $st = $this->pdo->prepare("UPDATE rooms SET status='vacant', current_booking_id=NULL WHERE id=?");
    $st->execute([$roomId]);
  }
}
