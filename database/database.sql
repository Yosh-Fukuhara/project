CREATE DATABASE IF NOT EXISTS cybersphere CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE cybersphere;

-- Disable foreign key checks temporarily to avoid errors during table creation
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(30) DEFAULT 'user',
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_profiles (
    profile_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    profile_pic VARCHAR(255),
    cover_pic VARCHAR(255),
    bio TEXT,
    location VARCHAR(100),
    website VARCHAR(255),
    phone VARCHAR(20),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_work (
    work_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    company VARCHAR(100) NOT NULL,
    title VARCHAR(100) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_education (
    edu_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    school VARCHAR(100) NOT NULL,
    degree VARCHAR(100) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS posts (
    post_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(30) DEFAULT 'text',
    content TEXT,
    shared_from INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (shared_from) REFERENCES posts(post_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS post_attachments (
    attachment_id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(50),
    FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS post_tags (
    tag_id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    tag VARCHAR(50) NOT NULL,
    FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS post_likes (
    like_id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    emoji VARCHAR(10) NOT NULL DEFAULT '👍',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_like (post_id, user_id),
    FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS job_post_details (
    job_detail_id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    is_hiring BOOLEAN DEFAULT TRUE,
    enable_apply BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_applications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    applicant_id INT NOT NULL,
    app_first_name VARCHAR(50) NOT NULL,
    app_last_name VARCHAR(50) NOT NULL,
    app_email VARCHAR(100) NOT NULL,
    app_phone VARCHAR(20),
    app_message TEXT,
    resume_path VARCHAR(255) NOT NULL,
    status VARCHAR(30) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
    FOREIGN KEY (applicant_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saved_jobs (
    saved_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    post_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_save (user_id, post_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255),
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employer_applications (
    eapp_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    industry VARCHAR(100),
    company_size VARCHAR(50),
    website VARCHAR(255),
    description TEXT,
    contact_name VARCHAR(255),
    contact_phone VARCHAR(50),
    status VARCHAR(50) DEFAULT 'pending',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS EMPLOYER_APPLICATION_DOCUMENTS (
    doc_id INT AUTO_INCREMENT PRIMARY KEY,
    eapp_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_url VARCHAR(255) NOT NULL,
    FOREIGN KEY (eapp_id) REFERENCES employer_applications(eapp_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS EMPLOYERS (
    employer_id INT AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    industry VARCHAR(100),
    company_size VARCHAR(50),
    website VARCHAR(255),
    description TEXT,
    contact_name VARCHAR(255),
    contact_phone VARCHAR(50),
    logo_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS communities (
    community_id INT AUTO_INCREMENT PRIMARY KEY,
    created_by INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    type VARCHAR(30) DEFAULT 'public',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_members (
    member_id INT AUTO_INCREMENT PRIMARY KEY,
    community_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_membership (community_id, user_id),
    FOREIGN KEY (community_id) REFERENCES communities(community_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Match your existing community_posts structure
CREATE TABLE IF NOT EXISTS community_posts (
    post_id INT AUTO_INCREMENT PRIMARY KEY,
    community_id INT NOT NULL,
    post_author_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (community_id) REFERENCES communities(community_id) ON DELETE CASCADE,
    FOREIGN KEY (post_author_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Use community_post_id as the foreign key for community_post_attachments
CREATE TABLE IF NOT EXISTS community_post_attachments (
    attachment_id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(50),
    FOREIGN KEY (post_id) REFERENCES community_posts(post_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conversations (
    conversation_id INT AUTO_INCREMENT PRIMARY KEY,
    user_a INT NOT NULL,
    user_b INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_a) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (user_b) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    body TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_attachments (
    attachment_id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    file_name VARCHAR(100) NOT NULL,
    file_url VARCHAR(255) NOT NULL,
    FOREIGN KEY (message_id) REFERENCES messages(message_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    image_url VARCHAR(255),
    badge VARCHAR(50),
    stock INT DEFAULT 0,
    discount_pct DECIMAL(5, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES product_categories(category_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    tax DECIMAL(10, 2) NOT NULL,
    grand_total DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50),
    payment_details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    quantity INT NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_modules (
    module_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_objectives (
    objective_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    objective TEXT NOT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_assessments (
    assessment_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assessments (
    assessment_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    instructions TEXT,
    time_limit_mins INT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assessment_challenges (
    challenge_id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(150) NOT NULL,
    body TEXT NOT NULL,
    points_available INT DEFAULT 0,
    correct_answer TEXT,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (assessment_id) REFERENCES assessments(assessment_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS challenge_attachments (
    attachment_id INT AUTO_INCREMENT PRIMARY KEY,
    challenge_id INT NOT NULL,
    file_name VARCHAR(100) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    icon VARCHAR(50),
    note TEXT,
    FOREIGN KEY (challenge_id) REFERENCES assessment_challenges(challenge_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS challenge_options (
    option_id INT AUTO_INCREMENT PRIMARY KEY,
    challenge_id INT NOT NULL,
    option_text TEXT NOT NULL,
    is_correct BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (challenge_id) REFERENCES assessment_challenges(challenge_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assessment_sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    post_id INT NOT NULL,
    deadline DATETIME,
    status VARCHAR(30) DEFAULT 'active',
    FOREIGN KEY (assessment_id) REFERENCES assessments(assessment_id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_assessment_attempts (
    attempt_id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    user_id INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    total_time_secs INT,
    total_score INT DEFAULT 0,
    challenges_solved INT DEFAULT 0,
    status VARCHAR(30) DEFAULT 'in_progress',
    FOREIGN KEY (session_id) REFERENCES assessment_sessions(session_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_challenge_answers (
    answer_id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    challenge_id INT NOT NULL,
    submitted_answer TEXT,
    status VARCHAR(30) DEFAULT 'unreviewed',
    points_awarded INT DEFAULT 0,
    time_used_secs INT,
    FOREIGN KEY (attempt_id) REFERENCES user_assessment_attempts(attempt_id) ON DELETE CASCADE,
    FOREIGN KEY (challenge_id) REFERENCES assessment_challenges(challenge_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;
