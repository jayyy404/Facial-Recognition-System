<?php

function GET()
{
  $monthly = Database::instance()->query(
    "SELECT 
      u.id AS user_id, u.name, u.role, u.dept, DATE_FORMAT(a.date, '%Y-%m') AS month,                
      a.status         
    FROM attendance a         
    JOIN users u ON a.user_id=u.id         
    ORDER BY month DESC, u.name ASC",
    []
  )->fetchEntireList();

  return json($monthly);
}