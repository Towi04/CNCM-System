<?php

use Carbon\Carbon;
use Carbon\CarbonInterface;

if (!function_exists('countDaysInMonth')) {

    /**
     * Obtiene el numero de dias en un mes especificado
     *
     * @param int|Carbon $month
     * @param int $numberDay
     * @return integer
     */
    function countDaysInMonth($month = null, int $numberDay):int
    {

        if($numberDay == 7){
            // EL DOMINGO LO CONSIDERAN COMO 0 EN CARBON
            $numberDay = 0;
        }


        if(is_int($month)){
            $today = Carbon::today()->setMonth($month)->startOfMonth();
        }else{
            if($month instanceof CarbonInterface ){
                $today = $month;
            }else{
                $today = Carbon::today()->startOfMonth();
            }    
        }

        

        $nextmonth = $today->clone()->endOfMonth();
       

        $days = [
            Carbon::MONDAY      => 'Monday',
            Carbon::TUESDAY     => 'Tuesday',
            Carbon::WEDNESDAY   => 'Wednesday',
            Carbon::THURSDAY    => 'Thursday',
            Carbon::FRIDAY      => 'Friday',
            Carbon::SATURDAY    => 'Saturday',
            Carbon::SUNDAY      => 'Sunday',
        ];
        
        if(!array_key_exists($numberDay,$days)){
            return 0;
        }

        $day = $days[$numberDay];
        $methodName = "is{$day}";
        
        return $today->diffInDaysFiltered(function($date)use($methodName){
            return $date->$methodName();
        },$nextmonth);
    }
}
