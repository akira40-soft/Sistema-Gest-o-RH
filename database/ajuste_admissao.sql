ALTER TABLE funcionarios
MODIFY COLUMN tipo_contrato ENUM(
    'efetivo',
    'determinado',
    'estagio_curricular',
    'estagio_profissional',
    'voluntariado',
    'prestacao_servicos'
) DEFAULT 'efetivo';

ALTER TABLE candidatos
ADD COLUMN arquivo_bi VARCHAR(255) AFTER curriculo_arquivo;

ALTER TABLE candidatos
ADD COLUMN arquivo_certificado VARCHAR(255) AFTER arquivo_bi;

ALTER TABLE candidatos
ADD COLUMN tipo_candidatura ENUM(
    'emprego',
    'estagio',
    'voluntariado'
) DEFAULT 'emprego';