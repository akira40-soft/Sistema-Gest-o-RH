USE farmacia_gingongo_rh;

-- Vincular Isaac (usuario_id=1) ao funcionário id=1
UPDATE funcionarios SET usuario_id = 1 WHERE id = 1 AND nome_completo LIKE '%Isaac%';

-- Vincular Ilda (usuario_id=5) ao funcionário id=2
UPDATE funcionarios SET usuario_id = 5 WHERE id = 2 AND nome_completo LIKE '%Ilda%';

-- Vincular Jardel (usuario_id=4) ao funcionário id=3
UPDATE funcionarios SET usuario_id = 4 WHERE id = 3 AND nome_completo LIKE '%Jardel%';

-- Verificar resultado
SELECT f.id, f.nome_completo, f.usuario_id, u.username
FROM funcionarios f
LEFT JOIN usuarios u ON f.usuario_id = u.id;
