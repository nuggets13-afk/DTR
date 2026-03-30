-- 1. Create the Users Table
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'student',
    total_required_hours DECIMAL(10,2) NOT NULL DEFAULT 600.00,
    theme_mode VARCHAR(10) NOT NULL DEFAULT 'dark',
    progress_color_hex VARCHAR(7) NOT NULL DEFAULT '#e10600',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Create the Time Logs Table
CREATE TABLE IF NOT EXISTS time_logs (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    check_in TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    check_out TIMESTAMP,
    status VARCHAR(20) DEFAULT 'pending',
    remarks TEXT
);

-- 3. (Optional) Insert a Test Admin Account
-- Password is: admin123
INSERT INTO users (name, email, password, role, total_required_hours) 
VALUES (
    'Ziggy', 
    'admin@test.com', 
    '$2y$10$89.v/zK3R4.T.tC.0.B.0.bXvj9fM7Y1/2g3h4i5j6k7l8m9n0o1p', 
    'admin', 
    600.00
) ON CONFLICT (email) DO NOTHING;
