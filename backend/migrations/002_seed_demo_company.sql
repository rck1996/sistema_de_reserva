INSERT INTO companies (name, slug)
VALUES ('Demo Company', 'demo')
ON CONFLICT (slug) DO NOTHING;

INSERT INTO users (company_id, email, username, password_hash, role)
SELECT id, 'admin@demo.local', 'admin_demo', '$2y$10$replace_with_hash', 'admin_empresa'
FROM companies
WHERE slug = 'demo'
ON CONFLICT (company_id, email) DO NOTHING;
