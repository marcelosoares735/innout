<?php
  session_start();
  requireValidSession();


  $user = $_SESSION['user'];
  $records = WorkingHours::loadFromUserAndDate($user->id, date('Y-m-d'));
  try{
    $currentTime = strftime('%H:%M:%S', time());

    if($_POST['forced-time']){
      $currentTime = $_POST['forced-time'];
    }

    $records->innout($currentTime);
    addSuccessMsg('Ponto inserido com sucesso!');
  }catch(AppException $e){
    addErrorMessage($e->getMessage());
  }finally{
    
    header("Location: day_records.php");
  }

?>