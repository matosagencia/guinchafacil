<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__.'/../config.php'; require __DIR__.'/../src/Services/Logger.php';
$p=getPDO(); $run='perfil-normalizacao-'.date('YmdHis'); $p->beginTransaction();
try {
 $ids=$p->query("SELECT e.id FROM especialistas e JOIN guinchos g ON g.usuario_id=e.usuario_id")->fetchAll(PDO::FETCH_COLUMN);
 $removed=0; if($ids){$in=implode(',',array_fill(0,count($ids),'?')); $q=$p->prepare("DELETE FROM especialista_servicos WHERE especialista_id IN ($in)");$q->execute(array_map('intval',$ids));$removed=$q->rowCount();$p->prepare("UPDATE especialistas SET aprovado=0,disponivel=0 WHERE id IN ($in)")->execute(array_map('intval',$ids));}
 $email='especialista.qa.canonico@guinchafacil.com'; $q=$p->prepare('SELECT id FROM usuarios WHERE email=?');$q->execute([$email]);$uid=(int)$q->fetchColumn();
 if(!$uid){$p->prepare("INSERT INTO usuarios (nome,email,senha_hash,telefone,cpf,tipo,ativo) VALUES (?,?,?,?,?,'especialista',1)")->execute(['Especialista QA Canônico',$email,password_hash('Teste@2026!Canonico',PASSWORD_BCRYPT),'21999994000','98765432100']);$uid=(int)$p->lastInsertId();} else {$p->prepare("UPDATE usuarios SET tipo='especialista',ativo=1,senha_hash=? WHERE id=?")->execute([password_hash('Teste@2026!Canonico',PASSWORD_BCRYPT),$uid]);}
 $q=$p->prepare('SELECT id FROM especialistas WHERE usuario_id=?');$q->execute([$uid]);$eid=(int)$q->fetchColumn(); if(!$eid){$p->prepare("INSERT INTO especialistas (usuario_id,nome_profissional,cpf_cnpj,documento_tipo,chave_pix,chave_pix_tipo,aprovado,disponivel,lat_atual,lng_atual) VALUES (?,?,?,?,?,'aleatoria',1,1,-22.90,-43.10)")->execute([$uid,'Especialista QA Canônico','98765432100','CPF','qa-canonico-chave']);$eid=(int)$p->lastInsertId();}
 $p->commit(); Logger::log(Logger::LEVEL_WARN,'qa_normalizar_perfis','run','qa',$run,['guinchos_normalizados'=>count($ids),'capacidades_removidas'=>$removed,'especialista_qa_id'=>$eid]); echo json_encode(['ok'=>true,'run_id'=>$run,'guinchos_normalizados'=>count($ids),'capacidades_removidas'=>$removed,'email'=>$email,'senha'=>'Teste@2026!Canonico'],JSON_UNESCAPED_UNICODE).PHP_EOL;
} catch(Throwable $e){if($p->inTransaction())$p->rollBack();Logger::exception('qa_normalizar_perfis','run','qa',$e,['run_id'=>$run]);fwrite(STDERR,"[ERRO] {$e->getMessage()}\n");exit(1);}
