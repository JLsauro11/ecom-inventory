<?php
function migrate_v5(){
 $pdo=db();global $CFG;
 // CREATE IF NOT EXISTS never replaces a table; existing rows and keys remain.
 foreach(explode(';',file_get_contents(__DIR__.'/schema.sql')) as $statement){if(trim($statement)!=='')$pdo->exec($statement);}
 $columns=[
  'products'=>['main_category'=>'VARCHAR(100) NULL','low_stock_threshold'=>'INT DEFAULT 10','variant'=>'VARCHAR(150) NULL'],
  'users'=>['active'=>'TINYINT DEFAULT 1','must_change_password'=>'TINYINT DEFAULT 0','updated_at'=>'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP','approval_status'=>"VARCHAR(20) NOT NULL DEFAULT 'Approved'",'approved_by'=>'INT NULL','approved_at'=>'DATETIME NULL'],
  'inflow_batches'=>['result_json'=>'LONGTEXT NULL','error_json'=>'LONGTEXT NULL','error_count'=>'INT DEFAULT 0','transfer_ref'=>'VARCHAR(100) NULL'],
  'import_batches'=>['result_json'=>'LONGTEXT NULL','stored_path'=>'VARCHAR(255) NULL','generated_path'=>'VARCHAR(255) NULL','status'=>"VARCHAR(30) DEFAULT 'Successful'",'units'=>'INT DEFAULT 0','error_json'=>'LONGTEXT NULL'],
  'unmapped_imports'=>['external_id'=>'VARCHAR(120) NULL'],
  'platform_aliases'=>['external_id'=>'VARCHAR(120) NULL'],
  'outflows'=>['dedupe_key'=>'CHAR(64) NULL'],
 ];
 foreach($columns as $table=>$cols)foreach($cols as $column=>$definition){
  $st=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?');$st->execute([$CFG['db_name'],$table,$column]);
  if(!(int)$st->fetchColumn())$pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
 }
 $key=$pdo->prepare("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='outflows' AND COLUMN_NAME='dedupe_key'");$key->execute([$CFG['db_name']]);if((int)$key->fetchColumn()<64)$pdo->exec('ALTER TABLE outflows MODIFY COLUMN dedupe_key CHAR(64) NULL');
 $pdo->exec("CREATE TABLE IF NOT EXISTS threshold_batches(id BIGINT AUTO_INCREMENT PRIMARY KEY,batch_ref VARCHAR(80) UNIQUE,filename VARCHAR(255),status VARCHAR(40),row_count INT DEFAULT 0,error_json LONGTEXT NULL,user_id INT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,confirmed_at TIMESTAMP NULL)");
 $pdo->exec("CREATE TABLE IF NOT EXISTS threshold_lines(id BIGINT AUTO_INCREMENT PRIMARY KEY,batch_id BIGINT,product_id INT,master_sku VARCHAR(60),product_name VARCHAR(255),old_threshold INT,new_threshold INT,INDEX(batch_id),INDEX(product_id))");
 // Explicit links only for new releases; legacy releases remain unlinked, never guessed.
 $pdo->exec("CREATE TABLE IF NOT EXISTS nonsellable_release_links(release_id BIGINT,non_sellable_id BIGINT,qty INT NOT NULL,PRIMARY KEY(release_id,non_sellable_id),INDEX(non_sellable_id))");
 set_setting('schema_version','5');
 set_setting('workflow_version','5.1');
 migrate_process_controls();
}
