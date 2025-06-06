<?php

if (!function_exists('GenerateUuid')) {
	function GenerateUuid($model, $prefix=""){
		$lastIdx = 0;
		$last_entry = $model::latest()->first();
		if($last_entry){
			$lastIdx = $last_entry->idx;
		}
		if($prefix != ""){
			$newUUid = $prefix.'-'.Str::uuid().'-'.($lastIdx+1);
		}else{
			$newUUid = Str::uuid().'-'.($lastIdx+1);
		}
		$getUuid = $model::where([
			['id', $newUUid],
		])->first();
		if($getUuid){
			return GenerateUuid($model);
		}else{
			return $newUUid;
		}
	}
}

if (!function_exists('ChecRefkAgeLab')) {
	function ChecRefkAgeLab($age, $refAge){
		$age = str_replace(' ', '', $age);
		$refAge = str_replace(' ', '', $refAge);

		$return = false;

		if (strpos($refAge, '<') !== false) {
			$array = explode('<', $refAge);
			$first = $array[1];
			if($age < $first){
				$return = true;
			}
		}else
		if (strpos($refAge, '=<') !== false) {
			$array = explode('=<', $refAge);
			$first = $array[1];

			if($age <= $first){
				$return = true;
			}
		}else
		if (strpos($refAge, '>') !== false) {
			$array = explode('>', $refAge);
			$first = $array[1];

			if($age > $first){
				$return = true;
			}
		}else
		if (strpos($refAge, '>=') !== false) {
			$array = explode('>=', $refAge);
			$first = $array[1];

			if($age >= $first){
				$return = true;
			}
		}else
		if (strpos($refAge, '-') !== false) {
			$array = explode('-', $refAge);
			$first = $array[0];
			$second = $array[1];

			if($age >= $first && $age <= $second){
				$return = true;
			}
		}else{
			if($age <= $refAge){
				$return = true;
			}
		}


		return $return;
	}
}

if (!function_exists('CheckAnomaliLab')) {
	function CheckAnomaliLab($result, $ref){
		$ref = str_replace(' ', '', $ref);
		$result = str_replace(' ', '', $result);
		$return = false;

		if(strpos(strtoupper($result), 'NEGATIF') !== false) {
			if(strtoupper($ref) == 'POSITIF'){
				$return = true;
			}
		}else if(strpos(strtoupper($result), 'POSITIF') !== false) {
			if(strtoupper($ref) == 'NEGATIF'){
				$return = true;
			}
		}else{
			if (strpos($ref, '<') !== false) {
				$array = explode('<', $ref);
				$first = $array[1];
				if($result >= $first){
					$return = true;
				}
			}
			if (strpos($ref, '<=') !== false) {
				$array = explode('<=', $ref);
				$first = $array[1];
				if($result > $first){
					$return = true;
				}
			}
			if (strpos($ref, '>') !== false) {
				$array = explode('>', $ref);
				$first = $array[1];
				if($result <= $first){
					$return = true;
				}
			}
			if (strpos($ref, '>=') !== false) {
				$array = explode('>=', $ref);
				$first = $array[1];
				if($result < $first){
					$return = true;
				}
			}
			if (strpos($ref, '-') !== false) {
				$array = explode('-', $ref);
				$first = $array[0];
				$second = $array[1];
				if($result < $first OR $result > $second){
					$return = true;
				}
			}
			// if (strpos($ref, '–') !== false) {
			// 	$array = explode('–', $ref);
			// 	$first = $array[0];
			// 	$second = $array[1];
			// 	if($result <= $first OR $result >= $second){
			// 		$return = true;
			// 	}
			// }
		}

		return $return;
	}
}

if (!function_exists('LimitText')) {
	function LimitText($text, $limit=200){
		if(strlen($text) <= $limit){
			return $text;
		}else{
			$text = substr($text,0,$limit) . '...';
			return $text;
		}
	}
}

if (!function_exists('clean')) {
    function clean($text){
		$str = preg_replace("/[^A-Za-z& ]/", "", $text);
		$str = strtolower($str);
		$str = preg_replace('/\s/', '-', $str);
		return $str;
	}
}

if (!function_exists('aliases')){
    function aliases($text){
		$str = preg_replace('/\s/', '-', $text);
		$str = strtolower($str);
		return $str;
	}
}

if (!function_exists('ViewMoney')){
	function ViewMoney($money){
		return number_format($money, 2, ",", ".");
	}
}

if (!function_exists('ViewDecimal')){
	function ViewDecimal($number){
		return number_format($number, 2, ",", ".");
	}
}

if (!function_exists('StoreDecimal')){
	function StoreDecimal($num){
		$is_numeric = (is_numeric($num) && stripos($num,'.') !== false);
		if(!$is_numeric){
			$num = preg_replace("/[^0-9,]/", "", $num);
			if((strpos($num, '%') !== false)){
				$num = str_replace('%', '', $num);
			}
			if((strpos($num, 'Rp.') !== false)){
				$array = explode('Rp.', $num);
				$num = $array[1];
			}
			if((strpos($num, 'Rp') !== false)){
				$array = explode('Rp', $num);
				$num = $array[1];
			}
			if (strpos($num, ',') !== false) {
				$array = explode(',', $num);
				$first = $array[0];
				$second = $array[1];
				$str_replace = '.';
				if( strpos($first, $str_replace) !== false ) {
					return str_replace($str_replace, '', $first).".".$second;
				}else{
					return (double)($first.".".$second);
				}
			}else{
				if (strpos($num, '.') !== false) {
					$str_replace = '.';
					if( strpos($num, $str_replace) !== false ) {
						$num = str_replace($str_replace, '', $num);
					}
				}
				return (double)$num;
			}
		}else{
			return $num;
		}
	}
}

if (!function_exists('StoreMoney')){
	function StoreMoney($money){
		$is_numeric = (is_numeric($money) && stripos($money,'.') !== false);
		if(!$is_numeric){
			$money = preg_replace("/[^0-9,]/", "", $money);
			if((strpos($money, 'Rp.') !== false)){
				$array = explode('Rp.', $money);
				$money = $array[1];
			}
			if((strpos($money, 'Rp') !== false)){
				$array = explode('Rp', $money);
				$money = $array[1];
			}
			if (strpos($money, ',') !== false) {
				$array = explode(',', $money);
				$first = $array[0];
				$second = $array[1];
				$str_replace = '.';
				if( strpos($first, $str_replace) !== false ) {
					return str_replace($str_replace, '', $first).".".$second;
				}else{
					return (double)($first.".".$second);
				}
			}else{
				if (strpos($money, '.') !== false) {
					$str_replace = '.';
					if( strpos($money, $str_replace) !== false ) {
						$money = str_replace($str_replace, '', $money);
					}
				}
				return (double)$money;
			}
		}else{
			return $money;
		}
	}
}

if (!function_exists('ViewPercent')){
	function ViewPercent($val){
		return number_format($val, 2, ",", ".")."%";
	}
}

if (!function_exists('StorePercent')){
	function StorePercent($val){
		if((strpos($val, '%') !== false)){
			$val = str_replace('%', '', $val);
		}
		if (strpos($val, ',') !== false) {
			$array = explode(',', $val);
			$first = $array[0];
			$second = $array[1];
			$str_replace = ',';
			if( strpos($val, $str_replace) !== false ) {
				return str_replace($str_replace, '', $first).".".$second;
			}else{
				return $first.".".$second;
			}
		}else{
			return $val;
		}
	}
}

if (!function_exists('romanNumerals')){
	function romanNumeral($num){
		$n = intval($num);
		$res = '';
		$roman_numerals = array(
			'M'  => 1000,
			'CM' => 900,
			'D'  => 500,
			'CD' => 400,
			'C'  => 100,
			'XC' => 90,
			'L'  => 50,
			'XL' => 40,
			'X'  => 10,
			'IX' => 9,
			'V'  => 5,
			'IV' => 4,
			'I'  => 1
		);
		foreach ($roman_numerals as $roman => $number){
			$matches = intval($n / $number);
			$res .= str_repeat($roman, $matches);
			$n = $n % $number;
		}
		return $res;
	}
}

if (!function_exists('CodeNumber')){
	function CodeNumber($numb, $length=4){
		if (strlen($numb) <= $length) {
			return sprintf('%0'.$length.'d',$numb);
		}else{
			return $numb;
		}
	}
}



if (!function_exists('usia')){
	function usia($tanggal_lahir, $date = '', $get = 'full') {
		$date1 = new DateTime($tanggal_lahir);
		if($date == ''){
			$date2 = new DateTime(date('Y-m-d'));
		}else{
			$date2 = new DateTime($date);
		}
		$interval = $date1->diff($date2);
		if($get == 'full'){
			return $interval->y . " Thn, " . $interval->m." Bln, ".$interval->d." Hari";
		}else{
			return [$interval->y, $interval->m, $interval->d];
		}
	}
}

if (!function_exists('dayNameLocal')){
	function dayNameLocal($D){
		//$day = date ("D");
		switch($D){
			case 'Sun':
				$dayname = "Minggu";
			break;

			case 'Mon':
				$dayname = "Senin";
			break;

			case 'Tue':
				$dayname = "Selasa";
			break;

			case 'Wed':
				$dayname = "Rabu";
			break;

			case 'Thu':
				$dayname = "Kamis";
			break;

			case 'Fri':
				$dayname = "Jumat";
			break;

			case 'Sat':
				$dayname = "Sabtu";
			break;

			default:
				$dayname = "Tidak di ketahui";
			break;
		}
		return $dayname;
	}
}

if (!function_exists('terbilang')){
	function terbilang($x){
		if($x<0){
			$poin =  "";
			$hasil = "minus ".trim(penyebut($x));
		}else{
			$poin = trim(tkoma($x));
			$hasil = trim(penyebut($x));
		}

		if($poin){
			$hasil = $hasil." Koma ".$poin;
		}else{
			$hasil = $hasil;
		}
		return $hasil;
	}
}

function penyebut($x){
	$x = abs($x);
	$angka = array ("","Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas");
	$temp = "";

	if($x < 12){
		$temp = " ".$angka[$x];
	}else if($x<20){
		$temp = penyebut($x - 10)." Belas";
	}else if ($x<100){
		$temp = penyebut($x/10)." Puluh". penyebut($x%10);
	}else if($x<200){
		$temp = " Seratus".penyebut($x-100);
	}else if($x<1000){
		$temp = penyebut($x/100)." Ratus".penyebut($x%100);
	}else if($x<2000){
		$temp = " seribu".penyebut($x-1000);
	}else if($x<1000000){
		$temp = penyebut($x/1000)." Ribu".penyebut($x%1000);
	}else if($x<1000000000){
		$temp = penyebut($x/1000000)." Juta".penyebut($x%1000000);
	}else if($x<1000000000000){
		$temp = penyebut($x/1000000000)." Milyar".penyebut($x%1000000000);
	}
	return $temp;
}

function tkoma($x){
	$str = stristr($x,".");
	$ex = explode('.',$x);

	$a = 0;
	$a2 = 0;
	if( strpos($str, '.') !== false ) {
		if(($ex[1]/10) >= 1){
			$a = abs($ex[1]);
		}
		$a2 = $ex[1]/10;
	}

	$string = array ("Nol","Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas");
	$temp = "";

	$pjg = strlen($str);
	$i =1;
	if($a>=1 && $a< 12){
		$temp .= " ".$string[$a];
	}else if($a>12 && $a < 20){
		$temp .= penyebut($a - 10)." Belas";
	}else if ($a>20 && $a<100){
		$temp .= penyebut($a / 10)." Puluh". penyebut($a % 10);
	}else{
		if($a2<1){
			while ($i<$pjg){
				$char = substr($str,$i,1);
				$i++;
				$temp .= " ".$string[$char];
			}
		}
	}
	return $temp;
}



if (!function_exists('getTimeDiff')){
    function getTimeDiff($dtime,$atime){
        $depDateTime = new DateTime($dtime);
        $arrDateTime = new DateTime($atime);
        $interval = $depDateTime->diff($arrDateTime);
        $years = $interval->y;
        $months = $interval->m;
        $days = $interval->d;
        $hours = $interval->h;
        $mins = $interval->i;
        $secs = $interval->s;
        $result = '';
        if($years > 0){
            $result .= $years . ' Tahun ';
        }
        if($months > 0){
            $result .= $months . ' Bulan ';
        }
        if($days > 0){
            $result .= $days . ' Hari ';
        }
        if($hours > 0){
            $result .= $hours . ' Jam ';
        }
        if($mins > 0){
            $result .= $mins . ' Menit ';
        }
        // if($secs > 0){
        //     $result .= $secs . ' Detik ';
        // }
        return trim($result);
    }
}

if (!function_exists('dateDiff')){
	function dateDiff ($d1, $d2) {
		//return on days
		return round(abs(strtotime($d1) - strtotime($d2))/86400);
	}
}
