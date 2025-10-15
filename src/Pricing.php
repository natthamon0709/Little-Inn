<?php
class Pricing {
    /** ปัดชั่วโมงขึ้น */
    public static function ceilHours($start, $end) {
        $s = strtotime($start);
        $e = strtotime($end);
        return max(1, (int)ceil(($e - $s) / 3600));
    }

    /** ชั่วคราว: ชม.แรก 130 ชม.ถัดไป +60 */
    public static function tempByHours($hours) {
        return 130 + max(0, $hours - 1) * 60;
    }

    /**
     * ค้างคืน: คิดเรตตามเวลาเช็คอิน และชนิดห้อง
     * - ห้องธรรมดา: 12:00=550, 15:00=450, 18:00=350, 00:00–11:59=300
     * - ห้อง 6/7/8:  12:00=600, 15:00=500, 18:00=450, 00:00–11:59=400
     */
    // public static function ceilHours($start, $end) { /* ... */ }
    // public static function tempByHours($hours) { /* ... */ }

    // รองรับกรณีห้อง 6,7,8 เป็นเรตพิเศษ
    public static function overnightByCheckin($checkin, int $roomId = 0) {
        $t = (int)date('Hi', strtotime($checkin));
        $isSpecial = in_array($roomId, [6,7,8], true);

        if ($isSpecial) {
        // ตัวอย่างเรตพิเศษ (แก้ตามจริงได้)
        if ($t >= 1800) return 400;
        if ($t >= 1500) return 500;
        return 600;
        } else {
        if ($t >= 1800) return 350;
        if ($t >= 1500) return 450;
        return 550;
        }
    }
}

