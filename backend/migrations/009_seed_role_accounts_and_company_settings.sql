-- Demo accounts for the marketplace role model.
-- super admin: super@rck1996.com / SuperAdmin12345
-- company admin: admin@demo.local / Admin12345
-- staff: camila.rojas@centrokine.local / Staff12345

INSERT INTO users (company_id, email, username, password_hash, role, is_active)
VALUES (NULL, 'super@rck1996.com', 'rck1996', crypt('SuperAdmin12345', gen_salt('bf', 10)), 'super_admin', TRUE)
ON CONFLICT (email) DO UPDATE
SET company_id = NULL,
    username = 'rck1996',
    role = 'super_admin',
    is_active = TRUE,
    updated_at = NOW();

INSERT INTO users (company_id, email, username, password_hash, role, is_active)
SELECT c.id, 'camila.rojas@centrokine.local', 'camila-rojas', crypt('Staff12345', gen_salt('bf', 10)), 'staff', TRUE
FROM companies c
WHERE c.slug = 'centro-kine'
ON CONFLICT (email) DO UPDATE
SET company_id = EXCLUDED.company_id,
    username = EXCLUDED.username,
    role = 'staff',
    is_active = TRUE,
    updated_at = NOW();

UPDATE professionals
SET user_id = users.id,
    updated_at = NOW()
FROM users
JOIN companies ON companies.id = users.company_id
WHERE professionals.company_id = companies.id
  AND professionals.email = users.email
  AND users.email = 'camila.rojas@centrokine.local';

INSERT INTO company_settings (company_id, key, value)
SELECT c.id, setting.key, setting.value
FROM companies c
CROSS JOIN (
    VALUES
        ('booking_slot_interval', '30'),
        ('booking_min_notice_hours', '2'),
        ('company_timezone', 'America/Santiago'),
        ('holiday_policy', 'manual')
) AS setting(key, value)
WHERE c.slug IN ('demo', 'centro-kine', 'estudio-estetica', 'consultora-pro', 'wellness-studio')
ON CONFLICT (company_id, key) DO NOTHING;
