<?php
  class WorkingHours extends Model{
    protected static $tableName = 'working_hours';
    protected static $columns = [
      'id',
      'user_id',
      'work_date',
      'time1',
      'time2',
      'time3',
      'time4',
      'worked_time',
    ];

    public static function loadFromUserAndDate($userId, $workDate){
      $registry = self::getOne(['user_id' => $userId, 'work_date' => $workDate]);
      if(!$registry){
        $registry = new WorkingHours([
          'user_id' => $userId,
          'work_date' => $workDate,
          'worked_time' => 0,
        ]);
      }
      return $registry;
    }

    public function getNextTime(){
      if(!$this->time1) return 'time1';
      if(!$this->time2) return 'time2';
      if(!$this->time3) return 'time3';
      if(!$this->time4) return 'time4';
      return null;
    }

    public function getActiveClock(){
      $nextTime = $this->getNextTime();
      if($nextTime === 'time1' || $nextTime === 'time3'){
        return 'exitTime';
      }elseif($nextTime === 'time2' || $nextTime === 'time4'){
        return 'workedInterval';
      }else{
        return null;
      }
    }

    public function innout($time){
      $timeColumn = $this->getNextTime();
      if(!$timeColumn){
        throw new AppException("Você já fez os 4 batimentos do dia!");
      }
      $this->$timeColumn = $time;
      $this->worked_time = getSecondsFromDateInterval($this->getWorkedInterval());
      if($this->id){
        $this->update();
      }else{
        $this->insert();
      }
    }

    function getWorkedInterval(){
      [$t1, $t2, $t3, $t4] = $this->getTimes();

      // P de periodo sempre se inicia com a letra P, T 
      // para especificar elementos de tempo com segundos, 
      // minuto e horas. Para dias meses e anos não se 
      // utiliza o T. Seis anos e cinco minutos é 
      // representado por P6YT5M
      $part1 = new DateInterval('PT0S');
      $part2 = new DateInterval('PT0S');

      if($t1) $part1 = $t1->diff(new DateTime(), true);
      if($t2) $part1 = $t1->diff($t2,true);
      if($t3) $part2 = $t3->diff(new DateTime(),true);
      if($t4) $part2 = $t3->diff($t4,true);

      return sumIntervals($part1, $part2);
    }

    function getLunchInterval(){
      [, $t2, $t3,] = $this->getTimes();
      $lunchInterval = new DateInterval('PT0S');

      if($t2) $lunchInterval = $t2->diff(new DateTime(), true);
      if($t3) $lunchInterval = $t2->diff($t3, true);

      return $lunchInterval;
    }

    function getExitTime(){
      [$t1, , ,$t4] = $this->getTimes();
      $workday = new DateInterval('PT8H');

      if(!$t1) {
        return (new DateTimeImmutable())->add($workday);
      } elseif($t4){
        return $t4;
      } else{
        $total = sumIntervals($workday, $this->getLunchInterval());
        return $t1->add($total);
      }
    }

    function getBalance(){
      if(!$this->time1 && !isPastWorkday($this->work_date))
        return '';
      
      $balance = $this->worked_time - DAILY_TIME;

      if($balance === 0) 
        return '-';

      $balanceString = getTimeStringFromSeconds(abs($balance));
      $sign = $this->worked_time >= DAILY_TIME ? '+' : '-';
      return "{$sign}{$balanceString}";
    }

    public static function getAbsentUsers(){
      $today = new DateTime();
      $result = Database::getResultFromQuery("
        SELECT name FROM users
        WHERE end_date is NULL
        AND id NOT IN(
          SELECT user_id FROM working_hours
          WHERE work_date = '{$today->format('Y-m-d')}'
          AND time1 IS NOT NULL
        )
      ");

      $absentUsers = [];
      if($result->num_rows > 0){
        while($row = $result->fetch_assoc()){
          array_push($absentUsers, $row['name']);
        }
      }

      return $absentUsers;
    }

    public static function getWorkedTimeInMonth($yearAndMonth){
      $startDate = getFirstDayOfMonth($yearAndMonth)->format('Y-m-d');
      $endDate = getLastDayOfMonth($yearAndMonth)->format('Y-m-d');
      print_r($endDate);
      $result = static::getResultSetFromSelect([
        'raw' => "work_date BETWEEN '{$startDate}' AND '{$endDate}'"
      ], "sum(worked_time) as sum");
      return $result->fetch_assoc()['sum'];
    }

    public static function getMonthlyReport($userId, $date){
      $registries = [];
      $startDate = getFirstDayOfMonth($date)->format("Y-m-d");
      $endDate = getLastDayOfMonth($date)->format("Y-m-d");

      $result = static::getResultSetFromSelect([
        'user_id' => $userId,
        'raw' => "work_date between '{$startDate}' and '{$endDate}'"
      ]);

      if($result){
        while($row = $result->fetch_assoc()){
         
          $registries[$row['work_date']] = new WorkingHours($row);
        }
      }
      return $registries;
    }

    private function getTimes(){
      $times = [];

      array_push($times, $this->time1 ?  getDateFromString($this->time1) : null);
      array_push($times, $this->time2 ?  getDateFromString($this->time2) : null);
      array_push($times, $this->time3 ?  getDateFromString($this->time3) : null);
      array_push($times, $this->time4 ?  getDateFromString($this->time4) : null);
      return $times;
    }
  }
?>