ALTER TABLE users DROP CONSTRAINT IF EXISTS users_company_id_email_key;
ALTER TABLE users DROP CONSTRAINT IF EXISTS users_company_id_username_key;

CREATE UNIQUE INDEX IF NOT EXISTS idx_users_global_email ON users (email);
CREATE UNIQUE INDEX IF NOT EXISTS idx_users_company_username ON users (company_id, username) WHERE username IS NOT NULL;

CREATE TABLE IF NOT EXISTS company_profiles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id UUID NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    display_name VARCHAR(180) NOT NULL DEFAULT '',
    tagline VARCHAR(220) NOT NULL DEFAULT '',
    description TEXT NOT NULL DEFAULT '',
    logo_url TEXT NOT NULL DEFAULT '',
    cover_url TEXT NOT NULL DEFAULT '',
    square_logo_url TEXT NOT NULL DEFAULT '',
    primary_color VARCHAR(20) NOT NULL DEFAULT '#22d3ee',
    accent_color VARCHAR(20) NOT NULL DEFAULT '#8b5cf6',
    city VARCHAR(120) NOT NULL DEFAULT '',
    address TEXT NOT NULL DEFAULT '',
    contact_email VARCHAR(190) NOT NULL DEFAULT '',
    contact_phone VARCHAR(60) NOT NULL DEFAULT '',
    website_url TEXT NOT NULL DEFAULT '',
    is_public BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (company_id)
);

CREATE TABLE IF NOT EXISTS customer_profiles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    first_name VARCHAR(120) NOT NULL,
    last_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(60) NOT NULL DEFAULT '',
    avatar_url TEXT NOT NULL DEFAULT '',
    notes TEXT NOT NULL DEFAULT '',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (user_id),
    UNIQUE (email)
);

CREATE TABLE IF NOT EXISTS company_customers (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id UUID NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    customer_profile_id UUID NOT NULL REFERENCES customer_profiles(id) ON DELETE CASCADE,
    status VARCHAR(40) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'pending', 'blocked', 'archived')),
    company_notes TEXT NOT NULL DEFAULT '',
    joined_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (company_id, customer_profile_id)
);

ALTER TABLE professional_services ADD COLUMN IF NOT EXISTS custom_price NUMERIC(12, 2);
ALTER TABLE professional_services ADD COLUMN IF NOT EXISTS custom_duration_minutes INTEGER;
ALTER TABLE professional_services ADD COLUMN IF NOT EXISTS is_active BOOLEAN NOT NULL DEFAULT TRUE;
ALTER TABLE professional_services ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW();

CREATE TABLE IF NOT EXISTS professional_availability (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id UUID NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    professional_id UUID NOT NULL REFERENCES professionals(id) ON DELETE CASCADE,
    weekday SMALLINT NOT NULL CHECK (weekday BETWEEN 0 AND 6),
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS professional_time_blocks (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id UUID NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    professional_id UUID NOT NULL REFERENCES professionals(id) ON DELETE CASCADE,
    block_type VARCHAR(40) NOT NULL DEFAULT 'block' CHECK (block_type IN ('block', 'vacation', 'break', 'exception')),
    starts_at TIMESTAMPTZ NOT NULL,
    ends_at TIMESTAMPTZ NOT NULL,
    reason TEXT NOT NULL DEFAULT '',
    is_available BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CHECK (ends_at > starts_at)
);

ALTER TABLE bookings ADD COLUMN IF NOT EXISTS customer_profile_id UUID REFERENCES customer_profiles(id) ON DELETE SET NULL;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS company_customer_id UUID REFERENCES company_customers(id) ON DELETE SET NULL;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS final_price NUMERIC(12, 2);
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS price_source VARCHAR(40) NOT NULL DEFAULT 'service_base' CHECK (price_source IN ('service_base', 'professional_custom', 'manual'));

INSERT INTO company_profiles (company_id, display_name, tagline, description, logo_url, primary_color, accent_color, contact_email, contact_phone, city, address)
SELECT id, name, 'Reservas demo marketplace', 'Empresa demo para validar marketplace multiempresa.', COALESCE(logo, ''), '#22d3ee', '#8b5cf6', 'contacto@demo.local', '+56900000000', 'Santiago', ''
FROM companies
ON CONFLICT (company_id) DO NOTHING;

INSERT INTO customer_profiles (user_id, first_name, last_name, email, phone, notes, is_active)
SELECT users.id, customers.first_name, customers.last_name, users.email, customers.phone, customers.notes, users.is_active AND customers.is_active
FROM users
JOIN customers ON customers.user_id = users.id
WHERE users.role = 'customer'
ON CONFLICT (user_id) DO NOTHING;

INSERT INTO company_customers (company_id, customer_profile_id, status, company_notes)
SELECT customers.company_id, customer_profiles.id, 'active', customers.notes
FROM customers
JOIN customer_profiles ON customer_profiles.user_id = customers.user_id
WHERE customers.user_id IS NOT NULL
ON CONFLICT (company_id, customer_profile_id) DO NOTHING;

UPDATE bookings
SET customer_profile_id = customer_profiles.id,
    company_customer_id = company_customers.id,
    final_price = COALESCE(bookings.final_price, services.price)
FROM customers
JOIN customer_profiles ON customer_profiles.user_id = customers.user_id
JOIN company_customers ON company_customers.company_id = customers.company_id
    AND company_customers.customer_profile_id = customer_profiles.id
JOIN services ON services.company_id = customers.company_id
WHERE bookings.customer_id = customers.id
  AND bookings.company_id = customers.company_id
  AND services.id = bookings.service_id
  AND bookings.customer_profile_id IS NULL;

INSERT INTO professional_availability (company_id, professional_id, weekday, start_time, end_time)
SELECT professionals.company_id, professionals.id, weekdays.weekday, TIME '09:00', TIME '18:00'
FROM professionals
CROSS JOIN generate_series(1, 5) AS weekdays(weekday)
WHERE NOT EXISTS (
    SELECT 1
    FROM professional_availability existing
    WHERE existing.company_id = professionals.company_id
      AND existing.professional_id = professionals.id
      AND existing.weekday = weekdays.weekday
);

CREATE INDEX IF NOT EXISTS idx_company_profiles_public ON company_profiles(is_public, display_name);
CREATE INDEX IF NOT EXISTS idx_customer_profiles_user ON customer_profiles(user_id);
CREATE INDEX IF NOT EXISTS idx_company_customers_company_status ON company_customers(company_id, status);
CREATE INDEX IF NOT EXISTS idx_company_customers_profile ON company_customers(customer_profile_id);
CREATE INDEX IF NOT EXISTS idx_professional_availability_lookup ON professional_availability(company_id, professional_id, weekday);
CREATE INDEX IF NOT EXISTS idx_professional_time_blocks_lookup ON professional_time_blocks(company_id, professional_id, starts_at, ends_at);
CREATE INDEX IF NOT EXISTS idx_bookings_customer_profile ON bookings(customer_profile_id, starts_at);
