<?php
    
class CoordinatesConverter {
    
    private static function divide($a)
    {
        // evaluate the string fraction and return a float //	
        $e = explode('/', $a);
        // prevent division by zero //
        if (!$e[0] || !$e[1]) {
            return 0;
        }	else{
            return $e[0] / $e[1];
        }
    }
    
    public static function dms_to_decimal($point)
    {
        if(gettype($point[0]) == 'string')
            $point[0] = CoordinatesConverter::divide($point[0]);
        if(gettype($point[1]) == 'string')
            $point[1] = CoordinatesConverter::divide($point[1]);
        if(gettype($point[2]) == 'string')
            $point[2] = CoordinatesConverter::divide($point[2]);
        
        $d = $point[0] + $point[1]/60 + $point[2]/3600;
        return ($point[3]=='S' || $point[3]=='W') ? $d*=-1 : $d;
    }
    
    public static function decimal_to_dms($decimal, $type = true) {
        //set default values for variables passed by reference
        $degrees = 0;
        $minutes = 0;
        $seconds = 0;
        $direction = 'X';
 
        //decimal must be integer or float no larger than 180;
        //type must be Boolean
        if(!is_numeric($decimal) || abs($decimal) > 180 || !is_bool($type)) {
            return false;
        }
    
        //inputs OK, proceed
        //type is latitude when true, longitude when false
    
        //set direction; north assumed
        if($type && $decimal < 0) { 
            $direction = 'S';
        }
        elseif(!$type && $decimal < 0) {
            $direction = 'W';
        }
        elseif(!$type) {
            $direction = 'E';
        }
        else {
            $direction = 'N';
        }
    
        //get absolute value of decimal
        $d = abs($decimal);
        //get degrees
        $degrees = floor($d);
        //get seconds
        $seconds = ($d - $degrees) * 3600;
        //get minutes
        $minutes = floor($seconds / 60);
        //reset seconds
        $seconds = floor($seconds - ($minutes * 60));
   
        return array($degrees,$minutes,$seconds,$direction);
    } 
}

/*
$latitude_decimal = 40.741895;
$longitude_decimal = -73.989308;

$latitude_dms = CoordinatesConverter::decimal_to_dms($latitude_decimal, false);
$longitude_dms = CoordinatesConverter::decimal_to_dms($longitude_decimal, true);

$latitude_decimal_converted = CoordinatesConverter::dms_to_decimal($latitude_dms);
$longitude_decimal_converted = CoordinatesConverter::dms_to_decimal($longitude_dms);

var_dump($latitude_decimal, $longitude_decimal);
var_dump($latitude_dms, $longitude_dms);
var_dump($latitude_decimal_converted, $longitude_decimal_converted);
*/

?>