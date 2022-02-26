#!/usr/bin/php -q
<?php

$tic  = isset($argv[1]) !== FALSE ? $argv[1] : NULL; 
$date = isset($argv[2]) !== FALSE ? $argv[2] : NULL; 
new TicketPrice($tic, $date);

/**
 * 
 * チケット価格の計算プログラム
 * 
 * ターミナル画面上よりプログラムファイルが配置されているディレクトリに移動し、
 * 下記の通りコマンドを実行実行
 * > php ticket_price_calculation.php {param1} {param2}
 * param1 : チケット入力枚数（大人、子供、シニアの順に入力します）
 * 入力例）"1,2,3"、"0,1,2"...
 * param2 : 指定日付（入力しない場合はプログラム実行時間が指定されます）
 * 入力例）"2022/01/01 09:00:00"...
 * 
 */
class TicketPrice{
   
    // チケット単価
    const ADULT_UNIT_PRICE  = 1000; // 大人
    const CHIRD_UNIT_PRICE  = 500;  // 子供
    const SENIOR_UNIT_PRICE = 800;  // シニア

    // オプション名
    const OPT_GROUP      = "団体割引";
    const OPT_GRP_DSCNT  = 0.9;
    const OPT_NIGHT      = "平日夜間割引";
    const OPT_NGHT_DSCNT = 300;
    const OPT_HOLIDAY    = "土日割増料金";
    const OPT_HLDY_EXTRA = 1.15;
    
    // プロパティ
    private $ticketStr;  // 枚数文字列（カンマ区切り）
    private $adult;      // 枚数（大人）
    private $child;      // 枚数（子供）
    private $senior;     // 枚数（シニア）
    private $dateStr;    // 入力日付（文字列）

    private $dateTime;   // 日付
    private $option;     // 適用オプション

    private $error;      // エラーメッセージ
    
    // コンストラクタ
    public function __construct(string $ticketStr=NULL, string $dateStr=NULL){
       
        $this->setTickets($ticketStr); // 入力値のセット（枚数）
        $this->setDateTime($dateStr);  // 入力値のセット（日付）
        $this->getOptions();           // 適用オプション判定
        
        echo $this->getMsg(). "\n"; // 結果画面出力
        
    }
    
    // メッセージ出力
    public function getMsg (){
        
        // エラーを含む場合は詳細を出力しない
        if(isset($this->error)){
            $msg = "【ERROR】DETAIL：". $this->error;
            
        }else{    
            $msg = "【SUCCESS】DETAIL：";
            $msg .= sprintf("大人：%d 枚、", $this->adult);
            $msg .= sprintf("子供：%d 枚、", $this->child);
            $msg .= sprintf("シニア：%d 枚、", $this->senior);
            $msg .= sprintf("購入日時：%s です。", $this->dateTime);
            
            $msg .= "\n";
            
            // 購入金額を出力、オプションありの場合は割引後の金額を出力
            $msg .= sprintf("購入金額は【%s 円】です。", $this->getTotalPrice());
            if($this->getTotalPrice() !== $this->getTotalPrice(TRUE)){
                $msg .= sprintf("オプション適用後の購入金額は【%s 円】です。", $this->getTotalPrice(TRUE));            
                $msg .= "\n";
                
            }
            $d = $this->option == "" ? "ありませんでした。" : $this->option. "";
            $msg .= sprintf("適用オプション：%s", $d);    
            
        }
        
        $msg .= "\n";
        
        return $msg; 
    }
    
    // 合計人数計算（子供は0.5人扱い）
    public function getTotalUsers(){
        return $this->adult + ($this->child * 0.5) + $this->senior;
    }
    
    public function getPrice($type, $extra=NULL, $discount=NULL){
        switch ($type) {
            case 'ADULT':
                return $this->adult * (self::ADULT_UNIT_PRICE + $extra - $discount);
                break;
            case 'CHILD':
                return $this->child * (self::CHIRD_UNIT_PRICE + $extra - $discount);
                break;
            case 'SENIOR':
                return $this->senior * (self::SENIOR_UNIT_PRICE + $extra - $discount);
                break;
            default:
                return FALSE;
                break;
        }

    }

    // 価格を計算
    // $isOption=TRUE：オプション適用
    public function getTotalPrice($isOption=NULL){
        $ret = 0;

        // チケット一枚あたりに影響するオプション
        if($isOption == TRUE && strpos($this->option, self::OPT_NIGHT) !== FALSE){
            $d = self::OPT_NGHT_DSCNT;
            $ret =  $this->getPrice("ADULT", NULL, $d);
            $ret += $this->getPrice("CHILD", NULL, $d);
            $ret += $this->getPrice("SENIOR", NULL, $d);
            
        }else{
            $ret =  $this->getPrice("ADULT");
            $ret += $this->getPrice("CHILD");
            $ret += $this->getPrice("SENIOR");

        }

        // 総額に影響するオプション
        if($isOption == TRUE){
            $ret = $this->clacTotalOptions($ret);
        }
        
        return $ret;
        
    }

    // 総額に影響するオプションを適用
    public function clacTotalOptions($totalPrice){
        
        $finalPrice = $totalPrice;
        if(strpos($this->option, self::OPT_GROUP) !== FALSE){
            $finalPrice = ($finalPrice * 0.9);
        }
        
        if(strpos($this->option, self::OPT_HOLIDAY) !== FALSE){
            $finalPrice = ($finalPrice * 1.15);
        }
        
        return $finalPrice;
    }

    // 平日夜間かどうか
    public function isWeekDayNight(){
        $h = date("H", strtotime($this->dateTime));
    	return ($h < 9 || $h > 17) ? TRUE : FALSE;
    }

    // 土日かどうか
    public function isHoliday(){
        $timestamp = strtotime($this->dateTime);
        $date = date('w', $timestamp);
        return ($date == 6 || $date == 0) ? TRUE : FALSE;
    }
    
    // 適用オプションを反映
    private function getOptions(){
        
        $options = array();
        
        // 団体割引
        if($this->getTotalUsers() > 10){
            $options[] = "団体割引";
        }
        
        // 平日夜間割引
        if($this->isWeekDayNight() == TRUE){
            $options[] = "平日夜間割引";    
        }
        
        // 土日割増
        if($this->isholiday()){
            $options[] = "土日割増料金";    
        }

        $this->option = implode(",", $options);
        
    }

    // チケットの枚数をセット
    public function setTickets($tickets){

        $arr = explode(",", trim($tickets));
        $this->adult  = isset($arr[0]) ? (int)$arr[0] : 0;
        $this->child  = isset($arr[1]) ? (int)$arr[1] : 0;
        $this->senior = isset($arr[2]) ? (int)$arr[2] : 0;

        // 枚数に有効値が含まれるか、0枚ではないか
        if($tickets == NULL || $this->adult == 0 && $this->child == 0 && $this->senior == 0){
            $this->error = "枚数入力値に有効な値が含まれていません。";
            return;
        }

        // カンマ区切り形式で入力されているか
        if(!preg_match("/^[0-9]{1,},[0-9]{1,},[0-9]{1,}+$/",$tickets)){
            $this->error = "枚数入力値は、【XX,XX,XX】の形式で入力して下さい。";
            return;
        }
        
    }
    
    // 日付入力文字列のセット、バリデーション
    public function setDateTime($input=NULL){
        
        date_default_timezone_set('Asia/Tokyo');
        
        try {
            if($input != NULL){                
                if($this->checkDatetimeFormat($input) == FALSE){
                    $this->error = "日付入力情報を【XXXX/XX/XX XX:XX:XX】の形式、もしくは空で入力して下さい。";
                    return;
                }
                
            }
            $d = new DateTime($input);
            $this->dateTime = $d->format('Y-m-d H:i:s');
        } catch (Exception $ex){
            $this->error = "日付情報が設定出来ませんでした。";
            return;
        }
    }
    
    // 日付の入力形式チェック
    public function checkDatetimeFormat($datetime){
        return $datetime === date("Y-m-d H:i:s", strtotime($datetime));
    }

}
    
?>
