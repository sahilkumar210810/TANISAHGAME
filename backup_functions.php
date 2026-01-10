<?php
/**
 * BACKUP FUNCTIONS FILE
 * Functions for backup and broadcast only
 * Restore functions have been removed
 */

// ============================================
// BACKUP SYSTEM
// ============================================

/**
 * Send backup to Telegram channel
 */
function sendBackupToTelegram() {
    $dataFile = __DIR__ . '/users_backup.json';
    
    if (!file_exists($dataFile)) {
        error_log("Backup file not found: {$dataFile}");
        return false;
    }
    
    // Get file stats
    $fileSize = filesize($dataFile);
    $fileSizeMB = round($fileSize / (1024 * 1024), 2);
    
    // Read and count data
    $dataContent = file_get_contents($dataFile);
    $data = json_decode($dataContent, true);
    
    if (!$data) {
        error_log("Invalid JSON in backup file");
        return false;
    }
    
    $totalUsers = isset($data['users']) ? count($data['users']) : 0;
    $totalCoins = 0;
    $activeUsers = 0;
    
    if (isset($data['users'])) {
        foreach ($data['users'] as $user) {
            $totalCoins += $user['coins'] ?? 0;
            // Check if active (last active within 7 days)
            if (isset($user['last_active'])) {
                $lastActive = strtotime($user['last_active']);
                if (time() - $lastActive < 7 * 24 * 60 * 60) {
                    $activeUsers++;
                }
            }
        }
    }
    
    // Create backup info message
    $backupInfo = "💾 *DATA BACKUP*\n\n" .
                 "📅 Date: " . date('d/m/Y H:i:s') . "\n" .
                 "👥 Total Users: {$totalUsers}\n" .
                 "👤 Active Users (7d): {$activeUsers}\n" .
                 "💰 Total Coins: " . number_format($totalCoins) . "\n" .
                 "📁 File Size: {$fileSizeMB} MB\n\n" .
                 "Save this file for records!";
    
    // First send info message
    sendMessage(BACKUP_CHANNEL_ID, $backupInfo, 'Markdown');
    
    // Send the actual file using Telegram API
    $botToken = BOT_TOKEN;
    $chatId = BACKUP_CHANNEL_ID;
    
    // Generate unique filename with timestamp
    $timestamp = date('Ymd_His');
    $filename = "tanu_bot_backup_{$timestamp}.json";
    
    // Prepare file data
    $fileData = [
        'chat_id' => $chatId,
        'caption' => "🔐 Backup File: {$filename}\n💾 Keep this file for records!",
        'parse_mode' => 'HTML'
    ];
    
    // Use cURL to send file
    $url = "https://api.telegram.org/bot{$botToken}/sendDocument";
    
    // Create CURLFile object if class exists
    if (class_exists('CURLFile')) {
        $fileData['document'] = new CURLFile($dataFile, 'application/json', $filename);
    } else {
        // Fallback for environments without CURLFile
        $fileData['document'] = "@{$dataFile};type=application/json;filename={$filename}";
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $result = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    if ($error) {
        error_log("Backup upload failed - cURL Error: " . $error);
        return false;
    }
    
    $resultArray = json_decode($result, true);
    
    if (!$resultArray || !isset($resultArray['ok']) || !$resultArray['ok']) {
        error_log("Backup upload failed - Telegram API Error: " . json_encode($resultArray));
        return false;
    }
    
    return true;
}

// ============================================
// BOTCAST SYSTEM
// ============================================

/**
 * Broadcast message to all users
 */
function broadcastToAllUsers($messageText, $adminId) {
    $data = getUsersData();
    $users = $data['users'];
    $sentCount = 0;
    $failedCount = 0;
    
    // Create broadcast message
    $message = "📢 *BROADCAST MESSAGE*\n\n" . 
               $messageText . 
               "\n\n────────────────────\n" .
               "💎 *Tanu Coins Gaming Bot*";
    
    foreach ($users as $userId => $user) {
        // Skip bots, admin, and users who haven't started
        if ((isset($user['is_bot']) && $user['is_bot']) || 
            $userId == $adminId ||
            (isset($user['has_started']) && !$user['has_started'])) {
            continue;
        }
        
        try {
            $result = sendMessage($userId, $message, 'Markdown');
            
            if ($result && isset($result['ok']) && $result['ok']) {
                $sentCount++;
                
                // Update user's last active time
                if (isset($data['users'][$userId])) {
                    $data['users'][$userId]['last_active'] = date('Y-m-d H:i:s');
                }
            } else {
                $failedCount++;
                error_log("Broadcast failed for user {$userId}");
            }
            
            // Delay to avoid rate limiting (100ms between messages)
            usleep(100000);
            
        } catch (Exception $e) {
            $failedCount++;
            error_log("Broadcast exception for {$userId}: " . $e->getMessage());
        }
    }
    
    // Save updated last_active times
    saveUsersData($data);
    
    return [
        'sent' => $sentCount,
        'failed' => $failedCount,
        'total' => count($users)
    ];
}

/**
 * Handle botcast command
 */
function handleBotcastCommand($chatId, $text, $messageId) {
    // Check if user is admin
    global $GLOBALS;
    $userId = $GLOBALS['current_user_id'] ?? 0;
    
    if ($userId != ADMIN_ID) {
        sendReplyMessage($chatId, "❌ *ACCESS DENIED!*\n\nOnly bot admin can use this command.", $messageId, 'Markdown');
        return;
    }
    
    // Extract message from command
    // Format: /botcast Your message here
    $parts = explode(' ', $text, 2);
    
    if (count($parts) < 2) {
        sendReplyMessage($chatId, 
            "❌ *Invalid format!*\n\n" .
            "✅ *Usage:* `/botcast Your message here`\n\n" .
            "📝 *Example:*\n`/botcast New game coming soon!`", 
            $messageId, 'Markdown');
        return;
    }
    
    $messageToSend = $parts[1];
    
    // Limit message length
    if (strlen($messageToSend) > 1000) {
        sendReplyMessage($chatId, 
            "❌ *Message too long!*\n\n" .
            "Maximum 1000 characters allowed.", 
            $messageId, 'Markdown');
        return;
    }
    
    // Send confirmation
    $confirmMsg = "📢 *BROADCAST CONFIRMATION*\n\n" .
                  "💬 *Message:*\n" . $messageToSend . "\n\n" .
                  "⚠️ This will be sent to ALL users.\n" .
                  "Are you sure?";
    
    sendReplyMessage($chatId, $confirmMsg, $messageId, 'Markdown', [
        'inline_keyboard' => [
            [
                ['text' => '✅ Yes, Send to All', 'callback_data' => 'confirm_botcast_' . base64_encode($messageToSend)],
                ['text' => '❌ Cancel', 'callback_data' => 'cancel_botcast']
            ]
        ]
    ]);
}

/**
 * Handle manual backup command
 */
function handleBackupCommand($chatId, $messageId) {
    // Check if user is admin
    global $GLOBALS;
    $userId = $GLOBALS['current_user_id'] ?? 0;
    
    if ($userId != ADMIN_ID) {
        sendReplyMessage($chatId, "❌ *ACCESS DENIED!*\n\nOnly bot admin can use this command.", $messageId, 'Markdown');
        return;
    }
    
    // Send processing message
    $processingMsg = sendReplyMessage($chatId, "💾 *Creating backup...*\n\nPlease wait...", $messageId, 'Markdown');
    
    // Create backup
    $result = sendBackupToTelegram();
    
    if ($result) {
        // Get current data stats for report
        $data = getUsersData();
        $totalUsers = count($data['users']);
        $totalCoins = 0;
        
        foreach ($data['users'] as $user) {
            $totalCoins += $user['coins'] ?? 0;
        }
        
        $successMsg = "✅ *BACKUP CREATED!*\n\n" .
                     "📊 *Current Stats:*\n" .
                     "👥 Users: {$totalUsers}\n" .
                     "💰 Total Coins: " . number_format($totalCoins) . "\n\n" .
                     "📁 Backup file has been sent to backup channel.\n" .
                     "✅ Backup completed successfully!";
        
        // Edit the processing message
        apiRequest('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $processingMsg['result']['message_id'],
            'text' => $successMsg,
            'parse_mode' => 'Markdown'
        ]);
    } else {
        $errorMsg = "❌ *BACKUP FAILED!*\n\n" .
                   "Could not create backup.\n" .
                   "Please check:\n" .
                   "1. Bot has permission to send files\n" .
                   "2. Backup channel ID is correct\n" .
                   "3. Internet connection is stable\n" .
                   "4. Check error logs for details";
        
        apiRequest('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $processingMsg['result']['message_id'],
            'text' => $errorMsg,
            'parse_mode' => 'Markdown'
        ]);
    }
}
?>
