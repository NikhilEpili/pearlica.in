<?php
function admin_user_find_by_username(PDO $pdo, $username) {
    $stmt = $pdo->prepare('SELECT id, username, password_hash, COALESCE(is_super_admin, 0) AS is_super_admin FROM admin_users WHERE username = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([trim((string) $username)]);
    return $stmt->fetch();
}

function admin_user_find_by_id(PDO $pdo, $id) {
    $stmt = $pdo->prepare('SELECT id, username, COALESCE(is_super_admin, 0) AS is_super_admin FROM admin_users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([(int) $id]);
    return $stmt->fetch();
}

function admin_user_list_all(PDO $pdo) {
    $stmt = $pdo->query('SELECT id, username, COALESCE(is_super_admin, 0) AS is_super_admin FROM admin_users WHERE deleted_at IS NULL ORDER BY username');
    return $stmt->fetchAll();
}

function admin_user_username_exists(PDO $pdo, $username, $exclude_id = null) {
    $sql = 'SELECT 1 FROM admin_users WHERE username = ? AND deleted_at IS NULL';
    $params = [trim((string) $username)];
    if ($exclude_id !== null) {
        $sql .= ' AND id != ?';
        $params[] = (int) $exclude_id;
    }
    $stmt = $pdo->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    return (bool) $stmt->fetch();
}

function admin_user_create(PDO $pdo, $username, $password) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $username = trim((string) $username);
    $stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash, is_super_admin) VALUES (?, ?, 0)');
    $stmt->execute([$username, $hash]);
    return $pdo->lastInsertId();
}

function admin_user_update_password(PDO $pdo, $id, $new_password) {
    $hash = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ? AND deleted_at IS NULL');
    return $stmt->execute([$hash, (int) $id]);
}

function admin_user_soft_delete(PDO $pdo, $id) {
    // Soft delete user; permissions are removed via foreign key ON DELETE CASCADE if configured.
    $stmt = $pdo->prepare('UPDATE admin_users SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL');
    return $stmt->execute([(int) $id]);
}

function admin_user_permissions_get(PDO $pdo, $user_id) {
    $defaults = [
        'dashboard' => true,
        'categories' => true,
        'product_groups' => true,
        'collections' => true,
        'products' => true,
        'orders' => true,
        'banners' => true,
        'faqs' => true,
        'policies' => true,
    ];
    try {
        $stmt = $pdo->prepare('SELECT can_dashboard, can_categories, can_product_groups, can_collections, can_products, can_orders, can_banners, can_faqs, can_policies FROM admin_user_permissions WHERE user_id = ?');
        $stmt->execute([(int) $user_id]);
        $row = $stmt->fetch();
    } catch (Throwable $e) {
        try {
            $stmt = $pdo->prepare('SELECT can_dashboard, can_categories, can_product_groups, can_collections, can_products, can_orders, can_banners, can_faqs FROM admin_user_permissions WHERE user_id = ?');
            $stmt->execute([(int) $user_id]);
            $row = $stmt->fetch();
        } catch (Throwable $e2) {
            try {
                $stmt = $pdo->prepare('SELECT can_dashboard, can_categories, can_product_groups, can_collections, can_products, can_orders, can_banners FROM admin_user_permissions WHERE user_id = ?');
                $stmt->execute([(int) $user_id]);
                $row = $stmt->fetch();
            } catch (Throwable $e3) {
                try {
                    $stmt = $pdo->prepare('SELECT can_dashboard, can_categories, can_product_groups, can_collections, can_products, can_orders FROM admin_user_permissions WHERE user_id = ?');
                    $stmt->execute([(int) $user_id]);
                    $row = $stmt->fetch();
                } catch (Throwable $e4) {
                    return $defaults;
                }
            }
        }
    }
    if (!$row) {
        return $defaults;
    }
    return [
        'dashboard' => !empty($row['can_dashboard']),
        'categories' => !empty($row['can_categories']),
        'product_groups' => !empty($row['can_product_groups']),
        'collections' => !empty($row['can_collections']),
        'products' => !empty($row['can_products']),
        'orders' => !empty($row['can_orders']),
        'banners' => array_key_exists('can_banners', $row) ? !empty($row['can_banners']) : true,
        'faqs' => array_key_exists('can_faqs', $row) ? !empty($row['can_faqs']) : true,
        'policies' => array_key_exists('can_policies', $row) ? !empty($row['can_policies']) : true,
    ];
}

function admin_user_permissions_set(PDO $pdo, $user_id, array $perms) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return;
    }
    $flags = [
        'dashboard' => !empty($perms['dashboard']),
        'categories' => !empty($perms['categories']),
        'product_groups' => !empty($perms['product_groups']),
        'collections' => !empty($perms['collections']),
        'products' => !empty($perms['products']),
        'orders' => !empty($perms['orders']),
        'banners' => !empty($perms['banners']),
        'faqs' => !empty($perms['faqs']),
        'policies' => !empty($perms['policies']),
    ];
    try {
        $stmt = $pdo->prepare('REPLACE INTO admin_user_permissions (user_id, can_dashboard, can_categories, can_product_groups, can_collections, can_products, can_orders, can_banners, can_faqs, can_policies) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $user_id,
            $flags['dashboard'] ? 1 : 0,
            $flags['categories'] ? 1 : 0,
            $flags['product_groups'] ? 1 : 0,
            $flags['collections'] ? 1 : 0,
            $flags['products'] ? 1 : 0,
            $flags['orders'] ? 1 : 0,
            $flags['banners'] ? 1 : 0,
            $flags['faqs'] ? 1 : 0,
            $flags['policies'] ? 1 : 0,
        ]);
    } catch (Throwable $e) {
        try {
            $stmt = $pdo->prepare('REPLACE INTO admin_user_permissions (user_id, can_dashboard, can_categories, can_product_groups, can_collections, can_products, can_orders, can_banners, can_faqs) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $user_id,
                $flags['dashboard'] ? 1 : 0,
                $flags['categories'] ? 1 : 0,
                $flags['product_groups'] ? 1 : 0,
                $flags['collections'] ? 1 : 0,
                $flags['products'] ? 1 : 0,
                $flags['orders'] ? 1 : 0,
                $flags['banners'] ? 1 : 0,
                $flags['faqs'] ? 1 : 0,
            ]);
        } catch (Throwable $e2) {
            try {
                $stmt = $pdo->prepare('REPLACE INTO admin_user_permissions (user_id, can_dashboard, can_categories, can_product_groups, can_collections, can_products, can_orders, can_banners) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([
                    $user_id,
                    $flags['dashboard'] ? 1 : 0,
                    $flags['categories'] ? 1 : 0,
                    $flags['product_groups'] ? 1 : 0,
                    $flags['collections'] ? 1 : 0,
                    $flags['products'] ? 1 : 0,
                    $flags['orders'] ? 1 : 0,
                    $flags['banners'] ? 1 : 0,
                ]);
            } catch (Throwable $e3) {
                try {
                    $stmt = $pdo->prepare('REPLACE INTO admin_user_permissions (user_id, can_dashboard, can_categories, can_product_groups, can_collections, can_products, can_orders) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([
                        $user_id,
                        $flags['dashboard'] ? 1 : 0,
                        $flags['categories'] ? 1 : 0,
                        $flags['product_groups'] ? 1 : 0,
                        $flags['collections'] ? 1 : 0,
                        $flags['products'] ? 1 : 0,
                        $flags['orders'] ? 1 : 0,
                    ]);
                } catch (Throwable $e4) {
                    $stmt = $pdo->prepare('REPLACE INTO admin_user_permissions (user_id, can_dashboard, can_categories, can_product_groups, can_products, can_orders) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute([
                        $user_id,
                        $flags['dashboard'] ? 1 : 0,
                        $flags['categories'] ? 1 : 0,
                        $flags['product_groups'] ? 1 : 0,
                        $flags['products'] ? 1 : 0,
                        $flags['orders'] ? 1 : 0,
                    ]);
                }
            }
        }
    }
}
