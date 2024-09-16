<?php
if (is_super_admin($from_id)) {
    if ($data == 'menu_statistics') {
        list_statistics_menu();
    } elseif ($data == 'live_statistics') {
        $user = $db->table('user')->update(["live_statistics" => '1'])->where([['tid', '=', $from_id]])->execute();
        show_alert("✅ بازدید زنده با موفقیت فعال شد");
        list_statistics_menu();
    } elseif ($data == 'cancel_live_statistics') {
        $user = $db->table('user')->update(["live_statistics" => '0'])->where([['tid', '=', $from_id]])->execute()[0];
        show_alert("✅ بازدید زنده با موفقیت غیر فعال شد");
        list_statistics_menu();
    } elseif ($data == 'users_statistics') {
        $user_count = $db->raw("SELECT COUNT(id) AS user_count FROM user")->execute()[0]['user_count'];

        $currentDate = new DateTime();

        $firstDay = clone $currentDate;
        $firstDay->modify('this week')->setTime(0, 0, 0);
        $lastDay = clone $firstDay;
        $lastDay->modify('this week +6 days')->setTime(23, 59, 59);
        $weekStartDate = $firstDay->format('Y-m-d H:i:s');
        $weekEndDate = $lastDay->format('Y-m-d H:i:s');

        $firstDay = clone $currentDate;
        $firstDay->modify('first day of this month')->setTime(0, 0, 0);
        $lastDay = clone $currentDate;
        $lastDay->modify('last day of this month')->setTime(23, 59, 59);
        $monthStartDate = $firstDay->format('Y-m-d H:i:s');
        $monthEndDate = $lastDay->format('Y-m-d H:i:s');

        $todayStart = clone $currentDate;
        $todayStart = $todayStart->setTime(0, 0, 0);
        $todayEnd = clone $currentDate;
        $todayEnd = $todayEnd->setTime(23, 59, 59);
        $todayStartDate = $todayStart->format('Y-m-d H:i:s');
        $todayEndDate = $todayEnd->format('Y-m-d H:i:s');

        $month_new_users = (string)$db->raw("SELECT COUNT(id) AS user_count FROM user WHERE joined_at >= '$monthStartDate'")->execute()[0]['user_count'];
        $month_active_users = (string)$db->raw("SELECT COUNT(id) AS user_count FROM user WHERE last_interaction >= '$monthStartDate'")->execute()[0]['user_count'];
        $week_new_users = (string)$db->raw("SELECT COUNT(id) AS user_count FROM user WHERE joined_at >= '$weekStartDate' AND joined_at <= '$weekEndDate'")->execute()[0]['user_count'];
        $week_active_users = (string)$db->raw("SELECT COUNT(id) AS user_count FROM user WHERE last_interaction >= '$weekStartDate' AND last_interaction <= '$weekEndDate'")->execute()[0]['user_count'];
        $today_new_users = (string)$db->raw("SELECT COUNT(id) AS user_count FROM user WHERE joined_at >= '$todayStartDate' AND joined_at <= '$todayEndDate'")->execute()[0]['user_count'];
        $today_active_users = (string)$db->raw("SELECT COUNT(id) AS user_count FROM user WHERE last_interaction >= '$todayStartDate' AND last_interaction <= '$todayEndDate'")->execute()[0]['user_count'];

        $buttons = [
            [['text' => "👥 کاربران: $user_count", 'callback_data' => 'none']],
            [['text' => "🌱 تازه ماه: $month_new_users", 'callback_data' => 'none'], ['text' => "🚀 فعال ماه: $month_active_users", 'callback_data' => 'none']],
            [['text' => "🌱 تازه هفته: $week_new_users", 'callback_data' => 'none'], ['text' => "🚀 فعال هفته: $week_active_users", 'callback_data' => 'none']],
            [['text' => "🌱 تازه امروز: $today_new_users", 'callback_data' => 'none'], ['text' => "🚀 فعال امروز: $today_active_users", 'callback_data' => 'none']],
        ];

        $buttons[] = [
            ['text' => "🏠 خانه", 'callback_data' => 'home'],
            ['text' => "🔙 برگشت", 'callback_data' => 'menu_statistics'],
        ];

        editMsg(
            "👥 کاربران",
            ['inline_keyboard' => $buttons]
        );
    }
}

function list_statistics_menu() {
    global $from_id, $db;
    $user = $db->table('user')->select()->where([['tid', '=', $from_id]])->execute()[0];
    if ($user['live_statistics']) {
        $live_statistics_text = "🚫 لغو بازدید زنده";
        $live_statistics_data = 'cancel_live_statistics';
    } else {
        $live_statistics_text = "🛰 بازدید زنده";
        $live_statistics_data = 'live_statistics';
    }

    $config = $db->table('config')->select()->execute()[0];
    $instagram = $config['instagram_download'];
    $youtube = $config['youtube_download'];

    editMsg(
        "📊 آمار\n\n🔴 دانلود YouTube: $youtube\n🟠 دانلود Instagram: $instagram\n\n",
        ['inline_keyboard' => [
            [['text' => $live_statistics_text, 'callback_data' => $live_statistics_data]],
            [['text' => "👥 کاربران", 'callback_data' => 'users_statistics']],
            [['text' => "🏠 خانه", 'callback_data' => 'home']],
        ]]
    );
}