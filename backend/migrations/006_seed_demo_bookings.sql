INSERT INTO bookings (company_id, customer_id, professional_id, service_id, starts_at, ends_at, status, notes)
SELECT companies.id, customers.id, professionals.id, services.id,
       '2026-06-02T10:00:00-04:00'::timestamptz,
       '2026-06-02T10:45:00-04:00'::timestamptz,
       'confirmed',
       'Reserva demo PostgreSQL API v1.'
FROM companies
JOIN customers ON customers.company_id = companies.id AND customers.email = 'camila.rojas@demo.local'
JOIN professionals ON professionals.company_id = companies.id AND professionals.email = 'valentina.morales@demo.local'
JOIN services ON services.company_id = companies.id AND services.name = 'Diagnostico inicial'
WHERE companies.slug = 'demo'
  AND NOT EXISTS (
      SELECT 1
      FROM bookings
      WHERE bookings.company_id = companies.id
        AND bookings.professional_id = professionals.id
        AND bookings.starts_at = '2026-06-02T10:00:00-04:00'::timestamptz
  );
