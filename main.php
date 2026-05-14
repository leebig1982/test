<?php
$today = date('Ymd');
$todayTimestamp = strtotime($today);
$todayW = (int)date('w', $todayTimestamp);
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

$tradeBase = 100;//基本交易量要N張以上
$volMax = 2;//成交量N倍以上
$todayList = getMarketData($today, $tradeBase);
if (empty($todayList)) {
    echo sprintf('查無今日(%s)資料',$today);
    exit();
}

$yesterdayList = getMarketData($yesterday, $tradeBase);
if (empty($yesterdayList)) {
    echo sprintf('查無前一交易日(%s)資料',$yesterday);
    exit();
}

echo sprintf('====%s~%s[上市]成交%s張以上|成交量%s倍以上====', $today, $yesterday, $tradeBase, $volMax).PHP_EOL;

$result = [];
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
    $volOdds = (float)number_format($volNum, 2);

    //漲幅%=今日收盤-昨日收盤 * 100
    $p = $todayClose - $yesterdayClose;
    $priceNum = $p / $yesterdayClose * 100;
    $pricePercent = (float)number_format( $priceNum, 2);

    if ($volNum >= $volMax) {
        $result[] = [
            'name' => $name,
            'vol_odds'  => $volOdds,
            'price_percent'=> $pricePercent,
        ];
    }
}

usort($result, function($a, $b){
    if ($a['vol_odds'] == $b['vol_odds']) {
        return 0;
    }
    return ($a['vol_odds'] < $b['vol_odds']) ? 1 : -1;
});
foreach($result as $row) {
    echo sprintf('%s | %s倍 | %s%%', $row['name'], $row['vol_odds'], $row['price_percent']).PHP_EOL;
}


function getMarketData($date, $tradeBase = 100) {
    $tradeLimit = $tradeBase * 1000;
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
        if ($volume < $tradeLimit) {
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
