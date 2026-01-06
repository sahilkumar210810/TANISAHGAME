<?php
/**
 * BUTTONS CONFIGURATION FILE
 * All button layouts for the bot
 */

/**
 * Get buttons based on chat type and command
 */
function getButtons($buttonType, $chatType = 'private', $userId = null) {
    // If group chat, return null for most buttons
    if ($chatType !== 'private' && $buttonType !== 'group_welcome' && $buttonType !== 'group_help') {
        return null;
    }
    
    switch ($buttonType) {
        case 'welcome_new':
            return [
                'inline_keyboard' => [
                    [
                        ['text' => '👥 ADD TO GROUP', 'url' => 'https://t.me/' . BOT_USERNAME . '?startgroup=true']
                    ],
                    [
                        ['text' => '🎁 WELCOME BONUS', 'callback_data' => 'welcome_bonus'],
                        ['text' => '🎮 HOW TO PLAY', 'callback_data' => 'how_to_play']
                    ]
                ]
            ];
            
        case 'welcome_old':
            // OLD USER WELCOME - SAME AS NEW USER
            return [
                'inline_keyboard' => [
                    [
                        ['text' => '👥 ADD TO GROUP', 'url' => 'https://t.me/' . BOT_USERNAME . '?startgroup=true']
                    ],
                    [
                        ['text' => '🎁 WELCOME BONUS', 'callback_data' => 'welcome_bonus'],
                        ['text' => '🎮 HOW TO PLAY', 'callback_data' => 'how_to_play']
                    ]
                ]
            ];
            
        case 'games':
            return [
                'inline_keyboard' => [
                    [
                        ['text' => '🎡 Spin Wheel', 'callback_data' => 'game_spin']
                    ],
                    [
                        ['text' => '🔙 Back', 'callback_data' => 'how_to_play']
                    ]
                ]
            ];
                     
        case 'help':
            return [
                'inline_keyboard' => [
                    [
                        ['text' => '🔙 Back', 'callback_data' => 'how_to_play']
                    ]
                ]
            ];
                        
        case 'safe_zone':
            return [
                'inline_keyboard' => [
                    [
                        ['text' => '1 Day - 200 Coins', 'callback_data' => 'safe_1'],
                        ['text' => '2 Days - 500 Coins', 'callback_data' => 'safe_2']
                    ],
                    [
                        ['text' => '3 Days - 1000 Coins', 'callback_data' => 'safe_3']
                    ],
                    [
                        ['text' => '🔙 Back', 'callback_data' => 'btn_all']
                    ]
                ]
            ];
            
        case 'back_only':
            return [
                'inline_keyboard' => [
                    [
                        ['text' => '🔙 Back', 'callback_data' => 'btn_all']
                    ]
                ]
            ];
            
        case 'group_welcome':
            // GROUP में button - CLICK करने पर /start command send होगी
            return [
                'inline_keyboard' => [
                    [
                        ['text' => '🎮 Start Bot', 'url' => "https://t.me/" . BOT_USERNAME . "?start=group_welcome"]
                    ]
                ]
            ];
            
        case 'group_help':
            return [
                'inline_keyboard' => [
                    [
                        ['text' => '🎮 Start Bot', 'url' => "https://t.me/" . BOT_USERNAME . "?start=help"]
                    ]
                ]
            ];
            
        case 'how_to_play':
            // SIMPLIFIED BUTTONS - केवल 3 बटन
            return [
                'inline_keyboard' => [
                    [
                        ['text' => '🎮 Play Games', 'callback_data' => 'btn_games'],
                        ['text' => '🆘 Help', 'callback_data' => 'btn_help']
                    ],
                    [
                        ['text' => '♻️ All commands work', 'callback_data' => 'btn_all']
                    ],
                    [
                        ['text' => '🔙 Back', 'callback_data' => 'btn_back']
                    ]

                ]
            ];
        case 'all_commands':
            return [
                'inline_keyboard' => [
                    [
                        ['text' => '💰 Bal', 'callback_data' => 'btn_coins']
                    ],
                    [                
                        ['text' => '🚨 Rob', 'callback_data' => 'btn_steal'],
                        ['text' => '🛡️ Safe', 'callback_data' => 'btn_safe']
                    ],
                    [                
                        ['text' => '📊 Mystats', 'callback_data' => 'btn_stats'],
                        ['text' => '🏆 Leaderboard', 'callback_data' => 'btn_leaderboard']
                    ],
                    [
                        ['text' => '🔙 Back', 'callback_data' => 'how_to_play']
                    ]
                ]
            ];
            
        case 'no_buttons':
            // NO BUTTONS - सिर्फ text
            return null;
            
        default:
            return null;
    }
}
// OLD FUNCTIONS (backward compatibility)
function getWelcomeButtons() {
    return getButtons('welcome_new', 'private');
}

function getGamesButtons() {
    return getButtons('games', 'private');
}

function getCoinsButtons() {
    return getButtons('coins', 'private');
}

function getStatsButtons() {
    return getButtons('stats', 'private');
}

function getLeaderboardButtons() {
    return getButtons('leaderboard', 'private');
}

function getHelpButtons() {
    return getButtons('help', 'private');
}

function getStealButtons() {
    return getButtons('steal', 'private');
}

function getSafeZoneButtons() {
    return getButtons('safe_zone', 'private');
}

function getBackButton() {
    return getButtons('back_only', 'private');
}
?>
