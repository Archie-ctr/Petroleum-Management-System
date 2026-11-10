UPDATE users AS u
JOIN branches AS b ON b.id = u.branch_id
SET u.employee_id = CONCAT(
    COALESCE(NULLIF(UPPER(LEFT(TRIM(b.branch_name), 1)), ''), 'B'),
    '-',
    YEAR(u.created_at),
    '-',
    LPAD(u.id, 6, '0')
)
WHERE u.role <> 'admin'
  AND (u.employee_id IS NULL OR u.employee_id = '')
  AND u.branch_id IS NOT NULL;
