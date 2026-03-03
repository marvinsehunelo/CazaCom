<?php
class SmsGateway {
    public static function sendSms($from_user_id, $to, $message) {
        // Simulate sending SMS (for now)
        // Later, integrate with real SMS provider if needed
        return ["status"=>"success","message"=>"SMS delivered to {$to}"];
    }
}
