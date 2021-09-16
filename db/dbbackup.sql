CREATE TABLE `clients` (
  `id` int(10) NOT NULL,
  `name` varchar(50) CHARACTER SET latin1 NOT NULL,
  `email` varchar(50) CHARACTER SET latin1 NOT NULL,
  `phone` varchar(50) CHARACTER SET latin1 DEFAULT NULL,
  `is_billing` int(1) NOT NULL DEFAULT '1',
  `balance` varchar(20) CHARACTER SET latin1 DEFAULT NULL,
  `credit` varchar(20) CHARACTER SET latin1 DEFAULT NULL,
  `out_standing` varchar(10) CHARACTER SET latin1 DEFAULT NULL,
  `currency` varchar(10) CHARACTER SET latin1 NOT NULL,
  `organization` varchar(100) CHARACTER SET latin1 DEFAULT NULL,
  `created_at` varchar(100) CHARACTER SET latin1 NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `invoices` (
  `id` int(10) NOT NULL,
  `client_id` int(10) NOT NULL,
  `number` varchar(100) CHARACTER SET latin1 NOT NULL,
  `subtotal` varchar(50) CHARACTER SET latin1 NOT NULL,
  `currency` varchar(10) CHARACTER SET latin1 NOT NULL,
  `status` int(1) NOT NULL,
  `invocie_templete` int(5) NOT NULL,
  `payment_covers` text CHARACTER SET latin1,
  `due_date` varchar(50) CHARACTER SET latin1 DEFAULT NULL,
  `created_at` varchar(50) CHARACTER SET latin1 NOT NULL,
  PRIMARY KEY (`id`,`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `payment_history` (
  `uuid` varchar(200) NOT NULL,
  `trans_id` varchar(50) NOT NULL,
  `client_id` varchar(50) NOT NULL,
  `payment_method_id` varchar(50) NOT NULL,
  `amount` varchar(50) NOT NULL,
  `invoice_data` text,
  `currency` varchar(50) NOT NULL,
  `note` text,
  `receipt_sent_date` varchar(100) DEFAULT NULL,
  `created_date` varchar(100) NOT NULL,
  PRIMARY KEY (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `payment_methods` (
  `id` varchar(100) CHARACTER SET latin1 NOT NULL,
  `name` varchar(50) CHARACTER SET latin1 NOT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `file_data` (
  `trans_id` bigint(10) NOT NULL,
  `file_name` varchar(200) NOT NULL,
  `file_create_date` date NOT NULL,
  PRIMARY KEY (`trans_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4



