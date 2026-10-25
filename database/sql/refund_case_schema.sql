CREATE TABLE fin3_refund_cases_qa (
  operation_key CHAR(64) NOT NULL,
  order_id INT NOT NULL,
  actor_id INT NOT NULL,
  status VARCHAR(24) NOT NULL,
  requested_amount DECIMAL(10,2) NOT NULL,
  source_sha256 CHAR(64) NOT NULL,
  review_sha256 CHAR(64) NOT NULL,
  request_sha256 CHAR(64) NOT NULL,
  reason VARCHAR(500) NOT NULL,
  report_json LONGTEXT NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (operation_key),
  UNIQUE KEY uq_refund_case_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='GuinchaFacil R11BE QA refund cases v1';
