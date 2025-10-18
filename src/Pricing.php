<?php
class Pricing
{
    /**
     * ปัดชั่วโมงตามเกณฑ์: ถ้าเกินชั่วโมงมา >= $graceMin นาที ให้ปัดขึ้นเป็นชั่วโมงถัดไป
     * ตัวอย่าง: diff = 1ชม 05นาที, grace=5  => 2 ชม.
     *           diff = 1ชม 04นาที, grace=5  => 1 ชม.
     */
    public static function ceilHours(string $start, string $end, int $graceMin = 5): int
    {
        $s = strtotime($start);
        $e = strtotime($end);
        if ($s === false || $e === false || $e <= $s) return 1;

        $diff = $e - $s;
        $hours = intdiv($diff, 3600);
        $remain = $diff - ($hours * 3600);
        $remainMin = (int)ceil($remain / 60);

        // กรณีต่ำกว่า 1 ชม. แต่มีเวลาเหลือ ให้เริ่มที่ 1 ชม. เสมอ
        if ($hours === 0 && $remainMin > 0) {
            return ($remainMin >= $graceMin) ? 1 : 1; // อย่างน้อย 1 ชม.
        }

        if ($remainMin >= $graceMin && $remainMin > 0) {
            return $hours + 1;
        }
        return max(1, $hours);
    }

    /** ราคาเหมาจ่ายชั่วคราวตามชั่วโมงที่คิดได้ */
    public static function tempByHours(int $hours): int
    {
        return 130 + max(0, $hours - 1) * 60;
    }

    /**
     * ราคาเหมาค้างคืนตามเวลาเช็คอิน และห้องพิเศษ (6,7,8)
     * กฎเพิ่ม: เวลา 22:00–23:59 และ 00:00–11:59 ให้คิด “เรตหลัง 4 ทุ่ม”
     */
    public static function overnightByCheckin(string $checkin, int $roomId = 0): int
    {
        $t = (int)date('Hi', strtotime($checkin));
        $isSpecial = in_array($roomId, [6,7,8], true);

        // เรต "หลัง 4 ทุ่ม" (22:00-23:59) และ 00:00-11:59
        if ($t >= 2200 || $t < 1200) {
            return $isSpecial ? 400 : 300;
        }

        // ช่วงเวลาเดิม
        if ($t >= 1800) return $isSpecial ? 400 : 350;
        if ($t >= 1500) return $isSpecial ? 500 : 450;
        return $isSpecial ? 600 : 550; // ก่อน 15:00
    }
}
