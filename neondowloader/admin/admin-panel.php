<?php
if (is_super_admin($chat_id)) {
    if ($text == '/start') {
        sendMessage(
            $chat_id,
            "سلام رِئیس 👑 خوش اومدی",
            ['inline_keyboard' => [
                [['text' => "🚀 تبلیغات", 'callback_data' => 'menu_ad'], ['text' => "📊 آمار", 'callback_data' => 'menu_statistics']],
                [['text' => !$maintance ? "⚡ ربات روشن است" : "🛠️ ربات خاموش است", 'callback_data' => !$maintance ? 'power_off' : 'power_on']]
            ]],
            null, true, true, true
        );
        exit();
    } elseif ($data == 'home') {
        editMsg(
            "سلام رِئیس 👑 خوش اومدی",
            ['inline_keyboard' => [
                [['text' => "🚀 تبلیغات", 'callback_data' => 'menu_ad'], ['text' => "📊 آمار", 'callback_data' => 'menu_statistics']],
                [['text' => !$maintance ? "⚡ ربات روشن است" : "🛠️ ربات خاموش است", 'callback_data' => !$maintance ? 'power_off' : 'power_on']]
            ]],
        );
        exit();
    } elseif (preg_match('/^power_(\w+)$/', $data, $matches)) {
        $type = $matches[1];
        if ($type == 'off') {
            $db->table('config')->update(['maintance'=>'1'])->where([['id', '=', '1']])->execute();
            editMsg(
                "سلام رِئیس 👑 خوش اومدی",
                ['inline_keyboard' => [
                    [['text' => "🚀 تبلیغات", 'callback_data' => 'menu_ad'], ['text' => "📊 آمار", 'callback_data' => 'menu_statistics']],
                    [['text' => "🛠️ ربات خاموش است", 'callback_data' => 'power_on']]
                ]],
            );
        } else {
            $db->table('config')->update(['maintance'=>'0'])->where([['id', '=', '1']])->execute();
            editMsg(
                "سلام رِئیس 👑 خوش اومدی",
                ['inline_keyboard' => [
                    [['text' => "🚀 تبلیغات", 'callback_data' => 'menu_ad'], ['text' => "📊 آمار", 'callback_data' => 'menu_statistics']],
                    [['text' => "⚡ ربات روشن است", 'callback_data' => 'power_off']]
                ]],
            );
        }
        exit();
    }

    require_once($_SERVER['DOCUMENT_ROOT'] . "/admin/statistics.php");
    require_once($_SERVER['DOCUMENT_ROOT'] . "/admin/ad.php");
}