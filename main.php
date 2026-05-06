<?php
$today = date('Ymd');
$todayTimestamp = strtotime($today);
$todayW = date('w', $todayTimestamp);
if (in_array($todayW, [0,6])) {
    echo '周末不開盤';
    exit();
}

//周一
if ($todayW === 1) {
    $yesterday = date('Ymd', strtotime('last friday', $todayTimestamp));
}else{
    $yesterday = date('Ymd', strtotime('-1 day', $todayTimestamp));
}

$volMax = 2;//成交量N倍以上
$priceMin = 5;//股價n%以下
$todayList = getMarketData($today);

if (empty($todayList)) {
    echo sprintf('查無今日(%s)資料',$today);
    exit();
}

$yesterdayList = getMarketData($yesterday);
if (empty($yesterdayList)) {
    echo sprintf('查無前一交易日(%s)資料',$yesterday);
    exit();
}

echo sprintf('====%s~%s 成交量%s倍以上 & 漲幅%s%%以下 ====', $today, $yesterday, $volMax, $priceMin).PHP_EOL;
foreach ($todayList as $id => $row) {
    if (!isset($yesterdayList[$id])) {
        continue;
    }

    $todayVolume = $row['volume'] ?? 0;
    $todayClose = $row['close'] ?? 0;
    if (empty($todayVolume) || empty($todayClose)) {
        continue;
    }

    $name = $row['name'];
    $yesterdayVolume = $yesterdayList[$id]['volume'] ?? 0;
    $yesterdayClose = $yesterdayList[$id]['close'] ?? 0;
    //成交量倍數=今日成交/昨日成交
    $volNum = $todayVolume / $yesterdayVolume;
    $volText = number_format($volNum, 2).'倍';

    //漲幅%=今日收盤-昨日收盤 * 100
    $p = $todayClose - $yesterdayClose;
    $priceNum = $p / $yesterdayClose * 100;
    $priceText = number_format( $priceNum, 2).'%';

    if ($volNum >= $volMax && $priceNum <= $priceMin) {
        echo sprintf('%s | %s | %s', $name, $volText, $priceText).PHP_EOL;
    }
}

function getMarketData($date, $limit = 100000) {
    $url = "https://www.twse.com.tw/exchangeReport/MI_INDEX?response=json&date={$date}&type=ALLBUT0999";
    $json = file_get_contents($url);
    if (!$json) {
        return [];
    }

    $res = json_decode($json, true);
    if (!isset($res['tables'])) {
        return [];
    }

    //每日收盤行情
    $allStock = [];
    foreach($res['tables'] as $row) {
        $title = $row['title'] ?? '';
        if (strpos($title,'每日收盤行情') !== false) {
            $allStock = $row['data'] ?? [];
            break;
        }
    }


    $map = [];
    foreach ($allStock as $row) {
        $id = $row[0];
        $volume = (float)str_replace(',', '', $row[2]);
        $close = (float)str_replace(',', '', $row[8]);

        if ($volume < $limit) {
            continue;
        }

        $map[$id] = [
            'name'   => sprintf('%s(%s)',$row[1],$row[0]),
            'volume' => $volume,
            'close'  => $close
        ];
    }
    return $map;
}

?>