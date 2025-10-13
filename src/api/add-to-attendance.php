<?php 

function POST()
{
  Database::instance()->query(
    "INSERT INTO attendance 
      (user_id, date, status) 
    VALUES
      (?, CURRENT_TIMESTAMP, ?)
    ", 
    [$_POST['user_id'], $_POST['status']]
  );

  return "Successfully logged attendance of user {$_POST['user_id']}";
}