INSERT INTO company_settings (company_id, key, value)
SELECT id, 'notifications_email_enabled', '0'
FROM companies
WHERE slug = 'demo'
ON CONFLICT (company_id, key) DO UPDATE SET value = '0', updated_at = NOW();

INSERT INTO company_settings (company_id, key, value)
SELECT id, 'notifications_whatsapp_enabled', '0'
FROM companies
WHERE slug = 'demo'
ON CONFLICT (company_id, key) DO UPDATE SET value = '0', updated_at = NOW();

INSERT INTO company_settings (company_id, key, value)
SELECT id, 'notifications_send_email', '0'
FROM companies
WHERE slug = 'demo'
ON CONFLICT (company_id, key) DO UPDATE SET value = '0', updated_at = NOW();

INSERT INTO professionals (company_id, name, email, phone, bio, calendar_color, booking_capacity, accepts_waitlist, is_active)
SELECT id, 'Valentina Morales', 'valentina.morales@demo.local', '+56933333333', 'Especialista demo para diagnostico y acompanamiento.', '#22d3ee', 1, TRUE, TRUE
FROM companies
WHERE slug = 'demo'
  AND NOT EXISTS (
      SELECT 1
      FROM professionals
      WHERE professionals.company_id = companies.id
        AND professionals.email = 'valentina.morales@demo.local'
  );

INSERT INTO professionals (company_id, name, email, phone, bio, calendar_color, booking_capacity, accepts_waitlist, is_active)
SELECT id, 'Diego Fernandez', 'diego.fernandez@demo.local', '+56944444444', 'Profesional demo orientado a sesiones online y seguimiento.', '#34d399', 1, TRUE, TRUE
FROM companies
WHERE slug = 'demo'
  AND NOT EXISTS (
      SELECT 1
      FROM professionals
      WHERE professionals.company_id = companies.id
        AND professionals.email = 'diego.fernandez@demo.local'
  );

INSERT INTO professional_services (company_id, professional_id, service_id)
SELECT companies.id, professionals.id, services.id
FROM companies
JOIN professionals ON professionals.company_id = companies.id AND professionals.email = 'valentina.morales@demo.local'
JOIN services ON services.company_id = companies.id AND services.name = 'Diagnostico inicial'
WHERE companies.slug = 'demo'
ON CONFLICT (professional_id, service_id) DO NOTHING;

INSERT INTO professional_services (company_id, professional_id, service_id)
SELECT companies.id, professionals.id, services.id
FROM companies
JOIN professionals ON professionals.company_id = companies.id AND professionals.email = 'diego.fernandez@demo.local'
JOIN services ON services.company_id = companies.id AND services.name = 'Sesion de seguimiento'
WHERE companies.slug = 'demo'
ON CONFLICT (professional_id, service_id) DO NOTHING;
