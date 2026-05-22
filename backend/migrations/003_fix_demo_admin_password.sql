UPDATE users
SET password_hash = '$2y$10$7Hpvj9.rV8YccDbC9jD.4.l4/hWC/gsHQUmw4zUP9iPyTcoCtp9PG'
WHERE email = 'admin@demo.local'
  AND role = 'admin_empresa';
