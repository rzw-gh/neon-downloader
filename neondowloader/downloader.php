<?php
function forceDownload($url)
{
    $ch = curl_init();
    $headers = array();
    $headers[] = 'User-Agent: Mozilla/5.0 (Windows NT 6.3; WOW64; rv:49.0) Gecko/20100101 Firefox/49.0';
    header("Pragma: public");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: private", false);
    //header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="mp4');
    header("Content-Transfer-Encoding: binary");
    if (isset($_SERVER['HTTP_RANGE'])) {
        $headers[] = 'Range: ' . $_SERVER['HTTP_RANGE'];
    }
    $headers[] = 'Origin: https://www.youtube.com';
    $headers[] = 'Referer: https://www.youtube.com/';
    // otherwise you get weird "OpenSSL SSL_read: No error"
    curl_setopt($ch, CURLOPT_REFERER, $url);
    curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/94.0.4606.81 Safari/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_BUFFERSIZE, 256 * 1024);
    curl_setopt($ch, CURLOPT_URL, $url);
    //curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    // we deal with this ourselves
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 0);
    curl_setopt($ch, CURLOPT_HEADER, 0);

    $ret = curl_exec($ch);
    $error = ($ret === false) ? sprintf('curl error: %s, num: %s', curl_error($ch), curl_errno($ch)) : null;
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    set_log("$status_code - here: $error");
    set_log($ret);
    curl_close($ch);
    // if we are still here by now, then all must be okay
    return true;


    // $response = curl_exec($ch);
    // set_log("now");
    // set_log($response);
    // $error = ($response === false) ? sprintf('curl error: %s, num: %s', curl_error($ch), curl_errno($ch)) : null;
    // $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // set_log("$status_code - here: $error");
    // curl_close($ch);

    // // Split header and body
    // list($header, $body) = explode("\r\n\r\n", $response, 2);

    // // Extract filename from Content-Disposition header
    // if (preg_match('/filename="([^"]+)"/', $header, $matches)) {
    //     $filename = $matches[1];
    // } else {
    //     $filename = 'video.mp4'; // Default filename if Content-Disposition header is not present
    // }

    // // Save the body to a file in the document root
    // $file_path = $_SERVER['DOCUMENT_ROOT'] . "/video.mp4";
    // file_put_contents($file_path, $body);

    // // Return true if download and save were successful
    // return true;
}

if (isset($text)) {
    [$link, $video_id, $type] = link_is_valid();

    if (is_null($type)) {die();}

    if ($type == 'instagram') {
        $cooldown = check_user_download_cooldown();
        if (!is_null($cooldown) && !is_super_admin($from_id)) {
            show_error("⚠️ لطفا تا دانلود بعدی $cooldown ثانیه صبر کنید", true);
        }

        try {
            $insta = new Insta($link);
            if($insta->videoid() == null){
                show_error("⚠️ لینک اینستاگرام ارسال شده معتبر نمیباشد", true);
            }

            $media = $insta->get_media();
            if($media==false || count($media['links'])==0) {
                show_error("⚠️ لینک دانلود یافت نشد", true);
            }

            foreach($media['links'] as $media_link) {
                $video_link = $media_link['url'];
                $id = download_instagram_content($video_link);
            }

            if (isset($id)) {
                live_statistics($link, $type = 'instagram', $from_id);
                $db->table('user')->update(['last_download_date'=>$now])->where([['tid', '=', $from_id]])->execute();
            }

            $instagram_download = intval($config['instagram_download']) + 1;
            $db->table("config")->update(["instagram_download" => $instagram_download])->where([['id', '=', $config['id']]])->execute();
        } catch (Exception $ex) {
            $db->table('user')->update(['last_download_date'=>$now])->where([['tid', '=', $from_id]])->execute();
            show_error("⚠️ $ex", true);
        }
    } elseif ($type == 'youtube') {        
        $cooldown = check_user_download_cooldown();
        if (!is_null($cooldown) && !is_super_admin($from_id)) {
            // show_error("⚠️ لطفا تا دانلود بعدی $cooldown ثانیه صبر کنید", true);
        }

        try {
            preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $link, $match);
            $video_id =  $match[1];
            $video = json_decode(getVideoInfo($video_id));
            $expires_in_seconds = $video->streamingData->expiresInSeconds;
            $formats = $video->streamingData->formats;
            $adaptiveFormats = $video->streamingData->adaptiveFormats;
            $thumbnails = $video->videoDetails->thumbnail->thumbnails;
            $title = $video->videoDetails->title;
            $length_seconds = $video->videoDetails->lengthSeconds;
            $short_description = $video->videoDetails->shortDescription;
            $thumbnail = end($thumbnails)->url;

            $caption = "$title\n\n" . str_split($short_description, 100)[0];

            foreach($formats as $format) {
                if(isset($format->url) && $format->url){
                    $url = $format->url;
                }else{
                    $signature = "https://example.com?".$format->signatureCipher;
                    parse_str( parse_url( $signature, PHP_URL_QUERY ), $parse_signature );
                    $url = $parse_signature['url']."&sig=".$parse_signature['s'];
                }

                // $url = urlencode($url);

                // $id = download_instagram_content($url);
            }

            foreach ($adaptiveFormats as $video) {
                if(isset($video->url) && $format->url){
                    $url = $video->url;
                }else{
                    $signature = $video->signatureCipher;
                    parse_str( parse_url( $signature, PHP_URL_QUERY ), $parse_signature );
                    $url = $parse_signature['url'];
                }

                // $url = urlencode($url);

                if(isset($video->mimeType) && $video->mimeType) {
                    $type = explode(";",explode("/",$video->mimeType)[1])[0];
                } else {
                    $type = "Unknown";
                }

                if(isset($video->qualityLabel) && $video->qualityLabel) {
                    $quality = $video->qualityLabel;
                } else {
                    $quality = "Unknown";
                }
                
                // $id = download_instagram_content($url);
            }

            $id = sendPhoto(
                $from_id,
                $thumbnail,
                utf8_encode($caption),
                ['inline_keyboard' => [
                    [['text' => "لینک دانلود به زودی ... ♥️", 'callback_data' => 'none']]
                ]]
            );

            if (isset($id)) {
                live_statistics($link, $type = 'youtube', $from_id);
                $db->table('user')->update(['last_download_date'=>$now])->where([['tid', '=', $from_id]])->execute();
            }

            $youtube_download = intval($config['youtube_download']) + 1;
            $db->table("config")->update(["youtube_download" => $youtube_download])->where([['id', '=', $config['id']]])->execute();
        } catch (Exception $ex) {
            $db->table('user')->update(['last_download_date'=>$now])->where([['tid', '=', $from_id]])->execute();
            show_error("⚠️ $ex", true);
        }
    }
}