<?php
require_once 'config/database.php';
require_once 'data/products.php';

$pdo = get_db_connection();

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
        // Check if product exists by id (or name)
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
    }

    $pdo->commit();
    echo "Products seeded successfully!";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error seeding products: " . $e->getMessage();
}
?>