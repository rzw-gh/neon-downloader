<?php
use GuzzleHttp\Psr7\Utils;

function getVideoInfo($video_id){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://www.youtube.com/youtubei/v1/player?key=' . YT_API_KEY);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt(
        $ch, CURLOPT_POSTFIELDS, '{
            "context": {
                "client": {
                    "hl": "en",
                    "clientName": "WEB",
                    "clientVersion": "2.20210721.00.00",
                    "clientFormFactor": "UNKNOWN_FORM_FACTOR",
                    "clientScreen": "WATCH",
                    "mainAppWebInfo": {
                        "graftUrl": "/watch?v='.$video_id.'",
                    }
                },
                "user": {
                    "lockedSafetyMode": false
                },
                "request": {
                    "useSsl": true,
                    "internalExperimentFlags": [],
                    "consistencyTokenJars": []
                }
            },
            "videoId": "'.$video_id.'",
            "playbackContext": {
                "contentPlaybackContext": {
                    "vis": 0,
                    "splay": false,
                    "autoCaptionsDefaultOn": false,
                    "autonavState": "STATE_NONE",
                    "html5Preference": "HTML5_PREF_WANTS",
                    "lactMilliseconds": "-1"
                }
            },
            "racyCheckOk": false,
            "contentCheckOk": false
        }'
    );
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');

    $headers = array();
    $headers[] = 'Content-Type: application/json';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        show_error("⚠️ " . curl_error($ch), true);
    }
    curl_close($ch);
    return $result;
}