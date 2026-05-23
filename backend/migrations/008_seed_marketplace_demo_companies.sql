-- Demo marketplace data for end-to-end testing.
-- Customer account:
--   email: cliente@marketplace.local
--   password: Cliente12345

DROP TABLE IF EXISTS _seed_companies;
DROP TABLE IF EXISTS _seed_disciplines;
DROP TABLE IF EXISTS _seed_services;
DROP TABLE IF EXISTS _seed_professionals;
DROP TABLE IF EXISTS _seed_professional_services;
DROP TABLE IF EXISTS _seed_blocks;
DROP TABLE IF EXISTS _seed_bookings;

CREATE TEMP TABLE _seed_companies (
    slug TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    display_name TEXT NOT NULL,
    tagline TEXT NOT NULL,
    description TEXT NOT NULL,
    primary_color TEXT NOT NULL,
    accent_color TEXT NOT NULL,
    city TEXT NOT NULL,
    address TEXT NOT NULL,
    contact_email TEXT NOT NULL,
    contact_phone TEXT NOT NULL,
    website_url TEXT NOT NULL
) ON COMMIT DROP;

INSERT INTO _seed_companies VALUES
('demo', 'Reserva Studio Demo', 'Reserva Studio Demo', 'Servicios profesionales con agenda inteligente', 'Empresa demo general para validar el flujo marketplace, reservas, agenda cliente y panel empresa.', '#22d3ee', '#8b5cf6', 'Santiago', 'Av. Demo 1200, Santiago', 'contacto@demo.local', '+56910000000', 'https://demo.local'),
('centro-kine', 'Centro Kine Integral', 'Centro Kine Integral', 'Kinesiologia, rehabilitacion y bienestar fisico', 'Centro de atencion multidisciplinaria con servicios de evaluacion, rehabilitacion y seguimiento profesional.', '#06b6d4', '#10b981', 'Providencia', 'Av. Providencia 1840', 'agenda@centrokine.local', '+56920000000', 'https://centrokine.local'),
('estudio-estetica', 'Estudio Estetica Aura', 'Estudio Estetica Aura', 'Estetica avanzada con atencion personalizada', 'Estudio boutique para tratamientos faciales, corporales y planes de cuidado con profesionales certificados.', '#a855f7', '#ec4899', 'Las Condes', 'El Golf 310', 'hola@aura.local', '+56930000000', 'https://aura.local'),
('consultora-pro', 'Consultora ProActiva', 'Consultora ProActiva', 'Consultorias y mentorias para equipos modernos', 'Consultora de estrategia, operaciones y crecimiento con sesiones individuales y talleres para empresas.', '#0f172a', '#22d3ee', 'Santiago Centro', 'Moneda 920', 'contacto@proactiva.local', '+56940000000', 'https://proactiva.local'),
('wellness-studio', 'Wellness Studio Norte', 'Wellness Studio Norte', 'Salud mental, nutricion y bienestar integral', 'Espacio de bienestar integral con sesiones presenciales y remotas enfocadas en habitos sostenibles.', '#10b981', '#f59e0b', 'La Serena', 'Av. del Mar 450', 'reservas@wellnessnorte.local', '+56950000000', 'https://wellnessnorte.local');

INSERT INTO companies (name, slug)
SELECT name, slug
FROM _seed_companies
ON CONFLICT (slug) DO UPDATE
SET name = EXCLUDED.name,
    updated_at = NOW();

INSERT INTO company_profiles (
    company_id, display_name, tagline, description, primary_color, accent_color,
    city, address, contact_email, contact_phone, website_url, is_public
)
SELECT c.id, s.display_name, s.tagline, s.description, s.primary_color, s.accent_color,
       s.city, s.address, s.contact_email, s.contact_phone, s.website_url, TRUE
FROM _seed_companies s
JOIN companies c ON c.slug = s.slug
ON CONFLICT (company_id) DO UPDATE
SET display_name = EXCLUDED.display_name,
    tagline = EXCLUDED.tagline,
    description = EXCLUDED.description,
    primary_color = EXCLUDED.primary_color,
    accent_color = EXCLUDED.accent_color,
    city = EXCLUDED.city,
    address = EXCLUDED.address,
    contact_email = EXCLUDED.contact_email,
    contact_phone = EXCLUDED.contact_phone,
    website_url = EXCLUDED.website_url,
    is_public = TRUE,
    updated_at = NOW();

CREATE TEMP TABLE _seed_disciplines (
    company_slug TEXT NOT NULL,
    name TEXT NOT NULL,
    description TEXT NOT NULL,
    color TEXT NOT NULL
) ON COMMIT DROP;

INSERT INTO _seed_disciplines VALUES
('demo', 'Diagnostico', 'Evaluaciones iniciales y seguimiento general.', '#22d3ee'),
('demo', 'Asesoria', 'Sesiones de acompanamiento profesional.', '#8b5cf6'),
('centro-kine', 'Kinesiologia', 'Rehabilitacion, dolor musculoesqueletico y movilidad.', '#06b6d4'),
('centro-kine', 'Entrenamiento terapeutico', 'Planes de fuerza, retorno deportivo y prevencion.', '#10b981'),
('estudio-estetica', 'Estetica facial', 'Tratamientos faciales personalizados.', '#a855f7'),
('estudio-estetica', 'Estetica corporal', 'Tratamientos corporales y protocolos de cuidado.', '#ec4899'),
('consultora-pro', 'Estrategia', 'Diagnostico y diseno de planes de crecimiento.', '#22d3ee'),
('consultora-pro', 'Operaciones', 'Mejora de procesos, rituales y productividad.', '#64748b'),
('wellness-studio', 'Salud mental', 'Acompanamiento psicologico y manejo de estres.', '#10b981'),
('wellness-studio', 'Nutricion', 'Planes nutricionales y cambio de habitos.', '#f59e0b');

INSERT INTO disciplines (company_id, name, description, color, is_active)
SELECT c.id, d.name, d.description, d.color, TRUE
FROM _seed_disciplines d
JOIN companies c ON c.slug = d.company_slug
WHERE NOT EXISTS (
    SELECT 1 FROM disciplines existing
    WHERE existing.company_id = c.id AND existing.name = d.name
);

CREATE TEMP TABLE _seed_services (
    company_slug TEXT NOT NULL,
    discipline_name TEXT NOT NULL,
    name TEXT NOT NULL,
    description TEXT NOT NULL,
    price NUMERIC(12, 2) NOT NULL,
    duration_minutes INTEGER NOT NULL,
    modality TEXT NOT NULL,
    color TEXT NOT NULL
) ON COMMIT DROP;

INSERT INTO _seed_services VALUES
('demo', 'Diagnostico', 'Diagnostico inicial', 'Primera evaluacion para entender necesidad, contexto y plan de accion.', 22000, 45, 'Presencial', '#22d3ee'),
('demo', 'Asesoria', 'Sesion de seguimiento', 'Sesion de control, ajustes y recomendaciones.', 18000, 40, 'Online', '#8b5cf6'),
('centro-kine', 'Kinesiologia', 'Evaluacion kinesica', 'Evaluacion funcional, objetivos y plan de rehabilitacion.', 32000, 50, 'Presencial', '#06b6d4'),
('centro-kine', 'Kinesiologia', 'Rehabilitacion musculoesqueletica', 'Sesion guiada de terapia y ejercicios correctivos.', 28000, 45, 'Presencial', '#0ea5e9'),
('centro-kine', 'Entrenamiento terapeutico', 'Retorno deportivo', 'Plan progresivo de fuerza, movilidad y retorno a actividad.', 36000, 60, 'Presencial', '#10b981'),
('estudio-estetica', 'Estetica facial', 'Limpieza facial premium', 'Protocolo facial profundo con diagnostico y cuidado post sesion.', 30000, 60, 'Presencial', '#a855f7'),
('estudio-estetica', 'Estetica facial', 'Tratamiento glow', 'Sesion facial express para luminosidad e hidratacion.', 24000, 45, 'Presencial', '#d946ef'),
('estudio-estetica', 'Estetica corporal', 'Drenaje linfatico', 'Tratamiento corporal manual orientado a bienestar y recuperacion.', 35000, 60, 'Presencial', '#ec4899'),
('consultora-pro', 'Estrategia', 'Sesion estrategia negocio', 'Sesion ejecutiva para priorizar objetivos, riesgos y oportunidades.', 65000, 60, 'Online', '#22d3ee'),
('consultora-pro', 'Estrategia', 'Mentoria founder', 'Acompanamiento uno a uno para founders y lideres.', 80000, 75, 'Online', '#38bdf8'),
('consultora-pro', 'Operaciones', 'Workshop productividad', 'Taller para ordenar procesos, responsabilidades y cadencias.', 120000, 120, 'Hibrido', '#64748b'),
('wellness-studio', 'Salud mental', 'Sesion psicologica inicial', 'Primera sesion para levantar contexto y objetivos terapeuticos.', 38000, 50, 'Online', '#10b981'),
('wellness-studio', 'Salud mental', 'Sesion manejo de estres', 'Sesion practica enfocada en herramientas de regulacion y habitos.', 34000, 50, 'Online', '#34d399'),
('wellness-studio', 'Nutricion', 'Evaluacion nutricional', 'Evaluacion de habitos, objetivos y plan alimentario inicial.', 30000, 45, 'Online', '#f59e0b');

INSERT INTO services (company_id, discipline_id, name, description, price, duration_minutes, modality, color, is_active)
SELECT c.id, d.id, s.name, s.description, s.price, s.duration_minutes, s.modality, s.color, TRUE
FROM _seed_services s
JOIN companies c ON c.slug = s.company_slug
JOIN disciplines d ON d.company_id = c.id AND d.name = s.discipline_name
WHERE NOT EXISTS (
    SELECT 1 FROM services existing
    WHERE existing.company_id = c.id AND existing.name = s.name
);

CREATE TEMP TABLE _seed_professionals (
    company_slug TEXT NOT NULL,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    phone TEXT NOT NULL,
    bio TEXT NOT NULL,
    calendar_color TEXT NOT NULL,
    booking_capacity INTEGER NOT NULL
) ON COMMIT DROP;

INSERT INTO _seed_professionals VALUES
('demo', 'Valentina Demo', 'valentina@demo.local', '+56911000001', 'Especialista demo para validar reservas generales.', '#22d3ee', 1),
('demo', 'Diego Demo', 'diego@demo.local', '+56911000002', 'Profesional demo con agenda de seguimiento.', '#8b5cf6', 1),
('centro-kine', 'Camila Rojas', 'camila.rojas@centrokine.local', '+56921000001', 'Kinesiologa especializada en dolor y rehabilitacion funcional.', '#06b6d4', 1),
('centro-kine', 'Matias Leiva', 'matias.leiva@centrokine.local', '+56921000002', 'Preparador fisico orientado a retorno deportivo.', '#10b981', 2),
('estudio-estetica', 'Fernanda Silva', 'fernanda.silva@aura.local', '+56931000001', 'Cosmetologa con foco en protocolos faciales personalizados.', '#a855f7', 1),
('estudio-estetica', 'Paula Torres', 'paula.torres@aura.local', '+56931000002', 'Terapeuta corporal y drenaje linfatico.', '#ec4899', 1),
('consultora-pro', 'Ignacio Perez', 'ignacio.perez@proactiva.local', '+56941000001', 'Consultor de estrategia, pricing y crecimiento.', '#22d3ee', 1),
('consultora-pro', 'Laura Medina', 'laura.medina@proactiva.local', '+56941000002', 'Especialista en operaciones, procesos y productividad.', '#64748b', 1),
('wellness-studio', 'Sofia Herrera', 'sofia.herrera@wellnessnorte.local', '+56951000001', 'Psicologa enfocada en estres, ansiedad y habitos.', '#10b981', 1),
('wellness-studio', 'Tomas Araya', 'tomas.araya@wellnessnorte.local', '+56951000002', 'Nutricionista orientado a bienestar y cambios sostenibles.', '#f59e0b', 1);

INSERT INTO professionals (company_id, name, email, phone, bio, calendar_color, booking_capacity, accepts_waitlist, is_active)
SELECT c.id, p.name, p.email, p.phone, p.bio, p.calendar_color, p.booking_capacity, TRUE, TRUE
FROM _seed_professionals p
JOIN companies c ON c.slug = p.company_slug
ON CONFLICT (company_id, email) DO UPDATE
SET name = EXCLUDED.name,
    phone = EXCLUDED.phone,
    bio = EXCLUDED.bio,
    calendar_color = EXCLUDED.calendar_color,
    booking_capacity = EXCLUDED.booking_capacity,
    accepts_waitlist = TRUE,
    is_active = TRUE,
    updated_at = NOW();

CREATE TEMP TABLE _seed_professional_services (
    professional_email TEXT NOT NULL,
    service_name TEXT NOT NULL,
    custom_price NUMERIC(12, 2),
    custom_duration_minutes INTEGER
) ON COMMIT DROP;

INSERT INTO _seed_professional_services VALUES
('valentina@demo.local', 'Diagnostico inicial', NULL, NULL),
('valentina@demo.local', 'Sesion de seguimiento', 20000, NULL),
('diego@demo.local', 'Sesion de seguimiento', NULL, NULL),
('camila.rojas@centrokine.local', 'Evaluacion kinesica', NULL, NULL),
('camila.rojas@centrokine.local', 'Rehabilitacion musculoesqueletica', 30000, NULL),
('matias.leiva@centrokine.local', 'Retorno deportivo', NULL, NULL),
('matias.leiva@centrokine.local', 'Rehabilitacion musculoesqueletica', NULL, NULL),
('fernanda.silva@aura.local', 'Limpieza facial premium', NULL, NULL),
('fernanda.silva@aura.local', 'Tratamiento glow', 26000, NULL),
('paula.torres@aura.local', 'Drenaje linfatico', NULL, NULL),
('ignacio.perez@proactiva.local', 'Sesion estrategia negocio', NULL, NULL),
('ignacio.perez@proactiva.local', 'Mentoria founder', 90000, NULL),
('laura.medina@proactiva.local', 'Workshop productividad', NULL, NULL),
('sofia.herrera@wellnessnorte.local', 'Sesion psicologica inicial', NULL, NULL),
('sofia.herrera@wellnessnorte.local', 'Sesion manejo de estres', 36000, NULL),
('tomas.araya@wellnessnorte.local', 'Evaluacion nutricional', NULL, NULL);

INSERT INTO professional_services (company_id, professional_id, service_id, custom_price, custom_duration_minutes, is_active)
SELECT p.company_id, p.id, s.id, ps.custom_price, ps.custom_duration_minutes, TRUE
FROM _seed_professional_services ps
JOIN professionals p ON p.email = ps.professional_email
JOIN services s ON s.company_id = p.company_id AND s.name = ps.service_name
ON CONFLICT (professional_id, service_id) DO UPDATE
SET custom_price = EXCLUDED.custom_price,
    custom_duration_minutes = EXCLUDED.custom_duration_minutes,
    is_active = TRUE,
    updated_at = NOW();

INSERT INTO professional_availability (company_id, professional_id, weekday, start_time, end_time, is_active)
SELECT p.company_id, p.id, weekdays.weekday,
       CASE WHEN c.slug = 'consultora-pro' THEN TIME '10:00' ELSE TIME '09:00' END,
       CASE WHEN c.slug = 'estudio-estetica' THEN TIME '19:00' ELSE TIME '18:00' END,
       TRUE
FROM professionals p
JOIN companies c ON c.id = p.company_id
CROSS JOIN generate_series(1, 5) AS weekdays(weekday)
WHERE c.slug IN (SELECT slug FROM _seed_companies)
  AND NOT EXISTS (
      SELECT 1 FROM professional_availability existing
      WHERE existing.company_id = p.company_id
        AND existing.professional_id = p.id
        AND existing.weekday = weekdays.weekday
  );

CREATE TEMP TABLE _seed_blocks (
    professional_email TEXT NOT NULL,
    block_type TEXT NOT NULL,
    starts_at TIMESTAMPTZ NOT NULL,
    ends_at TIMESTAMPTZ NOT NULL,
    reason TEXT NOT NULL,
    is_available BOOLEAN NOT NULL
) ON COMMIT DROP;

INSERT INTO _seed_blocks VALUES
('camila.rojas@centrokine.local', 'break', TIMESTAMPTZ '2026-06-05 13:00:00-04', TIMESTAMPTZ '2026-06-05 14:00:00-04', 'Pausa administrativa demo', FALSE),
('fernanda.silva@aura.local', 'vacation', TIMESTAMPTZ '2026-06-15 00:00:00-04', TIMESTAMPTZ '2026-06-19 23:59:00-04', 'Vacaciones demo', FALSE),
('ignacio.perez@proactiva.local', 'exception', TIMESTAMPTZ '2026-06-08 08:00:00-04', TIMESTAMPTZ '2026-06-08 10:00:00-04', 'Bloqueo por reunion interna', FALSE),
('sofia.herrera@wellnessnorte.local', 'block', TIMESTAMPTZ '2026-06-09 12:00:00-04', TIMESTAMPTZ '2026-06-09 13:30:00-04', 'Bloque protegido demo', FALSE);

INSERT INTO professional_time_blocks (company_id, professional_id, block_type, starts_at, ends_at, reason, is_available)
SELECT p.company_id, p.id, b.block_type, b.starts_at, b.ends_at, b.reason, b.is_available
FROM _seed_blocks b
JOIN professionals p ON p.email = b.professional_email
WHERE NOT EXISTS (
    SELECT 1 FROM professional_time_blocks existing
    WHERE existing.professional_id = p.id
      AND existing.starts_at = b.starts_at
      AND existing.ends_at = b.ends_at
      AND existing.block_type = b.block_type
);

INSERT INTO users (company_id, email, username, password_hash, role, is_active)
VALUES (NULL, 'cliente@marketplace.local', 'cliente-marketplace', crypt('Cliente12345', gen_salt('bf', 10)), 'customer', TRUE)
ON CONFLICT (email) DO UPDATE
SET company_id = NULL,
    username = 'cliente-marketplace',
    role = 'customer',
    is_active = TRUE,
    updated_at = NOW();

INSERT INTO customer_profiles (user_id, first_name, last_name, email, phone, notes, is_active)
SELECT id, 'Cliente', 'Marketplace', 'cliente@marketplace.local', '+56910101010',
       'Cuenta global demo inscrita en cinco empresas marketplace.', TRUE
FROM users
WHERE email = 'cliente@marketplace.local'
ON CONFLICT (user_id) DO UPDATE
SET first_name = EXCLUDED.first_name,
    last_name = EXCLUDED.last_name,
    email = EXCLUDED.email,
    phone = EXCLUDED.phone,
    notes = EXCLUDED.notes,
    is_active = TRUE,
    updated_at = NOW();

INSERT INTO customers (company_id, user_id, first_name, last_name, email, phone, notes, is_active)
SELECT c.id, u.id, 'Cliente', 'Marketplace', u.email, '+56910101010',
       'Ficha local vinculada a cuenta marketplace global.', TRUE
FROM companies c
CROSS JOIN users u
WHERE c.slug IN (SELECT slug FROM _seed_companies)
  AND u.email = 'cliente@marketplace.local'
ON CONFLICT (company_id, email) DO UPDATE
SET user_id = EXCLUDED.user_id,
    first_name = EXCLUDED.first_name,
    last_name = EXCLUDED.last_name,
    phone = EXCLUDED.phone,
    notes = EXCLUDED.notes,
    is_active = TRUE,
    updated_at = NOW();

INSERT INTO company_customers (company_id, customer_profile_id, status, company_notes)
SELECT c.id, cp.id, 'active', 'Cliente demo inscrito para pruebas marketplace.'
FROM companies c
CROSS JOIN customer_profiles cp
WHERE c.slug IN (SELECT slug FROM _seed_companies)
  AND cp.email = 'cliente@marketplace.local'
ON CONFLICT (company_id, customer_profile_id) DO UPDATE
SET status = 'active',
    company_notes = EXCLUDED.company_notes,
    updated_at = NOW();

CREATE TEMP TABLE _seed_bookings (
    company_slug TEXT NOT NULL,
    professional_email TEXT NOT NULL,
    service_name TEXT NOT NULL,
    starts_at TIMESTAMPTZ NOT NULL,
    status TEXT NOT NULL,
    notes TEXT NOT NULL
) ON COMMIT DROP;

INSERT INTO _seed_bookings VALUES
('demo', 'valentina@demo.local', 'Diagnostico inicial', TIMESTAMPTZ '2026-06-04 10:00:00-04', 'confirmed', 'Reserva demo marketplace general.'),
('centro-kine', 'camila.rojas@centrokine.local', 'Evaluacion kinesica', TIMESTAMPTZ '2026-06-05 11:00:00-04', 'confirmed', 'Primera evaluacion kinesica demo.'),
('estudio-estetica', 'fernanda.silva@aura.local', 'Limpieza facial premium', TIMESTAMPTZ '2026-06-06 12:00:00-04', 'pending', 'Reserva facial demo pendiente.'),
('consultora-pro', 'ignacio.perez@proactiva.local', 'Sesion estrategia negocio', TIMESTAMPTZ '2026-06-08 14:00:00-04', 'confirmed', 'Sesion estrategica demo.'),
('wellness-studio', 'sofia.herrera@wellnessnorte.local', 'Sesion psicologica inicial', TIMESTAMPTZ '2026-06-09 16:00:00-04', 'pending', 'Sesion wellness demo.');

INSERT INTO bookings (
    company_id, customer_id, customer_profile_id, company_customer_id,
    professional_id, service_id, starts_at, ends_at, status, notes, final_price, price_source
)
SELECT c.id,
       local_customer.id,
       cp.id,
       cc.id,
       p.id,
       s.id,
       b.starts_at,
       b.starts_at + ((COALESCE(ps.custom_duration_minutes, s.duration_minutes)::TEXT || ' minutes')::INTERVAL),
       b.status,
       b.notes,
       COALESCE(ps.custom_price, s.price),
       CASE WHEN ps.custom_price IS NULL THEN 'service_base' ELSE 'professional_custom' END
FROM _seed_bookings b
JOIN companies c ON c.slug = b.company_slug
JOIN professionals p ON p.company_id = c.id AND p.email = b.professional_email
JOIN services s ON s.company_id = c.id AND s.name = b.service_name
JOIN professional_services ps ON ps.professional_id = p.id AND ps.service_id = s.id
JOIN customer_profiles cp ON cp.email = 'cliente@marketplace.local'
JOIN company_customers cc ON cc.company_id = c.id AND cc.customer_profile_id = cp.id
JOIN customers local_customer ON local_customer.company_id = c.id AND local_customer.email = cp.email
WHERE NOT EXISTS (
    SELECT 1 FROM bookings existing
    WHERE existing.company_id = c.id
      AND existing.customer_profile_id = cp.id
      AND existing.professional_id = p.id
      AND existing.service_id = s.id
      AND existing.starts_at = b.starts_at
);

INSERT INTO audit_log (company_id, entity_type, action, summary, payload_json)
SELECT c.id,
       'seed',
       'marketplace_demo_seeded',
       'Datos demo marketplace disponibles para pruebas.',
       jsonb_build_object('customer_email', 'cliente@marketplace.local', 'company_slug', c.slug)
FROM companies c
WHERE c.slug IN (SELECT slug FROM _seed_companies)
  AND NOT EXISTS (
      SELECT 1 FROM audit_log existing
      WHERE existing.company_id = c.id
        AND existing.entity_type = 'seed'
        AND existing.action = 'marketplace_demo_seeded'
  );
