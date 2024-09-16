<?php
if (is_super_admin($from_id)) {
    if ($data == 'menu_ad') {
        editMsg(
            "🚀 تبلیغات",
            ['inline_keyboard' => [
                [['text' => "💭 کامنت", 'callback_data' => 'ad_comment'], ['text' => "🔒 اسپانسر", 'callback_data' => 'ad_sponser']],
                [['text' => "🐳 همگانی", 'callback_data' => 'ad_global'], ['text' => "🖼 بنر", 'callback_data' => 'chat_group']],
                [['text' => "🏠 خانه", 'callback_data' => 'home']]
            ]]
        );
    }

    //////SPONSER//////
    if ($data == 'ad_sponser') {
        sponsers();
    } elseif ($data == 'ad_sponser_add') {
        $buttons = add_return_home([], "ad_sponser");
        editMsg("🔒 افزودن اسپانسر\n\nلطفا با استفاده از دستور زیر اطلاعات را ارسال کنید\n\n`action=add_sponser&chat_id=-1002042093640&invite_link=+a7S9MJ_9bZo3Y2M0`", ['inline_keyboard' => $buttons]);
    } elseif ($data == 'ad_sponser_guide') {
        show_alert("⁉️ در صورت داشتن اسپانسر فعال, کاربران باید قبل از دریافت محتوا عضو کانال اسپانسر شوند");
    } elseif (preg_match('/^ad_sponser_active_(\d+)$/', $data, $matches)) {
        $sponser_id = $matches[1];
        $sponser = $db->table('sponser')->select()->where([['id', '=', $sponser_id]])->execute()[0];
        if ((int)$sponser['active'] === 1) {
            $db->table('sponser')->update(["active" => "0"])->where([['id', '=', $sponser_id]])->execute();
            show_alert("❌ اسپانسر غیر فعال شد");
        } else {
            $db->table('sponser')->update(["active" => "1"])->where([['id', '=', $sponser_id]])->execute();
            show_alert("✅ اسپانسر فعال شد");
        }
        sponsers();
    } elseif (preg_match('/^ad_sponser_delete_(warning|confirm)_(\d+)$/', $data, $matches)) {
        $type = $matches[1];
        $sponser_id = $matches[2];

        if ($type === "warning") {
            $buttons = [
                [['text' => "❌ لغو", 'callback_data' => "ad_sponser"], ['text' => "✅ تایید", 'callback_data' => "ad_sponser_delete_confirm_$sponser_id"]],
            ];
            editMsg("🗑 حذف اسپانسر", ['inline_keyboard' => $buttons]);
        } elseif ($type === "confirm") {
            $db->table('sponser')->delete()->where([['id', '=', $sponser_id]])->execute();
            if (!$db->hasError()) {
                show_alert("✅ اسپانسر با موفقیت حذف شد");
            } else {
                show_alert("⚠️ خطایی رخ داد");
            }
            sponsers();
        }
    } elseif (preg_match('/action=(\w+)&chat_id=(-?\d+)&invite_link=(\+[a-zA-Z0-9_-]+.*?)$/', $text, $matches)) {
        $type = $matches[1];
        $chat_id = $matches[2];
        $invite_link = $matches[3];

        $buttons = add_return_home([], "ad_sponser");
        if ($type == 'add_sponser') {
            deleteMessage();
            $db->table('sponser')->insert(["chat_id" => $chat_id, "invite_link" => $invite_link])->execute();
            if (!$db->getError()) {
                sendMessage($from_id, '✅ اسپانسر با موفقیت اضافه شد', ['inline_keyboard' => $buttons]);
            } else {
                sendMessage($from_id, '⚠️ خطایی رخ داده است لطفا دوباره تلاش کنید ', ['inline_keyboard' => $buttons]);
            }
        }
    }
}

function sponsers() {
    global $db;
    $buttons = [
        [['text' => "⁉️ توضیحات", 'callback_data' => "ad_sponser_guide"]],
    ];

    $sponsers = $db->table("sponser")->select()->execute();
    if (count($sponsers) > 0) {
        $buttons[] = [
            ['text' => "حذف", "callback_data" => "none"],
            ['text' => "فعال", "callback_data" => "none"],
            ['text' => "لینک", "callback_data" => "none"],
            ['text' => "کانال", "callback_data" => "none"],
        ];
    }
    foreach ($sponsers as $sponser) {
        $sponser_id = $sponser['id'];
        [$title, $link, $username] = get_chat_info($sponser['chat_id'], 'channel');
        if (is_null($link)) {
            $callback_type = 'callback_data';
            $callback_data = 'none';
            $title = "کانال";
        } else {
            $callback_type = 'url';
            $callback_data = $link;
        }

        $buttons[] = [
            ['text' => "❌", 'callback_data' => "ad_sponser_delete_warning_$sponser_id"],
            ['text' => (int)$sponser['active'] === 1 ? "✅" : "❌", 'callback_data' => "ad_sponser_active_$sponser_id"],
            ['text' => "🔗", "url" => "t.me/" . $sponser['invite_link']],
            ['text' => $title, $callback_type => $callback_data],
        ];
    }

    $buttons[] = [['text' => "➕ افزودن", 'callback_data' => "ad_sponser_add"]];

    $buttons = add_return_home($buttons, "menu_ad");
    editMsg("🔒 اسپانسر", ['inline_keyboard' => $buttons]);
}