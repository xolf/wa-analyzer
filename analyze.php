<?php

if(!isset($argv[1])) {
    die("Usage: php analyze.php [SOURCE-FILE]".PHP_EOL);
}

$dateFormat = 'W';

if(!isset($argv[2])) {
    $argv[2] = "week";
}

if($argv[2] == "week") $argv[2] = "W#Y";
if($argv[2] == "dayofweek" || $argv[2] == "dow") $argv[2] = "D";
if($argv[2] == "month") $argv[2] = "m.Y";
if($argv[2] == "monthofyear") $argv[2] = "F";
if($argv[2] == "year") $argv[2] = "Y";

$word_usage_total = [];
$participants = [];

$messages = $previous_message = [];

$maxMessagesPerTimeline = 0;

$handle = fopen($argv[1], "r");
if ($handle) {
    while (($line = fgets($handle)) !== false) {
        $line_splitted = explode(" - ", $line);
        $datetime = array_shift($line_splitted);
        $message = implode(" - ", $line_splitted);
        $message = explode(": ", $message);
        $user = array_shift($message);
        $message = implode(": ", $message);
        $message = trim($message);
        $message = trim($message, PHP_EOL);
        if($message == "") continue;

        $datetime = str_replace(
            [".13,",   ".14,",   ".15,",   ".16,",   ".17,",   ".18,",   ".19,",   ".20,"],
            [".2013", ".2014", ".2015", ".2016", ".2017", ".2018", ".2019", ".2020"],
            $datetime);
        $timestamp = strtotime($datetime);


        $message_data = [
            'user' => $user,
            'datetime' => $datetime,
            'timestamp' => $timestamp,
            'text' => $message
        ];

        if(!$timestamp) {
            $messages[count($messages)-1]['text'] .= (PHP_EOL.$line);
            continue;
        }

        $messages[] = $message_data;
        $previous_message = $message_data;
    }

    fclose($handle);
} else {
    die("Some error happens");
}

foreach ($messages as $message) {
    if(!isset($participants[$message['user']])) $participants[$message['user']] = [
        'words' => [],
        'total_messages' => 0, 'total_words' => 0, 'total_length' => 0, 'media' => 0
    ];

    if($message['text'] == '<Medien ausgeschlossen>') {
        $participants[$message['user']]['media']++;
        continue;
    }

    $participants[$message['user']]['total_length'] += mb_strlen($message['text']);

    $words = mb_strtolower($message['text']);
    $words = str_replace(["\n", '.', '?', '!', ',', '/', '(', ')', '_', '*', '[', ']', '='], " ", $words);
    $words = preg_replace('/\s+/', ' ', $words);
    $words = explode(" ", $words);
    $participants[$message['user']]['total_words'] += count($words);
    foreach ($words as $word) {
        if(trim($word) == '') continue;

        if(!isset($word_usage_total[$word])) $word_usage_total[$word] = 1;
        else $word_usage_total[$word]++;

        if(!isset($participants[$message['user']]['words'][$word])) $participants[$message['user']]['words'][$word] = 1;
        else $participants[$message['user']]['words'][$word]++;
    }

    $participants[$message['user']]['total_messages']++;

    if(!isset($participants[$message['user']]['timeline'])) $participants[$message['user']]['timeline'] = [];
    $timeline = $participants[$message['user']]['timeline'];

    $datetime = new DateTime($message['datetime']);
    $dateFormatted = $datetime->format($argv[2]);

    if(!isset($timeline[$dateFormatted])) $timeline[$dateFormatted] = ['text' => $dateFormatted, 'messages' => 0];
    $timeline[$dateFormatted]['messages']++;
    if($timeline[$dateFormatted]['messages'] > $maxMessagesPerTimeline) $maxMessagesPerTimeline = $timeline[$dateFormatted]['messages'];

    $participants[$message['user']]['timeline'] = $timeline;
}

foreach ($participants as $participant) {
    if(!isset($participants['All together'])) {
        $participants['All together'] = $participant;
    } else {
        $together = $participants['All together'];
        $together['media'] += $participant['media'];
        $together['total_messages'] += $participant['total_messages'];
        $together['total_length'] += $participant['total_length'];
        $together['total_words'] += $participant['total_words'];

        if(!isset($together['words'])) $together['words'] = [];
        foreach ($participant['words'] as $word => $used) {
            if(!isset($together['words'][$word])) $together['words'][$word] = $used;
            else $together['words'][$word] += $used;
        }

        if(isset($participants['timeline'])) {
            foreach ($participants['timeline'] as $date => $timeline) {
                if(!isset($participants[$date])) $participants[$date] = $timeline;
                else $participants[$date]['messages'] += $timeline['messages'];
            }
        }

        $participants['All together'] = $together;
    }
}


$first_message_timestamp = $messages[0]['timestamp'];
$last_message_timestamp = $messages[count($messages)-1]['timestamp'];

$timespan = $last_message_timestamp - $first_message_timestamp;
$timespan_minutes = round($timespan / 60);
$timespan_hours = round($timespan_minutes / 60);
$timespan_days = round($timespan_hours / 24);
$timespan_weeks = round($timespan_days / 7, 1);
$timespan_years = round($timespan_weeks / 52, 2);


function relative_stat($my, $total) {
    if($my == $total) return '';

    $percent = (round($my/$total, 4)*100);
    return ' - ('.$percent.'%)';
}

$together = $participants['All together'];
echo PHP_EOL;
echo 'Participants:'.PHP_EOL;
foreach ($participants as $participant => $usage_data) {
    if($usage_data['total_messages'] == 0) continue;

    echo '+-------------------------'.PHP_EOL;
    echo '| '.$participant.PHP_EOL;
    echo '+-------------------------'.PHP_EOL;

    $media = $usage_data['media'];
    $media_together = $together['media'];
    echo '| Sent media: '.$media." ".relative_stat($media, $media_together).PHP_EOL;

    $total_messages = $usage_data['total_messages'];
    $total_messages_together = $together['total_messages'];
    echo '| Total messages: '.$total_messages." ".relative_stat($total_messages, $total_messages_together).PHP_EOL;

    $avg_message_per_day = round($usage_data['total_messages']/$timespan_days, 2);
    $avg_message_per_day_together = round($together['total_messages']/$timespan_days, 2);
    echo '| Avg. messages per day: '.$avg_message_per_day." ".relative_stat($avg_message_per_day, $avg_message_per_day_together).PHP_EOL;

    $total_length = $usage_data['total_length'];
    $total_length_together = $together['total_length'];
    echo '| Total letters: '.$total_length." ".relative_stat($total_length, $total_length_together).PHP_EOL;

    $avg_message_length = round($usage_data['total_length']/$usage_data['total_messages'], 2);
    $avg_message_length_together = round($together['total_length']/$together['total_messages'], 2);
    echo '| Avg. message length: '.$avg_message_length." ".relative_stat($avg_message_length, $avg_message_length_together).PHP_EOL;

    $total_words = $usage_data['total_words'];
    $total_words_together = $together['total_words'];
    echo '| Total words: '.$total_words." ".relative_stat($total_words, $total_words_together).PHP_EOL;

    $different_words = count($usage_data['words']);
    $different_words_together = count($together['words']);
    echo '| Different words: '.$different_words." ".relative_stat($different_words, $different_words_together).PHP_EOL;

    asort($usage_data['words']);
    $words = array_reverse($usage_data['words']);
    echo '| Most used words:'.PHP_EOL;
    $place = 1;
    foreach (array_slice($words, 0, 20) as $word => $used) {
        $used_together = $together['words'][$word];
        echo "|   ".$place.". ".$word.": ".$used." times ".relative_stat($used, $used_together).PHP_EOL;
        $place++;
    }
    echo '|'.PHP_EOL;

    echo '| Timeline: (total messages)'.PHP_EOL;
    foreach ($usage_data['timeline'] as $date => $step) {
        $points = (int)round($step['messages']/$maxMessagesPerTimeline*40);
        echo '| '.$step['text'].' '.str_repeat("#", $points).' ('.$step['messages'].')'.PHP_EOL;
    }

    echo '|'.PHP_EOL;
}



echo PHP_EOL.PHP_EOL;
echo '- - - - - - - - - - - - - - - - - - -'.PHP_EOL;
echo 'Analyzed timespan:'.PHP_EOL;
echo '  => Minutes: '.$timespan_minutes.PHP_EOL;
echo '  => Hours: '.$timespan_hours.PHP_EOL;
echo '  => Days: '.$timespan_days.PHP_EOL;
echo '  => Weeks: '.$timespan_weeks.PHP_EOL;
echo '  => Years: '.$timespan_years.PHP_EOL;

