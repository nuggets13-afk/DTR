-- 1. Create Users Table with Postgres Syntax
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    total_required_hours DECIMAL(10,2) NOT NULL DEFAULT 600.00,
    theme_mode VARCHAR(10) NOT NULL DEFAULT 'dark',
    progress_color_hex VARCHAR(7) NOT NULL DEFAULT '#e10600',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Create Time Logs Table (Foreign Key linked to SERIAL id)
CREATE TABLE IF NOT EXISTS time_logs (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    time_in TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    time_out TIMESTAMP NULL,
    hours_rendered DECIMAL(10,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_time_logs_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
);

-- 3. Create your initial account for the 600-hour OJT
-- Login: ziggy@ptc.edu | Password: password123
INSERT INTO users (name, email, password, total_required_hours) 
VALUES ('Ziggy', 'ziggy@ptc.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 600.00)
ON CONFLICT (email) DO NOTHING;
