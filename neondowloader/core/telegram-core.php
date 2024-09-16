<?php
function bot($method, $params = [], $update_user = true)
{
    global $bot_token, $bot_msg_id;
    $url = "https://api.telegram.org/bot" . $bot_token . "/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    $response = curl_exec($ch);
    if(curl_errno($ch)) {
        set_log(curl_error($ch));
    }
    curl_close($ch);
    if ($response) {
        $responseData = json_decode($response, true);
        if ($responseData && $responseData['ok']) {
            if ($update_user) {
                $bot_msg_id = $responseData['result']['message_id'];
                update_user($bot_msg_id);
            }
            if (isset($responseData['result']['message_id'])) {
                return $responseData['result']['message_id'];
            }
        } else {
            if (preg_match('/message to copy not found/', $responseData['description'], $matches)) {
                return "message_to_copy_not_found";
            } else {
                set_log($responseData['description']);
                return null;
            }
        }
    } else {
        set_log(json_decode($response));
        return null;
    }
}

function sendAction($chat_id, $action) {
    // Type of action to broadcast. Choose one, depending on what the user is about to receive: typing for text messages, upload_photo for photos, record_video or upload_video for videos, record_voice or upload_voice for voice notes, upload_document for general files, choose_sticker for stickers, find_location for location data, record_video_note or upload_video_note for video notes.

    $params = [
        'chat_id' => $chat_id,
        'action' => $action
    ];

    return bot('SendChatAction', $params);
}

function sendContent($post_id, $caption = null, $replyMarkup = null, $update_user = true, $delete_previous = false, $protect_content = false)
{
    global $from_id, $content_channel_id, $message_id;

    $params = [
        'chat_id' => $from_id,
        'from_chat_id' => $content_channel_id,
        'message_id' => $post_id,
        'protect_content' => $protect_content,
    ];

    if (isset($caption)) {
        $params['caption'] = $caption;
    }

    if (!is_null($replyMarkup)) {
        $params['reply_markup'] = json_encode($replyMarkup);
    }

    $msg_id = bot('copyMessage', $params, $update_user);

    if (isset($msg_id) && $delete_previous) {
        deleteMessage($message_id);
    }

    return $msg_id;
}

function sendMessage($chat_id, $text, $replyMarkup = null, $message_id = null, $web_page_preview = true, $update_user = true, $delete_previous = false)
{
    $params = [
        'chat_id' => $chat_id,
        'text' => (string)$text,
        'parse_mode' => "Markdown",
        'disable_web_page_preview' => $web_page_preview,
    ];

    if (!is_null($message_id) && !$delete_previous) {
        $params['reply_to_message_id'] = $message_id;
    }

    if (!is_null($replyMarkup)) {
        $replyMarkup['resize_keyboard'] = true;
        $replyMarkup['one_time_keyboard'] = true;
        $params['reply_markup'] = json_encode($replyMarkup);
    }

    $msg_id = bot('sendMessage', $params, $update_user);

    if (isset($msg_id) && $delete_previous) {
        deleteMessage($message_id);
    }

    return $msg_id;
}

function editMsg($text = null, $replyMarkup = null, $type = 'text') {
    global $bot_msg_id, $chat_id;
    
    $params = [
        'chat_id' => $chat_id,
        'message_id' => $bot_msg_id,
        'parse_mode' => "Markdown",
        'disable_web_page_preview' => true,
    ];

    if (!is_null($text)) {
        $text = (string)$text;
        if ($type == 'text') {
            $params['text'] = $text;
        } elseif ($type == 'caption') {
            $params['caption'] = $text;
        }
    }

    if (!is_null($replyMarkup)) {
        $params['reply_markup'] = json_encode($replyMarkup);
    }

    if ($type == 'text') {
        $type = 'editMessageText';
    } elseif ($type == 'caption') {
        $type = 'editMessageCaption';
    } elseif ($type == 'button') {
        $type = 'editMessageReplyMarkup';
    }

    if (!is_null($text) || !is_null($replyMarkup)) {
        return bot($type, $params);
    }
}

function deleteMessage($messageID = null)
{
    global $chat_id, $message_id;
    if (isset($messageID)) {
        $message_id = $messageID;
    }
    $params = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
    ];

    return bot('deleteMessage', $params, false);
}

function sendDocument($type, $chatID = null, $documentID, $caption = null, $replyMarkup = null)
{
    global $chat_id;
    if (isset($chatID)) {
        $chat_id = $chatID;
    }

    $params = [
        'chat_id' => $chat_id,
        $type => $documentID,
    ];

    if (isset($caption)) {
        $params['caption'] = $caption;
    }

    if (!is_null($replyMarkup)) {
        $params['reply_markup'] = json_encode($replyMarkup);
    }

    if ($type == "photo") {
        return bot('sendPhoto', $params);
    } elseif ($type == "video") {
        return bot('sendVideo', $params);
    } elseif ($type == "document") {
        return bot('sendDocument', $params);
    }
}

function show_alert($text="⚠️ خطایی رخ داد لطفا دوباره تلاش کنید", $exit = false) {
    global $query_id, $bot_token;
    $ch = curl_init("https://api.telegram.org/bot$bot_token/answerCallbackQuery");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, ['callback_query_id' => $query_id, 'text' => $text, "show_alert" => true]);
    curl_exec($ch);
    curl_close($ch);
    if ($exit) {
        exit;
    }
}

function show_error($text, $exit = false) {
    global $from_id;
    sendMessage($from_id, $text);
    if ($exit) {
        exit;
    }
}

function get_chat_info($from_id, $type) {
    global $bot_token;
    $username = null;
    $title = null;
    $link = null;

    $chatInfo = json_decode(file_get_contents("https://api.telegram.org/bot$bot_token/getChat?chat_id=$from_id"), true);
    if ($chatInfo['ok']) {
        if ($type == 'channel') {
            $title = $chatInfo['result']['title'];
            $username = isset($chatInfo['result']['username']) ? $chatInfo['result']['username'] : null;
            $link = isset($chatInfo['result']['invite_link']) ? $chatInfo['result']['invite_link'] : null;
        } elseif ($type == 'user') {
            $username = isset($chatInfo['result']['username']) ? $chatInfo['result']['username'] : null;
            if (isset($chatInfo['result']['has_private_forwards'])) {
                $link = null;
            } else {
                if (is_null($username)) {
                    $link = "tg://user?id=$from_id";
                } else {
                    $link = "https://t.me/$username";
                }
            }
            if (strlen($chatInfo['result']['first_name']) == 0) {
                $title = "کاربر";
            } else {
                $title = $chatInfo['result']['first_name'];
            }
        } elseif ($type == 'private') {
            $username = isset($chatInfo['result']['username']) ? $chatInfo['result']['username'] : null;
            if (isset($chatInfo['result']['has_private_forwards'])) {
                $link = null;
            } else {
                if (is_null($username)) {
                    $link = "tg://user?id=$from_id";
                } else {
                    $link = "https://t.me/$username";
                }
            }
            $title = $chatInfo['result']['first_name'];
        }
    }

    return [$title, $link, $username];
}

function is_chat_member($user_id, $chat_id, $sponser_id = null) {
    global $bot_token, $db;

    if (is_array($chat_id)) {
        foreach ($chat_id as $sponser) {
            if (!is_chat_member($user_id, $sponser['chat_id'])) {
                return false;
            }
        }
    } else {
        $res = json_decode(file_get_contents("https://api.telegram.org/bot" . $bot_token . "/getChatMember?chat_id=$chat_id&user_id=" . $user_id));

        if (isset($sponser_id) && !$res->ok && $res->description == "Bad Request: chat not found") {
            $db->table('sponser')->update(['active' => '0'])->where([['id', '=', $sponser_id]])->execute();
            return true;
        } else {
            $res = $res->result->status;
            if ($res != 'member' && $res != 'creator' && $res != 'administrator' && !is_super_admin($user_id)) {
                return false;
            }
        }
    }
    return true;
}

// avoid sending message in this function. it will cause infinit loop
function update_user($bot_msg_id = null, $new_step = null, $bot_text = null) {
    global $from_id, $db, $now;

    $update_arr = ['blocked_by_user' => '0', "last_interaction"=>$now];

    if (isset($new_step)) {
        $update_arr['step'] = $new_step;
    }

    if (isset($bot_msg_id)) {
        $update_arr['bot_msg_id'] = $bot_msg_id;
    }

    if (isset($bot_text)) {
        $update_arr['bot_text'] = $bot_text;
    }

    $db->table("user")->update($update_arr)->where([['tid', '=', $from_id]])->execute();
}