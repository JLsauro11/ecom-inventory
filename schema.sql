CREATE TABLE IF NOT EXISTS inventory_count_batches(
 id BIGINT AUTO_INCREMENT PRIMARY KEY,batch_ref VARCHAR(100) UNIQUE,status VARCHAR(30) NOT NULL,filter_json LONGTEXT,filename VARCHAR(255),error_json LONGTEXT,created_by INT,created_name VARCHAR(120),created_role VARCHAR(40),reviewed_by INT,reviewed_name VARCHAR(120),reviewed_role VARCHAR(40),reviewed_at DATETIME,approved_by INT,approved_name VARCHAR(120),approved_role VARCHAR(40),applied_at DATETIME,reason TEXT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS inventory_count_lines(
 id BIGINT AUTO_INCREMENT PRIMARY KEY,batch_id BIGINT NOT NULL,product_id INT NOT NULL,master_sku VARCHAR(60),product_name VARCHAR(255),on_hand INT NOT NULL,sellable_on_hand INT NOT NULL,nonsellable_on_hand INT NOT NULL,stock_signature CHAR(64) NOT NULL,actual_count INT NULL,variance INT NULL,remarks TEXT,UNIQUE(batch_id,product_id),INDEX(batch_id)
);
CREATE TABLE IF NOT EXISTS users(
 id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(120),username VARCHAR(80) UNIQUE,password_hash VARCHAR(255),role VARCHAR(40) DEFAULT 'Viewer / Management',active TINYINT DEFAULT 1,must_change_password TINYINT DEFAULT 0,approval_status VARCHAR(20) NOT NULL DEFAULT 'Approved',approved_by INT NULL,approved_at DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS settings(key_name VARCHAR(100) PRIMARY KEY,value_text TEXT);
CREATE TABLE IF NOT EXISTS products(
 id INT AUTO_INCREMENT PRIMARY KEY,master_sku VARCHAR(60) UNIQUE NULL,name VARCHAR(255),normalized_name VARCHAR(255),brand VARCHAR(80),category VARCHAR(100),main_category VARCHAR(100) NULL,variant VARCHAR(150) NULL,application VARCHAR(180) NULL,base_uom VARCHAR(20) DEFAULT 'pc',low_stock_threshold INT DEFAULT 10,active TINYINT DEFAULT 1,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(normalized_name),INDEX(brand),INDEX(category)
);
CREATE TABLE IF NOT EXISTS product_audit(
 id BIGINT AUTO_INCREMENT PRIMARY KEY,product_id INT,old_name VARCHAR(255),new_name VARCHAR(255),user_id INT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS beginning_balances(
 id INT AUTO_INCREMENT PRIMARY KEY,product_id INT UNIQUE,qty INT NOT NULL DEFAULT 0,locked TINYINT DEFAULT 0,updated_by INT NULL,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,FOREIGN KEY(product_id) REFERENCES products(id)
);
CREATE TABLE IF NOT EXISTS inflow_batches(
 id BIGINT AUTO_INCREMENT PRIMARY KEY,batch_ref VARCHAR(80) UNIQUE,batch_type VARCHAR(40) DEFAULT 'Manual',source_name VARCHAR(180),reference_no VARCHAR(100),transfer_ref VARCHAR(100),txn_date DATE,attachment_path VARCHAR(255),original_filename VARCHAR(255),status VARCHAR(30) DEFAULT 'Confirmed',total_qty INT DEFAULT 0,error_count INT DEFAULT 0,error_json LONGTEXT NULL,result_json LONGTEXT NULL,user_id INT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS inflows(
 id BIGINT AUTO_INCREMENT PRIMARY KEY,batch_id BIGINT NULL,txn_date DATE,reference_no VARCHAR(100),source_name VARCHAR(180),source_platform VARCHAR(40) NULL,product_id INT,source_qty DECIMAL(12,2) DEFAULT 0,source_uom VARCHAR(40) DEFAULT 'pc',conversion_factor INT DEFAULT 1,qty INT,type VARCHAR(80),remarks TEXT,user_id INT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(product_id) REFERENCES products(id),INDEX(txn_date),INDEX(reference_no)
);
CREATE TABLE IF NOT EXISTS outflows(
 id BIGINT AUTO_INCREMENT PRIMARY KEY,txn_date DATE,platform VARCHAR(40),order_id VARCHAR(100),product_id INT,source_item VARCHAR(255),qty INT,type VARCHAR(80),import_batch_id BIGINT NULL,dedupe_key CHAR(64) UNIQUE NULL,remarks TEXT,user_id INT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(product_id) REFERENCES products(id),INDEX(txn_date),INDEX(platform),INDEX(order_id)
);
CREATE TABLE IF NOT EXISTS non_sellable(
 id BIGINT AUTO_INCREMENT PRIMARY KEY,txn_date DATE,platform VARCHAR(40),order_id VARCHAR(100),expected_product_id INT NULL,product_id INT,qty INT,condition_type VARCHAR(40),action_type VARCHAR(60),status VARCHAR(40) DEFAULT 'For Review',origin VARCHAR(40) DEFAULT 'Customer Return',sellable_deducted TINYINT DEFAULT 0,remarks TEXT,user_id INT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(product_id) REFERENCES products(id),INDEX(product_id),INDEX(status)
);
CREATE TABLE IF NOT EXISTS nonsellable_outflows(
 id BIGINT AUTO_INCREMENT PRIMARY KEY,txn_date DATE,product_id INT,qty INT,type VARCHAR(60),reference_no VARCHAR(100),remarks TEXT,user_id INT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(product_id) REFERENCES products(id)
);
CREATE TABLE IF NOT EXISTS platform_aliases(
 id BIGINT AUTO_INCREMENT PRIMARY KEY,platform VARCHAR(40),external_id VARCHAR(120) NULL,alias VARCHAR(255),normalized_alias VARCHAR(255),product_id INT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE(platform,normalized_alias),INDEX(platform,external_id),FOREIGN KEY(product_id) REFERENCES products(id)
);
CREATE TABLE IF NOT EXISTS platform_stock(
 id BIGINT AUTO_INCREMENT PRIMARY KEY,platform VARCHAR(40),product_id INT,qty INT DEFAULT 0,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE(platform,product_id),FOREIGN KEY(product_id) REFERENCES products(id)
);
CREATE TABLE IF NOT EXISTS allocation_rules(
 id BIGINT AUTO_INCREMENT PRIMARY KEY,product_id INT UNIQUE NULL,lazada_pct DECIMAL(6,2) DEFAULT 20,shopee_pct DECIMAL(6,2) DEFAULT 40,tiktok_pct DECIMAL(6,2) DEFAULT 40,updated_by INT,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS allocation_batches(
 id BIGINT AUTO_INCREMENT PRIMARY KEY,batch_ref VARCHAR(80) UNIQUE,mode VARCHAR(30),remarks TEXT,user_id INT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS allocation_lines(
 id BIGINT AUTO_INCREMENT PRIMARY KEY,batch_id BIGINT,product_id INT,sellable_qty INT,lazada_qty INT,shopee_qty INT,tiktok_qty INT,unallocated_qty INT,FOREIGN KEY(product_id) REFERENCES products(id),INDEX(batch_id)
);
CREATE TABLE IF NOT EXISTS platform_transfers(
 id BIGINT AUTO_INCREMENT PRIMARY KEY,transfer_ref VARCHAR(80) UNIQUE,txn_date DATE,product_id INT,from_platform VARCHAR(40),to_platform VARCHAR(40),qty INT,reason VARCHAR(255),user_id INT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(product_id) REFERENCES products(id)
);
CREATE TABLE IF NOT EXISTS import_batches(
 id BIGINT AUTO_INCREMENT PRIMARY KEY,platform VARCHAR(40),import_type VARCHAR(40) DEFAULT 'Orders',filename VARCHAR(255),stored_path VARCHAR(255) NULL,generated_path VARCHAR(255) NULL,status VARCHAR(30) DEFAULT 'Successful',rows_read INT DEFAULT 0,units INT DEFAULT 0,imported_count INT DEFAULT 0,unmapped_count INT DEFAULT 0,duplicate_count INT DEFAULT 0,error_count INT DEFAULT 0,error_json LONGTEXT NULL,result_json LONGTEXT NULL,user_id INT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS unmapped_imports(
 id BIGINT AUTO_INCREMENT PRIMARY KEY,batch_id BIGINT,platform VARCHAR(40),txn_date DATE,order_id VARCHAR(100),external_id VARCHAR(120) NULL,source_item VARCHAR(255),normalized_item VARCHAR(255),qty INT,status VARCHAR(30) DEFAULT 'Pending',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(platform,normalized_item)
);
CREATE TABLE IF NOT EXISTS reconciliation_batches(id BIGINT AUTO_INCREMENT PRIMARY KEY,filename VARCHAR(255),created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,user_id INT);
CREATE TABLE IF NOT EXISTS reconciliations(id BIGINT AUTO_INCREMENT PRIMARY KEY,batch_id BIGINT,product_id INT,netsuite_qty INT,internal_sellable INT,nonsellable INT,physical_total INT,variance INT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(product_id) REFERENCES products(id));
CREATE TABLE IF NOT EXISTS activity_log(
 id BIGINT AUTO_INCREMENT PRIMARY KEY,user_id INT,action_type VARCHAR(80),entity_type VARCHAR(80),entity_ref VARCHAR(120),details TEXT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(created_at)
);

CREATE TABLE IF NOT EXISTS brands(
 id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100) UNIQUE,active TINYINT DEFAULT 1,sort_order INT DEFAULT 100,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS categories(
 id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(120) UNIQUE,active TINYINT DEFAULT 1,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS receipt_staging_lines(
 id BIGINT AUTO_INCREMENT PRIMARY KEY,batch_id BIGINT,line_no INT,raw_item VARCHAR(255),product_id INT NULL,source_qty DECIMAL(12,2) DEFAULT 0,source_uom VARCHAR(40),conversion_factor INT NULL,final_qty INT NULL,validation_status VARCHAR(40) DEFAULT 'Unmapped',validation_message VARCHAR(255) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(batch_id),INDEX(product_id)
);
CREATE TABLE IF NOT EXISTS receipt_aliases(id BIGINT AUTO_INCREMENT PRIMARY KEY,normalized_alias VARCHAR(255) UNIQUE,alias VARCHAR(255),product_id INT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(product_id));
CREATE TABLE IF NOT EXISTS allocation_override_history(
 id BIGINT AUTO_INCREMENT PRIMARY KEY,history_ref VARCHAR(80) UNIQUE,product_id INT,old_lazada DECIMAL(6,2),old_shopee DECIMAL(6,2),old_tiktok DECIMAL(6,2),new_lazada DECIMAL(6,2),new_shopee DECIMAL(6,2),new_tiktok DECIMAL(6,2),sellable_qty INT,old_lazada_qty INT,old_shopee_qty INT,old_tiktok_qty INT,new_lazada_qty INT,new_shopee_qty INT,new_tiktok_qty INT,unallocated_qty INT,reason VARCHAR(255),user_id INT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(product_id)
);
