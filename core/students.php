<?php
/**
 * core/students.php
 * Centralized student directory queries and helpers.
 */

/**
 * Fetches paginated student directory rows with optional search, class, and status filters.
 *
 * @return array{students: array<int, array<string, mixed>>, total: int, pages: int, page: int}
 */
function query_student_directory(
    PDO $pdo,
    string $search = '',
    ?int $classId = null,
    string $status = '',
    int $page = 1,
    int $perPage = 50
): array {
    $page = max(1, $page);
    $perPage = max(1, min(100, $perPage));
    $offset = ($page - 1) * $perPage;

    $where = [];
    $params = [];

    $search = trim($search);
    if ($search !== '') {
        $where[] = "(s.admission_number LIKE :q1 OR s.first_name LIKE :q2 OR s.last_name LIKE :q3 OR CONCAT(s.first_name, ' ', s.last_name) LIKE :q4)";
        $like = '%' . $search . '%';
        $params['q1'] = $like;
        $params['q2'] = $like;
        $params['q3'] = $like;
        $params['q4'] = $like;
    }

    if ($classId !== null && $classId > 0) {
        $where[] = 'se.class_id = :class_id';
        $params['class_id'] = $classId;
    }

    $allowedStatuses = ['ACTIVE', 'GRADUATED', 'WITHDRAWN', 'SUSPENDED', 'INACTIVE'];
    $statusUpper = strtoupper(trim($status));
    if ($statusUpper !== '' && in_array($statusUpper, $allowedStatuses, true)) {
        $where[] = 's.status = :status';
        $params['status'] = $statusUpper;
    }

    $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

    // Join latest active enrollment without per-row correlated subquery
    $baseFrom = "
        FROM students s
        LEFT JOIN (
            SELECT student_id, MAX(id) AS latest_enrollment_id
            FROM student_enrollments
            WHERE status = 'ACTIVE'
            GROUP BY student_id
        ) le ON le.student_id = s.id
        LEFT JOIN student_enrollments se ON se.id = le.latest_enrollment_id
        LEFT JOIN classes c ON c.id = se.class_id
        {$whereSql}
    ";

    $countStmt = $pdo->prepare("SELECT COUNT(*) {$baseFrom}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $pages = max(1, (int)ceil($total / $perPage));
    if ($page > $pages) {
        $page = $pages;
        $offset = ($page - 1) * $perPage;
    }

    $dataSql = "
        SELECT s.*, c.name AS current_class, se.class_id AS current_class_id
        {$baseFrom}
        ORDER BY s.last_name ASC, s.first_name ASC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($dataSql);
    foreach ($params as $key => $val) {
        $stmt->bindValue(':' . $key, $val);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'students' => $stmt->fetchAll(),
        'total'    => $total,
        'pages'    => $pages,
        'page'     => $page,
    ];
}
