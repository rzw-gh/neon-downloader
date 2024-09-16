<?php
function set_log($log)
{
    global $from_id;
    $logContent = "\n" . "-------------------" . "\n" . '-' . date("Y-m-d H:i:s") . '-' . $from_id . "-" . $log;
    $path = $_SERVER['DOCUMENT_ROOT'] . '/';
    if (!file_exists($path)) {
        mkdir($path, 0700);
    }
    file_put_contents($path . "bot_log.txt", $logContent . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function extract_actions($data)
{
    preg_match('/^(.*?)~(.*?)$/', $data, $matches);

    $current = isset($matches[1]) ? $matches[1] : null;
    $previous = isset($matches[2]) ? $matches[2] : null;

    return [$current, $previous];
}

function append_previous_action($new_command, $previous_command = null)
{
    global $input_type, $text, $data, $chat_id;

    if ($input_type == 'message') {
        $current_command = $text;
    } elseif (preg_match('/^(.*?)~(.*?)$/', $data, $matches)) {
        [$current, $previous] = extract_actions($data);
        $current_command = $current;
    } else {
        $current_command = $data;
    }

    if ($current_command == $new_command) {
        $previous_command = $previous_command;
    } else {
        $previous_command = $current_command;
    }

    return $new_command . "~" . $previous_command;
}

function check_user()
{
    global $db, $input_type, $from_id, $from_username, $full_name, $chat_id, $text, $maintance, $data, $now;

    maintance();

    $user = $db->table("user")->select()->where([["tid", "=", $from_id]])->execute();
    $bot_msg_id = null;
    $step = null;

    // new user
    if (count($user) == 0) {
        if ($input_type == 'message') {
            $haystack = $text;
        } else {
            $haystack = $data;
        }
        [$current, $previous] = extract_actions($haystack);
        if (isset($current)) {
            $haystack = $previous;
        }

        $user = $db->table("user")->insert([
            "tid" => $from_id,
            "name" => $full_name,
            "username" => $from_username,
            "joined_at" => $now
        ])->execute();
    } else {
        $bot_msg_id = $user[0]['bot_msg_id'];
        $step = $user[0]['step'];
    }

    return [$user, $bot_msg_id, $step];
}

function maintance()
{
    global $maintance, $chat_id, $from_id;
    if ($maintance && !is_super_admin($from_id)) {
        bot('sendmessage', [
            'chat_id' => $chat_id,
            'text' => "سرویس در حال ارتقا میباشد. به زودی با قابلیت های بیشتر برمیگردیم ♥️"
        ]);
        exit();
    }
}

function is_super_admin($from_id)
{
    global $super_user_tids;
    if (in_array($from_id, $super_user_tids)) {
        return true;
    }
    return false;
}

function format_size($clen) {
    $size = 'Unknown';

    switch ($clen) {
        case $clen < 1024:
            $size = $clen . ' B';
            break;
        case $clen < 1048576:
            $size = round($clen / 1024, 2) . ' KB';
            break;
        case $clen < 1073741824:
            $size = round($clen / 1048576, 2) . ' MB';
            break;
        case $clen < 1099511627776:
            $size = round($clen / 1073741824, 2) . ' GB';
            break;
    }
    return $size;
}

function get_file_size($url) {
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, TRUE);
    curl_setopt($ch, CURLOPT_NOBODY, TRUE);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

    $data = curl_exec($ch);
    $clen = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    curl_close($ch);
    $size = 'Unknown';
    return format_size($clen);
}

function get_string_between($string, $start, $end) {
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) return '';
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
}

function link_is_valid() {
    global $text;

    if (preg_match('/action/', $text) === 0) {
        if (!is_null(is_instagram_link($text))) {
            $video_id = is_instagram_link($text);
            return [$text, $video_id, "instagram"];
        } elseif (!is_null(is_youtube_link($text))) {
            $video_id = is_youtube_link($text);
            return [$text, $video_id, "youtube"];
        } else {
            show_error("⚠️ لطفا لینک اینستاگرام یا یوتیوب بفرستید", true);
        }
    } else {
        return [null, null, null];
    }
}

function is_instagram_link($url) {
    $reelPattern = '/(?:https?:\/\/)?(?:www\.)?instagram\.com\/reel\/([^\/?#]+)/i';
    $picturePattern = '/(?:https?:\/\/)?(?:www\.)?instagram\.com\/p\/([^\/?#]+)/i';

    if (preg_match($reelPattern, $url, $reelMatches)) {
        return $reelMatches[1];
    } elseif (preg_match($picturePattern, $url, $pictureMatches)) {
        return $pictureMatches[1];
    } else {
        return null;
    }
}

function is_youtube_link($url) {
    $pattern = '/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:\S+\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})\S*/i';

    if (preg_match($pattern, $url, $matches)) {
        return $matches[1];
    } else {
        return null;
    }
}

function live_statistics($content_link, $type = 'instagram', $user_tid) {
    global $db, $from_id;
    $users = $db->raw("SELECT tid FROM user WHERE live_statistics = '1'")->execute();

    foreach ($users as $user) {
        if ($from_id != $user['tid'] || !is_super_admin($from_id)) {
            [$title, $link, $username] = get_chat_info($user_tid, 'user');
            if (is_null($link)) {
                $callback_type = 'callback_data';
                $callback_data = 'none'; 
            } else {
                $callback_type = 'url';
                $callback_data = $link; 
            }

            $buttons = [
                [['text' => $title, $callback_type => $callback_data]],
                [['text' => $type, 'url' => $content_link]],
                [['text' => "🚫 لغو بازدید زنده", 'callback_data' => 'cancel_live_statistics']],
            ];

            sendMessage($user['tid'], "🛰 بازدید زنده", ['inline_keyboard' => $buttons], null, true, false);
        }
    }
}

function add_return_home($buttons = [], $return_data = null) {
    if (isset($return_data)) {
        $buttons[] = [
            ['text' => "🏠 خانه", 'callback_data' => 'home'], ['text' => "🔙 برگشت", 'callback_data' => $return_data]
        ];
    } else {
        $buttons[] = [
            ['text' => "🏠 خانه", 'callback_data' => 'home']
        ];
    }
    return $buttons;
}

function check_user_download_cooldown() {
    global $from_id, $db;
    $user = $db->table('user')->select()->where([['tid', '=', $from_id]])->execute()[0];
    if (!isset($user['last_download_date'])) {
        return null;
    }
    $last_download_date = strtotime($user['last_download_date']);
    $current_timestamp = time();
    $difference = $current_timestamp - $last_download_date;
    if ($difference >= 30) {
        return null;
    } else {
        return 30 - $difference;
    }
}

function set_orm_log($err) {
    foreach ($err as $err) {
        foreach ($err as $err) {
            set_log($err);
        }
    }
}

function paginate($query, $limit = 12, $page = 1) {
    global $db;

    $offset = ($page - 1) * $limit;

    $total_records = $db->raw("SELECT COUNT(*) as total FROM ($query) as total")->execute()[0]['total'];

    $total_pages = ceil($total_records / $limit);
    $prev_page = max($page - 1, 1);
    $next_page = min($page + 1, $total_pages);

    $data = $db->raw($query . " LIMIT $limit OFFSET $offset")->execute();

    $pagination = [];
    if ($prev_page != $page) {
        $pagination['prev'] = $prev_page;
    }
    if ($next_page != $page) {
        $pagination['next'] = $next_page;
    }

    return [$data, $pagination];
}