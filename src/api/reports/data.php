<?php

function GET()
{
  $data = Database::instance()->query(
    "SELECT 
      a.date, u.id AS user_id, u.name, u.role,
      u.dept, a.status 
    FROM attendance a 
    JOIN users u ON a.user_id=u.id
    ORDER BY a.date DESC, u.name ASC",
  [])->fetchEntireList();

  return json($data);
}