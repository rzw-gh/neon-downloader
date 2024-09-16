<?php
$input = json_decode(file_get_contents("php://input"));
$bot_msg_id = null;

if (isset($input->message)) {
    $input_type = 'message';
    $message = $input->message;
    $from_id    = $input->message->from->id;
    $from_username    = isset($input->message->from->username) ? $input->message->from->username : null;
    $chat_id    = $input->message->chat->id;
    $chat_type  = $input->message->chat->type;
    $text       = isset($input->message->text) ? ltrim($input->message->text) : null;
    $first_name = isset($input->message->from->first_name) ? $input->message->from->first_name : null;
    $last_name = isset($input->message->from->last_name) ? $input->message->from->last_name : null;
    $full_name = "$first_name $last_name";
    $message_id = $input->message->message_id;
    $forwardedChatId = isset($message->forward_from_chat->id) ? $message->forward_from_chat->id : null;

    if (isset($input->message->photo)) {
        $photo_id = isset($input->message->photo) ? end($input->message->photo)->file_id : null;
        $caption = isset($input->message->caption) ? $input->message->caption : null;
    }

    if (isset($input->message->contact)) {
        $contact = $input->message->contact;
        $phone_number = $input->message->contact->phone_number;
    }
} elseif (isset($input->callback_query)) {
    $input_type = 'callbackquery';
    $from_id    = $input->callback_query->from->id;
    $from_username    = isset($input->callback_query->from->username) ? $input->callback_query->from->username : null;
    $first_name    = isset($input->callback_query->from->first_name) ? $input->callback_query->from->first_name : null;
    $last_name    = isset($input->callback_query->from->last_name) ? $input->callback_query->from->last_name : null;
    $full_name = "$first_name $last_name";
    $chat_id    = $input->callback_query->message->chat->id;
    $data       = ltrim($input->callback_query->data);
    $query_id   = $input->callback_query->id;
    $message_id = $input->callback_query->message->message_id;
    $in_text    = isset($input->callback_query->message->text) ? $input->callback_query->message->text : null;
}

require_once($_SERVER['DOCUMENT_ROOT'] . "/core/config.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/vendor/autoload.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/core/telegram-core.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/core/instagram-core.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/core/youtube-core.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/core/utils.php");

[$user, $bot_msg_id, $step] = check_user();

require_once($_SERVER['DOCUMENT_ROOT'] . "/admin/admin-panel.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/downloader.php");