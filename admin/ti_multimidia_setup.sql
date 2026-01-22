ALTER TABLE ti_artigos 
ADD COLUMN video_url VARCHAR(255) NULL AFTER conteudo,
ADD COLUMN anexo_path VARCHAR(255) NULL AFTER video_url,
ADD COLUMN imagem_path VARCHAR(255) NULL AFTER anexo_path;
