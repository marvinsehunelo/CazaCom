public function getHistory($user_id) {
    // 1️⃣ Get the user's phone number
    $stmt = $this->db->prepare("SELECT phone_number FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return ["status" => "error", "message" => "User not found"];
    }

    // 2️⃣ Fetch messages where user is sender or receiver
    // Normalize sender and target numbers in SQL as well
    $stmt = $this->db->prepare("
        SELECT 
            id,
            sender_number,
            target_number,
            message,
            cost,
            created_at,
            CASE
                WHEN REPLACE(REPLACE(sender_number,'+',''),' ','') = :phone THEN 'sent'
                ELSE 'received'
            END AS direction
        FROM sms
        WHERE REPLACE(REPLACE(sender_number,'+',''),' ','') = :phone
           OR REPLACE(REPLACE(target_number,'+',''),' ','') = :phone
        ORDER BY created_at DESC
    ");
    $stmt->execute(['phone' => $phone]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return ["status" => "success", "history" => $history];
}
