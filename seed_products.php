<?php
require_once 'config/database.php';
require_once 'data/products.php';

$pdo = get_db_connection();

// Curriculum data from curriculum.php
$curriculumData = [
    1 => [
        'modules' => [
            ['name' => 'Introduction to Penetration Testing', 'lessons' => 8, 'duration' => '2 weeks'],
            ['name' => 'Reconnaissance & Footprinting', 'lessons' => 12, 'duration' => '3 weeks'],
            ['name' => 'Scanning & Enumeration', 'lessons' => 10, 'duration' => '2 weeks'],
            ['name' => 'Exploitation Fundamentals', 'lessons' => 15, 'duration' => '4 weeks'],
            ['name' => 'Post-Exploitation Techniques', 'lessons' => 11, 'duration' => '3 weeks'],
            ['name' => 'Web Application Penetration Testing', 'lessons' => 18, 'duration' => '5 weeks'],
        ],
        'objectives' => ['Master ethical hacking', 'Perform penetration tests', 'Secure systems'],
        'assessments' => ['Hands-on labs', 'Capture-the-Flag challenges', 'Final practical exam']
    ],
    4 => [
        'modules' => [
            ['name' => 'Introduction to SOC', 'lessons' => 6, 'duration' => '1 week'],
            ['name' => 'Network Security Monitoring', 'lessons' => 12, 'duration' => '3 weeks'],
            ['name' => 'Incident Detection & Response', 'lessons' => 15, 'duration' => '4 weeks'],
            ['name' => 'SIEM Implementation', 'lessons' => 10, 'duration' => '2.5 weeks'],
            ['name' => 'Threat Intelligence', 'lessons' => 8, 'duration' => '2 weeks'],
            ['name' => 'Advanced Exploitation', 'lessons' => 12, 'duration' => '3 weeks'],
        ],
        'objectives' => ['Identify web vulnerabilities', 'Exploit common flaws', 'Secure web applications'],
        'assessments' => ['Simulated incident response drills', 'Capstone project', 'Certification prep']
    ],
    5 => [
        'modules' => [
            ['name' => 'Python Fundamentals for Cybersecurity', 'lessons' => 10, 'duration' => '2 weeks'],
            ['name' => 'Scripting for Network Scanning', 'lessons' => 8, 'duration' => '1.5 weeks'],
            ['name' => 'Automating Vulnerability Assessment', 'lessons' => 12, 'duration' => '3 weeks'],
            ['name' => 'Malware Analysis with Python', 'lessons' => 10, 'duration' => '2.5 weeks'],
            ['name' => 'Forensic Data Processing', 'lessons' => 8, 'duration' => '2 weeks'],
        ],
        'objectives' => ['Automate security tasks', 'Write custom scripts', 'Analyze data'],
        'assessments' => ['Scripting projects', 'Automated scanning tool', 'Final capstone']
    ]
];

try {
    $pdo->beginTransaction();

    // Insert product categories
    $categories = ['Courses', 'Books', 'Resources'];
    $categoryIds = [];

    foreach ($categories as $categoryName) {
        // Check if category exists
        $stmt = $pdo->prepare("SELECT category_id FROM product_categories WHERE name = ?");
        $stmt->execute([$categoryName]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing) {
            $stmt = $pdo->prepare("INSERT INTO product_categories (name) VALUES (?)");
            $stmt->execute([$categoryName]);
            $categoryIds[$categoryName] = $pdo->lastInsertId();
        } else {
            $categoryIds[$categoryName] = $existing['category_id'];
        }
    }

    // Insert products
    foreach ($products as $product) {
        // Check if product exists by id
        $stmt = $pdo->prepare("SELECT product_id FROM products WHERE product_id = ?");
        $stmt->execute([$product['id']]);
        $existingProduct = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existingProduct) {
            $stmt = $pdo->prepare("
                INSERT INTO products (product_id, category_id, name, description, price, image_url, badge)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $product['id'],
                $categoryIds[$product['category']],
                $product['name'],
                $product['description'],
                $product['price'],
                $product['image'],
                $product['badge'] ?? null
            ]);
        } else {
            // Optional: Update existing product if needed
            $stmt = $pdo->prepare("
                UPDATE products 
                SET category_id = ?, name = ?, description = ?, price = ?, image_url = ?, badge = ?
                WHERE product_id = ?
            ");
            $stmt->execute([
                $categoryIds[$product['category']],
                $product['name'],
                $product['description'],
                $product['price'],
                $product['image'],
                $product['badge'] ?? null,
                $product['id']
            ]);
        }

        // Seed product modules, objectives, assessments if curriculum data exists
        if (isset($curriculumData[$product['id']])) {
            $curriculum = $curriculumData[$product['id']];

            // Seed product modules
            foreach ($curriculum['modules'] as $index => $module) {
                $stmt = $pdo->prepare("
                    SELECT module_id FROM product_modules 
                    WHERE product_id = ? AND title = ?
                ");
                $stmt->execute([$product['id'], $module['name']]);
                $existingModule = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$existingModule) {
                    $stmt = $pdo->prepare("
                        INSERT INTO product_modules (product_id, title, sort_order)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$product['id'], $module['name'], $index]);
                }
            }

            // Seed product objectives
            foreach ($curriculum['objectives'] as $index => $objective) {
                $stmt = $pdo->prepare("
                    SELECT objective_id FROM product_objectives 
                    WHERE product_id = ? AND objective = ?
                ");
                $stmt->execute([$product['id'], $objective]);
                $existingObjective = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$existingObjective) {
                    $stmt = $pdo->prepare("
                        INSERT INTO product_objectives (product_id, objective, sort_order)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$product['id'], $objective, $index]);
                }
            }

            // Seed product assessments
            foreach ($curriculum['assessments'] as $index => $assessment) {
                $stmt = $pdo->prepare("
                    SELECT assessment_id FROM product_assessments 
                    WHERE product_id = ? AND title = ?
                ");
                $stmt->execute([$product['id'], $assessment]);
                $existingAssessment = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$existingAssessment) {
                    $stmt = $pdo->prepare("
                        INSERT INTO product_assessments (product_id, title, sort_order)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$product['id'], $assessment, $index]);
                }
            }
        }
    }

    $pdo->commit();
    echo "Products, modules, objectives, and assessments seeded successfully!";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error seeding: " . $e->getMessage();
}
?>