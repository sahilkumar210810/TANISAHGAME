<?php
// ============================================
// TANISAH GAMING BOT - MAIN FILE
// ============================================

// Set timezone if not already set
if (date_default_timezone_get() !== 'Asia/Kolkata') {
    date_default_timezone_set('Asia/Kolkata');
}

// Include all required files
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/messages.php';
require_once __DIR__ . '/buttons.php';
require_once __DIR__ . '/backup_functions.php'; // NEW FILE FOR BACKUP/RESTORE

// Initialize data storage
initDataStorage();

// Handle webhook setup or bot requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleWebhook();
} else {
    // Display information page
    showInfo();
}

// ============================================
// DATA STORAGE FUNCTIONS
// ============================================

/**
 * Initialize data storage - FIXED
 */
function initDataStorage() {
    // First check if users_backup.json exists
    $backupFile = __DIR__ . '/users_backup.json';
    
    if (!file_exists($backupFile)) {
        // Create initial data structure
        $data = [
            'users' => [],
            'system' => [
                'total_spins_today' => 0,
                'total_games_today' => 0,
                'last_reset' => date('Y-m-d')
            ]
        ];
        
        // Save to file with proper permissions
        file_put_contents($backupFile, json_encode($data, JSON_PRETTY_PRINT));
        chmod($backupFile, 0664);
        
        // Also create error.log if not exists
        $errorLog = __DIR__ . '/error.log';
        if (!file_exists($errorLog)) {
            file_put_contents($errorLog, "# Error Log - Created at " . date('Y-m-d H:i:s') . "\n");
            chmod($errorLog, 0664);
        }
    }
}

/**
 * Load data from Telegram group - FIXED
 */
function loadDataFromGroup() {
    $file = __DIR__ . '/users_backup.json';
    
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        
        // Check if JSON is valid
        if (json_last_error() === JSON_ERROR_NONE) {
            return $data;
        } else {
            // If JSON is invalid, create fresh data
            error_log('Invalid JSON in users_backup.json: ' . json_last_error_msg());
            return false;
        }
    }
    
    return false;
}

/**
 * Save data to Telegram group - FIXED
 */
function saveDataToGroup($data) {
    $file = __DIR__ . '/users_backup.json';
    
    // Ensure directory exists
    if (!file_exists(dirname($file))) {
        mkdir(dirname($file), 0755, true);
    }
    
    // Save with pretty print
    $result = file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    
    if ($result !== false) {
        chmod($file, 0664);
        return true;
    } else {
        error_log('Failed to save data to ' . $file);
        return false;
    }
}

/**
 * Get users data
 */
function getUsersData() {
    $data = loadDataFromGroup();
    
    // If data is false, initialize fresh data
    if (!$data) {
        $data = [
            'users' => [],
            'system' => [
                'total_spins_today' => 0,
                'total_games_today' => 0,
                'last_reset' => date('Y-m-d')
            ]
        ];
        saveDataToGroup($data);
    }
    
    return $data;
}

/**
 * Save users data
 */
function saveUsersData($data) {
    return saveDataToGroup($data);
}

/**
 * Get user data - AUTO CREATE IF NOT EXISTS (NEW FUNCTION)
 */
function getUser($userId, $userData = null, $isBot = false) {
    $data = getUsersData();
    
    if (!isset($data['users'][$userId])) {
        // Check if user is a bot
        if ($isBot) {
            return null; // Don't create accounts for bots
        }
        
        // AUTO CREATE USER WITH DEFAULT COINS
        $data['users'][$userId] = [
            'id' => $userId,
            'username' => $userData['username'] ?? '',
            'first_name' => $userData['first_name'] ?? '',
            'is_bot' => $isBot,
            'last_active' => date('Y-m-d H:i:s'),
            'coins' => AUTO_COINS, // Auto 500 coins
            'got_welcome_bonus' => false, // Bonus not claimed yet
            'daily_spins' => 0,
            'games_played' => [
                'spin_wheel' => 0
            ],
            'safe_zone' => [
                'active' => false,
                'type' => null,
                'activated_at' => null,
                'expires_at' => null,
                'coins_deposited' => 0
            ],
            'total_robberies' => 0,
            'total_stolen' => 0,
            'failed_robberies' => 0,
            'last_robbery' => 0,
            'last_spin' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'auto_created' => true, // Flag for auto-created users
            'has_started' => false  // User hasn't used /start yet
        ];
        
        saveUsersData($data);
    }
    
    return $data['users'][$userId];
}

/**
 * Get or create user data (for existing users who use commands)
 */
function getOrCreateUser($userId, $userData = null, $isBot = false) {
    $data = getUsersData();
    
    // Check if user is a bot
    if ($isBot) {
        return null;
    }
    
    if (!isset($data['users'][$userId])) {
        // Create user with default 500 coins
        $data['users'][$userId] = [
            'id' => $userId,
            'username' => $userData['username'] ?? '',
            'first_name' => $userData['first_name'] ?? '',
            'is_bot' => $isBot,
            'last_active' => date('Y-m-d H:i:s'),
            'coins' => DEFAULT_COINS, // Default Tanu Coins (500)
            'got_welcome_bonus' => false, // Bonus not claimed yet
            'daily_spins' => 0,
            'games_played' => [
                'spin_wheel' => 0
            ],
            'safe_zone' => [
                'active' => false,
                'type' => null,
                'activated_at' => null,
                'expires_at' => null,
                'coins_deposited' => 0
            ],
            'total_robberies' => 0,
            'total_stolen' => 0,
            'failed_robberies' => 0,
            'last_robbery' => 0,
            'last_spin' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'auto_created' => false, // Manually created via /start
            'has_started' => true   // User has used /start
        ];
        
        saveUsersData($data);
    } else {
        // Update user info if exists
        $data['users'][$userId]['has_started'] = true;
        $data['users'][$userId]['last_active'] = date('Y-m-d H:i:s');
        $data['users'][$userId]['is_bot'] = $isBot;
        if ($userData) {
            if (isset($userData['username'])) $data['users'][$userId]['username'] = $userData['username'];
            if (isset($userData['first_name'])) $data['users'][$userId]['first_name'] = $userData['first_name'];
        }
        saveUsersData($data);
    }
    
    return $data['users'][$userId];
}

// ============================================
// WEBHOOK HANDLING
// ============================================

/**
 * Handle incoming webhook requests
 */
function handleWebhook() {
    global $secretToken;
    
    // Verify secret token if provided
    $headers = getallheaders();
    if (isset($headers['X-Telegram-Bot-Api-Secret-Token']) && $headers['X-Telegram-Bot-Api-Secret-Token'] !== $secretToken) {
        http_response_code(401);
        error_log('Unauthorized webhook attempt');
        exit;
    }
    
    // Get the update from Telegram
    $update = json_decode(file_get_contents('php://input'), true);
    
    if (!$update) {
        error_log('Invalid update received');
        exit;
    }
    
    // Store update globally for reference
    $GLOBALS['update'] = $update;
    
    // Process the update
    processUpdate($update);
    
    // Send OK response
    http_response_code(200);
    echo 'OK';
}

/**
 * Process Telegram update
 */
function processUpdate($update) {
    if (isset($update['message'])) {
        handleMessage($update['message']);
    } elseif (isset($update['callback_query'])) {
        handleCallbackQuery($update['callback_query']);
    }
}

// ============================================
// MESSAGE HANDLING
// ============================================

/**
 * Handle incoming messages
 */
function handleMessage($message) {
    $chatId = $message['chat']['id'];
    $userId = $message['from']['id'];
    $isBot = $message['from']['is_bot'] ?? false;
    $text = $message['text'] ?? '';
    $chatType = $message['chat']['type'] ?? 'private';
    
    // Store message info globally for reply functionality
    $GLOBALS['current_message'] = $message;
    $GLOBALS['current_user_id'] = $userId;
    
    // Remove bot username from command if present
    $text = str_replace('@' . BOT_USERNAME, '', $text);
    $text = trim($text);
    
    // Handle /bal command (check balance) - REMOVED /coins
    if (strpos($text, '/bal') === 0) {
        // Check if replying to someone
        if (isset($message['reply_to_message'])) {
            // Get target user from reply
            $targetUserId = $message['reply_to_message']['from']['id'];
            $targetIsBot = $message['reply_to_message']['from']['is_bot'] ?? false;
            
            // Check if target is a bot
            if ($targetIsBot) {
                // REPLY to the message with bot detected message
                sendReplyMessage($chatId, getBotDetectedMessage(), $message['message_id'], 'Markdown');
                return;
            }
            
            // AUTO CREATE TARGET USER IF NOT EXISTS
            $targetUserData = [
                'username' => $message['reply_to_message']['from']['username'] ?? '',
                'first_name' => $message['reply_to_message']['from']['first_name'] ?? ''
            ];
            $targetUser = getUser($targetUserId, $targetUserData, $targetIsBot);
            
            if (!$targetUser) {
                sendReplyMessage($chatId, getBotDetectedMessage(), $message['message_id'], 'Markdown');
                return;
            }
            
            // REPLY with coins info
            if ($chatType === 'private') {
                // Private chat में normal balance दिखाएं
                sendCoinsInfo($chatId, $targetUserId, true, $message['message_id']);
            } else {
                // Group chat में special message दिखाएं
                $groupMessage = getGroupBalanceMessage($targetUser, $targetUserId);
                sendReplyMessage($chatId, $groupMessage, $message['message_id'], 'HTML');
            }
        } else {
            // Check own balance - AUTO CREATE IF NOT EXISTS
            $userData = [
                'username' => $message['from']['username'] ?? '',
                'first_name' => $message['from']['first_name'] ?? ''
            ];
            $user = getUser($userId, $userData, $isBot);
            
            // REPLY to user's message
            if ($chatType === 'private') {
                // Private chat में normal balance दिखाएं
                sendCoinsInfo($chatId, $userId, false, $message['message_id']);
            } else {
                // Group chat में special message दिखाएं
                $groupMessage = getGroupOwnBalanceMessage($user, $userId);
                sendReplyMessage($chatId, $groupMessage, $message['message_id'], 'HTML');
            }
        }
        return;
    }
    
    // Handle /rob command (REPLACED /steal)
    if (strpos($text, '/rob') === 0) {
        // Check if replying to someone with amount
        if (isset($message['reply_to_message']) && strpos($text, '/rob ') === 0) {
            // Handle rob with amount
            // Check if robbing user is a bot
            if ($isBot) {
                sendReplyMessage($chatId, "❌ *BOTS NOT ALLOWED!*\n\n🤖 Bots cannot rob!", $message['message_id'], 'Markdown');
                return;
            }
            
            // First check if robbing user exists, if not auto create
            $thiefData = [
                'username' => $message['from']['username'] ?? '',
                'first_name' => $message['from']['first_name'] ?? ''
            ];
            $thief = getUser($userId, $thiefData, $isBot);
            
            // Check if target is a bot
            $targetIsBot = $message['reply_to_message']['from']['is_bot'] ?? false;
            if ($targetIsBot) {
                sendReplyMessage($chatId, getBotDetectedMessage(), $message['message_id'], 'Markdown');
                return;
            }
            
            handleRobCommand($chatId, $userId, $text, $message['reply_to_message'], $message['message_id']);
            return;
        }
        
        // If just /rob without reply, show instructions
        // Check if user is a bot
        if ($isBot) {
            sendReplyMessage($chatId, "❌ *BOTS NOT ALLOWED!*\n\n🤖 Bots cannot rob!", $message['message_id'], 'Markdown');
            return;
        }
        
        // Check if user exists, if not auto create
        $userData = [
            'username' => $message['from']['username'] ?? '',
            'first_name' => $message['from']['first_name'] ?? ''
        ];
        $user = getUser($userId, $userData, $isBot);
        
        if ($chatType === 'private') {
            showRobInstructions($chatId, $message['message_id']);
        } else {
            // In group, show simple instructions
            sendReplyMessage($chatId, "🚨 *HOW TO ROB:*\n1. Reply to user's message\n2. Type: `/rob AMOUNT`\n\nExample: `/rob 1000`", $message['message_id'], 'Markdown');
        }
        return;
    }
    
    // For group chats, only respond to specific commands
    if (($chatType === 'group' || $chatType === 'supergroup')) {
        $allowedCommands = ['/start', '/help', '/rob', '/bal', '/games', '/spin', '/leaderboard', '/safe', '/stats'];
        $isCommand = strpos($text, '/') === 0;
        $isAllowed = false;
        
        foreach ($allowedCommands as $cmd) {
            if (strpos($text, $cmd) === 0) {
                $isAllowed = true;
                break;
            }
        }
        
        if ($isCommand && !$isAllowed) {
            // Ignore other commands in groups
            return;
        }
    }
    
    // Handle commands
    switch (true) {
        case $text === '/start':
        case strpos($text, '/start ') === 0:
            // Check if user is a bot
            if ($isBot) {
                sendReplyMessage($chatId, "❌ *BOTS NOT ALLOWED!*\n\n🤖 Bots cannot use this bot!", $message['message_id'], 'Markdown');
                return;
            }
            
            // Extract start parameter if any (e.g., /start group_welcome)
            $startParam = '';
            if (strpos($text, '/start ') === 0) {
                $parts = explode(' ', $text, 2);
                $startParam = $parts[1] ?? '';
            }
            handleStart($chatId, $userId, $chatType, $startParam, $message['message_id']);
            break;
            
        case $text === '/games':
            // ✅ FIXED: Now works in both private and group
            // Check if user is a bot
            if ($isBot) {
                sendReplyMessage($chatId, "❌ *BOTS NOT ALLOWED!*\n\n🤖 Bots cannot play games!", $message['message_id'], 'Markdown');
                return;
            }
            
            // Check if user exists, if not auto create
            $userData = [
                'username' => $message['from']['username'] ?? '',
                'first_name' => $message['from']['first_name'] ?? ''
            ];
            $user = getUser($userId, $userData, $isBot);
            showGamesMenu($chatId, $userId, $chatType, $message['message_id']);
            break;
            
        case $text === '/spin':
            // ✅ FIXED: Now works in both private and group
            // Check if user is a bot
            if ($isBot) {
                sendReplyMessage($chatId, "❌ *BOTS NOT ALLOWED!*\n\n🤖 Bots cannot play games!", $message['message_id'], 'Markdown');
                return;
            }
            
            // Check if user exists, if not auto create
            $userData = [
                'username' => $message['from']['username'] ?? '',
                'first_name' => $message['from']['first_name'] ?? ''
            ];
            $user = getUser($userId, $userData, $isBot);
            spinWheelGame($chatId, $userId, $chatType, $message['message_id']);
            break;
            
        case $text === '/safe':
            // Check if user is a bot
            if ($isBot) {
                sendReplyMessage($chatId, "❌ *BOTS NOT ALLOWED!*\n\n🤖 Bots cannot use safe zone!", $message['message_id'], 'Markdown');
                return;
            }
            
            // Check if user exists, if not auto create
            $userData = [
                'username' => $message['from']['username'] ?? '',
                'first_name' => $message['from']['first_name'] ?? ''
            ];
            $user = getUser($userId, $userData, $isBot);
            
            if ($chatType === 'private') {
                showSafeZoneOptions($chatId, $userId, $message['message_id']);
            } else {
                // Group में private chat का button दिखाएं
                sendPrivateOnlyMessage($chatId, 'safe', $message['message_id']);
            }
            break;
            
        case $text === '/stats':
            // Check if user is a bot
            if ($isBot) {
                sendReplyMessage($chatId, "❌ *BOTS NOT ALLOWED!*\n\n🤖 Bots don't have stats!", $message['message_id'], 'Markdown');
                return;
            }
            
            // Check if user exists, if not auto create
            $userData = [
                'username' => $message['from']['username'] ?? '',
                'first_name' => $message['from']['first_name'] ?? ''
            ];
            $user = getUser($userId, $userData, $isBot);
            
            if ($chatType === 'private') {
                showMyStats($chatId, $userId, $message['message_id']);
            } else {
                // Group में private chat का button दिखाएं
                sendPrivateOnlyMessage($chatId, 'stats', $message['message_id']);
            }
            break;
            
        case $text === '/leaderboard':
            // ✅ FIXED: Now works in all chats with same text
            showLeaderboard($chatId, $chatType, $message['message_id']);
            break;
            
        case $text === '/help':
            showHelp($chatId, $chatType, $message['message_id']);
            break;
            
        // ADMIN COMMANDS - Only show to admin
        case $text === '/admin' && $userId == ADMIN_ID:
            showAdminPanel($chatId, $message['message_id']);
            break;
            
        case $text === '/addcoins' && $userId == ADMIN_ID:
            sendReplyMessage($chatId, "Usage: /addcoins USER_ID AMOUNT\nExample: /addcoins 123456 1000", $message['message_id'], 'Markdown');
            break;
        case strpos($text, '/addcoins ') === 0 && $userId == ADMIN_ID:
            handleAddCoins($chatId, $text, $message['message_id']);
            break;
            
        case $text === '/resetspins' && $userId == ADMIN_ID:
            showResetSpinsOptions($chatId, $message['message_id']);
            break;
        case strpos($text, '/resetspins ') === 0 && $userId == ADMIN_ID:
            handleResetSpins($chatId, $text, $message, $message['message_id']);
            break;
            
        case $text === '/userinfo' && $userId == ADMIN_ID:
            sendReplyMessage($chatId, "Usage: /userinfo USER_ID\nExample: /userinfo 123456", $message['message_id'], 'Markdown');
            break;
        case strpos($text, '/userinfo ') === 0 && $userId == ADMIN_ID:
            handleUserInfo($chatId, $text, $message['message_id']);
            break;
            
        // BACKUP/BOTCAST COMMANDS
        case $text === '/backup' && $userId == ADMIN_ID:
            handleBackupCommand($chatId, $message['message_id']);
            break;
            
        case strpos($text, '/botcast ') === 0 && $userId == ADMIN_ID:
            handleBotcastCommand($chatId, $text, $message['message_id']);
            break;
            
        // For non-admin users trying admin commands
        case in_array(explode(' ', $text)[0], ['/admin', '/addcoins', '/resetspins', '/userinfo', '/backup', '/botcast']) && $userId != ADMIN_ID:
            // NO RESPONSE - Bot won't reply to non-admins for admin commands
            break;
            
        default:
            // For private chats, show main menu for unknown commands
            if ($chatType === 'private' && strpos($text, '/') === 0) {
                sendReplyMessage($chatId, "❌ Unknown command. Type /help for available commands.", $message['message_id'], 'Markdown');
            }
            // For groups, ignore non-command messages completely
            break;
    }
}

// ============================================
// COMMAND HANDLERS
// ============================================

/**
 * Send private only message with button for group
 */
function sendPrivateOnlyMessage($chatId, $commandType, $replyToMsgId = null) {
    $message = "";
    $buttonText = "";
    
    switch ($commandType) {
        case 'safe':
            $message = "🛡️ *SAFE ZONE*\n\nThis command only works in private chat! ";
            $buttonText = "🛡️ Use Safe Zone";
            $command = "safe";
            break;
        case 'stats':
            $message = "📊 *STATISTICS*\n\nThis command only works in private chat! ";
            $buttonText = "📊 View Stats";
            $command = "stats";
            break;
        default:
            return;
    }
    
    $buttons = [
        'inline_keyboard' => [
            [
                ['text' => $buttonText, 'url' => "https://t.me/" . BOT_USERNAME . "?start={$command}"]
            ]
        ]
    ];
    
    if ($replyToMsgId) {
        sendReplyMessage($chatId, $message, $replyToMsgId, 'Markdown', $buttons);
    } else {
        sendMessage($chatId, $message, 'Markdown', $buttons);
    }
}

/**
 * Handle start with DIFFERENT MESSAGES FOR GROUP AND PRIVATE
 */
function handleStart($chatId, $userId, $chatType, $startParam = '', $replyToMsgId = null) {
    $data = getUsersData();
    $isNewUser = !isset($data['users'][$userId]);
    
    // Create or update user
    $userInfo = [
        'id' => $userId,
        'username' => $GLOBALS['current_message']['from']['username'] ?? '',
        'first_name' => $GLOBALS['current_message']['from']['first_name'] ?? '',
        'is_bot' => false
    ];
    getOrCreateUser($userId, $userInfo);
    
    if ($chatType === 'private') {
        // PRIVATE CHAT
        if ($startParam === 'safe' || $startParam === 'stats') {
            // User clicked button from group
            if ($startParam === 'safe') {
                showSafeZoneOptions($chatId, $userId, $replyToMsgId);
            } elseif ($startParam === 'stats') {
                showMyStats($chatId, $userId, $replyToMsgId);
            }
            return;
        }
        
        if ($startParam === 'group_welcome' || $startParam === 'help' || $startParam === 'start_bot') {
            // User clicked button in group or came from deep link
            $message = getWelcomeMessage();
            $buttons = getButtons('welcome_new', $chatType);
        } else {
            // Normal /start in private chat
            $message = getWelcomeMessage();
            $buttons = getButtons('welcome_new', $chatType);
        }
        
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $message, $replyToMsgId, 'Markdown', $buttons);
        } else {
            sendMessage($chatId, $message, 'Markdown', $buttons);
        }
        
    } else {
        // GROUP CHAT - DIFFERENT SIMPLE MESSAGE
        $message = getGroupWelcomeMessage();
        $buttons = getButtons('group_welcome', $chatType);
        
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $message, $replyToMsgId, 'Markdown', $buttons);
        } else {
            sendMessage($chatId, $message, 'Markdown', $buttons);
        }
    }
}

/**
 * Show help (different for group and private)
 */
function showHelp($chatId, $chatType, $replyToMsgId = null) {
    if ($chatType === 'private') {
        $message = getHelpMessage($chatType);
        $buttons = getButtons('help', $chatType);
    } else {
        $message = getHelpMessage($chatType);
        $buttons = getButtons('group_help', $chatType);
    }
    
    if ($replyToMsgId) {
        sendReplyMessage($chatId, $message, $replyToMsgId, 'Markdown', $buttons);
    } else {
        sendMessage($chatId, $message, 'Markdown', $buttons);
    }
}

/**
 * Show rob instructions
 */
function showRobInstructions($chatId, $replyToMsgId = null) {
    $message = getRobInstructionsMessage();
    $buttons = getButtons('steal', 'private'); // Still using steal buttons but message changed
    
    if ($replyToMsgId) {
        sendReplyMessage($chatId, $message, $replyToMsgId, 'Markdown', $buttons);
    } else {
        sendMessage($chatId, $message, 'Markdown', $buttons);
    }
}

/**
 * Show games menu - UPDATED FOR GROUP
 */
function showGamesMenu($chatId, $userId, $chatType, $replyToMsgId = null) {
    $data = getUsersData();
    
    // Check if user is auto-created and hasn't started
    if (isset($data['users'][$userId]['auto_created']) && 
        $data['users'][$userId]['auto_created'] && 
        (!isset($data['users'][$userId]['has_started']) || !$data['users'][$userId]['has_started'])) {
        
        $message = "🎮 *GAMES MENU*\n\nYou need to start the bot first to play games!\n\nUse /start command to activate your account!";
        
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $message, $replyToMsgId, 'Markdown');
        } else {
            sendMessage($chatId, $message, 'Markdown');
        }
        return;
    }
    
    $message = getGamesMenuMessage();
    
    if ($chatType === 'private') {
        $buttons = getButtons('games', $chatType);
    } else {
        // Group में सिर्फ spin button, back button नहीं
        $buttons = [
            'inline_keyboard' => [
                [
                    ['text' => '🎡 Spin Wheel', 'callback_data' => 'game_spin']
                ]
            ]
        ];
    }
    
    if ($replyToMsgId) {
        sendReplyMessage($chatId, $message, $replyToMsgId, 'Markdown', $buttons);
    } else {
        sendMessage($chatId, $message, 'Markdown', $buttons);
    }
}

/**
 * Show coins information - UPDATED WITH REPLY FUNCTIONALITY
 */
function sendCoinsInfo($chatId, $userId, $isOtherUser = false, $replyToMsgId = null) {
    $data = getUsersData();
    
    // AUTO CREATE USER IF NOT EXISTS
    if (!isset($data['users'][$userId])) {
        // Get user info from current message if available
        if (isset($GLOBALS['current_message'])) {
            $userData = [
                'username' => $GLOBALS['current_message']['from']['username'] ?? '',
                'first_name' => $GLOBALS['current_message']['from']['first_name'] ?? '',
                'is_bot' => $GLOBALS['current_message']['from']['is_bot'] ?? false
            ];
        } else {
            $userData = ['is_bot' => false];
        }
        
        $user = getUser($userId, $userData, $userData['is_bot'] ?? false);
        $data = getUsersData(); // Reload
    }
    
    // Check if user exists and is not a bot
    if (!isset($data['users'][$userId]) || (isset($data['users'][$userId]['is_bot']) && $data['users'][$userId]['is_bot'])) {
        if ($replyToMsgId) {
            sendReplyMessage($chatId, getBotDetectedMessage(), $replyToMsgId, 'Markdown');
        } else {
            sendMessage($chatId, getBotDetectedMessage(), 'Markdown');
        }
        return;
    }
    
    $user = $data['users'][$userId];
    
    // If checking other user
    if ($isOtherUser) {
        // Use getCoinsMessage function which now returns HTML for mentions
        $message = getCoinsMessage($user, $userId, true);
        
        // OTHER USER के लिए HTML parse mode use करें MENTION के लिए
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $message, $replyToMsgId, 'HTML');
        } else {
            sendMessage($chatId, $message, 'HTML');
        }
        
    } else {
        // अपने coins check करने पर
        $message = getCoinsMessage($user, $userId, false);
        $buttons = getButtons('back_only', 'private');
        
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $message, $replyToMsgId, 'Markdown', $buttons);
        } else {
            sendMessage($chatId, $message, 'Markdown', $buttons);
        }
    }
}

/**
 * Show user stats
 */
function showMyStats($chatId, $userId, $replyToMsgId = null) {
    $data = getUsersData();
    
    // AUTO CREATE USER IF NOT EXISTS
    if (!isset($data['users'][$userId])) {
        // Get user info from update if available
        if (isset($GLOBALS['current_message'])) {
            $userData = [
                'username' => $GLOBALS['current_message']['from']['username'] ?? '',
                'first_name' => $GLOBALS['current_message']['from']['first_name'] ?? '',
                'is_bot' => $GLOBALS['current_message']['from']['is_bot'] ?? false
            ];
        } else {
            $userData = ['is_bot' => false];
        }
        
        $user = getUser($userId, $userData, $userData['is_bot'] ?? false);
        $data = getUsersData(); // Reload
    }
    
    // Check if user is a bot
    if (isset($data['users'][$userId]['is_bot']) && $data['users'][$userId]['is_bot']) {
        if ($replyToMsgId) {
            sendReplyMessage($chatId, "❌ *BOTS NOT ALLOWED!*\n\n🤖 Bots don't have stats!", $replyToMsgId, 'Markdown');
        } else {
            sendMessage($chatId, "❌ *BOTS NOT ALLOWED!*\n\n🤖 Bots don't have stats!", 'Markdown');
        }
        return;
    }
    
    $user = $data['users'][$userId];
    $message = getStatsMessage($user, $userId);
    $buttons = getButtons('back_only', 'private');
    
    if ($replyToMsgId) {
        sendReplyMessage($chatId, $message, $replyToMsgId, 'HTML', $buttons);
    } else {
        sendMessage($chatId, $message, 'HTML', $buttons);
    }
}

/**
 * Show safe zone options
 */
function showSafeZoneOptions($chatId, $userId, $replyToMsgId = null) {
    $data = getUsersData();
    
    // AUTO CREATE USER IF NOT EXISTS
    if (!isset($data['users'][$userId])) {
        // Get user info from update if available
        if (isset($GLOBALS['current_message'])) {
            $userData = [
                'username' => $GLOBALS['current_message']['from']['username'] ?? '',
                'first_name' => $GLOBALS['current_message']['from']['first_name'] ?? '',
                'is_bot' => $GLOBALS['current_message']['from']['is_bot'] ?? false
            ];
        } else {
            $userData = ['is_bot' => false];
        }
        
        $user = getUser($userId, $userData, $userData['is_bot'] ?? false);
        $data = getUsersData(); // Reload
    }
    
    // Check if user is a bot
    if (isset($data['users'][$userId]['is_bot']) && $data['users'][$userId]['is_bot']) {
        if ($replyToMsgId) {
            sendReplyMessage($chatId, "❌ *BOTS NOT ALLOWED!*\n\n🤖 Bots cannot use safe zone!", $replyToMsgId, 'Markdown');
        } else {
            sendMessage($chatId, "❌ *BOTS NOT ALLOWED!*\n\n🤖 Bots cannot use safe zone!", 'Markdown');
        }
        return;
    }
    
    $user = $data['users'][$userId];
    $message = getSafeZoneMessage($user);
    
    if ($user['safe_zone']['active']) {
        $buttons = getButtons('back_only', 'private');
    } else {
        $buttons = getButtons('safe_zone', 'private');
    }
    
    if ($replyToMsgId) {
        sendReplyMessage($chatId, $message, $replyToMsgId, 'Markdown', $buttons);
    } else {
        sendMessage($chatId, $message, 'Markdown', $buttons);
    }
}

/**
 * Handle rob command with amount - CHANGED FROM STEAL TO ROB
 */
function handleRobCommand($chatId, $userId, $commandText, $replyToMessage, $replyToMsgId) {
    // Extract amount from command
    // Format: /rob 5000 (when replying to a user)
    $parts = explode(' ', $commandText);
    
    if (count($parts) !== 2) {
        sendReplyMessage($chatId, "❌ *Invalid format!*\n\n✅ *Correct format:*\n`/rob 5000`\n\n💡 *Steps:*\n1. Reply to user's message\n2. Type: `/rob 5000`\n3. Replace 5000 with your desired amount", $replyToMsgId, 'Markdown');
        return;
    }
    
    $amount = intval($parts[1]);
    
    // Validate amount
    if ($amount <= 0) {
        sendReplyMessage($chatId, "❌ *Invalid amount!*\n\n💰 Minimum 1 Tanu Coin can be robbed.", $replyToMsgId, 'Markdown');
        return;
    }
    
    if ($amount > 100000) {
        sendReplyMessage($chatId, "❌ *Amount too high!*\n\n💰 Maximum 100,000 Tanu Coins can be robbed at once.", $replyToMsgId, 'Markdown');
        return;
    }
    
    // Get target user from reply
    $targetUserId = $replyToMessage['from']['id'];
    
    // Call rob function
    robCoins($chatId, $userId, $targetUserId, $amount, $replyToMessage['from'], $replyToMsgId);
}

// ============================================
// ADMIN FUNCTIONS
// ============================================

/**
 * Show reset spins options
 */
function showResetSpinsOptions($chatId, $replyToMsgId = null) {
    $message = getResetSpinsMessage();
    
    if ($replyToMsgId) {
        sendReplyMessage($chatId, $message, $replyToMsgId, 'Markdown');
    } else {
        sendMessage($chatId, $message, 'Markdown');
    }
}

/**
 * Handle Reset Spins with multiple options
 */
function handleResetSpins($chatId, $text, $message, $replyToMsgId = null) {
    $parts = explode(' ', $text);
    
    if (count($parts) !== 2) {
        $errorMsg = "❌ *Invalid format!*\n\nUsage: `/resetspins USER_ID` or `/resetspins @username` or `/resetspins allusers`";
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $errorMsg, $replyToMsgId, 'Markdown');
        } else {
            sendMessage($chatId, $errorMsg, 'Markdown');
        }
        return;
    }
    
    $target = $parts[1];
    $data = getUsersData();
    $resetCount = 0;
    $notifiedUsers = [];
    
    // Case 1: Reset all users
    if (strtolower($target) === 'allusers') {
        foreach ($data['users'] as $userId => &$user) {
            // Skip bots
            if (isset($user['is_bot']) && $user['is_bot']) {
                continue;
            }
            $user['daily_spins'] = 0;
            $resetCount++;
            $notifiedUsers[] = $userId;
        }
        
        // Reset system stats
        $data['system']['total_spins_today'] = 0;
        
        saveUsersData($data);
        
        // Notify all users
        foreach ($notifiedUsers as $userId) {
            if ($userId != ADMIN_ID) { // Don't notify admin
                sendMessage($userId, "🔄 *DAILY SPINS RESET!*\n\nYour daily spins have been reset by admin!\n🎮 You now have 0/3 spins used.\n\nSpin again: /spin", 'Markdown');
            }
        }
        
        $successMsg = "✅ *ALL USERS SPINS RESET!*\n\n" .
                     "🔄 Total users reset: {$resetCount}\n" .
                     "📢 All users have been notified.";
        
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $successMsg, $replyToMsgId, 'Markdown');
        } else {
            sendMessage($chatId, $successMsg, 'Markdown');
        }
        return;
    }
    
    // Case 2: Reset by reply
    if ($target === '' && isset($message['reply_to_message'])) {
        $targetUserId = $message['reply_to_message']['from']['id'];
        $targetIsBot = $message['reply_to_message']['from']['is_bot'] ?? false;
        
        // Check if target is a bot
        if ($targetIsBot) {
            $errorMsg = "❌ Cannot reset spins for a bot!";
            if ($replyToMsgId) {
                sendReplyMessage($chatId, $errorMsg, $replyToMsgId, 'Markdown');
            } else {
                sendMessage($chatId, $errorMsg, 'Markdown');
            }
            return;
        }
        
        // AUTO CREATE USER IF NOT EXISTS
        if (!isset($data['users'][$targetUserId])) {
            $targetUserData = [
                'username' => $message['reply_to_message']['from']['username'] ?? '',
                'first_name' => $message['reply_to_message']['from']['first_name'] ?? '',
                'is_bot' => $targetIsBot
            ];
            $user = getUser($targetUserId, $targetUserData, $targetIsBot);
            $data = getUsersData(); // Reload
        }
        
        $data['users'][$targetUserId]['daily_spins'] = 0;
        
        // Get username with mention
        $username = $data['users'][$targetUserId]['username'] ? '@' . $data['users'][$targetUserId]['username'] : 
                   "<a href=\"tg://user?id={$targetUserId}\">" . htmlspecialchars($data['users'][$targetUserId]['first_name'] ?? "User") . "</a>";
        
        saveUsersData($data);
        
        // Notify the user
        sendMessage($targetUserId, "🔄 *DAILY SPINS RESET!*\n\nYour daily spins have been reset by admin!\n🎮 You now have 0/3 spins used.\n\nSpin again: /spin", 'Markdown');
        
        $successMsg = "✅ *SPINS RESET!*\n\n" .
                     "👤 User: {$username}\n" .
                     "🆔 ID: `{$targetUserId}`\n" .
                     "🎮 Spins reset to: 0/3\n" .
                     "📢 User has been notified.";
        
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $successMsg, $replyToMsgId, 'HTML');
        } else {
            sendMessage($chatId, $successMsg, 'HTML');
        }
        return;
    }
    
    // Case 3: Reset by @username
    if (strpos($target, '@') === 0) {
        $username = str_replace('@', '', $target);
        $foundUserId = null;
        
        // Find user by username
        foreach ($data['users'] as $userId => $user) {
            if (strtolower($user['username'] ?? '') === strtolower($username)) {
                // Skip if user is a bot
                if (isset($user['is_bot']) && $user['is_bot']) {
                    $errorMsg = "❌ Cannot reset spins for a bot!";
                    if ($replyToMsgId) {
                        sendReplyMessage($chatId, $errorMsg, $replyToMsgId, 'Markdown');
                    } else {
                        sendMessage($chatId, $errorMsg, 'Markdown');
                    }
                    return;
                }
                $foundUserId = $userId;
                break;
            }
        }
        
        if (!$foundUserId) {
            $errorMsg = "❌ User @{$username} not found!";
            if ($replyToMsgId) {
                sendReplyMessage($chatId, $errorMsg, $replyToMsgId, 'Markdown');
            } else {
                sendMessage($chatId, $errorMsg, 'Markdown');
            }
            return;
        }
        
        $data['users'][$foundUserId]['daily_spins'] = 0;
        
        saveUsersData($data);
        
        // Notify the user
        sendMessage($foundUserId, "🔄 *DAILY SPINS RESET!*\n\nYour daily spins have been reset by admin!\n🎮 You now have 0/3 spins used.\n\nSpin again: /spin", 'Markdown');
        
        $successMsg = "✅ *SPINS RESET!*\n\n" .
                     "👤 User: @{$username}\n" .
                     "🆔 ID: `{$foundUserId}`\n" .
                     "🎮 Spins reset to: 0/3\n" .
                     "📢 User has been notified.";
        
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $successMsg, $replyToMsgId, 'Markdown');
        } else {
            sendMessage($chatId, $successMsg, 'Markdown');
        }
        return;
    }
    
    // Case 4: Reset by User ID
    if (is_numeric($target)) {
        $targetUserId = $target;
        
        // AUTO CREATE USER IF NOT EXISTS
        if (!isset($data['users'][$targetUserId])) {
            $userData = ['is_bot' => false];
            $user = getUser($targetUserId, $userData, false);
            $data = getUsersData(); // Reload
        }
        
        // Check if user is a bot
        if (isset($data['users'][$targetUserId]['is_bot']) && $data['users'][$targetUserId]['is_bot']) {
            $errorMsg = "❌ Cannot reset spins for a bot!";
            if ($replyToMsgId) {
                sendReplyMessage($chatId, $errorMsg, $replyToMsgId, 'Markdown');
            } else {
                sendMessage($chatId, $errorMsg, 'Markdown');
            }
            return;
        }
        
        $data['users'][$targetUserId]['daily_spins'] = 0;
        $username = $data['users'][$targetUserId]['username'] ? '@' . $data['users'][$targetUserId]['username'] : 
                   "<a href=\"tg://user?id={$targetUserId}\">" . htmlspecialchars($data['users'][$targetUserId]['first_name'] ?? "User") . "</a>";
        
        saveUsersData($data);
        
        // Notify the user
        sendMessage($targetUserId, "🔄 *DAILY SPINS RESET!*\n\nYour daily spells have been reset by admin!\n🎮 You now have 0/3 spins used.\n\nSpin again: /spin", 'Markdown');
        
        $successMsg = "✅ *SPINS RESET!*\n\n" .
                     "👤 User: {$username}\n" .
                     "🆔 ID: `{$targetUserId}`\n" .
                     "🎮 Spins reset to: 0/3\n" .
                     "📢 User has been notified.";
        
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $successMsg, $replyToMsgId, 'HTML');
        } else {
            sendMessage($chatId, $successMsg, 'HTML');
        }
        return;
    }
    
    $errorMsg = "❌ *Invalid target!*\n\nUse: USER_ID, @username, or 'allusers'";
    if ($replyToMsgId) {
        sendReplyMessage($chatId, $errorMsg, $replyToMsgId, 'Markdown');
    } else {
        sendMessage($chatId, $errorMsg, 'Markdown');
    }
}

/**
 * Admin Panel
 */
function showAdminPanel($chatId, $replyToMsgId = null) {
    $data = getUsersData();
    $message = getAdminPanelMessage($data);
    
    if ($replyToMsgId) {
        sendReplyMessage($chatId, $message, $replyToMsgId, 'Markdown');
    } else {
        sendMessage($chatId, $message, 'Markdown');
    }
}

/**
 * Handle Add Coins (Admin)
 */
function handleAddCoins($chatId, $text, $replyToMsgId = null) {
    $parts = explode(' ', $text);
    
    if (count($parts) !== 3) {
        $errorMsg = "❌ *Invalid format!*\n\nUsage: `/addcoins USER_ID AMOUNT`";
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $errorMsg, $replyToMsgId, 'Markdown');
        } else {
            sendMessage($chatId, $errorMsg, 'Markdown');
        }
        return;
    }
    
    $targetId = $parts[1];
    $amount = intval($parts[2]);
    
    if ($amount <= 0) {
        $errorMsg = "❌ Amount must be positive!";
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $errorMsg, $replyToMsgId, 'Markdown');
        } else {
            sendMessage($chatId, $errorMsg, 'Markdown');
        }
        return;
    }
    
    $data = getUsersData();
    
    // AUTO CREATE USER IF NOT EXISTS
    if (!isset($data['users'][$targetId])) {
        $userData = ['is_bot' => false];
        $user = getUser($targetId, $userData, false);
        $data = getUsersData(); // Reload
    }
    
    // Check if user is a bot
    if (isset($data['users'][$targetId]['is_bot']) && $data['users'][$targetId]['is_bot']) {
        $errorMsg = "❌ Cannot add coins to a bot!";
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $errorMsg, $replyToMsgId, 'Markdown');
        } else {
            sendMessage($chatId, $errorMsg, 'Markdown');
        }
        return;
    }
    
    // Add coins
    $data['users'][$targetId]['coins'] += $amount;
    
    saveUsersData($data);
    
    $successMsg = "✅ *Coins Added!*\n\n" .
                 "👤 User ID: `{$targetId}`\n" .
                 "💰 Amount: {$amount} Tanu Coins\n" .
                 "💎 New Balance: " . $data['users'][$targetId]['coins'] . " Tanu Coins";
    
    if ($replyToMsgId) {
        sendReplyMessage($chatId, $successMsg, $replyToMsgId, 'Markdown');
    } else {
        sendMessage($chatId, $successMsg, 'Markdown');
    }
    
    // Notify user
    sendMessage($targetId, "🎁 *ADMIN GIFT!*\n\nYou received {$amount} Tanu Coins from admin!\n💎 New balance: " . $data['users'][$targetId]['coins'] . " Tanu Coins", 'Markdown');
}

/**
 * Handle User Info (Admin)
 */
function handleUserInfo($chatId, $text, $replyToMsgId = null) {
    $parts = explode(' ', $text);
    
    if (count($parts) !== 2) {
        $errorMsg = "❌ *Invalid format!*\n\nUsage: `/userinfo USER_ID`";
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $errorMsg, $replyToMsgId, 'Markdown');
        } else {
            sendMessage($chatId, $errorMsg, 'Markdown');
        }
        return;
    }
    
    $targetId = $parts[1];
    $data = getUsersData();
    
    // AUTO CREATE USER IF NOT EXISTS
    if (!isset($data['users'][$targetId])) {
        $userData = ['is_bot' => false];
        $user = getUser($targetId, $userData, false);
        $data = getUsersData(); // Reload
    }
    
    $user = $data['users'][$targetId];
    
    $message = "📋 *USER INFO*\n\n";
    $message .= "🆔 User ID: `{$targetId}`\n";
    $message .= "👤 Username: @" . ($user['username'] ?: 'None') . "\n";
    $message .= "👤 First Name: " . ($user['first_name'] ?: 'None') . "\n";
    $message .= "🤖 Is Bot: " . (isset($user['is_bot']) && $user['is_bot'] ? '✅ Yes' : '❌ No') . "\n";
    $message .= "💰 Tanu Coins: " . $user['coins'] . "\n";
    $message .= "🎮 Daily Spins: " . ($user['daily_spins'] ?? 0) . "/3\n";
    $message .= "📅 Joined: " . date('d/m/Y', strtotime($user['created_at'])) . "\n";
    $message .= "⏰ Last Active: " . date('d/m/Y H:i', strtotime($user['last_active'])) . "\n\n";
    
    $message .= "🚨 *Robbery Stats:*\n";
    $message .= "• Successful: " . ($user['total_robberies'] ?? 0) . "\n";
    $message .= "• Failed: " . ($user['failed_robberies'] ?? 0) . "\n";
    $message .= "• Total Robbed: " . ($user['total_stolen'] ?? 0) . " Tanu Coins\n\n";
    
    $message .= "🎮 *Games Played:*\n";
    $message .= "• Spin Wheel: " . $user['games_played']['spin_wheel'] . "\n\n";
    
    $message .= "🛡️ *Safe Zone:* " . ($user['safe_zone']['active'] ? '✅ ACTIVE' : '❌ INACTIVE');
    if ($user['safe_zone']['active']) {
        $message .= "\n• Expires: " . date('d/m/Y H:i', strtotime($user['safe_zone']['expires_at']));
    }
    
    $message .= "\n\nℹ️ *Account Status:* ";
    if (isset($user['auto_created']) && $user['auto_created']) {
        $message .= "Auto-created";
    } else {
        $message .= "Manual start";
    }
    
    if ($replyToMsgId) {
        sendReplyMessage($chatId, $message, $replyToMsgId, 'Markdown');
    } else {
        sendMessage($chatId, $message, 'Markdown');
    }
}

// ============================================
// GAME FUNCTIONS
// ============================================

/**
 * Spin wheel game with DAILY LIMIT - UPDATED WITH USER MENTION
 */
function spinWheelGame($chatId, $userId, $chatType, $replyToMsgId = null) {
    $data = getUsersData();
    
    // AUTO CREATE USER IF NOT EXISTS
    if (!isset($data['users'][$userId])) {
        // Get user info from update if available
        if (isset($GLOBALS['current_message'])) {
            $userData = [
                'username' => $GLOBALS['current_message']['from']['username'] ?? '',
                'first_name' => $GLOBALS['current_message']['from']['first_name'] ?? '',
                'is_bot' => $GLOBALS['current_message']['from']['is_bot'] ?? false
            ];
        } else {
            $userData = ['is_bot' => false];
        }
        
        $user = getUser($userId, $userData, $userData['is_bot'] ?? false);
        $data = getUsersData(); // Reload
    }
    
    // Check if user is a bot
    if (isset($data['users'][$userId]['is_bot']) && $data['users'][$userId]['is_bot']) {
        $errorMsg = "❌ *BOTS NOT ALLOWED!*\n\n🤖 Bots cannot play games!";
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $errorMsg, $replyToMsgId, 'Markdown');
        } else {
            sendMessage($chatId, $errorMsg, 'Markdown');
        }
        return;
    }
    
    // Check if user is auto-created and hasn't started
    if (isset($data['users'][$userId]['auto_created']) && 
        $data['users'][$userId]['auto_created'] && 
        (!isset($data['users'][$userId]['has_started']) || !$data['users'][$userId]['has_started'])) {
        
        $errorMsg = "🎡 *SPIN WHEEL*\n\nYou need to start the bot first to play games!\n\nUse /start command to activate your account and\nclaim your 500 Tanu Coins welcome bonus!";
        
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $errorMsg, $replyToMsgId, 'Markdown');
        } else {
            sendMessage($chatId, $errorMsg, 'Markdown');
        }
        return;
    }
    
    // Check daily spin limit (3 spins per day)
    $dailySpins = $data['users'][$userId]['daily_spins'] ?? 0;
    if ($dailySpins >= 3) {
        $errorMsg = "⏳ *Daily spin limit reached!*\n\n🎮 Daily spins: 3/3\n📅 Try again tomorrow!";
        
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $errorMsg, $replyToMsgId, 'Markdown');
        } else {
            sendMessage($chatId, $errorMsg, 'Markdown');
        }
        return;
    }
    
    // Check game cooldown (10 seconds between spins)
    $lastSpin = $data['users'][$userId]['last_spin'] ?? 0;
    if (time() - $lastSpin < 10) { // 10 seconds cooldown
        $remaining = 10 - (time() - $lastSpin);
        $errorMsg = "⏳ Please wait {$remaining} seconds before next spin!";
        
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $errorMsg, $replyToMsgId, 'Markdown');
        } else {
            sendMessage($chatId, $errorMsg, 'Markdown');
        }
        return;
    }
    
    // Spin wheel prizes (from config)
    $prizes = [
        ['amount' => 100, 'chance' => 30, 'text' => ' 100 Tanu Coins', 'emoji' => '💰'],
        ['amount' => 200, 'chance' => 20, 'text' => ' 200 Tanu Coins', 'emoji' => '🎯'],
        ['amount' => 500, 'chance' => 12, 'text' => ' 500 Tanu Coins', 'emoji' => '🎁'],
        ['amount' => 750, 'chance' => 8, 'text' => ' 750 Tanu Coins', 'emoji' => '🏆'],
        ['amount' => 900, 'chance' => 4, 'text' => ' 900 Tanu Coins', 'emoji' => '🎊'],
        ['amount' => 2000, 'chance' => 1, 'text' => ' 2000 Tanu Coins', 'emoji' => '🎊'],
        ['amount' => 0, 'chance' => 25, 'text' => ' Better Luck Next Time', 'emoji' => '😢']
    ];
    
    // Random selection based on chance
    $random = rand(1, 100);
    $cumulative = 0;
    $wonAmount = 0;
    $prizeText = '';
    $prizeEmoji = '';
    
    foreach ($prizes as $prize) {
        $cumulative += $prize['chance'];
        if ($random <= $cumulative) {
            $wonAmount = $prize['amount'];
            $prizeText = $prize['text'];
            $prizeEmoji = $prize['emoji'];
            break;
        }
    }
    
    // Get user info for mention
    $user = $data['users'][$userId];
    $userMention = $user['username'] ? 
                   "@" . $user['username'] : 
                   "<a href=\"tg://user?id={$userId}\">" . htmlspecialchars($user['first_name'] ?? "User") . "</a>";
    
    // Show spinning animation message
    $spinningMsg = "🎡 <b>Spinning the wheel for {$userMention}...</b>";
    
    if ($replyToMsgId) {
        $spinMsg = sendReplyMessage($chatId, $spinningMsg, $replyToMsgId, 'HTML');
    } else {
        $spinMsg = sendMessage($chatId, $spinningMsg, 'HTML');
    }
    
    sleep(1);
    
    // Update user coins and spins
    $data['users'][$userId]['coins'] += $wonAmount;
    $data['users'][$userId]['daily_spins'] = $dailySpins + 1;
    $data['users'][$userId]['last_spin'] = time();
    $data['users'][$userId]['games_played']['spin_wheel']++;
    $data['system']['total_spins_today'] = ($data['system']['total_spins_today'] ?? 0) + 1;
    
    saveUsersData($data);
    
    // Send result with user mention
    $message = "🎡 <b>SPIN WHEEL RESULT</b>\n\n";
    if ($wonAmount > 0) {
        $message .= "<b>✅ {$userMention}</b>\n";
        $message .= "<b>YOU WON: {$wonAmount} Tanu Coins!</b>\n";
        $message .= "💎 <b>Total Tanu Coins:</b> " . $data['users'][$userId]['coins'] . "\n";
        $message .= "🎮 <b>Daily spins:</b> " . ($data['users'][$userId]['daily_spins']) . "/3";
    } else {
        $message .= "<b>😢 {$userMention}</b>\n";
        $message .= "<b> Better Luck Next Time!</b>\n";
        $message .= "💎 <b>Total Tanu Coins:</b> " . $data['users'][$userId]['coins'] . "\n";
        $message .= "🎮 <b>Daily spins:</b> " . ($data['users'][$userId]['daily_spins']) . "/3";
    }
    
    $message .= "\n\n🔄 <b>Spin again:</b> /spin";
    
    // Edit the original spinning message
    apiRequest('editMessageText', [
        'chat_id' => $chatId,
        'message_id' => $spinMsg['result']['message_id'],
        'text' => $message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ]);
}

/**
 * Rob coins with specified amount - CHANGED FROM STEAL TO ROB
 */
function robCoins($chatId, $userId, $targetId, $amount, $targetUserInfo = null, $replyToMsgId = null) {
    $data = getUsersData();
    
    // Check if robbing from self
    if ($userId == $targetId) {
        $errorMsg = "❌ You cannot rob from yourself!";
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $errorMsg, $replyToMsgId, 'Markdown');
        } else {
            sendMessage($chatId, $errorMsg, 'Markdown');
        }
        return;
    }
    
    // Check if target is a bot
    if ($targetUserInfo && isset($targetUserInfo['is_bot']) && $targetUserInfo['is_bot']) {
        $errorMsg = getBotDetectedMessage();
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $errorMsg, $replyToMsgId, 'Markdown');
        } else {
            sendMessage($chatId, $errorMsg, 'Markdown');
        }
        return;
    }
    
    // AUTO CREATE TARGET USER IF NOT EXISTS (TARGET can be auto-created)
    if (!isset($data['users'][$targetId])) {
        $targetUserData = [
            'username' => $targetUserInfo['username'] ?? '',
            'first_name' => $targetUserInfo['first_name'] ?? '',
            'is_bot' => $targetUserInfo['is_bot'] ?? false
        ];
        $targetUser = getUser($targetId, $targetUserData, $targetUserData['is_bot']);
        $data = getUsersData(); // Reload data
    }
    
    // Double-check if target is a bot
    if (isset($data['users'][$targetId]['is_bot']) && $data['users'][$targetId]['is_bot']) {
        $errorMsg = getBotDetectedMessage();
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $errorMsg, $replyToMsgId, 'Markdown');
        } else {
            sendMessage($chatId, $errorMsg, 'Markdown');
        }
        return;
    }
    
    // Check if target is in safe zone
    if ($data['users'][$targetId]['safe_zone']['active']) {
        $expires = date('d/m/Y H:i', strtotime($data['users'][$targetId]['safe_zone']['expires_at']));
        $errorMsg = "🛡️ *SAFE ZONE PROTECTED!*";
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $errorMsg, $replyToMsgId, 'Markdown');
        } else {
            sendMessage($chatId, $errorMsg, 'Markdown');
        }
        return;
    }
    
    // Check if target has enough coins
    if ($data['users'][$targetId]['coins'] < $amount) {
        $errorMsg = "😢 User doesn't have enough Tanu Coins!\n\n💰 Required: {$amount} Tanu Coins\n💎 User has: {$data['users'][$targetId]['coins']} Tanu Coins";
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $errorMsg, $replyToMsgId, 'Markdown');
        } else {
            sendMessage($chatId, $errorMsg, 'Markdown');
        }
        return;
    }
    
    // Check if user has enough space for coins
    $maxCoins = 10000000; // 10 million max
    if (($data['users'][$userId]['coins'] + $amount) > $maxCoins) {
        $errorMsg = "❌ You don't have enough space!\n\n💰 Maximum limit: 10,000,000 Tanu Coins";
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $errorMsg, $replyToMsgId, 'Markdown');
        } else {
            sendMessage($chatId, $errorMsg, 'Markdown');
        }
        return;
    }
    
    // Check if user has attempted robbery in last 10 seconds
    $lastRobberyTime = $data['users'][$userId]['last_robbery'] ?? 0;
    if (time() - $lastRobberyTime < 10) { // 10 seconds cooldown
        $remaining = 10 - (time() - $lastRobberyTime);
        $errorMsg = "⏳ *Please wait!*\n\nYou can rob again in {$remaining} seconds.";
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $errorMsg, $replyToMsgId, 'Markdown');
        } else {
            sendMessage($chatId, $errorMsg, 'Markdown');
        }
        return;
    }
    
    // Random success chance (75% success)
    $success = rand(1, 100) <= 75;
    
    // Get robber info
    $thiefUserId = $userId;
    $thiefFirstName = $data['users'][$userId]['first_name'] ?? "User";
    
    // Get victim info
    $victimUserId = $targetId;
    $victimFirstName = $data['users'][$targetId]['first_name'] ?? "User";
    
    if ($success) {
        // Successful robbery
        $data['users'][$targetId]['coins'] -= $amount;
        $data['users'][$userId]['coins'] += $amount;
        
        // Update stats
        $data['users'][$userId]['last_robbery'] = time();
        $data['users'][$userId]['total_robberies'] = ($data['users'][$userId]['total_robberies'] ?? 0) + 1;
        $data['users'][$userId]['total_stolen'] = ($data['users'][$userId]['total_stolen'] ?? 0) + $amount;
        $data['system']['total_games_today'] = ($data['system']['total_games_today'] ?? 0) + 1;
        
        saveUsersData($data);
        
        // Send success message to robber
        $message = getRobSuccessMessage($chatId, $victimUserId, $victimFirstName, $amount);
        
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $message, $replyToMsgId, 'HTML');
        } else {
            sendMessage($chatId, $message, 'HTML');
        }
        
        // Notify victim
        $victimMessage = getVictimAlertMessage($chatId, $thiefUserId, $thiefFirstName, $amount);
        sendMessage($targetId, $victimMessage, 'HTML');
        
    } else {
        // Failed robbery - lose 100 coins fine
        $fine = 100;
        $data['users'][$userId]['coins'] -= $fine;
        $data['users'][$userId]['coins'] = max(0, $data['users'][$userId]['coins']);
        $data['users'][$userId]['last_robbery'] = time();
        $data['users'][$userId]['failed_robberies'] = ($data['users'][$userId]['failed_robberies'] ?? 0) + 1;
        
        saveUsersData($data);
        
        // Send failed message to robber
        $message = getRobFailedMessage($chatId, $victimUserId, $victimFirstName, $fine);
        
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $message, $replyToMsgId, 'HTML');
        } else {
            sendMessage($chatId, $message, 'HTML');
        }
        
        // Notify victim about failed attempt
        $victimMessage = getFailedRobberyAlertMessage($chatId, $thiefUserId, $thiefFirstName, $amount);
        sendMessage($targetId, $victimMessage, 'HTML');
    }
}

/**
 * Activate safe zone - FIXED: Now uses current activation time with proper timezone
 */
function activateSafeZone($chatId, $userId, $days, $cost, $replyToMsgId = null) {
    $data = getUsersData();
    
    // Check if user has enough coins
    if ($data['users'][$userId]['coins'] < $cost) {
        $errorMsg = "❌ *Insufficient Tanu Coins!*\n\n💰 Required: *{$cost} Tanu Coins*\n💎 You have: *{$data['users'][$userId]['coins']} Tanu Coins*";
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $errorMsg, $replyToMsgId, 'Markdown');
        } else {
            sendMessage($chatId, $errorMsg, 'Markdown');
        }
        return;
    }
    
    // Check if already in safe zone
    if ($data['users'][$userId]['safe_zone']['active']) {
        $expires = date('d/m/Y H:i', strtotime($data['users'][$userId]['safe_zone']['expires_at']));
        $errorMsg = "❌ *Already Protected!*\n\nYou are already in safe zone!\n⏳ Expires: {$expires}";
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $errorMsg, $replyToMsgId, 'Markdown');
        } else {
            sendMessage($chatId, $errorMsg, 'Markdown');
        }
        return;
    }
    
    // Get current time for activation - USE CURRENT TIME NOT BOT START TIME
    $activationTime = time();
    $activatedAt = date('Y-m-d H:i:s', $activationTime);
    $expiresAt = date('Y-m-d H:i:s', $activationTime + ($days * 24 * 60 * 60));
    
    // DEBUG: Check current timezone and time
    $currentTimezone = date_default_timezone_get();
    $currentTime = date('Y-m-d H:i:s');
    $currentUnixTime = time();
    
    // Log for debugging
    error_log("User {$userId} activating safe zone:");
    error_log("Timezone: {$currentTimezone}");
    error_log("Current time: {$currentTime}");
    error_log("Unix timestamp: {$currentUnixTime}");
    error_log("Activated at: {$activatedAt}");
    error_log("Expires at: {$expiresAt}");
    error_log("Days: {$days}");
    
    // Activate safe zone with current time
    $data['users'][$userId]['coins'] -= $cost;
    $data['users'][$userId]['safe_zone']['active'] = true;
    $data['users'][$userId]['safe_zone']['type'] = $days . "d";
    $data['users'][$userId]['safe_zone']['coins_deposited'] = $cost;
    $data['users'][$userId]['safe_zone']['activated_at'] = $activatedAt;
    $data['users'][$userId]['safe_zone']['expires_at'] = $expiresAt;
    
    saveUsersData($data);
    
    $activatedDate = date('d/m/Y H:i', $activationTime);
    $expiresDate = date('d/m/Y H:i', $activationTime + ($days * 24 * 60 * 60));
    
    $message = "✅ *SAFE ZONE ACTIVATED!*\n\n";
    $message .= "🛡️ *Protection:* {$days} days\n";
    $message .= "💰 *Cost:* {$cost} Tanu Coins\n";
    $message .= "⏰ *Activated:* {$activatedDate}\n";
    $message .= "⏳ *Expires:* {$expiresDate}\n";
    $message .= "💎 *Remaining Tanu Coins:* {$data['users'][$userId]['coins']}\n\n";
    $message .= "🔒 *Now no one can rob from you!*";
        
    if ($replyToMsgId) {
        sendReplyMessage($chatId, $message, $replyToMsgId, 'Markdown');
    } else {
        sendMessage($chatId, $message, 'Markdown');
    }
}

// ============================================
// CALLBACK QUERY HANDLING
// ============================================

/**
 * Handle callback queries
 */
function handleCallbackQuery($callbackQuery) {
    $chatId = $callbackQuery['message']['chat']['id'];
    $userId = $callbackQuery['from']['id'];
    $isBot = $callbackQuery['from']['is_bot'] ?? false;
    $data = $callbackQuery['data'];
    $messageId = $callbackQuery['message']['message_id'];
    $chatType = $callbackQuery['message']['chat']['type'] ?? 'private';
    
    // Set global user ID
    $GLOBALS['current_user_id'] = $userId;
    
    // Check if user is a bot
    if ($isBot) {
        apiRequest('answerCallbackQuery', [
            'callback_query_id' => $callbackQuery['id'],
            'text' => '❌ Bots are not allowed!',
            'show_alert' => true
        ]);
        return;
    }
    
    // Handle button clicks
    switch ($data) {
        // Welcome buttons
        case 'welcome_bonus':
            handleWelcomeBonus($chatId, $userId, $messageId);
            break;
        case 'add_to_group':
            sendReplyMessage($chatId, "👥 *Add to Group*\n\nTo add me to your group:\n1. Go to your group\n2. Click 'Add Members'\n3. Search: @" . BOT_USERNAME . "\n4. Make me admin for full features!", $messageId, 'Markdown', getButtons('welcome_new', 'private'));
            break;
        case 'how_to_play':
            // HOW TO PLAY पर click करने पर SIMPLIFIED BUTTONS
            $message = getHowToPlayMessage();
            $buttons = getButtons('how_to_play', 'private');
            
            // EDIT मौजूदा message
            apiRequest('editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $message,
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode($buttons)
            ]);
            break;

        // ✅ ALL COMMANDS CASE - SAHI JAGAH PAR
        case 'btn_all':
            // सभी कमांड्स की लिस्ट दिखाएं
            $message = "📋 *Available Commands*\n";
            $message .= "────────────────────\n";
            $message .= "Click buttons below:";            
            $buttons = getButtons('all_commands', 'private');
            
            // EDIT मौजूदा message
            apiRequest('editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $message,
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode($buttons)
            ]);
            break;

        // Main menu buttons - ALL IN EDIT MODE
        case 'btn_coins':
            // EDIT message for coins info
            $data = getUsersData();
            $user = $data['users'][$userId] ?? null;
            
            if ($user) {
                $message = getCoinsMessage($user, $userId, false);
                $buttons = getButtons('back_only', 'private');
                
                apiRequest('editMessageText', [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => $message,
                    'parse_mode' => 'Markdown',
                    'reply_markup' => json_encode($buttons)
                ]);
            }
            break;
            
        case 'btn_steal': // Still using steal button ID but functionality changed to rob
            $message = getRobInstructionsMessage();
            $buttons = getButtons('back_only', 'private');
            
            apiRequest('editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $message,
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode($buttons)
            ]);
            break;
            
        case 'btn_games':
            // Check if user is auto-created
            $userData = getUsersData();
            if (isset($userData['users'][$userId]['auto_created']) && 
                $userData['users'][$userId]['auto_created'] && 
                (!isset($userData['users'][$userId]['has_started']) || !$userData['users'][$userId]['has_started'])) {
                
                $message = "🎮 *GAMES MENU*\n\nYou need to start the bot first to play games!\n\nUse /start command to activate your account!";
                
                apiRequest('editMessageText', [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => $message,
                    'parse_mode' => 'Markdown'
                ]);
            } else {
                $message = getGamesMenuMessage();
                
                if ($chatType === 'private') {
                    $buttons = getButtons('games', 'private');
                } else {
                    // Group में सिर्फ spin button, back button नहीं
                    $buttons = [
                        'inline_keyboard' => [
                            [
                                ['text' => '🎡 Spin Wheel', 'callback_data' => 'game_spin']
                            ]
                        ]
                    ];
                }
                
                apiRequest('editMessageText', [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => $message,
                    'parse_mode' => 'Markdown',
                    'reply_markup' => json_encode($buttons)
                ]);
            }
            break;
            
        case 'btn_safe':
            $data = getUsersData();
            $user = $data['users'][$userId] ?? null;
            
            if ($user) {
                $message = getSafeZoneMessage($user);
                
                if ($user['safe_zone']['active']) {
                    $buttons = getButtons('back_only', 'private');
                } else {
                    $buttons = getButtons('safe_zone', 'private');
                }
                
                apiRequest('editMessageText', [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => $message,
                    'parse_mode' => 'Markdown',
                    'reply_markup' => json_encode($buttons)
                ]);
            }
            break;
            
        case 'btn_stats':
            $data = getUsersData();
            $user = $data['users'][$userId] ?? null;
            
            if ($user) {
                $message = getStatsMessage($user, $userId);
                $buttons = getButtons('back_only', 'private');
                
                apiRequest('editMessageText', [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode($buttons)
                ]);
            }
            break;
            
        case 'btn_leaderboard':
            $data = getUsersData();
            $users = $data['users'];
            $message = "🏆 <b>TOP 10 PLAYERS</b>\n\n";
            
            if (empty($users)) {
                $message .= "❌ No players yet!\n\nStart with /start!";
            } else {
                // Remove bots from leaderboard
                $realUsers = [];
                foreach ($users as $userId => $user) {
                    if (!isset($user['is_bot']) || !$user['is_bot']) {
                        $realUsers[$userId] = $user;
                    }
                }
                
                // Sort by coins
                usort($realUsers, function($a, $b) {
                    return $b['coins'] - $a['coins'];
                });
                
                $rank = 1;
                $medals = ["🥇", "🥈", "🥉", "4️⃣", "5️⃣", "6️⃣", "7️⃣", "8️⃣", "9️⃣", "🔟"];
                
                foreach (array_slice($realUsers, 0, 10) as $user) {
                    $medal = $medals[$rank-1] ?? "  ";
                    
                    // Use mention link for user display
                    $userDisplay = $user['username'] ? 
                                   "@" . $user['username'] : 
                                   "<a href=\"tg://user?id={$user['id']}\">" . htmlspecialchars($user['first_name'] ?? "User") . "</a>";
                    
                    $message .= "{$medal} <b>{$userDisplay}</b>  ". $user['coins']. "\n";
                    
                    if ($user['safe_zone']['active']) {
                        $message .= " 🛡️";
                    }
                    
                    $message .= "\n\n";
                    $rank++;
                }
                
                $message .= "📊 <b>Stats:</b>\n";
                $message .= "👥 Total players: " . count($realUsers);
            }
            
            $buttons = getButtons('back_only', 'private');
            
            apiRequest('editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $message,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode($buttons)
            ]);
            break;
            
        case 'btn_help':
            $message = getHelpMessage('private');
            $buttons = getButtons('help', 'private');
            
            apiRequest('editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $message,
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode($buttons)
            ]);
            break;
            
        case 'btn_back':
            // Back to welcome screen with NEW USER buttons
            $message = getWelcomeMessage();
            $buttons = getButtons('welcome_new', 'private');
            
            apiRequest('editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $message,
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode($buttons)
            ]);
            break;
        
        // Game buttons
        case 'game_spin':
            spinWheelGame($chatId, $userId, $chatType, $messageId);
            break;
        
        // Safe zone buttons - FIXED: Now uses current activation time
        case 'safe_1':
            activateSafeZone($chatId, $userId, 1, 200, $messageId);
            break;
        case 'safe_2':
            activateSafeZone($chatId, $userId, 2, 500, $messageId);
            break;
        case 'safe_3':
            activateSafeZone($chatId, $userId, 3, 1000, $messageId);
            break;
            
        // Botcast confirmation
        case (strpos($data, 'confirm_botcast_') === 0):
            $encodedMessage = str_replace('confirm_botcast_', '', $data);
            $messageToSend = base64_decode($encodedMessage);
            
            // Update message to show processing
            apiRequest('editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => "📢 *SENDING BROADCAST...*\n\n⏳ Please wait, this may take a while...",
                'parse_mode' => 'Markdown'
            ]);
            
            // Send broadcast
            $result = broadcastToAllUsers($messageToSend, $userId);
            
            // Prepare result message
            $resultMsg = "✅ *BROADCAST COMPLETED!*\n\n" .
                        "📤 Successfully sent: {$result['sent']} users\n" .
                        "❌ Failed to send: {$result['failed']} users\n" .
                        "👥 Total users in database: {$result['total']}\n\n" .
                        "💬 *Message sent:*\n" . $messageToSend;
            
            apiRequest('editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $resultMsg,
                'parse_mode' => 'Markdown'
            ]);
            break;
            
        case 'cancel_botcast':
            apiRequest('editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => "❌ *BROADCAST CANCELLED*",
                'parse_mode' => 'Markdown'
            ]);
            break;
    }
    
    // Answer callback query
    apiRequest('answerCallbackQuery', [
        'callback_query_id' => $callbackQuery['id'],
        'text' => '✅'
    ]);
}

/**
 * Handle welcome bonus button click
 */
function handleWelcomeBonus($chatId, $userId, $replyToMsgId = null) {
    $data = getUsersData();
    
    if (!isset($data['users'][$userId])) {
        // AUTO CREATE USER IF NOT EXISTS
        if (isset($GLOBALS['current_message'])) {
            $userData = [
                'username' => $GLOBALS['current_message']['from']['username'] ?? '',
                'first_name' => $GLOBALS['current_message']['from']['first_name'] ?? '',
                'is_bot' => $GLOBALS['current_message']['from']['is_bot'] ?? false
            ];
        } else {
            $userData = ['is_bot' => false];
        }
        
        $user = getUser($userId, $userData, $userData['is_bot'] ?? false);
        $data = getUsersData(); // Reload
    }
    
    // Check if already got welcome bonus
    if (isset($data['users'][$userId]['got_welcome_bonus']) && $data['users'][$userId]['got_welcome_bonus']) {
        // Already claimed - NO BUTTONS
        $message = getAlreadyClaimedMessage();
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $message, $replyToMsgId, 'Markdown');
        } else {
            sendMessage($chatId, $message, 'Markdown');
        }
        return;
    }
    
    // Give 500 Tanu Coins welcome bonus
    $data['users'][$userId]['coins'] += 500;
    $data['users'][$userId]['got_welcome_bonus'] = true;
    $data['users'][$userId]['total_welcome_bonus'] = 500;
    $data['users'][$userId]['has_started'] = true; // Mark as started
    
    saveUsersData($data);
    
    // Successfully claimed - NO BUTTONS
    $message = getWelcomeBonusClaimedMessage($data['users'][$userId]['coins']);
    if ($replyToMsgId) {
        sendReplyMessage($chatId, $message, $replyToMsgId, 'Markdown');
    } else {
        sendMessage($chatId, $message, 'Markdown');
    }
}

// ============================================
// UTILITY FUNCTIONS
// ============================================

/**
 * Leaderboard - UPDATED FOR GROUP SUPPORT
 */
function showLeaderboard($chatId, $chatType, $replyToMsgId = null) {
    $data = getUsersData();
    $users = $data['users'];
    
    if (empty($users)) {
        $errorMsg = "🏆 <b>TOP 10 PLAYERS</b>\n\n❌ No players yet!\n\nStart with /start!";
        if ($replyToMsgId) {
            sendReplyMessage($chatId, $errorMsg, $replyToMsgId, 'HTML');
        } else {
            sendMessage($chatId, $errorMsg, 'HTML');
        }
        return;
    }
    
    // Remove bots from leaderboard
    $realUsers = [];
    foreach ($users as $userId => $user) {
        if (!isset($user['is_bot']) || !$user['is_bot']) {
            $realUsers[$userId] = $user;
        }
    }
    
    // Sort by coins
    usort($realUsers, function($a, $b) {
        return $b['coins'] - $a['coins'];
    });
    
    $message = "🏆 <b>TOP 10 PLAYERS</b>\n\n";
    
    $rank = 1;
    $medals = ["🥇", "🥈", "🥉", "4️⃣", "5️⃣", "6️⃣", "7️⃣", "8️⃣", "9️⃣", "🔟"];
    
    foreach (array_slice($realUsers, 0, 10) as $user) {
        $medal = $medals[$rank-1] ?? "  ";
        
        // Use mention link for user display
        $userDisplay = $user['username'] ? 
                       "@" . $user['username'] : 
                       "<a href=\"tg://user?id={$user['id']}\">" . htmlspecialchars($user['first_name'] ?? "User") . "</a>";
        
        $message .= "{$medal} <b>{$userDisplay}</b> - " . $user['coins'] . " coins\n";
        
        if ($user['safe_zone']['active']) {
            $message .= " 🛡️";
        }
        
        $message .= "\n";
        $rank++;
    }
    
    $message .= "\n📊 <b>Stats:</b>\n";
    $message .= "👥 Total players: " . count($realUsers);
    
    // Group में buttons नहीं दिखाएं
    $buttons = null;
    
    // Private chat में ही back button दिखाएं
    if ($chatType === 'private') {
        $buttons = getButtons('back_only', 'private');
    }
    
    if ($replyToMsgId) {
        sendReplyMessage($chatId, $message, $replyToMsgId, 'HTML', $buttons);
    } else {
        sendMessage($chatId, $message, 'HTML', $buttons);
    }
}

// ============================================
// TELEGRAM API FUNCTIONS
// ============================================

/**
 * Send message via Telegram API
 */
function sendMessage($chatId, $text, $parseMode = 'Markdown', $replyMarkup = null) {
    if (empty(BOT_TOKEN)) {
        error_log('BOT_TOKEN not set');
        return false;
    }
    
    $parameters = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => $parseMode,
        'disable_web_page_preview' => true
    ];
    
    if ($replyMarkup) {
        $parameters['reply_markup'] = json_encode($replyMarkup);
    }
    
    return apiRequest('sendMessage', $parameters);
}

/**
 * Send reply message via Telegram API
 */
function sendReplyMessage($chatId, $text, $replyToMessageId, $parseMode = 'Markdown', $replyMarkup = null) {
    if (empty(BOT_TOKEN)) {
        error_log('BOT_TOKEN not set');
        return false;
    }
    
    $parameters = [
        'chat_id' => $chatId,
        'text' => $text,
        'reply_to_message_id' => $replyToMessageId,
        'parse_mode' => $parseMode,
        'disable_web_page_preview' => true
    ];
    
    if ($replyMarkup) {
        $parameters['reply_markup'] = json_encode($replyMarkup);
    }
    
    return apiRequest('sendMessage', $parameters);
}

/**
 * Make API request to Telegram
 */
function apiRequest($method, $parameters = []) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/{$method}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($parameters));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log('cURL Error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    return json_decode($response, true);
}

// ============================================
// INFO PAGE
// ============================================

/**
 * Display information page
 */
function showInfo() {
    $data = getUsersData();
    $hasBotToken = !empty(BOT_TOKEN);
    $totalPlayers = count($data['users']);
    
    // Count real users and bots
    $realUsers = 0;
    $bots = 0;
    foreach ($data['users'] as $user) {
        if (isset($user['is_bot']) && $user['is_bot']) {
            $bots++;
        } else {
            $realUsers++;
        }
    }
    
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>🎮 TANISAH GAMING BOT</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                margin: 0;
                padding: 20px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                min-height: 100vh;
            }
            .container { 
                max-width: 800px; 
                margin: 0 auto;
                background: rgba(255, 255, 255, 0.1);
                backdrop-filter: blur(10px);
                padding: 30px;
                border-radius: 15px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            }
            h1 { 
                color: #fff;
                text-align: center;
                margin-bottom: 30px;
                font-size: 2.5em;
            }
            .status { 
                padding: 15px; 
                margin: 15px 0; 
                border-radius: 10px;
                border-left: 5px solid;
            }
            .success { 
                background: rgba(46, 204, 113, 0.2); 
                border-color: #2ecc71;
            }
            .warning { 
                background: rgba(241, 196, 15, 0.2); 
                border-color: #f1c40f;
            }
            .error { 
                background: rgba(231, 76, 60, 0.2); 
                border-color: #e74c3c;
            }
            .info { 
                background: rgba(52, 152, 219, 0.2); 
                border-color: #3498db;
            }
            .feature-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin: 30px 0;
            }
            .feature {
                background: rgba(255, 255, 255, 0.1);
                padding: 20px;
                border-radius: 10px;
                text-align: center;
                transition: transform 0.3s;
            }
            .feature:hover {
                transform: translateY(-5px);
            }
            .feature-icon {
                font-size: 2em;
                margin-bottom: 10px;
            }
            .command-list {
                background: rgba(0, 0, 0, 0.2);
                padding: 20px;
                border-radius: 10px;
                margin: 20px 0;
            }
            code {
                background: rgba(0, 0, 0, 0.3);
                padding: 5px 10px;
                border-radius: 5px;
                font-family: monospace;
            }
            .steal-guide {
                background: rgba(231, 76, 60, 0.2);
                padding: 20px;
                border-radius: 10px;
                margin: 20px 0;
                border-left: 5px solid #e74c3c;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🎮 TANISAH GAMING BOT</h1>
            
            <div class="status ' . ($hasBotToken ? 'success' : 'error') . '">
                <strong>🤖 BOT STATUS:</strong> ' . ($hasBotToken ? '✅ CONNECTED' : '❌ NOT CONNECTED') . '
            </div>
            
            <div class="status info">
                <strong>📊 BOT INFO:</strong><br>
                Total Users: ' . $totalPlayers . '<br>
                Real Users: ' . $realUsers . '<br>
                Bots Detected: ' . $bots . '<br>
                Admin ID: ' . ADMIN_ID . '<br>
                Bot Username: @' . BOT_USERNAME . '<br>
                Data Group ID: ' . DATA_GROUP_ID . '<br>
                Daily Spins Today: ' . ($data['system']['total_spins_today'] ?? 0) . '<br>
                Daily Games Today: ' . ($data['system']['total_games_today'] ?? 0) . '
            </div>
            
            <div class="steal-guide">
                <h3>🚨 IMPORTANT UPDATES:</h3>
                <p><strong>• 🤖 BOT DETECTION:</strong> Bots are now detected and cannot be robbed from</p>
                <p><strong>• 👤 USER MENTIONS:</strong> All users are mentioned with clickable links</p>
                <p><strong>• 🔗 PROFILE LINKS:</strong> Click on user names to open their profiles</p>
                <p><strong>• 👥 GROUP LINKS:</strong> Click on group links to jump to robbery location</p>
                <p><strong>• 💬 REPLY SYSTEM:</strong> Bot now replies to user messages</p>
                <p><strong>• 🛡️ DIRECT ADMIN COMMANDS:</strong> Admin can use commands directly</p>
            </div>
            
            <div class="feature-grid">
                <div class="feature">
                    <div class="feature-icon">🤖</div>
                    <h3>Bot Detection</h3>
                    <p>Bots are detected and blocked</p>
                </div>
                <div class="feature">
                    <div class="feature-icon">👤</div>
                    <h3>User Mentions</h3>
                    <p>Clickable profile links</p>
                </div>
                <div class="feature">
                    <div class="feature-icon">🛡️</div>
                    <h3>Admin System</h3>
                    <p>Direct admin commands</p>
                </div>
                <div class="feature">
                    <div class="feature-icon">📢</div>
                    <h3>Broadcast</h3>
                    <p>Send messages to all users</p>
                </div>
            </div>
            
            <div class="command-list">
                <h3>🎯 AVAILABLE COMMANDS:</h3>
                <p><code>/start</code> - Start bot & claim 500 bonus coins</p>
                <p><code>/bal</code> - Check Tanu Coins (removed /coins)</p>
                <p><code>/rob 1000</code> - Rob coins (reply to user, removed /steal)</p>
                <p><code>/games</code> - Play games (need /start)</p>
                <p><code>/spin</code> - Spin wheel (3 daily, need /start)</p>
                <p><code>/safe</code> - Safe zone (need /start)</p>
                <p><code>/stats</code> - Your statistics</p>
                <p><code>/leaderboard</code> - Top players</p>
                <p><code>/help</code> - Help guide</p>
                <h4>🔧 ADMIN COMMANDS:</h4>
                <p><code>/addcoins USER_ID AMOUNT</code> - Add coins to user</p>
                <p><code>/resetspins OPTION</code> - Reset spins (allusers/@username/ID)</p>
                <p><code>/userinfo USER_ID</code> - Get user information</p>
                <p><code>/backup</code> - Create data backup</p>
                <p><code>/botcast message</code> - Broadcast to all users</p>
                <p><code>/admin</code> - Admin panel</p>
            </div>
            
            <div class="status success">
                <strong>✅ NEW FEATURES:</strong><br>
                • 🤖 <b>Bot Detection:</b> Bots are automatically detected and blocked<br>
                • 👤 <b>Smart Mentions:</b> All users are mentioned with clickable profile links<br>
                • 🔗 <b>Group Links:</b> Clickable links to jump to robbery location<br>
                • 💬 <b>Reply System:</b> Bot replies to user commands<br>
                • 🛡️ <b>Direct Admin Commands:</b> Admin can use commands without /admin prefix<br>
                • 🚨 <b>/rob Command:</b> Use /rob instead of /steal<br>
                • 💰 <b>/bal Only:</b> Removed /coins command<br>
                • 📢 <b>Broadcast:</b> Send messages to all users<br>
                • 🔄 <b>Direct Commands:</b> Admin can use <code>/addcoins</code>, <code>/resetspins</code> directly
            </div>
        </div>
    </body>
    </html>';
}
?>
