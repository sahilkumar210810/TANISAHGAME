<?php
/**
 * MESSAGES FILE
 * All text messages for the bot
 */

/**
 * Get welcome message - SAME FOR ALL PRIVATE CHATS
 */
function getWelcomeMessage() {
    return "🎉 *Welcome to Tanisah Bot!*\n\n" .
           "This is a gaming bot where you can:\n" .
           "• Earn Tanu Coins through games\n" .
           "• Steal coins from other players\n" .
           "• Protect your coins in Safe Zone\n" .
           "• See top players ranking\n\n" .
           "Click the buttons below to get started:";
}

/**
 * Get GROUP welcome message (DIFFERENT)
 */
function getGroupWelcomeMessage() {
    return "*WELCOME TO TANISAH BOT*\n" .
           "This is a gaming bot:\n\n" .
           "🎮 *GAME COMMANDS:*\n" .
           "• /bal - Check Tanu Coins\n" .
           "• /rob - Rob Tanu Coins from others\n" .
           "• /stats - Your statistics\n" .
           "• /help - This help message\n\n" .
           "Get more info to start Bot:";
}

/**
 * Get welcome bonus claimed message (NO BUTTONS)
 */
function getWelcomeBonusClaimedMessage($coins) {
    return "🎁 *WELCOME BONUS CLAIMED!*\n\n" .
           "✅ You received *500 Tanu Coins*!\n" .
           "💰 You now have: *{$coins} Tanu Coins*\n\n";
}

/**
 * Get already claimed message (NO BUTTONS)
 */
function getAlreadyClaimedMessage() {
    return "🎁 *Already Claimed!*\n\n" .
           "You've already claimed your welcome bonus!";
}

/**
 * Get help message based on chat type
 */
function getHelpMessage($chatType) {
    if ($chatType === 'private') {
        return "🆘 *TANISAH GAMING BOT HELP*\n\n" .
               "🎮 *GAME COMMANDS:*\n" .
               " Use buttons or type commands\n\n" .
               "• /bal - Check Tanu Coins\n" .
               "• /rob - Rob Tanu Coins from others\n" .
               "• /games - Play games\n" .
               "• /spin - Spin wheel (3 daily)\n" .
               "• /safe - Safe zone protection\n" .
               "• /stats - Your statistics\n" .
               "• /leaderboard - Top players\n" .
               "• /help - This help message\n\n";
    } else {
        // Group message - SIMPLE
        return "🤖 *Tanisah Bot Help*\n\n" .
               "Available commands:\n" .
               "• /start - Start the bot\n" .
               "• /bal - Check your balance\n" .
               "• /rob [amount] - Rob coins (reply to user)\n" .
               "• /games - Play games\n" .
               "• /spin - Spin wheel (3 daily)\n" .
               "• /leaderboard - Top players\n" .
               "• /help - Show this message\n\n" .
               "For full features, use /start in private chat!";
    }
}

/**
 * Get rob instructions message - CHANGED FROM STEAL TO ROB
 */
function getRobInstructionsMessage() {
    return "🚨 *HOW TO ROB TANU COINS*\n\n" .
           "✅ *Single Method:*\n" .
           "1️⃣ **REPLY** to any message of the user you want to rob from\n" .
           "2️⃣ Type: /rob [amount]\n\n" .
           "📝 *Examples:*\n" .
           "• `/rob 1000` - To rob 1000 Tanu Coins\n\n" .
           "⚡ *Success Chance:* 75%\n" .
           "💸 *Fine if caught:* 100 Tanu Coins\n" .
           "⏳ *Cooldown:* 10 seconds\n\n" .
           "🛡️ *Safe Zone:* If user is in safe zone, robbery is not possible!";
}

/**
 * Get games menu message - UPDATED FOR GROUP
 */
function getGamesMenuMessage() {
    return "🎮 *GAMES MENU*\n\nYou can play these games:\n\n" .
           "1️⃣ *Spin Wheel* - Try your luck\n" .
           "   • Daily Limit: 3 spins\n" .
           "   • Cooldown: 10 seconds\n" .
           "   • Command: /spin\n\n ";
}

/**
 * Get coins info message - UPDATED WITH MENTION AND BOT CHECK
 */
function getCoinsMessage($user, $userId, $isOtherUser = false) {
    if ($isOtherUser) {
        // Check if user is a bot
        if (isset($user['is_bot']) && $user['is_bot']) {
            return "🤖 *This user is a bot!*\n";
        }
        
        // USER को MENTION करें - ALWAYS use user mention link
        $mention = "<a href=\"tg://user?id={$userId}\">" . 
                   ($user['username'] ? '@' . htmlspecialchars($user['username']) : 
                   (htmlspecialchars($user['first_name'] ?? "User"))) . 
                   "</a>";
        
        return "👤 <b>User:</b> {$mention}\n" .
               "💎 <b>Tanu Coins:</b> " . $user['coins'];
    } else {
        $message = "💰 *YOUR TANU COINS*\n\n" .
                   "💎 *Current Balance:* " . $user['coins'] . " Tanu Coins\n" .
                   "🎮 *Daily Spins:* " . ($user['daily_spins'] ?? 0) . "/3\n" .
                   "🚨 *Robbed Tanu Coins:* " . ($user['total_stolen'] ?? 0) . "\n\n";
        
        if ($user['safe_zone']['active']) {
            $expires = date('d/m/Y H:i', strtotime($user['safe_zone']['expires_at']));
            $activated = date('d/m/Y H:i', strtotime($user['safe_zone']['activated_at']));
            $message .= "🛡️ *Safe Zone:* ✅ ACTIVE\n" .
                       "   • Type: " . $user['safe_zone']['type'] . "\n" .
                       "   • Activated: {$activated}\n" .
                       "   • Expires: {$expires}";
        } else {
            $message .= "🛡️ *Safe Zone:* ❌ INACTIVE\n" .
                       "   • For protection: /safe\n";
        }
        
        return $message;
    }
}

/**
 * Get stats message
 */
function getStatsMessage($user, $userId) {
    // Safe check for all fields
    $totalRobberies = isset($user['total_robberies']) ? $user['total_robberies'] : 0;
    $totalStolen = isset($user['total_stolen']) ? $user['total_stolen'] : 0;
    $failedRobberies = isset($user['failed_robberies']) ? $user['failed_robberies'] : 0;
    $spinWheelPlays = isset($user['games_played']['spin_wheel']) ? $user['games_played']['spin_wheel'] : 0;
    
    // Use mention link for user display
    $userDisplay = $user['username'] ? 
                   '@' . $user['username'] : 
                   "<a href=\"tg://user?id={$userId}\">" . htmlspecialchars($user['first_name'] ?? "User") . "</a>";
    
    $message = "📊 <b>YOUR STATS</b>\n\n" .
               "👤 <b>User:</b> {$userDisplay}\n" .
               "💰 <b>Current Tanu Coins:</b> " . $user['coins'] . "\n" .
               "🎮 <b>Daily Spins:</b> " . ($user['daily_spins'] ?? 0) . "/3\n" .
               "🚨 <b>Successful Robberies:</b> " . $totalRobberies . "\n" .
               "💸 <b>Total Robbed:</b> " . $totalStolen . " Tanu Coins\n" .
               "❌ <b>Failed Robberies:</b> " . $failedRobberies . "\n\n" .
               "🎮 <b>Games Played:</b>\n" .
               "  • 🎡 Spin Wheel: " . $spinWheelPlays . "\n\n";
    
    // Safe Zone info
    if (isset($user['safe_zone']['active']) && $user['safe_zone']['active']) {
        $activated = date('d/m/Y H:i', strtotime($user['safe_zone']['activated_at']));
        $expires = date('d/m/Y H:i', strtotime($user['safe_zone']['expires_at']));
        $message .= "🛡️ <b>Safe Zone:</b> ✅ ACTIVE\n" .
                   "  • Type: " . $user['safe_zone']['type'] . "\n" .
                   "  • Activated: {$activated}\n" .
                   "  • Expires: {$expires}\n" .
                   "  • Deposited: " . $user['safe_zone']['coins_deposited'] . " Tanu Coins\n";
    } else {
        $message .= "🛡️ <b>Safe Zone:</b> ❌ INACTIVE\n";
    }
    
    // Join date
    $message .= "\n📅 <b>Joined:</b> " . date('d/m/Y', strtotime($user['created_at']));
    
    return $message;
}

/**
 * Get safe zone message
 */
function getSafeZoneMessage($user) {
    if ($user['safe_zone']['active']) {
        $activated = date('d/m/Y H:i', strtotime($user['safe_zone']['activated_at']));
        $expires = date('d/m/Y H:i', strtotime($user['safe_zone']['expires_at']));
        
        return "🛡️ *SAFE ZONE ACTIVE*\n\nYou are already in safe zone!\n\n" .
               "⏰ *Activated:* {$activated}\n" .
               "⏳ *Expires:* {$expires}\n" .
               "💎 *Tanu Coins deposited:* {$user['safe_zone']['coins_deposited']}";
    } else {
        return "🏦 *SAFE ZONE*\n\n" .
               "Secure your Tanu Coins here:\n\n" .
               "1️⃣ *1 Day* - 200 Tanu Coins\n" .
               "2️⃣ *2 Days* - 500 Tanu Coins\n" .
               "3️⃣ *3 Days* - 1000 Tanu Coins\n\n" .
               "💡 While in safe zone, no one can rob from you!\n" .
               "⏰ *Time starts when you activate*\n" .
               "💰 *Your Tanu Coins:* {$user['coins']}";
    }
}

/**
 * Get safe zone activated message
 */
function getSafeZoneActivatedMessage($type, $expires, $coinsDeposited) {
    return "✅ *SAFE ZONE ACTIVATED!*\n\n" .
           "🛡️ *Type:* {$type}\n" .
           "💰 *Tanu Coins deposited:* {$coinsDeposited}\n" .
           "⏳ *Expires:* {$expires}\n\n" .
           "You are now protected!";
}

/**
 * Get admin panel message
 */
function getAdminPanelMessage($data) {
    $totalPlayers = count($data['users']);
    $totalCoins = 0;
    
    foreach ($data['users'] as $user) {
        $totalCoins += $user['coins'];
    }
    
    return "🛡️ *ADMIN PANEL*\n\n" .
           "📊 *Statistics:*\n" .
           "• Total Players: {$totalPlayers}\n" .
           "• Total Tanu Coins: {$totalCoins}\n" .
           "• Daily Spins Used: " . ($data['system']['total_spins_today'] ?? 0) . "\n" .
           "• Daily Games Played: " . ($data['system']['total_games_today'] ?? 0) . "\n\n" .
           "⚙️ *Admin Commands:*\n" .
           "• `/addcoins USER_ID AMOUNT` - Add coins\n" .
           "• `/resetspins OPTION` - Reset spins\n" .
           "• `/userinfo USER_ID` - User information\n" .
           "• `/backup` - Create data backup\n" .
           "• `/botcast MESSAGE` - Broadcast message\n\n" .
           "🔄 *Reset Spins Options:*\n" .
           "1. Reply to user + `/resetspins`\n" .
           "2. `/resetspins @username`\n" .
           "3. `/resetspins USER_ID`\n" .
           "4. `/resetspins allusers` (ALL users)\n\n" .
           "⚠️ *For Owner Use Only*";
}

/**
 * Get reset spins message
 */
function getResetSpinsMessage() {
    return "🔄 *RESET SPINS OPTIONS*\n\n" .
           "1️⃣ *Reset single user:*\n" .
           "   • Reply to user's message: `/resetspins`\n" .
           "   • Or use: `/resetspins @username`\n" .
           "   • Or use: `/resetspins USER_ID`\n\n" .
           "2️⃣ *Reset ALL users:*\n" .
           "   • `/resetspins allusers`\n\n" .
           "📢 *All reset users will receive notification*";
}
/**
 * Get how to play message (SIMPLIFIED)
 */
function getHowToPlayMessage() {
    return "🎮 *HOW TO PLAY*\n\n" .
           "1️⃣ Play games to earn coins\n" .
           "2️⃣ Rob from other players\n" .
           "3️⃣ Protect coins in safe zone\n" .
           "4️⃣ See top players ranking!\n\n" .
           "Choose an option below:";
}

/**
 * Get user not started message
 */
function getUserNotStartedMessage() {
    return "❌ User hasn't started the bot!\n\n" .
           "They will get 500 Tanu Coins when they start.\n" .
           "Tell them to use /start command!";
}

// ADDITIONAL MESSAGES FOR GAME ACTIONS

/**
 * Get rob success message - UPDATED WITH MENTION AND GROUP LINK
 */
function getRobSuccessMessage($chatId, $targetUserId, $targetFirstName, $amountStolen) {
    // Always use mention link with first name
    $victimMention = "<a href=\"tg://user?id={$targetUserId}\">" . htmlspecialchars($targetFirstName) . "</a>";
    
    return "✅ <b>SUCCESSFUL ROBBERY!</b>\n\n" .
           "💰 You robbed <b>{$amountStolen} Tanu Coins</b>!\n" .
           "👤 <b>Victim:</b> {$victimMention}\n\n" .
           "⚠️ Warning: Victim has been notified!";
}

/**
 * Get rob failed message (caught) - UPDATED WITH MENTION AND GROUP LINK
 */
function getRobFailedMessage($chatId, $targetUserId, $targetFirstName, $fineAmount) {
    // Always use mention link with first name
    $victimMention = "<a href=\"tg://user?id={$targetUserId}\">" . htmlspecialchars($targetFirstName) . "</a>";
    
    return "❌ <b>CAUGHT!</b>\n\n" .
           "👤 <b>Target:</b> {$victimMention}\n" .
           "🚨 <b>You were caught robbing!</b>\n" .
           "💸 <b>Fine paid:</b> {$fineAmount} Tanu Coins\n\n" .
           "⚠️ <i>Better luck next time!</i>";
}

/**
 * Get spin wheel result message
 */
function getSpinWheelResultMessage($result, $coinsWon) {
    return "🎡 *SPIN WHEEL RESULT*\n\n" .
           "🎯 *Landed on:* {$result}\n" .
           "💰 *Tanu Coins won:* {$coinsWon}\n" .
           "🎊 *Congratulations!*";
}

/**
 * Get no spins left message
 */
function getNoSpinsLeftMessage() {
    return "❌ *NO SPINS LEFT*\n\n" .
           "You've used all 3 daily spins!\n" .
           "⏰ *Reset time:* Tomorrow 00:00\n\n" .
           "Try again tomorrow!";
}

/**
 * Get cooldown message
 */
function getCooldownMessage($secondsLeft) {
    return "⏳ *COOLDOWN ACTIVE*\n\n" .
           "Please wait {$secondsLeft} seconds\n" .
           "before using this command again!";
}

/**
 * Get bot detected message
 */
function getBotDetectedMessage() {
    return "🤖 *This user is a bot!*\n";
}

/**
 * Get victim alert message - UPDATED WITH THIEF MENTION AND GROUP LINK
 */
function getVictimAlertMessage($groupId, $thiefUserId, $thiefFirstName, $amountStolen) {
    // Thief mention with first name only
    $thiefMention = "<a href=\"tg://user?id={$thiefUserId}\">" . htmlspecialchars($thiefFirstName) . "</a>";
    
    return "🚨 <b>ALERT: TANU COINS ROBBED!</b>\n\n" .
           "😱 Your <b>{$amountStolen} Tanu Coins</b> were robbed!\n" .
           "👤 <b>Robber:</b> {$thiefMention}\n\n" .
           "🛡️ <b>For protection:</b> Use /safe command!";
}

/**
 * Get failed robbery alert to victim - UPDATED WITH THIEF MENTION AND GROUP LINK
 */
function getFailedRobberyAlertMessage($groupId, $thiefUserId, $thiefFirstName, $amountAttempted) {
    // Thief mention with first name only
    $thiefMention = "<a href=\"tg://user?id={$thiefUserId}\">" . htmlspecialchars($thiefFirstName) . "</a>";
    
    return "🛡️ <b>ROBBERY ATTEMPT FAILED!</b>\n\n" .
           "✅ Someone tried to rob <b>{$amountAttempted} Tanu Coins</b> from you!\n" .
           "👤 <b>Robber:</b> {$thiefMention}\n" .
           "🎯 <b>Attempt failed!</b> You are safe!\n\n" .
           "💎 <b>Your Tanu Coins are secure!</b>";
}

/**
 * Get group balance message (when checking others in group)
 */
function getGroupBalanceMessage($user, $userId) {
    // Check if user is a bot
    if (isset($user['is_bot']) && $user['is_bot']) {
        return "🤖 <b>This user is a bot!</b>\n" .
               "Bots don't have Tanu Coins.\n" .
               "You can only rob from real users!";
    }
    
    // USER को MENTION करें
    $mention = "<a href=\"tg://user?id={$userId}\">" . 
               ($user['username'] ? '@' . htmlspecialchars($user['username']) : 
               (htmlspecialchars($user['first_name'] ?? "User"))) . 
               "</a>";
    
    $coins = $user['coins'] ?? 0;
    $safeActive = isset($user['safe_zone']['active']) && $user['safe_zone']['active'];
    
    $message .= "👤 <b>User:</b> {$mention}\n";
    $message .= "💎 <b>Tanu Coins:</b> " . number_format($coins) . "\n";
    
    return $message;
}

/**
 * Get group own balance message
 */
function getGroupOwnBalanceMessage($user, $userId) {
    $coins = $user['coins'] ?? 0;
    $safeActive = isset($user['safe_zone']['active']) && $user['safe_zone']['active'];
    
    $message = "💰 <b>YOUR TANU COINS</b>\n\n";
    $message .= "💎 <b>Balance:</b> " . number_format($coins) . " Tanu Coins\n\n";
        
    return $message;
}
?>
