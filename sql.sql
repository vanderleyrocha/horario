SELECT id, nome, nome_abreviado as codigo, 'integral' AS turno, 40 AS numero_alunos, 2026 as ano, 1 AS ativa, CURRENT_TIMESTAMP AS created_at
FROM `turmas` 
WHERE escola_id = 12011517 AND ano = 2025

SELECT DISTINCT id, nome, email, 28 AS carga_horaria_maxima, 1 AS ativo, CURRENT_TIMESTAMP as created_at
FROM servidores
WHERE EXISTS (SELECT 1 FROM disciplina_professor dp JOIN professor_turma pt ON pt.disciplina_professor_id = dp.id
             WHERE dp.professor_id = servidores.id AND dp.escola_id = 12011517 AND dp.ano = 2025 and dp.deleted_at IS NULL AND pt.deleted_at IS NULL)

SELECT 1 AS horario_id, dp.professor_id, dp.disciplina_id, pt.turma_id, 1 AS aulas_semana, 'simples' AS tipo, 2 AS aulas_consecutivas, 2 AS max_aulas_dia,
0 AS min_intervalo_dias, 'qualquer' AS preferencia_periodo, 1 AS ativa
FROM professor_turma pt JOIN disciplina_professor dp ON dp.id = pt.disciplina_professor_id
WHERE dp.ano = 2025 AND dp.escola_id = 12011517 AND dp.deleted_at IS NULL AND pt.deleted_at IS NULL AND dp.disciplina_id != 19 AND NOT pt.turma_id IS NULL
ORDER BY pt.turma_id, dp.disciplina_id

SELECT disciplina_id, turma_id,
(SELECT ch_semanal 
 	FROM disciplina_serie ds
     WHERE ds.escola_id = 12011517 AND ds.disciplina_id = a.disciplina_id AND ds.ano = 2025
 		AND ds.serie_id = (SELECT t.serie FROM turmas t where t.id = a.turma_id)
) AS aulas_semana
FROM aulas a
WHERE 1

UPDATE aulas
SET aulas_semana =
(SELECT ch_semanal 
 	FROM disciplina_serie ds
     WHERE ds.escola_id = 12011517 AND ds.disciplina_id = aulas.disciplina_id AND ds.ano = 2025
 		AND ds.serie_id = (SELECT t.serie FROM turmas t where t.id = aulas.turma_id)
)
WHERE professor_id != 747 AND disciplina_id != 14

SELECT turma_id, dia_semana, tempo, count(*)
FROM alocacoes 
WHERE horario_id = 1
GROUP BY turma_id, dia_semana, tempo
HAVING count(*) > 1
ORDER BY turma_id, dia_semana, tempo

