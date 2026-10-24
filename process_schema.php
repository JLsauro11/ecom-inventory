<?php
function process_ready(){try{return setting('process_version','')==='5.2';}catch(Throwable $e){return false;}}
function migrate_process_controls(){
 $pdo=db();global $CFG;
 $sql=file_get_contents(__DIR__.'/process_schema.sql');foreach(explode(';',$sql) as $s)if(trim($s)!=='')$pdo->exec($s);
 $columns=['platform_transfers'=>['reversed_by_request'=>'BIGINT NULL'],'nonsellable_outflows'=>['reversed_by_request'=>'BIGINT NULL'],'process_reconciliations'=>['legacy_batch_id'=>'BIGINT NULL'],'inventory_count_batches'=>['count_date'=>'DATE NULL'],'inventory_count_lines'=>['actual_good'=>'INT NULL','actual_damaged'=>'INT NULL','good_variance'=>'INT NULL','damaged_variance'=>'INT NULL'],'outflows'=>['reversed_by_request'=>'BIGINT NULL'],'inflows'=>['reversed_by_request'=>'BIGINT NULL'],'non_sellable'=>['reversed_by_request'=>'BIGINT NULL'],'inflow_batches'=>['file_hash'=>'CHAR(64) NULL'],'return_batches'=>['file_hash'=>'CHAR(64) NULL'],'posted_returns'=>['reversed'=>'TINYINT NOT NULL DEFAULT 0','inflow_id'=>'BIGINT NULL','non_sellable_id'=>'BIGINT NULL']];
 foreach($columns as $table=>$cols)foreach($cols as $name=>$definition){$q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?');$q->execute([$CFG['db_name'],$table,$name]);if(!$q->fetchColumn())$pdo->exec("ALTER TABLE `$table` ADD COLUMN `$name` $definition");}
 $pdo->exec('UPDATE inventory_count_batches SET count_date=DATE(created_at) WHERE count_date IS NULL');
 $index=$pdo->query("SHOW INDEX FROM transaction_reversals WHERE Key_name='source_type' AND Non_unique=0")->fetch();if($index)$pdo->exec('ALTER TABLE transaction_reversals DROP INDEX source_type, ADD INDEX reversal_source(source_type,source_id)');
 set_setting('process_version','5.2');
 $pdo->exec("CREATE TABLE IF NOT EXISTS inflow_receipt_claims(id BIGINT AUTO_INCREMENT PRIMARY KEY,claim_key CHAR(64) NOT NULL,batch_id BIGINT NOT NULL,active TINYINT NOT NULL DEFAULT 1,UNIQUE(claim_key,active),INDEX(batch_id))");
 foreach($pdo->query("SELECT id,attachment_path FROM inflow_batches WHERE file_hash IS NULL AND attachment_path IS NOT NULL AND batch_type IN ('Receipt PDF','Excel Upload')") as $b){$base=realpath(__DIR__.'/uploads');$path=realpath(__DIR__.'/'.$b['attachment_path']);if($base&&$path&&str_starts_with(str_replace('\\','/',$path),str_replace('\\','/',$base).'/')&&is_file($path))$pdo->prepare('UPDATE inflow_batches SET file_hash=? WHERE id=?')->execute([hash_file('sha256',$path),$b['id']]);}
 set_setting('inflow_controls_version','5.2.1');migrate_complete_controls();
}
function process_date($value){$d=DateTime::createFromFormat('!Y-m-d',(string)$value);if(!$d||$d->format('Y-m-d')!==$value||$value>date('Y-m-d'))throw new Exception('Use a valid transaction date, not a future date.');return $value;}
function process_transaction(callable $f){$pdo=db();if($pdo->inTransaction())return $f();$pdo->beginTransaction();try{$v=$f();$pdo->commit();return $v;}catch(Throwable $e){$pdo->rollBack();throw $e;}}
function process_reason($value){$value=trim((string)$value);if(strlen($value)<3||strlen($value)>2000)throw new Exception('Enter a clear reason (3 to 2000 characters).');return $value;}
