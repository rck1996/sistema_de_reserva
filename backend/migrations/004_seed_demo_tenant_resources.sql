INSERT INTO disciplines (company_id, name, description, color)
SELECT id, 'Consultoria', 'Servicios de diagnostico, asesoria y seguimiento profesional.', '#22d3ee'
FROM companies
WHERE slug = 'demo'
  AND NOT EXISTS (
      SELECT 1
      FROM disciplines
      WHERE disciplines.company_id = companies.id
        AND disciplines.name = 'Consultoria'
  );

INSERT INTO disciplines (company_id, name, description, color)
SELECT id, 'Bienestar', 'Sesiones orientadas a evaluacion, acompaniamiento y mejora continua.', '#34d399'
FROM companies
WHERE slug = 'demo'
  AND NOT EXISTS (
      SELECT 1
      FROM disciplines
      WHERE disciplines.company_id = companies.id
        AND disciplines.name = 'Bienestar'
  );

INSERT INTO services (company_id, discipline_id, name, description, price, duration_minutes, modality, color, is_active)
SELECT companies.id, disciplines.id, 'Diagnostico inicial', 'Primera sesion para levantar contexto, objetivos y plan de accion.', 29990, 45, 'presencial', '#22d3ee', TRUE
FROM companies
JOIN disciplines ON disciplines.company_id = companies.id AND disciplines.name = 'Consultoria'
WHERE companies.slug = 'demo'
  AND NOT EXISTS (
      SELECT 1
      FROM services
      WHERE services.company_id = companies.id
        AND services.name = 'Diagnostico inicial'
  );

INSERT INTO services (company_id, discipline_id, name, description, price, duration_minutes, modality, color, is_active)
SELECT companies.id, disciplines.id, 'Sesion de seguimiento', 'Reserva de continuidad para revisar avances, acuerdos y proximas acciones.', 24990, 30, 'online', '#34d399', TRUE
FROM companies
JOIN disciplines ON disciplines.company_id = companies.id AND disciplines.name = 'Bienestar'
WHERE companies.slug = 'demo'
  AND NOT EXISTS (
      SELECT 1
      FROM services
      WHERE services.company_id = companies.id
        AND services.name = 'Sesion de seguimiento'
  );

INSERT INTO customers (company_id, first_name, last_name, email, phone, notes)
SELECT id, 'Camila', 'Rojas', 'camila.rojas@demo.local', '+56911111111', 'Cliente demo para pruebas de API v1.'
FROM companies
WHERE slug = 'demo'
  AND NOT EXISTS (
      SELECT 1
      FROM customers
      WHERE customers.company_id = companies.id
        AND customers.email = 'camila.rojas@demo.local'
  );

INSERT INTO customers (company_id, first_name, last_name, email, phone, notes)
SELECT id, 'Martin', 'Silva', 'martin.silva@demo.local', '+56922222222', 'Cliente demo con telefono y correo para flujos de reserva.'
FROM companies
WHERE slug = 'demo'
  AND NOT EXISTS (
      SELECT 1
      FROM customers
      WHERE customers.company_id = companies.id
        AND customers.email = 'martin.silva@demo.local'
  );
