<?php
/**
 * CSDL.PHP - Database Functions (MySQL)
 * XPARKING - Hệ thống quản lý bãi đỗ xe
 */
date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/../includes/config.php';

// ============================================================
// CRUD FUNCTIONS
// ============================================================

/**
 * Lấy 1 record
 */
function dbGetOne($table, $column, $value) {
    try {
        $stmt = db()->prepare("SELECT * FROM `$table` WHERE `$column` = ? LIMIT 1");
        $stmt->execute([$value]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        error_log("dbGetOne error: " . $e->getMessage());
        return null;
    }
}

/**
 * Lấy nhiều records với filter
 */
function dbGetMany($table, $filters = [], $select = '*', $order = null, $limit = null) {
    try {
        $sql = "SELECT $select FROM `$table`";
        $params = [];
        
        if (!empty($filters)) {
            $where = [];
            foreach ($filters as $col => $val) {
                $where[] = "`$col` = ?";
                $params[] = $val;
            }
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        if ($order) {
            $order = preg_replace('/\.desc$/', ' DESC', $order);
            $order = preg_replace('/\.asc$/', ' ASC', $order);
            $sql .= " ORDER BY $order";
        }
        
        if ($limit) {
            $sql .= " LIMIT $limit";
        }
        
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("dbGetMany error: " . $e->getMessage());
        return [];
    }
}

/**
 * Lấy tất cả records
 */
function dbGetAll($table, $select = '*', $order = null, $limit = null) {
    return dbGetMany($table, [], $select, $order, $limit);
}

/**
 * Insert record
 */
function dbInsert($table, $data) {
    try {
        $columns = implode('`, `', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO `$table` (`$columns`) VALUES ($placeholders)";
        $stmt = db()->prepare($sql);
        $stmt->execute(array_values($data));
        
        $id = db()->lastInsertId();
        return [array_merge(['id' => $id], $data)];
    } catch (PDOException $e) {
        error_log("dbInsert error: " . $e->getMessage());
        return null;
    }
}

/**
 * Update record
 */
function dbUpdate($table, $column, $value, $data) {
    try {
        $set = [];
        $params = [];
        foreach ($data as $col => $val) {
            $set[] = "`$col` = ?";
            $params[] = $val;
        }
        $params[] = $value;
        
        $sql = "UPDATE `$table` SET " . implode(', ', $set) . " WHERE `$column` = ?";
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return true;
    } catch (PDOException $e) {
        error_log("dbUpdate error: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete record
 */
function dbDelete($table, $column, $value) {
    try {
        $stmt = db()->prepare("DELETE FROM `$table` WHERE `$column` = ?");
        return $stmt->execute([$value]);
    } catch (PDOException $e) {
        error_log("dbDelete error: " . $e->getMessage());
        return false;
    }
}

/**
 * Count records
 */
function dbCount($table, $filters = []) {
    try {
        $sql = "SELECT COUNT(*) as count FROM `$table`";
        $params = [];
        
        if (!empty($filters)) {
            $where = [];
            foreach ($filters as $col => $val) {
                $where[] = "`$col` = ?";
                $params[] = $val;
            }
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    } catch (PDOException $e) {
        error_log("dbCount error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Query phức tạp với điều kiện
 */
function dbQuery($table, $queryString, $select = '*') {
    try {
        $sql = "SELECT $select FROM `$table`";
        $params = [];
        $where = [];
        
        parse_str($queryString, $parts);
        
        $orderBy = null;
        $limit = null;
        
        foreach ($parts as $key => $val) {
            if ($key === 'order') {
                $orderBy = preg_replace('/\.desc$/', ' DESC', $val);
                $orderBy = preg_replace('/\.asc$/', ' ASC', $orderBy);
                continue;
            }
            if ($key === 'limit') {
                $limit = (int)$val;
                continue;
            }
            
            if (preg_match('/^(eq|neq|gt|gte|lt|lte|like|ilike|in|is)\.(.*)$/', $val, $m)) {
                $op = $m[1];
                $v = $m[2];
                
                switch ($op) {
                    case 'eq':
                        $where[] = "`$key` = ?";
                        $params[] = $v;
                        break;
                    case 'neq':
                        $where[] = "`$key` != ?";
                        $params[] = $v;
                        break;
                    case 'gt':
                        $where[] = "`$key` > ?";
                        $params[] = $v;
                        break;
                    case 'gte':
                        $where[] = "`$key` >= ?";
                        $params[] = $v;
                        break;
                    case 'lt':
                        $where[] = "`$key` < ?";
                        $params[] = $v;
                        break;
                    case 'lte':
                        $where[] = "`$key` <= ?";
                        $params[] = $v;
                        break;
                    case 'like':
                    case 'ilike':
                        $where[] = "`$key` LIKE ?";
                        $params[] = str_replace('*', '%', $v);
                        break;
                    case 'in':
                        $values = explode(',', trim($v, '()'));
                        $placeholders = implode(',', array_fill(0, count($values), '?'));
                        $where[] = "`$key` IN ($placeholders)";
                        $params = array_merge($params, $values);
                        break;
                    case 'is':
                        if ($v === 'null') {
                            $where[] = "`$key` IS NULL";
                        }
                        break;
                }
            }
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        if ($orderBy) {
            $sql .= " ORDER BY $orderBy";
        }
        if ($limit) {
            $sql .= " LIMIT $limit";
        }
        
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("dbQuery error: " . $e->getMessage());
        return [];
    }
}

// ============================================================
// ALIAS (Tương thích code cũ dùng tên supabase)
// ============================================================
function supabaseGetOne($t, $c, $v) { return dbGetOne($t, $c, $v); }
function supabaseGetMany($t, $f=[], $s='*', $o=null, $l=null) { return dbGetMany($t, $f, $s, $o, $l); }
function supabaseGetAll($t, $s='*', $o=null, $l=null) { return dbGetAll($t, $s, $o, $l); }
function supabaseInsert($t, $d) { return dbInsert($t, $d); }
function supabaseUpdate($t, $c, $v, $d) { return dbUpdate($t, $c, $v, $d); }
function supabaseDelete($t, $c, $v) { return dbDelete($t, $c, $v); }
function supabaseCount($t, $f=[]) { return dbCount($t, $f); }
function supabaseQuery($t, $q, $s='*') { return dbQuery($t, $q, $s); }
